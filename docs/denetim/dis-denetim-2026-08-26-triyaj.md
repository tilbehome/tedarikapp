# DIŞ DENETİM #2 — TRİYAJ (26 Ağu 2026)

**Girdi:** `docs/denetim/dis-denetim-2026-08-26.md` (33 bulgu) — rapor
`main@7cce9db` (v0.11.5) üzerinde yazıldı.
**Triyaj tabanı:** `v3-faz1@a812905` (rc7) — rapordan **sonraki** 6 iş emri turunu
içerir (İE#21 + D1–D11 saha düzeltmeleri).
**Yöntem:** her bulgu rc7 ağacında **kod okunarak** değerlendirildi; F-01 için
ayrıca gerçek `AppBuilder` üzerinden çalıştırmalı kanıt üretildi (§2).
**Kod değişikliği YAPILMADI.** rc7 paketi ve damgası değişmedi.

> Dürüstlük notu: raporun satır numaraları `7cce9db`e aittir; aşağıdaki kanıt
> satırları rc7'nin (`a812905`) kendi satırlarıdır. Bir bulgu "KAPANDI" ise
> hangi turda kapandığı yazılıdır; "doğrulanmadı" yazan satır, incelemenin o
> maddede yüzeysel kaldığını söyler — kapandı ya da geçersiz demek DEĞİLDİR.

---

## 1. TRİYAJ TABLOSU

| # | Rapor iddiası (özet) | rc7'de durum | Kanıt (rc7) | Önerilen konum |
|---|---|---|---|---|
| **F-01** | Üretim composition'ı `MediaService`'i atlıyor; ana görsel `.tmp` kalıyor | **HÂLÂ GEÇERLİ (P0)** | `app/Core/AppBuilder.php:263-275` — `CaptureApplier`e `media` verilmiyor (yorum "bilinçli" diyor, yanlış) · `app/Services/CaptureApplier.php:69-73` erken `return` · **çalıştırmalı kanıt §2.1** | **rc8** |
| **F-02** | `SetupLock` settings okuma arızasını "kilitsiz" sayıyor | **HÂLÂ GEÇERLİ (P0)** | `app/Setup/SetupLock.php:65-92` — `catch (Throwable)` → `connectionResponds() ? STATE_UNLOCKED : STATE_UNKNOWN`; hata SINIFI ayırt edilmiyor | **rc8** |
| **F-03** | Yedek geri yüklenebilir sistem yedeği değil (medya yok, off-site yalnız SQL) | **HÂLÂ GEÇERLİ** (kısmen hafifletilmiş) | `app/Services/BackupService.php:87,113-161` — `.sql.enc` + `.files.enc` (config + `storage/sozluk-*.php`); `public/media` HİÇ geçmiyor · off-site yalnız `$backup['name']` = `.sql.enc` (`SystemController.php:65`, `bin/backup.php:55`) | **İE#22** (paket/manifest tasarımı) |
| **F-04** | Kurulum HTTPS olmadan sır kabul ediyor | **KISMEN** — `SetupHttpsGate` var, loopback muaf (`app/Middleware/SetupHttpsGate.php:37`) | Kapı mevcut; raporun itirazı muafiyet kapsamına | İE#22 (gözden geçirme) |
| **F-05** | Kurulum state'i tahmin edilebilir bootstrap anahtarıyla istemcide | **HÂLÂ GEÇERLİ** | `app/Setup/CookieSession.php:217-221` — anahtar `basePath\|PHP_VERSION\|php_uname('n')` türevi | İE#22 |
| **F-06** | Uzak galeri görselleri `/https://...` olarak sunuluyor | **KAPANDI** — rc6/D11a | `app/Models/ProductRepository.php:738-747` — uzak adrese `/` eklenmiyor, `uzak` bayrağı eklendi · `tests/Http/GaleriGorselleriTest.php` | — |
| **F-07** | İdempotans yalnız doğrudan-liste yolunda atomik | **HÂLÂ GEÇERLİ** | `app/Controllers/ExtensionController.php:43-56` ön kontrol bir OKUMADIR (TOCTOU); `:67-70` (hata) ve `:111` (varsayılan GK) doğrudan `inbox->create()`; `InboxRepository::create()` duplicate kurtarma YAPMAZ | **rc8** |
| **F-08** | `APP_URL` yazımı sessizce başarısız; Host fallback açık | **HÂLÂ GEÇERLİ** | `app/Controllers/SetupController.php:754-771` — `catch (Throwable) {}` ve kurulum yine kilitleniyor (`:658` sonrası) · `app/Core/AppUrl.php:41-49` — değer yoksa `$uri->getAuthority()`; regex ön-ek eşlemesi (`^https?://[^\s/]+`) | **rc8** |
| **F-09** | PHP 8.1 desteği dependency sözleşmesiyle çelişiyor | **KISMEN — bilinen ve KAYITLI karar (K45)** | `composer.json:7` `^8.1`, `:47` `platform-check: false` · lock'ta yalnız `robthree/twofactorauth v3.0.3` `php >=8.2.0` (§4) · `docs/TECH-BASELINE.md:11` gerekçe + RİSK NOTU + `php81-uyum` CI bekçisi | İE#22 (taban 8.2 kararı — §4) |
| **F-10** | Belgelenen çevrimdışı eklenti kuyruğu yok | **KAPANDI** — İE#21 A5 | `extension/core/kuyruk.ts`, `extension/core/toparlama.ts`, `extension/entrypoints/background.ts` (kuyruklu gönderim + MV3 uyanışta toparlama) | — |
| **F-11** | `main` korumasız; yeşil CI merge için zorunlu değil | **DOĞRULANMADI** (depo ayarı; kod dışı) | Repo ayarları bu ağaçtan okunamaz | İE#22 (yönetişim) |
| **F-12** | Capture isteğinde senkron dış ağ görsel indirmesi | **KISMEN** — ana görsel hâlâ istek içinde; galeri arka plana alındı (D11a) | `app/Services/CaptureApplier.php:109` (`prepareMedia` — transaction dışında ama istek içinde) · galeri: `medyaIsiYaz()` → `medya` kuyruk işi | İE#22 |
| **F-13** | Medya `rename()` başarısızlığı yutuluyor | **HÂLÂ GEÇERLİ** | `app/Services/MediaService.php:100-115` — `@rename(...)` dönüşü denetlenmiyor | rc8 (F-01 ile birlikte; aynı hat) |
| **F-14** | Bakım `.tmp` ve kırık medya referanslarını onarmıyor | **HÂLÂ GEÇERLİ** | `MaintenanceTasks.php` / `MediaJanitor.php` içinde `.tmp` geçmiyor (arama sonucu boş) | rc8 (F-01 paketi) |
| **F-15** | SSRF DNS doğrulaması ile bağlantı arasında TOCTOU | **HÂLÂ GEÇERLİ** | `app/Services/UrlGuard.php:76-91` (`dns_get_record` ile çözüm) ile cURL'ün kendi çözümü ayrı; `CURLOPT_RESOLVE` pinlemesi yok | İE#22 |
| **F-16** | Capture kaynak/galeri URL doğrulaması eksik | **KISMEN** | `ProductRepository::addRemoteImages()` yalnız `https://` ön-eki + uzunluk denetler (`:670`); `UrlGuard` galeri satırlarına uygulanmıyor (yalnız indirilen görselde) | rc8 (F-01 paketiyle aynı dosya hattı) |
| **F-17** | Remember-me rotasyonunda yarış | **KISMEN** | `app/Auth/RememberTokenService.php:78-95` — tek `UPDATE`, ama eski `token_hash` koşulu YOK: iki eşzamanlı rotasyon ikisi de "başarılı" döner, biri geçersiz çereze düşer | İE#22 |
| **F-18** | Actions'ın çoğu değişebilir tag'e pinli | **KISMEN** | `.github/workflows/ci.yml` — çoğu `@v2/@v4/@v7`; yalnız bir iş SHA pinli (`:280`) | İE#22 |
| **F-19** | Dependabot iki npm çalışma alanını izlemiyor | **HÂLÂ GEÇERLİ** | `.github/dependabot.yml` — yalnız `/frontend`; `extension` ve `e2e` yok (dosyada "extension" geçmiyor) | İE#22 (tek satır) |
| **F-20** | Tag var, yayımlanmış Release/artefakt yok | **DOĞRULANMADI** (depo/Release ayarı) | Yerel ağaçtan okunamaz | İE#22 (yönetişim) |
| **F-21** | Panel/eklenti kritik davranışlarında test boşlukları | **KISMEN KAPANDI** | rc5→rc7 turunda +26 PHPUnit, eklenti vitest 134, panel 215, Playwright 4 senaryo (EKL-30/31); defter 76/83 | İE#22 (kalan 7 senaryo) |
| **F-22** | Liste sunumu N+1 ve pagination yok | **HÂLÂ GEÇERLİ** | `app/Services/ListPresenter.php:190` — her ürün için `products->images($id)` (N+1); ürün listesinde LIMIT/sayfalama yok | İE#22 |
| **F-23** | 40 MP görsel sınırı paylaşımlı hosting için yüksek | **HÂLÂ GEÇERLİ (ayar)** | `app/Services/MediaService.php:31` — 40 MP tavan | İE#22 (ayar kararı) |
| **F-24** | SMTP off-site yedeği belleğe base64 ediyor | **HÂLÂ GEÇERLİ** | `app/Services/BackupOffsite.php:109` `base64_encode(file_get_contents(...))`, `:128` `chunk_split` | İE#22 (F-03 ile aynı paket) |
| **F-25** | Chunked/uzun gövde için kesin üst sınır yok | **KISMEN KAPANDI** | `app/Middleware/JsonRequest.php:62-75` sınır UYGULANIYOR; ölçüm `Content-Length` (`:116`) ve `getBody()->getSize()` (`:121`) — boyutu bilinmeyen chunked akış için kesin tavan hâlâ yok | İE#22 |
| **F-26** | `public/tani.php` üretimde bilgi sızdırabilir | **KAPANDI** — İE#19 G4 | `bin/release.php:259-263` dosyayı PAKETE ALMAZ; `:423` doğrulama zip'te bulunursa üretimi reddeder | — |
| **F-27** | Paylaşım kodunun düz metin kopyası DB'de | **RET (bilinçli karar, K62)** | `app/Models/ListRepository.php:21,173` — `share_key_plain`; gerekçe migration 0021 başlığında: 6 hane bir parola değil PAYLAŞIM KODUDUR, kullanıcı firmaya iletebilmelidir | RET (PM isterse V3-C'de `shares` taşımasıyla yeniden değerlendirilir) |
| **F-28** | Hız sınırı count-then-insert ve log hatasında fail-open | **HÂLÂ GEÇERLİ** | `app/Middleware/ExtensionAuth.php:126` COUNT → `:134` INSERT (atomik değil) · `:138` `catch (\Throwable)` → istek geçer | İE#22 |
| **F-29** | Yedek adı saniye hassasiyetinde | **HÂLÂ GEÇERLİ** | `app/Services/BackupService.php:86` `date('Ymd-His')` | İE#22 (F-03 paketi) |
| **F-30** | Panel build uyarılı; yönlendirme script'i hashlenmiyor | **DOĞRULANMADI** (sığ inceleme) | — | İE#22 |
| **F-31** | Büyük sınıflar composition/regresyon riski | **KABUL** (yapısal) | `SetupController`, `SharePage`, `ListPresenter` büyük | İE#22 (kademeli) |
| **F-32** | Dokümantasyonun bazı iddiaları kodla uyuşmuyor | **KISMEN GEÇERLİ — bu turda bir örneği kanıtlandı** | `AppBuilder.php:270-271` yorumu "medya bilinçli boş" diyor; §2.1 kanıtı bunun bir KUSUR olduğunu gösteriyor. Runbook'un "medya yedekleniyor" iddiası da F-03 ile çelişiyor | rc8 (F-01 ile aynı commit'te yorum düzeltilir) + İE#22 |
| **F-33** | Yönetişim/güvence katmanlarında eksikler | **DOĞRULANMADI** (kod dışı) | — | İE#22 |

