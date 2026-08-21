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
2. Release zip'ini üret — **TEK YOL: `php bin/release.php` (K43, İE#9.3).** Elle zip YASAK:
   iki üretim vakası (vendor/ eksik, setup/ eksik) elle paketlemeden çıktı. Script:
   - aşağıdaki tablodaki HER girdinin zip'te var olduğunu üretimden SONRA doğrular;
     biri eksikse zip'i SİLER ve hata koduyla çıkar — eksik release var olamaz;
   - zip köküne `MANIFEST.txt` (her dosya + sha256 + toplam) yazar; sunucudaki
     `GET /api/system/integrity` ve sihirbazın gereksinim adımı eksik/bozuk dosyaları
     bu manifeste göre isim isim raporlar;
   - ön şartları da denetler: dev'siz vendor (`composer install --no-dev`), panel build.

   ```
   composer install --no-dev --optimize-autoloader
   cd frontend && npm ci && npm run build && cd ..
   php bin/release.php --version=v0.9.2-faz1 --out=dist
   composer install   # geliştirme ortamına dönüş
   ```

   ("zip'ten kurulabilirlik" tanımı: temiz bir sunucuda zip → aç → sihirbaz → panel açılır;
   eksik klasör = kurulamayan sürüm. CI `uretim-profili` job'ı bunu her PR'da zip üretip
   çalıştırarak doğrular: `/setup` text/html + `/api/setup/state` 200 + integrity temiz.)

   | Zip'e GİRER | Neden |
   |---|---|
   | `app/` | uygulama kodu |
   | `bootstrap/` | K40 ön kontrol kapısı (`preflight.php`) — vendor'dan önce koşar; eksik PHP/eklenti/vendor'da çıplak 500 yerine 503 açıklama sayfası |
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
3. **Üretim profili doğrulaması (K41):** CI'daki `uretim-profili` job'ı yeşil olmadan release ÇIKARILMAZ — docs/SUNUCU-PROFILI.md manifestine uyum (sodium'suz şifreleme, yazılamaz disk yolları, allow_url_fopen/mail yasağı statik taraması) her PR'da otomatik denetlenir.
4. cPanel Dosya Yöneticisi ile yükle → mevcut sürümün üzerine AÇMADAN önce: `app/`'i `app_onceki/` olarak yedekle.
5. Zip'i aç, migration varsa çalıştır, smoke test (bölüm 6).
6. GitHub'da release tag'i atılır (`v0.x.0`), CHANGELOG güncellenir.

**Hata davranışı (K42):** kurulum ve açılış hataları hiçbir evrede çıplak 500 üretmez:
- Ön kontrol kapısı (`bootstrap/preflight.php`): PHP sürümü/eklenti/vendor eksikse **503** + madde madde eksik + çözüm; geçince tamamen sessiz.
- Açılış (bootstrap) hatası: `.env` yoksa tam teşhisli statik sayfa, varsa özet + teknik detay (sır maskeli).
- Sihirbaz adım hatası: dostane Türkçe mesaj + teknik detay bölümü + **"Tanılama raporunu kopyala"** düğmesi (ortam + eklenti VAR/YOK + hata + işlem günlüğü; sır İÇERMEZ).
- Kurulu (kilitli) sistemde çalışma zamanı hatası: kullanıcıya zarif genel mesaj + Request-ID; tam detay `app_logs`a yazılır ve aynı Request-ID ile bulunur.

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
- Ayda bir yedekten geri yükleme denemesi yapılır — denenmemiş yedek, yedek değildir.
  Tek komuttur: `php bin/restore-test.php` (İE#14 D2; ayrıntı aşağıda "Geri yükleme tatbikatı").
- **Off-site yedek CANLIYA ALMA ÖN ŞARTIDIR (İE#4 REV2, havuzdaki F11 yeniden sınıflandırıldı):** gece yedeğinin sunucu dışına da kopyalanması (ör. Google Drive) canlıya çıkmadan ÖNCE kurulur. Yalnızca aynı sunucuda duran yedek, sunucu kaybında yedek değildir.

## Zamanlanmış görevler (İE#13 EK-A — TEK CRON)

cPanel > Cron Jobs'a **tek satır** girilir (yol kuruluma göre uyarlanır):

```
0 3 * * *  /usr/local/bin/php /home/<kullanıcı>/<alan-adı>/bin/backup.php
```

`backup.php` gecelik koşunun tamamıdır ve iki adımı AYNI süreçte, arka arkaya çalıştırır:

1. **Yedek** — şifreli veritabanı yedeği alır, yapılandırılmışsa off-site gönderir.
2. **Bakım** — çöp kutusu kalıcı temizliği + yetim medya GC + app_logs saklama +
   hız sayacı satırları + yedek saklama (BACKUP_RETENTION_DAYS; en yeni 5 korunur).

**İki iş birbirinin hatasını yutmaz:** yedek adımı başarısız olsa bile bakım adımları
koşar (ve tersi). Sonuç `app_logs`a TEK birleşik özet satırı olarak yazılır — seviye
`LOG_LEVEL` ayarından bağımsız olarak Info'dur, yani başarılı koşunun da izi kalır.

Çıkış kodları: `0` her iki adım tamam · `2` kısmi (bir adım hatalı, diğeri koştu) ·
`1` koşu hiç başlayamadı (yapılandırma/bağlantı).

Elle koşum: `php bin/bakim.php` yalnız bakım adımlarını çalıştırır (cron'da GEREKMEZ).
`purge-trash.php` geriye uyum için durur.

### Cron gerçekten koşuyor mu? (İE#14 D1)

Her koşu — başarılı da başarısız da — `storage/logs/cron.log` dosyasına TEK satır bırakır:

```
2026-08-21 03:00:04 | OK   | yedek 4.2 MB, off-site ftp · bakım 3 iş | 12.4 sn
2026-08-22 03:00:02 | HATA | yedek başarısız: disk dolu              | 1.1 sn
```

Bu dosya `app_logs`tan farklı bir soruyu yanıtlar: **cron hiç tetiklendi mi?** Uygulama
hiç çalışmadıysa veritabanına da satır yazılmaz; dosyanın ilerlememesi "koşu yok"
demektir. Dosya 500 satırda sabitlenir (günde bir koşu ≈ 1,5 yıl geçmiş).

Panel karşılığı — **Ayarlar > Yedekler**:

- "Son yedek: 3 saat önce" (hiç yedek yoksa açıkça "hiç alınmadı" yazar),
- son cron koşusunun yaşı ve hata ile bitip bitmediği,
- yedek **30 saati** geçtiyse turuncu uyarı: *"Gecelik yedek gecikti — cron çalışmıyor
  olabilir"*. Eşik 24 değil 30 saattir: gecelik döngüye 6 saat pay bırakır, sunucu saati
  kayınca boş yere alarm vermez.

### Geri yükleme tatbikatı (İE#14 D2) — ayda bir

"Yedek alınıyor" ile "geri yüklenebiliyor" aynı şey değildir. Tatbikat tek komuttur:

```
php bin/restore-test.php                        # en yeni yedeği dener
php bin/restore-test.php yedek-20260821-030004.sql.enc
php bin/restore-test.php --tut                  # geçici veritabanını silmez (inceleme)
```

Betik sırasıyla: yedeği **çözer** (APP_KEY yanlışsa burada anlaşılır) → `<db>_restoretest_<zaman>`
adlı **geçici** bir veritabanı oluşturur → dökümü yükler → tablo ve satır sayılarını
listeler → kritik tabloları (`users`, `lists`, `products`, `migrations`) ve `users`
tablosunun boş olmadığını denetler → geçici veritabanını **düşürür**.

**CANLI VERİTABANINA DOKUNMAZ.** Hedef ad her koşuda yeniden üretilir, canlı ada eşit
çıkarsa betik durur; döküm içinde `USE` / `CREATE DATABASE` gibi veritabanı düzeyinde bir
ifade bulunursa yükleme yapılmadan durdurulur (geçici şemanın dışına yazma riski).

Çıkış kodu `0` tatbikat başarılı · `1` başarısız. **Başarısız çıktı alınırsa o yedeğe
güvenilmez:** nedeni giderilip yeni yedek alınır ve tatbikat tekrarlanır. Gereksinim:
veritabanı kullanıcısının `CREATE DATABASE`/`DROP DATABASE` yetkisi (cPanel'de yoksa
tatbikat yerel kopyada koşulur).

## Eklenti kurulumu (İE#11 — Faz 3)

1. `extension/dist/chrome-mv3` klasörü Chrome'a "Paketlenmemiş öğe yükle" ile eklenir
   (Store yayınında bu adım kullanıcıda mağaza linkiyle olur).
2. Eklentinin **Kimlik** değeri alınır ve sunucuda `EXTENSION_ALLOWED_ORIGINS`
   ayarına `chrome-extension://<kimlik>` olarak yazılır (K30 CORS allowlist; boşsa
   eklenti bağlanamaz, virgülle birden çok kimlik yazılabilir).
3. Panel > Ayarlar > Güvenlik > "Eklenti token'ı üret" — tam token bir kez görünür,
   eklentinin ayar ekranına panel adresiyle birlikte girilir.
4. Doğrulama: `detail.1688.com` ürün sayfasında eklenti simgesi → önizleme dolu gelmeli →
   "Panele Gönder" → panel Gelen Kutusu'nda kayıt görünmeli.
