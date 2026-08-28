# TedarikApp → WooCommerce taslak çıkışı sözleşmesi

**Sürüm:** `woo-draft-export/1.0`  
**Kapsam:** V3-H hafif hazırlık; TedarikApp ürününü Ürün Sahibi’nin WooCommerce mağazasına yalnızca **taslak** olarak hazırlama  
**REST hedefi:** WooCommerce REST API `wc/v3`  
**Örnek veri kaynağı:** `demo-urun-seti.json` — DM-001 ve DM-003

## 1. Değişmez kurallar

1. Ana ürün oluşturma gövdesinde `status` her zaman `"draft"` olur. `publish`, `pending` veya `private` üretilemez.
2. `regular_price`, `sale_price`, `price` ve maliyet/fiyatı Woo’ya taşıyabilecek eşdeğer alanlar hiçbir istek gövdesinde bulunmaz. “Boş fiyat alanı”, boş dize göndermek değil, fiyat anahtarlarını **tamamen çıkarmak** demektir.
3. Landed cost değeri gönderilmez. Yalnızca TedarikApp içindeki maliyet kaydına yönlendiren metinsel bir referans, özel `meta_data` notunda tutulabilir.
4. Stok senkronu yapılmaz. `manage_stock`, `stock_quantity`, `stock_status` ve varyant stokları çıkışa alınmaz.
5. Kaynak görseller pazar yeri veya tedarikçi URL’sinden doğrudan bağlanmaz; yalnızca TedarikApp kanonik medya URL’si kullanılır.
6. Varyantlı ürün önce `type: "variable"` ana ürün olarak oluşturulur; her varyant daha sonra ayrı variation endpoint’ine gönderilir.
7. Woo kategori kimliği mağazaya özgü tamsayıdır. Eşleme doğrulanmamışsa kategori uydurulmaz, otomatik oluşturulmaz ve `categories` alanı istekten çıkarılır.
8. SKU yalnızca TedarikApp tarafından üretilir; tedarikçi SKU’su ana SKU yapılmaz. SKU verildikten sonra kategori değişse dahi değiştirilmez.
9. TedarikApp iş akışı durumu ile Woo `status` alanı aynı şey değildir. TedarikApp durumu gerekiyorsa sadece 5B sözlüğündeki anahtarla `_tedarikapp_status_key` meta alanında saklanır; Woo `status` yine `draft` kalır.
10. Kullanıcı onayı olmadan yayınlama, fiyat girme, stok yönetimi açma veya kategori yaratma çağrısı çalıştırılmaz.

## 2. İşlem modeli

### 2.1 Basit ürün

Tek çağrı hazırlanır:

```text
POST /wp-json/wc/v3/products
```

Gövde `type: "simple"` ve `status: "draft"` içerir. Fiyat ve stok anahtarları içermez.

### 2.2 Varyantlı ürün

İşlem sırası zorunludur:

1. `POST /wp-json/wc/v3/products` ile `type: "variable"`, `status: "draft"` ana ürün oluşturulur.
2. Dönen ana ürün `id` değeri alınır.
3. Her varyant için `POST /wp-json/wc/v3/products/{parent_product_id}/variations` çağrısı hazırlanır.
4. Varyantların SKU ve attribute seçenekleri doğrulanır; fiyat ve stok alanı eklenmez.
5. Ana ürünün Woo’da hâlâ `draft` olduğu yeniden okunarak doğrulanır.

Bir Woo sürümü veya eklenti fiyatı olmayan varyantı reddederse sistem sahte `0`, `0.00` ya da kaynak satın alma fiyatı yazmaz. Ana ürün taslak bırakılır, hata `manual_price_required` olarak inceleme kuyruğuna alınır.

## 3. Alan eşleme sözleşmesi

