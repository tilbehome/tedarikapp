# Changelog — tedarikapp

Biçim: [Keep a Changelog](https://keepachangelog.com/tr/) · Sürümleme: SemVer.
Her release'te bu dosya güncellenir (docs/07 bölüm 4). Kategoriler: Eklendi / Değişti / Düzeltildi / Kaldırıldı / Güvenlik.

## [Yayınlanmadı]
### Eklendi
- Faz 0: Proje belge seti v1.0 (docs/00–09), CLAUDE.md geliştirme anayasası, repo iskeleti.
- Faz 0 (K18): API sözleşmesi (docs/10) — uç bazlı istek/yanıt, hata zarfı, sayfalama sabitlendi.
- Faz 1 çekirdeği: Slim iskeleti, config/log katmanı, migration altyapısı, auth çekirdeği tabloları (İE#3). PHP 8.4'e geçildi (K21).
- Faz 1 kimlik doğrulama (İE#4): docs/10 §2'deki 7 auth ucu (login / totp / recovery / logout / me / sessions listesi ve iptali); Argon2id şifre hash'i, şifreli TOTP secret'ı ve tek kullanımlık kurtarma kodları; oturum katmanı (HttpOnly + SameSite=Lax çerez, boşta kalma aşımı, girişte kimlik tazeleme); Auth / Csrf / LoginRateLimit middleware'leri (artan bekleme + IP kilidi, `meta.retry_after_seconds`); selector+validator desenli "beni hatırla" token'ı ve çalıntı token tespitinde toplu iptal; giriş olaylarının activity_log'a yazılması; `bin/user-create.php` geliştirme aracı (terminalde QR + kurtarma kodları). Paketler: robthree/twofactorauth, bacon/bacon-qr-code (K19).
### Değişti
- Faz 0 (K18): Şema tamamlandı — users 2FA alanları, recovery_codes ve remember_tokens tabloları, lists.period/updated_at, inbox_items.status, activity_log.ip; veri doğrulama kuralları (docs/04 §2d) eklendi; açık teknik sorular 1–2 kapandı olarak işaretlendi; Akış 6a → Akış 6 olarak yeniden numaralandı.
- Onaylı kütüphane listesi (K19), hızlı paylaşım kararı (K20), fikir havuzu senkronu (F-numaralı 12 fikir), karar numarası kuralı (protokol).