**Sayım:** KAPANDI 3 · HÂLÂ GEÇERLİ 14 · KISMEN 9 · RET 1 · DOĞRULANMADI 4 ·
KABUL (yapısal) 1 · KISMEN KAPANDI 2 → **toplam 33**.

---

## 2. ÖNCELİKLİ DOĞRULAMA — KANIT

### 2.1 F-01 — ÇALIŞTIRMALI KANIT (iddia değil, ölçüm)

Geçici bir test dosyasıyla **gerçek `AppBuilder`** üzerinden, arşiv modunda,
`POST /api/capture` (hedef liste seçili) koşuldu. Test dosyası kanıt alındıktan
sonra SİLİNDİ; repoya girmedi.

```
=== F-01 KANIT ===
DB main_image: /media/0404b97e56b66b74e09f8360cbfd8115.jpg
Yeni dosyalar : 0404b97e56b66b74e09f8360cbfd8115.jpg.tmp
Dosya var mı  : HAYIR
.tmp kaldı mı : EVET
```

**Sonuç:** iddia doğru. Arşiv modunda eklentiden **hedef listeye** yapılan her
yakalama, DB'ye çözülemeyen bir `/media/...` URL'si yazıyor ve diskte bir `.tmp`
bırakıyor. Aynı kusur Gelen Kutusu'ndan uygulama yolunda da var
(`CaptureApplier::applyInboxItem()` → `medyayiSonlandir()` yine no-op).

