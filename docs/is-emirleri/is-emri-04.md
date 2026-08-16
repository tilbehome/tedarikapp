# İŞ EMRİ #4 — Faz 1: Kimlik Doğrulama (M1 Auth Çekirdeği)
Faz: Faz 1 · Modül: M1/auth · Dal: `is-emri-4-auth` (PR aç, merge ETME)

> ÖN ŞART 1: PR #2 merge edilmiş olacak (İE#3 kapanışı — PM kod denetiminden geçti, onay verildi).
> ÖN ŞART 2: Bu iş emri dosyası `docs/is-emirleri/is-emri-04.md` olarak repoya konur (ilk commit).

## Hedef
docs/10 §2'deki kimlik doğrulama uçları eksiksiz çalışıyor; K16 sertleştirmeleri (Argon2id, giriş backoff/IP kilidi, CSRF, oturum güvenliği) yerinde; her uç için sözleşme testi yeşil; kalite hattı (PHPUnit + PHPStan lvl6 + CS-Fixer) temiz.

## Ön Koşul
- Oku: CLAUDE.md, docs/10 §1–2 (zarf, hata kodları, auth uçları), docs/04 §2 (users/recovery_codes/remember_tokens şeması) ve §2d, docs/08 (K16, K19).
- K19 listesinden BU emirde eklenecek paketler: `robthree/twofactorauth` + `bacon/bacon-qr-code`. Başka paket YOK (spreadsheet/mpdf sonraki emirlerde).

## Yapılacaklar

1. **Şifreleme ve kullanıcı temeli**
   - Şifre: `password_hash(..., PASSWORD_ARGON2ID)` — parametreler PHP varsayılanı; doğrulamada `password_verify` + `password_needs_rehash` kontrolü.
   - `app/Auth/` altında: `UserRepository` (PDO), `PasswordHasher`, `TotpService` (robthree sarmalayıcı), `RecoveryCodeService` (üretim: 10 adet, format `XXXX-XXXX`, DB'de hash), `RememberTokenService` (selector+validator deseni: selector düz, validator hash'li; çerez `selector:validator`).

2. **Oturum katmanı**
   - PHP native session, çerez adı/ömür .env'den (`SESSION_NAME`, `SESSION_LIFETIME` dakika — boşta kalma aşımı `last_activity` ile uygulanır).
   - Çerez bayrakları: `Secure`, `HttpOnly`, `SameSite=Lax`. Girişte `session_regenerate_id(true)`.
   - Oturum durumları: `anonim → sifre_dogru (TOTP bekliyor) → girisli`. `stage` oturumda tutulur; TOTP beklerken korumalı uçlar 401 `TOTP_REQUIRED` döner.

3. **Middleware'ler** (`app/Middleware/`)
   - `Auth`: korumalı uçlarda oturum ister; yoksa 401 `UNAUTHENTICATED`, TOTP aşamasındaysa 401 `TOTP_REQUIRED`. Oturum yoksa remember çerezinden sessiz giriş dener (selector bul → validator hash karşılaştır → uyuşmazsa TÜM remember token'ları sil ve logla → çalıntı token işareti).
   - `Csrf`: GET/HEAD/OPTIONS hariç tüm isteklerde `X-CSRF-Token` başlığı oturumdaki token'la eşleşmeli; değilse 403 `CSRF`. Token girişte üretilir, `GET /api/auth/me` yanıtında döner.
   - `LoginRateLimit` (yalnızca auth uçlarına): IP+e-posta anahtarıyla sayaç `activity_log` üzerinden; `LOGIN_MAX_ATTEMPTS` aşımında 403 `LOCKED` + `meta.retry_after_seconds`; tekrarlarda süre katlanır (exponential backoff, üst sınır 60 dk). Başarılı girişte sayaç sıfırlanır.

4. **Uçlar** (docs/10 §2 birebir — gövde/yanıt/kodlar oradaki gibi)
   - `POST /api/auth/login` — şifre doğruysa `{stage:"totp"}`; `remember:true` bayrağı oturumda saklanır (çerez TOTP SONRASI kurulur).
   - `POST /api/auth/totp` — kod doğruysa oturum `girisli`, `{user}` döner; yanlış kod 422; art arda yanlış TOTP da rate limit sayacına işler.
   - `POST /api/auth/recovery` — tek kullanımlık kod; `used_at` yazılır; `{user, remaining_codes}` döner.
   - `POST /api/auth/logout` — 204; oturum + varsa remember token DB'den silinir.
   - `GET /api/auth/me` — `{user, csrf_token}`.
   - `GET /api/auth/sessions` + `DELETE /api/auth/sessions/{id}` — remember token listesi (id, created_at, expires_at) ve iptali.
   - `user` nesnesi: `{id, email, created_at}` — hash/secret alanları ASLA yanıta girmez.

5. **Aktivite kaydı**
   - `activity_log`'a yazılır: başarılı giriş, başarısız giriş (e-posta + IP), kilitlenme, TOTP hata, recovery kullanımı, logout, remember-token sessiz giriş, çalıntı-token temizliği. `detail` alanına şifre/kod ASLA yazılmaz.

6. **Geliştirme yardımcı aracı**
   - `bin/user-create.php` (yalnızca CLI): e-posta + şifre alır, kullanıcı oluşturur, TOTP secret üretir ve `otpauth://` URI + terminale QR (bacon-qr ASCII) basar, kurtarma kodlarını BİR KEZ gösterir. Üretimde kullanıcı oluşturma kurulum sihirbazının işidir (İE#5) — bu araç lokal test içindir; betiğin başına bu not yazılır.

7. **Belge işlemeleri**
   - docs/10 §1'e tek satır not: "405 (metot desteklenmiyor) sözleşmede ayrı kod tanımlamaz; 422 `VALIDATION` zarfıyla döner (İE#3 uygulaması, PM onaylı)."
   - CHANGELOG: "[Yayınlanmadı] / Eklendi" altına auth teslimi satırı.
   - composer.json/lock: iki yeni paket + `composer audit` çıktısı rapora.

## Kapsam DIŞI
- Kurulum sihirbazı (İE#5), settings/kur/kategori uçları, listeler/ürünler, React paneli, `/api/capture`.

## Kabul Kriterleri
- [ ] 7 auth ucu docs/10 §2 ile birebir (gövde, kodlar, zarf, `meta.retry_after_seconds` dahil).
- [ ] Argon2id hash; yanıtlarda hiçbir hash/secret sızmıyor.
- [ ] Yanlış girişte backoff çalışıyor: N. denemeden sonra 403 `LOCKED`, bekleme süresi katlanıyor, başarılı girişte sıfırlanıyor — testli.
- [ ] CSRF: token'sız POST 403; token'lı geçiyor — testli.
- [ ] Remember me: çerezle sessiz giriş; validator uyuşmazlığında tüm token'lar siliniyor — testli.
- [ ] Recovery kodu tek kullanımlık — ikinci kullanım reddi testli.
- [ ] Her uç için en az bir sözleşme testi (docs/10 §9); PHPUnit tümü yeşil; PHPStan lvl6 sıfır hata; CS-Fixer sıfır düzeltme.
- [ ] `composer audit` temiz; repoda sır yok.

## Test
Test çıktıları + örnek istek/yanıt dökümleri (login→totp→me akışı, LOCKED yanıtı, CSRF reddi) rapora eklenir.

## Teslim
Dal `is-emri-4-auth`, commit standardı `feat(auth): ...`, PR aç, ÇIKTI RAPORU üret. PR merge edilmez — PM denetimi artık doğrudan GitHub üzerinden yapılacak.
