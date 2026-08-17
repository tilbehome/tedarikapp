# İE#9.3 — Kapsamlı Bütünlük Denetimi Raporu (K43)
Tarih: 17.08.2026 · Denetleyen: Claude Code · Kapsam: PM denetim emri a–g + kök çözüm

## Bulgular Tablosu

| # | Bulgu | Önem | Durum |
|---|---|---|---|
| 1 | **Release bütünlüğü güvencesizdi** — zip elle üretiliyordu; `vendor/` (1. vaka) ve `setup/` (2. vaka) eksik yüklenebildi, hata sessiz kaldı | KRİTİK | **DÜZELTİLDİ (kök çözüm):** `bin/release.php` tek yol — docs/07 §4 tarifindeki her girdiyi üretim SONRASI zip içinde doğrular, eksikse zip'i siler ve hata verir; MANIFEST.txt (yol+sha256+sayı) zip köküne yazılır |
| 2 | **Eksik kurulum sunucuda sessizdi** — `/setup` NOT_FOUND JSON dönüyordu, kullanıcı neyin eksik olduğunu bilemiyordu | KRİTİK | **DÜZELTİLDİ:** `GET /api/system/integrity` (kimliksiz, kurulum öncesi de çalışır — iki uygulamada da) MANIFEST'e göre eksik/bozuk dosyaları İSİM İSİM verir; sihirbazın gereksinim adımı gösterir ve eksikte devamı KAPATIR; sihirbaz dosyası yoksa `/setup` artık K42 uyumlu **503 HTML** ("setup/ eksik açılmış, üzerine yazarak yeniden açın") |
| 3 | (a) docs/10'da üç uç eksikti: `GET /api/health`, `POST /api/setup/env/verify` (K33 manuel akışı), yeni `GET /api/system/integrity` | ORTA | **DÜZELTİLDİ:** üçü de sözleşmeye işlendi. Faz 2/3 uçları (share, export, inbox, capture, extension-token, `/p/{token}`) bilinçli olarak sözleşmede var/kodda yok — faz planı gereği AÇIK |
| 4 | (b) Migration'lar: temiz MySQL 8.4'te 16/16 ✓; mevcut DB'de yeniden koşum 0 uygulama (checksum idempotens) ✓; FK envanteri sağlıklı (7 FK: CASCADE×6 + SET NULL×1) | — | **TEMİZ.** FK'sız `*_id` kolonları bilinçli: `activity_log.entity_id/actor_id` (polimorfik denetim izi), `*.request_id` (ULID metin), `products.external_id` (1688 kimliği) |
| 5 | (c) `.env.example`'da kodun okumadığı anahtarlar: `DB_CHARSET` (kodda sabit utf8mb4) · `APP_NAME`, `APP_DEBUG` · `CAPTURE_*`, `EXPORT_TTL_HOURS`, `SHARE_TOKEN_LENGTH` (Faz 2/3 rezervi, dosyada öyle belgeli) | DÜŞÜK | `DB_CHARSET` **DÜZELTİLDİ** (anahtar kaldırıldı, kural yorum olarak kaldı). `APP_NAME`/`APP_DEBUG` **AÇIK** — Faz 2'de karara bağlanmalı (APP_DEBUG bugün davranışı `APP_ENV` belirliyor). Faz rezervleri bilinçli, kalıyor. Kodda okunup .env.example'da olmayan anahtar YOK |
| 6 | (d) composer.json kodun gerçekten kullandığı eklentileri bildirmiyordu (sodium dersi): bcmath (MoneyService), curl (CurlMediaFetcher), gd (MediaService), mbstring, pdo_mysql, zip (release.php ZipArchive) | ORTA | **DÜZELTİLDİ:** altısı da `require`'a eklendi (openssl zaten vardı); kilit sürümler değişmeden yenilendi. `intl`/`fileinfo` kod tarafından HENÜZ kullanılmıyor → composer'a bilerek eklenmedi; RequirementChecker/preflight'ta Faz 2 rezervi olarak zorunlu (SUNUCU-PROFILI: sunucuda VAR). Kullanılmayan paket yok |
| 7 | (e) Frontend: `vite.config` outDir `../public/panel` + `base '/panel/'` ✓; API istemcisi aynı-origin göreli `/api` (çerez+CSRF uyumlu) ✓; para alanlarında JS aritmetiği YOK (tarama: parseFloat/Number(price·rate·total) → 0 eşleşme; istemci sözleşmesi "para dizesine dokunmaz") | — | **TEMİZ** |
| 8 | (f) `storage/.htaccess` YOKTU (kök yönlendirme koruyor ama savunma derinliği eksikti); `.gitignore` `storage/` kalıbı dosyayı repoya sokmuyordu | ORTA | **DÜZELTİLDİ:** `storage/.htaccess` (Require all denied) eklendi; `.gitignore` `storage/*` + `!storage/.htaccess` yapıldı; release script pakete koyuyor. `public/media/.htaccess` (çalıştırma kapalı) ve `public/.htaccess` yerinde ✓ |
| 9 | (f) CSP ↔ sihirbaz: `default-src 'self'` — wizard.js AYRI dosyadan (`/setup/wizard.js`) yükleniyor → satır içi script yok, CSP kırmıyor (ekran kanıtı: İE#9.1 tanılama ekranı çalışır hâlde). QR görseli `data:` URI → `img-src`'de `data:` VAR ✓ | — | **TEMİZ (doğrulandı)** |
| 10 | (g) Simülasyonlar: YAZILAMAZ kök → K33 manuel .env akışı + hotlink (testli) ✓ · sodium'suz → OpenSSL yolu, CI job'ı sodium'suz kuruyor ✓ · **setup/ EKSİK** → eskiden sessiz 404, artık 503 açıklamalı sayfa + integrity listesi (yeni test + gerçek koşum kanıtı) | — | **TAMAM** (üçü de anlaşılır ekran veriyor) |

## Gerçek Koşum Kanıtları (prova zip'i, `bin/release.php` çıktısı)

```
RELEASE HAZIR ve DOĞRULANDI — 654 dosya (+ MANIFEST.txt)

GET /setup                → HTTP 200 · text/html · sihirbaz sayfası ✓ (JSON DEĞİL)
GET /api/setup/state      → HTTP 200
GET /api/system/integrity → {"manifest_exists":true,"ok":true,"total":654,"checked":654,
                             "missing":[],"missing_count":0,"modified":[],"modified_count":0}

— setup/ silinerek eksik-açılma simülasyonu —
GET /setup                → HTTP 503 · "Kurulum dosyaları eksik yüklenmiş" sayfası ✓
GET /api/system/integrity → ok=false · missing_count=4 ·
                             setup/.gitkeep · setup/views/wizard.css · setup/views/wizard.html · setup/views/wizard.js
```

## Kök Çözümün Üç Katmanı (bir daha olamaz)
1. **Üretimde:** `bin/release.php` eksik zip'i HİÇ üretmez (üretim sonrası zorunlu doğrulama).
2. **CI'da:** `uretim-profili` job'ı her PR'da zip'i üretir, açar, çalıştırır: `/setup` text/html + state 200 + integrity temiz olmadan yeşil yok.
3. **Sunucuda:** eksik/bozuk açılma yine de olursa integrity ucu + sihirbaz gereksinim adımı + `/setup` 503 sayfası eksiği İSİM İSİM söyler — sessiz kalamaz.