**"Kaç `.tmp` oluşmuş olabilir?" — SİLME YOK, yalnız envanter.** Aşağıdaki komut
hiçbir dosyaya dokunmaz; sayar ve listeler:

```bash
# Kaç adet, ne kadar yer, en eskisi ne zaman:
find public/media -name '*.tmp' -printf '%TY-%Tm-%Td %10s %p\n' | sort | tee ~/tmp-envanter.txt | wc -l
du -ch public/media/*.tmp 2>/dev/null | tail -1

# Kaçının DB karşılığı kırık (yani main_image dosyası yok):
mysql -u <kullanici> -p <veritabani> -N -e \
  "SELECT id, main_image FROM products WHERE main_image LIKE '/media/%' AND deleted_at IS NULL" \
  | while read id url; do [ -f "public${url}" ] || echo "KIRIK #$id $url"; done | tee ~/kirik-gorsel.txt | wc -l
```

Beklenen ilişki: **kırık kayıt sayısı ≈ `.tmp` sayısı** (her başarısız commit bir
`.tmp` + bir kırık URL bırakır). İkisi tutmuyorsa aradaki fark, hotlink modunda
ya da `CaptureService::createProduct()` yolundan geçen kayıtlardır.

> Sahadaki gözlemle çelişki notu: D11a turunda ana görselin çalıştığı
> görülmüştü. Bu, o kurulumun **hotlink** modunda olması (o zaman `.tmp`
> üretilmez) ya da o ürünün başka yoldan girmiş olmasıyla açıklanabilir.
> Yukarıdaki envanter, hangisinin geçerli olduğunu **kesin** söyler — tahmin
> yerine sayım.

