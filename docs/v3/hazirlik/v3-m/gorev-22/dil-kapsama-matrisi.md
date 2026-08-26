# TedarikApp V3-M — Dil Kapsama Matrisi

**Sürüm:** `v3-m-language-coverage/1.0.0`  
**Tarih:** 26 Ağustos 2026  
**Kapsam:** Excel, PDF, CSV, paylaşım sayfası ve kanal metinleri; TR/EN/ZH.  
**Kabul ilkesi:** Seçilen dil dışında **sıfır sistem etiketi**. Açıkça “özgün kaynak” işaretli K55 alanı, ürün kodu/modeli, URL, ISO para kodu ve ticari özel ad sistem etiketi sayılmaz; bu istisnalar yeni sistem metni taşımak için genişletilemez.

## 1. Kaynak durumu

| Kaynak | Bu sette kullanılan kanıt |
|---|---|
| `cikti-terimleri.json` 5B | 185 benzersiz terim; 15 bağlayıcı `status.*`; TR/EN/ZH metinleri |
| `kabul-turu-v1.md` KT-032..037 | Excel/PDF/paylaşım için tek dil, K55 özgün satır, doğru ZH glif ve doğru liste/revision |
| `kabul-turu-v1.md` KT-038 | Eksik metrik boş veya seçilen dilde “mevcut değil”; `0`/sahte skor yok |
| `panel-e2e-senaryo-katalogu.md` E2E-PNL-37..40 | Alan değeri dâhil locale lint; K55 `DM-016` oracle'ı; XLSX/PDF metin çıkarımı |
| `ayarlar-bilgi-mimarisi.md` | K55 açık; tek dil kalite kapısı açık |
| `portal-metinleri.json` 7B | Kademeli fiyatın üç alanı için semantik dayanak; 5B'ye aktarılmış sayılmaz |
| Görev #22 şartnamesi | “—/null”, üç satırlı kademeli başlık ve biçim/font kabul koşulları |

**Kanıtlanamayan bağ:** K55 doğrulanmıştır; `K57` ve `K61` numaralarının hangi belge maddesine karşılık geldiği erişilen kaynaklarda bulunamadı. Bu numara–kural eşlemesi **kanıtlanmadı**. Görevde açık yazılan “—” ve üç satırlı başlık kuralları yine bağlayıcı test girdisidir.

## 2. Çıktı türü × dil kapsama matrisi

Bu 15 satır her render için zorunlu görünür yüzeyi tanımlar. Bir gruptaki anahtarların tam metni Bölüm 4'teki 185 satırlık envanterden alınır; uygulama alt küme gösterse bile ekranda görünen her anahtar seçili locale'den çözülür.

