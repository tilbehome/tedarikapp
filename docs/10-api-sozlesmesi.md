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

- **HTTP durum kodları:** 200 (ok) · 201 (oluşturuldu) · 204 (silindi, gövde yok) · 400 (bozuk istek) · 401 (oturum yok/2FA bekliyor) · 403 (CSRF/yetki/kilit) · 404 · 409 (çakışma; örn. tekrar-ekleme onayı bekliyor) · 415 · 422 (doğrulama/geçersiz durum geçişi) · 429 (hız sınırı).
- **Hata kodları (`error.code`):** `VALIDATION`, `UNAUTHENTICATED`, `TOTP_REQUIRED`, `FORBIDDEN`, `CSRF`, `NOT_FOUND`, `STATE_TRANSITION`, `DUPLICATE_WARNING`, `RATE_LIMITED`, `LOCKED`, `PAYLOAD_TOO_LARGE`, `SERVER_ERROR`. Mesajlar Türkçe ve kullanıcıya gösterilebilir; teknik detay yalnızca loga yazılır.
- **Sayfalama:** `?page=1&per_page=25` (üst sınır 100). Yanıtta `meta: { "page": 1, "per_page": 25, "total": 132 }`.
- **Sıralama/filtre:** `?sort=created_at&order=desc` + uca özel filtreler (aşağıda). Bilinmeyen parametre sessizce yok sayılmaz, 400 döner (yazım hatasını erken yakalamak için).
- **405 (metot desteklenmiyor)** sözleşmede ayrı kod tanımlamaz; 422 `VALIDATION` zarfıyla döner (İE#3 uygulaması, PM onaylı).

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
  "supplier_name": "…", "note": "…", "status": "Taslak",
  "visibility": "aktif", "yuan_rate": "7.0400", "usd_rate": "41.5000",
  "share_token": null, "product_count": 24,
  "progress": { "Verilecek": 6, "Verildi": 0, "Yolda": 10, "Geldi": 8, "İptal": 0 },
  "totals": { "qty": 480, "yuan": "4320.00", "yuan_tl": "30412.80", "ddp_usd": "0.00", "ddp_tl": "0.00" },
  "last_export": { "format": "xlsx", "created_at": "…" }, "is_export_stale": true,
  "created_at": "…", "updated_at": "…", "archived_at": null, "deleted_at": null }
