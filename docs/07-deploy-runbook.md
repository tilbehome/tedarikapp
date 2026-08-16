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
├── storage/         ← YAZILABİLİR olmalı (chmod cPanel'den)
│   ├── images/      (indirilen ürün görselleri)
│   ├── exports/     (geçici xlsx/pdf)
│   └── logs/
└── .env             (sırlar — repoya girmez)
```

Not: cPanel'de subdomain kökü `public/` klasörüne çekilemiyorsa, kök `.htaccess` ile `public/`e rewrite yapılır.

## 3. İlk Kurulum (Faz 1 başı, tek seferlik)

1. cPanel → MySQL: veritabanı + kullanıcı oluştur, yetki ver.
2. cPanel → Dosya Yöneticisi: `storage/` ve altını yazılabilir yap (docroot yazılamaz sorunu burada çözülür; PHP kullanıcısının sahipliği kontrol edilir).
3. `.env` dosyasını sunucuda elle oluştur (DB bilgileri, APP_KEY, extension token tuzu).
4. Release paketini yükle (bkz. bölüm 4), migration'ı çalıştır: `https://.../setup.php?key=...` (tek seferlik, sonra silinir) — sunucuda SSH garanti olmadığı için migration web tetiklemeli yazılır.
5. SSL'in aktif olduğu, HTTP→HTTPS yönlendirmesinin çalıştığı doğrulanır.

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

- cPanel cron, her gece: DB dump + `storage/images/` → tarihli arşiv → `~/backups/` (son 14 gün tutulur).
- Ayda bir yedekten geri yükleme denemesi (test DB'ye) yapılır — denenmemiş yedek, yedek değildir.
