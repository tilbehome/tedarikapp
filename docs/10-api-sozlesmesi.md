# tedarikapp — API Sözleşmesi (Panel REST API)

> Durum: v1.0 — SABİT SÖZLEŞME (K18, 16.08.2026)
> Frontend (React) ve backend (Slim 4) bu belgeye göre ayrı ayrı geliştirilir. Alan adı/format değişikliği PM kararı + bu belgenin güncellenmesini gerektirir (CLAUDE.md §6). Eklenti yakalama şeması ayrıca sabittir: docs/04 §2c.

## 1. Genel Kurallar

- **Taban:** `/api` altında, yalnızca JSON (UTF-8). `Content-Type: application/json` olmayan yazma istekleri 415 ile reddedilir.
- **Para alanları STRING taşınır** (`"9.00"`, `"63.36"`) — JSON float ASLA kullanılmaz (K14). TL değerleri API'de hesaplanmış alan olarak döner, DB'de saklanmaz.
- **Tarihler:** ISO 8601, Europe/Istanbul ofsetiyle (`2026-08-16T15:30:00+03:00`).
- **Kimlik doğrulama:** panel uçları = oturum çerezi + `X-CSRF-Token` başlığı (GET hariç tüm metodlarda zorunlu); `/api/capture` = `Authorization: Bearer <extension_token>` (yalnızca bu uca yetkili).
- **Yanıt zarfı (her uçta aynı):**

```json
{ "success": true,  "data": { }, "error": null,  "meta": { } }
{ "success": false, "data": null, "error": { "code": "VALIDATION", "message": "Doğrulama hatası", "fields": { "qty": "1–1.000.000 arası tam sayı olmalı" } }, "meta": { } }
```

