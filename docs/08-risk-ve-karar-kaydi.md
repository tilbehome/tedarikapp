# tedarikapp — Risk Kaydı ve Karar Günlüğü

> Durum: YAŞAYAN BELGE — her fazda güncellenir.

## 1. Risk Kaydı

| # | Risk | Olasılık | Etki | Önlem |
|---|---|---|---|---|
| R1 | 1688 sayfa yapısını değiştirir, eklenti parser'ı bozulur | Yüksek | Orta | Parser ayrı modül; elle giriş her zaman yedek yol; test sayfa setiyle hızlı doğrulama |
| R2 | 1688/alicdn görsel hotlink'i veya erişimi kapatır | Orta | Orta | Görseller yakalandığı an sunucuya indirilir; liste asla dış linke bağımlı kalmaz |
| R3 | Hosting kısıtları (yazma izni, PHP sürüm değişikliği) | Orta | Yüksek | Runbook'ta kurulum kontrol listesi; sunucu raporu scripti repo'da tutulur, sorun anında yeniden koşulur |
| R4 | Veri kaybı (DB/görsel) | Düşük | Çok yüksek | Günlük cron yedeği + aylık geri yükleme denemesi |
| R5 | Kur/fiyat hesap hatası → yanlış siparişe para bağlanır | Düşük | Çok yüksek | Kur listeye kilitlenir; hesap tek fonksiyonda, birim testli; export öncesi panelde toplam kontrol satırı |
| R6 | Paylaşım linki istenmeyen kişilere yayılır | Orta | Düşük | Uzun rastgele token, tek tıkla iptal/yenileme |
| R7 | Tek geliştirici/tek karar verici — bilgi tek yerde | Orta | Orta | Her karar bu belgeye işlenir; belgeler repo'da; iş emirleri arşivlenir |
| R8 | Kapsam şişmesi (yeni fikirler fazları uzatır) | Yüksek | Orta | Yeni fikirler "Faz 4+ havuzu"na yazılır, aktif iş emri kapsamı değişmez |

## 2. Karar Günlüğü (ADR)

