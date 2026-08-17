# tedarikapp — Teknik Tasarım

> Durum: v1.0 — ONAYLANDI (16.08.2026)
> Stack ve mimari kararlar 16.08.2026'da onaylanmıştır (bkz. 08 no'lu belge, K5).

## 1. Stack Önerisi

| Katman | Seçim | Gerekçe |
|---|---|---|
| Backend | PHP 8.4 (Slim 4 mikro-framework) + REST API | Hosting 8.4 destekliyor (K21); ek kurulum gerektirmeden çalışır; ekosistemin (WP eklentileri, MCP gateway) zaten PHP |
| Veritabanı | MySQL / MariaDB | Hosting'de hazır |
| Frontend (panel) | React 19 + TypeScript + Vite, mobile-first | Hızlı, uygulama hissi; build çıktısı hosting'e statik yüklenir. TypeScript, docs/10 sözleşmesindeki tiplerin derleme anında zorlanmasını sağlar (İE#4 REV2; sürümler Faz 1D başında doğrulanıp `package-lock` ile pin'lenir — K19 npm listesine TypeScript eklendi) |
| Paylaşım sayfası | Sunucu tarafında render edilen hafif sayfa (PHP + şablon) | Firma tarafı için JS yükü olmadan hızlı açılır, link önizlemeleri (WhatsApp/WeChat) düzgün çıkar |
| Excel | PhpSpreadsheet | Hücreye gömülü görsel destekler — örnek formatla birebir çıktı |
| PDF | mPDF | Görselli, Türkçe karakter sorunsuz |
| Eklenti | Chrome Manifest V3 (vanilla JS) | Content script 1688 DOM'unu okur, API'ye POST eder |

Barındırma: `tedarik.` alt alan adı (hangi domain altında olacağı kararlaştırılacak), SSL zorunlu.

## 2. Veritabanı Şeması (özet)

```
users            id, email, password_hash,
                 totp_secret,                 -- şifreli saklanır (K16, 2FA zorunlu)
                 created_at, updated_at
recovery_codes   id, user_id, code_hash, used_at      -- 2FA kurtarma kodları: hash'li, TEK kullanımlık
remember_tokens  id, user_id, selector, token_hash,   -- "beni hatırla": selector açık, doğrulayıcı hash'li
                 expires_at, created_at               -- panelden tek tıkla iptal edilebilir
settings         key, value                  -- yuan_tl, usd_tl, extension_token_hash...
rate_history     id, currency, rate, set_at  -- kur tarihçesi
categories       id, name, sort
lists            id, name, period,            -- dönem: Excel başlığındaki "{DÖNEM}" (örn. "EYLÜL 2026")
                 supplier_name, status, note, -- status: draft|sent|ordered|completed|cancelled (K22)
                 visibility,                  -- active | passive | archived (K22)
                 yuan_rate, usd_rate,         -- listeye kilitlenen kur
                 rate_locked_at,              -- kur kilit anı: status=sent olduğunda yazılır (K4)
                 revision,                    -- ürün/fiyat/adet/sıra değişiminde +1 (K25)
                 share_token_hash,            -- paylaşım linki: SHA-256 hash'i saklanır (K25)
                 share_token_prefix,          -- linki tanımak için ilk haneler (düz)
                 share_expires_at,            -- opsiyonel süre sınırı (UI Faz 2)
                 created_at, updated_at,
                 archived_at, deleted_at      -- soft delete (çöp kutusu)
products         id, list_id, sort_no, category_id,
                 platform,                    -- '1688' (ileride Taobao vb.)
                 external_id,                 -- 1688 ürün ID (tekrar kontrolü)
                 name, name_original,         -- Türkçe ad + yakalanan orijinal başlık
                 detail, url,
                 vendor_name, vendor_url,     -- Çinli satıcı mağaza
                 sku_selection,               -- JSON: seçilen varyasyon (renk/beden)
                 sku_matrix,                  -- JSON: yakalanan tüm varyasyon matrisi
                 main_image, video_url,
                 qty, price_yuan, price_ddp_usd,
                 tracking_no,                 -- kargo/konteyner takip kodu
                 status,                      -- to_order|ordered|in_transit|received|cancelled (K22)
                 note, created_at, updated_at, deleted_at  -- soft delete
product_images   id, product_id, path, sort  -- ek görseller
product_status_history                        -- durum tarihçesi activity_log'a GÖMÜLMEZ (K25)
                 id, product_id, from_status, to_status,
                 actor_type, actor_id, changed_at, request_id
inbox_items      id, capture_id,              -- UUIDv4, UNIQUE — idempotans anahtarı (K25)
                 raw_json, status,            -- pending | assigned | error (K22)
                 schema_version, extension_version, parser_version, platform,
                 created_at
exports          id, list_id, format,         -- export geçmişi + gerçek anlık görüntü (K25)
                 snapshot_json,               -- üretim anındaki liste + ürün verisi
                 sha256, file_size, status,
                 list_revision,               -- "çıktı güncel değil" = lists.revision != list_revision
                 created_at
activity_log     id, entity_type, entity_id, action, detail,
                 ip,                          -- giriş denemeleri ve kritik işlemler için kaynak IP (K16)
                 actor_type, actor_id,        -- kim yaptı (admin | extension | system) — K25
                 request_id, user_agent,      -- olay incelemesi: istek eşleştirme (K27)
                 created_at                   -- ekleme/silme/düzenleme tarihçesi
```

