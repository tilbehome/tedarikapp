# İŞ EMRİ #3 — Faz 1 Başlangıcı: Proje Çekirdeği, Migration Altyapısı, Auth Tabloları
Faz: Faz 1 · Modül: çekirdek · Dal: `is-emri-3-cekirdek` (PR aç, merge ETME)

> ÖN ŞART: PR #1 merge edilmiş olacak (İE#2 kapanışı). Bu emirle KOD DÖNEMİ başlar.

## Hedef
Composer kurulu, Slim çalışır, `.env` okunur, loglama açık, migration altyapısı işler durumda; auth çekirdeği tabloları migration olarak hazır; kalite hattı (PHPUnit + PHPStan + CS-Fixer) yeşil; `GET /api/health` docs/10 zarfıyla yanıt veriyor.

## Ön Koşul
- Oku: CLAUDE.md, docs/04 (şema, §2b-2c-2d, §6b dizin ağacı, §7), docs/10 (yanıt zarfı, hata kodları), docs/07 (dizin/deploy mantığı).
- Lokalde PHP 8.4 kurulu olmalı (K21 — aşağıda).

## Yapılacaklar
1. **K21 belge işlemesi (PHP 8.1 → 8.4):** docs/04, CLAUDE.md, README ve docs/07'deki "PHP 8.1" ibarelerini "PHP 8.4" yap ("sunucuda doğrulanan 8.1.34" tarihsel notu §7'de kalabilir, yanına "16.08.2026'da 8.4'e yükseltildi" ekle). docs/08'e karar satırı: "| K21 | 16 Ağu 2026 | PHP sürümü 8.4'e yükseltildi (hosting destekliyor) | 8.1'in güvenlik desteği Aralık 2025'te bitti; kod başlamadan geçiş sıfır maliyet |".
2. **Composer kurulumu** (`composer.json` + lock): require `php: ^8.4`, `slim/slim:^4`, `slim/psr7`, `vlucas/phpdotenv`, `monolog/monolog`; require-dev `phpunit/phpunit`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`. PSR-4: `App\\` → `app/`. Diğer K19 paketleri (spreadsheet, mpdf, totp, qr) BU emirde kurulmaz — kullanılacakları emirde eklenir.
3. **Çekirdek iskelet** (docs/04 §6b ağacına birebir):
   - `public/index.php`: front controller; `public/.htaccess` (her isteği index.php'ye yönlendir) + kök `.htaccess` (docroot public'e çekilemezse `public/`e rewrite — docs/07 notu).
   - `app/Core/Config.php`: `.env` yükleyici (phpdotenv) + tipli erişim + zorunlu anahtar denetimi (eksikse anlaşılır hata).
   - `app/Core/Database.php`: PDO bağlantısı (utf8mb4, ERRMODE_EXCEPTION, emulated prepares KAPALI).
   - `app/Core/Logger.php`: Monolog → `storage/logs/app-{tarih}.log` (LOG_LEVEL/LOG_PATH .env'den).
   - `app/Core/Response.php`: docs/10 zarfını üreten yardımcı (`success/data/error/meta`).
   - Middleware: `SecurityHeaders` (K16 başlıkları: CSP, HSTS, X-Frame-Options DENY, nosniff, Referrer-Policy) + JSON gövde ayrıştırma. Diğer middleware'ler (Auth, CSRF, RateLimit) İE#4'te.
   - `GET /api/health`: `{"success":true,"data":{"app":"tedarikapp","time":<ISO8601 +03:00>}}` — DB bağlantısını da yoklar, kapalıysa docs/10 hata zarfı.
4. **Migration altyapısı** (`app/Core/Migrator.php` + `migrations/`):
   - Sıralı dosyalar: `0001_....php` (her biri `up(): void` içeren sınıf; forward-only — geri alma runbook'taki DB yedeğiyle, K5 basitlik ilkesi).
   - `migrations` tablosu: `id, name, applied_at` — uygulananlar atlanır, kalanlar sırayla koşulur, her biri transaction içinde.
   - Çalıştırma: CLI `php bin/migrate.php` (lokal) — web tetikleme kurulum sihirbazının işi (İE#5), bu emirde YOK.
5. **İlk migration'lar** (docs/04 §2 şemasına + §2d doğrulama sınırlarına birebir; para alanları DECIMAL, kurlar DECIMAL(12,4)):
   - `0001_auth_core`: `users`, `recovery_codes`, `remember_tokens` (+ `remember_tokens(selector)` indeksi)
   - `0002_settings_core`: `settings`, `rate_history`, `categories`, `activity_log` (+ `activity_log(entity_type, entity_id)` indeksi)
   - `lists/products/inbox/exports` tabloları SONRAKİ emirde — bu emirde YAZILMAZ.
6. **Kalite hattı:** `phpstan.neon` (seviye 6, `app/` + `bin/`), `.php-cs-fixer.php` (PSR-12), `phpunit.xml`. Testler: Config zorunlu anahtar denetimi · Response zarf biçimi · Migrator (SQLite in-memory ile: sıra, tekrar koşmama, transaction geri alma) · health endpoint yanıt biçimi.
7. **CHANGELOG:** "[Yayınlanmadı] / Eklendi" altına: "Faz 1 çekirdeği: Slim iskeleti, config/log katmanı, migration altyapısı, auth çekirdeği tabloları (İE#3). PHP 8.4'e geçildi (K21)."

## Kapsam DIŞI
- Giriş/2FA uçları, kurulum sihirbazı, React paneli, liste/ürün tabloları, seed verisi — hepsi sonraki emirlerde.

## Kabul Kriterleri
- [ ] `composer install` temiz; lock repoda; PHP 8.4'te çalışıyor.
- [ ] `php bin/migrate.php` temiz MySQL veritabanında 2 migration'ı sırayla uygular; ikinci koşuda "uygulanacak yok" der.
- [ ] Tablolar docs/04 şemasıyla birebir (alan adı/tip uyuşmazlığı yok; para alanları DECIMAL).
- [ ] `GET /api/health` docs/10 zarfıyla 200 döner; güvenlik başlıkları yanıtta.
- [ ] PHPUnit tümü yeşil; PHPStan seviye 6 sıfır hata; CS-Fixer sıfır düzeltme.
- [ ] K21 belge güncellemeleri yapıldı; repoda sır yok.

## Test
- Test çıktıları + `SHOW CREATE TABLE` özetleri (users, recovery_codes) + health yanıt örneği rapora eklenir.

## Teslim
Dal `is-emri-3-cekirdek`, commit standardı `feat(core): ...` / `docs(k21): ...`, PR aç, ÇIKTI RAPORU üret.