| Çıktı | Dil | Görünebilen sistem metni kümeleri | Tarih / saat | Sayı / TRY / CNY örneği | Sıralama |
|---|---|---|---|---|---|
| Excel | TR | `doc.*, col.*, status.*, kpi.*, total.*, footnote.*` | `26.08.2026` / `14:05` | `1.234,56` / `₺1.234,56` / `¥1.234,56` | Ham veriyle, `tr-TR` metin eşitliği, `source_id` son bağ |
| Excel | EN | `doc.*, col.*, status.*, kpi.*, total.*, footnote.*` | `26 Aug 2026` / `14:05` | `1,234.56` / `TRY 1,234.56` / `CNY 1,234.56` | Ham veriyle, `en` metin eşitliği, `source_id` son bağ |
| Excel | ZH | `doc.*, col.*, status.*, kpi.*, total.*, footnote.*` | `2026年8月26日` / `14:05` | `1,234.56` / `TRY 1,234.56` / `¥1,234.56` | Ham veriyle, `zh-Hans-CN` metin eşitliği, `source_id` son bağ |
| PDF | TR | `doc.*, col.*, status.*, kpi.*, total.*, footnote.*` | `26.08.2026` / `14:05` | `1.234,56` / `₺1.234,56` / `¥1.234,56` | Ham veriyle, `tr-TR` metin eşitliği, `source_id` son bağ |
| PDF | EN | `doc.*, col.*, status.*, kpi.*, total.*, footnote.*` | `26 Aug 2026` / `14:05` | `1,234.56` / `TRY 1,234.56` / `CNY 1,234.56` | Ham veriyle, `en` metin eşitliği, `source_id` son bağ |
| PDF | ZH | `doc.*, col.*, status.*, kpi.*, total.*, footnote.*` | `2026年8月26日` / `14:05` | `1,234.56` / `TRY 1,234.56` / `¥1,234.56` | Ham veriyle, `zh-Hans-CN` metin eşitliği, `source_id` son bağ |
| CSV | TR | `col.* başlıkları + status.* değerleri; belge kromu yok` | `26.08.2026` / `14:05` | `1.234,56` / `₺1.234,56` / `¥1.234,56` | Ham veriyle, `tr-TR` metin eşitliği, `source_id` son bağ |
| CSV | EN | `col.* başlıkları + status.* değerleri; belge kromu yok` | `26 Aug 2026` / `14:05` | `1,234.56` / `TRY 1,234.56` / `CNY 1,234.56` | Ham veriyle, `en` metin eşitliği, `source_id` son bağ |
| CSV | ZH | `col.* başlıkları + status.* değerleri; belge kromu yok` | `2026年8月26日` / `14:05` | `1,234.56` / `TRY 1,234.56` / `¥1,234.56` | Ham veriyle, `zh-Hans-CN` metin eşitliği, `source_id` son bağ |
| Paylaşım sayfası | TR | `doc.*, col.*, status.*, kpi.*, total.*, filter.*, footnote.* + onaylı aday eylemler` | `26.08.2026` / `14:05` | `1.234,56` / `₺1.234,56` / `¥1.234,56` | Ham veriyle, `tr-TR` metin eşitliği, `source_id` son bağ |
| Paylaşım sayfası | EN | `doc.*, col.*, status.*, kpi.*, total.*, filter.*, footnote.* + onaylı aday eylemler` | `26 Aug 2026` / `14:05` | `1,234.56` / `TRY 1,234.56` / `CNY 1,234.56` | Ham veriyle, `en` metin eşitliği, `source_id` son bağ |
| Paylaşım sayfası | ZH | `doc.*, col.*, status.*, kpi.*, total.*, filter.*, footnote.* + onaylı aday eylemler` | `2026年8月26日` / `14:05` | `1,234.56` / `TRY 1,234.56` / `¥1,234.56` | Ham veriyle, `zh-Hans-CN` metin eşitliği, `source_id` son bağ |
| Kanal metni | TR | `msg.* + iletide durum gösteriliyorsa status.*` | `26.08.2026` / `14:05` | `1.234,56` / `₺1.234,56` / `¥1.234,56` | Ham veriyle, `tr-TR` metin eşitliği, `source_id` son bağ |
| Kanal metni | EN | `msg.* + iletide durum gösteriliyorsa status.*` | `26 Aug 2026` / `14:05` | `1,234.56` / `TRY 1,234.56` / `CNY 1,234.56` | Ham veriyle, `en` metin eşitliği, `source_id` son bağ |
| Kanal metni | ZH | `msg.* + iletide durum gösteriliyorsa status.*` | `2026年8月26日` / `14:05` | `1,234.56` / `TRY 1,234.56` / `¥1,234.56` | Ham veriyle, `zh-Hans-CN` metin eşitliği, `source_id` son bağ |

## 3. Görünür öğe sınıfları ve dil davranışı

| Sınıf | Görünür örnek | Kaynak | Dil kuralı |
|---|---|---|---|
| Belge başlığı/üstbilgi/altbilgi | Teklif Listesi, Firma, Sayfa 1/3 | `doc.*` | Seçili dil; yer tutucular korunur |
| Sütun başlığı | Ürün Adı, Durum, Birim Fiyat | `col.*` | Seçili dil; CSV başlığı da aynı sözlükten |
| Durum rozeti/değeri | Siparişe hazır / Ready to Order / 可下单 | Yalnız 15 `status.*` | Yeni durum yok; anahtar bilinmiyorsa çıktı kırmızı |
| KPI etiketi | Ürün Sayısı, Toplam CBM | `kpi.*` | Seçili dil; değer ham sayısal veriden biçimlenir |
| Finans etiketi | Ara Toplam, Kilitli Kur | `total.*` | Seçili dil; para/kur biçimi Bölüm 2 profilinden |
| Filtre ve görünür sıralama | En yeni, En eski, Tümü | `filter.*` | Paylaşım sayfasında seçili dil |
| Kanal cümlesi | Hazır özet, fiyat güncelleme, link bitişi | `msg.*` | Şablon ve bütün yer tutucu değerleri seçili dil projeksiyonundan |
| Dipnot | Kur kilidi, çeviri, beyan stok | `footnote.*` | Seçili dil; başka locale'den dipnot kabul edilmez |
| Çevrilebilir ürün değeri | Ürün adı, marka, renk, varyant, malzeme | Alanın dil projeksiyonu | Seçili dil; eksik çeviri ham ZH'ye sessiz düşmez |
| Özgün kaynak alanı | `高硼硅玻璃油壶 550ml` | K55/DM-016 | Yalnız açık “özgün kaynak” alanında byte/anlam korunur |
| Dil-bağımsız veri | `DM-016`, model, URL, ISO kodu | Kaynak veri | Çevrilmez; sistem etiketi gibi kullanılmaz |
| Boş alan | `—` | Görev #22; `ADAY-22-001` | `null`, `undefined`, `NaN`, boş sistem etiketi gösterilmez; gerçek `0` korunur |
| Tarih/saat | 26.08.2026 / 26 Aug 2026 / 2026年8月26日 | V3-M biçim profili | Locale'e göre; ZH'de TR ay adı yasak |
| Sayı/para/yüzde | 1.234,56 / 1,234.56 / ¥1,234.56 | V3-M biçim profili | Ham değer değişmez; yalnız görünüm değişir |
| Görsel alt metni | Ürün adı + görsel sıra bilgisi | Ürün dil projeksiyonu; şablon anahtarı yok | Görünür/erişilebilir sistem metni sayılır; şablon açıkça `5B'ye aday`dır |

