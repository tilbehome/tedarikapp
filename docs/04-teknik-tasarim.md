# tedarikapp — Teknik Tasarım

> Durum: v1.0 — ONAYLANDI (16.08.2026)
> Stack ve mimari kararlar 16.08.2026'da onaylanmıştır (bkz. 08 no'lu belge, K5).

## 1. Stack Önerisi

| Katman | Seçim | Gerekçe |
|---|---|---|
| Backend | PHP 8.1 (Slim 4 mikro-framework) + REST API | Sunucuda doğrulanan sürüm 8.1.34; ek kurulum gerektirmeden çalışır; ekosistemin (WP eklentileri, MCP gateway) zaten PHP |
| Veritabanı | MySQL / MariaDB | Hosting'de hazır |
| Frontend (panel) | React 18 + Vite, mobile-first | Hızlı, uygulama hissi; build çıktısı hosting'e statik yüklenir |
| Paylaşım sayfası | Sunucu tarafında render edilen hafif sayfa (PHP + şablon) | Firma tarafı için JS yükü olmadan hızlı açılır, link önizlemeleri (WhatsApp/WeChat) düzgün çıkar |
| Excel | PhpSpreadsheet | Hücreye gömülü görsel destekler — örnek formatla birebir çıktı |
| PDF | mPDF | Görselli, Türkçe karakter sorunsuz |
| Eklenti | Chrome Manifest V3 (vanilla JS) | Content script 1688 DOM'unu okur, API'ye POST eder |

Barındırma: `tedarik.` alt alan adı (hangi domain altında olacağı kararlaştırılacak), SSL zorunlu.

## 2. Veritabanı Şeması (özet)

```
users            id, email, password_hash, created_at
settings         key, value                  -- yuan_tl, usd_tl, extension_token...
rate_history     id, currency, rate, set_at  -- kur tarihçesi
categories       id, name, sort
lists            id, name, supplier_name, status, note,
                 visibility,                  -- aktif | pasif | arsiv
                 yuan_rate, usd_rate,         -- listeye kilitlenen kur
                 share_token,                 -- paylaşım linki
                 created_at, archived_at, deleted_at  -- soft delete (çöp kutusu)
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
                 status, note, created_at, deleted_at  -- soft delete
product_images   id, product_id, path, sort  -- ek görseller
inbox_items      id, raw_json, created_at    -- eklentiden gelen ham veri
exports          id, list_id, format, created_at     -- export geçmişi ("güncel değil" rozeti: lists.updated_at > son export)
activity_log     id, entity_type, entity_id, action, detail, created_at  -- durum değişimi + ekleme/silme/düzenleme tarihçesi
```

Para tipleri: tüm fiyat/kur alanları `DECIMAL` (fiyat 12,2 — kur 12,4); float KULLANILMAZ, hesaplar PHP bcmath ile yapılır (K14).

İndeksler: products(list_id), products(status), products(external_id), activity_log(entity_type, entity_id), lists(visibility). external_id üzerinden tekrar-ekleme uyarısı yapılır (benzersizlik zorlanmaz — aynı ürün bilerek iki listede olabilir).

TL fiyatları veritabanında saklanmaz; listenin kilitli kuru × orijinal fiyat olarak her yerde hesaplanır (tutarsızlık riski sıfır).

## 2b. Durum Makinesi (backend'de zorlanır)

```
Ürün : Verilecek → Verildi → Yolda → Geldi
       her durumdan → İptal (Geldi hariç)
       düzeltme: yalnızca bir adım geri alınabilir; durum atlama YASAK
Liste: Taslak → İletildi → Sipariş Verildi → Tamamlandı (+İptal)
       Tamamlandı ⇐ ancak tüm ürünler Geldi veya İptal ise
```

Geçersiz geçiş isteğini API reddeder (HTTP 422 + açıklama); kural yalnızca arayüzde değil sunucuda yaşar.

## 2c. Veri Sözleşmesi — Eklenti → `POST /api/capture` (SABİT ŞEMA, K14)

```json
{
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

Kurallar: para alanları STRING taşınır (float hassasiyet kaybına karşı); `target_list_id: null` → Gelen Kutusu; alan adları değiştirilemez, şema değişikliği PM kararı + belge güncellemesi gerektirir. Backend her alanı doğrular (tip, uzunluk, URL deseni), doğrulanamayan istek ham haliyle `inbox_items.raw_json`'a düşer ve panelde "hatalı yakalama" olarak gösterilir.

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

1. Alt alan adı hangisi olsun? (`tedarik.tilbehome.com` gibi)
2. Hosting'de PHP sürümü ve cron erişimi teyit edilecek.
3. 1688 video URL'leri bazı durumlarda oturum istiyor — Faz 3'te parser yazılırken gerçek sayfalarla test edilip gerekirse video dosyası da sunucuya indirilecek. Karar testten sonra.

## 7. Sunucu Ortamı (16.08.2026 raporuyla DOĞRULANDI)

Adres: **tedarikapp.tilbehometoptan.com** — açık soru 1 kapandı.
Docroot: `/home/tilbehometoptan/tedarikapp.tilbehometoptan.com`

**Yeşil ışık (stack'i doğrulayan bulgular):**
- PHP 8.1.34 + MySQL (pdo_mysql) + SQLite yedek seçenek → Slim 4 uyumlu.
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
5. Docroot şu an PHP tarafından YAZILAMAZ durumda → deploy öncesi cPanel'den düzeltilecek: uygulama kökü salt okunur kalabilir ama `storage/` (görseller, geçici export dosyaları, loglar) klasörü yazılabilir yapılmak ZORUNDA. Faz 1 kurulum kontrol listesine eklendi.
6. opcache/apcu yok → önbellek beklenmez; sorgular ve sayfa yükü buna göre hafif tutulur.
7. Zaman dilimi sunucuda UTC → uygulama her yerde `Europe/Istanbul` ayarlar.
8. Cron: yedekleme otomasyonu için cPanel cron kullanılacak (panelden tanımlanır; PHP CLI 8.1 mevcut ✓).