| # | Tarih | Karar | Gerekçe |
|---|---|---|---|
| K1 | Ağu 2026 | Platform: web uygulaması (masaüstü değil) | Paylaşım linki doğal üretimi, her cihazdan erişim, kurulumsuz |
| K2 | 16 Ağu 2026 | Veri yakalama: Chrome eklentisi + panel senkron (hibrit) | Resmi 1688 API'si erişilemez; scraping API ücretli ve B2B fiyatları eksik dönebiliyor; eklenti kullanıcı oturumuyla çalışır, engellenmez, bedava |
| K3 | 16 Ağu 2026 | Ana birim: sipariş listesi (ürün değil) | Gerçek operasyon liste bazlı: firmaya liste iletilir, liste takip edilir |
| K4 | 16 Ağu 2026 | Kur: elle girilir, listeye kilitlenir | Otomatik kur bağımlılık yaratır; kilitleme eski listelerin TL değerini korur — **ONAYLANDI 16 Ağu** |
| K5 | 16 Ağu 2026 | Stack: PHP 8.1 (Slim 4) + MySQL + React (Vite) | Sunucu raporuyla doğrulandı; mevcut hosting'de sıfır ek kurulum — **ONAYLANDI 16 Ağu** (uygulama kararları Ürün Sahibi tarafından PM'e delege) |
| K6 | 16 Ağu 2026 | Görseller sunucuya indirilir, hotlink yapılmaz | R2 riski + 1688 hotlink koruması |
| K7 | 16 Ağu 2026 | Adres: tedarikapp.tilbehometoptan.com | Subdomain kuruldu, sunucu raporu alındı |
| K8 | 16 Ağu 2026 | Dış istekler yalnızca cURL; e-postasız şifre kurtarma; vendor lokalde kurulup yüklenir | Sunucu kısıtları: allow_url_fopen ve mail() kapalı, composer yok |
| K9 | 16 Ağu 2026 | Çalışma modeli: Claude=PM, Bünyamin=Ürün Sahibi/Koordinatör, Claude Code=Geliştirici; iş emri döngüsü | 00-calisma-protokolu.md |
| K10 | 16 Ağu 2026 | Eklenti yakalama hibrit: varsayılan Gelen Kutusu + eklentiden opsiyonel hedef liste seçimi | Toplu ürün gezerken kutuya at, tek ürün eklerken direkt listeye — hız + esneklik |
| K11 | 16 Ağu 2026 | Paylaşım linkinde fiyatlar her zaman görünür; fiyat gizleme özelliği kapsam dışı | Ürün Sahibi kararı — ihtiyaç yok, gereksiz karmaşıklık eklenmez |
| K12 | 16 Ağu 2026 | Dış mimari görüşten alınanlar: SKU/varyasyon matrisi yakalama, 1688 ürün ID + tekrar uyarısı, mağaza adı/linki, orijinal başlık saklama, takip kodu alanı, DB indeksleri | Ürün Sahibi'nin paylaştığı dış öneri incelendi; operasyona gerçek değer katanlar alındı |
| K13 | 16 Ağu 2026 | Dış görüşten REDDEDİLENLER: FastAPI/PostgreSQL/Redis/Docker/S3/Next.js stack'i, gümrük-lojistik maliyet ayrıştırması, çoklu rol/onay, WebSocket, CI/CD | Paylaşımlı cPanel sunucuda çalışmaz / DDP modelinde gereksiz / solo operasyonda aşırı mühendislik. K5 stack kararı geçerli |
| K14 | 16 Ağu 2026 | AI bağlam mühendisliği benimsendi: repo köküne CLAUDE.md anayasası; durum makinesi geçiş kuralları backend'de zorlanır; eklenti→API JSON şeması sabitlendi; para asla float değil (DECIMAL+bcmath); para/kur/durum fonksiyonları test-first | İkinci dış bilgi paylaşımından; PM→Claude Code modelinde sapma ve halüsinasyonu yapısal olarak engeller |
| K15 | 16 Ağu 2026 | Liste yaşam döngüsü genişletildi: Aktif/Pasif/Arşiv görünümleri; listeler export sonrası dahil her zaman düzenlenebilir (export = anlık görüntü); export geçmişi + "çıktı güncel değil" rozeti; silme = 30 gün çöp kutusu; aktivite günlüğü; panel ve Excel'de TOPLAM satırı | Ürün Sahibi talebi + PM eklemeleri (kaza koruması ve firma iletişim güvenliği) |
| K16 | 16 Ağu 2026 | Kurulum: WordPress tarzı tek seferlik kurulum sihirbazı (gereksinim denetimi, .env üretimi, migration, admin+2FA kurulumu, kalıcı kilit); güvenlik sertleştirme: Argon2id, zorunlu TOTP 2FA + kurtarma kodları, artan bekleme + IP kilidi, CSRF, güvenlik başlıkları, storage webden kapalı, görseller public/media, hash'li API token, noindex paylaşım sayfası | Ürün Sahibi talebi (kurulum kolaylığı + yüksek güvenlik) + PM detaylandırması |
| K17 | 16 Ağu 2026 | Tanım aşaması KAPANDI: belge seti CHANGELOG.md, 09-arayuz-tasarim.md ve hedef kaynak dizin ağacıyla (04/6b) tamamlandı; bundan sonra yeni özellik fikirleri fikir havuzuna gider, belgeler yalnızca iş emirleriyle güncellenir | Belge doygunluğu — daha fazla hazırlık sahaya çıkışı geciktirir |
| K18 | 16 Ağu 2026 | Tanım tamamlama paketi: docs/10 API sözleşmesi sabitlendi (zarf, hata kodları, sayfalama, uç bazlı gövdeler); şema K16/K15 ile hizalandı (users.totp_secret, recovery_codes, remember_tokens, lists.period/updated_at, inbox_items.status, activity_log.ip); veri doğrulama kuralları docs/04 §2d'ye yazıldı; açık soru 1–2 kapandı işaretlendi; akış numaralandırması düzeltildi (6a→6, firma tarafı→7) | Ürün Sahibi talimatı: "sonradan ilave işi bozar, eksik kalmasın" — Faz 1 migration ve API iş emirleri sabit zemin ister |
| K19 | 16 Ağu 2026 | Onaylı kütüphane ve araç listesi anayasaya (CLAUDE.md §2) eklendi: composer/npm paketleri sabit; liste dışı her paket PM onayı ister; lock dosyaları repoya girer; her faz sonu composer/npm audit; PHPStan (seviye 6+) ve CS-Fixer her PR öncesi temiz | Bağımlılık disiplini — K5/K13 stack sınırlarının paket seviyesinde uygulanması; sürüm sürprizi ve bağımlılık şişmesini önler |
| K20 | 16 Ağu 2026 | Hızlı paylaşım butonları (WhatsApp wa.me + mailto + kopyala + Web Share) Faz 2 kapsamına alındı; sunucudan otomatik gönderim (SMTP / WhatsApp Business API) fikir havuzunda | Sıfır maliyet/API ile iletme akışının doğal parçası; otomatik gönderim solo operasyonda maliyetine değmez |
| K21 | 16 Ağu 2026 | PHP sürümü 8.4'e yükseltildi (hosting destekliyor) | 8.1'in güvenlik desteği Aralık 2025'te bitti; kod başlamadan geçiş sıfır maliyet |
| K22 | 16 Ağu 2026 | Makine enum standardı: DB ve API'de durum/görünürlük değerleri İngilizce sabit kod; Türkçe yalnızca UI etiketi. Ürün: `to_order, ordered, in_transit, received, cancelled` · Liste: `draft, sent, ordered, completed, cancelled` · Görünürlük: `active, passive, archived` · Gelen kutusu: `pending, error, assigned`. Çeviri tablosu docs/09 §6 | Ürün/liste tabloları henüz yazılmadı; değişim şimdi bedava, sonra çok pahalı. Türkçe makine değeri kodda karşılaştırma, sıralama ve JSON taşımada sorun çıkarır |
| K23 | 16 Ağu 2026 | Migration standardı: 1 migration = 1 DDL değişikliği; `migrations` tablosuna `checksum` (dosya sha256) + `execution_ms`; uygulanmış migration'ın checksum'u değişmişse Migrator hata verir | MySQL'de DDL örtük commit yapar — çok-DDL'li migration yarıda kalırsa transaction geri almaz ve tekrar koşumda patlar. Checksum, uygulanmış dosyanın sessizce değişmesini yakalar |
| K24 | 16 Ağu 2026 | Para ve yuvarlama politikası (docs/04 §2e): birim fiyatlar ve kurlar `DECIMAL(12,4)`; satır toplamı = bcmath ile `qty × birim × kur` → 2 hane HALF_UP; genel toplam = yuvarlanmış satır toplamlarının toplamı; ara hesaplarda scale ≥ 6; API'de para string (K14) | Yuvarlama sırası belirtilmezse panel, Excel ve paylaşım sayfası birbirini tutmayan toplamlar üretir |
| K25 | 16 Ağu 2026 | API sözleşmesi revizyon paketi (docs/10): gerçek 405 + `METHOD_NOT_ALLOWED` + `Allow` başlığı · `/api/capture` gövdesine `capture_id` (UUIDv4, UNIQUE — idempotans), `schema_version`, `extension_version`, `parser_version` · tekrar kontrolü ve indeks `platform + external_id` · `exports` gerçek anlık görüntü tutar (`snapshot_json, sha256, file_size, status, list_revision`) · `lists.revision` sayacı ("çıktı güncel değil" artık revision karşılaştırması) · paylaşım token'ı DB'de hash'li (`share_token_hash` + `share_token_prefix`) + opsiyonel `share_expires_at` | Eklenti tekrar denemesi çift kayıt açıyordu; `updated_at` karşılaştırması not düzenlemesinde bile "çıktı eski" diyordu; paylaşım token'ı DB sızıntısında düz metin ele geçiyordu |
| K26 | 16 Ağu 2026 | CI evet / CD hayır (K13'ün kısmi revizyonu): GitHub Actions ile her PR'da `composer validate` → `install` → PHPUnit → PHPStan → CS-Fixer (dry-run) → `composer audit`. Deploy manuel kalır | K13'te CI/CD birlikte reddedilmişti; CD paylaşımlı cPanel'de gerçekten gereksiz, ancak CI sıfır maliyetle regresyonu yakalıyor. Deploy'un manuel kalması K13 gerekçesini korur |
| K27 | 16 Ağu 2026 | Güvenlik sertleştirme ekleri: TOTP secret şifreleme spesifikasyonu (sodium `crypto_secretbox`, yoksa AES-256-GCM; anahtar APP_KEY'den türetilir; kayıt `versiyon:nonce:ciphertext`) · log redaction · Request-ID · katı config doğrulama · production'da APP_KEY/EXTENSION_TOKEN_SALT zorunlu · JSON log | Sır rotasyonu ve olay incelemesi (hangi istek neyi yaptı) sonradan eklenemeyecek şeyler; formatın versiyonlanması anahtar rotasyonunu mümkün kılar |

Yeni kararlar bu tabloya eklenir; bir karar değişirse silinmez, üzeri çizilip yeni satır açılır (tarihçe korunur).

## 3. Fikir Havuzu (Faz 4+)

Kapsamı şişirmemek için yeni fikirler buraya park edilir; aktif iş emri kapsamı değişmez (R8).

| # | Fikir | Hedef |
|---|---|---|
| F1 | Otomatik kur çekme (open.er-api.com erişimi doğrulandı) | Faz 4 |
| F2 | Çoklu tedarikçi karşılaştırma | Faz 4+ |
| F3 | Taobao/Alibaba.com desteği (platform alanı ve modüler parser altyapısı şimdiden hazır) | Faz 4+ |
| F4 | Maliyet-kâr analizi ve gümrük/lojistik kalem ayrıştırması (TilbeSync ile veri alışverişi) | Faz 4+ |
| F5 | Çince başlık otomatik çeviri | Faz 4+ |
| F6 | Sunucudan otomatik gönderim (SMTP mail / WhatsApp Business API) | Faz 4+ |
| F7 | PWA: panel ana ekrana kurulabilir uygulama gibi davranır — düşük maliyet, yüksek değer | Faz 4 güçlü aday |
| F8 | Mal kabul sayım modu: konteyner gelince telefondan ürünleri tek tek "Geldi" işaretleme + eksik/hasar notu | Faz 4 |
| F9 | Fiyat değişim uyarısı: aynı ürün tekrar yakalandığında eski/yeni Yuan fiyat karşılaştırması (external_id + fiyat geçmişi) | Faz 4 |
| F10 | Excel özet sayfası: çıktının 2. sekmesinde kategori bazlı adet/tutar özeti | Faz 4 |
| F11 | Yedeklerin uzak kopyası (off-site yedek): gece yedeğinin Google Drive'a da atılması | ~~Faz 4~~ → **CANLIYA ALMA ÖN ŞARTI** (İE#4 REV2) |
| F12 | Tedarikçi kartları: firma bazlı liste geçmişi ve iletişim notları | v1.1 (çoklu firma ihtiyacı doğunca) |
| F13 | MariaDB/MySQL tabanlı iş kuyruğu + asenkron export (`POST /exports` modeli) | Faz 2 tasarım sorusu — güçlü aday |
| F14 | Medya dedup: sha256 tabanlı `media_assets` tablosu, aynı görsel bir kez saklanır | Faz 4 |
| F15 | Thumbnail motoru (160/480 px türevleri) | Faz 2 güçlü aday |
| F16 | Optimistic concurrency: `version` kolonu ile çakışan düzenleme tespiti | Faz 4 |
| F17 | Staging subdomain (canlıya çıkmadan prova ortamı) | Faz 4 |
| F18 | Paylaşım linki süre seçenekleri arayüzü (`share_expires_at` UI'ı) | Faz 2 |
| F19 | `extension_devices`: cihaz bazlı eklenti token'ı ve tek tek iptal | Faz 4 |
| F20 | Parser telemetrisi + 1688 fixture test seti (sayfa yapısı değişimini erken yakalama) | Faz 3 |
| F21 | OpenAPI/contracts tek kaynak — docs/10'un makine okunur karşılığı | Faz 4 |
| F22 | Playwright ile uçtan uca (E2E) test | Faz 4 |
| F23 | WebAuthn / passkey ile girişte ikinci faktör alternatifi | Faz 4+ |
| F24 | ASVS 5.0 canlıya alma güvenlik kontrol listesi | Faz 4 |
| F25 | Ana ekran widget seti (dashboard kartları genişletmesi) | Faz 4 |
| F26 | Sevkiyat/konteyner modeli (ürünleri sevkiyata bağlama) | Faz 4+ |
| F27 | Tedarikçi teklif portalı + teklif versiyonları | Faz 4+ |
| F28 | Product master (`source_products`): aynı ürünün listeler arası tek kaydı | Faz 4+ |
| F29 | Mal kabul genişletme: QR ile sayım, koli/CBM bilgisi (F8'in devamı) | Faz 4+ |
| F30 | HS/GTIP kodu öneri modülü | Faz 4+ |
| F31 | Belgeler modülü (fatura, çeki listesi, konşimento saklama) | Faz 4+ |
| F32 | Tedarikçi skorlama (termin, kalite, fiyat geçmişine göre) | Faz 4+ |
| F33 | AI kullanım paketi: çeviri, kategori önerisi, anomali tespiti — hepsi insan onaylı | Faz 4+ |