| TedarikApp alanı / üretim kuralı | Woo REST alanı | Dönüşüm ve doğrulama | Zorunluluk |
|---|---|---|---|
| `name_tr` | `name` | TR ad; baş/son boşlık temizlenir, kaynak id ada eklenmez | Zorunlu |
| Ürün türü | `type` | Varyant varsa `variable`, yoksa `simple` | Zorunlu |
| Sabit sözleşme değeri | `status` | Her zaman `draft`; farklı değer şema ihlalidir | Zorunlu |
| Onaylı TR uzun açıklama | `description` | Güvenli HTML; kaynaktan doğrulanmayan pazarlama iddiası eklenmez | Zorunlu |
| Onaylı TR kısa açıklama | `short_description` | Kısa, olgusal TR özet | Önerilen |
| 21C SKU üretimi | `sku` | ASCII büyük harf, rakam ve tire; mağazada benzersiz | Zorunlu |
| 8B `category_code` | `categories[].id` | Kod, mağazaya özgü doğrulanmış Woo kategori ID’sine çevrilir | Eşleme varsa |
| Kanonik ana görsel | `images[0].src` | HTTPS TedarikApp kanonik URL; ilk görsel kapaktır | Görsel varsa |
| Kanonik ek görseller | `images[n].src` | Tekilleştirilmiş, kararlı sıralı HTTPS URL | Görsel varsa |
| TR görsel adı | `images[].name` | Dosya adı değil, okunur TR ad | Önerilen |
| TR alternatif metin | `images[].alt` | Görseli tarif eder; anahtar kelime doldurma yapılmaz | Önerilen |
| Varyant ekseni | Ana üründe `attributes[]` | `name`, `position`, `visible: true`, `variation: true`, TR `options[]` | Varyantlıda zorunlu |
| Varsayılan varyant kararı | `default_attributes[]` | Yalnız Ürün Sahibi seçerse; otomatik seçim yok | İsteğe bağlı |
| Her TedarikApp varyantı | Variation endpoint’i | Ayrı body; kendi `sku`, `attributes[]`, gerekirse `image` | Varyantlıda zorunlu |
| `demo_id` / kaynak kayıt id | `meta_data` | `_tedarikapp_source_ref`; ör. `demo-urun-seti.json#DM-001` | Zorunlu |
| Tekil dışa aktarım anahtarı | `meta_data` | `_tedarikapp_export_key`; tekrar çağrıda çoğaltmayı önler | Zorunlu |
| 5B durum anahtarı | `meta_data` | `_tedarikapp_status_key`; yalnız 5B’de tanımlıysa | Varsa |
| Landed cost kayıt bağlantısı | `meta_data` | `_tedarikapp_internal_note` içinde yalnız referans; tutar yok | Varsa |
| Kaynak kategori izi | `meta_data` | `_tedarikapp_category_code`; 8B kodu | Önerilen |
| Kaynak dilde ad | `meta_data` | `_tedarikapp_name_zh`; iç izleme için | Önerilen |
| `price_yuan`, fiyat kademeleri | — | Dışa aktarılmaz | Yasak |
| Varyant fiyatı | — | Dışa aktarılmaz | Yasak |
| Kaynak stok / satış sayısı | — | Dışa aktarılmaz | Yasak |
| Tedarikçi firma ve iletişim verisi | — | Müşteri yüzüne veya açıklamaya konmaz | Yasak |

`meta_data`, Woo’nun standart özel alan taşıyıcısıdır; alt öğeler `{ "key": "…", "value": "…" }` biçimindedir. `_tedarikapp_internal_note` müşteri mesajı olan `purchase_note` alanına konmaz.

## 4. Fiyat ve landed cost politikası

### 4.1 Yasak anahtarlar

Aşağıdaki anahtarlar ana ürün ve varyant gövdelerinde özyinelemeli olarak yasaktır:

```text
price
regular_price
sale_price
cost_of_goods_sold
manage_stock
stock_quantity
stock_status
```

Çıkış doğrulayıcısı bu anahtarlardan birini bulursa paketi göndermeden durdurur. `"regular_price": ""`, `"regular_price": "0"` ve `"regular_price": "0.00"` da ihlaldir.

### 4.2 İzin verilen iç not

```json
{
  "key": "_tedarikapp_internal_note",
  "value": "Landed cost kaydı: TedarikApp/DM-001/landed-cost/latest; Woo satış fiyatı Ürün Sahibi kararı bekliyor."
}
```

Bu not bir tutar içermez, fiyat alanını doldurmaz ve fiyat önerisi sayılmaz.

## 5. Kanonik görsel URL politikası

1. TedarikApp önce kaynak görseli kendi kontrollü medya alanına alır; dış pazar yeri hotlink’i Woo’ya verilmez.
2. URL biçimi `https://tedarikapp.tilbehometoptan.com/media/canonical/{source_ref}/{asset}` örüntüsünü izler.
3. URL HTTPS olmalı, kimlik doğrulama çerezi gerektirmemeli ve Woo sunucusundan erişilebilir olmalıdır.
4. İmzalı/geçici URL, sorgu dizesinde sır veya kısa süreli token kullanılamaz.
5. Aynı içerik için tek kanonik URL korunur; içerik değişirse yeni asset sürümü üretilir.
6. Dosya MIME türü ve uzantısı uyumlu olmalı; en azından `image/jpeg`, `image/png` veya mağazanın kabul ettiği `image/webp` kullanılmalıdır.
7. `images[0]` kapak görselidir. Sıra TedarikApp’ta sabitlenir; tekrar dışa aktarımda rastgele değişmez.
8. URL’nin `200` dönmesi, görsel MIME türü vermesi ve minimum görsel kalite eşiğini karşılaması çıkıştan önce kontrol edilir.
9. DM örneklerindeki URL’ler canlı görsel iddiası değil, beklenen sözleşme yolu örnekleridir.

## 6. Kategori eşleme önerisi

Woo `categories[].id` değeri mağaza veritabanına özgü bir tamsayıdır. 8B kodu doğrudan bu alana yazılmaz. Ayrı eşleme tablosu tutulur:

```json
{
  "ev_tekstili.havlu": { "woo_category_id": 123, "confirmed_by": "Ürün Sahibi", "confirmed_at": "YYYY-MM-DD" },
  "ev_tekstili.yatak_tekstili": { "woo_category_id": 124, "confirmed_by": "Ürün Sahibi", "confirmed_at": "YYYY-MM-DD" }
}
```

