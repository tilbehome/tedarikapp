# SUNUCU PROFİLİ — Üretim Ortamı Manifesti (K41)

> **KURAL (bağlayıcı):** Her yeni bağımlılık, PHP eklenti ihtiyacı veya dosya-sistemi
> varsayımı bu manifeste karşı kontrol edilmeden hiçbir iş emrine giremez.
> Uyumsuzluk görülürse uygulamadan ÖNCE PM'e bildirilir.
>
> CI'daki `uretim-profili` job'ı bu manifestin otomatik bekçisidir (K41):
> her PR'da "bu kod Bünyamin'in sunucusunda açılır mı?" sorusunu cevaplar.

## Bilinen Üretim Ortamı Gerçekleri

| Alan | Değer | Sonuç / kural |
|---|---|---|
| PHP | **8.4 (ea-php84)** | Taban çizgisi docs/TECH-BASELINE.md; RequirementChecker ≥ 8.4.0 zorlar |
| Handler | **DSO / mod_php** | Süreç kullanıcısı **`nobody`** — hesap kullanıcısı DEĞİL |
| Docroot yazılabilirliği | **YAZILAMAZ** | Tek istisna: kurulum günü elle izin verilecek `public/media`. **DİSKSİZ MOD (K44):** session→`sessions` tablosu (DbSessionHandler), sihirbaz state→şifreli çerez, log→`app_logs`, kilit→`settings`, ayarlar→`settings`; yapılandırma `config.php` (yalnız DB+APP_KEY; yazamıyorsa wp-config.php modeli manuel kayıt). `session.save_path`e HİÇ güvenilmez |
| Eklentiler VAR | pdo_mysql · curl · gd · mbstring · zip · intl · bcmath · fileinfo · **openssl** | RequirementChecker zorunlu listesi bunlarla sınırlı kalır |
| Eklentiler YOK ve AÇILAMAZ | **sodium** · imagick | Bayi hesabı — EasyApache/PHP Selector erişimi yok. sodium'a bağlı zorunluluk YASAK (K39: OpenSSL AES-256-GCM yedeği); imagick yerine GD |
| `allow_url_fopen` | **KAPALI** | Dış istek YALNIZ cURL (K8). URL'li `file_get_contents`/`fopen` YAZILAMAZ — `uretim-profili` statik taraması bunu PR'da yakalar |
| `mail()` | **KAPALI** | E-posta akışı tasarlanamaz (kurtarma kodları bu yüzden var) |
| opcache | **KAPALI** | Performans varsayımı yapılmaz; dosya değişikliği anında etkilidir |
| Ortam | CloudLinux + CageFS, cPanel **bayi** | Sunucu yapılandırması DEĞİŞTİRİLEMEZ varsayılır |
| MySQL | **8.4.x**, utf8mb4 | CI entegrasyon job'ı aynı sürümle koşar (K37 §E12) |
| Composer | Sunucuda **YOK** | `vendor/` lokalde kurulur, release zip ile taşınır (docs/07 §4) |
| SSL | Let's Encrypt **VAR** | Üretimde HTTPS zorunlu (K37 §A3 kapısı çalışır) |
| Cron | **VAR** | `bin/purge-trash.php` housekeeping cron adayı (docs/07 §7) |
| Dışa cURL | **VAR** (1688/alicdn doğrulandı) | Medya indirme/hotlink SSRF beyaz listesiyle çalışır |

## Bu manifeste aykırı olduğu için YASAK olanlar (özet)

- `ext-sodium`/`imagick` gerektiren paket veya kod yolu (zorunluluk olarak).
- URL alan `file_get_contents()` / `fopen()` (allow_url_fopen kapalı — çalışma anında ölür).
- `mail()` çağrısı; `exec/system/proc_open` (docs/04 §7).
- `storage/` veya docroot'a yazmayı başarı şartı sayan akış (yalnız `public/media` istisnası, o da denetimli düşüşle).
- Sunucuda composer/npm çalıştırma varsayımı.

## Güncelleme kuralı

Bu manifest **PM onayıyla** güncellenir (sunucu değişirse/yeni gerçek doğrulanırsa).
Değişiklik docs/08 karar günlüğüne işlenir; `uretim-profili` testi aynı PR'da uyarlanır.
