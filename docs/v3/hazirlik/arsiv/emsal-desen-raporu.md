# Görev #12 — Emsal Desen Araştırması

**Son kontrol:** 23 Ağustos 2026  
**Kapsam:** 5 MV3 yakalama eklentisi, 3 PHP/DB/cron kuyruk emsali, 2 çeviri hattı emsali  
**Karar sınırı:** Bu belge kod, bağımlılık veya entegrasyon önermez. Açık projelerden yalnız davranış ve işletim dersi çıkarır; seçim PM'ye aittir.

## Araştırma yöntemi ve okuma anahtarı

- Yalnız proje sahibinin deposu, proje dokümantasyonu ve Chrome'un resmî dokümantasyonu kanıt kabul edildi. Blog, forum ve üçüncü taraf incelemesi kullanılmadı.
- Yıldız sayısı yalnız aday bulma sinyalidir; kalite kanıtı sayılmadı. Seçimde güncel MV3 manifesti, devam eden depo faaliyeti ve resmî dağıtım izi arandı.
- Bir davranış için depoda veya dokümanda kanıt bulunamadığında “yok” denmedi; **“kanıtlanmadı”** denildi.
- “Store'dan geçmiş manifest” ifadesi, kamusal mağaza dağıtımı görülen sürüm ailesinin manifest kalıbını anlatır; aynı izinlerin TedarikApp için kabul garantisi olduğu anlamına gelmez.
- TedarikApp madde atıfları mevcut tasarımdaki şu bağlayıcı başlıklardır: eklenti **§4.1–§4.6**, arka uç kuyruğu **§13.1–§13.4**, sağlık/bozulma gözlemi **§14.1–§14.2**, eklenti testleri **E2E-EKL-15** ve **E2E-EKL-26**, çeviri kabulü **Görev #4A** ve sözlük çekirdeği **Görev #4B**.

## Yönetici özeti

| Konu | Emsallerden çıkan ortak ders | TedarikApp sonucu |
|---|---|---|
| MV3 service worker | Worker'ın ömrüne güvenilmez; iş durumu önce kalıcı depoya yazılır ve olay geldiğinde yeniden kurulabilir olmalıdır. | §4.2 çevrimdışı kuyruğu korunmalı; periyodik “uyanık tutma” güvenilirlik mekanizması sayılmamalı. |
| Seçici kırılması | Tek CSS seçicisi yerine algılama, öncelik, fallback, sürüm ve fikstür testi birlikte kullanılır. | §4.3 adaptör sözleşmesi ile §4.4 paketli seçici sürümü genişletilmeli; uzak çalıştırılabilir mantık alınmamalı. |
| İzinler | Geniş amaçlı araçlar geniş izin kullanıyor; dar amaçlı emsalde yetki kullanıcı hareketiyle isteğe bağlı yükseltiliyor. | 1688 ve panel alanlarıyla sınırlı, mümkün olan yerde isteğe bağlı host izni (§4.5) doğru yön. |
| Sayfa içi UI | İncelenen beş emsalin çoğu popup/side panel tercih ediyor; Shadow DOM kullanımı kanıtlanan ortak desen değil. | TedarikApp'in Shadow DOM izolasyonu ve E2E-EKL-26 testi korunmalı; side panel §4.6 ile uyumlu. |
| DB/cron kuyruğu | “En az bir kez” teslim, süreli sahiplik, idempotensi, kontrollü retry ve görünür hata kaydı birlikte gerekir. | §13.2 iyi bir çekirdek; lease taşması, ölü iş, adil kapasite ve replay işletimi eklenmeli. |
| Çeviri | Onaylı bellek LLM'den önce gelir; sözlük bağlamdır ama ayrıca deterministik kalite kontrolü gerekir. | Görev #4A/4B doğru temel; cache anahtarı sürümlenmeli ve kısmi sonuç doğrudan yayımlanmamalı. |

## 12A — MV3 yakalama eklentileri

### Resmî MV3 taban çizgisi

Chrome, service worker'ı hareketsizlikte sonlandırabilir ve global değişkenlerin kaybolacağını açıkça belirtir; kalıcı durum için `chrome.storage` veya IndexedDB önerir. Bu nedenle “işlem sırasında worker kapanmaz” varsayımı tasarım sözleşmesi olamaz. Chrome ayrıca mümkün olduğunda isteğe bağlı izinleri önerir. MV3'te uzaktan JavaScript/WASM yürütmek yasaktır; JSON ve CSS veri sayılır, ancak TedarikApp'in daha önce kabul edilen Store ihtiyatı gereği seçici ve dönüşüm davranışı v1.0 paketinde kalacaktır. [Service worker yaşam döngüsü](https://developer.chrome.com/docs/extensions/develop/concepts/service-workers/lifecycle), [izin bildirimi](https://developer.chrome.com/docs/extensions/develop/concepts/declare-permissions), [uzak kod kuralı](https://developer.chrome.com/docs/extensions/develop/migrate/remote-hosted-code)

