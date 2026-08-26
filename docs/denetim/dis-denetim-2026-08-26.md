# Tedarikapp Derin Kod, Güvenlik ve Operasyon İnceleme Raporu

**Depo:** [tilbehome/tedarikapp](https://github.com/tilbehome/tedarikapp)  
**İncelenen sabit sürüm:** [`7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a`](https://github.com/tilbehome/tedarikapp/commit/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a) (`v0.11.5`)  
**İnceleme tarihi:** 26 Ağustos 2026  
**Kapsam:** PHP/Slim arka uç, MySQL/MariaDB veri katmanı, React/Vite panel, WXT/TypeScript Chrome eklentisi, kurulum ve kimlik doğrulama, medya, yedekleme/geri yükleme, dışa aktarma, CI/CD, bağımlılıklar, testler, GitHub yönetişimi ve dokümantasyon.

> Bu rapor, yukarıdaki commit'in statik ve davranışsal incelemesidir. Canlı üretim sistemine saldırı testi yapılmamış, gerçek kullanıcı verisi görülmemiştir. Dolayısıyla “hiç başka hata yoktur” garantisi vermez; buna karşılık kaynak kodda doğrulanabilen bulguları, olası etkileri ve önerilen düzeltmeleri mümkün olduğunca kapsamlı biçimde kaydeder.

## 1. Yönetici özeti

Proje sıradan bir prototipten daha olgun: veri tabanı migration disiplini, PHPStan/CS-Fixer/PHPUnit, gerçek MySQL ve MariaDB entegrasyon testleri, Playwright E2E, gitleaks ve bağımlılık denetimleri var. İncelenen commit'in GitHub Actions çalışması da tamamen yeşil. Bununla birlikte yeşil CI'ın yakalayamadığı iki kritik üretim hatası ve birkaç ciddi güvenilirlik/güvenlik açığı bulunuyor.

**Genel karar:** Mevcut haliyle yeni bir üretim dağıtımı için **koşullu durdurma** öneriyorum. En az F-01, F-02, F-03, F-04, F-06 ve F-07 düzeltilmeden “güvenli ve geri yüklenebilir üretim” kabulü verilmemeli.

| Öncelik | Adet | Anlamı |
|---|---:|---|
| P0 — Kritik | 2 | Veri bütünlüğü veya güvenlik sınırını doğrudan bozuyor; dağıtım öncesi düzeltme |
| P1 — Yüksek | 10 | Gerçek kullanıcı/veri kaybı, güvenlik veya operasyon kesintisi riski |
| P2 — Orta | 14 | Ölçek, dayanıklılık, savunma derinliği veya test açığı |
| P3 — Düşük | 7 | Bakım, yönetişim, doküman doğruluğu ve küçük sertleştirmeler |
| **Toplam** | **33** | Bir kısmı aynı kök nedene bağlıdır; düzeltmeler birlikte ele alınabilir |

### En önemli altı sonuç

1. **Arşiv modunda eklentiden gelen ana görseller bozuk kalıyor.** Üretim bileşen kurulumunda `CaptureApplier`'a `MediaService` verilmemiş. DB kalıcı `/media/...` adresini kaydediyor, fakat dosya `.tmp` olarak kalıyor.
2. **Kurulum kilidi bazı veri tabanı okuma arızalarında fail-open davranıyor.** `settings` sorgusu herhangi bir nedenle hata verirken `SELECT 1` başarılıysa sistem “kilitsiz” kabul ediliyor.
3. **Yedek, felaket kurtarma için eksik.** `public/media` hiç yedeklenmiyor; ayrıca `config/sözlük` yan dosyası ne off-site gönderiliyor ne panelden indirilebiliyor.
4. **Kurulum sırları HTTP üzerinden taşınabiliyor.** Var olan HTTPS kapısı bilinçli olarak kaldırılmış; kurulum çerezi de gerçek bir sunucu sırrı olmayan öngörülebilir bir önyükleme anahtarına dayanıyor.
5. **Uzak galeri görselleri API'de hatalı URL'ye dönüştürülüyor.** `https://...`, `/https://...` olarak sunuluyor.
6. **README'deki çevrimdışı eklenti kuyruğu uygulanmamış.** Ağ hatasında yakalama sadece hata veriyor; kalıcı kuyruk, tekrar deneme veya backoff yok.

## 2. İnceleme ve doğrulama yöntemi

- GitHub deposunun dal, commit, PR, issue, release, ruleset ve Actions durumu incelendi.
- Kaynak kod; güven sınırları, transaction'lar, idempotans, dosya yaşam döngüsü, SSRF, oturum/çerez, Host/URL üretimi, yedekleme ve geri yükleme açısından elle izlendi.
- `frontend`, `extension` ve `e2e` paketleri temiz bağımlılık kurulumuyla derlendi/tür denetiminden geçirildi.
- Üç Node çalışma alanında tam `npm audit` çalıştırıldı.
- Test kapsamının dosya ve işlev dağılımı incelendi; testlerin gerçek üretim composition'ını ne ölçüde kurduğu kontrol edildi.
- Dağıtım paketi üretme ve CI workflow'ları, eylem pinleme ve release varlıkları açısından incelendi.

### Doğrulama özeti

| Denetim | Sonuç |
|---|---|
| En son `main` Actions çalışması | [Başarılı](https://github.com/tilbehome/tedarikapp/actions/runs/32589326609); incelenen commit ile aynı SHA |
| PHP kalite kapıları | GitHub'da başarılı: PHPUnit, PHPStan seviye 6, CS-Fixer, Composer audit |
| Veri tabanları | GitHub'da MySQL 8.4 ve MariaDB 11.4 entegrasyonları başarılı |
| PHP uyumluluk işi | GitHub'da PHP 8.1 lint/sınırlı smoke başarılı; tam uyumluluk kanıtı değil (F-09) |
| E2E | GitHub'da Playwright başarılı; 6 spec dosyası |
| Panel | Yerelde TypeScript, ESLint ve production build başarılı |
| Eklenti | Yerelde TypeScript ve build başarılı; 31 Vitest testi geçti |
| npm güvenlik denetimi | `frontend`, `extension`, `e2e`: doğrudan/geçişli, dev dahil **0 bilinen açık** |
| Panel build uyarısı | `public/yonlendirme.js` modül olmadığı için Vite tarafından bundle edilemiyor (F-30) |

Yerel ortamda PHP, Composer ve MySQL ikilisi bulunmadığı için PHP testleri ikinci kez yerelde koşturulamadı. Bu bölümdeki PHP sonucu, aynı commit üzerindeki GitHub Actions kaydına dayanır. Yerel Node sürümü 24'tür; projenin CI hedefi Node 22'dir. Her iki durum raporun sınırlamasıdır.

## 3. Kritik bulgular — P0

### F-01 — Üretim composition'ı `MediaService`'i atlıyor; ana görseller `.tmp` kalıyor

**Kanıt:** [`AppBuilder.php:247-254`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Core/AppBuilder.php#L247-L254), [`CaptureApplier.php:45-75`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CaptureApplier.php#L45-L75), [`CaptureService.php:135-168`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CaptureService.php#L135-L168)

`CaptureService::prepareMedia()` ana görseli ertelenmiş modda indiriyor: DB'ye kaydedilecek kalıcı `/media/<rastgele>.<uzantı>` URL'sini üretirken fiziksel dosyayı `<ad>.<uzantı>.tmp` olarak bırakıyor. Dosyanın kalıcı ada taşınması `CaptureApplier::medyayiSonlandir()` içinde. Ancak gerçek uygulama kurulumunda `new CaptureApplier(...)` çağrısına son parametre olan `MediaService` hiç verilmemiş; parametre opsiyonel ve `null` olunca sonlandırma sessizce atlanıyor.

**Etkisi:** Arşiv modunda eklentiden doğrudan listeye alınan ve Gelen Kutusu'ndan uygulanan ürünlerin DB kaydı başarılı görünebilir, fakat ana görsel URL'sinin karşılığı yoktur. `.tmp` dosyaları da kalıcı biçimde disk kotası tüketir. Mevcut yetim dosya temizleyicisi yalnız nihai 32-hex dosya desenini taradığından `.tmp` dosyalarını yakalamaz.

**CI neden yakalamadı:** İlgili testler `CaptureApplier`'ı elle kurarken `MediaService` veriyor; gerçek `AppBuilder` wiring'ini arşiv modunda sınayan composition/HTTP testi yok.

**Düzeltme:**

1. `AppBuilder` içinde `$mediaService` parametresini zorunlu olarak geçir.
2. `CaptureApplier` constructor'ında `?MediaService = null` geriye uyumluluk kaçışını kaldır; yanlış wiring derleme/test zamanında patlasın.
3. Gerçek `AppBuilder` ile HTTP seviyesinde regresyon testi ekle: istek sonrası nihai dosya var, `.tmp` yok ve API URL'si gerçekten okunabilir olmalı.
4. Yaşı belirli eşiği aşmış `.tmp` dosyaları için güvenli bir bakım görevi ve tek seferlik temizlik komutu ekle.

### F-02 — `SetupLock`, `settings` okuma arızalarını “kilitsiz” sayabiliyor

**Kanıt:** [`SetupLock.php:55-104`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Setup/SetupLock.php#L55-L104)

`status()` içindeki `readFromDatabaseStrict()` çağrısı **herhangi bir** `Throwable` üretirse kod `SELECT 1` ile bağlantıyı yokluyor. Bağlantı çalışıyorsa doğrudan `STATE_UNLOCKED` döndürüyor. Niyet yalnız “temiz kurulumda `settings` tablosu henüz yok” durumunu ayırmak; uygulama ise şu olayları da kilitsiz sayabilir:

- `settings` tablosuna SELECT yetkisi yokken DB bağlantısının çalışması,
- beklenmeyen kolon/şema uyuşmazlığı,
- bozuk view/statement veya sürücü kaynaklı sorgu hatası,
- yalnız ilgili tabloyu etkileyen geçici bir hata.

**Etkisi:** Kurulu bir sistemde kurulum rotaları açılabilir. Sonraki adımların tamamının başarıyla istismar edileceği garanti değildir; yine de güvenlik sınırı “belirsizlikte kapalı” olması gerekirken açık davranıyor. Üstelik yorum satırı fail-closed iddiasında olduğu için operatör yanlış güvence alır.

**Düzeltme:** Yalnızca doğrulanmış “tablo yok” hata kodu/SQLSTATE'i veya `information_schema` sonucu kilitsiz kabul edilmeli. Tüm izin, kolon ve diğer SQL hataları `STATE_UNKNOWN`/503 üretmeli. MySQL ve MariaDB için tablo-yok, SELECT-yasak ve bozuk-şema testleri eklenmeli.

## 4. Yüksek öncelikli bulgular — P1

### F-03 — Yedekleme geri yüklenebilir bir sistem yedeği değil

**Kanıt:** [`BackupService.php:71-168`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/BackupService.php#L71-L168), [`SystemController.php:50-68`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Controllers/SystemController.php#L50-L68), [`bin/backup.php:49-66`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/bin/backup.php#L49-L66), [`docs/07-deploy-runbook.md:149`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/docs/07-deploy-runbook.md#L149)

Yedekleme iki dosya üretiyor: SQL için `.sql.enc`, `config.php/.env` ve özel sözlük için `.files.enc`. Ancak:

- `public/media` hiç pakete alınmıyor; runbook bunun alındığını söylüyor.
- Off-site FTP/SMTP yoluna yalnız `.sql.enc` gönderiliyor.
- Panel indirme doğrulaması yalnız `.sql.enc` ad desenini kabul ediyor; `.files.enc` kullanıcıya sunulmuyor.
- Restore testi esas olarak SQL'i doğruluyor; medya ve yan dosya ile tam sistem geri dönüşü kanıtlanmıyor.

**Etkisi:** Sunucu kaybında DB geri dönebilir ama arşivlenmiş ürün görselleri, özel sözlük ve yapılandırma yan dosyası kaybolabilir. DB'deki `/media/...` referansları kırılır; orijinal 1688 kaynağı süreli/değişken olduğundan her görsel yeniden indirilemeyebilir.

**Düzeltme:** DB + medya + gerekli config/sözlükleri manifestli, sürümlü ve tek atomik şifreli paket halinde üret; ya da bütün parçaları tek yedek seti kimliğiyle eksiksiz gönder. Her parçaya hash, boyut ve içerik manifesti ekle. Off-site aktarımın set bazında tamamlanmasını ve aylık boş bir ortamda uçtan uca geri yükleme tatbikatını zorunlu kıl.

### F-04 — Kurulum HTTPS olmadan sır kabul ediyor

**Kanıt:** [`SetupAppBuilder.php:109-122`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Core/SetupAppBuilder.php#L109-L122), [`RequirementChecker.php:94-104`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Setup/RequirementChecker.php#L94-L104), [`SetupHttpsGate.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Middleware/SetupHttpsGate.php)

HTTPS denetim middleware'i yazılmış olmasına rağmen route grubuna eklenmiyor; gereksinim kontrolü HTTPS'i açıkça zorunlu olmayan uyarı yapıyor. Kurulum sırasında DB kullanıcı adı/şifresi, admin parolası, TOTP secret ve APP_KEY ile ilişkili state taşınıyor.

**Etkisi:** HTTP kullanılan üretim kurulumunda ağ üzerindeki bir saldırgan sırları ve setup çerezini okuyabilir/değiştirebilir. HSTS başlığının HTTP yanıtında bulunması ilk bağlantıyı korumaz; `.htaccess` de HTTPS'e yönlendirmiyor.

**Düzeltme:** Üretimde HTTPS'i zorunlu tut; yalnız `localhost`/açıkça tanımlı development profiline istisna ver. Güvenilir reverse-proxy başlıklarını allowlist ile yorumla, HTTP'yi HTTPS'e yönlendir ve setup/auth çerezlerinde `Secure` kullan. Kurulum sihirbazında “devam et ama uyar” seçeneğini üretimden kaldır.

### F-05 — Kurulum state'i tahmin edilebilir önyükleme anahtarıyla istemcide ve süresiz tutuluyor

**Kanıt:** [`CookieSession.php:182-226`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Setup/CookieSession.php#L182-L226)

`config.php` oluşmadan önce çerez şifreleme anahtarı; uygulama yolu, PHP sürümü, hostname ve `composer.lock` mtime değerlerinden türetiliyor. Bunlar gerçek bir sır değil; bazıları hata/diagnostic çıktısından veya dağıtım paketinden öğrenilebilir. Kod ayrıca APP_KEY oluşsa bile eski bootstrap anahtarını çözme adayları arasında tutuyor. Çerez için içerik seviyesinde `issued_at`/TTL bulunmuyor.

**Etkisi:** Özellikle F-04 ile birleştiğinde kurulum state'inin gizlilik ve yaşam süresi güvencesi zayıf. State içinde DB parolası ve admin kurulumu bilgileri bulunabiliyor.

**Düzeltme:** Sırları istemci çerezinde tutma; kısa ömürlü, sunucu tarafı bir kurulum state deposu kullan. Disk yazılamıyorsa DB'de tek kullanımlık/tahmin edilemez setup oturumu veya operatörün sağladığı bootstrap secret kullanılabilir. 10–20 dakikalık TTL, nonce, tek kullanım ve kurulum bileti bağlama ekle; APP_KEY sonrası bootstrap anahtarı kabulünü kes.

### F-06 — Uzak galeri görselleri `/https://...` olarak sunuluyor

**Kanıt:** [`ProductRepository.php:467-499`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Models/ProductRepository.php#L467-L499), [`ListPresenter.php:118-169`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/ListPresenter.php#L118-L169)

Yakalama sonrası ana görsel dışındaki galeriler `path='https://...'` ve `storage_mode='remote'` olarak yazılıyor. `images()` ise storage mode'a bakmadan her `path` başına `/` ekliyor. Sonuç `/https://cbu...` oluyor.

**Etkisi:** API, panel, paylaşım ve dışa aktarma yüzeylerindeki uzak galeri görselleri kırık görünür; ancak daha sonra yerel medyaya taşınırsa düzelebilir. Mevcut test DB kaydını kontrol ediyor ama Presenter/API çıktısını sınamıyor.

**Düzeltme:** `storage_mode/source_url` alanlarını sorguya dahil et; remote URL'yi olduğu gibi, local path'i `/` ile sun. Capture → API → share/export zincirinde uzak ve yerel galeri regresyon testleri ekle.

### F-07 — İdempotans yalnız doğrudan-liste yolunda atomik

**Kanıt:** [`ExtensionController.php:37-118`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Controllers/ExtensionController.php#L37-L118), [`CaptureApplier.php:89-160`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CaptureApplier.php#L89-L160)

Controller önce `findByCaptureId()` yapıyor. Hedef liste varsa `CaptureApplier` UNIQUE satırını transaction'ın ilk yazımı olarak kullanıyor ve yarışı yönetiyor. Ancak doğrulama-hatası ve varsayılan Gelen Kutusu yolları doğrudan `inbox->create()` çağırıyor. İki eşzamanlı tekrar isteği de ön kontrolü geçebilir; UNIQUE yarışını kaybeden istek ham PDO hatası/500 alır.

**Etkisi:** Eklenti tekrar denemelerinde “aynı `capture_id` ilk sonucu döndürür” sözleşmesi yaygın pending/error yolunda bozulur. F-10'daki gerçek çevrimdışı kuyruk eklendiğinde bu yarış daha sık görülecektir.

**Düzeltme:** Her statü için tek atomik `insert-or-select`/duplicate recovery primitive'i kullan. Hata ve pending kayıtları da aynı reservation servisine taşı. Eşzamanlı iki bağlantı ile MySQL ve MariaDB yarış testi ekle.

### F-08 — `APP_URL` yazımı sessizce başarısız olup Host başlığına geri düşebiliyor

**Kanıt:** [`SetupController.php:361-375`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Controllers/SetupController.php#L361-L375), [`SetupController.php:653-669`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Controllers/SetupController.php#L653-L669), [`SetupController.php:753-766`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Controllers/SetupController.php#L753-L766), [`AppUrl.php:36-53`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Core/AppUrl.php#L36-L53)

Kurulum `APP_URL` ayarını yazarken oluşan her hatayı yutup kurulumu yine kilitliyor. `AppUrl` değeri yoksa veya placeholder ise request URI authority'sine, yani istemcinin etkileyebildiği Host'a geri dönüyor. Ayrıca regex yalnız URL'nin başlangıcını doğruluyor; sonuna eklenen geçersiz veri için tam eşleşme/`FILTER_VALIDATE_URL` yok.

**Etkisi:** Paylaşım bağlantıları, QR ve kanal metinleri saldırganın Host'u veya hatalı bir URL ile üretilebilir. Kod yorumlarında daha önce çözüldüğü söylenen Host poisoning riski, degraded kurulumda devam ediyor.

**Düzeltme:** Gerçek `APP_URL` başarıyla yazılmadan kurulum kilitlenmemeli. Tam, kanonik URL doğrulaması yap; userinfo, fragment, kontrol karakteri ve beklenmeyen path/query'yi reddet. Üretimde APP_URL yoksa link üretimini açık hata ile durdur; Host fallback'ini yalnız açık development profiline sınırla.

### F-09 — İlan edilen PHP 8.1 desteği dependency sözleşmesiyle çelişiyor

**Kanıt:** [`composer.json:6-24,44-48`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/composer.json#L6-L48), [`composer.lock:1590-1605`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/composer.lock#L1590-L1605), [CI PHP 8.1 işi](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/.github/workflows/ci.yml#L190-L245)

Kök paket `php:^8.1` diyor; lock'taki `robthree/twofactorauth v3.0.3` ise `php >=8.2` istiyor. Composer runtime platform check global olarak kapalı. CI, PHP 8.1'de platform gereksinimini bilinçli biçimde ignore ediyor; vendor lint ve birkaç smoke kontrolü çalıştırıyor fakat auth/TOTP, export, backup, medya ve tam HTTP test setini koşturmuyor.

**Etkisi:** Bugünkü kod bazı 8.1 kurulumlarında çalışsa bile bu bir paket sözleşmesi değildir. Bir patch dependency sürümü 8.2 özelliği kullanmaya başladığında Composer kurulumda durdurmayacak ve üretim çalışma zamanında kırılabilir.

**Düzeltme:** Tercihen asgari PHP'yi 8.2'ye yükselt ve platform check'i geri aç. 8.1 zorunluysa 8.1 destekleyen dependency sürümlerini pinle/değiştir, Composer platformunu 8.1'e kilitle ve kritik tam test setini 8.1'de çalıştır.

### F-10 — Belgelenen çevrimdışı eklenti kuyruğu yok

**Kanıt:** [`README.md:25-33`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/README.md#L25-L33), [`extension/core/api.ts:13-71`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/extension/core/api.ts#L13-L71), [`docs/04-teknik-tasarim.md:238`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/docs/04-teknik-tasarim.md#L238)

README “panel kapalıyken kuyrukta bekletir, bağlantı gelince gönderir” diyor. Kaynakta ise `chrome.storage.local` yalnız `panelUrl` ve token'ı saklıyor; `fetch` başarısızsa hata fırlatılıyor. Capture payload outbox'ı, retry, alarm, exponential backoff, dead-letter veya kullanıcıya bekleyen iş sayacı yok.

**Etkisi:** Ağ/panel kesintisinde kullanıcı ürünün kaydedildiğini düşünebilir veya yakalamayı yeniden yapmak zorunda kalır. Tekrar denemeler F-07 yarışını tetikleyebilir.

**Düzeltme:** `capture_id` anahtarlı kalıcı `chrome.storage.local` outbox, retry/backoff+jitter, `chrome.alarms` veya service-worker uyanışı, maksimum deneme/dead-letter ve görünür kuyruk durumu ekle. Tamamlanana kadar README ve teknik tasarımdaki “var” ifadelerini “planlanan” olarak düzelt.

### F-11 — `main` korumasız; yeşil CI merge için zorunlu değil

**Kanıt:** [Branches](https://github.com/tilbehome/tedarikapp/branches), [Actions](https://github.com/tilbehome/tedarikapp/actions)

GitHub API incelemesinde `main` için branch protection kapalı ve repository ruleset listesi boş bulundu. CI kapsamlı olsa da doğrudan push, force-push veya CI çalışmadan değişiklik alma teknik olarak engellenmiyor.

**Etkisi:** İnsan hatası veya hesabın ele geçirilmesi tüm test/güvenlik kapılarını atlayabilir. Tek geliştiricili proje olması bu riski azaltır ama ortadan kaldırmaz.

**Düzeltme:** PR zorunluluğu, gerekli başarılı check'ler, force-push/dal silme yasağı, mümkünse signed commit/tag, en az bir review veya kritik alanlar için CODEOWNERS kuralı ekle. Yönetici bypass'ını olay günlüğü/istisna prosedürüyle sınırla.

### F-12 — Capture isteği içinde senkron dış ağ görsel indirmesi yapılıyor

**Kanıt:** [`CaptureService.php:124-159`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CaptureService.php#L124-L159), [`CurlMediaFetcher.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CurlMediaFetcher.php)

DB transaction'ı dışında olması doğru; ancak 10 saniyelik connect ve 25 saniyelik toplam dış istek süresi yine kullanıcı HTTP isteğinin içinde. Yavaş 1688/CDN, paylaşımlı PHP worker'ını ve eklenti isteğini uzun süre tutabilir.

**Etkisi:** Proxy/PHP timeout, worker tükenmesi, kullanıcı tekrarları ve duplicate yarışları; toplu kullanımda kapasite düşüşü.

**Düzeltme:** Capture kaydını ve kaynak URL'yi önce hızlı/atomik kaydet; medya indirmesini DB tabanlı outbox + cron worker'a taşı. UI'da `pending/downloaded/failed` durumu ve retry sun. Acil ara çözüm olarak timeout'ları düşür ve eşzamanlı indirme limitini uygula.

## 5. Orta öncelikli bulgular — P2

### F-13 — Medya `rename()` başarısızlığı yutuluyor

**Kanıt:** [`MediaService.php:100-114`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/MediaService.php#L100-L114)

`@rename()` sonucu kontrol edilmiyor. DB commit edildikten sonra dosya taşıma; izin, disk doluluğu veya dosya sistemi hatasıyla başarısız olursa ürün yine nihai URL'yi taşır. `@chmod` da sonucu gizler.

**Öneri:** Hata döndür/throw et, kalıcı retry/outbox kaydı oluştur ve “DB kaydı var ama medya finalize olmadı” durumunu sistem statüsünde görünür yap. Cross-filesystem durumunda copy+fsync+atomic rename stratejisini değerlendir.

### F-14 — Bakım, `.tmp` ve kırık medya referanslarını onarmıyor

**Kanıt:** [`MediaService.php:298-365`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/MediaService.php#L298-L365), [`MaintenanceTasks.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/MaintenanceTasks.php)

Yetim taraması nihai ad desenini eşliyor; `.tmp` dosyaları kapsam dışı. Nightly maintenance bozuk DB→dosya referanslarını tarayıp kaynak URL'den otomatik onarmıyor.

**Öneri:** Yaş eşiğiyle stale temp purge, DB→disk ve disk→DB çift yönlü integrity scan, güvenli retry ve metrik/uyarı ekle. İlk dağıtımda F-01'in bırakmış olabileceği temp dosyalar için dry-run raporlu tek seferlik komut sağla.

### F-15 — SSRF DNS doğrulaması ile gerçek bağlantı arasında TOCTOU var

**Kanıt:** [`UrlGuard.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/UrlGuard.php), [`CurlMediaFetcher.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CurlMediaFetcher.php)

Kod hostu çözüp private/reserved IP'leri reddediyor; ardından cURL aynı hostu tekrar çözüyor. DNS cevabı iki an arasında değişirse doğrulanan IP ile bağlanılan IP farklı olabilir. Dar host allowlist olasılığı azaltır ama DNS/subdomain kontrolü kaybedilirse koruma tam değildir.

**Öneri:** Doğrulanmış IP'yi `CURLOPT_RESOLVE` benzeri yöntemle bağlantıya pinle; TLS Host/SNI'yi koru. Her redirect'i yeniden doğrula ve yeniden pinle. IPv4/IPv6, çoklu A/AAAA ve DNS rebinding testleri ekle.

### F-16 — Capture kaynak ve galeri URL doğrulaması eksik

**Kanıt:** [`CaptureService.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/CaptureService.php), [`ProductRepository.php:467-479`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Models/ProductRepository.php#L467-L479)

`source.url` ve galeri girdileri büyük ölçüde `https://` başlangıcı + uzunluk ile kontrol ediliyor. Kaynak platform/host eşleşmesi, tam URL parse'ı, beklenen 1688 offer biçimi ve bütün galeri hostları için `UrlGuard` uygulanmıyor. Bazı sayısal/karmaşık alanlarda da (koli adedi, SKU shape, görsel adedi) sunucu sınırları zayıf.

**Etkisi:** Ürün linklerinde phishing/istenmeyen dış alan; CSP nedeniyle kırık görsel; aşırı payload/veri kalitesi sorunu.

**Öneri:** Platforma göre tam domain+path politikası, `parse_url`/`FILTER_VALIDATE_URL`, userinfo/fragment reddi, tüm medya URL'lerinde aynı allowlist ve alan başına açık uzunluk/adet/numeric bounds uygula.

### F-17 — Remember-me token rotasyonunda eşzamanlı istek yarışı

**Kanıt:** [`RememberTokenService.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Auth/RememberTokenService.php)

Doğrulama ile yeni validator hash'inin yazımı tek compare-and-swap değildir. Aynı eski çerezle paralel iki istek ikisi de doğrulanabilir; son DB yazımı kazanırken tarayıcı ilk response çerezini saklayabilir. Sonraki istek bunu token hırsızlığı sanıp tüm token'ları iptal edebilir.

**Öneri:** `UPDATE ... WHERE id=? AND validator_hash=?` CAS veya row lock+transaction kullan; yalnız bir istek rotate edebilsin. Paralel request testi ekle.

### F-18 — GitHub Actions'ın çoğu değişebilir tag'e pinli

**Kanıt:** [`.github/workflows/ci.yml`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/.github/workflows/ci.yml)

Workflow'da bazı güvenlik-hassas adımlar SHA'ya pinlenmiş olsa da `actions/checkout@v7`, `shivammathur/setup-php@v2`, `actions/cache@v4`, `actions/setup-node@v7` ve bazı `upload-artifact` çağrıları hareketli tag kullanıyor. gitleaks binary'si sürüm numarasıyla indiriliyor fakat checksum/imza doğrulanmıyor.

**Öneri:** Tüm third-party actions'ı tam commit SHA'ya pinle; Renovate/Dependabot yorumuyla insan okunur sürümü koru. İndirilen binary'nin yayınlanan SHA256/imzasını doğrula. Workflow token izinleri asgari kalmalı; mevcut `contents: read` yaklaşımı olumlu.

### F-19 — Dependabot iki npm çalışma alanını izlemiyor

**Kanıt:** [`.github/dependabot.yml`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/.github/dependabot.yml)

Composer, `/frontend` ve GitHub Actions kapsanıyor; `/extension` ve `/e2e` yok. İnceleme anında altı Dependabot dalı ve çok sayıda eski özellik dalı da duruyordu; açık PR yoktu.

**Öneri:** İki eksik dizini ekle, update gruplama ve aylık bakım SLA'sı tanımla. Birleştirilmiş/kapanmış çalışma dallarını güvenli biçimde temizle.

### F-20 — Tag var, yayımlanmış GitHub Release ve üretim artefaktı yok

**Kanıt:** [Releases](https://github.com/tilbehome/tedarikapp/releases), [v0.11.5 tag](https://github.com/tilbehome/tedarikapp/tree/v0.11.5), [CI workflow](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/.github/workflows/ci.yml)

API'de hiç GitHub Release bulunmadı. CI production ZIP üretiyor ancak bunu kalıcı bir release asset olarak yüklemiyor; yalnız eklenti ZIP'i artefakt oluyor.

**Etkisi:** Dağıtılan paketin hangi commit/bağımlılık setinden geldiğini kanıtlama, immutable rollback ve checksum doğrulama zayıf.

**Öneri:** Signed tag tetiklemeli release workflow; production ZIP, extension ZIP, SHA256, dependency/SBOM ve kısa migration/rollback notu yayımlasın. Ortam onayı olmadan canlı deploy yapmasın.

### F-21 — Panel ve eklenti kritik davranışlarında test boşlukları var

**Kanıt:** [`frontend`](https://github.com/tilbehome/tedarikapp/tree/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/frontend), [`extension/tests`](https://github.com/tilbehome/tedarikapp/tree/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/extension/tests), [`e2e`](https://github.com/tilbehome/tedarikapp/tree/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/e2e)

78 PHP test dosyası güçlü bir temel. Buna karşılık panelde unit/component test paketi yok; CI typecheck+lint+build ile 6 Playwright spec'e güveniyor. Eklentide 31 test var ama yalnız iki test dosyası büyük ölçüde parser/format katmanını kapsıyor; background, API, form, storage, ağ hatası ve gelecek queue davranışı kapsam dışı.

**Öneri:** Panel için Vitest + React Testing Library; özellikle store/hook, para biçimleme, form doğrulama, optimistic state ve hata durumları. Eklenti için `chrome.*` mock'larıyla API/background/storage/retry testleri. Kritik akışlarda branch/behavior coverage eşiği kullan; salt toplam yüzdeyi hedef yapma.

### F-22 — Liste sunumu N+1 sorgu yapıyor ve pagination yok

**Kanıt:** [`ListPresenter.php:62-109,118-181`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/ListPresenter.php#L62-L181)

Her liste için ürünler ve son export ayrı okunuyor; `lists()` her satırda `list()` çağırıyor. Her ürün sunumunda galeri ayrıca sorgulanıyor. Koleksiyon yollarında net LIMIT/OFFSET/cursor sınırı yok.

**Etkisi:** Liste/ürün büyüdükçe 2N ve N+1 sorgular, büyük JSON ve export/share gecikmesi; paylaşımlı hostingde timeout.

**Öneri:** Liste özetlerini aggregate sorguyla, ürün galerilerini toplu `WHERE product_id IN (...)` ile yükle. API'de cursor veya sayfa+limit, üst limit ve total/count sözleşmesi ekle. Gerçekçi 1k/10k ürün verisiyle query-count ve süre testi yap.

### F-23 — 40 megapiksel görsel sınırı paylaşımlı hosting için yüksek

**Kanıt:** [`MediaService.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/MediaService.php)

Sıkıştırılmış dosya küçük olsa bile 40 MP görsel GD'de ham RGBA olarak yaklaşık 160 MB; decode, kopya ve encoder ile bunun belirgin üstüne çıkabilir. 128/256 MB `memory_limit` ortamında OOM, PHP process ölümü ve yarım temp dosyası mümkündür.

**Öneri:** Pixel ve tek eksen limitini daha düşük/configurable yap; `memory_limit` ve formatın kanal maliyetinden güvenli bütçe hesapla. Decode öncesi metadata kontrolü sürsün; büyük/bozuk örneklerle gerçek memory-limit testi ekle.

### F-24 — SMTP off-site yedek tüm dosyayı bellekte base64 ediyor

**Kanıt:** [`BackupOffsite.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/BackupOffsite.php)

SQL zaten memory dump+encrypt ediliyor; SMTP yolu dosyayı yeniden tamamen okuyup base64 gövdesi kuruyor. DB büyüdükçe 4/3 base64 maliyeti ve MIME/string kopyaları OOM yaratabilir. Mail eki boyut limiti de güvenilir off-site strateji değildir.

**Öneri:** Tercihen streaming SFTP/object storage; boyut sınırı ve açık hata. SQL dump, şifreleme ve aktarımı stream tabanlı yap. SMTP yalnız küçük yedekler için kontrollü fallback olsun.

### F-25 — Chunked/uzun request body için uygulama içi kesin üst sınır yok

**Kanıt:** [`JsonRequest.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Middleware/JsonRequest.php)

Middleware Content-Length veya bilinen stream boyutunu denetliyor; boyutu bilinmeyen chunked gövde alttaki PHP/web server sınırlarına kalabiliyor.

**Öneri:** Apache/PHP `LimitRequestBody` ve `post_max_size` dağıtım manifestinde zorunlu olsun; uygulamada bounded stream/read yaklaşımı veya erken kapatma ekle. Chunked aşım entegrasyon testi yap.

### F-26 — `public/tani.php` üretimde bilgi sızdırabilir

**Kanıt:** [`public/tani.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/public/tani.php), [`bin/release.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/bin/release.php)

Diagnostic dosya PHP sürümü/SAPI, mutlak uygulama/docroot yolları ve config/.env varlığı gibi bilgileri açıklar. Standart release script'i bunu paketten dışlıyor ve CI bunu kontrol ediyor; fakat repository veya manuel kopya ile deploy edilirse webden erişilebilir.

**Öneri:** Diagnostic'i public docroot dışına taşıyıp CLI yap veya admin+tek kullanımlık tanı bileti arkasına al. Webde kalacaksa production'da varsayılan 404 ve açık feature flag uygula.

## 6. Düşük öncelikli bulgular — P3

### F-27 — Paylaşım erişim kodunun düz metin kopyası DB'de tutuluyor

**Kanıt:** [`ListRepository.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Models/ListRepository.php), [migrations](https://github.com/tilbehome/tedarikapp/tree/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/migrations)

Doğrulama için HMAC bulunmasına rağmen kullanıcıya yeniden gösterebilmek amacıyla `share_key_plain` tutuluyor. Ayrıca tek cookie adı farklı listelerin unlock bilgisini birbirinin üzerine yazabiliyor.

**Öneri:** Yeniden gösterim gerekiyorsa APP_KEY türeviyle authenticated encryption kullan; doğrulama hash'i ayrı kalsın. Cookie'yi liste token/hash kapsamına bağla veya birden çok yetkiyi güvenli sunucu oturumunda tut.

### F-28 — Hız sınırı count-then-insert ve log hatasında fail-open

**Kanıt:** [`ExtensionAuth.php`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Middleware/ExtensionAuth.php)

Paralel istekler aynı sayımı görüp limiti aşabilir. Activity/log tablosu hatası sınırlamayı devre dışı bırakabilir; rate limit için audit tablosuna bağımlılık iki sorumluluğu karıştırıyor.

**Öneri:** Ayrı anahtarlı rate bucket tablosu ve atomik upsert/CAS; cache yoksa DB transaction. Hata politikasını endpoint riskine göre fail-closed veya düşük acil limit olarak tanımla.

### F-29 — Yedek adı saniye hassasiyetinde; eşzamanlı çağrı üzerine yazabilir

**Kanıt:** [`BackupService.php:81-101`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/app/Services/BackupService.php#L81-L101)

Aynı saniyede cron+manuel veya iki manuel çağrı aynı dosya adını üretir. Kilit/exclusive create olmadığından biri diğerini overwrite edebilir; SQL ve `.files` parçası farklı çağrılardan eşleşebilir.

**Öneri:** Mikro saniye+random suffix/UUID, `fopen(..., 'x')` veya process lock kullan; set kimliğini manifestte doğrula.

### F-30 — Panel build'i uyarılı ve yönlendirme script'i hashlenmiyor

**Kanıt:** [`frontend/index.html`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/frontend/index.html), [`frontend/public/yonlendirme.js`](https://github.com/tilbehome/tedarikapp/blob/7cce9db7d40efc7d3c1f6ce6b05c2281d7c32e7a/frontend/public/yonlendirme.js)

Vite, modül olmayan `/panel/yonlendirme.js` script'ini bundle edemediğini bildiriyor. Public kopyalama nedeniyle dosya dağıtıma gelse de hashing/minification/dependency graph dışında kalıyor.

**Öneri:** Bilinçli klasik script ise build config ve testte açık allowlist ile belgele; mümkünse module entry'ye çevirip Vite'a dahil et. CI'da yeni build uyarılarını hata yap.

### F-31 — Büyük sınıflar composition ve regresyon riskini artırıyor

Öne çıkan örnekler: `SetupController` ~858 satır, `SharePage` ~795, `ProductController` ~777, `ListDetailScreen` ~723, `Settings` ~587, `Xlsx` ~547, `AppBuilder` ~535, `ProductRepository` ~504, `Migrator` ~489.

F-01 tam olarak büyük manuel composition kökünde oluşan türden bir wiring hatasıdır.

**Öneri:** Use-case bazlı servis/controller ayrımı, typed DTO'lar, composition factory/testleri ve repository read/write ayrımı. Sırf satır sayısı için bölme; transaction/güvenlik sınırlarını görünür yapan modüller hedeflenmeli.

### F-32 — Dokümantasyonun bazı kritik iddiaları kodla uyuşmuyor

**Örnekler:**

- README ve teknik tasarım çevrimdışı kuyruğu var gösteriyor; kodda yok (F-10).
- Deploy runbook `public/media` yedeği diyor; kod almıyor (F-03).
- Bazı belgeler yapılandırma sırlarını yalnız `.env` ile tarif ederken güncel model `config.php` + DB settings.
- `TECH-BASELINE` Axios'tan söz ediyor; panel API istemcisi native `fetch` kullanıyor.
- `docs/09` bazı Gelen Kutusu işlerini gelecek olarak gösterirken kodda uygulanmış.
- Migration yorumları galeri medyasının taşındığını ima edebiliyor; ilk yazım remote kalıyor.

**Öneri:** Release CI'a docs capability matrix testi ekle. README özellikleri yalnız otomatik kabul testi veya açık “planlanan” etiketiyle yayımlansın. Operasyon runbook'u restore testinin gerçek manifestinden türetilsin.

### F-33 — Yönetişim ve güvence katmanlarında eksikler

İnceleme anında GitHub Issues boş, CODEOWNERS yok, açık Dependabot PR'ı yok ve eski dallar duruyor. CI'da CodeQL/SAST, coverage trend/eşik, SBOM, lisans raporu veya artifact provenance/imza yok. Repo public iken `composer.json` lisansı proprietary ve kökte dağıtım şartlarını açıklayan LICENSE/NOTICE bulunmuyor.

**Öneri:** Issue/bug/security şablonları, CODEOWNERS, düzenli triage; CodeQL veya eşdeğer PHP/JS SAST; CycloneDX/SPDX SBOM; release provenance ve imza. Proprietary dağıtım niyeti için açık LICENSE/NOTICE metni ekle.

## 7. Olumlu bulgular

Sorunlar önemli olsa da aşağıdaki temel çizgi güçlüdür ve korunmalıdır:

- İncelenen commit'te PHP kalite kapıları, gerçek MySQL/MariaDB entegrasyonları, üretim profili ve Playwright E2E yeşil.
- Composer ve üç npm alanında tarama anında bilinen güvenlik açığı görünmüyor.
- Prepared statement kullanımı yaygın; transaction ve idempotans için bilinçli mimari çalışma yapılmış.
- CSRF, session/auth middleware ayrımı, hash'li extension/remember token'ları, Argon2id ve TOTP encryption mevcut.
- CSP, HSTS, X-Frame-Options, nosniff, referrer/noindex gibi güvenlik başlıkları düşünülmüş.
- Medya için allowlist, private IP reddi, redirect kontrolü, yeniden kodlama ve rastgele dosya adı gibi doğru savunma katmanları var.
- Gitleaks, Composer/npm audit, lockfile'lar, release dosya allowlist'i ve migration şema kontrolleri iyi uygulamalar.
- Güvenlik bildirim süreci `SECURITY.md` içinde tanımlı.
- Kodda birçok kararın “neden”i yorum ve karar kayıtlarında açıklanmış; bakım açısından değerli.

## 8. Önerilen düzeltme sırası

### İlk 48 saat — dağıtım blokajları

1. **F-01:** `MediaService` wiring + constructor zorunluluğu + AppBuilder arşiv modu regresyon testi.
2. **F-02:** SetupLock yalnız doğrulanmış table-not-found durumunda kilitsiz; diğer bütün hatalarda kapalı.
3. **F-06:** remote/local galeri URL üretimini düzelt ve API/share testi ekle.
4. **F-07:** bütün capture yollarında atomik idempotency primitive'i.
5. **F-03:** Mevcut üretim medyası için hemen bağımsız snapshot al; yedek setini DB+media+files olarak tamamla.
6. Üretim diskinde `.tmp` envanterini **önce dry-run** ile çıkar; DB/source eşlemesi yapılmadan körlemesine silme.

### 1 hafta — güvenlik ve operasyon

1. **F-04/F-05:** HTTPS-only setup, server-side kısa ömürlü kurulum state'i.
2. **F-08:** APP_URL zorunluluğu ve Host fallback'in üretimde kaldırılması.
3. **F-09:** PHP hedef kararını netleştir; 8.2 minimum veya gerçek 8.1 full-suite.
4. **F-11:** Branch protection/ruleset ve required checks.
5. **F-12/F-13/F-14:** Medya outbox/finalization/integrity bakım zinciri.
6. Yedekten boş ortama DB+medya+config+sözlük geri yükleme tatbikatı yap ve sonucu kaydet.

### 2–4 hafta — dayanıklılık ve kalite

1. **F-10:** Eklenti çevrimdışı kuyruğu ve retry UX'i.
2. **F-15/F-16:** DNS pinleme ve URL/payload doğrulama sertleştirmesi.
3. **F-17:** Remember-token CAS.
4. **F-18/F-19/F-20:** Supply-chain pinleme, eksik Dependabot alanları, immutable release artefaktı/SBOM.
5. **F-21/F-22/F-23:** UI/extension testleri, pagination/batch query, media memory budget.

### Sonraki bakım döngüsü

F-24–F-33: streaming backup, request body sınırları, diagnostic erişimi, paylaşım kodu şifreleme, atomik rate limit, yedek adı, uyarısız build, modülerleştirme, docs doğrulama ve yönetişim.

## 9. Düzeltme sonrası zorunlu kabul testleri

| Alan | Kabul testi |
|---|---|
| Medya composition | Gerçek `AppBuilder` üzerinden capture; DB URL var, nihai dosya var, `.tmp` yok; rollback'te temp yok |
| Galeri | Remote ve local galeri URL'leri panel/API/share/export'ta geçerli |
| Setup lock | Tablo yok → setup açık; DB kapalı/SELECT yasak/kolon bozuk → setup 503 ve kapalı |
| HTTPS | Üretimde bütün secret setup uçları HTTP'de reddedilir/yönlenir; Secure cookie doğrulanır |
| Kurulum state | TTL, tek kullanım, replay reddi, APP_KEY geçişi ve secret loglanmaması |
| Idempotans | Pending, error ve assigned yollarında 10 paralel aynı `capture_id`: tek kayıt, tüm yanıtlar aynı sonucu verir |
| Backup/restore | Boş sunucuda tek yedek setinden DB+media+config+sözlük; hash/manifest doğrulaması; örnek görsel HTTP 200 |
| PHP sürümü | Desteklenen minimum sürümde auth+2FA, capture/media, export, backup, migration ve HTTP testleri |
| Eklenti kuyruğu | Panel kapalıyken capture kalıcı; browser restart sonrası duruyor; panel gelince bir kez gönderiliyor |
| SSRF | DNS rebinding, çoklu A/AAAA, redirect-to-private ve mixed public/private cevaplar reddediliyor |
| Ölçek | 1.000+ ürünlü listede query count sabit/bounded; pagination ve export süresi bütçe içinde |
| Release | Tag'den immutable ZIP, checksum, SBOM; temiz ortamda kurulum ve rollback kanıtı |

## 10. Sonuç

Tedarikapp'ın temel mühendislik yaklaşımı iyi ve CI kapsamı benzer ölçekli projelerin üzerindedir. Ana risk, testlerin çoğunlukla sınıfları doğru bağımlılıklarla elle kurması ve böylece gerçek üretim composition'ındaki hataları kaçırmasıdır. İkinci ana risk, “disk yazılamayan paylaşımlı hosting” hedefi için geliştirilen fail-open/fallback davranışlarının güvenlik ve veri bütünlüğü sınırlarını aşmasıdır. Üçüncü ana risk de arşiv medyasının iş açısından kalıcı veri olmasına rağmen yedek setine dahil edilmemesidir.

Önce P0/P1 maddeleri ve bunların kabul testleri tamamlanmalı; ardından CI'ın yeşil olması tekrar güvenilir bir dağıtım sinyali haline gelir. Özellikle F-01 için yalnız bir satırlık wiring düzeltmesi yeterli kabul edilmemeli: mevcut `.tmp` envanteri, bozuk DB medya referansları ve full-composition regresyon testi birlikte ele alınmalıdır.