## 4. 5B'nin tam görünür metin envanteri — 185 anahtar

Dağılım: `col.*=59`, `doc.*=18`, `filter.*=25`, `footnote.*=15`, `kpi.*=18`, `msg.*=12`, `status.*=15`, `total.*=23`. `gozden_gecir` durumu olan mevcut 5B kayıtları yeni terim değildir; mevcut inceleme bayrakları aynen korunur.

| 5B anahtarı | TR | EN | ZH | Görünebildiği çıktılar | Kaynak durumu |
|---|---|---|---|---|---|
| `doc.offer_list` | Teklif Listesi | Quotation List | 报价单 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.purchase_list` | Satın Alma Listesi | Purchase List | 采购清单 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.discovery_report` | Keşif Havuzu Raporu | Discovery Pool Report | 选品池报告 | Excel, PDF, Paylaşım sayfası | 5B · gozden_gecir |
| `doc.company` | Firma | Company | 公司 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.customer` | Müşteri | Customer | 客户 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.list_name` | Liste Adı | List Name | 清单名称 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.date` | Tarih | Date | 日期 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.prepared_by` | Hazırlayan | Prepared By | 制表人 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.approved_by` | Onaylayan | Approved By | 批准人 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.company_approval` | Firma Onayı | Company Approval | 公司确认 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.signature` | İmza / Kaşe | Signature / Stamp | 签字 / 盖章 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.page` | Sayfa {current}/{total} | Page {current}/{total} | 第 {current}/{total} 页 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.code` | Belge Kodu | Document Code | 文件编号 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.revision` | Rev | Rev | 修订版 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.version` | Sürüm | Version | 版本 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.currency` | Para Birimi | Currency | 币种 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.generated_at` | Oluşturulma Tarihi | Generated At | 生成时间 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `doc.confidential` | Ticari ve gizli | Commercial and Confidential | 商业机密 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `col.no` | No | No. | 序号 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.product_name` | Ürün Adı | Product Name | 产品名称 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.product_details` | Ürün Detayları | Product Details | 产品详情 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.variation` | Varyasyon | Variation | 规格 / 款式 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.category` | Kategori | Category | 类目 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.source` | Kaynak | Source | 来源 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.status` | Durum | Status | 状态 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.note` | Not | Note | 备注 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.quantity` | Miktar | Quantity | 数量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.unit` | Birim | Unit | 单位 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.moq` | Minimum Sipariş | Minimum Order Quantity | 最小起订量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.variant_moq` | Varyasyon MOQ | Variant MOQ | 规格起订量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.showcase_cny` | Vitrin ¥ | Showcase ¥ | 展示价（¥） | Excel, PDF, CSV, Paylaşım sayfası | 5B · gozden_gecir |
| `col.showcase_try` | Vitrin ₺ | Showcase ₺ | 展示价（土耳其里拉） | Excel, PDF, CSV, Paylaşım sayfası | 5B · gozden_gecir |
| `col.ddp_usd` | DDP $ | DDP $ | DDP价格（美元） | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.ddp_try` | DDP ₺ | DDP ₺ | DDP价格（土耳其里拉） | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.unit_price` | Birim Fiyat | Unit Price | 单价 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.total_price` | Toplam Fiyat | Total Price | 总价 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.carton_count` | Koli Sayısı | Carton Count | 箱数 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.units_per_carton` | Koli İçi | Units per Carton | 每箱数量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.cbm` | Hacim (CBM) | Volume (CBM) | 体积（CBM） | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.gross_weight` | Brüt Ağırlık | Gross Weight | 毛重 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.net_weight` | Net Ağırlık | Net Weight | 净重 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.seller` | Satıcı | Seller | 供应商 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.seller_years` | Satıcı Yılı | Seller Years | 经营年限 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.quality_score` | Kalite Sinyali | Quality Signal | 质量指标 | Excel, PDF, CSV, Paylaşım sayfası | 5B · gozden_gecir |
| `col.ship48h` | 48 Saat Sevk | 48h Dispatch | 48小时发货率 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.repeat_rate` | Tekrar Alış | Repeat Purchase | 复购率 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.sales_30d` | 30 Günlük Satış | 30-Day Sales | 近30天销量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.sales_total` | Toplam Satış | Total Sales | 累计销量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.rating` | Puan | Rating | 评分 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.review_count` | Yorum Sayısı | Review Count | 评价数 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.listed_at` | İlan Tarihi | Listed At | 上架日期 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.updated_at` | Güncellenme | Updated At | 更新时间 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.video` | Video | Video | 视频 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.cluster` | Aynı Ürün Kümesi | Same-Product Cluster | 同款分组 | Excel, PDF, CSV, Paylaşım sayfası | 5B · gozden_gecir |
| `col.completeness` | Veri Tamlığı | Data Completeness | 数据完整度 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.score` | Keşif Skoru | Discovery Score | 选品评分 | Excel, PDF, CSV, Paylaşım sayfası | 5B · gozden_gecir |
| `col.source_id` | Kaynak Kimliği | Source ID | 来源编号 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.image` | Ürün Görseli | Product Image | 产品图片 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.product_code` | Ürün Kodu | Product Code | 产品编号 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.model` | Model | Model | 型号 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.brand` | Marka | Brand | 品牌 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.material` | Malzeme | Material | 材质 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.color` | Renk | Color | 颜色 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.dimensions` | Ürün Ölçüleri | Product Dimensions | 产品尺寸 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.capacity` | Kapasite | Capacity | 容量 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.stock` | Beyan Stok | Declared Stock | 申报库存 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.origin` | Menşe | Country of Origin | 原产地 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.source_link` | Kaynak Bağlantısı | Source Link | 来源链接 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.china_shipping` | Çin İçi Nakliye | China Domestic Shipping | 中国境内运费 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.lead_time` | Üretim Termin Süresi | Production Lead Time | 生产交期 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.dispatch_time` | Sevk Süresi | Dispatch Time | 发货时效 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.payment_terms` | Ödeme Koşulları | Payment Terms | 付款条件 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.packaging` | Ambalaj Şekli | Packaging | 包装方式 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.carton_dimensions` | Koli Ölçüleri | Carton Dimensions | 外箱尺寸 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.unit_net_weight` | Birim Net Ağırlık | Unit Net Weight | 单件净重 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.custom_order` | Özel Üretim | Custom Order | 是否定制 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `col.translation_status` | Çeviri Durumu | Translation Status | 翻译状态 | Excel, PDF, CSV, Paylaşım sayfası | 5B · kesin |
| `status.preparing` | Hazırlanıyor | Preparing | 准备中 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.waiting_price` | Fiyat bekleniyor | Awaiting Price | 待报价 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.found` | Bulundu | Found | 已找到 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.not_found` | Bulunamadı | Not Found | 未找到 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.alternative_available` | Alternatif var | Alternative Available | 有替代品 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.waiting_approval` | Onay bekliyor | Awaiting Approval | 待确认 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.approved` | Onaylandı | Approved | 已确认 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.rejected` | Reddedildi | Rejected | 已拒绝 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.ready` | Siparişe hazır | Ready to Order | 可下单 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.missing_data` | Eksik veri | Missing Data | 数据不完整 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.sent` | Gönderildi | Sent | 已发送 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.archived` | Arşivlendi | Archived | 已归档 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.cancelled` | İptal edildi | Cancelled | 已取消 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.waiting_supplier` | Tedarikçi yanıtı bekleniyor | Awaiting Supplier Response | 等待供应商回复 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `status.expired` | Güncelliğini yitirdi | Expired | 已过期 | Excel, PDF, CSV, Paylaşım sayfası, Kanal metni | 5B · kesin |
| `kpi.products` | Ürün Sayısı | Product Count | 产品数量 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.ready` | Hazır Ürün | Ready Products | 可下单产品 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.missing` | Eksik Verili | Incomplete | 数据不完整 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.sources` | Kaynak Sayısı | Source Count | 来源数量 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.avg_price` | Ortalama Fiyat | Average Price | 平均价格 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.total_quantity` | Toplam Miktar | Total Quantity | 总数量 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.total_cartons` | Toplam Koli | Total Cartons | 总箱数 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.total_cbm` | Toplam CBM | Total CBM | 总体积（CBM） | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.total_gross` | Toplam Brüt Ağırlık | Total Gross Weight | 总毛重 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.video_products` | Videolu Ürün | Products with Video | 含视频产品 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.multi_variant` | Çok Varyantlı | Multi-Variant | 多规格产品 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.clusters` | Aynı Ürün Kümesi | Same-Product Clusters | 同款分组数 | Excel, PDF, Paylaşım sayfası | 5B · gozden_gecir |
| `kpi.avg_quality` | Ortalama Kalite Sinyali | Average Quality Signal | 平均质量指标 | Excel, PDF, Paylaşım sayfası | 5B · gozden_gecir |
| `kpi.avg_ship48h` | Ortalama 48 Saat Sevk | Average 48h Dispatch | 平均48小时发货率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.avg_repeat` | Ortalama Tekrar Alış | Average Repeat Purchase | 平均复购率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.added_today` | Bugün Eklenen | Added Today | 今日新增 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.updated_today` | Bugün Güncellenen | Updated Today | 今日更新 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `kpi.alternatives` | Alternatif Sayısı | Alternative Count | 替代品数量 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.subtotal` | Ara Toplam | Subtotal | 小计 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.discount` | İndirim | Discount | 折扣 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.china_shipping` | Çin İçi Nakliye | China Domestic Shipping | 中国境内运费 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.international_freight` | Uluslararası Navlun | International Freight | 国际运费 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.insurance` | Sigorta | Insurance | 保险费 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.customs` | Gümrük Gideri | Customs Cost | 清关费用 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.tax` | Vergiler | Taxes | 税费 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.other_costs` | Diğer Giderler | Other Costs | 其他费用 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.ddp_total` | DDP Toplam | DDP Total | DDP总额 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.grand_total` | GENEL TOPLAM | GRAND TOTAL | 总计 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.vat_included` | KDV DAHİL | VAT INCLUDED | 含增值税 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.vat_excluded` | KDV HARİÇ | VAT EXCLUDED | 不含增值税 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.locked_rate` | Kilitli Kur | Locked Exchange Rate | 锁定汇率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.rate_date` | Kur Tarihi | Exchange Rate Date | 汇率日期 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.rate_source` | Kur Kaynağı | Exchange Rate Source | 汇率来源 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.usd_cny` | USD/CNY Kuru | USD/CNY Rate | 美元/人民币汇率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.try_cny` | TRY/CNY Kuru | TRY/CNY Rate | 土耳其里拉/人民币汇率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.rounding` | Yuvarlama Farkı | Rounding Difference | 舍入差额 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.unit_ddp` | Birim DDP Maliyet | Unit DDP Cost | 单件DDP成本 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.vat_rate` | KDV Oranı | VAT Rate | 增值税率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.vat_amount` | KDV Tutarı | VAT Amount | 增值税额 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.exchange_rate` | Döviz Kuru | Exchange Rate | 汇率 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `total.freight_per_unit` | Birim Navlun | Freight per Unit | 单件运费 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `filter.search` | Ürünlerde ara | Search Products | 搜索产品 | Paylaşım sayfası | 5B · kesin |
| `filter.category` | Kategori filtresi | Category Filter | 类目筛选 | Paylaşım sayfası | 5B · kesin |
| `filter.status` | Durum filtresi | Status Filter | 状态筛选 | Paylaşım sayfası | 5B · kesin |
| `filter.source` | Kaynak filtresi | Source Filter | 来源筛选 | Paylaşım sayfası | 5B · kesin |
| `filter.video_only` | Yalnız videolu | With Video Only | 仅含视频 | Paylaşım sayfası | 5B · kesin |
| `filter.variants_only` | Yalnız varyantlı | With Variants Only | 仅多规格 | Paylaşım sayfası | 5B · kesin |
| `filter.date_range` | Tarih aralığı | Date Range | 日期范围 | Paylaşım sayfası | 5B · kesin |
| `filter.min_price` | En düşük fiyat | Minimum Price | 最低价格 | Paylaşım sayfası | 5B · kesin |
| `filter.max_price` | En yüksek fiyat | Maximum Price | 最高价格 | Paylaşım sayfası | 5B · kesin |
| `filter.ready_only` | Yalnız hazır | Ready Only | 仅可下单 | Paylaşım sayfası | 5B · kesin |
| `filter.completeness` | Veri tamlığı | Data Completeness | 数据完整度 | Paylaşım sayfası | 5B · kesin |
| `filter.score` | Skor aralığı | Score Range | 评分范围 | Paylaşım sayfası | 5B · kesin |
| `filter.clustered` | Kümelenmiş ürünler | Clustered Products | 已分组产品 | Paylaşım sayfası | 5B · gozden_gecir |
| `filter.same_product` | Aynı ürün | Same Product | 同款产品 | Paylaşım sayfası | 5B · gozden_gecir |
| `filter.seller_years` | Satıcı yılı | Seller Years | 经营年限 | Paylaşım sayfası | 5B · kesin |
| `filter.quality` | Kalite sinyali | Quality Signal | 质量指标 | Paylaşım sayfası | 5B · kesin |
| `filter.ship48h` | 48 saat sevk | 48h Dispatch | 48小时发货 | Paylaşım sayfası | 5B · kesin |
| `filter.repeat` | Tekrar alış | Repeat Purchase | 复购率 | Paylaşım sayfası | 5B · kesin |
| `filter.sales` | Satış adedi | Sales | 销量 | Paylaşım sayfası | 5B · kesin |
| `filter.rating` | Puan | Rating | 评分 | Paylaşım sayfası | 5B · kesin |
| `filter.newest` | En yeni | Newest | 最新 | Paylaşım sayfası | 5B · kesin |
| `filter.oldest` | En eski | Oldest | 最早 | Paylaşım sayfası | 5B · kesin |
| `filter.all` | Tümü | All | 全部 | Paylaşım sayfası | 5B · kesin |
| `filter.reset` | Filtreleri temizle | Clear Filters | 清除筛选 | Paylaşım sayfası | 5B · kesin |
| `filter.no_results` | Sonuç bulunamadı | No Results Found | 未找到结果 | Paylaşım sayfası | 5B · kesin |
| `msg.share_intro` | Merhaba, {list_name} listesini incelemeniz için paylaşıyorum. | Hello, I am sharing {list_name} for your review. | 您好，现将 {list_name} 分享给您审核。 | Kanal metni | 5B · kesin |
| `msg.product_count` | Listede {product_count} ürün bulunmaktadır. | The list contains {product_count} products. | 清单共包含 {product_count} 个产品。 | Kanal metni | 5B · kesin |
| `msg.valid_until` | Fiyatlar {valid_until} tarihine kadar geçerlidir. | Prices are valid until {valid_until}. | 价格有效期至 {valid_until}。 | Kanal metni | 5B · kesin |
| `msg.approval_request` | Lütfen listeyi inceleyip firma onayınızı iletin. | Please review the list and provide company approval. | 请审核清单并提供公司确认。 | Kanal metni | 5B · kesin |
| `msg.revision_shared` | {revision} revizyonu paylaşılmıştır. | Revision {revision} has been shared. | 已分享修订版 {revision}。 | Kanal metni | 5B · kesin |
| `msg.price_updated` | {product_name} ürünü için fiyat güncellendi. | The price for {product_name} has been updated. | 产品 {product_name} 的价格已更新。 | Kanal metni | 5B · kesin |
| `msg.alternative_found` | {product_name} için bir alternatif bulundu. | An alternative was found for {product_name}. | 已为 {product_name} 找到替代品。 | Kanal metni | 5B · kesin |
| `msg.supplier_waiting` | {product_name} için tedarikçi yanıtı bekleniyor. | Awaiting supplier response for {product_name}. | 正在等待供应商回复 {product_name}。 | Kanal metni | 5B · kesin |
| `msg.missing_data` | {product_name} kaydında eksik alanlar var: {missing_fields}. | {product_name} has missing fields: {missing_fields}. | {product_name} 存在缺失字段：{missing_fields}。 | Kanal metni | 5B · kesin |
| `msg.ready_summary` | {ready_count}/{product_count} ürün siparişe hazırdır. | {ready_count}/{product_count} products are ready to order. | {ready_count}/{product_count} 个产品可下单。 | Kanal metni | 5B · kesin |
| `msg.download_ready` | {document_name} dosyanız hazırdır. | Your {document_name} file is ready. | 您的 {document_name} 文件已生成。 | Kanal metni | 5B · kesin |
| `msg.link_expiry` | Paylaşım bağlantısı {expiry_date} tarihinde sona erer. | The share link expires on {expiry_date}. | 分享链接将于 {expiry_date} 失效。 | Kanal metni | 5B · kesin |
| `footnote.valid_days` | Bu fiyatlar {days} gün geçerlidir. | These prices are valid for {days} days. | 以上价格有效期为 {days} 天。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.rate_locked` | Hesaplamalarda {rate_date} tarihli kilitli kur kullanılmıştır. | Calculations use the locked exchange rate dated {rate_date}. | 计算采用 {rate_date} 的锁定汇率。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.ddp_estimate` | DDP değerleri tahminidir; kesin maliyet değildir. | DDP values are estimates and not final costs. | DDP金额为估算值，并非最终成本。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.vat_included` | Fiyatlara KDV dahildir. | Prices include VAT. | 价格含增值税。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.vat_excluded` | Fiyatlara KDV dahil değildir. | Prices exclude VAT. | 价格不含增值税。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.china_shipping_excluded` | Çin içi nakliye fiyata dahil değildir. | China domestic shipping is not included in the price. | 价格不含中国境内运费。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.moq` | Sipariş, belirtilen minimum miktara tabidir. | The order is subject to the stated minimum quantity. | 订单须满足所示最小起订量。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.conditional_price` | Koşullu fiyatlar standart fiyatın yerine geçmez. | Conditional prices do not replace the regular price. | 条件价格不替代常规价格。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.seller_signals` | Satıcı oranları platform sinyalleridir; bağımsız doğrulama değildir. | Seller rates are platform signals, not independent verification. | 供应商指标为平台信号，并非独立认证。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.declared_stock` | Stok, satıcının beyan ettiği miktardır. | Stock is the quantity declared by the seller. | 库存数量为供应商申报值。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.package_declared` | Ölçü ve ağırlıklar satıcı beyanıdır; sipariş öncesi teyit edilmelidir. | Dimensions and weights are seller-declared and must be confirmed before ordering. | 尺寸和重量由供应商申报，下单前须确认。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.image_reference` | Görseller yalnız referans amaçlıdır. | Images are for reference only. | 图片仅供参考。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.translation` | Çeviri bilgilendirme amaçlıdır; ticari koşullarda kaynak metin esas alınır. | The translation is for information; the source text governs commercial terms. | 译文仅供参考，商业条款以原文为准。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.custom_order` | Özel üretim ürünlerinde numune, termin ve MOQ ayrıca teyit edilmelidir. | For custom products, samples, lead time, and MOQ must be confirmed separately. | 定制产品的样品、交期和起订量须另行确认。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |
| `footnote.rounding` | Yuvarlama nedeniyle küçük toplam farkları oluşabilir. | Minor total differences may occur due to rounding. | 因四舍五入，合计金额可能存在微小差异。 | Excel, PDF, Paylaşım sayfası | 5B · kesin |

## 5. 5B'de bulunmayan görünür öğeler

Aşağıdakiler bu belgeyle yeni sözlük anahtarı hâline gelmez. Uygulama öncesinde 5B değişiklik sürecinden geçmeleri gerekir; o zamana kadar yalnız `5B'ye aday`dır.

