# İŞ EMRİ #4 (REV2) — Karar İşlemesi (K22–K27) + Migration Standardı + CI + Kimlik Doğrulama
Faz: Faz 1 · Modül: çekirdek + M1/auth · Dal: `is-emri-4-auth` (PR aç, merge ETME)

> REV2 NOTU: İlk İE#4 yayınından sonra gelen dış mimari incelemenin PM tarafından kabul edilen maddeleri bu emre işlendi. Bu dosya öncekinin yerine geçer.
> ÖN ŞART 1: PR #2 merge edilmiş olacak (PM onayı verildi; edilmediyse önce merge et).
> ÖN ŞART 2: Bu dosya `docs/is-emirleri/is-emri-04.md` olarak repoya konur (ilk commit).

---

## BÖLÜM A — Karar ve Belge İşlemesi (kod öncesi, ayrı commit)

docs/08 karar tablosuna aşağıdaki satırlar eklenir (K# PM tarafından atandı):

- **K22 — Makine enum standardı:** DB ve API'de durum/görünürlük değerleri İngilizce sabit kod olur; Türkçe yalnızca UI etiketidir. Ürün durumları: `to_order, ordered, in_transit, received, cancelled`. Liste durumları: `draft, sent, ordered, completed, cancelled`. Görünürlük: `active, passive, archived`. Gelen kutusu: `pending, error, assigned`. docs/10 ve docs/04'teki TÜM Türkçe makine değerleri değiştirilir; Türkçe↔kod çeviri tablosu docs/09'a eklenir. Gerekçe: ürün/liste tabloları yazılmadan değişim bedava, sonra çok pahalı.
- **K23 — Migration standardı:** 1 migration = 1 DDL değişikliği (MySQL örtük commit gerçeği; yarıda kalan çok-DDL'li migration tekrar koşumda patlar). `migrations` tablosuna `checksum` (dosya sha256) ve `execution_ms` kolonları; uygulanmış migration'ın checksum'u değişmişse Migrator hata verir.
- **K24 — Para ve yuvarlama politikası (docs/04'e "Para Politikası" bölümü):** birim fiyatlar `DECIMAL(12,4)`, kurlar `DECIMAL(12,4)` (mevcut), satır toplamı = bcmath ile `qty × birim × kur` → 2 hane HALF_UP; genel toplam = yuvarlanmış satır toplamlarının toplamı; API'de para alanları string (mevcut, K14). Ara hesaplarda scale ≥ 6.
- **K25 — API sözleşmesi revizyon paketi (docs/10 güncellenir; implementasyon ilgili fazda):**
  - 405 → HTTP 405 + `METHOD_NOT_ALLOWED` hata kodu + `Allow` başlığı (İE#3'teki 422 eşlemesi kaldırılır).
  - `/api/capture` gövdesine zorunlu `capture_id` (UUIDv4, sistemde UNIQUE — aynı capture_id tekrar gelirse yeni kayıt açılmaz, ilk sonuç döner) + `schema_version`, `extension_version`, `parser_version`, `platform` alanları.
  - Dublicate kontrolü ve indeksi `platform + external_id` olur (tek başına external_id değil).
  - `exports` tablosu gerçek anlık görüntü tutar: `snapshot_json`, `sha256`, `file_size`, `status`, `list_revision`.
  - `lists.revision` sayacı: ürün ekle/sil/fiyat/adet/sıra değişiminde +1; "çıktı güncel değil" = `lists.revision != last_export.list_revision` (updated_at karşılaştırması kalkar).
  - Paylaşım token'ı DB'de hash'li saklanır (`share_token_hash` + `share_token_prefix`); route'tan gelen token SHA-256'lanıp aranır. Opsiyonel `share_expires_at` alanı şemaya eklenir (UI'ı Faz 2).
- **K26 — CI evet / CD hayır (K13 kısmi revizyonu):** GitHub Actions ile her PR'da: `composer validate` → `composer install` → PHPUnit → PHPStan → CS-Fixer (dry-run) → `composer audit`. Deploy manuel kalır.
- **K27 — Güvenlik sertleştirme ekleri (bu emrin D bölümünde uygulanır):** TOTP secret şifreleme spesifikasyonu, log redaction, Request-ID, strict config, production anahtar zorunlulukları, JSON log.

Ayrıca belge işlemeleri:
1. **Kur kilitleme tek ifadeye bağlanır** (belge çelişkisi gideriliyor): Taslak liste güncel kuru izler; durum `sent` olduğunda kur o anki değerle kilitlenir ve `lists.rate_locked_at` yazılır. Tüm belgelerde bu ifade kullanılır.
2. **docs/04 §2d medya güvenliği derinleştirilir:** görsel MIME allowlist yalnızca JPEG/PNG/WebP/AVIF/GIF (SVG YASAK); indirilen görsel GD ile yeniden encode edilir (EXIF/metadata temizliği); SSRF kontrolleri: yalnız HTTPS, DNS çözümü private/loopback/link-local IP ise ret, redirect hedefi yeniden doğrulanır (max 3), max boyut `MEDIA_MAX_MB`, magic-byte MIME doğrulaması, host eşleşmesi tam sonek kuralıyla (`alicdn.com.evil.com` GEÇMEZ).
3. **docs/06 test planına eklenir:** Excel/CSV formula injection testi (`=`, `+`, `-`, `@` ile başlayan `name_original/vendor_name/detail/note` değerleri Faz 2 export motorunda etkisizleştirilir).
4. **F5 (off-site yedek) yeniden sınıflandırılır:** Faz 4 → CANLIYA ALMA ÖN ŞARTI (docs/05 ve docs/07'ye not).
5. **docs/04 şema planına eklenir (tablolar yazılırken uygulanır):** `product_status_history` (id, product_id, from_status, to_status, actor_type, actor_id, changed_at, request_id) — durum tarihçesi activity_log'a gömülmez; `activity_log`'a `actor_type, actor_id, request_id, user_agent` kolonları.
6. **Frontend standardı güncellenir (docs/04):** React 19 + TypeScript + Vite (güncel sürümler Faz 1D başında doğrulanıp pin'lenir); K19 npm listesine TypeScript eklendi sayılır.
7. **docs/08 fikir havuzuna F13–F33 eklenir** (birer satır): F13 MariaDB-tabanlı iş kuyruğu + async export (POST /exports modeli — Faz 2 tasarım sorusu, güçlü aday) · F14 medya dedup (sha256 media_assets) · F15 thumbnail motoru (160/480 — Faz 2 güçlü aday) · F16 optimistic concurrency (version kolonu) · F17 staging subdomain · F18 paylaşım linki süre seçenekleri UI · F19 extension_devices (cihaz bazlı token) · F20 parser telemetry + 1688 fixture test seti · F21 OpenAPI/contracts tek kaynak · F22 Playwright E2E · F23 WebAuthn/passkey · F24 ASVS 5.0 go-live kontrol listesi · F25 dashboard widget seti · F26 sevkiyat/konteyner modeli · F27 tedarikçi teklif portalı + teklif versiyonları · F28 product master (source_products) · F29 mal kabul genişletme + QR + koli/CBM · F30 HS/GTIP öneri modülü · F31 belgeler modülü · F32 tedarikçi skorlama · F33 AI kullanım paketi (çeviri/kategori/anomali — insan onaylı).
8. CHANGELOG güncellenir.

**Reddedilenler (kayıt için, docs/08'e işlenmez):** kör MariaDB pin (üretim DB türü/versiyonu deploy'da `SELECT VERSION()` ile doğrulanıp o pin'lenecek — F-İE3-1 kapsamı), DB saatinin UTC'ye çevrilmesi (mevcut TZ kararı kalıyor), PR #2'nin geri açılması (fix-forward).

---

## BÖLÜM B — Migration Bölme (K23 uygulaması)

- Mevcut `0001_auth_core` ve `0002_settings_core` tablo başına bölünür: `0001_create_users`, `0002_create_recovery_codes`, `0003_create_remember_tokens`, `0004_create_settings`, `0005_create_rate_history`, `0006_create_categories`, `0007_create_activity_log`.
- `activity_log` bu bölme sırasında yeni kolonlarla yazılır: `actor_type VARCHAR(16) NOT NULL DEFAULT 'admin'`, `actor_id BIGINT UNSIGNED NULL`, `request_id CHAR(26) NULL`, `user_agent VARCHAR(255) NULL`.
- Migrator: `checksum`/`execution_ms` kolonları + checksum doğrulaması (uygulanmış dosya değiştiyse anlaşılır hata). `migrations` tablosu şeması güncellenir (henüz üretimde koşulmadığı için eski kayıt taşıma derdi yok; lokal DB sıfırlanabilir).
- Testler yeni yapıya uyarlanır (sıra, tekrar koşmama, rollback, checksum ihlali testi eklenir).

## BÖLÜM C — Çekirdek Düzeltmeleri

1. **405 düzeltmesi:** AppBuilder'da `HttpMethodNotAllowedException` → HTTP 405, `error.code = METHOD_NOT_ALLOWED`, `Allow` başlığında izinli metodlar. Testi güncelle/ekle.
2. **Config sertleştirme:** `getInt` katı tam sayı doğrular ("1.5", "12abc" ret — regex `^-?\d+$`); `getPositiveInt` eklenir. `APP_ENV=production` iken zorunlu anahtarlara `APP_KEY` (64 hex) ve `EXTENSION_TOKEN_SALT` (min 32) eklenir ve biçimleri doğrulanır; local'de opsiyonel kalır (sihirbaz öncesi kurulum durumu İE#5'te ele alınacak).
3. **Request-ID middleware:** her isteğe ULID/UUID `request_id`; `X-Request-Id` yanıt başlığı + tüm log kayıtlarına processor ile eklenir; activity_log yazımlarında kullanılır.
4. **Log iyileştirme:** Monolog JSON formatter; merkezi redaction processor — `Authorization`, `Cookie`, `password`, `code`, `token`, `secret`, `DB_PASS`, `APP_KEY` içeren alanlar `[GİZLENDİ]` olarak yazılır; redaction'ın birim testi yazılır.

## BÖLÜM D — Kimlik Doğrulama (M1 Auth)

K19'dan bu emirde eklenecek paketler: `robthree/twofactorauth` + `bacon/bacon-qr-code`. Başka paket YOK.

1. **Şifreleme ve kullanıcı temeli**
   - Şifre: `PASSWORD_ARGON2ID` + `password_verify` + `password_needs_rehash`.
   - `app/Auth/`: `UserRepository`, `PasswordHasher`, `TotpService`, `RecoveryCodeService` (10 adet, `XXXX-XXXX`, DB'de hash), `RememberTokenService` (selector+validator: selector düz, validator hash'li; çerez `selector:validator`).
   - **TOTP secret şifreli saklanır (K27):** sodium `crypto_secretbox` (yoksa openssl AES-256-GCM'e düş); anahtar APP_KEY'den türetilir (HKDF/`crypto_generichash`, sabit info etiketi); kayıt formatı `versiyon:nonce:ciphertext` (base64) — anahtar rotasyonuna hazır. Sunucuda sodium varlığı raporda doğrulanır.
2. **Oturum katmanı:** native session; `SESSION_NAME`/`SESSION_LIFETIME` .env'den; boşta kalma `last_activity` ile; çerez `Secure/HttpOnly/SameSite=Lax`; girişte `session_regenerate_id(true)`; aşamalar `anonim → sifre_dogru(TOTP bekliyor) → girisli`.
3. **Middleware'ler:** `Auth` (401 `UNAUTHENTICATED` / 401 `TOTP_REQUIRED`; remember çerezinden sessiz giriş; validator uyuşmazlığında TÜM remember token'ları sil + logla), `Csrf` (GET/HEAD/OPTIONS hariç `X-CSRF-Token` zorunlu; ret 403 `CSRF`), `LoginRateLimit` (IP+e-posta kombine sayaç; `LOGIN_MAX_ATTEMPTS` aşımında 403 `LOCKED` + `meta.retry_after_seconds`; exponential backoff, üst sınır 60 dk; TOTP ve recovery denemeleri de sayaca işler; başarılı girişte sıfırlanır).
4. **Uçlar (docs/10 §2 birebir):** `POST /api/auth/login` (`remember:true` bayrağı oturumda; çerez TOTP SONRASI kurulur) · `POST /api/auth/totp` · `POST /api/auth/recovery` (tek kullanımlık, `remaining_codes`) · `POST /api/auth/logout` (204) · `GET /api/auth/me` (`{user, csrf_token}`) · `GET /api/auth/sessions` + `DELETE /api/auth/sessions/{id}`. `user` nesnesi `{id, email, created_at}` — hash/secret ASLA yanıta girmez.
5. **Aktivite kaydı:** başarılı/başarısız giriş (e-posta+IP), kilit, TOTP hata, recovery kullanımı, logout, sessiz giriş, çalıntı-token temizliği — `detail`e şifre/kod ASLA yazılmaz; `request_id` doldurulur.
6. **Dev aracı:** `bin/user-create.php` (yalnızca CLI): kullanıcı oluşturur, TOTP secret üretir (şifreli kaydeder), `otpauth://` URI + ASCII QR basar, kurtarma kodlarını BİR KEZ gösterir. Üretimde bu iş sihirbazındır (İE#5) — nota yazılır.

## BÖLÜM E — CI (K26)

`.github/workflows/ci.yml`: push + PR tetikli; PHP 8.4 kur (gerekli eklentilerle), `composer validate --strict`, `composer install`, `composer test`, `composer stan`, `composer cs`, `composer audit`. Yeşil olmadan PR raporlanmaz.

---

## Kapsam DIŞI
Kurulum sihirbazı (İE#5), settings/kur/kategori uçları, listeler/ürünler, React paneli, `/api/capture` implementasyonu (yalnızca sözleşme güncellenir).

## Kabul Kriterleri
- [ ] K22–K27 ve tüm belge işlemeleri yapıldı; docs/10'da Türkçe makine değeri kalmadı; F13–F33 havuzda; CHANGELOG güncel.
- [ ] Migration'lar 1-DDL standardında; checksum doğrulaması testli; temiz DB'de koşum + ikinci koşum "uygulanacak yok".
- [ ] 405 gerçek 405 + `Allow` başlığı + `METHOD_NOT_ALLOWED` — testli.
- [ ] `getInt` "1.5"/"12abc" reddediyor — testli; production modunda APP_KEY eksikse uygulama anlaşılır hatayla durmuyor değil DURUYOR — testli.
- [ ] 7 auth ucu docs/10 §2 ile birebir; Argon2id; yanıtlarda hash/secret sızıntısı yok.
- [ ] TOTP secret DB'de şifreli (düz base32 görünmüyor) — testli.
- [ ] Backoff/kilit, CSRF reddi, remember-me sessiz giriş + çalıntı-token temizliği, recovery tek kullanımlık — hepsi testli.
- [ ] Request-ID yanıt başlığında ve loglarda; redaction testli; loglar JSON.
- [ ] CI workflow'u PR'da koşuyor ve yeşil.
- [ ] PHPUnit yeşil; PHPStan lvl6 sıfır hata; CS-Fixer sıfır düzeltme; `composer audit` temiz; repoda sır yok.

## Test
Login→TOTP→me akışı, LOCKED yanıtı, CSRF reddi, 405 yanıtı, checksum ihlali örneği ve CI koşum linki rapora eklenir.

## Teslim
Dal `is-emri-4-auth`, commit standardı `feat(auth): ... / docs(k22): ... / ci: ...`, PR aç, ÇIKTI RAPORU üret. PR merge edilmez — PM denetimi GitHub üzerinden yapılacak.
