# GÖREV #21 — Teslim raporu

**Paket:** V3-H / V3-I / V3-J hafif hazırlık üçlüsü  
**Teslim tarihi:** 25 Ağustos 2026  
**Üretim konumu:** Repo dışı teslim klasörü  
**Teslim dosyası:** 4 çalışma dosyası + bu rapor

## 1. Dosyalar ve sayımlar

| Dosya | Kapsam | Sayım özeti |
|---|---|---|
| `woo-taslak-sema.md` | TedarikApp → WooCommerce `wc/v3` taslak çıkış sözleşmesi | 10 değişmez kural, 24 alan eşleme satırı, 9 kanonik görsel kuralı, 10 gönderim öncesi kontrolü, 8 yapılmayacak |
| `ornek-cikti.json` | DM referanslı beklenen çıktı | 2 ürün: DM-001 varyantlı + DM-003 basit; 2 ana ürün POST gövdesi, 3 variation POST gövdesi; 2/2 ana ürün `draft` |
| `numune-formlari.md` | Talep, değerlendirme, AQL, onay numunesi ve mahsup formları | 37 numune talep/takip/teyit alanı, 4 değerlendirme boyutu, 12 AQL parti aralığı, 7 özellikli onay matrisi, 1 mahsup örneği |
| `etiket-sablonlari.md` | SKU, ürün etiketi, koli etiketi ve arşiv davranışı | 2 SKU alternatifi, 16 kategori kısa kodu, 6 varyant eki örneği, 2 etiket şablonu, 2 pasiflik eşlemesi, 11 baskı öncesi kontrolü |
| `TESLIM-RAPORU.md` | Sayımlar, doğrulama beyanı ve kaynak listesi | Bu rapor |

Toplam çalışma içeriği: rapor hariç **942 satır**, yaklaşık **5.615 kelime**. JSON söz dizimi `jq` ile doğrulanmıştır.

## 2. Kabul ölçütü karşılığı

### 21A — Woo taslak çıkışı

- TR ad, uzun/kısa açıklama, kanonik görsel, kategori önerisi, SKU ve varyasyon eşlemeleri tanımlandı.
- Ana ürünlerde `status: "draft"` zorunlu kılındı ve iki örnekte de uygulandı.
- Fiyat “boş dize” olarak bile gönderilmiyor; tüm fiyat anahtarları istek gövdelerinden çıkarılıyor.
- Landed cost yalnız TedarikApp kaydına giden metinsel iç not referansı olarak tutuluyor; tutar yok.
- Stok alanları ve stok senkronu yok.
- Varyantlar ana ürün içindeki salt-okunur ilişki listesine gömülmek yerine ayrı variation endpoint’lerine hazırlanıyor.
- Woo kategori ID’si mağazaya özgü olduğu için örneklerde uydurulmadı; `null / needs_review` bırakıldı ve `categories` body’den çıkarıldı.
- `demo-urun-seti.json` görsel URL içermediğinden örnek URL’ler açıkça sözleşme yolu olarak işaretlendi; canlı dosya iddiası taşımıyor.

### 21B — Numune süreci

- Firma, ürün, varyant, adet, bedel/mahsup koşulu ve kargo takibi alanları kapsandı.
- Görsel uyum, malzeme–işçilik, fonksiyon ve ambalaj için ayrı 1–5 puan, not ve foto yuvası verildi.
- AQL 2.5/4.0 hızlı referansı parti–örnek–Ac/Re yapısında sunuldu.
- Kritik kusur için iç `Ac 0 / Re 1` kuralı, rastgele örnekleme ve AQL’nin sınırları açıklandı.
- Onay numunesi（确认样）tutanağı ve kurgusal mahsup kayıt örneği eklendi.
- Kritik operasyon terimlerinde TR yanında ZH karşılığı kullanıldı.

### 21C — Etiket ve SKU

- 8B kategori kodu + sıra + varyant ekli okunabilir düzen ile kategoriden bağımsız opak düzen karşılaştırıldı; karar verilmedi.
- Ürün ve koli etiketi için baskıya aktarılabilir metin şablonları ve doğrulama kontrolleri verildi.
- `Made in PRC` ve ithalatçı satırı pratik şablona eklendi; bunun mevzuat görüşü olmadığı ve teyidin Ürün Sahibi’nde olduğu açıkça belirtildi.
- “Artık almıyoruz” ve “uykuda” yeni durum yapılmadı. 5B tek kaynağı korunarak sırasıyla `status.archived — Arşivlendi` ve `status.expired — Güncelliğini yitirdi` altında iş gerekçesi olarak modellendi.

