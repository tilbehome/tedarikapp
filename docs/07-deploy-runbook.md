# tedarikapp — Deploy Runbook

> Durum: v1.0 — ONAYLANDI (16.08.2026) — sunucu raporuna (04 no'lu belge, bölüm 7) göre yazılmıştır.

## 1. Ortamlar

| Ortam | Yer | Amaç |
|---|---|---|
| Lokal | Bünyamin'in bilgisayarı (Claude Code) | Geliştirme + testler |
| Üretim | tedarikapp.tilbehometoptan.com (cPanel) | Gerçek kullanım |

Ayrı staging yok (solo proje); riskli değişiklikler lokalde tam test edilmeden üretime çıkmaz.

## 2. Dizin Yapısı (üretim)

```
tedarikapp.tilbehometoptan.com/
├── public/          ← Apache docroot buraya yönlendirilir (subdomain kökü)
│   ├── index.php    (API + paylaşım sayfaları giriş noktası)
│   └── panel/       (React build çıktısı — `npm run build` üretir, repoda YOK)
├── app/             (PHP kaynak — docroot DIŞI)
├── vendor/          (lokalde composer ile kurulup yüklenir)
├── public/media/    ← YAZILABİLİR (ürün görselleri — webden servis edilen tek yazılabilir yer)
├── storage/         ← YAZILABİLİR ama webden ERİŞİME KAPALI (.htaccess deny)
│   ├── exports/     (geçici xlsx/pdf)
│   └── logs/
└── .env             (sırlar — repoya girmez)
```

Not: cPanel'de subdomain kökü `public/` klasörüne çekilemiyorsa, kök `.htaccess` ile `public/`e rewrite yapılır.

## 3. İlk Kurulum — Kurulum Sihirbazı (K16, tek seferlik)

1. cPanel → MySQL: veritabanı + kullanıcı oluştur, yetki ver (sihirbaz DB oluşturamaz, cPanel yetkisi ister — tek elle yapılan adım budur).
2. Release zip'ini yükle ve aç, tarayıcıdan siteye gir → **kurulum sihirbazı** otomatik açılır:
   - Gereksinim denetimi: PHP sürümü/eklentileri, production'da HTTPS (zorunlu — K37 §A3), `public/media/` ve `storage/` yazma izinleri. Yazılamayan klasör kurulumu BLOKLAMAZ (K37 §D10): sihirbaz hotlink + DB-log moduyla devam eder ve hangi klasöre hangi iznin verileceğini ekranda söyler.
   - DB bilgilerini sorar, bağlantıyı test eder, `.env`'i kendisi yazar (APP_KEY ve token tuzunu kriptografik üretir).
   - Migration'ları çalıştırır, admin hesabını oluşturtur, **2FA'yı QR kodla tanımlatır** ve kurtarma kodlarını gösterir.
   - Bitince kendini **kalıcı olarak kilitler** (kilit `settings` tablosunda; tekrar erişim denemeleri loglanır).
3. SSL aktif ve HTTP→HTTPS yönlendirmesi çalışıyor mu doğrula; smoke test (bölüm 6) koş.

### 3b. Bu sunucuda kurulum — yazılamayan docroot (K33)

Üretim sunucusunda PHP **`nobody`** kullanıcısıyla (DSO) çalışıyor ve uygulama diske
yazamıyor. Bu kalıcı bir kısıt; kurulum akışı buna göre farklıdır:

1. **`.env` elle kaydedilir.** Sihirbaz dosyayı yazamadığını fark eder ve üretilen içeriği
   ekranda gösterir. İçeriği kopyalayın, cPanel > Dosya Yöneticisi ile uygulama kökünde
   **`.env`** adıyla kaydedin (baştaki nokta dahil), sonra "Kaydettim" deyin. Sihirbaz
   dosyayı okuyup APP_KEY eşleşmesini doğrular; uyuşmazsa devam etmez.
2. **Loglar veritabanına gider.** `.env` içinde `LOG_DRIVER=db` gelir; loglar `app_logs`
   tablosuna yazılır. Dosya hedefi bu sunucuda kullanılmaz — sessizce kaybolurdu.
3. **Görseller için `public/media` izni.** Sihirbaz bu klasörü yazılabilir bulamazsa
   **hotlink moduna** düşer: görseller indirilmez, 1688 URL'si saklanır. Panel bunu rozetle
   gösterir. Görselleri sunucuya indirmek istiyorsanız (K6 — önerilen):
   ```
   chmod 777 public/media
   ```
   ⚠️ **YALNIZCA `public/media/.htaccess` yerindeyken.** O dosya PHP çalıştırmayı kapatır,
   dizin listelemeyi engeller ve hotlink kuralını uygular. Yazma izni verilmiş + çalıştırma
   açık bir klasör, yüklenen ilk dosyayla uzaktan kod çalıştırmaya döner. İzni verdikten
   sonra sihirbazın gereksinim adımını **yeniden denetleyin**; mod `download`'a döner.
4. Kurulum kilidi de veritabanındadır (`settings` tablosu) — dosya yazılamadığı için.

Sonraki sürümler: sihirbaz YOK — zip yüklenir, admin girişinde "veritabanı güncellemesi var" uyarısı çıkar, tek tıkla migration koşulur.

## 4. Sürüm Çıkarma (her release)

1. Lokal: testler yeşil → `composer install --no-dev` → **panel derlemesi**:
   ```
   cd frontend
   npm ci
   npm run build      # tsc --noEmit + vite build → ../public/panel/
   ```
   Çıktı `public/panel/` altına düşer ve **repoya commit EDİLMEZ** (`.gitignore`). Sürüm zip'i bu klasörü içermek ZORUNDADIR; yoksa `/panel` adresi "Panel henüz derlenmemiş" sayfasını (503) gösterir.
2. Release zip'i oluştur (İE#9 §F15 — "zip'ten kurulabilirlik" tanımı: temiz bir sunucuda
   zip → aç → sihirbaz → panel açılır; eksik klasör = kurulamayan sürüm):

   | Zip'e GİRER | Neden |
   |---|---|
   | `app/` | uygulama kodu |
   | `public/` (**`public/panel/` build çıktısı ve `public/media/.htaccess` dahil**) | docroot; panel derlemesi yoksa `/panel` 503 döner |
   | `vendor/` | sunucuda composer yok — lokalde `composer install --no-dev` ile kurulup taşınır (K8) |
   | `migrations/` | sihirbazın ve güncelleme yolunun migration kaynağı |
   | `setup/` | kurulum sihirbazının HTML/JS/CSS'i — olmadan sihirbaz açılamaz |
   | `bin/` | `migrate.php`, `purge-trash.php` (housekeeping cron), `user-create.php` |
   | `.env.example` | sihirbazın `.env` ŞABLONU — olmadan env adımı çalışmaz |
   | `.htaccess` + `public/.htaccess` | yönlendirme ve koruma kuralları |
   | `storage/` (boş iskelet) | loglar/kilit için; yazılamıyorsa K33 DB modu devreye girer |

   Zip'e GİRMEZ: `.env` (sunucuda üretilir/korunur), `.git*`, `frontend/` kaynakları,
   `tests/`, `docs/`, `node_modules/`, geliştirme konfigleri (`phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.php`).