Para tipleri: birim fiyat ve kur alanları `DECIMAL(12,4)`; float KULLANILMAZ, hesaplar PHP bcmath ile yapılır (K14/K24 — ayrıntı §2e).

İndeksler: products(list_id), products(status), products(platform, external_id), activity_log(entity_type, entity_id), activity_log(request_id), lists(visibility), remember_tokens(selector), inbox_items(capture_id) UNIQUE, product_status_history(product_id). Tekrar-ekleme uyarısı **platform + external_id** çifti üzerinden yapılır (benzersizlik zorlanmaz — aynı ürün bilerek iki listede olabilir; K25).

TL fiyatları veritabanında saklanmaz; listenin kilitli kuru × orijinal fiyat olarak her yerde hesaplanır (tutarsızlık riski sıfır).

## 2b. Durum Makinesi (backend'de zorlanır)

Makine değerleri İngilizcedir (K22); Türkçe karşılıklar yalnızca arayüz etiketidir (çeviri tablosu docs/09 §6).

```
Ürün : to_order → ordered → in_transit → received
       her durumdan → cancelled (received hariç)
       düzeltme: yalnızca bir adım geri alınabilir; durum atlama YASAK
Liste: draft → sent → ordered → completed (+cancelled)
       completed ⇐ ancak tüm ürünler received veya cancelled ise
       completed ve cancelled TERMİNALDİR (K37 §B4): çıkış yok, reopen ucu yok
       sent'e geçişte kur kilitlenir ve rate_locked_at yazılır (K4)
```

Geçersiz geçiş isteğini API reddeder (HTTP 422 + açıklama); kural yalnızca arayüzde değil sunucuda yaşar. Her geçiş `product_status_history`'ye yazılır (K25).

