# TedarikApp Eklenti v3 Vizyon Paketi

> **Belge türü:** Vizyon — şartname değildir.  
> **Hedef:** V1.0 sahaya çıktıktan ve gerçek kullanım dersleri biriktikten sonra V3-K şartnamesine girdi sağlamak.  
> **Son araştırma tarihi:** 23 Ağustos 2026  
> **Yöntem:** Kod veya bağımlılık alınmadı; yalnız ürün, arayüz, iş akışı ve politika desenleri incelendi.

## Yönetici özeti

Profesyonel eklentiler tek bir yüzeye sıkışmıyor. En başarılı desen üç katmanlı: **sayfa içi katman bağlamı zenginleştiriyor**, **Side Panel kalıcı işi ve kuyruğu taşıyor**, **popup ise anlık komutlara ayrılıyor**. Helium 10, Jungle Scout ve SellerSprite arama sonuçlarını bir araştırma tablosuna dönüştürüyor; Keepa ve AliPrice karar anına grafik/rozet yerleştiriyor; Grammarly ve 1Password ise küçük sayfa içi tetikleyici ile daha derin panel arasında temiz rol ayrımı kuruyor.

TedarikApp v3 için önerilen ana yön, eklentiyi yeni ve bağımsız bir “her şeyi yapan panel” haline getirmek değildir. Eklenti, kullanıcının Çin kaynaklı ürünleri **araştırma → seçme → doğrulama → kendi paneline kontrollü aktarma** tek amacı etrafında profesyonelleşmelidir. Kalıcı Side Panel; yakalama kuyruğu, son yakalamalar, hızlı arama ve karşılaştırmayı taşır. Sayfa içi katman; lacivert TedarikApp kimliğiyle fiyat geçmişi, izleme, skor ve seçim rozetlerini gösterir. Turuncu yalnız ikon ve eylem vurgusudur; 1688 arayüzü taklit edilmez.

## 14A — Emsal araştırması

### Araştırma çerçevesi

Sekiz emsal şu beş soruyla incelendi:

1. Ana yüzey popup, sayfa içi overlay, gömülü kart/tablo veya yan panel mi?
2. Kaynak sayfayı hangi grafik, rozet ve hızlı eylemlerle zenginleştiriyor?
3. Tek ürün araştırmasını toplu seçme, kaydetme, karşılaştırma veya dışa aktarmaya nasıl bağlıyor?
4. Ücretsiz/ücretli katman ayrımını nerede kuruyor?
5. Chrome Web Store açısından hangi görünürlük, izin, veri kullanımı veya kullanıcı kontrolü desenleri okunabiliyor?

Store stratejisi başlıklarında ürünlerin iç manifest kodu incelenmemiştir. Store kaydı ve resmî yardım belgelerinden çıkan yorumlar **“çıkarım”** olarak işaretlenmiştir.

### 1. Helium 10 Chrome Extension

**Arayüz mimarisi.** Araç çubuğu menüsü bir başlatıcı gibi çalışıyor; ağır araştırma Xray sonuç penceresinde tabloya dönüşüyor. Arama sonuçlarından tek ürün yerine pazar kümesi analiz ediliyor. Xray tablosu ortalama metrikleri üstte özetliyor, satır seçimi/silme ve başka araca geçiş sağlıyor. Bu yapı klasik küçük popup'tan çok, sayfaya bağlı geçici bir araştırma çalışma alanı desenidir. [Resmî kurulum ve Xray gezintisi](https://kb.helium10.com/hc/en-us/articles/360049023813-How-Do-I-Install-and-Navigate-the-Chrome-Extension-An-Introduction-and-Overview), [Chrome Web Store kaydı](https://chromewebstore.google.com/detail/helium-10-for-amazon-sell/njmehopjdpcckochcggncklnlmikcbnb)

