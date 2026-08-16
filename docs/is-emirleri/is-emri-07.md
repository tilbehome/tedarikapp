# İŞ EMRİ #7 — K33 Sunucu Uyum Paketi (Paylaşımlı-DSO Modu) + Eksik Uçlar
Faz: Faz 1 · Dal: `is-emri-7-k33-uyum` (PR aç; merge PM onayıyla) · Test rejimi: K35 (kritik=test, gerisi smoke)

> ÖN ŞART: PR #5 merge. Bağlam: üretim sunucusunda PHP `nobody` (DSO) ile çalışıyor ve KALICI —
> uygulama diske yazamaz; tek istisna kontrollü `public/media`. Oku: docs/07, K33–K35 (bu emirde işlenecek).

## Bölüm A — K33 Uyum (dosyasızlaştırma)
1. **DB loglama:** `app_logs` migration (K23) + Monolog DB handler. `LOG_DRIVER` env: `db|file` (üretim varsayılanı db, dev'de file kalabilir). Redaction/Request-ID aynen geçerli.
2. **SetupLock → DB:** kilit `settings` (veya `system_state`) kaydına taşınır; kilit-sonrası-403 davranışı ve testleri korunur (kritik: regresyon testi).
3. **EnvWriter manuel akış:** sihirbaz `.env`'i yazamıyorsa üretilen içeriği ekranda gösterir → "Dosya Yöneticisi'nden kaydettim" → sihirbaz dosyayı okuyup doğrular (APP_KEY eşleşmesi) → devam. Yazabiliyorsa eski yol.
4. **MediaService (kritik, testli):** URL'den indir (SSRF korumalı: yalnız http/https + izinli hostlar) → GD ile yeniden kodla → kriptografik rastgele ad → `public/media/`. **Çift mod:** media yazılamıyorsa `media_mode=hotlink` (ayar DB'de; API `/api/system/status`'ta expose → panel rozeti Faz 1D'de bağlanır). Hotlink modunda orijinal URL saklanır, indirme denenmez.
5. **Koruma dosyaları repo'da hazır gelir:** `public/media/.htaccess` — PHP engine kapalı (`<FilesMatch \.ph.*>` deny + `SetHandler none`), `Options -Indexes`, Referer hotlink kuralı (kendi domain + boş referer izinli), `X-Robots-Tag: noindex` header; kök `robots.txt` — `Disallow: /media/`.

## Bölüm B — Panel Öncesi Eksik Uçlar (docs/10'a uygun; Auth+CSRF)
6. **Settings:** `GET/PUT /api/settings` (yuan_rate, usd_rate; PUT → `rate_history` kaydı) + `GET /api/settings/rate-history`. Yeni liste oluşturmada kur bu ayardan kilitlenir (SettingsRepository seed yalnız ilk kurulumda).
7. **Categories:** CRUD uçları (`GET/POST/PATCH/DELETE /api/categories`; silmede ürünü olan kategori 422).

## Bölüm C — Şema Ekleri (K23 tek-DDL; GTİP hazırlığı — M23 değerlendirmesi §6)
8. `products.raw_attributes` JSON NULL (parser'ın ham özellik/spec verisi; Faz 3'te dolacak).
9. `products.country_of_origin` + `products.country_of_dispatch` (CHAR(2) ISO, NULL).

## Bölüm D — Belge Senkronu
10. docs/08 karar tablosuna: **K31** (paylaşım detay görünümü — Faz 2), **K33** (paylaşımlı-DSO uyumu, bu emirdeki 5 madde + çift mod), **K34** (güvenlik temel çizgisi: kapı-başına-anahtar + DB'de hash/şifreli saklama), **K35** (test kalibrasyonu).
11. docs/06'ya K35 rejimi; docs/07 runbook'a K33 kurulum akışı (manuel .env adımı, media izni adımı: `chmod 777 public/media` YALNIZ koruma dosyaları yerindeyken, sihirbaz talimatıyla).
12. docs/04 §2b'ye: `cancelled` terminaldir; iptal edilen ürün toplamlara girmez; yanlış iptal çözümü = ürünü kopyala.
13. docs/arastirma: Ürün Sahibi'nin vereceği **güncel 1688parserraporu.md** (55KB final) mevcut sürümün YERİNE; **M23gtipmodulupmdegerlendirmesi.md** → `docs/arastirma/gtip-m23-degerlendirme.md` olarak eklenir; f30-gtip-motoru.md'ye tek satır: "Doğrulanmış kaynaklar ve v1-dışı hükmü için bkz. gtip-m23-degerlendirme.md".
14. CHANGELOG.

## Kapsam DIŞI
React panel, export üretimi, paylaşım sayfası/uçları, capture ucu, eklenti.

## Kabul (K35 rejimi)
- [ ] KRİTİK testler: SetupLock-DB kilit sonrası 403; MediaService (yeniden kodlama çıktısı orijinalden bağımsız + rastgele ad + hotlink moduna düşüş + SSRF reddi); kur PUT → yeni listede kilitlenen değer; app_logs'a yazım + redaction.
- [ ] Smoke: categories CRUD, rate-history listesi, robots.txt/htaccess dosyalarının varlığı ve içeriği.
- [ ] Temiz kurulum uçtan uca YAZILAMAZ docroot simülasyonuyla: manuel .env akışı + DB kilit + hotlink modu algılama çalışıyor.
- [ ] CI yeşil; PHPStan lvl6 0; CS-Fixer 0; Bölüm D birebir.

## Teslim
PR + ÇIKTI RAPORU (yazılamaz-ortam kurulum akışının adım çıktıları dahil).