**Uygulama kuralları (İE#6'da sabitlendi, K37/İE#9 ile güncellendi):**
- **`cancelled` TERMİNALDİR** — iptal edilen bir ürünün hangi duruma döneceği belirsizdir; tarihçeden çıkarmak sessiz veri üretir.
- **`completed` liste de TERMİNALDİR (K37 §B4)** — İE#6'daki "bir adım geri (`ordered`)" izni kaldırılmıştır: tamamlanmış liste donmuş kayıttır, ürünlerine ve alanlarına hiçbir mutasyon yapılamaz (`LIST_IMMUTABLE`). Yanlış kapatılan listenin çözümü kopyalamaktır (kopya `draft` açılır, güncel kuru alır — K4 ile tutarlı). Ürün tarafındaki `received → in_transit` tek-adım-geri düzeltmesi aynen korunur.
- **İptal edilen ürün liste toplamlarına GİRMEZ** — sipariş edilmeyecek mala para bağlanmaz. İlerleme sayacında (`progress`) görünmeye devam eder.
- **Yanlış iptalin çözümü:** ürünü kopyalayıp yeni kayıtla devam etmek. Durum makinesini gevşetmek yerine bu yol seçilmiştir; tarihçe bozulmaz, iptal kaydı yerinde kalır.

## 2c. Veri Sözleşmesi — Eklenti → `POST /api/capture` (SABİT ŞEMA, K14)

```json
{
  "capture_id": "9f1c8d2e-4b7a-4c31-9f0e-2a6d5b3c8e11",
  "schema_version": 1,
  "extension_version": "1.0.0",
  "parser_version": "1688-2026.08",
  "platform": "1688",
  "external_id": "678239127001",
  "title_original": "304不锈钢保温饭盒...",
  "url": "https://detail.1688.com/offer/....html",
  "vendor": { "name": "...", "url": "..." },
  "price_yuan": "9.00",
  "sku_matrix": [ { "props": {"颜色": "白色"}, "price_yuan": "9.00", "min_qty": 24 } ],
  "sku_selection": null,
  "images": ["https://cbu01.alicdn.com/...jpg"],
  "video_url": null,
  "target_list_id": null
}
```

Kurallar: para alanları STRING taşınır (float hassasiyet kaybına karşı); `target_list_id: null` → Gelen Kutusu; alan adları değiştirilemez, şema değişikliği PM kararı + belge güncellemesi gerektirir. Backend her alanı doğrular (tip, uzunluk, URL deseni), doğrulanamayan istek ham haliyle `inbox_items.raw_json`'a düşer ve panelde "hatalı yakalama" (`status = error`) olarak gösterilir.

**İdempotans (K25):** `capture_id` UUIDv4'tür ve `inbox_items` üzerinde UNIQUE'tir. Eklenti bir isteği tekrar denerse (kuyruk mantığı — §4) aynı `capture_id` gelir; sunucu yeni kayıt AÇMAZ, ilk isteğin sonucunu döner. Böylece ağ kopmasında çift ürün oluşmaz. `schema_version`, `extension_version` ve `parser_version` her kayda yazılır: 1688 sayfa yapısı değişip parser bozulduğunda hatalı kayıtların hangi sürümden geldiği kaynaktan okunur.

## 2d. Veri Doğrulama Kuralları (sistem sınırında zorlanır — K18)

Tüm kurallar backend'de uygulanır; arayüz yalnızca kullanıcı deneyimi için aynı kuralları önden gösterir. İhlal → HTTP 422 + alan bazlı hata (format: docs/10 §1).

| Alan | Kural |
|---|---|
| Liste adı / dönem / tedarikçi | zorunlu ad: 1–200; dönem ≤ 50; tedarikçi ≤ 200 karakter |
| Ürün adı | zorunlu, 1–300 karakter (name_original ≤ 500, salt saklama) |
| Detay / not | ≤ 2000 karakter |
| Miktar (qty) | tam sayı, 1 – 1.000.000 |
| Fiyatlar (price_yuan, price_ddp_usd) | string decimal, `0` – `9999999.99`, en çok 2 ondalık; negatif ASLA |
| Kurlar (yuan_rate, usd_rate) | string decimal, `0.0001` – `1000`, en çok 4 ondalık |
| URL alanları (url, vendor_url, video_url, görsel URL'leri) | https zorunlu, ≤ 1000 karakter |
| Görsel/video indirme | YALNIZCA `MEDIA_ALLOWED_HOSTS` alan adlarından (SSRF koruması); tek dosya ≤ `MEDIA_MAX_MB`; içerik-tipi doğrulanır — ayrıntılı kurallar aşağıda |
| Ürün başına ek görsel | ≤ 20 |
| Takip kodu | ≤ 100 karakter |
| Kategori | tanımlı listeden `category_id` (serbest metin kabul edilmez) |
| E-posta / şifre | e-posta RFC uyumlu ≤ 190; şifre ≥ 10 karakter |
| Yakalama isteği | gövde ≤ `CAPTURE_MAX_PAYLOAD_KB`; hız ≤ `CAPTURE_RATE_PER_MIN`; `capture_id` UUIDv4 ve tekil |

### Medya İndirme Güvenliği (derinleştirilmiş — İE#4 REV2)

Sunucu, dışarıdan gelen bir URL'yi kendisi çektiği için bu akış klasik SSRF yüzeyidir. Kurallar sırayla uygulanır; herhangi biri düşerse indirme yapılmaz:

1. **Yalnız HTTPS.** `http://`, `ftp://`, `file://`, `data:` reddedilir.
2. **Host eşleşmesi tam sonek kuralıyla:** izinli alan adı `alicdn.com` ise `cbu01.alicdn.com` GEÇER, `alicdn.com.evil.com` GEÇMEZ (düz `str_contains` denetimi kullanılmaz).
3. **DNS çözümü denetlenir:** çözülen IP private (10/8, 172.16/12, 192.168/16), loopback (127/8, ::1), link-local (169.254/16, fe80::/10) veya benzeri ayrılmış aralıklardaysa REDDEDİLİR — iç ağa erişim engellenir.
4. **Yönlendirme (redirect) hedefi yeniden doğrulanır:** en fazla 3 yönlendirme; her adımda 1–3 numaralı kurallar baştan uygulanır (cURL'ün kör redirect takibi kullanılmaz).
5. **Boyut sınırı:** `MEDIA_MAX_MB`; hem `Content-Length` hem de akış sırasında gerçek bayt sayımı denetlenir (yalan başlık koruması).
6. **MIME allowlist:** yalnızca `image/jpeg`, `image/png`, `image/webp`, `image/avif`, `image/gif`. **SVG YASAKTIR** (içine script gömülebilir ve tarayıcıda çalışır).
7. **Magic-byte doğrulaması:** dosyanın gerçek türü içeriğinden okunur; sunucunun bildirdiği `Content-Type` tek başına yeterli sayılmaz.
8. **Yeniden encode:** indirilen görsel GD ile yeniden üretilir — EXIF/metadata ve dosyaya iliştirilmiş gizli yükler temizlenir. Dosya adı sunucu tarafından üretilir, kaynak adı kullanılmaz.

## 2e. Para ve Yuvarlama Politikası (K24)

Para asla float değildir (K14). Bu bölüm, yuvarlamanın **nerede** yapılacağını sabitler; aksi hâlde panel, Excel ve paylaşım sayfası birbirini tutmayan toplamlar üretir.

- **Saklama:** birim fiyatlar `DECIMAL(12,4)`, kurlar `DECIMAL(12,4)`.
- **Ara hesap:** bcmath ile, **scale ≥ 6**. Ara sonuçlar yuvarlanmaz.
- **Satır toplamı:** `qty × birim_fiyat × kur` → sonuç **2 haneye HALF_UP** yuvarlanır.
- **Genel toplam:** yuvarlanmış **satır toplamlarının** toplamıdır (ham değerlerin toplamı yuvarlanmaz — "önce yuvarla, sonra topla").
- **Taşıma:** API'de tüm para alanları string (K14), örn. `"30412.80"`.
- Bu kuralların birim testleri para/kur fonksiyonlarıyla birlikte TEST-FIRST yazılır (CLAUDE.md §3).

## 3. API (özet)

```
POST   /api/auth/login
GET    /api/lists                 GET/POST/PATCH/DELETE /api/lists/{id}
POST   /api/lists/{id}/duplicate
GET    /api/lists/{id}/export?format=xlsx|pdf|csv
POST   /api/lists/{id}/share      (token üret/yenile/iptal)
CRUD   /api/lists/{id}/products   + PATCH /api/products/bulk (taşı/durum/sil)
GET    /api/inbox                 POST /api/inbox/{id}/assign
POST   /api/capture               ← eklentinin tek endpoint'i (Bearer: extension_token)
GET/PUT /api/settings, /api/categories
GET    /p/{share_token}           ← herkese açık paylaşım sayfası (API değil, sayfa)
```

Uç bazlı istek/yanıt gövdeleri, hata zarfı, sayfalama ve durum kodları **docs/10-api-sozlesmesi.md**'de sabitlenmiştir (K18) — frontend ve backend bu sözleşmeye göre ayrı ayrı geliştirilebilir.

## 4. Eklenti Mimarisi

- **Content script**: 1688 ürün sayfasında `window` içindeki ürün veri objesini ve DOM'u okur (başlık, fiyat kademeleri, **tam skuMap/varyasyon matrisi**, görseller, video URL, **ürün ID, mağaza adı/linki**). 1688 sayfa yapısı değişirse yalnızca bu parser güncellenir — parser ayrı bir modül olarak yazılır.
- **Popup**: önizleme + fiyat/görsel seçimi + opsiyonel hedef liste seçimi + "Panele Gönder".
- **Background**: `POST /api/capture` çağrısı; başarısızsa `chrome.storage.local` kuyruğuna yazar, tekrar dener.
- Görseller URL olarak gönderilir; sunucu tarafı görselleri indirip kendi diskine kaydeder (1688 hotlink koruması ve link ölmesi riskine karşı).

## 5. Güvenlik (K16 — sertleştirilmiş)

**Kimlik doğrulama**
- Tek admin; şifre **Argon2id** ile hash'lenir.
- **2FA zorunlu:** TOTP (Google Authenticator/Authy) — kurulum sihirbazında QR ile tanımlanır; kurtarma kodları üretilir (mail kapalı olduğundan tek doğru yöntem).
- Giriş denemelerinde artan bekleme (exponential backoff) + 5 hatalı denemede IP bazlı geçici kilit; tüm denemeler (IP, zaman, sonuç) activity_log'a yazılır.
- Oturum: HttpOnly + Secure + SameSite=Lax çerez, boşta kalmada zaman aşımı, "beni hatırla" ayrı ve iptal edilebilir token.

**İstek güvenliği**
- Tüm formlarda CSRF token; API'de yalnızca JSON + origin denetimi.
- SQL yalnızca prepared statements; tüm çıktılar escape (XSS) — dışa açık tek yüzey olan paylaşım sayfasında ekstra titizlik.
- Güvenlik başlıkları: CSP, HSTS, X-Frame-Options: DENY, X-Content-Type-Options: nosniff, Referrer-Policy.

**Dosya ve token güvenliği**
- `storage/` webden tamamen erişime kapalı (.htaccess deny + docroot dışı mantık); yalnızca ürün görselleri `public/media/` altından servis edilir.
- Eklenti Bearer token'ı DB'de hash'li saklanır, yalnızca `/api/capture` yetkisi vardır, panelden tek tıkla yenilenir.
- Paylaşım linki: 22+ karakter kriptografik rastgele token, salt okunur, iptal/yenileme; paylaşım sayfaları `noindex` başlığıyla arama motorlarına kapalıdır.
- Kurulum sihirbazı kurulumdan sonra kendini kalıcı kilitler; kilitliyken erişim denemesi loglanır.

**İşletme**
- Yedekleme: veritabanı + `public/media/` günlük cron yedeği; aylık geri yükleme denemesi.
- Her faz sonunda bağımlılık güvenlik güncellemeleri (composer/npm audit) kontrol edilir.
- `display_errors` kapalı kalır; hata detayı yalnızca `storage/logs/`a yazılır.

## 6. Açık Teknik Sorular (istişare)

1. ~~Alt alan adı hangisi olsun?~~ **KAPANDI:** `tedarikapp.tilbehometoptan.com` (K7, bkz. §7).
2. ~~Hosting'de PHP sürümü ve cron erişimi teyit edilecek.~~ **KAPANDI:** PHP 8.1.34 + cPanel cron doğrulandı (16.08.2026 sunucu raporu, bkz. §7).
3. 1688 video URL'leri bazı durumlarda oturum istiyor — Faz 3'te parser yazılırken gerçek sayfalarla test edilip gerekirse video dosyası da sunucuya indirilecek. Karar testten sonra. **(AÇIK — tek kalan soru)**

## 6b. Kaynak Dizin Ağacı

Bu bölüm **gerçeği yansıtır** (İE#5'te repo ile eşitlendi). Henüz yazılmamış klasörler ⏳ ile işaretlidir;
yazıldıklarında bu ağaç aynı PR'da güncellenir — belge ile repo arasında sapma bırakılmaz.

```
tedarikapp/
├── CLAUDE.md · README.md · CHANGELOG.md · .gitignore · .env.example
├── composer.json · composer.lock · phpunit.xml · phpstan.neon · .php-cs-fixer.php
├── .htaccess                 (docroot public'e çekilemezse public/'e yönlendirir)
├── ornek-tedarik-listesi.xlsx  (Excel çıktısının referans şablonu — gerçek veri içermez, K28)
├── .github/workflows/ci.yml  (K26: PHPUnit + PHPStan + CS-Fixer + audit)
├── docs/                     00–10 belgeleri
│   ├── is-emirleri/          (is-emri-01 … 05)
│   ├── arastirma/            (1688 veri envanteri + parser raporu)
│   └── fikirler/             (havuzdaki fikirlerin taslak metinleri)
├── app/                      PHP kaynak (docroot dışı)
│   ├── Auth/                 AuthServices · AuthSession · SessionInterface · NativeSession
│   │                         UserRepository · User · PasswordHasher · TotpService
│   │                         RecoveryCodeService · RememberTokenService/Match/Status · LoginThrottle
│   ├── Controllers/          ApiController (temel) · AuthController · SetupController
│   │                         SystemController · ListController · ProductController · TrashController
│   ├── Core/                 AppBuilder · SetupAppBuilder · Config · Connection · Database
│   │                         Clock/SystemClock · Response · Cookie · ClientIp · Dates · Encrypter
│   │                         Ulid · AppVersion · RequestContext · Logger · LogRedactor
│   │                         Migration · Migrator · AsciiQrCode
│   ├── Middleware/           Auth · Csrf · LoginRateLimit · RequestId · SecurityHeaders
│   │                         JsonRequest · SetupGuard · SetupCsrf
│   ├── Setup/                SetupLock · SetupState · RequirementChecker · DatabaseProbe
│   │                         EnvWriter · QrCodeSvg
│   ├── Services/             ActivityLog · MoneyService (K29) · StateMachine · ListPresenter
│   │                         InputValidator · TrashPolicy · StateTransitionException
│   ├── Models/               ListRepository · ProductRepository · SettingsRepository
│   └── Parsers/            ⏳ (backend doğrulama adaptörleri — Faz 3)
├── bin/                      migrate.php · user-create.php · purge-trash.php   (yalnızca CLI)
├── migrations/               0001_create_users … 0012_create_exports (K23: 1 dosya = 1 DDL)
├── public/                   index.php · .htaccess · media/ (görseller)  ⏳ assets/ (React build)
├── setup/views/              wizard.html · wizard.js · wizard.css
│                             (docroot DIŞINDA; rotalardan servis edilir, kurulum sonrası kilitlenir)
├── storage/               ⏳ (webden kapalı: logs/ · exports/ · setup.lock — çalışma anında oluşur)
├── frontend/              ⏳ React kaynak (screens/ · components/ · api/ · store/)
├── extension/             ⏳ manifest.json · background.js · popup/ · parsers/parser_1688.js
└── tests/                    Auth/ · Core/ · Http/ · Support/ · fixtures/
```

## 7. Sunucu Ortamı (16.08.2026 raporuyla DOĞRULANDI)

Adres: **tedarikapp.tilbehometoptan.com** — açık soru 1 kapandı.
Docroot: `/home/<cpanel-kullanıcısı>/<alan-adı>` (cPanel'in alt alan adı için açtığı klasör).

> Bu bölümdeki yollar bilinçli olarak genelleştirilmiştir (K28): depo herkese açık olabileceği için
> sunucuya özel mutlak yollar ve hesap adı yazılmaz. Gerçek değerler yalnızca `.env` ve deploy notlarındadır.

**Yeşil ışık (stack'i doğrulayan bulgular):**
- PHP 8.1.34 + MySQL (pdo_mysql) + SQLite yedek seçenek → Slim 4 uyumlu. (Rapor tarihindeki sürüm; 16.08.2026'da PHP 8.4'e yükseltildi — K21.)
- PhpSpreadsheet gereksinimleri tam: zip, xml, gd, mbstring, iconv ✓ (görsel gömme GD ile çalışır, imagick gerekmez).
- mPDF gereksinimleri tam: mbstring, gd ✓.
- Dış ağ açık: detail.1688.com ve alicdn CDN'e cURL erişimi VAR → görselleri sunucuya indirme planı çalışır. Not: 1688 yanıtı yavaş (7,5 sn) — indirme işlemleri arka planda/kuyrukta yapılmalı, kullanıcıyı bekletmemeli.
- open.er-api.com erişilebilir → Faz 4'teki opsiyonel otomatik kur mümkün.
- Bellek/servis limitleri geniş (5 GB memory_limit, uzun execution time) — export'lar için rahat.

**Deploy kurallarını belirleyen kısıtlar:**
1. `allow_url_fopen` KAPALI → tüm dış istekler yalnızca cURL ile yazılır (`file_get_contents('http...')` asla kullanılmaz).
2. Sunucuda composer YOK → bağımlılıklar lokalde kurulur, `vendor/` klasörü deploy paketiyle birlikte yüklenir (git deploy: vendor repo'ya dahil edilir veya release zip'i ile taşınır).
3. `mail()` KAPALI → e-posta yok; şifre sıfırlama e-postayla YAPILMAZ. Kurtarma: sunucuya konulan tek seferlik CLI/secret script ile şifre resetleme.
4. `exec/system/proc_open` kapalı (`shell_exec` açık ama güvenilmez) → uygulama hiçbir shell komutuna dayanmaz; görsel işleme GD ile PHP içinde.
5. Docroot şu an PHP tarafından YAZILAMAZ durumda → deploy öncesi cPanel'den düzeltilecek: uygulama kökü salt okunur kalabilir ama `storage/` (görseller, geçici export dosyaları, loglar) ve `public/media/` klasörleri yazılabilir yapılmak ZORUNDA. Kurulum sihirbazının gereksinim adımı bu iki klasörü ayrı ayrı denetler ve eksiği isim isim gösterir (İE#5).
6. opcache/apcu yok → önbellek beklenmez; sorgular ve sayfa yükü buna göre hafif tutulur.
7. Zaman dilimi sunucuda UTC → uygulama her yerde `Europe/Istanbul` ayarlar.
8. Cron: yedekleme otomasyonu için cPanel cron kullanılacak (panelden tanımlanır; PHP CLI mevcut ✓ — 8.4'e yükseltildi, K21).