### 2.2 F-02 — kod okuması

`app/Setup/SetupLock.php:65-92`:

```php
try { $stored = $this->readFromDatabaseStrict(); }
catch (Throwable) { return $this->connectionResponds() ? self::STATE_UNLOCKED : self::STATE_UNKNOWN; }
```

Yakalanan **her** `Throwable` için, bağlantı yanıt veriyorsa `STATE_UNLOCKED`
dönüyor. Ayrım "tablo yok" ile sınırlı DEĞİL: SELECT yetkisi yokluğu, kolon/şema
uyuşmazlığı, sürücü hatası da kilitsiz sayılır ve **kurulum rotaları açılır**.

**İE#19 G1'in kapsamı bunu içeriyor muydu?** Hayır. Kodun kendi yorumu (satır
71-82) düzeltmenin amacını "veritabanı ayakta ama `settings` tablosu YOK" olarak
tanımlıyor. Uygulama o niyetten geniş çıkmış: ayrım hata SINIFINA değil, yalnız
bağlantının yaşadığına bakıyor. Yani bu bir kapsam aşımı, bilinçli bir karar değil.

### 2.3 F-07 — kod okuması

- `ExtensionController::capture()` `:43-56` — `findByCaptureId()` bir **okuma**;
  iki eşzamanlı tekrar isteği de bu kontrolü geçebilir (TOCTOU).
