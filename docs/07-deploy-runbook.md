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
│   └── assets/      (React build çıktısı)
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
   - Gereksinim denetimi: PHP sürümü/eklentileri, `public/media/` ve `storage/` yazma izinleri (yazılamıyorsa hangi klasöre hangi iznin verileceğini ekranda söyler).
   - DB bilgilerini sorar, bağlantıyı test eder, `.env`'i kendisi yazar (APP_KEY ve token tuzunu kriptografik üretir).
   - Migration'ları çalıştırır, admin hesabını oluşturtur, **2FA'yı QR kodla tanımlatır** ve kurtarma kodlarını gösterir.
   - Bitince kendini **kalıcı olarak kilitler** (kilit dosyası + tekrar erişim denemeleri loglanır).
3. SSL aktif ve HTTP→HTTPS yönlendirmesi çalışıyor mu doğrula; smoke test (bölüm 6) koş.

Sonraki sürümler: sihirbaz YOK — zip yüklenir, admin girişinde "veritabanı güncellemesi var" uyarısı çıkar, tek tıkla migration koşulur.

## 4. Sürüm Çıkarma (her release)

1. Lokal: testler yeşil → `composer install --no-dev` → React `npm run build`.
2. Release zip'i oluştur: `app/ public/ vendor/ migrations/` (+ varsa yeni `.env.example` farkı NOT edilir).
3. cPanel Dosya Yöneticisi ile yükle → mevcut sürümün üzerine AÇMADAN önce: `app/`'i `app_onceki/` olarak yedekle.
4. Zip'i aç, migration varsa çalıştır, smoke test (bölüm 6).
5. GitHub'da release tag'i atılır (`v0.x.0`), CHANGELOG güncellenir.

## 5. Geri Alma

- Kod: `app_onceki/` geri adlandırılır (5 dk).
- Veritabanı: her deploy ÖNCESİ cPanel'den DB export alınır; migration geri alınamıyorsa bu yedekten dönülür.

## 6. Smoke Test (her deploy sonrası, 5 dakika)

- [ ] Giriş yapılıyor.
- [ ] Bir listede ürünler görünüyor, TL fiyatlar doğru.
- [ ] Bir Excel export alınıp açılıyor.
- [ ] Bir paylaşım linki telefonda açılıyor.
- [ ] `storage/logs/` içine yeni hata düşmemiş.

## 7. Yedekleme

- cPanel cron, her gece: DB dump + `public/media/` → tarihli arşiv → `~/backups/` (son 14 gün tutulur).
- Ayda bir yedekten geri yükleme denemesi (test DB'ye) yapılır — denenmemiş yedek, yedek değildir.
- **Off-site yedek CANLIYA ALMA ÖN ŞARTIDIR (İE#4 REV2, havuzdaki F11 yeniden sınıflandırıldı):** gece yedeğinin sunucu dışına da kopyalanması (ör. Google Drive) canlıya çıkmadan ÖNCE kurulur. Yalnızca aynı sunucuda duran yedek, sunucu kaybında yedek değildir.