- **HTTP durum kodları:** 200 (ok) · 201 (oluşturuldu) · 204 (silindi, gövde yok) · 400 (bozuk istek) · 401 (oturum yok/2FA bekliyor) · 403 (CSRF/yetki/kilit/HTTPS kapısı) · 404 · 405 (metot desteklenmiyor) · 409 (yalnızca iki yerde: tekrar-ekleme onayı `DUPLICATE_WARNING` ve "önce listeyi geri al" bağımlılığı — İE#9 düzeltmesi) · 415 · 422 (doğrulama/geçersiz durum geçişi/dokunulmaz liste — **standart budur**) · 429 (hız sınırı).
- **Hata kodları (`error.code`):** `VALIDATION`, `UNAUTHENTICATED`, `TOTP_REQUIRED`, `FORBIDDEN`, `CSRF`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `UNSUPPORTED_MEDIA_TYPE`, `STATE_TRANSITION`, `LIST_IMMUTABLE` (K37 §B4 — terminal listeye mutasyon), `HTTPS_REQUIRED` (K37 §A3 — kurulumda sır adımı HTTPS değil), `DUPLICATE_WARNING`, `RATE_LIMITED`, `LOCKED`, `PAYLOAD_TOO_LARGE`, `SERVER_ERROR`. Mesajlar Türkçe ve kullanıcıya gösterilebilir; teknik detay yalnızca loga yazılır.
- **415 uygulaması (İE#5):** kural GÖVDELİ yazma isteklerine uygulanır — gövdesiz bir `POST`/`DELETE` içerik tipi bildirmek zorunda değildir. İhlalde `415` + `UNSUPPORTED_MEDIA_TYPE`; istek gövdesi hiç ayrıştırılmaz.
- **405 (K25):** desteklenmeyen metot gerçek **HTTP 405** döner; `error.code = METHOD_NOT_ALLOWED` ve yanıtta izin verilen metodları listeleyen `Allow` başlığı bulunur. (İE#3'teki 422 `VALIDATION` eşlemesi kaldırılmıştır.)
- **Makine değerleri İngilizcedir (K22):** durum/görünürlük alanları API'de ve DB'de sabit İngilizce kodlar taşır (`draft`, `sent`, `to_order`, `active` …). Türkçe karşılıklar yalnızca arayüz etiketidir — çeviri tablosu docs/09 §6.
- **Her yanıtta `X-Request-Id`** başlığı döner (K27); hata bildirirken bu değer istenir, loglarla eşleştirilir.
- **500 davranışı (K42):** kurulum kilidi YOKKEN (sistemde üretim sırrı yok) beklenmeyen hata yanıtı `meta.diagnostics` (ortam + redaksiyonlu hata detayı) taşır; kilit VARKEN yanıt geneldir ve `meta.request_id` + mesajda destek kodu döner, tam detay `app_logs`a yazılır.
- **Sayfalama (Faz 4 notu — İE#9 F13):** Faz 1'de liste/ürün uçları sayfalama YAPMAZ (tek kullanıcılı sistemde veri hacmi düşük); yalnızca `GET /api/activity` sayfalıdır (`page`, `per_page` — varsayılan 25, üst sınır 100, yanıtta `meta: {page, per_page, total}`). Genel sayfalama Faz 4'te değerlendirilecektir.
- **Sıralama/filtre:** uca özel filtreler (aşağıda). Sıralama sabittir (listeler `created_at DESC`, ürünler `sort_no`); `?sort=` parametresi Faz 1'de yoktur. Bilinmeyen sorgu parametreleri yok sayılır; tanımlı bir filtrenin geçersiz DEĞERİ 422 döner.


> **MIGRATION_PENDING (İE#10.5):** defterde bekleyen migration varken veri uçları
> (liste/ürün/çöp/export/paylaşım-yönetimi grubu) **503** `MIGRATION_PENDING` döner;
> panel tam sayfa "Güncelleme tamamlanmalı" ekranına geçer ve migrate+baseline'ı buradan
> koşar. `/api/system/*`, `/api/auth/*`, `/api/health` ve kurulum uçları bu korumanın
> DIŞINDADIR. Defter okunamıyorsa (taze kurulum) koruma isteği geçirir.

## 2. Kimlik Doğrulama

| Uç | Gövde → Yanıt |
|---|---|
| `POST /api/auth/login` | `{email, password}` → 200 `{stage:"totp"}` (şifre doğru, TOTP bekleniyor) · 401 · 403 `LOCKED` (backoff/kilit; `meta.retry_after_seconds` döner) |
| `POST /api/auth/totp` | `{code}` → 200 `{user}` + oturum çerezi · 422 (yanlış kod) |
| `POST /api/auth/recovery` | `{code}` → 200 `{user, remaining_codes}` — kod TEK kullanımlık, düşen sayı döner |
| `POST /api/auth/logout` | → 204 (oturum + varsa remember token iptal) |
| `GET /api/auth/me` | → 200 `{user, csrf_token}` — SPA açılışında oturum/CSRF tazeleme |

`login` isteğinde `remember: true` gönderilirse TOTP sonrası ayrı "beni hatırla" çerezi kurulur; `GET /api/auth/sessions` + `DELETE /api/auth/sessions/{id}` ile iptal edilir.

## 3. Listeler

**Liste nesnesi (yanıtlarda):**

```json
{ "id": 3, "name": "Eylül 2026 DDP Sipariş", "period": "EYLÜL 2026",
  "supplier_name": "…", "note": "…", "status": "draft",
  "visibility": "active", "yuan_rate": "7.0400", "usd_rate": "41.5000",
  "rate_locked_at": null, "revision": 17,
  "share_token_prefix": null, "share_expires_at": null, "product_count": 24,
  "progress": { "to_order": 6, "ordered": 0, "in_transit": 10, "received": 8, "cancelled": 0 },
  "totals": { "qty": 480, "yuan": "4320.00", "yuan_tl": "30412.80", "ddp_usd": "0.00", "ddp_tl": "0.00" },
  "last_export": { "format": "xlsx", "created_at": "…", "list_revision": 12 }, "is_export_stale": true,
  "created_at": "…", "updated_at": "…", "archived_at": null, "deleted_at": null }
```

- **`revision` (K25):** ürün ekleme/silme, fiyat, adet ve sıra değişiminde +1 artar. **`is_export_stale` = `revision != last_export.list_revision`** — `updated_at` karşılaştırması kaldırıldı (yalnızca not düzenlemek çıktıyı eskitmez).
- **Kur kilidi (K4, tek ifade):** Taslak (`draft`) liste güncel kuru izler; durum `sent` olduğunda kur o anki değerle **kilitlenir** ve `rate_locked_at` yazılır. Kilitlendikten sonra kur değişmez.
- **Terminal liste dokunulmazlığı (K37 §B4):** `completed`/`cancelled` liste DONMUŞTUR. Ürün ekleme/taşıma/silme, durum ve alan düzenleme, yeniden sıralama, çöp kutusundan bu listeye geri alma → **422 `LIST_IMMUTABLE`**. Yeniden açma ucu YOKTUR (`completed` durumundan çıkış yok); çözüm `duplicate` ile kopyalamaktır. İstisna: `visibility` (arşivleme) ve listenin kendisinin çöp kutusuna taşınması serbesttir (yaşam döngüsü, içerik değil).
- **Paylaşım token'ı (K25):** tam token yalnızca üretildiği yanıtta bir kez döner; DB'de `share_token_hash` (SHA-256) saklanır. Liste nesnesinde yalnızca `share_token_prefix` (linkin tanınması için ilk haneler) görünür.

| Uç | Açıklama |
|---|---|
| `GET /api/lists` | Filtre: `visibility=active\|passive\|archived`, `status`, `q` (ad/tedarikçi içinde arama) |
| `POST /api/lists` | `{name, period, supplier_name?, note?}` → 201. Kurlar o anki ayarlardan atanır; `draft`'ta güncel kuru izler, **`sent` olduğunda kilitlenir** (K4) |
| `GET /api/lists/{id}` | Tek liste (ürünler ayrı uçtan) |
| `PATCH /api/lists/{id}` | Kısmi güncelleme: `{name?, period?, supplier_name?, note?, visibility?, status?}` — geçersiz durum geçişi → 422 `STATE_TRANSITION`; terminal listede `visibility` dışındaki her alan → 422 `LIST_IMMUTABLE` (K37 §B4) |
| `DELETE /api/lists/{id}` | Çöp kutusuna taşır (30 gün) → 204 |
| `POST /api/lists/{id}/duplicate` | → 201 yeni liste (`draft`, günün kuru, ürünler `to_order`, export geçmişi taşınmaz) |
| `POST /api/lists/{id}/share` | K51 — → 200 `{share_url, share_token_prefix, share_expires_at}`; tam token YALNIZCA bu yanıtta bir kez görünür (DB'de SHA-256). Tekrar çağrı = YENİLEME (eski link anında ölür); `{expires_at}` opsiyonel (gelecek tarih, 422 denetimli) |
| `DELETE /api/lists/{id}/share` | Linki iptal eder → 204 (paylaşım sayfası 404 döner) |
| `GET /api/lists/{id}/export?format=xlsx\|pdf\|csv` | K50 — dosya BELLEKTE üretilir ve akışla döner (`Content-Disposition: attachment`; diske yazılmaz). Kayıt gerçek anlık görüntü tutar (K25): `snapshot_json`, `sha256`, `file_size`, `status`, `list_revision`. Geçersiz biçim 422 |
| `GET /api/lists/{id}/exports` | K50 — export geçmişi → 200 `[{id, format, file_size, list_revision, created_at}]` (snapshot gövdesiz) |
| `GET /api/exports/{id}/file` | K50 — geçmiş kaydını SAKLANAN snapshot'tan yeniden üretip akıtır; liste sonradan değişmiş olsa bile içerik AYNIDIR (yeni hali istemek = yeni export) |

## 4. Ürünler

**Ürün nesnesi:** şemadaki alanlar (docs/04 §2) + hesaplanan `price_yuan_tl`, `price_ddp_tl`, `images: [{id, url, sort}]`. `sku_matrix`/`sku_selection` JSON olarak aynen taşınır.

| Uç | Açıklama |
|---|---|
| `GET /api/lists/{id}/products` | Filtre: `status`, `category_id`, `q`; sıralama varsayılanı `sort_no` |
| `POST /api/lists/{id}/products` | Elle ekleme. Zorunlu: `{name, qty, price_yuan}`; opsiyonel diğer alanlar. Görsel/video URL verilirse sunucu arka planda indirir (beyaz liste — docs/04 §2d). Aynı **`platform` + `external_id`** çifti sistemde varsa → 409 `DUPLICATE_WARNING` + `meta.existing` (hangi listede); `{force:true}` ile tekrar gönderilirse eklenir (K25) |
| `PATCH /api/products/{id}` | Kısmi güncelleme (alan kuralları docs/04 §2d) |
| `PATCH /api/products/{id}/status` | `{status}` — durum makinesine aykırıysa 422 `STATE_TRANSITION` + izinli geçişler `meta.allowed` içinde |
| `DELETE /api/products/{id}` | Çöp kutusuna → 204 |
| `PATCH /api/products/bulk` | `{ids: [...], action: "status"\|"move"\|"delete", status?, target_list_id?}` → 200 `{updated, failed: [{id, error}]}` — kısmi başarı desteklenir; tüm işlem TEK transaction'dır (K37 §B5). Terminal listedeki ürün `failed`'a düşer; terminal HEDEFE taşıma → 422 `LIST_IMMUTABLE` |
| `PATCH /api/lists/{id}/products/reorder` | `{ordered_ids: [...]}` → sıra numaraları yeniden yazılır. Dizi, listedeki ürünlerin **TAM permütasyonu** olmalı: eksik/fazla/yinelenen kimlik → 422 (K37 §B6) |

## 5. Gelen Kutusu

| Uç | Açıklama |
|---|---|
| `GET /api/inbox` | Filtre: `status=pending\|error`; her öğe ham yakalama + önizleme alanları |
| `POST /api/inbox/{id}/assign` | `{list_id, category_id, qty, name?, detail?, sku_selection?}` → 201 ürün oluşur, öğe `assigned` olur |
| `DELETE /api/inbox/{id}` | Öğeyi atmadan siler → 204 |

## 6. Çöp Kutusu

| Uç | Açıklama |
|---|---|
| `GET /api/trash` | Silinen liste + ürünler, kalan gün bilgisiyle |
| `POST /api/trash/{type}/{id}/restore` | `type: lists\|products` → 200 (listesi de silinmiş bir ürün geri alınırken önce listesi geri alınmalı → 409; listesi terminal (`completed`/`cancelled`) ise → 422 `LIST_IMMUTABLE` — K37 §B4) |
| `DELETE /api/trash/{type}/{id}` | Kalıcı siler → 204. Fiziksel medya dosyaları da temizlenir (K37 §C7): DB silme tek transaction'da, dosya silme commit SONRASI; kopyalanmış listelerin paylaştığı dosya son referans gidince silinir |

## 7. Ayarlar, Kategoriler, Kur

| Uç | Açıklama |
|---|---|
| `GET /api/settings` | `{yuan_tl, usd_tl, totp_enabled, extension_token_preview}` (token'ın yalnızca son 4 hanesi) |
| `PUT /api/settings/rates` | `{yuan_tl?, usd_tl?}` → 200 `{yuan_tl, usd_tl, changes:[{currency, from, to}]}`. İE#9.8 3b (K48 ek): yalnız DEĞİŞEN değer ayara + rate_history'ye yazılır; gönderilen değer kayıtlıyla aynıysa tarihçeye satır YAZILMAZ ve `changes` boş döner (panel "zaten güncel" der). Yalnızca `draft` listelerin görünen TL'sini etkiler (kur `sent` ile kilitlenir — K4/K48) |
| `GET /api/settings/rates/history` | Kur tarihçesi (sayfalı) |
| `POST /api/settings/extension-token` | Yeni token üretir → **tam token yalnızca bu yanıtta bir kez** görünür; DB'de SHA-256 hash (K34); tek kullanıcı çok cihaz — iptal hepsini düşürür |
| `DELETE /api/settings/extension-token` | Token'ı iptal eder → 204; eklenti istekleri anında 401 alır |
| `GET /api/inbox` | İE#11 — Gelen Kutusu: 200 `[{id, status(pending\|error), name, price_yuan, image_url, url, platform, external_id, created_at}]` |
| `POST /api/inbox/assign` | İE#11 — `{ids:[], list_id}` → seçilenleri listeye ürün olarak taşır (K25 mükerrer uyarısı ürün oluşturmada uygulanmaz — kullanıcı bilinçli taşıyor); 200 `{moved, failed:[{id, error}]}` |
| `DELETE /api/inbox/{id}` | İE#11 — kaydı siler (çöp kutusuna girmez; ham yakalama verisidir) → 204 |
| `GET/POST/PATCH/DELETE /api/categories` | CRUD; kullanımda olan kategori silinirken → 422 `VALIDATION` + `meta.product_count` (İE#9 düzeltmesi: 422 standardı — 409 yalnızca tekrar-ekleme ve geri alma bağımlılığında) |
| `GET /api/activity` | Filtre: `entity_type`, `entity_id`, sayfalı (`page`, `per_page` — varsayılan 25, üst sınır 100) — E9 ekranının kaynağı. Yanıt `data: [{id, entity_type, entity_id, action, detail, ip, actor_type, created_at}]`, `meta: {page, per_page, total}`. Salt okunur |

## 8. Yakalama ve Dışa Açık Sayfa

- `POST /api/capture` — istek şeması **docs/04 §2c v2'de sabit** (İE#11/K32: source+raw+normalized üç blok). Yanıt: 201 `{inbox_id}` veya `{product_id}` (hedef liste seçiliyse) + varsa `duplicate:{product_id, list_id, list_name}` (K25 uyarısı — engel değil); doğrulanamayan gövde → 201 `{inbox_id, status:"error"}` (raw saklanır, veri kaybolmaz); hız aşımı → 429. CORS: yalnız allowlist'teki extension origin'i (K30, wildcard YOK).
- `GET /api/extension/selectors?platform=1688` — Bearer'lı; schema_version'lı seçici JSON'ı (K53: seçiciler KOD DEĞİL VERİ — site değişince eklenti güncellemesiz düzeltme).
  - **Zorunlu `capture_id` (UUIDv4, K25):** sistemde UNIQUE'tir. Aynı `capture_id` tekrar gelirse yeni kayıt AÇILMAZ, ilk isteğin sonucu döner (idempotans) — eklentinin kuyruk tekrar denemeleri çift ürün oluşturamaz.
  - Gövdede ayrıca `schema_version`, `extension_version`, `parser_version` ve `platform` zorunludur; parser bozulduğunda hangi sürümün ürettiği kayıttan anlaşılır.
- `GET /p/{share_token}` — API değil, sunucu render sayfa (docs/09 P1, K51). Token SHA-256'lanıp aranır; biçimsiz/bilinmeyen/iptal/süresi dolmuş token ve hız sınırı aşımı AYNI sabit 404'ü döndürür (ayrım sızmaz). Enumeration: IP başına 10 dk'da 30 geçersiz deneme → blok (sayaç activity_log'da, token loglanmaz). `noindex` + robots `/p/` kapsamı + CSP (stil `/p-style.css`). Sayfa CANLI listeyi gösterir — export snapshot'ının aksine (fark K50/K51'de belgeli). İptal edilen ürünler gösterilmez.
- `GET /media/{name}` — İE#10 5c YEDEK HAT: /media normalde Apache statik sunar (.htaccess [END]); rewrite şaşarsa uygulama aynı adresi sunucu-üretimi ad deseni doğrulamasıyla akıtır (desen dışı/dosyasız → sade 404, SPA yönlendirmesi YOK).

## 8b. Kurulum ve Sistem (İE#5 — PM onaylı ek)

**Kurulum sihirbazı** (`/api/setup/*`) kimlik doğrulaması İSTEMEZ ama kendi oturumu ve kendi CSRF token'ı vardır (`X-Setup-Token`). Kurulum bittiğinde kilit **veritabanına** yazılır (`settings.system.setup_lock` — K33; K33 öncesi `storage/setup.lock` dosyası salt-okunur uyumluluk için tanınır); ondan sonra **tüm** setup uçları `403 FORBIDDEN` döner ve deneme loglanır.

**K37 kapı katmanları (İE#9 §A):**
1. **Fail-closed kilit:** DB yapılandırılmışken kilit OKUNAMIYORSA (bağlantı düştü/tablo yok) sihirbaz kilitli sayılır → 403. Tek istisna `.env`i bu oturumda üretmiş devam eden kurulumdur.
2. **`.env` katmanı:** `.env` diskte varsa ve oturum onu üretmemişse tüm setup uçları → 403; HTTP akışı mevcut `.env`in üzerine ASLA yazamaz (yeniden üretim de 422). Yeniden kurulum dosyanın elle silinmesini gerektirir.
3. **HTTPS kapısı:** `APP_ENV=production` iken sır taşıyan adımlar (`database`, `admin`, `admin/verify`) HTTPS değilse → 403 `HTTPS_REQUIRED` (loopback istisna). Sihirbaz oturum çerezi HTTPS'te `Secure` işaretlenir.

| Uç | Açıklama |
|---|---|
| `GET /api/setup/state` | `{step, steps[], csrf_token, env_exists}` — sihirbaz açılışı. Yapılandırma (config.php/.env) varsa ilk üç adım otomatik geçilir (K45) |
| `POST /api/setup/unlock` | K46 — kilit kaldırma, SAHİPLİK KANITLI: `{app_key}` (config.php'deki 64 haneli değer, hash_equals). Doğru → 200 `{unlocked:true}`; kanıtsız/yanlış → 403 `FORBIDDEN` (+`fields.app_key`); IP bazlı artan bekleme → 429 `RATE_LIMITED` + `meta.retry_after_seconds`. Her deneme activity_log'a yazılır (`setup_unlock`/`setup_unlock_failed`); APP_KEY asla loglanmaz. Kilitliyken erişilebilen TEK yazma ucudur (guard istisnası) |
| `GET /api/setup/diagnostics` | K42 — ortam özeti: `{app_version, php_version, sapi, os, extensions{ad: VAR\|YOK}, mysql_version, timestamp}`. "Tanılama raporunu kopyala" düğmesinin kaynağı; SIR İÇERMEZ. Adım hatalarında aynı biçim + `failure{step, exception, message, location, trace[]}` yanıtın `meta.diagnostics` alanında döner |
| `GET /api/setup/requirements` | `{ok, php, extensions[], writable[], https, warnings[]}`. ZORUNLU (ok'u düşürür): PHP ≥ 8.1 ve zorunlu eklentiler; HTTPS hiçbir modda bloklamaz (K45) — yalnız uyarı. Yazılabilirlik (`storage/`, `public/media/`) ZORUNLU DEĞİLDİR (K37 §D10): yazılamıyorsa hotlink + DB-log moduyla devam edilir, uyarı kartı gösterilir. Eksikler isim isim ve çözüm önerisiyle |
| `POST /api/setup/database` | `{host, port?, name, user, pass}` → 200 `{version, charset}`; `SELECT VERSION()` sonucu döner, utf8mb4 değilse 422 |
| `POST /api/setup/env` | `{app_url?}` → `.env` üretir (APP_KEY ve EXTENSION_TOKEN_SALT kriptografik, dosya izni 0600); `.env` zaten varsa 422 (K37 §A2 — üzerine yazılmaz). Yazılamaz kökte `{manual: true, content}` döner (K33) |
| `POST /api/setup/env/verify` | K33 manuel akışı: elle kaydedilen `.env` APP_KEY eşleşmesiyle doğrulanır → `{verified}`; dosya yok/yanlış içerik → 422 (İE#9.3 a — belgeye işlendi) |
| `POST /api/setup/migrate` | Bekleyenleri koşar → `{applied[], migrations[{name, execution_ms}]}` |
| `POST /api/setup/admin` | `{email, password}` → `{otpauth_uri, qr_svg, manual_key}`. Kullanıcı HENÜZ oluşturulmaz |
| `POST /api/setup/admin/verify` | `{code}` → kullanıcı oluşur; `{user, recovery_codes[]}` — **kodlar yalnızca bu yanıtta bir kez** |
| `POST /api/setup/finish` | `{codes_saved: true}` zorunlu → kilidi yazar, özet döner |

Adım sırası zorlanır: sırası gelmemiş uç `422 STATE_TRANSITION` + `meta.current_step` döner.

**Sistem uçları** (panel oturumu gerektirir — güncelleme yolu):

| Uç | Açıklama |
|---|---|
| `GET /api/health` | KİMLİKSİZ — `{app, time}`; DB'ye ulaşılamıyorsa 500 zarfı (İE#9.3 a — belgeye işlendi) |
| `GET /api/system/integrity` | KİMLİKSİZ (K43) — MANIFEST.txt'e göre `{manifest_exists, ok, total, checked, missing[], missing_count, modified[], modified_count, message}`. Kurulumdan ÖNCE de çalışır (setup uygulaması da sunar); sihirbazın gereksinim adımı gösterir. Sır içermez — yalnız göreli yollar |
| `GET /api/system/status` | `{app_version, php_version, db_version, installed_at, migrations:{applied, pending[], pending_count}, media:{mode, writable}, setup_lock_in_database}` |
| `GET /api/system/state-machine` | (İE#8) `{product:{durum: [izinli...]}, list:{...}}` — docs/04 §2b geçiş matrisinin okunur kopyası. Panel durum menüsünü buradan kurar; **kural yine backend'de zorlanır**, bu uç yalnızca geçersiz seçeneğin kullanıcıya sunulmamasını sağlar |
| `POST /api/system/migrate` | Auth + CSRF. Bekleyen migration'ları koşar → `{applied[], applied_count}`; sonuç `activity_log`'a yazılır |
| `POST /api/system/setup-unlock` | K46 — kilit kaldırmanın ADMİN OTURUMU yolu (Auth + CSRF). → 200 `{unlocked:true}`; activity_log'a `setup_unlock (admin:<e-posta>)` yazılır |
| `POST /api/system/media-migrate` | K47 — uzak görselleri arşive taşıma (Auth + CSRF). Gövde (İE#10 5b): `{exclude_products?:[], exclude_images?:[]}` — önceki turların başarısızları dışlanır, parti başı tıkanmaz. Tek çağrı BİR parti işler (≤20 kayıt) → 200 `{mode, scanned, migrated, failed:[{kind, id, product_id, url, error}], remaining}`; panel `remaining` sıfırlanana dek tekrar çağırır. Medya yazılamıyorsa 422 `MEDIA_NOT_WRITABLE`. İdempotent; başarısız kayıt bozulmaz. Sonuç `activity_log`'a yazılır |
| `POST /api/system/media-check` | İE#10 5d — medya bütünlük denetimi + onarım (Auth + CSRF). Yerel /media kayıtlarını diskle karşılaştırır; kayıpları `main_image_source`/`source_url`'den yeniden indirir (parti ≤20) → 200 `{mode, checked, missing, repaired, failed[]}`. İdempotent; kaynaksız kayıt bozulmaz, raporlanır. Export ve paylaşım görselleri aynı kayıtlardan okunduğu için denetim o yüzeyleri de kapsar |
| `POST /api/products/{id}/media-repair` | İE#10 5d — tek ürün görsel onarımı (panel "yeniden dene"): uzaksa arşive alır, yerel+kayıpsa kaynaktan indirir → 200 `{repaired, main_image}`; onarılamazsa 422 `MEDIA_REPAIR_FAILED` |
| `POST /api/system/backup` | İE#10.5 — elle yedek (Auth+CSRF): şifreli DB dökümü üretir (AES-256-GCM; anahtar APP_KEY'den HKDF ile türetilir) → 200 `{backup:{name,size,sha256,created_at}, offsite:{attempted,sent,via,error}}`. Off-site hedef dosya yapılandırmasından okunur (BACKUP_FTP_*/BACKUP_SMTP_* — sır, DB'ye yazılmaz); yapılandırılmamışsa gönderim atlanır. Üretim sonrası SAKLAMA koşar: BACKUP_RETENTION_DAYS'ten eskiler silinir (en yeni 5 korunur) → yanıtta `pruned`. Üretilemezse 500 `BACKUP_FAILED` |
| `GET /api/system/backups` | İE#10.5 — → 200 `{backups:[{name,size,created_at}], writable, last_age_seconds, stale, offsite_configured}`; `stale`=son yedek >24 saat (panel rozeti) |
| `GET /api/system/backups/{name}/file` | İE#10.5 — şifreli yedeği indirir (Auth'lu; ad deseni `yedek-*.sql.enc` doğrulanır, desen dışı 404) |
| `POST /api/system/migrate-baseline` | K49 — migration defterini gerçeğe eşitler (Auth + CSRF; APP_KEY kanıtı GEREKMEZ — yıkıcı değil). Bekleyen her kayıt için hedef nesne şema sorgusuyla doğrulanır: VARSA kayıt KOŞULMADAN checksum'uyla deftere işlenir, YOKSA/haritada değilse atlanır → 200 `{recorded[], skipped:[{name, reason}], pending_count}`. HİÇBİR DDL çalıştırmaz; idempotent. Sonuç `activity_log`'a yazılır. CLI eşi: `bin/migrate-baseline.php` |

## 9. Sözleşme Testleri

Faz 1'den itibaren her uç için en az bir PHPUnit sözleşme testi yazılır: örnek istek → beklenen zarf/alanlar/durum kodu. Frontend, `frontend/src/api/` katmanında bu belgeyle birebir aynı tipleri kullanır; sapma kod incelemesinde reddedilir (docs/00 §6).