## 3. Woo REST güncellik beyanı

21A’daki Woo REST alan adları ve endpoint’ler **25 Ağustos 2026** tarihinde WooCommerce’in güncel resmi dokümanlarıyla kontrol edilmiştir. Sözleşme `wc/v3` alanlarını kullanır: ana ürün için `name`, `type`, `status`, `description`, `short_description`, `sku`, `categories`, `images`, `attributes`, `default_attributes`, `meta_data`; variation için `sku`, `description`, `attributes`, `image`, `meta_data`. Ürün ve variation yaratımı için resmi `POST` endpoint yapıları esas alınmıştır.

Fiyat ve stok alanları Woo’da mevcut olsa da iş gereği özellikle kullanılmamıştır. Ana ürün `variations` alanı yaratım gövdesine konmamış; varyantlar resmi ayrı variation endpoint’ine ayrılmıştır.

## 4. Kaynaklar

### 4.1 TedarikApp iç kaynakları

- `demo-urun-seti.json`, şema v2: örnek ürünler DM-001 ve DM-003; veri demo/test amaçlıdır.
- `kategori-agaci.json`, sürüm 1.0: 8B kategori kodları ve kategori eşleme davranışı.
- `cikti-terimleri.json`: 5B’nin 15 kanonik durum anahtarı ve TR/EN/ZH karşılıkları.

### 4.2 WooCommerce resmi kaynakları

- [WooCommerce REST API — Products](https://developer.woocommerce.com/docs/apis/rest-api/v3/products/)
- [WooCommerce REST API — Product Variations](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-variations/)
- [WooCommerce REST API — Product Categories](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-categories/)
- [WooCommerce REST API — Product Attributes](https://developer.woocommerce.com/docs/apis/rest-api/v3/product-attributes/)
- [WooCommerce REST API genel dokümanı](https://woocommerce.com/document/woocommerce-rest-api/)
- [WooCommerce REST API GitHub deposu — `wc/v3` kararlı sürüm notu](https://github.com/woocommerce/woocommerce-rest-api)

### 4.3 AQL / kalite kaynakları

- [ISO 2859-1:2026 — Sampling procedures for inspection by attributes](https://www.iso.org/standard/85464.html) — güncel standart tanımı ve kapsamı.
- [U.S. Defense Logistics Agency ASSIST — MIL-STD-105E](https://quicksearch.dla.mil/qaDocDetails.aspx?ident_number=35496) — kamuya açık tarihsel örnekleme tabloları için resmi arşiv. DLA kaydı standardın 2008’de iptal edildiğini gösterir; kaynak güncel normatif dayanak değil, sayıların tarihsel çapraz kontrolüdür.

## 5. Açık belirsizlikler ve karar sahipleri

| Konu | Bilinen / belirsiz | Karar sahibi |
|---|---|---|
| SKU Alternatif A veya B | İki seçenek karşılaştırıldı; seçim yapılmadı | PM + Ürün Sahibi |
| Gerçek Woo kategori ID’leri | Mağaza verisi sağlanmadı; örnekte uydurulmadı | Ürün Sahibi |
| Kanonik görsel dosyalarının canlılığı | URL örüntüsü tanımlı; örnek varlıklar canlı kabul edilmedi | TedarikApp uygulama akışı |
| Satış fiyatı | Bilerek boş; landed cost fiyat değildir | Ürün Sahibi |
| AQL sözleşme profili | 2.5/4.0 pratik başlangıç; ürün riskine göre teyit gerekir | PM + Ürün Sahibi |
| Nihai etiket zorunlulukları | Bu paket mevzuat görüşü değildir | Ürün Sahibi / gerektiğinde uzman |

## 6. Teknik doğrulama sonucu

- JSON parse: **başarılı**.
- Örnek ürün sayısı: **2**.
- Varyant sayısı: **3**.
- Ana ürün gövdelerinde `draft`: **2 / 2**.
- Ana ürün/varyant istek gövdelerinde yasak fiyat veya stok anahtarı: **0**.
- Kaynak durum sözlüğü dışında yeni TedarikApp durum anahtarı: **0**.
- Repo yazımı: **yok**; tüm çıktılar görev teslim klasöründedir.
