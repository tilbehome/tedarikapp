# GÖREV #30-EK — Teslim raporu

## 1. Teslim özeti

| Ölçüm | Sonuç |
|---|---:|
| Korunan mevcut `portal.*` anahtarı | 111 |
| Şartname bölüm 11’den eklenen anahtar | 22 |
| RFQ enum kodları için eklenen etiket | 16 |
| Toplam yeni `portal.*` anahtarı | 38 |
| Güncel `portal-metinleri.json` toplamı | 149 |
| 5B mevcut toplamı | 185 |
| Eklenen `status.*` satırı | 1 |
| 5B yeni toplamı | 186 |

Mevcut 111 terimin sırası, anahtarı ve bütün alan değerleri değiştirilmedi. Kaynak 111 terim dizisinin SHA-256 değeri ile güncel dosyanın ilk 111 terim dizisinin SHA-256 değeri aynıdır: `8739d1d0e26d9d85dc2a57731655beb7389b5f1d63e741d3fccd1c6f7f986902`.

## 2. Enum listesi

| Grup | Kaynaktaki kanonik kodlar | Etiket sayısı |
|---|---|---:|
| `termin_baslangici` | `order_confirmation`, `deposit_received`, `sample_approval`, `artwork_approval`, `custom` | 5 |
| `termin_birimi` | `calendar_day`, `working_day`, `week` | 3 |
| Miktar birimi | `adet`, `set`, `paket`, `koli`, `kg`, `m`, `m2`, `ozel` | 8 |
| Ambalaj türü | RFQ v1’de enum tanımı yok; alan `string|null` | 0 |
| **Toplam** |  | **16** |

Kodlar değiştirilmedi; yalnız kullanıcı etiketleri `portal.enum.*` altında eklendi. TR kaynak metindir. EN/ZH karşılıkları LLM hattında oluşturuldu; mevcut 5B’deki kesin terimler (ürün, koli, MOQ, durum ve ölçü dili) korunarak kullanıldı. Marka, model ve ölçü değerleri çevrilmedi.

## 3. RFQ şema diff’i — v1 → v2

| Alan | v1 | v2 |
|---|---|---|
| `schema_version` | `1.0.0` | `2.0.0` |
| Asıl `yanit_durumu` | `unanswered/found/not_found/alternative_available` | `unanswered/found/not_found` |
| Asıl satırdaki alternatif alanları | `alternatif_urun_baglantisi`, `alternatif_aciklamasi` | Kaldırıldı |
| Asıl `not_found` cevabı | Alternatif durumuna dönüşebiliyordu | `not_found` olarak değişmeden kalır |
| Alternatif | Asıl yanıt kaydının durumu/alanları | `asil_rfq_satir_id` ile bağlanan ayrı `alternatif_cevap_modeli` |
| Alternatif iş alanları | Link + açıklama ve asıl cevabın ticari alanları | `ad`, `kaynak`, `fiyat_kademeleri`, `moq`, `not` |
| Alternatif rozeti | Kaydedilen `alternative_available` durumu | Bağlı alternatif nesnesinin varlığından türetilir; alan değildir |
| Çapraz kontroller | Tek kayıttaki alternatif durumuna bağlı | Asıl cevabın `not_found` kalması ve ayrı nesnenin aynı tur/satıra bağlanması doğrulanır |

Bu model #28 kabul sınırı 8 ile aynıdır: alternatif ayrı cevap nesnesidir, asıl RFQ satırına bağlanır ve asıl ürünün `Bulunamadı` cevabı korunur.

## 4. Dil ve anahtar kontrolleri

| Kontrol | Sonuç |
|---|---|
| Yeni 38 portal anahtarının TR+EN+ZH alanı dolu | GEÇTİ |
| Yer tutucular üç dilde aynı | GEÇTİ |
| Yeni kullanıcı metinlerinde “1688” geçmiyor | GEÇTİ |
| Yeni anahtarların kendi içinde tekrarı | 0 |
| Yeni anahtarlar ↔ mevcut 111 (7B) çakışması | 0 |
| Güncel portal anahtarları ↔ mevcut 185 (5B) çakışması | 0 |
| `status.viewed` ↔ mevcut 5B çakışması | 0 |
| Mevcut 111 terim veri eşitliği | GEÇTİ — SHA-256 eşit |

## 5. JSON parse kanıtı

Aşağıdaki dosyalar standart JSON ayrıştırıcısıyla başarıyla açıldı ve kök nesne olarak doğrulandı:

- `portal-metinleri.json` — 149 terim
- `rfq-alan-sozlesmesi-v2.json` — şema `2.0.0`

Doğrulama komutları: `python3 -m json.tool <dosya>` ve `jq -e 'type == "object"' <dosya>`. Her iki araç için çıkış kodu `0` olmalıdır; paketleme öncesi kabul betiği bunu yeniden çalıştırır.

## 6. Prototip değişmezlik kanıtı

| Onaylı dosya | SHA-256 | Durum |
|---|---|---|
| `firma-portali-prototip.html` | `bbf65571b22686f4e434b5a4ecd0f883cdead8020c7de67c141726d160028e6a` | Değiştirilmedi |
| `OKUBENI.md` | `8356aeb3730c9bd715b5c77100ff0f7c1cbf9f673f142f0c1250ba1753ecdca1` | Değiştirilmedi |

## 7. Açık sorular

1. **Ambalaj enum kaynağı eksik:** Verilen `rfq-alan-sozlesmesi.json` dosyasında `ambalaj` alanı `string|null`; izinli ambalaj kodları tanımlanmamış. “Enum listeleri sözleşmeyle birebir” ve “yeni özellik/alan türetme yok” koşulları gereği `portal.enum.ambalaj_turu.*` seçenekleri uydurulmadı. PM ambalajı enum yapmak istiyorsa önce kanonik kod listesini RFQ sözleşmesine karar olarak eklemelidir. Bunun dışındaki dört PM kararı uygulanmıştır.