- Hedef liste **varsa** `CaptureApplier::applyToList()` UNIQUE satırını
  transaction'ın ilk yazımı yapar ve `kisitIhlaliyseIlkSonuc()` ile yarışı
  kurtarır → **atomik**.
- Hedef liste **yoksa** (`:111`) ve **doğrulama hatasında** (`:67-70`) doğrudan
  `InboxRepository::create()` çağrılır; bu metotta duplicate kurtarma yoktur
  (`app/Models/InboxRepository.php:46-70`) → yarışı kaybeden istek `PDOException`
  alır. Sözleşme ("aynı `capture_id` ilk sonucu döndürür") bu iki yolda **bozuk**.

### 2.4 F-08 — kod okuması

- `SetupController::rememberAppSettings()` `:754-771`: `APP_URL` yazımı dahil tüm
  ayar yazımları `catch (Throwable) {}` ile yutuluyor; kurulum sonra
  `lock->write()` ile **kilitleniyor** (`:664`). Yani APP_URL yazılamamış bir
  kurulum "tamamlandı" sayılıyor.
- `AppUrl::base()` `:41-49`: değer boş/placeholder ya da regex'e uymuyorsa
  `$uri->getAuthority()` — yani **istemcinin gönderdiği Host** kullanılıyor.
  Regex ön-ek eşlemesidir (`^https?://[^\s/]+`); sonuna eklenen bozuk veri
  reddedilmez. Üretimde ayrım yok (development profili koşulu yok).

### 2.5 F-03 — kod okuması

| Parça | Durum |
|---|---|
| `.sql.enc` (veritabanı) | ✅ üretiliyor (`BackupService.php:87`) |
| `.files.enc` (config.php + `storage/sozluk-*.php`) | ✅ üretiliyor (`:113-161`) — raporun "config ve sözlük yok" kısmı YANLIŞ, ikisi de var |
| `public/media` | ❌ **hiçbir yedeğe girmiyor** (dosyada geçmiyor) |
| Off-site (FTP/SMTP) | ❌ yalnız `.sql.enc` gönderiliyor (`SystemController.php:65`, `bin/backup.php:55`) |

Yani bulgu **kısmen** doğru: yan dosyalar yerelde yedekleniyor ama **medya hiç**,
off-site ise **yalnız SQL**. Runbook'un "medya yedekleniyor" iddiası kodla
uyuşmuyor (F-32'nin ikinci örneği).

---

## 3. rc8 ADAY MADDELERİ (PM kararı bekler — emir gelmeden kod yazılmaz)

### rc8-01 · F-01 — medya commit'i üretim composition'ında yok (P0)

- **Kök:** `AppBuilder.php:263-275` `CaptureApplier`e `MediaService` geçmiyor;
  `CaptureApplier::medyayiSonlandir()` `null` görünce sessizce dönüyor.
  Yanıltıcı yorum ("bilinçli boş bırakılır") kusuru kalıcılaştırmış.
- **Düzeltme:** `MediaService` zorunlu parametre olur; `?MediaService = null`
  kaçışı kaldırılır (yanlış wiring **derleme/test zamanında** patlasın).
  `rename()` dönüşü denetlenir (F-13) ve başarısızlık loglanır.
- **Regresyon testi (ZORUNLU, gerçek composition):** `AppBuilder::build()` ile
  ayağa kalkan uygulamada arşiv modunda `POST /api/capture` → (a) nihai dosya
  VAR, (b) `.tmp` YOK, (c) `main_image` URL'si diskteki dosyayı gösterir.
  Aynı üçlü Gelen Kutusu'ndan uygulama yolu için de koşulur.
- **Yan iş:** `.tmp` envanteri (silme yok) + yaş eşikli bakım görevi (F-14).

### rc8-02 · F-02 — SetupLock fail-open (P0)

- **Kök:** `SetupLock.php:70-83` — hata sınıfı ayırt edilmiyor.
- **Düzeltme:** yalnız **doğrulanmış "tablo yok"** (SQLSTATE `42S02` / MySQL 1146
  ya da `information_schema` sorgusu) `STATE_UNLOCKED` sayılır; diğer tüm SQL
  hataları `STATE_UNKNOWN` → 503.
- **Regresyon testi:** üç senaryo — tablo yok, SELECT yetkisi yok, kolon/şema
  bozuk. MySQL ve MariaDB'de (mevcut `--group mysql` işleri kullanılabilir).

### rc8-03 · F-07 — idempotans tüm yollarda atomik değil

- **Kök:** `ExtensionController.php:67-70` ve `:111` doğrudan `inbox->create()`.
- **Düzeltme:** tek bir `insert-or-select` primitive'i (`InboxRepository`
  içinde), UNIQUE ihlalinde mevcut satırı döndürür; üç yol da onu kullanır.