Yukarıdaki `123` ve `124` yalnız biçim örneğidir; üretimde gerçek Woo kategori listesi `GET /wp-json/wc/v3/products/categories` üzerinden okunup Ürün Sahibi tarafından eşleştirilir.

Eşleme sonucu:

- Doğrulanmış ID varsa `categories: [{"id": …}]` eklenir.
- Kod var ama ID yoksa taslak gövdesinden `categories` çıkarılır ve `category_resolution: "needs_review"` kaydedilir.
- Kaynak kategori belirsizse 8B’nin davranışı korunur: sahte kategori atanmaz, panelde “kategori ata” işi açılır.
- Yeni Woo kategorisi otomatik oluşturulmaz.

DM-001 için `浴巾` eşlemesi nedeniyle `ev_tekstili.havlu`; DM-003 için kaynak üst yol ve ürün anlamı nedeniyle `ev_tekstili.yatak_tekstili` önerilir. DM-003 alt kategori önerisi insan teyidi ister.

## 7. SKU bağı — V3-J

Bu çıkış varsayılan olarak 21C’deki **Alternatif A** düzenini kullanır:

```text
{KATEGORİ-KISA-KODU}-{6 HANELİ GLOBAL SIRA}-{VARYANT EKİ}
```

- DM-001 ana SKU: `EVT-HVL-000001`
- DM-001 krem varyant: `EVT-HVL-000001-KRM`
- DM-003 basit ürün: `EVT-YTK-000003`

Kategori kısa kodu okunabilirlik sağlar fakat SKU kimliği atandıktan sonra değişmez. Sonraki kategori düzeltmesi SKU’yu yeniden yazmaz. 21C Alternatif B seçilirse aynı export sözleşmesi korunur, sadece `sku` üreticisi `TH-{sıra}-{varyant}` biçimine geçer.

## 8. Tekrarlı çalıştırma ve hata davranışı

1. `_tedarikapp_export_key` ve SKU, TedarikApp’ta Woo ürün id’siyle birlikte saklanır.
2. Aynı kayıt yeniden çalıştırıldığında yeni ürün açmadan önce kayıtlı Woo id ve SKU kontrol edilir.
3. Ana ürün oluşturulup varyantların bir kısmı başarısız olursa ana ürün silinmez veya yayınlanmaz; `draft` kalır ve eksik varyantlar hata listesine alınır.
4. Kategori, medya veya varyant hatası fiyat/stok alanı ekleyerek aşılmaz.
5. API hatası, HTTP kodu, Woo hata kodu ve `operation_id` ile kaydedilir; erişim anahtarı ve gizli veri loglanmaz.
6. Düzeltme sonrası yalnız başarısız operasyon yeniden denenir.

## 9. Gönderim öncesi doğrulama listesi

- [ ] Ana ürün body’sinde `status` tam olarak `draft`.
- [ ] Ürün `type` değeri `simple` veya `variable` ve kaynakla uyumlu.
- [ ] Fiyat, landed cost tutarı ve stok anahtarları yok.
- [ ] Her `name`, `description` ve `short_description` TR.
- [ ] Her SKU 21C düzenine uygun ve mağazada benzersiz.
- [ ] Varyant seçenekleri ana ürün `attributes[].options` listesinde mevcut.
- [ ] Görseller yalnız HTTPS kanonik TedarikApp URL’lerinden geliyor.
- [ ] Woo kategori ID’si doğrulanmış; değilse `categories` alanı çıkarılmış.
- [ ] Kaynak referansı ve dışa aktarım anahtarı `meta_data` içinde.
- [ ] Ana ürün oluşturma yanıtından sonra durum yeniden okunup `draft` doğrulanıyor.

## 10. Yapılmayacaklar

- Stok senkronu yapılmayacak.
- Satış fiyatı, indirimli fiyat, sıfır fiyat veya landed cost fiyat alanına basılmayacak.
- Ürün ya da varyant otomatik yayınlanmayacak.
- Tedarikçi görseli doğrudan hotlink edilmeyecek.
- Doğrulanmamış Woo kategori ID’si uydurulmayacak ve kategori otomatik yaratılmayacak.
- TedarikApp dışındaki kaynak SKU ana kimlik olarak kullanılmayacak.
- `purchase_note` alanına iç maliyet veya tedarik notu yazılmayacak.
- TedarikApp için 5B dışında yeni iş akışı durum adı türetilmeyecek.

## 11. REST kaynak dayanağı

Alan adları ve endpoint’ler 25 Ağustos 2026 tarihinde WooCommerce’in güncel resmi `wc/v3` belgeleriyle karşılaştırılmıştır:

- [Products API](https://developer.woocommerce.com/docs/apis/rest-api/v3/products/)
- [Product Variations API](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-variations/)
- [Product Categories API](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-categories/)
- [Product Attributes API](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-attributes/)
- [WooCommerce REST API genel dokümanı](https://woocommerce.com/document/woocommerce-rest-api/)

Woo dokümanında ürün `variations` alanı okuma tarafında ilişki/id listesi olarak yer aldığı için varyant yaratımı ana ürün gövdesine gömülmez; ayrı variations endpoint’i kullanılır.