**Sayfa içi zenginleştirme ve toplu akış.** Ürün/sorgu sonuçlarını satış, gelir, BSR ve benzeri sütunlarla tarıyor; seçili ürünleri Cerebro gibi başka analizlere veya Alibaba tedarikçi bulma akışına geçiriyor. Tedarikçi penceresinde fiyat, MOQ, satıcı puanı, ülke ve mağaza yaşı filtreleri kullanılıyor. [Supplier Finder akışı](https://www.helium10.com/tools/product-research/chrome-extension/supplier-finder/)

**Kademe yapısı.** Ücretsiz planda eklenti erişimi sınırlı; Platinum ve üstünde tam erişim var. Ücret, yalnız eklentiye değil web uygulamasındaki araştırma/operasyon paketine bağlanıyor. Bu, eklentiyi tek başına ürün değil panel hizmetinin “saha terminali” olarak konumlandırıyor. [Resmî plan karşılaştırması](https://www.helium10.com/pricing/)

**Store/politika deseni.** Store kaydı kullanıcı etkinliği veri kategorisini beyan ediyor ve verinin alakasız amaçlarla kullanılmayacağını bildiriyor. Resmî yardım belgesi bazı özellikler için “tüm siteler” erişimi açtırıyor; bu ticari kapsamlarını büyütüyor ama TedarikApp için alınacak desen değildir. **Çıkarım:** Geniş izin yerine 1688 ve açıkça desteklenen platform/panel kökenleriyle dar kapsam korunmalıdır.

**TedarikApp için ders.** Alınacak: sonuç sayfasını seçilebilir araştırma tablosuna çevirme; seçimi panelde daha derin akışa devretme. Alınmayacak: her özelliği eklentiye doldurma ve genel “tüm siteler” erişimi.

### 2. Jungle Scout Extension

**Arayüz mimarisi.** Eklenti Amazon arama sonucu, ürün veya mağaza sayfasında açılıyor; web uygulaması daha geniş iş merkezi olarak kalıyor. Arama sonuçlarındaki gömülü ürün kartları, eklentiyi ayrıca açmadan temel metrikleri gösteriyor; kart üzerindeki artı, anahtar ve ayar eylemleri ürün izleme, anahtar kelime araştırması ve görünüm özelleştirmesine gidiyor. [Eklenti–web uygulaması rol ayrımı](https://support.junglescout.com/hc/es/articles/360008616534--Cu%C3%A1l-es-la-diferencia-entre-la-aplicaci%C3%B3n-web-de-Jungle-Scout-y-la-extensi%C3%B3n-para-Chrome), [gömülü ürün kartları](https://support.junglescout.com/hc/en-us/articles/360051811194-Extension-Embedded-Product-Cards-in-Search-Results)

**Sayfa içi zenginleştirme ve toplu akış.** Arama sonucunda Opportunity Score ve Listing Quality Score gibi karar özetleri kullanılıyor; kullanıcı hangi sütunların görüneceğini seçebiliyor. LQS, başlık/açıklama/görsel kalitesi gibi sinyalleri 1–10 ölçeğine indiriyor ancak ayrıntılı araştırmaya geçişi açık bırakıyor. [Opportunity Score bağlamı](https://support.junglescout.com/hc/en-us/articles/4403745539351-Missing-Opportunity-Score), [Listing Quality Score](https://support.junglescout.com/hc/en-us/articles/360008617394-Listing-Quality-Score-LQS)

**Kademe yapısı.** Eklenti bütün ana planlara dahil; üst planlar tarih, dışa aktarma, izleme limiti, çoklu kullanıcı ve ileri analiz kapasitesini artırıyor. [Resmî planlar](https://www.junglescout.com/pricing/)

**Store/politika deseni.** Eklenti yalnız desteklenen Amazon sayfalarında anlamlı çalışıyor ve ürün araştırması için Seller Central bağlantısını zorunlu tutmuyor. **Çıkarım:** Bağlama göre özelliği açma, desteklenmeyen sayfada sessiz veri toplamamak ve kullanıcı hesabı bağlantısını ancak gereken özellikte istemek Store riskini azaltan iyi desenlerdir. [Hesap bağlantısı ve desteklenen sayfalar](https://support.junglescout.com/hc/en-us/articles/29621869133719-Do-I-need-to-connect-Seller-Central-account-to-use-the-Extension)

**TedarikApp için ders.** Alınacak: sonuç kartında özelleştirilebilir küçük veri bandı ve açıklanabilir skor. Alınmayacak: ticari kararı tek puana indirgeme; TedarikApp Skoru veri eksikse gizli kalmalıdır.

### 3. Keepa — Amazon Price Tracker

**Arayüz mimarisi.** Keepa'nın güçlü deseni “sıfır tık” sayfa içi entegrasyondur: fiyat geçmişi grafiği doğrudan ürün sayfasına yerleşir; araç çubuğu kalabalığı yaratmadan izleme eylemi grafiğin yakınında yaşar. [Chrome Web Store kaydı](https://chromewebstore.google.com/detail/keepa-amazon-price-tracke/neebplgakaahbhdphmkckjjcegoiijjo), [Keepa ürün sitesi](https://keepa.com/)

**Sayfa içi zenginleştirme ve toplu akış.** Etkileşimli tarih grafiği, hedef fiyat, stok dönüşü ve fiyat düşüş uyarısı ana desenlerdir. Uluslararası mağaza karşılaştırması aynı ürünü pazarlar arasında bağlar. Ücretli veri/API tarafında ürün bulucu, fırsat tarama, satıcı verisi ve izleme nesneleri daha büyük toplu araştırma akışına dönüşür. [Resmî API kapsam özeti](https://keepa.com/api-docs/), [izleme nesnesi](https://keepa.com/api-docs/tracking-object.html)

**Kademe yapısı.** Eklenti temel grafik ve izleme deneyimini ücretsiz başlatır; gelişmiş veri ve programatik erişim ayrı abonelik/token modeliyle derinleşir. Bu, görünür karar yardımını düşük eşikte sunup ağır veri maliyetini sunucu hizmetine taşıyan modeldir.

**Store/politika deseni.** Store açıklaması gerekli Amazon/Keepa alanlarını, depolama kullanımını ve isteğe bağlı bağlam menüsünü tek tek gerekçelendiriyor; “minimum permissions” yaklaşımını doğrudan anlatıyor. **Çıkarım:** Özelliği kapatılabilir ve izin gerekçesini ürün dilinde açıklayan tasarım güven oluşturuyor.

**TedarikApp için ders.** Alınacak: veri yaşını gösteren küçük grafik, hedef/eşik izleme ve rozet üzerinden panel akışına geçiş. Alınmayacak: eklentinin kendi başına sürekli fiyat tarayan ayrı bir izleme motoruna dönüşmesi; izleme V3-F panel tarafında yaşamalıdır.

### 4. AliPrice / AiPrice 1688 Sourcing Tool

**Arayüz mimarisi.** AliPrice ailesi sayfa içi araç çubuğu, ürün görseli üzerinde tetikleyici, sağ tık menüsü ve kendi karşılaştırma/son aramalar yüzeyini birlikte kullanıyor. 1688 ürün ve liste sayfalarında görsel büyütme, medya indirme ve kopyalama gibi hızlı araçları kaynağın yakınında sunuyor. [Resmî ürün sayfası](https://www.aliprice.com/Extension/), [1688 Chrome Web Store kaydı](https://chromewebstore.google.com/detail/1688%E4%BB%B7%E6%A0%BC%E8%BF%BD%E8%B8%AA%E5%99%A8/pkghjinojggjcpfnbpncpkbmpdijldla)

**Sayfa içi zenginleştirme ve toplu akış.** Fiyat geçmişi, aynı ürünü görselle arama, satıcı/MOQ bilgisi, resim-video toplu indirme, sepet veya mağaza ürünlerini Excel'e aktarma ve reklam etiketi vurgulama aynı tedarik bağlamında birleşiyor. [1688 medya ve araştırma özellikleri](https://www.aliprice.com/en/taobao_tutorials/2551_459/1688-image-downloader)

**Kademe yapısı.** Temel uzantı ücretsiz; gelişmiş ürün arama, filtreleme ve bazı dışa aktarma araçları PRO olarak ayrılıyor. Bu, hızlı yardımcıları ücretsiz tutup büyük veri/işlem kapasitesini ücretli katmana koyan modeldir.

**Store/politika deseni.** Store kaydı web geçmişi ve kullanıcı etkinliği işlediğini beyan ediyor; ayrıca ortaklık/ reklam yönlendirmesini açıkça açıklıyor. **Çıkarım:** Şeffaf beyan doğru olsa da TedarikApp için ortaklık yönlendirmesi, reklam enjeksiyonu ve geniş gezinme geçmişi toplanması tek amaçla bağdaşmaz; alınmamalıdır.

**Çok siteyi tek ürün kabuğunda yönetme deseni.** AliPrice sınıfının dışarıdan görülen yaklaşımı, ortak araçları—görsel arama, fiyat geçmişi, medya işlemleri, karşılaştırma ve dışa aktarma—tek ürün ailesinde tutup alan adına göre uygun site davranışını açmaktır. Ürün sayfası ve Store kayıtları 1688, Taobao/Tmall, AliExpress, Alibaba ve başka pazarlar için farklı kapsamlardan söz eder; bu da ortak UI/iş akışı çekirdeğinin, URL ve sayfa tipini tanıyan site katmanlarıyla beslendiği bir yetenek matrisi desenine işaret eder. Kod deposu incelenmediği için bunun tek repository veya birebir iç sınıf yapısı olduğu iddia edilmez; TedarikApp'in alacağı ders, ortak komutları çoğaltmamak ve her adaptörün yalnız güvenle sağlayabildiği `ürün_kimliği`, `fiyat`, `MOQ`, `varyant`, `satıcı`, `medya` gibi yetenekleri ilan etmesidir. [AliPrice uzantı ailesi ve desteklenen siteler](https://www.aliprice.com/Extension/)

**TedarikApp için ders.** Alınacak: görsel üstü hızlı önizleme, sonuç sayfası toplu seçimi, medya seçimi ve kaynak bağlamında fiyat geçmişi. Alınmayacak: affiliate yönlendirme, reklam katmanı, geniş site ailesi ve açıklanmamış otomasyon.

### 5. SellerSprite — Amazon Research Tool

**Arayüz mimarisi.** Ürün arama sonuçlarında hızlı veri görünümü, ürün detayında zengin araç çubuğu/panel ve sağ alt köşede açılan “My Product List” geçişi birlikte çalışıyor. Store açıklaması web uygulamasının işlevlerinin büyük bölümünü sayfa içinde sunduğunu belirtiyor. [Chrome Web Store kaydı](https://chromewebstore.google.com/detail/sellersprite-amazon-resea/lnbmbgocenenhhhdojdielgnmeflbnfb), [ürün detay paneli](https://www.sellersprite.com/en/help/listing-page-of-extension)

**Sayfa içi zenginleştirme ve toplu akış.** BSR, fiyat, yorum artışı, satış ve gelir trendleri etkileşimli grafiklere açılıyor. Kullanıcı ürünleri adlandırılmış listelerde topluyor; 2–5 ASIN karşılaştırıyor, çoklu ASIN analizi başlatıyor ve Excel dışa aktarıyor. [My Product List ve toplu işlemler](https://www.sellersprite.com/en/help/product-list-of-extension)

**Kademe yapısı.** Kurulum ücretsiz ve ücretsiz hesapla başlıyor; yeni kullanıcıya premium deneme veriliyor, derin tarih/AI/limitler üyelikle açılıyor. Eklenti, panel ürün listesiyle aynı koleksiyonu paylaşarak ücretli web hizmetine doğal geçiş sağlıyor.

**Store/politika deseni.** Store kaydı yalnız “website content” veri kategorisini beyan ediyor, verinin alakasız amaçlara aktarılmayacağını söylüyor ve önerilen uygulamalar etiketini taşıyor. **Çıkarım:** Web–eklenti koleksiyon eşitliği iyi; fakat “web uygulamasının %80'i eklentide” yaklaşımı TedarikApp'in tek amaç anlatımını gereksiz yere genişletebilir.

**TedarikApp için ders.** Alınacak: adlandırılmış araştırma oturumu, 2–6 ürün karşılaştırma ve seçili kayıtları panelde ileri analize gönderme. Alınmayacak: panelin tamamını eklentiye kopyalama ve sayfayı ağırlaştıran her zaman açık modüller.

### 6. AliNiche — AliHunter sınıfı ürün araştırma eklentisi

**Arayüz mimarisi.** Arama ve kategori sayfasında ürün kartına hızlı veri tablosu ekliyor; ürün detayında yüzen ikonla kapsamlı analiz açılıyor. Bu, AliHunter sınıfındaki “listeyi zenginleştir + detayda derinleş” deseninin güncel ve Store'da görünür bir örneğidir. [Chrome Web Store kaydı](https://chromewebstore.google.com/detail/aliniche-aliexpress-produ/lmlkbclipoijbhjcmfppfgibpknbefck)

**Sayfa içi zenginleştirme ve toplu akış.** Sipariş, istek listesi, fiyat, kâr ve yayın tarihi hızlı görünümde; satış/istek/fiyat trendi, yorum özeti, lojistik ve benzer tedarikçi analizi detay yüzeyinde. Görsel arama, medya ve yorum indirme de aynı bağlamda bulunuyor.

**Kademe yapısı.** Eklenti ücretsiz başlıyor; bağlı SellerCenter/FindNiche ekosistemi temel veriyi ücretsiz, tam özellik ve yüksek limitleri ücretli sunuyor. [SellerCenter ücretsiz/ücretli yapı](https://sellercenter.io/en), [FindNiche plan katmanları](https://findniche.com/en)

**Store/politika deseni.** Store kaydı önerilen uygulamalar etiketini taşıyor, veri toplamadığını beyan ediyor, kullandığı AliExpress API'siyle platform arasında resmî bağ olmadığını açıkça yazıyor. **Çıkarım:** Bağımsızlık beyanı ve veri kullanım açıklığı iyi; ancak her sayfada otomatik çalışan yoğun genişletme yerine TedarikApp kullanıcı kontrollü açılmalıdır.

**TedarikApp için ders.** Alınacak: sonuç kartında hızlı ticari sinyaller ve detayda satıcı/lojistik derinleşmesi. Alınmayacak: “kazanan ürün” gibi kesin satış vaadi ve kullanıcı niyeti olmadan sürekli sayfa genişletme.

### 7. Grammarly Browser Extension — genel profesyonel emsal

**Arayüz mimarisi.** Küçük bir metin alanı içi simge, satır altı işaretler, hover öneri kartı ve daha geniş sağ kenar paneli arasında kademeli açıklama kullanıyor. Basit düzeltme yerinde; neden, ton ve çoklu öneri panelde yaşıyor. [Tarayıcı eklentisi kılavuzu](https://support.grammarly.com/hc/en-us/articles/115000091592-How-does-Grammarly-s-browser-extension-work-), [Google Docs yan panel akışı](https://support.grammarly.com/hc/en-us/articles/115000090991-Does-Grammarly-support-Google-Docs), [Chrome Web Store yayıncı kaydı](https://chromewebstore.google.com/publisher/grammarly/u7431803589585a11dd7aea578e1005d0)

**Sayfa içi zenginleştirme ve toplu akış.** Kullanıcı küçük işaretten tek öneriyi kabul ediyor veya panelde öneriler arasında ilerliyor. Klavye ile panel açma, Tab/Shift+Tab ve Enter/Space desteği profesyonel akışı tamamlıyor. [Klavye erişimi](https://support.grammarly.com/hc/en-us/articles/34517006125837-Can-I-use-my-keyboard-to-navigate-the-Grammarly-browser-extension)

**Kademe yapısı.** Free temel düzeltme ve sınırlı AI; Pro daha ileri yeniden yazım, ton, hedef kitle, özgünlük ve kurumsal özellikler sunuyor. Ücret duvarı yüzey değişiminden çok önerinin derinliğinde kuruluyor.

**Store/politika deseni.** Kullanıcı eklentinin ne zaman etkin olduğunu görebiliyor; site bazında kapatma ve Enterprise alan adı denetimleri var. Hassas alanları dışlama ve ek bağlam için ayrı izin bildirimi açıklanıyor. [Alan adı denetimleri](https://support.grammarly.com/hc/en-us/articles/27448664194957-Manage-domain-controls), [veri erişimi açıklaması](https://support.grammarly.com/hc/en-us/articles/360003816032-Is-Grammarly-a-keylogger)

**TedarikApp için ders.** Alınacak: küçük rozet → açıklama kartı → Side Panel derinliği, site bazlı kapatma ve tam klavye akışı. Alınmayacak: her metin alanında sürekli çalışma; TedarikApp yalnız desteklenen ürün bağlamında görünmelidir.

### 8. 1Password Browser Extension — genel profesyonel emsal

**Arayüz mimarisi.** Araç çubuğu popup'ı arama, hesap/kasa ve ayar merkezi; form alanı altındaki inline menü yalnız bağlama uygun hızlı seçim; masaüstü Quick Access ise daha geniş klavye odaklı erişimdir. Aynı veri modelini farklı yüzeylerde, farklı derinlikle sunar. [Tarayıcı başlangıç kılavuzu](https://support.1password.com/getting-started-browser/), [inline kaydet/doldur akışı](https://support.1password.com/save-fill-passwords/), [Chrome Web Store kaydı](https://chromewebstore.google.com/detail/1password-%E2%80%93-password-mana/aeblfdkhhhdcdjpifhhbdiojplfjncoa)

**Sayfa içi zenginleştirme ve toplu akış.** Form alanı bağlamında birden fazla hesap filtrelenebilir, ok tuşlarıyla seçilebilir ve popup'tan “Open & Fill” yapılabilir. Hesaplar/kasalar, cihazlar ve masaüstü uygulamayla senkron davranış profesyonel çoklu kimlik yönetimi örneğidir.

**Kademe yapısı.** Üyelik zorunlu, ücretsiz deneme var; Individual, Families ve işletme planları cihazlar arası kullanım ve yönetim kapasitesini büyütüyor. [Resmî planlar](https://1password.com/pricing/personal)

**Store/politika deseni.** Ana parolanın yalnız araç çubuğu popup'ında istenmesi, inline menünün hassas kimlik istememesi ve verinin uçtan uca şifreli olduğunun açık anlatımı “doğru yüzeyde doğru güven eylemi” desenidir. [Tarayıcı güvenliği](https://support.1password.com/1password-browser-security/)

**TedarikApp için ders.** Alınacak: cihazı adlandırma, dar kapsamlı token, masked değer, popup'ta güvenlik ve Side Panel'de operasyon ayrımı. Alınmayacak: token değerini sayfa içine veya günlük paketine taşıma.

## Emsallerden çıkan ortak desenler

| Desen | Güçlü örnekler | TedarikApp karşılığı |
|---|---|---|
| Bağlamda küçük, panelde derin | Grammarly, 1Password | Sayfa içi rozet → Side Panel ayrıntısı |
| Arama sonucunu çalışma tablosuna dönüştürme | Helium 10, Jungle Scout, SellerSprite | 1688 toplu seçim ve önizleme |
| Grafik + eylem aynı yerde | Keepa, AliPrice | V3-F fiyat geçmişi + İzlemeye al |
| Koleksiyonun web ve eklentide ortak olması | SellerSprite, Helium 10 | Son yakalamalar, listeler ve karşılaştırma sepeti |
| Ücretsiz çekirdek, ücretli veri derinliği | Helium 10, Jungle Scout, SellerSprite | Şimdilik lisans kararı yok; mimari limitlere hazır olabilir |
| Kullanıcıya açık etkinlik/izin kontrolü | Grammarly, Keepa, 1Password | Site bazlı UI, ihtiyaç anında izin, veri faaliyet günlüğü |

## Çok platform mimarisi

### Çekirdek + adaptör ilkesi

V3-E merdiveni **P0 1688 → P1 Alibaba → P2 Made-in-China/Global Sources → P3 AliExpress/Taobao → P4 Amazon/Temu** sırasıyla ilerler. Bu sıra beş ayrı eklenti veya beş ayrı arayüz üretme gerekçesi değildir. V1'deki `adapter_id` yaklaşımı korunur ve genişletilir:

- **Platformdan bağımsız çekirdek**, Side Panel, popup, komutlar, önizleme formu, kuyruk, idempotensi, token, izin isteme, bildirim, denetim günlüğü ve panel API sözleşmesini yönetir.
- **Site adaptörü**, alan adı ve sayfa türünü tanır; görünür DOM'dan desteklediği alanları çıkarır; kaynak ürün/satıcı kimliğini normalleştirir ve yalnız güvenle sağlayabildiği yetenekleri ilan eder.
- **Yetenek kapısı**, arayüzü `adapter_id` adına göre sabit dallandırmaz. Örneğin `supports.moq`, `supports.variants`, `supports.price_history_link`, `supports.bulk_cards` ve `supports.seller_metrics` gibi adaptör çıktısına göre eylemi açar, kısmi gösterir veya gerekçesiyle gizler.
- **Ortak ürün sözleşmesi**, platformun ham adlarını panelin normalize edilmiş alanlarına bağlar; kaynak değer ve kaynak izi korunur. Adaptör eksik alanı uydurmaz, `yok` ile `okunamadı` durumlarını ayırır.
- **Sürümlü adaptör kaydı**, `adapter_id`, `adapter_version`, desteklenen alan adları, sayfa tipleri, yetenekler ve son doğrulama tarihini taşır. Seçici güncellemeleri paket içi kod sınırında kalır; uzak JSON yalnız veri/konfigürasyon olabilir, çalıştırılabilir kod olamaz.

Bu nedenle katalogdaki “1688 arama sonuçlarından toplu yakalama” gibi P0 ifadeleri çekirdeğin 1688'e gömülmesi anlamına gelmez. Çekirdek “adaptörün sağladığı ürün kartlarını seç ve önizlemeye taşı” komutunu bilir; ilk üretim adaptörü bunu 1688'de sağlar, sonraki adaptörler aynı sözleşmeye kendi seçicileri ve alan eşlemeleriyle katılır.

### V3-E destek merdiveni ve yetenek beklentisi

| Faz | Platformlar | İlk adaptör odağı | Arayüzde örnek uyarlama |
|---|---|---|---|
| P0 | 1688 | Ürün, arama sonucu, kategori, mağaza, varyant, fiyat kademesi, MOQ, medya | MOQ ve kademeli fiyat öne çıkar; 同款/benzer kayıt sinyali varsa gösterilir. |
| P1 | Alibaba | Ürün/tedarikçi, RFQ bağlamı, MOQ, teslim/Incoterm ve doğrulanmış tedarikçi işaretleri | MOQ, fiyat kademesi ve tedarikçi sinyalleri karar bandında vurgulanır. |
| P2 | Made-in-China, Global Sources | Ürün/tedarikçi kartı, sertifika ve minimum sipariş alanları | Sertifika ve tedarikçi doğrulama alanları adaptör sağlıyorsa görünür. |
| P3 | AliExpress, Taobao | Perakende/varyant fiyatı, mağaza, sipariş/yorum sinyalleri, görsel eşleme | MOQ yoksa alan uydurulmaz; varyant, perakende fiyatı ve kaynak bulma karşılaştırması öne çıkar. |
| P4 | Amazon, Temu | Hedef pazar fiyatı, liste/ürün kimliği, satıcı/yorum sinyalleri | “Hedef pazar fiyat kıyası” rozeti gösterilir; bu kaynak fiyatı değil satış pazarı referansı olarak etiketlenir. |

Faz ilerlemesi “alan adı açıldı” diye tamamlanmış sayılmaz. Her site/sayfa türü için fikstür, seçici sağlık ölçümü, veri sözleşmesi ve izin gerekçesi kapısından geçilmelidir.

### Side Panel ve popup'ın siteye göre davranışı

Side Panel'in iskeleti her yerde aynıdır: bağlantı durumu, site desteği, aktif sayfa, önizleme, kuyruk ve son yakalamalar. Değişen bölüm adaptörün sağladığı veri bandıdır. Alibaba'da MOQ, fiyat kademesi ve tedarikçi doğrulama sinyali; Amazon'da hedef pazar fiyat kıyası ve ürün kimliği; AliExpress/Taobao'da varyant fiyatı ve görsel eşleme; 1688'de MOQ, kademeli fiyat, mağaza ve 同款 sinyali öne çıkabilir. Bir alan adaptörde yoksa boş bir sayı veya yanıltıcı sıfır gösterilmez.

Popup da aynı ortak komutları korur: “Önizle ve yakala”, “Side Panel'i aç”, sağlık ve son işlem. Metin ve ikincil bilgi siteye göre uyarlanabilir; örneğin Alibaba ürününde “MOQ: 500” özeti, Amazon ürününde “Hedef pazar kaydı” etiketi görülür. Yakalama eylemi her platformda kullanıcı tetiklemeli ve önizlemeli kalır.

```text
┌──────────────────────────────────────┐
│ TedarikApp                 ● Bağlı   │
│ Bu site: TAM DESTEK · Alibaba        │
│ Ürün detayı · adapter: alibaba@1.2   │
├──────────────────────────────────────┤
│ MOQ 500 · 3 fiyat kademesi           │
│ Tedarikçi sinyalleri: kullanılabilir │
│ [Önizle ve yakala] [Karşılaştır +]   │
└──────────────────────────────────────┘
```

### Site destek durumu göstergesi

Her yüzey başlığında tek ve tutarlı bir gösterge bulunur:

- **Bu site: Tam destek** — geçerli sayfa türü tanınıyor, zorunlu yakalama alanları fikstürlerle doğrulanmış ve ana akış kullanılabilir.
- **Bu site: Kısmi destek** — güvenli olarak okunabilen alanlar ve kullanılabilir eylemler açıkça listelenir; eksik yetenekler pasif gerekçeyle gösterilir. Kısmi destek otomatik “tam”a yükselmez.
- **Bu site: Yakında** — adaptör üretime açılmamıştır; sayfa içeriği okunmaz veya gönderilmez. Kullanıcı yalnız destek planını ve genel kuyruk/geçmiş yüzeyini görebilir.
- **Desteklenmeyen site** — sayfa içi UI enjekte edilmez; Side Panel yalnız genel kuyruk, geçmiş ve ayar işlerini sunar.

Destek etiketi yalnız alan adına değil, **alan adı + sayfa türü + adaptör sürümü + sağlık durumu** bileşimine göre hesaplanır. Böylece örneğin bir platformun ürün detayı tam, mağaza sayfası kısmi olabilir.

### Çok platforma özgü Store riskleri

Yeni faz, yalnız o fazın alan adlarını mümkünse `optional_host_permissions` ile ve ihtiyaç anında ister. P0 izni gelecekteki P1–P4 için örtük genişletilmez; “yakında” platformunda içerik betiği çalıştırılmaz. Store açıklaması desteklenen platformları ve her izinle açılan özelliği güncel tutar. Adaptörler uzaktan indirilen çalıştırılabilir kod olamaz; kullanıcı verisi platformlar arası profilleme, reklam veya affiliate yönlendirme için birleştirilemez.

## 14C — Arayüz vizyon taslağı

### Rol ayrımının ana kuralı

Üç yüzey aynı işi tekrar etmemelidir:

- **Side Panel:** Süreklilik, durum ve çok adımlı iş. Kuyruk, son yakalamalar, toplu seçim incelemesi, karşılaştırma, hızlı panel araması ve bildirim özeti burada yaşar.
- **Popup:** En fazla birkaç saniyelik tek adımlı komut. “Bu sayfayı yakala”, “Side Panel'i aç”, bağlantı/token sağlığı ve son işlemin sonucu burada yaşar.
- **Sayfa içi katman:** Kaynak bağlamına bağlı bilgi ve eylem. Ürün kartı seçim kutusu, skor/fiyat geçmişi/izleme rozeti, medya önizleme ve karşılaştırmaya ekle burada yaşar.

### Side Panel yerleşim krokisi

```text
┌──────────────────────────────────────┐
│ TedarikApp                 ● Bağlı   │
│ [Ara: ürün, kaynak ID, liste...]     │
├──────────────────────────────────────┤
│ BUGÜNKÜ SAYFA                         │
│ 1688 · Ürün detayı                    │
│ [Önizle ve yakala]  [Karşılaştır +]  │
├──────────────────────────────────────┤
│ Sekmeler                              │
│ [Kuyruk 3] [Son yakalananlar] [Ara]  │
├──────────────────────────────────────┤
│ DM benzeri ürün kartı                 │
│ küçük resim · fiyat · MOQ · durum     │
│ alan tamlığı ███████░  izleme: açık  │
│ [Düzenle] [Gönder] [Hata ayrıntısı]  │
├──────────────────────────────────────┤
│ Bildirim: 1 uyarı     [Panelde aç]   │
└──────────────────────────────────────┘
```

Side Panel sekmeler arasında açık kalabilir; ancak içerik **aktif sekmenin bağlamını** açıkça göstermelidir. Kullanıcı Amazon/1688 dışı bir sekmeye geçtiğinde önceki ürün “aktif sayfa” gibi sunulmamalı; “Desteklenmeyen sayfa — kuyruk ve geçmiş kullanılabilir” durumu gösterilmelidir.

### Popup yerleşim krokisi

```text
┌──────────────────────────────┐
│ TedarikApp           ● Bağlı │
├──────────────────────────────┤
│ 1688 ürün sayfası algılandı  │
│ [Bu ürünü önizle ve yakala]  │
│ [Side Panel'i aç]            │
├──────────────────────────────┤
│ Kuyruk: 3 · Hata: 1          │
│ Son işlem: Gönderildi        │
│ [Ayarlar] [Paneli aç]        │
└──────────────────────────────┘
```

Popup içine karşılaştırma matrisi, varyant formu, fiyat grafiği veya uzun ayarlar konmamalıdır. Popup kapanması kullanıcı durumunu yok etmemeli; gerçek iş Side Panel/kalıcı kuyrukta yaşamalıdır.

### Sayfa içi katman krokileri

Arama sonucu kartı:

```text
┌──────────────────────── ürün kartı ───────────────────────┐
│ [✓]  ürün görseli  başlık/fiyat/MOQ                       │
│      [Skor: Yüksek] [İzlenmiyor] [Benzer: 4]              │
│      [Hızlı önizle] [Karşılaştır +]                        │
└────────────────────────────────────────────────────────────┘
```

Ürün detayı işlem rayı:

```text
                 ┌─ TedarikApp ───────────┐
                 │ ◉ Fiyat: son kayda -%4 │
                 │ ◫ Skor: Orta           │
                 │ ☆ İzlemeye al          │
                 │ ▣ Medya önizle         │
                 │ + Karşılaştır          │
                 │ [Önizle ve yakala]     │
                 └─────────────────────────┘
```

Sayfa içi yüzey lacivert ana blok, beyaz metin/yüzey ve turuncu aksiyon vurgusuyla TedarikApp kimliğini taşır. 1688'in turuncu butonlarına benzeyen yerleşim, tipografi veya ikon dili kullanılmaz. Katman küçültülebilir, site bazında kapatılabilir ve Shadow DOM ile izole edilir.

### Durum modeli

Her yüzey aynı temel durum sözlüğünü paylaşmalıdır:

- **Hazır:** Desteklenen sayfa algılandı; kullanıcı işlem başlatabilir.
- **Önizleme gerekiyor:** Veri okundu fakat gönderim onayı yok.
- **Kuyrukta:** İş yerel kalıcı kuyruğa yazıldı.
- **Gönderiliyor:** Tek aktif lease sahibi işlemi yürütüyor.
- **Gönderildi:** Panel kimliği ve derin bağlantı hazır.
- **Düzeltme gerekiyor:** Kritik alan eksik veya düşük güvenli.
- **Çevrimdışı:** İş korunuyor; otomatik backoff veya kullanıcı yeniden denemesi bekleniyor.
- **Yetki gerekiyor:** Token veya alan adı izni eylem bağlamında istenecek.

Bu durumların metinleri yüzeye göre kısalabilir; anlamları değişmemelidir.

## Store ve MV3 inceleme riskleri

1. **Tek amaç genişlemesi:** Fiyat izleme, medya, karşılaştırma ve bildirim ayrı ürünler gibi anlatılırsa “araştır–doğrula–kendi paneline aktar” amacı dağılır. Store açıklaması bütün özellikleri bu tek akışa bağlamalıdır. Chrome, dar ve anlaşılır tek amaç ister. [Quality Guidelines](https://developer.chrome.com/docs/webstore/program-policies/quality-guidelines)
2. **Kalıcı Side Panel'in taramayı ele geçirmesi:** Side Panel kullanıcı gezinmesini veya aramasını yönlendiren, dikkat dağıtan bir yüzeye dönüşmemelidir. Desteklenen sayfada yardımcı olmalı; diğer sayfalarda pasif kalmalıdır. [Side Panel API ve UX notları](https://developer.chrome.com/docs/extensions/reference/api/sidePanel)
3. **Geniş host izinleri:** 1688, desteklenen ek platformlar ve kullanıcının panel kökeni dışında genel `<all_urls>` istenmemelidir. Yeni platformlar mümkünse `optional_host_permissions` ile ihtiyaç anında açılmalıdır. [İzin bildirme rehberi](https://developer.chrome.com/docs/extensions/develop/concepts/declare-permissions)
4. **Uzak çalıştırılabilir kod:** Seçici, özellik bayrağı veya model çıktısı JSON veri olabilir; fakat uzaktan gelen JavaScript/WASM ya da çalıştırılabilir ifade olamaz. Kod paket içinde bulunmalı, JSON yalnız doğrulanmış veri olarak yorumlanmalıdır. [Remote Hosted Code rehberi](https://developer.chrome.com/docs/extensions/develop/migrate/remote-hosted-code)
5. **Service worker ömrüne güvenmek:** Kuyruk ve token durumu global bellekte tutulamaz. Service worker yaklaşık 30 saniyelik boşlukta sonlanabilir; kalıcı durum `chrome.storage`/IndexedDB üzerinde ve idempotent olmalıdır. [Service worker yaşam döngüsü](https://developer.chrome.com/docs/extensions/develop/concepts/service-workers/lifecycle)
6. **Kullanıcı hareketi olmadan veri aktarımı:** Toplu seçim ve kategori/mağaza taraması açık kullanıcı oturumuyla başlamalı; seçili ürünler önizlenmeli ve gönderim onaylanmalıdır. Limited Use ilkesi veri kullanımını beyan edilen tek amaç ve görünür özellik gereğiyle sınırlar. [Limited Use politikası](https://developer.chrome.com/docs/webstore/program-policies/limited-use)
7. **Bildirim ve rozetlerde hassas içerik:** Araç çubuğu rozeti yalnız sayı/önem gösterebilir; ürün başlığı, tedarikçi veya token gibi içerik sistem bildiriminde varsayılan açık gösterilmemelidir.
8. **Site arayüzünü taklit etme:** TedarikApp sayfa içi katmanı kaynak sitenin yerel butonu sanılmamalı; marka adı, lacivert yüzey ve ayrı ikon sistemi açık olmalıdır.
9. **İzin ile özellik arasında kopukluk:** Kullanıcı neden izin istediğini o anda anlamalı; reddedilen izin temel kuyruğu veya ayarları bozmamalıdır.
10. **Ağır sayfa enjeksiyonu:** Arama sonuçlarının her kartına büyük bileşen koymak performans ve kalite riski yaratır. Sanal, tembel ve görünür kartla sınırlı işleme; açık oturum üst sınırı ve kapatma denetimi gerekir.

## V1.0'dan V3-K'ya geçişte karar kapıları

Bu vizyon doğrudan geliştirmeye açılmamalıdır. Şartnameye dönüşmeden önce aşağıdaki saha kanıtları istenmelidir:

1. V1.0'da günlük/haftalık yakalama sayısı ve tek ürün başına gerçek süre.
2. Yakalama hatalarının seçici, token, ağ, veri eksikliği ve kullanıcı iptali dağılımı.
3. Çevrimdışı kuyruğun gerçek kullanım sıklığı ve en uzun bekleme süresi.
4. Arama sonucu sayfasından tek tek açılan ürün sayısı; toplu seçimin beklenen tasarrufu.
5. Panelde yakalamadan sonra en sık yapılan düzeltmeler.
6. V3-F izleme verisinin ürün sayfasında karar değiştirip değiştirmediği.
7. Kullanıcının popup, sayfa içi buton ve panel arasında hangi yolu tercih ettiği.
8. Eklentinin sayfa performansına etkisi; p95 etkileşim ve bellek ölçümü.
9. Store incelemesinde izin, veri kullanımı veya tek amaçla ilgili gelen gerçek geri bildirim.
10. Her özellik için “eklenti mi, panel mi?” kararı; panelde yaşaması gereken iş eklentiye kopyalanmamalıdır.

## Kaynakça

### Emsal ürün kaynakları

- [Helium 10 Chrome Web Store](https://chromewebstore.google.com/detail/helium-10-for-amazon-sell/njmehopjdpcckochcggncklnlmikcbnb)
- [Helium 10 eklenti kılavuzu](https://kb.helium10.com/hc/en-us/articles/360049023813-How-Do-I-Install-and-Navigate-the-Chrome-Extension-An-Introduction-and-Overview)
- [Helium 10 planları](https://www.helium10.com/pricing/)
- [Jungle Scout gömülü ürün kartları](https://support.junglescout.com/hc/en-us/articles/360051811194-Extension-Embedded-Product-Cards-in-Search-Results)
- [Jungle Scout Extension ve web uygulaması ayrımı](https://support.junglescout.com/hc/es/articles/360008616534--Cu%C3%A1l-es-la-diferencia-entre-la-aplicaci%C3%B3n-web-de-Jungle-Scout-y-la-extensi%C3%B3n-para-Chrome)
- [Jungle Scout planları](https://www.junglescout.com/pricing/)
- [Keepa Chrome Web Store](https://chromewebstore.google.com/detail/keepa-amazon-price-tracke/neebplgakaahbhdphmkckjjcegoiijjo)
- [Keepa](https://keepa.com/)
- [AliPrice 1688 Chrome Web Store](https://chromewebstore.google.com/detail/1688%E4%BB%B7%E6%A0%BC%E8%BF%BD%E8%B8%AA%E5%99%A8/pkghjinojggjcpfnbpncpkbmpdijldla)
- [AliPrice kaynak bulma aracı](https://www.aliprice.com/Extension/)
- [SellerSprite Chrome Web Store](https://chromewebstore.google.com/detail/sellersprite-amazon-resea/lnbmbgocenenhhhdojdielgnmeflbnfb)
- [SellerSprite ürün listesi ve karşılaştırma](https://www.sellersprite.com/en/help/product-list-of-extension)
- [AliNiche Chrome Web Store](https://chromewebstore.google.com/detail/aliniche-aliexpress-produ/lmlkbclipoijbhjcmfppfgibpknbefck)
- [Grammarly Chrome Web Store yayıncı sayfası](https://chromewebstore.google.com/publisher/grammarly/u7431803589585a11dd7aea578e1005d0)
- [Grammarly tarayıcı eklentisi kılavuzu](https://support.grammarly.com/hc/en-us/articles/115000091592-How-does-Grammarly-s-browser-extension-work-)
- [1Password Chrome Web Store](https://chromewebstore.google.com/detail/1password-%E2%80%93-password-mana/aeblfdkhhhdcdjpifhhbdiojplfjncoa)
- [1Password tarayıcı başlangıç kılavuzu](https://support.1password.com/getting-started-browser/)

### Chrome / Store kaynakları

- [Chrome Side Panel API](https://developer.chrome.com/docs/extensions/reference/api/sidePanel)
- [Chrome izin bildirme rehberi](https://developer.chrome.com/docs/extensions/develop/concepts/declare-permissions)
- [Chrome Web Store Quality Guidelines](https://developer.chrome.com/docs/webstore/program-policies/quality-guidelines)
- [Chrome Web Store Limited Use](https://developer.chrome.com/docs/webstore/program-policies/limited-use)
- [MV3 uzak barındırılan kod ihlalleri](https://developer.chrome.com/docs/extensions/develop/migrate/remote-hosted-code)
- [Extension service worker yaşam döngüsü](https://developer.chrome.com/docs/extensions/develop/concepts/service-workers/lifecycle)