- **Regresyon testi:** aynı `capture_id` ile iki eşzamanlı istek → iki 201, tek
  satır, aynı `inbox_id`; pending ve error yolları ayrı ayrı.

### rc8-04 · F-08 — APP_URL sessiz başarısızlık + Host fallback

- **Kök:** `SetupController.php:754-771` yutulan hata + `AppUrl.php:41-49`
  fallback.
- **Düzeltme:** `APP_URL` yazımı başarısızsa kurulum **kilitlenmez**, açık hata
  döner. `AppUrl` tam kanonik doğrulama yapar (şema + host + boş path;
  userinfo/fragment/kontrol karakteri reddi). Üretimde APP_URL yoksa link
  üretimi **açık hata** verir; Host fallback yalnız geliştirme profilinde.
- **Regresyon testi:** ayar yazımı başarısızken `finish` 500 döner ve kilit
  YAZILMAZ; APP_URL boşken paylaşım linki üretimi üretimde hata verir; bozuk
  APP_URL değerleri reddedilir.

> Sıra önerisi: **rc8-01 → rc8-02 → rc8-03 → rc8-04**. İlk ikisi veri/güvenlik
> sınırı; son ikisi sözleşme bütünlüğü. Dördü de tek rc8 turunda kapatılabilir
> büyüklükte; F-13/F-14/F-16 rc8-01 ile aynı dosya hattında olduğu için birlikte
> ele alınması ucuzdur.

---

## 4. K45 TABANI — `php >= 8.2` isteyen paketler

**Çalışma zamanı (`packages`) — TEK paket:**

| Paket | Sürüm | Kısıt |
|---|---|---|
| `robthree/twofactorauth` | v3.0.3 | `php >=8.2.0` |

`mpdf/mpdf` (8.3.1) ve `slim/slim` (4.15.2) 8.1'i açıkça destekliyor; diğer
üretim paketlerinde 8.2+ kısıtı yok.

**Geliştirme (`packages-dev`) — zaten 8.3/8.4 istiyor:** PHPUnit 12 zinciri
(`phpunit/phpunit`, `sebastian/*`, `php-code-coverage`) `>=8.3`;
`symfony/*` v8.1 (php-cs-fixer bağımlılıkları) **`>=8.4.1`**. Bu, geliştirme
ortamının fiilen 8.3+ olduğunu gösterir (yerelde CS-Fixer 8.4 ikilisiyle koşuyor).

**Bugünkü duruş kayıtlıdır:** `composer.json:7` `^8.1`, `:47`
`platform-check: false`; gerekçe ve risk notu `docs/TECH-BASELINE.md:11`de yazılı
(K45), bekçi `php81-uyum` CI işi (`ci.yml:198`).

**Taban 8.2 kararının etkisi:**