```

| Uç | Açıklama |
|---|---|
| `GET /api/lists` | Filtre: `visibility=aktif\|pasif\|arsiv`, `status`, `q` (ad/tedarikçi içinde arama) |
| `POST /api/lists` | `{name, period, supplier_name?, note?}` → 201. Kurlar o anki ayarlardan atanır; Taslak'ta güncel kuru izler, **İletildi olduğunda kilitlenir** (K4) |
| `GET /api/lists/{id}` | Tek liste (ürünler ayrı uçtan) |
| `PATCH /api/lists/{id}` | Kısmi güncelleme: `{name?, period?, supplier_name?, note?, visibility?, status?}` — geçersiz durum geçişi → 422 `STATE_TRANSITION` |
| `DELETE /api/lists/{id}` | Çöp kutusuna taşır (30 gün) → 204 |
| `POST /api/lists/{id}/duplicate` | → 201 yeni liste (Taslak, günün kuru, ürünler `Verilecek`, export geçmişi taşınmaz) |
| `POST /api/lists/{id}/share` | → 200 `{share_url}` (token yoksa üretir; `{renew:true}` ile yenilenir — eski link ölür) |
| `DELETE /api/lists/{id}/share` | Linki iptal eder → 204 (paylaşım sayfası 404 döner) |
| `GET /api/lists/{id}/export?format=xlsx\|pdf\|csv` | Dosya döner (`Content-Disposition: attachment`); export geçmişine kaydolur |

## 4. Ürünler

**Ürün nesnesi:** şemadaki alanlar (docs/04 §2) + hesaplanan `price_yuan_tl`, `price_ddp_tl`, `images: [{id, url, sort}]`. `sku_matrix`/`sku_selection` JSON olarak aynen taşınır.

| Uç | Açıklama |
|---|---|
| `GET /api/lists/{id}/products` | Filtre: `status`, `category_id`, `q`; sıralama varsayılanı `sort_no` |
| `POST /api/lists/{id}/products` | Elle ekleme. Zorunlu: `{name, qty, price_yuan}`; opsiyonel diğer alanlar. Görsel/video URL verilirse sunucu arka planda indirir (beyaz liste — docs/04 §2d). Aynı `external_id` sistemde varsa → 409 `DUPLICATE_WARNING` + `meta.existing` (hangi listede); `{force:true}` ile tekrar gönderilirse eklenir |
| `PATCH /api/products/{id}` | Kısmi güncelleme (alan kuralları docs/04 §2d) |
| `PATCH /api/products/{id}/status` | `{status}` — durum makinesine aykırıysa 422 `STATE_TRANSITION` + izinli geçişler `meta.allowed` içinde |
| `DELETE /api/products/{id}` | Çöp kutusuna → 204 |
| `PATCH /api/products/bulk` | `{ids: [...], action: "status"\|"move"\|"delete", status?, target_list_id?}` → 200 `{updated, failed: [{id, error}]}` — kısmi başarı desteklenir |
| `PATCH /api/lists/{id}/products/reorder` | `{ordered_ids: [...]}` → sıra numaraları yeniden yazılır |

## 5. Gelen Kutusu

| Uç | Açıklama |
|---|---|
| `GET /api/inbox` | Filtre: `status=bekliyor\|hatali`; her öğe ham yakalama + önizleme alanları |
| `POST /api/inbox/{id}/assign` | `{list_id, category_id, qty, name?, detail?, sku_selection?}` → 201 ürün oluşur, öğe `atandi` olur |
| `DELETE /api/inbox/{id}` | Öğeyi atmadan siler → 204 |

## 6. Çöp Kutusu

| Uç | Açıklama |
|---|---|
| `GET /api/trash` | Silinen liste + ürünler, kalan gün bilgisiyle |
| `POST /api/trash/{type}/{id}/restore` | `type: lists\|products` → 200 (listesi de silinmiş bir ürün geri alınırken önce listesi geri alınmalı → 409) |
| `DELETE /api/trash/{type}/{id}` | Kalıcı siler → 204 (görselleri de diskten temizler) |

## 7. Ayarlar, Kategoriler, Kur

| Uç | Açıklama |
|---|---|
| `GET /api/settings` | `{yuan_tl, usd_tl, totp_enabled, extension_token_preview}` (token'ın yalnızca son 4 hanesi) |
| `PUT /api/settings/rates` | `{yuan_tl?, usd_tl?}` → rate_history'ye yazar; yalnızca Taslak listelerin görünen TL'sini etkiler |
| `GET /api/settings/rates/history` | Kur tarihçesi (sayfalı) |
| `POST /api/settings/extension-token` | Yeni token üretir → **tam token yalnızca bu yanıtta bir kez** görünür; DB'de hash saklanır |
| `GET/POST/PATCH/DELETE /api/categories` | CRUD; kullanımda olan kategori silinirken → 409 + ürün sayısı |
| `GET /api/activity` | Filtre: `entity_type`, `entity_id`, sayfalı — E9 ekranının kaynağı |

## 8. Yakalama ve Dışa Açık Sayfa

- `POST /api/capture` — istek şeması **docs/04 §2c'de sabit**. Yanıt: 201 `{inbox_id}` veya `{product_id}` (hedef liste seçiliyse); doğrulanamayan gövde → 201 `{inbox_id, status:"hatali"}` (veri kaybolmaz); hız aşımı → 429.
- `GET /p/{share_token}` — API değil, sunucu render sayfa (docs/09 P1). Geçersiz/iptal token → 404. `noindex` başlığı zorunlu.

## 9. Sözleşme Testleri

Faz 1'den itibaren her uç için en az bir PHPUnit sözleşme testi yazılır: örnek istek → beklenen zarf/alanlar/durum kodu. Frontend, `frontend/src/api/` katmanında bu belgeyle birebir aynı tipleri kullanır; sapma kod incelemesinde reddedilir (docs/00 §6).