### MV3-01 — Obsidian Web Clipper

**Repo ve üretim izi:** [obsidianmd/obsidian-clipper](https://github.com/obsidianmd/obsidian-clipper); depo bunu Obsidian'ın resmî Web Clipper'ı olarak tanımlar ve resmî Chrome Web Store bağlantısı verir. İncelenen [Chromium manifesti](https://github.com/obsidianmd/obsidian-clipper/blob/main/src/manifest.chrome.json) MV3, service worker, side panel, `storage`, `activeTab`, `scripting` ve geniş host erişimi içerir.

**Nasıl çözmüşler:** Ana kullanıcı akışı popup/side panelde yaşar; sayfa seçimi ve vurgular sayfa ile eklenti arasında aktarılır. Vurgular ve ayarlar kalıcı tarayıcı depolamasına yazılır, fakat TedarikApp'teki gibi sunucuya teslimi garanti eden kalıcı bir dış gönderim kuyruğu kanıtlanmadı. Seçici kırılganlığı tek katmanda bırakılmamış: genel içerik çıkarıcı, meta/OpenGraph ve Schema.org değişkenleri, kullanıcı CSS seçicileri, URL/regex/şema tabanlı şablon eşlemesi ve en üstte fallback şablonu birlikte bulunur. [Şablon seçimi](https://obsidian.md/help/web-clipper/templates), [değişken ve seçici katmanları](https://obsidian.md/help/web-clipper/variables), [kalıcı vurgu yöneticisi](https://github.com/obsidianmd/obsidian-clipper/blob/main/src/managers/highlights-manager.ts). Ana UI eklenti yüzeyindedir; incelenen kaynaklarda sayfa içi UI için Shadow DOM kanıtlanmadı.

**Bizde karşılığı var mı:** Çok katmanlı çıkarım §4.3 adaptör sözleşmesi ve §4.4 fallback/sürüm fikriyle **var**. Side panel yönü §4.6 ile **var**. Kalıcı dış teslim, bu emsalden daha güçlü biçimde §4.2 ve E2E-EKL-15'te **var**. Geniş `<all_urls>` yaklaşımı §4.5 ile **uyumsuz**.

**Alınacak ders:**

1. Kullanıcıya “bu sayfada hangi ham değişkenler görüldü?” denetim görünümü sunmak, seçici kırılmasını destek kaydına dönüştürür.
2. İçe aktarılan şablon/ayar verisini alan izin listesiyle ve toplu doğrulamayla kabul etmek, yarım ve bozuk yapılandırmayı önler.

**Alınmayacak şey:** Sunucu teslim kuyruğunun olmaması ve bütün sitelere kalıcı host erişimi TedarikApp'e taşınmamalı.

### MV3-02 — SingleFile MV3

**Repo ve üretim izi:** [gildas-lormeau/SingleFile-MV3](https://github.com/gildas-lormeau/SingleFile-MV3), SingleFile'ın MV3 sürümüdür; projenin [resmî sitesi](https://www.getsinglefile.com/) Chrome Web Store dağıtımını gösterir. [Manifest](https://github.com/gildas-lormeau/SingleFile-MV3/blob/main/manifest.json) MV3 service worker, side panel, offscreen belge ve tüm URL'lerde içerik betikleri kullanır.

**Nasıl çözmüşler:** Sekmeye bağlı durumun kalıcı ve geçici parçalarını ayırıp kalıcı parçayı tarayıcı depolamasında tutar; kapanmış sekmelerden kalan durumu temizler. DOM/Blob gibi service worker'a uygun olmayan ağır işleri gerektiğinde offscreen belgeye taşır ve büyük sayfa verisini parçalara böler. [Sekme durum yönetimi](https://github.com/gildas-lormeau/SingleFile-MV3/blob/main/src/core/bg/tabs-data.js), [offscreen işleme](https://github.com/gildas-lormeau/SingleFile-MV3/blob/main/src/core/bg/offscreen.js). Seçici kırılganlığını alan alan ürün ayrıştırarak değil, sayfanın bütününü arşivleyerek azaltır; bu yüzden TedarikApp'in kanonik ürün alanları için doğrudan seçici emsali değildir. UI daha çok eklenti sayfaları/side paneldir; Shadow DOM kanıtlanmadı.

**Bizde karşılığı var mı:** Kalıcı/geçici durum ayrımı §4.2 ile **kısmen var**; açık çöp toplama kuralı yok. Büyük medya ve kanıt verisi §13.4 ile **kısmen var**, fakat eklenti tarafında parça boyutu sözleşmesi yok. Tam sayfa yakalama, §4.1 önizleme ve §4.3 alan çıkarımının yerini tutmaz.

**Alınacak ders:**

1. Kuyruk kaydı ile yalnız açık sekmeye ait geçici çalışma durumunu ayrı yaşam döngülerine koymak; kapanmış sekme artıklarını ölçülü temizlemek.
2. Büyük ham kanıtı tek mesajda taşımak yerine boyut sınırı ve parçalı aktarım sözleşmesi koymak.

**Alınmayacak şey:** `<all_urls>`, sürekli içerik betiği ve offscreen karmaşıklığı ölçülmüş ihtiyaç olmadan alınmamalı; bütün sayfa arşivi kanonik ürün doğrulamasının yerine geçmemeli.

### MV3-03 — Zotero Connectors

**Repo ve üretim izi:** [zotero/zotero-connectors](https://github.com/zotero/zotero-connectors), Zotero'nun Chrome/Firefox/Edge/Safari bağlayıcılarının resmî deposudur. [MV3 manifesti](https://github.com/zotero/zotero-connectors/blob/master/src/browserExt/manifest-v3.json) service worker, offscreen, `storage`, `scripting`, ağ gözlem izinleri ve geniş HTTP/HTTPS host erişimi kullanır.

**Nasıl çözmüşler:** Uzun kullanıcı işlemi sırasında worker ömrünü uzatmak için sınırlı süre çalışan periyodik bir API çağrısı bulunur; bu kalıcı kuyruk değil, yaşam döngüsü workaround'udur. [MV3 keep-alive dosyası](https://github.com/zotero/zotero-connectors/blob/master/src/browserExt/keep-mv3-alive.js). Asıl güçlü desen seçici tarafındadır: URL hedef filtresiyle aday çevirmenler daraltılır, sayfa algılama çalışır, öncelik sırası uygulanır ve siteye özel çevirmen başarısızsa genel çevirmenler fallback olabilir. Çevirmen kimliği kararlıdır; fikstür testleri ve “son çevirmen hataları” görünümü bulunur. [Çevirmen mimarisi](https://www.zotero.org/support/dev/translators), [çevirmen deposu](https://github.com/zotero/translators). Sayfa enjeksiyonu ve eklenti modalları kullanılır; incelenen kaynaklarda Shadow DOM kanıtlanmadı.

**Bizde karşılığı var mı:** Algılama/çıkarma/doğrulama zinciri §4.3 ile **var**; kararlı adaptör kimliği, açık öncelik ve hata panosu §14.1'de **kısmen var**. Fikstür yaklaşımı Görev #3 ile **var**. Zotero'nun uzaktan güncellenebilen çevirmen kodu TedarikApp'in bağlayıcı §4.4 ve Store teyidiyle **uyumsuzdur**; emsal olması politika güvenliği sağlamaz.

**Alınacak ders:**

1. Her adaptör için kararlı kimlik + öncelik + genel fallback ve sürüm bazlı “son hatalar” panosu tutmak.
2. Üretim hata örneklerini gizli veriden arındırıp fikstür testine terfi ettiren kapalı döngü kurmak.

**Alınmayacak şey:** Worker'ı periyodik çağrıyla ayakta tutmak teslim garantisi sayılmamalı; uzaktan JavaScript adaptörü kesinlikle kopyalanmamalı.

### MV3-04 — Automa

**Repo ve üretim izi:** [AutomaApp/automa](https://github.com/AutomaApp/automa), aktif açık kaynak tarayıcı otomasyon eklentisidir. [Chrome manifesti](https://github.com/AutomaApp/automa/blob/main/src/manifest.chrome.json) MV3 service worker, alarm, offscreen, `storage`, `scripting`, `activeTab`, otomasyon amaçlı geniş izinler ve tüm URL içerik betikleri kullanır.

**Nasıl çözmüşler:** İş akışı tanımları, tetikleyiciler ve bazı kuyruk bilgileri kalıcı depoda; daha büyük yapılandırılmış veri IndexedDB katmanında tutulur. Başlangıçta zamanlanmış tetikleyiciler yeniden kaydedilir ve alarmlar işi uyandırır. Ancak tarayıcı başlangıcında bazı çalışma durumları özellikle temizlenir; dolayısıyla bu, başarısız dış teslimi kesin sürdüren kuyruk değildir. [Tetikleyici/başlangıç yönetimi](https://github.com/AutomaApp/automa/blob/main/src/background/BackgroundWorkflowTriggers.js), [arka plan olayları](https://github.com/AutomaApp/automa/blob/main/src/background/BackgroundEventsListeners.js), [IndexedDB depolaması](https://github.com/AutomaApp/automa/blob/main/src/db/storage.js). Seçici aracı CSS/XPath adayını sayfada anında doğrular ve bulunamama hatasını gösterir; kanıtlanan otomatik fallback zinciri yoktur. [Seçici doğrulama aracı](https://github.com/AutomaApp/automa/blob/main/src/newtab/utils/elementSelector.js). Ana UI eklenti panelidir; Shadow DOM kanıtlanmadı.

**Bizde karşılığı var mı:** Olayla yeniden kurulum §4.2'de **kısmen var**; startup recovery kabul senaryosu açık değil. Seçici sağlık gözlemi §14.1'de **var**, kaydetmeden önce canlı doğrulama **eksik**. Geniş otomasyon izinleri §4.5 ile **uyumsuz**.

**Alınacak ders:**

1. Seçici paketi yayımlanmadan önce hedef sayfada “bulundu/tekil/alan tipi uygun” doğrulamasını zorunlu yapmak.
2. Seçici ve kullanıcı ayarlarının zamanlanmış, geri yüklenebilir yerel dışa aktarımını işletim seçeneği yapmak.

**Alınmayacak şey:** Başlangıçta in-flight durumu silmek, çevrimdışı teslim kuyruğunda veri kaybıdır; `debugger`, proxy ve `<all_urls>` gibi otomasyon izinleri dar amaçlı eklentiye taşınmamalı.

### MV3-05 — Karakeep Browser Extension

**Repo ve üretim izi:** [karakeep-app/karakeep](https://github.com/karakeep-app/karakeep) içindeki [browser extension](https://github.com/karakeep-app/karakeep/tree/main/apps/browser-extension), Karakeep'in resmî yakalama yüzüdür. [Manifest](https://github.com/karakeep-app/karakeep/blob/main/apps/browser-extension/manifest.json) MV3, `storage`, `activeTab`, `scripting` ve kullanıcı hareketiyle istenen isteğe bağlı `<all_urls>` host izni kullanır.

**Nasıl çözmüşler:** Bağlantı/metin yakalama isteği service worker'dan popup'a `storage.session` üzerinden taşınır; bu tarayıcı yeniden başlatmasında kalıcı teslim kuyruğu değildir. Ana iş, URL kaydedildikten sonra sunucunun başlık/açıklama/görsel çıkarmasıyla sadeleştirilir. Kullanıcı tam sayfa istemci arşivlemeyi açarsa geniş host izni ayrıca ve kullanıcı hareketi sırasında istenir; izin sonradan kaldırılabilir. [İsteğe bağlı izin akışı](https://github.com/karakeep-app/karakeep/blob/main/apps/browser-extension/src/utils/permissions.ts), [yakalama ekranı](https://github.com/karakeep-app/karakeep/blob/main/apps/browser-extension/src/SavePage.tsx), [arka plan köprüsü](https://github.com/karakeep-app/karakeep/blob/main/apps/browser-extension/src/background/background.ts). UI popup/options sayfasıdır; sayfa içi Shadow DOM kanıtlanmadı.

**Bizde karşılığı var mı:** Kullanıcı tetiklemesi §4.1 ile **var**; host yetkisini kullanıcı hareketiyle yükseltme §4.5 ile **var/kısmi**. Sunucu zenginleştirmesi §13.1 ile **var**; ancak TedarikApp'te ürün sayfasındaki temel kanonik veriyi yalnız URL'den sonradan çıkarma yeterli değildir. `storage.session`, §4.2 kalıcılık şartını karşılamaz.

**Alınacak ders:**

1. Temel bağlantı yakalamayı düşük izinle çalıştırıp yalnız kullanıcı tam yakalamayı açarsa geniş erişim istemek.
2. Yetki yükseltmesini geri alınabilir yapmak ve ayarda mevcut izin durumunu açık göstermek.

**Alınmayacak şey:** Oturum depolamasını teslim kuyruğu gibi kullanmak ve temel ürün verisini tamamen sunucu tarafı yeniden kazımaya bırakmak.

### 12A karşılaştırma tablosu

| Emsal | SW uyku/kalıcı durum | Seçici kırılmasına yaklaşım | İzin deseni | UI izolasyonu | TedarikApp kararı |
|---|---|---|---|---|---|
| Obsidian Web Clipper | Kalıcı vurgu/ayar; dış teslim kuyruğu kanıtlanmadı | Genel çıkarıcı + meta/schema + CSS + şablon fallback | Geniş host + activeTab/scripting/storage | Side panel/popup; Shadow DOM kanıtlanmadı | Katmanlı çıkarımı al, geniş hostu alma |
| SingleFile MV3 | Kalıcı/geçici sekme durumu; offscreen ve parçalama | Tam sayfa kanıtı; alan seçici sorusunu kaçınır | Tüm URL + offscreen | Eklenti UI; Shadow DOM kanıtlanmadı | Durum ayrımı ve boyut sınırını al |
| Zotero Connectors | Sınırlı keep-alive; kalıcı dış kuyruk değil | Hedef filtresi + detect + öncelik + fallback + test | Geniş host ve ağ izinleri | Enjekte modal/eklenti UI; Shadow DOM kanıtlanmadı | Adaptör sağlık döngüsünü al; uzak kodu alma |
| Automa | Alarm + kalıcı tanım; bazı in-flight durumlar startup'ta silinir | Kullanıcının seçicisini anında doğrular | Otomasyon gereği çok geniş | Dashboard/overlay; Shadow DOM kanıtlanmadı | Preflight seçici testini al; izinleri alma |
| Karakeep | `storage.session` köprüsü; sunucu zenginleştirme | URL yakala, ağır çıkarımı sunucuya bırak | `<all_urls>` isteğe bağlı ve kullanıcı hareketli | Popup/options; Shadow DOM kanıtlanmadı | Kademeli izin desenini al; session kuyruğunu alma |

## 12B — PHP kuyruk ve çeviri hattı emsalleri

### PHPQ-01 — WooCommerce Action Scheduler

**Repo:** [woocommerce/action-scheduler](https://github.com/woocommerce/action-scheduler) · **Resmî doküman:** [Action Scheduler](https://actionscheduler.org/)

**Nasıl çözmüşler:** İşler tarih ve argümanlarıyla veritabanında tutulur; cron ve gerektiğinde asenkron loopback çalıştırıcı kuyruğu uyandırır. Çalıştırıcı küçük bir parti için benzersiz claim alır, zaman/bellek sınırına ulaşınca yeni sürece devreder; süresini aşmış claim'leri temizler. Oluşturma, başlama, tamamlama ve hata olayları ayrı tabloda görünür; eski tamamlanan ve başarısız kayıtlar için saklama politikası vardır. Büyük kuyruklarda CLI çalıştırıcı, grup/hook bazlı kapasite ve kontrollü eşzamanlılık anlatılır; doküman ayrıca yanlış paralelliğin sunucuyu ve DB kilitlerini zorlayabileceğini uyarır. [Çalışma/claim/housekeeping](https://actionscheduler.org/), [ölçek ve saklama](https://actionscheduler.org/perf/), [CLI ve grup uyarıları](https://actionscheduler.org/wp-cli/). Resmî belgede genel amaçlı otomatik üstel retry sözleşmesi kanıtlanmadı; retry/backoff iş sahibinin açık politikası olmalıdır.

**Bizde karşılığı var mı:** `locked_at`, `locked_by`, `attempts`, `available_at`, `last_error` ve idempotensi §13.2'de **var**. Parti zaman/bellek bütçesi, claim temizliği eşiği, olay günlüğü ve başarısız kayıt saklama süresi **kısmi/eksik**.

**Alınacak ders:** Worker her cron'da sınırsız boşaltma yapmamalı; süre, adet ve bellek bütçesiyle çıkmalı. Claim temizliği ile başarısız iş geçmişi ayrı işletim politikaları olmalı.

**Alınmayacak şey:** WordPress loopback mekanizması veya paketin kendisi önerilmiyor; yalnız claim, bütçe ve izlenebilirlik deseni alınır.

### PHPQ-02 — Moodle Task API

**Repo:** [moodle/moodle](https://github.com/moodle/moodle) · **Resmî doküman:** [Task API](https://moodledev.io/docs/5.0/apis/subsystems/task), [Adhoc tasks](https://moodledev.io/docs/5.0/apis/subsystems/task/adhoc)

**Nasıl çözmüşler:** Uzun işler kullanıcı isteğinden ayrılıp cron'la çalışan zamanlanmış veya tek seferlik göreve çevrilir. Hata exception ile görünür olur; ilk retry kısa gecikmeyle başlar ve ardışık hatalarda bekleme en çok 24 saate kadar büyür. Ad-hoc görevlerde deneme bütçesi sınırlandırılabilir. Aynı sınıf, bileşen, veri ve kullanıcı birleşimiyle mükerrer görev isteğe bağlı engellenebilir; yüksek değişim varsa aynı görev yeniden zamanlanabilir. Doküman, döngü içindeki tek alt öğe hatasının kalan öğeleri engellememesi gerektiğini ve kısmi başarıda işin genel durumunun bilinçli seçilmesini özellikle vurgular.

**Bizde karşılığı var mı:** `attempts` ve `available_at` §13.2'de **var**; üstel gecikmenin tavanı, deneme bütçesi ve aynı işi “yeniden zamanla ya da tekilleştir” sözleşmesi **eksik/kısmi**. Kısmi işleme sorusu çeviri hattında açık kapı bırakıyor.

**Alınacak ders:** Retry sayısı sınırsız olmamalı; geçici hata için artan bekleme ve tavan, kalıcı hata için erken durma gerekir. Çok parçalı iş, “bazıları oldu” bilgisini saklamalı ve iş sonunda doğru nihai durum üretmelidir.

**Alınmayacak şey:** Moodle görev altyapısının kendisi veya varsayılan 12 deneme değeri kopyalanmıyor; sayılar TedarikApp yük testi ve sağlayıcı davranışıyla seçilmeli.

### PHPQ-03 — Drupal Queue API

**Repo:** [drupal/drupal](https://github.com/drupal/drupal) · **Resmî doküman:** [Queue API](https://api.drupal.org/api/drupal/core%21core.api.php/group/queue/11.x), [QueueWorker davranışı](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Queue%21QueueWorkerInterface.php/function/QueueWorkerInterface%3A%3AprocessItem/11.x)

**Nasıl çözmüşler:** Tüketici işi süreli lease ile claim eder, başarıdan sonra siler; tüketici ölürse lease sonunda iş yeniden görünür olur. Doküman bunun aynı işin birden çok kez verilebileceği anlamına geldiğini açıkça kabul eder: tüketici idempotent olmalıdır. Worker normal hata ile daha sonra tekrar deneme, anında requeue, gecikmeli requeue ve uzak servis gibi kuyruk-geneli sorunlarda o cron turunda kuyruğu askıya alma davranışlarını ayırır. [Gecikmeli requeue](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Queue%21DelayedRequeueException.php/class/DelayedRequeueException/11.x), [cron kuyruk işleyişi](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cron.php/class/Cron/11.x). Çekirdekte genel max-attempt/DLQ sözleşmesi kanıtlanmadı.

**Bizde karşılığı var mı:** Lease alanları ve idempotensi §13.2'de **var**. Lease süresi dolmadan uzatma/heartbeat, sahiplik tokenı ve kuyruk-geneli devre kesici **eksik**. Geçici/kalıcı hata ayrımı §4.2'de eklenti için var; sunucu kuyruğunda aynı açıklıkta değil.

**Alınacak ders:** “At least once” sözleşmesi açık yazılmalı; başarılı yan etki ile job silme arasındaki çöküş dahil her tekrar güvenli olmalı. Sağlayıcı kapalıysa her işi ayrı ayrı yakmak yerine iş türünü geçici askıya alan devre kesici gerekir.

**Alınmayacak şey:** Lease bitince kör tekrar tek başına yeterli değildir; max deneme, dead-letter ve operatör replay'i ayrıca tasarlanmalıdır.

## Çeviri bellek + sözlük + kalite emsalleri

### TRN-01 — Weblate

**Repo:** [WeblateOrg/weblate](https://github.com/WeblateOrg/weblate) · **Resmî doküman:** [Translation Memory](https://docs.weblate.org/en/latest/admin/memory.html), [Glossary](https://docs.weblate.org/en/latest/user/glossary.html), [Automatic suggestions](https://docs.weblate.org/en/latest/admin/machine.html), [Quality checks](https://docs.weblate.org/en/latest/user/checks.html)

**Nasıl çözmüşler:** Onaylı geçmiş çeviriler proje/kullanıcı/çalışma alanı kapsamlı translation memory'de tutulur. Yüzde yüz bellek eşleşmesi makine çevirisinden önce gelir ve bu durumda sağlayıcı çağrılmaz. Bellek girdileri aktif/bekleyen durum taşır; bekleyen girdiye kalite cezası uygulanabilir ve inceleme sonrası eski çelişkili girdiler temizlenebilir. Sözlük; tercih edilen, çevrilmeyecek ve yasak terimleri ayrı anlamlarla taşır. LLM tabanlı öneriye bağlam, açıklama, yer tutucular, mevcut kalite uyarıları ve eşleşen sözlük açıklamaları verilebilir. Ancak dönen metin ayrıca deterministik kalite kontrollerinden geçer; sözlük kontrolü varsayılan olarak otomatik kapı değildir, ayrıca etkinleştirilir.

**Bizde karşılığı var mı:** Görev #4B tercih/yasak/korunacak terim çekirdeğiyle **var**. Görev #4A anlam doğruluğu, korunacaklar ve yasak hatalar kapısıyla **var**. Onaylı belleğin LLM'den önce gelmesi **kısmen var/ayrıntısız**; bellek kapsamı, aktif-bekleyen durumu ve temizleme politikası açık değil.

**Alınacak ders:** Tam eşleşmiş, onaylı çeviri sağlayıcı çağrısını atlamalı; taslak/insan onaylı sonuç aynı güven sınıfında cache'e girmemeli. Sözlük prompt bağlamı ile çıktıdaki deterministik “terim gerçekten korundu mu?” kontrolü iki ayrı katman olmalı.

**Alınmayacak şey:** Weblate'in tamamı veya puanları alınmıyor. Ayrıca translation memory, tek başına LLM istek cache'i değildir; TedarikApp cache anahtarı model/prompt/sözlük sürümünü ayrıca kapsamalıdır.

### TRN-02 — Translation Agent

**Repo:** [andrewyng/translation-agent](https://github.com/andrewyng/translation-agent)

**Nasıl çözmüşler:** Deneysel akış önce taslak çeviri üretir, sonra LLM'den eleştiri/iyileştirme önerisi alır, son adımda çeviriyi yeniden yazar. Dil varyantı, üslup ve özel terimler prompt ile yönlendirilebilir; sözlüğün prompta eklenmesi tutarlılık aracı olarak anlatılır. Proje kendisini olgun üretim yazılımı değil gösterim olarak tanımlar ve klasik BLEU ölçümünün insan tercihleriyle her zaman örtüşmediğini açıkça not eder. Kalıcı translation memory/cache, DB/cron teslimi veya bağlayıcı deterministik kalite kapısı kanıtlanmadı.

**Bizde karşılığı var mı:** İnsan incelemesine giden komşu/yoruma açık sonuç mantığı Görev #4A ile **var**. “Taslak → eleştiri → düzeltme” ayrı bir aşama olarak **yok**; gerekirse yalnız altın sette problemli sınıflar için deney olur. Sözlük Görev #4B ile **var**.

**Alınacak ders:** LLM öz-eleştirisi, düşük güvenli kayıtlar için ikinci aday üretme mekanizması olabilir; fakat kabul hakemi olmamalı. Belge/ürün bağlamı ve ülke/dil varyantı prompt sürümünün parçası olmalı.

**Alınmayacak şey:** Her metinde üç LLM çağrısı maliyet ve kota baskısı yaratır; tek kalite kanıtı olarak LLM'nin kendi eleştirisi kullanılmamalı.

### Açık proje bulgusu: tam eşleşme yok

Bu araştırmada **hafif/vendor'sız PHP + MySQL/cron kuyruğu + sürümlü LLM cache'i + terim sözlüğü + bağlayıcı kalite kapısı** bileşimini tek üretim projesinde açıkça kanıtlayan bir emsal bulunmadı. Weblate bileşimin çeviri belleği/sözlük/öneri/kalite tarafını güçlü biçimde gösterir, fakat PHP değildir ve genel amaçlı ağır bir platformdur. Translation Agent LLM iyileştirme fikrini gösterir, fakat kendisi de deneysel olduğunu söyler. Bu nedenle TedarikApp tasarımı kompozit bir desen olarak değerlendirilmeli; “bir proje böyle yapmış” rahatlığına dayanılmamalıdır.

## Tasarımın kör noktası olabilecek 5 saha senaryosu

| No | Senaryo | Muhtemel zarar | Tasarımda aranacak karşılık |
|---|---|---|---|
| KR-01 | Çeviri sağlayıcısı yavaşlar; retry işleri yeni yakalamaların önüne yığılır. | Kuyruk yaşı büyür, kullanıcı “accepted” görür ama ürün saatlerce tamamlanmaz. | İş türü başına kapasite/adil sıra, en eski iş yaşı, kuyruk büyüme alarmı, sağlayıcı devre kesici. |
| KR-02 | İş süresi lease'i aşar; ikinci worker aynı ürünü işler. | Çift çeviri maliyeti, çakışan medya/alan güncellemesi, yanlış son-yazan kazanır. | Sahiplik tokenı + güvenli lease uzatma/heartbeat + yan etkide idempotency key + kaydı yalnız sahibi bitirebilir. |
| KR-03 | Kalıcı bozuk veri veya yasak içerik her cron'da yeniden denenir. | “Poison job” kapasiteyi tüketir; `last_error` sürekli değişir ama çözüm olmaz. | Geçici/kalıcı hata sınıfı, max deneme, `dead` durumu, operatör düzelt/replay, ham hata örneği ve saklama süresi. |
| KR-04 | TR başarılı, EN/ZH veya ürün alanlarının bir bölümü başarısız olur. | Panelde sessiz karışık dil ya da eski-yeni çeviri birleşimi yayımlanır. | Staging sonuç, dil/alan tamlık bitmap'i, atomik “yayına terfi”, ham veriyi koruma ve kısmi sonucu açık durumla gösterme. |
| KR-05 | Kota biter veya sözlük/prompt değişirken eski cache sonucu döner. | Yanlış terim uzun süre yayılır; retry fırtınası maliyeti artırır. | `Retry-After` uyumu, jitter'lı backoff, kota devre kesici; cache anahtarında kaynak hash + hedef dil + sağlayıcı/model + prompt + sözlük sürümü; başarısız sonucu cache'lememe. |

## ŞARTNAMEYE ÖNERİLEN 9 MADDE

PM bu maddeleri kapsam, maliyet ve Dilim sırasına göre süzecektir.

1. **MV3 başlangıç toparlama sözleşmesi:** `onStartup`, `onInstalled`, alarm ve ağ geri geldi olaylarında `pending/retry_wait/sending` kayıtları taranmalı; sahipsiz `sending` kayıtları güvenli biçimde geri alınmalı. Worker global değişkeni iş durumu sayılmamalı.
2. **Adaptör kimliği ve sağlık matrisi:** Her paketli adaptör `adapter_id`, sürüm, öncelik, desteklediği sayfa türü ve fallback kimliği taşımalı; §14.1 panosu son başarı, başarı oranı, alan tamlığı ve hata kodunu adaptör sürümüne göre göstermeli.
3. **Seçici yayın ön-kapısı:** Yeni seçici paketi 1688 fikstürlerinde ve kontrollü gerçek sayfalarda “bulundu, tekil, tip/doğrulama uygun” kontrollerini geçmeden yayımlanmamalı. Uzak JSON davranış üretmemeli; parser/dönüşüm kodu paketli kalmalı.
4. **Sunucu lease sahipliği:** §13.2'ye benzersiz claim tokenı, lease bitişi ve yalnız sahibin bitirebilmesi kuralı eklenmeli; uzun işler ölçülü heartbeat ile lease uzatabilmeli. Her yan etki iş/ürün idempotency key'iyle korunmalı.
5. **Retry sınıfları:** Ağ/429/5xx için jitter'lı üstel backoff ve `Retry-After`; doğrulama/kimlik doğrulama/şema gibi kalıcı hatalar için erken durma; iş türüne göre max deneme ve maksimum gecikme açıkça tanımlanmalı.
6. **Dead-letter işletimi:** Deneme bütçesi biten iş `dead` durumuna alınmalı; neden, ilk/son hata, payload/schema sürümü ve son worker kaydedilmeli. Panelde düzelt, yeniden dene ve vazgeç işlemleri yetkili ve denetlenebilir olmalı.
7. **Kuyruk bütçesi ve adalet:** Cron turu süre + adet bütçesiyle çalışmalı; çeviri, medya ve zenginleştirme iş türleri birbirini aç bırakmamalı. Kuyruk uzunluğu yanında en eski hazır iş yaşı, hata oranı, dead sayısı ve throughput izlenmeli.
8. **Sürümlü çeviri belleği:** Cache anahtarı en az kaynak metin hash'i, kaynak/hedef dil, sağlayıcı, model, prompt sürümü, sözlük sürümü ve normalizasyon sürümünü içermeli. Yalnız kalite kapısını geçen/insan onaylı sonuç aktif belleğe girmeli; taslak sonuç ayrı güven durumunda kalmalı.
9. **Atomik çeviri yayını:** Dil ve alan bazlı sonuçlar staging'de birikmeli; Görev #4A'nın kritik korunacak/yasak hata koşulları ve zorunlu alan tamlığı geçmeden kanonik ürüne toplu terfi etmemeli. Kısmi sonuç görünür olabilir, fakat çıktı/paylaşımda “tamamlandı” sayılmamalı.

## Kaynakça

### Chrome

- [Extension service worker lifecycle](https://developer.chrome.com/docs/extensions/develop/concepts/service-workers/lifecycle)
- [Declare permissions](https://developer.chrome.com/docs/extensions/develop/concepts/declare-permissions)
- [Remote hosted code violations](https://developer.chrome.com/docs/extensions/develop/migrate/remote-hosted-code)

### MV3 projeleri

- [Obsidian Web Clipper repo](https://github.com/obsidianmd/obsidian-clipper), [manifest](https://github.com/obsidianmd/obsidian-clipper/blob/main/src/manifest.chrome.json), [templates](https://obsidian.md/help/web-clipper/templates), [variables](https://obsidian.md/help/web-clipper/variables)
- [SingleFile MV3 repo](https://github.com/gildas-lormeau/SingleFile-MV3), [manifest](https://github.com/gildas-lormeau/SingleFile-MV3/blob/main/manifest.json), [resmî site](https://www.getsinglefile.com/)
- [Zotero Connectors repo](https://github.com/zotero/zotero-connectors), [MV3 manifest](https://github.com/zotero/zotero-connectors/blob/master/src/browserExt/manifest-v3.json), [translator docs](https://www.zotero.org/support/dev/translators), [translator repo](https://github.com/zotero/translators)
- [Automa repo](https://github.com/AutomaApp/automa), [MV3 manifest](https://github.com/AutomaApp/automa/blob/main/src/manifest.chrome.json)
- [Karakeep repo](https://github.com/karakeep-app/karakeep), [extension manifest](https://github.com/karakeep-app/karakeep/blob/main/apps/browser-extension/manifest.json), [permission helper](https://github.com/karakeep-app/karakeep/blob/main/apps/browser-extension/src/utils/permissions.ts)

### Kuyruk ve çeviri projeleri

- [Action Scheduler repo](https://github.com/woocommerce/action-scheduler), [çalışma modeli](https://actionscheduler.org/), [ölçek](https://actionscheduler.org/perf/), [CLI](https://actionscheduler.org/wp-cli/)
- [Moodle Task API](https://moodledev.io/docs/5.0/apis/subsystems/task), [Adhoc tasks](https://moodledev.io/docs/5.0/apis/subsystems/task/adhoc), [Moodle repo](https://github.com/moodle/moodle)
- [Drupal Queue API](https://api.drupal.org/api/drupal/core%21core.api.php/group/queue/11.x), [QueueWorker](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Queue%21QueueWorkerInterface.php/function/QueueWorkerInterface%3A%3AprocessItem/11.x), [Drupal repo](https://github.com/drupal/drupal)
- [Weblate repo](https://github.com/WeblateOrg/weblate), [translation memory](https://docs.weblate.org/en/latest/admin/memory.html), [glossary](https://docs.weblate.org/en/latest/user/glossary.html), [automatic suggestions](https://docs.weblate.org/en/latest/admin/machine.html), [quality checks](https://docs.weblate.org/en/latest/user/checks.html)
- [Translation Agent repo](https://github.com/andrewyng/translation-agent)

---

**Araştırmacı beyanı:** Kaynaklardan kod kopyalanmamış, lisans uygunluğu analizi yapılmamış ve hiçbir proje bağımlılık/entegrasyon kararı olarak sunulmamıştır.