3. cPanel Dosya Yöneticisi ile yükle → mevcut sürümün üzerine AÇMADAN önce: `app/`'i `app_onceki/` olarak yedekle.
4. Zip'i aç, migration varsa çalıştır, smoke test (bölüm 6).
5. GitHub'da release tag'i atılır (`v0.x.0`), CHANGELOG güncellenir.

## 5. Geri Alma

- Kod: `app_onceki/` geri adlandırılır (5 dk).
- Veritabanı: her deploy ÖNCESİ cPanel'den DB export alınır; migration geri alınamıyorsa bu yedekten dönülür.

## 6. Smoke Test (her deploy sonrası, 5 dakika)

- [ ] `/panel` açılıyor (derleme zip'e girmiş), giriş ekranı geliyor.
- [ ] Giriş yapılıyor.
- [ ] Bir listede ürünler görünüyor, TL fiyatlar doğru.
- [ ] Bir Excel export alınıp açılıyor.
- [ ] Bir paylaşım linki telefonda açılıyor.
- [ ] `storage/logs/` içine yeni hata düşmemiş.

## 7. Yedekleme

- cPanel cron, her gece: DB dump + `public/media/` → tarihli arşiv → `~/backups/` (son 14 gün tutulur).
- **Çöp kutusu temizliği (K15, İE#6):** `bin/purge-trash.php` saklama süresi (`TRASH_RETENTION_DAYS`, varsayılan 30 gün) dolan soft-delete kayıtlarını kalıcı siler. Cron önerisi — yedekten SONRA koşsun ki silinen kayıt en az bir gece yedeğe girmiş olsun:
  ```
  0 4 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/purge-trash.php
  ```
  `--dry-run` ile ne silineceği yazdırılır, dokunulmaz.
- Ayda bir yedekten geri yükleme denemesi (test DB'ye) yapılır — denenmemiş yedek, yedek değildir.
- **Off-site yedek CANLIYA ALMA ÖN ŞARTIDIR (İE#4 REV2, havuzdaki F11 yeniden sınıflandırıldı):** gece yedeğinin sunucu dışına da kopyalanması (ör. Google Drive) canlıya çıkmadan ÖNCE kurulur. Yalnızca aynı sunucuda duran yedek, sunucu kaybında yedek değildir.