| Aday | Görünür öğe | TR öneri | EN öneri | ZH öneri | Çıktı | Dayanak | Durum |
|---|---|---|---|---|---|---|---|
| `ADAY-22-001` | Eksik değer yer tutucusu | — | — | — | Excel, PDF, CSV, Paylaşım sayfası | Görev #22 boş alan kuralı + KT-038 anlamı | 5B'ye aday; bu paket 5B'ye eklemez |
| `ADAY-22-002` | Özgün kaynak alanı etiketi | Orijinal | Original | 原文 | Excel, PDF, Paylaşım sayfası | K55/KT-035 semantiği; kesin 5B karşılığı yok | 5B'ye aday; ZH karşılık dil uzmanı onayı ister |
| `ADAY-22-003` | Kademeli fiyat bölüm başlığı | Kademeli fiyat | Tiered pricing | 阶梯报价 | Excel, PDF, Paylaşım sayfası | 7B portal.field.tier_pricing; 5B'de yok | 5B'ye aday; yeni kesin terim açılmadı |
| `ADAY-22-004` | Kademe başlangıç miktarı | Kademe başlangıç miktarı | Tier minimum quantity | 阶梯起订数量 | Excel, PDF, Paylaşım sayfası | 7B portal.field.tier_min_quantity; 5B'de yok | 5B'ye aday |
| `ADAY-22-005` | Kademe bitiş miktarı | Kademe bitiş miktarı | Tier maximum quantity | 阶梯最高数量 | Excel, PDF, Paylaşım sayfası | 7B portal.field.tier_max_quantity; 5B'de yok | 5B'ye aday |
| `ADAY-22-006` | Kademe birim fiyatı | Kademe birim fiyatı | Tier unit price | 阶梯单价 | Excel, PDF, Paylaşım sayfası | 7B portal.field.tier_unit_price; 5B'de yok | 5B'ye aday |
| `ADAY-22-007` | Excel indir eylemi | Excel indir | Download Excel | 下载 Excel | Paylaşım sayfası | 5B'de eylem anahtarı kanıtlanmadı | 5B'ye aday; ZH/EN marka biçimi PM onayı ister |
| `ADAY-22-008` | PDF indir eylemi | PDF indir | Download PDF | 下载 PDF | Paylaşım sayfası | 5B'de eylem anahtarı kanıtlanmadı | 5B'ye aday |
| `ADAY-22-009` | CSV indir eylemi | CSV indir | Download CSV | 下载 CSV | Paylaşım sayfası | 5B'de eylem anahtarı kanıtlanmadı | 5B'ye aday |
| `ADAY-22-010` | Yazdır eylemi | Yazdır | Print | 打印 | Paylaşım sayfası | 5B'de eylem anahtarı kanıtlanmadı | 5B'ye aday |
| `ADAY-22-011` | Artan sıralama etiketi | Artan | Ascending | 升序 | Paylaşım sayfası | 5B yalnız En yeni/En eski içeriyor | 5B'ye aday |
| `ADAY-22-012` | Azalan sıralama etiketi | Azalan | Descending | 降序 | Paylaşım sayfası | 5B yalnız En yeni/En eski içeriyor | 5B'ye aday |
| `ADAY-22-013` | Finans özeti bölüm başlığı | Finans Özeti | Financial Summary | 财务汇总 | Excel, PDF, Paylaşım sayfası | 5B total.* satırları var; bölüm başlığı yok | 5B'ye aday; ZH karşılık dil uzmanı onayı ister |
| `ADAY-22-014` | Dil seçici etiketi | Dil | Language | 语言 | Paylaşım sayfası | 5B'de dil seçici anahtarı kanıtlanmadı | 5B'ye aday |