| Alan | Etki |
|---|---|
| `composer.json` | `"php": "^8.2"`; `platform-check` **geri açılabilir** (tek engel olan paket zaten 8.2 istiyor) |
| CI | `php81-uyum` işi (`ci.yml:190-245`) düşer ya da 8.2'ye çevrilir; kalan işler zaten 8.4/8.3 |
| Runbook / SUNUCU-PROFILI | "PHP 8.1–8.4" ifadesi "8.2–8.4" olur; MultiPHP'de 8.1'e düşen kurulum artık desteklenmez — **bu bir kullanıcı etkisidir**, canlı sunucunun ea-php83 kullandığı doğrulanmalı |
| Sihirbaz | `RequirementChecker::MIN_PHP_VERSION` `'8.1.0'` → `'8.2.0'` (`app/Setup/RequirementChecker.php:25`); gereksinim adımı 8.1 sunucuyu **açıkça reddeder** — bugün sessizce kabul ediyor |
| Kazanç | "Beyan edilen destek" ile "gerçekte test edilen sürüm" arasındaki boşluk kapanır; F-09'un asıl riski (gelecek bir patch'in 8.2 sözdizimi getirmesi) ortadan kalkar |

**Öneri:** taban 8.2'ye çıkarılsın ve `platform-check` geri açılsın; ama önce
canlı sunucunun PHP sürümü **ölçülsün** (`php -v` + cPanel MultiPHP ekranı).
8.1'e düşme ihtimali gerçekse karar PM'indir — bu triyaj yalnız etkiyi listeler.

---

## 5. AÇIK NOTLAR

1. **Rapor bir kopyadır, değiştirilmedi** (`docs/denetim/dis-denetim-2026-08-26.md`).
2. **Kod değişmedi.** F-01 kanıtı için kullanılan geçici test dosyası silindi;
   `git status` temiz bırakıldı.
3. **Dört bulgu doğrulanamadı** (F-11, F-20, F-30, F-33): üçü depo/yönetişim
   ayarı, biri sığ inceleme. "Geçersiz" değil, **incelenmedi** demektir.
4. Raporun F-03 iddiasının bir kısmı **yanlıştır**: config ve sözlük yedeğe
   giriyor (`.files.enc`). Medya ve off-site kapsamı ise doğru.
5. F-27 **RET** önerilir: düz metin paylaşım kodu bilinçli bir karardır (K62);
   6 hane bir parola değil, kullanıcının firmaya ilettiği koddur.

---

## 6. rc8 UYGULAMA SONUCU (26 Ağu 2026)

Triyaj §3'teki dört aday **rc8'de kapatıldı** (dal `is-emri-rc8-denetim-p0`):

| Madde | Bulgular | Kanıt |
|---|---|---|
| rc8-01 | F-01 · F-13 · F-14 · F-16 · F-32 | `tests/Http/MedyaKompozisyonTest.php` — GERÇEK `AppBuilder` ile üç yol: nihai dosya var · `.tmp` yok · adres diski gösteriyor |
| rc8-02 | F-02 | `tests/Setup/SetupLockTest.php` +4 — tablo yok / SELECT yetkisi yok / bozuk kolon / SQLite metni |
| rc8-03 | F-07 | `tests/Http/IdempotansYollariTest.php` — GK ve hata yollarında iki tekrar tek satır; yarışı kaybeden istek 500 almaz |
| rc8-04 | F-08 | `tests/Core/AppUrlKanonikTest.php` +18 — kanonik doğrulama; üretimde `AppUrlYokException` |

**Sözleşme değişikliği (rapora değer):** `AppUrl` artık üretimde Host'a
düşmüyor. Eski `AppUrlTest`teki üç test bu davranışı "bilinçli yedek" diye
kodluyordu; silinmediler, **yeni sözleşmeye taşındılar** (yedek yalnız açık
bayrakla). Davranış değişikliği testte görünür kaldı.

Kalan bulgular (F-03, F-05, F-15, F-17, F-19, F-22, F-24, F-28, F-29) **İE#22**
kapsamındadır; F-27 RET; F-11/F-20/F-30/F-33 hâlâ **doğrulanmadı**.
