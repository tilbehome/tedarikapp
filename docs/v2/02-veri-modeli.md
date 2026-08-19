# tedarikapp v2 — Veri Modeli (v2-02)

Durum: TASLAK (PM, 19 Ağu 2026) · Yerleşim: `docs/v2/02-veri-modeli.md`
Not: Şema ayrıntısı (kolon/indeks) iş emirlerinde K23 kuralıyla yazılır;
bu belge nesneleri ve ilişkileri sabitler.

## 1. Çekirdek nesneler (pragmatik alt küme)

| Nesne | Faz | Açıklama |
|---|---|---|
| ResearchProject | V2-A | Araştırma dosyası ("Eylül 2026 Mutfak") |
| Candidate | V2-A | Havuzdaki aday (Product'a veya doğrudan Listing'e bağlanır; durum: aday/kısa liste/elendi) |
| Product | V2-A | Kanonik/gerçek ürün (bugünkü products'ın evrimi) |
| SourceListing | V2-A | Bir platformdaki ilan (platform, external_id, seller, url, source_type) |
| ListingSnapshot | V2-B | İlanın tarihli hali (fiyat/stok/SKU değişim izi — F09'un temeli) |
| SKU / PriceTier | V2-B | Varyant + adet-fiyat kademeleri (capture v2'den) |
| Supplier / SupplierOffer | V2-B/C | Tedarikçi kartı + teklif; kendi geçmişimiz (sipariş sayısı, gecikme, hasar) zamanla birikir |
| Quote (+versiyon) | V2-C | Alınan teklif; versiyonlar silinmez |
| Sample | V2-C | Numune kaydı |
| PurchaseOrder | V2-D | Kesin sipariş (OrderList'ten türer) |
| Shipment / ShipmentItem | V2-D | Sevkiyat; N ürün ↔ M sevkiyat ilişkisi |
| PaymentMilestone | V2-D | Ödeme kilometre taşı |
| Receipt / Claim | V2-D | Mal kabul + sorun kaydı |
| Document | V2-D | Belge arşivi (her nesneye bağlanabilir) |
| TechnicalProfile | V2-E | Normalize teknik bilgi (F30 girdisi — kolon temeli v1'de var: raw_attributes) |
| TariffClassification | V2-E | GTİP kaydı: kod + tarife sürümü + gerekçe + doğrulayan + BTB |

Belgedeki tam listeden bilinçli çıkarılanlar: ComplianceRecord (F30-lite
kapsamında TariffClassification'a not alanı yeter — K36), ayrı MediaAsset
nesnesi (mevcut product_images + usage_scope kolonu genişlemesi yeter).

## 2. Kritik ilişki: Product ≠ SourceListing

```
Product (gerçek ürün)
 ├── SourceListing — 1688 / Satıcı A   ¥8,40
 ├── SourceListing — 1688 / Satıcı B   ¥7,90
 └── SourceListing — Taobao / Satıcı C ¥11,50
```

- Sipariş satırı (OrderLine) ürüne DEĞİL, seçilmiş Listing'e (+SKU) bağlanır;
  snapshot ilkesi (K50) korunur.
- Karşılaştırma, duplicate engine ve "daha ucuz tedarikçi buldum" bildirimi
  bu ayrımın meyveleridir.

## 3. Migration stratejisi (v1 → V2-A)

1. `source_listings` tablosu açılır.
2. Her mevcut product için 1 listing üretilir (platform, external_id, url,
   main_image_source, fiyat alanları taşınır/kopyalanır).
3. products üzerindeki kaynak-özel alanlar bir sürüm boyunca senkron tutulur
   (okuma geriye uyumlu), sonraki majör sürümde kaldırılır.
4. Export/paylaşım snapshot'ları etkilenmez (K50 — geçmiş dosyalar donuk).
5. Kabul testi: migration öncesi/sonrası aynı listenin Excel çıktısı bayt
   düzeyinde olmasa da alan düzeyinde birebir.

## 4. RAW / NORMALIZED / PROVENANCE katmanları

- RAW: capture'ın orijinal yükü (mevcut raw_attributes çizgisi genişler) —
  salt okunur, hiçbir işlem üzerine yazamaz.
- NORMALIZED: çeviri + standart birimler; her alan için provenance kaydı:
  `{kaynak, orijinal, yöntem(structured_json|dom|ai_inference|manual),
  zaman, güven}`.
- KARAR: insan seçimi (GTİP onayı, tedarikçi seçimi, kısa liste) — kim/ne
  zaman alanlarıyla.
- UI kuralı: ai_inference alanları görsel olarak işaretli; tıklayınca RAW
  değer görünür.

## 5. Durum makineleri (K22 uyumlu büyüme)

- Ürün/liste makineleri v1'deki gibi kalır.
- Yeni nesneler kendi KÜÇÜK makineleriyle gelir (Candidate: aday→kısa
  liste→elendi; Sample: istendi→geldi→incelendi→onay/ret; Shipment:
  hazırlanıyor→yolda→gümrük→teslim). Tek dev zincir kurulmaz; ekranlar
  bağlama göre yalnız ilgili durumu gösterir.