## 6. Çıktı özel tamlık kuralları

### Excel

- Görünen worksheet adı, hücre başlıkları, rozetler, KPI/finans blokları, dipnotlar, yorum/not etiketleri ve yazdırma üst/altbilgisi locale lint kapsamındadır.
- Formül adı/dosya içi teknik XML sistem etiketi değildir; hücrede görünen sonuç seçili biçimdedir.
- K55 özgün satırı üç dilde de aynıdır; çevrilmiş ürün adı ayrı alandadır.

### PDF

- Metin katmanında ve görsel render'da aynı locale sonucu aranır; kesilmiş metin “var” sayılmaz.
- Kademeli fiyat alt başlığı üç ayrı mantıksal satırdır: `ADAY-22-004`, `ADAY-22-005`, `ADAY-22-006`. Tek satıra birleştirme veya bir satırı düşürme kırmızıdır.
- ZH PDF fontu Bölüm 22C'deki cmap kapsam kapısını geçmeden yayımlanmaz.

### CSV

- İlk satır yalnız seçili dilde `col.*` başlıklarından oluşur; durum hücreleri ilgili `status.*` çevirisidir.
- Belge kromu, KPI kartı veya dipnot CSV'ye ayrı veri satırı diye eklenmez.
- UTF-8, locale delimiter ve RFC 4180 kaçış kuralları 22C'ye tabidir.

### Paylaşım sayfası

- Görsel metin kadar `title`, erişilebilir ad, alt metin, tooltip, boş durum, hata, filtre ve buton metni de kapıya girer.
- Orijinal alan allowlist'i yalnız etiketlenmiş kaynak alanıdır; çevrilebilir marka/renk/varyant bu muafiyeti kullanamaz.

### Kanal metni

- Konu/başlık, gövde, özet, durum, dipnot ve yer tutucuya giren çevrilebilir ürün alanı aynı locale'dedir.
- URL, altı haneli anahtar, ürün kodu ve ISO para kodu çevrilmez; anahtarın linkle aynı mesajda olup olmaması bu dil setinin değil paylaşım güvenlik sözleşmesinin konusudur.

## 7. Kabul

1. Görünen sistem metinlerinde yanlış locale sayısı `0`.
2. Bilinmeyen `status.*` sayısı `0`.
3. `null`/`undefined`/`NaN` görünür sızıntısı `0`.
4. K55 özgün kaynak mutasyonu `0`.
5. Üç satırlı kademeli başlık kaybı `0`.
6. ZH glif eksikliği/tofu `0`.
7. 5B dışı öğe, kabul edilene dek mutlaka `5B'ye aday` kaydı taşır.
