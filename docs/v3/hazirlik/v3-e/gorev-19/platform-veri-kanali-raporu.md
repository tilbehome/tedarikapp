Ne işe yarar: PARTİ 1 HAR’larında ürün verisinin hangi HTML, gömülü durum ve JSON kanallarından okunabildiğini kanıtlar.
Hangi fazda kullanılır: V3-E platform adaptörleri, fikstür altın çıktıları ve Görev #17 kanıt güncellemesinde kullanılır.
Kapsam: Alibaba.com, Amazon, Tmall ve Yiwugo ürün sayfaları; Trendyol yalnız yurtiçi fiyat kaynağı adayıdır.
Kanıt ilkesi: Yalnız temizlenmiş HAR yanıtlarında görülen alanlar yazılmış, query parametreleri ve kimlik/oturum değerleri dışarıda bırakılmıştır.
Kapsam dışı: PARTİ 2 platformları, GTİP, mevzuat, gümrük vergisi/oran hesabı ve ince ekran tasarımıdır.

# Görev #19A — Platform veri-kanalı raporu

## 1. Kapsam ve yöntem

İncelenen arşiv `har-temiz-parti1.zip` içinde beş HAR ve toplam 3.853 ağ kaydı vardır. Bulgular, HAR’daki yanıt gövdelerinin çevrimdışı incelenmesine dayanır; canlı site davranışı hakkında ek varsayım yapılmamıştır.

| HAR | Kayıt | Ana ürün kanıtı | Rol |
|---|---:|---|---|
| `alibaba.har` | 954 | Ürün detay HTML’i + `window.detailData` | Matris platformu: Alibaba.com |
| `amazon-tr-us.har` | 2.088 | Amazon US `/dp/` HTML’i; ayrıca US/TR arama ve karışık Trendyol kaydı | Matris platformu: Amazon |
| `tmall.har` | 212 | Tmall ürün detay SSR HTML’i + imzalı MTOP açıklama yanıtı | Ayrı Tmall yakalaması; Taobao hücresine otomatik eşlenmedi |
| `trendyol.har` | 520 | Trendyol ürün detay HTML’i + `window.__envoy__` + JSON-LD | Matris dışı yurtiçi fiyat kaynağı adayı |
| `yiwugo.har` | 79 | Ürün detay HTML’i + `window.__INITIAL_STATE__` | Ayrı Yiwugo yakalaması; mevcut 17A matrisinde satırı yok |

### 1.1 Kanıt referansları

Rapor içindeki köşeli referanslar şu çevrimdışı kanıtlara gider. Bütün URL’ler query parametresizdir.

| Ref | HAR / kayıt | İstek URL’i veya nesne |
|---|---|---|
| ALI-HTML | `alibaba.har` / 175 | `https://www.alibaba.com/product-detail/Fashion-Customized-Color-500ml-750ml-Food_1601766428465.html` |
| ALI-STATE | `alibaba.har` / 175 | HTML içindeki `window.detailData` |
| ALI-REV | `alibaba.har` / 289, 327–329 | `https://acs.h.alibaba.com/h5/mtop.alibaba.icbu.review.media.review/1.0/`; `https://acs.h.alibaba.com/h5/mtop.alibaba.icbu.review.new.shopreview.scatter/1.0/`; `https://acs.h.alibaba.com/h5/mtop.alibaba.icbu.screview.getaggregatescore/1.0/`; `https://acs.h.alibaba.com/h5/mtop.alibaba.icbu.review.complete.pc.productreview/1.0/` |
| ALI-LOG | `alibaba.har` / 275 | `https://www.alibaba.com/event/app/productDetail/logistics.do` |
| AMZ-DP | `amazon-tr-us.har` / 1.566 | `https://www.amazon.com/dp/B0764HS4SL` |
| AMZ-VIDEO | `amazon-tr-us.har` / 1.700, 1.701, 1.961 | `https://www.amazon.com/vap/ew/subcomponent/vseplayer`; `https://www.amazon.com/vap/ew/componentbuilder`; `https://www.amazon.com/vap/ew/subcomponent/relatedvideos` |
| AMZ-SEARCH | `amazon-tr-us.har` / 948, 1.427 ve 520 | `https://www.amazon.com/s`; `https://www.amazon.com.tr/s` |
| TML-HTML | `tmall.har` / 14 | `https://detail.tmall.com/item.htm` |
| TML-DESC | `tmall.har` / 129 | `https://h5api.m.tmall.com/h5/mtop.taobao.detail.getdesc/7.0/` |
| TML-FC | `tmall.har` / 2, 7–9, 53, 124–125, 161, 176, 195–199, 202, 205, 207, 209, 211 | `https://h5api.m.tmall.com/h5/mtop.alibaba.fc.api.maoxland.containerfacade.singleview/1.0/` |
| YWG-HTML | `yiwugo.har` / 0 | `https://www.yiwugo.com/product/detail/983676960.html`; HTML içindeki `window.__INITIAL_STATE__` |
| YWG-FREIGHT | `yiwugo.har` / 29, 57 | `https://www.yiwugo.com/api/product/getFreightV2.htm` |
| YWG-RATE | `yiwugo.har` / 34, 37 | `https://www.yiwugo.com/api/api/avgRateScore/983676960.html`; `https://www.yiwugo.com/api/product/getProductRateCount.htm` |
| TRY-HTML | `trendyol.har` / 73 | `https://www.trendyol.com/tilbe-home/chopper-rendeleme-13-parca-hazneli-sebze-dograyici-dicer-dilimleyici-kesici-cok-fonksiyonlu-seti-p-104149336` |
| TRY-STATE | `trendyol.har` / 73 | HTML içindeki `window.__envoy__` ve `application/ld+json` Product/WebPage nesneleri |
| TRY-VIDEO | `trendyol.har` / 239 | `https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/video-content/{video_content_id}`; yol kimliği gizlendi |
| TRY-REV | `trendyol.har` / 309 | `https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/review-read/product-reviews/detailed` |

Kayıt numarası yalnız bu teslimdeki HAR dizinini belirtir; adaptör sözleşmesinin parçası değildir.

## 2. Alibaba.com

### 2.1 Render mimarisi ve ana kaynak

Ürün detay cevabı SSR HTML ile gelir ve yaklaşık 304 KB’lık yapılandırılmış ürün durumu `window.detailData` atamasına gömülüdür. Ana nesnenin üst düzeyi `devData`, `globalData`, `hierarchy`, `metaData`, `nodeMap`; kalıcı ürün alanlarının çoğu `globalData` altındadır. Temel adaptörün bu nesneyi birincil kaynak seçmesi gerekir. [ALI-HTML] [ALI-STATE]

Ayrı ağ çağrıları değerlendirme, mağaza puanı ve lojistik için vardır. İncelenen sayfada başlık, fiyat, MOQ, varyant, stok, satıcı, puan, satış, medya ve termin bilgilerinin tamamı ayrı çağrı beklemeden gömülü nesnede bulunur. Bu nedenle canlı MTOP çağrısı ana çıkarım yoluna konmamalıdır.

### 2.2 Ana nesne yolları

| Ortak alan | JSON yolu | HAR’da görülen kısa örnek / semantik |
|---|---|---|
| Ürün kimliği | `globalData.product.productId` | Sayısal ürün kimliği |
| Kanonik URL | `globalData.seo.globalSeoUrl` ve sayfa URL’i | Ürün detay URL’i |
| Başlık | `globalData.product.subject` | Yerelleştirilmiş ürün başlığı |
| Kategori/kırıntı | `globalData.seo.breadCrumb.pathList[].hrefObject.name` | İki düzeyli kırıntı örneği |
| Para birimi | `globalData.global.productPriceCurrencySimpleName` | `US $` |
| Görünür fiyat | `globalData.product.price.formatLadderPrice` | `$0,98-1,98` |
| Kademe | `globalData.product.price.productLadderPrices[].min`, `.max`, `.price` | İlk eşik 2; yapı birden çok kademe içeriyor |
| MOQ | `globalData.product.moq` | `2` |
| Birim | `globalData.product.price.unit` | `Adet` |
| Varyant boyutları | `globalData.product.sku.skuAttrs[].name`, `.values[].name` | `Renk`, `Kapasite` |
| SKU bağı | `globalData.product.sku.skuInfoMap` ve `globalData.product.sku.skuSample[]` | Varyant bileşimi → SKU kimliği; örnek fiyat ayrıca tutuluyor |
| Stok | `globalData.inventory.skuInventory.{sku_id}.warehouseInventoryList[].inventoryCount` | SKU ve depo bazlı sayı |
| Özellikler | `globalData.product.productBasicProperties[].attrName`, `.attrValue` | Ad/değer çiftleri |
| Galeri | `globalData.product.mediaItems[]` içinden `type == "image"`; `.imageUrl.big` | Altı görsel öğesi |
| Video | `globalData.product.mediaItems[]` içinden `type == "video"`; `.videoUrl.{quality}.videoUrl` | Bir video, birden çok kalite |
| Satıcı kimliği | `globalData.seller.companyId` | Değer rapora alınmadı |
| Satıcı adı/konumu | `globalData.seller.companyName`, `.companyRegisterCountry` | Firma adı mevcut; ülke `CN` |
| Rozet/doğrulama | `globalData.seller.verifiedManufactruers`, `.authCards.value.authGroupInfoList[]` | Doğrulanmış üretici ve yetenek etiketleri |
| Satıcı karnesi | `globalData.seller.supplierRatingReviews`, `.responseTimeText`, `.supplierOnTimeDeliveryRate` | Puan, yanıt süresi ve zamanında teslim oranı |
| Satış toplamı | `globalData.trade.salesVolume` | `3555 satıldı` |
| Ürün puanı/sayısı | `globalData.review.productReview.averageStar`, `.totalReviewCount` | `4.7`, `4` |
| Paket/ölçü vekili | `globalData.trade.logisticInfo.unitSize`, `.unitWeight`, `.unitVolume` | Ölçü ve ağırlık dolu; hacim `0`, bu yüzden CBM kanıtı değildir |
| Özelleştirme | `globalData.product.supportFastCustomization`, `.supportLightCustomization`, `.productLightCustomizationList[]` | Özelleştirme bayrakları ve özelleştirme MOQ’u |
| Termin | `globalData.trade.leadTimeInfo.ladderPeriodList[].minQuantity`, `.maxQuantity`, `.processPeriod` | Adet kademesine bağlı işlem günü |

`quantityUnit` sayısal bir platform iç kodudur; kullanıcı birimi için `product.price.unit` tercih edilmelidir. `skuSample[].price` örnekte numune fiyatıdır ve ana toptan kademe fiyatı yerine yazılmamalıdır. `unitSize` ile `unitWeight` açıkça “koli” olarak adlandırılmadığı için koli ölçüsü/brüt koli ağırlığına yükseltilmemelidir. [ALI-STATE]

### 2.3 İmzalı/korumalı uçlar ve çıkarım önerisi

`acs.h.alibaba.com/h5/mtop...` değerlendirme uçlarında `appKey`, zaman ve `sign` parametreleri bulunur. İmza/oturum değerleri rapora alınmamıştır. Bu uçlar ürün ve mağaza değerlendirmelerinin ayrıntısını tamamlayabilir; ancak canlı tekrar çağrı, imza üretme veya oturum taklidi adaptörün zorunlu yolu olmamalıdır. [ALI-REV]

`productDetail/logistics.do` POST isteği lojistik tamamlayıcısıdır; temiz HAR’da POST gövdesi yoktur. Bu nedenle parametre sözleşmesi kanıtlanmadı. [ALI-LOG]

Önerilen sıra:

1. `window.detailData` → temel ürün, fiyat, SKU, stok, satıcı ve medya.
2. Mevcut HTML/DOM → gömülü nesne bulunamazsa sınırlı yedek.
3. HAR’da zaten yakalanmış MTOP yanıtı → yalnız değerlendirme altın fikstürü.
4. Canlı imzalı MTOP tekrar çağrısı → kalıcı RET; kullanıcı tarayıcısında doğal olarak oluşmuş yanıt dışında zorunlu bağımlılık yapılmaz.

## 3. Amazon

### 3.1 Render mimarisi ve ana kaynak

Ana kanıt Amazon US `/dp/B0764HS4SL` detay cevabıdır. Sayfa tek ve temiz bir küresel ürün nesnesi sunmuyor; ürün verisi SSR DOM, `data-a-state` bileşen durumları, `P.register(...)` betikleri ve bazı HTML-encoded bileşen içerikleri arasında dağılmıştır. Ana adaptör DOM’u okumalı, bileşen JSON’unu yalnız alan bazlı tamamlayıcı olarak kullanmalıdır. [AMZ-DP]

İncelenen snapshot’ta standart fiyat kapsayıcıları (`#corePrice_desktop`, `#desktop_unifiedPrice`) boştu. Aynı HTML içindeki yardımcı bir bileşenin encoded içeriğinde `productDetails.fullPrice`, `currencySymbol`, `offer.productImages` ve değerlendirme özeti vardı. Bu yardımcı bileşen reklam/yerleşim bağlamına ait olduğundan kararlı ana fiyat kaynağı sayılmamalıdır. Sonuç: Amazon fiyatı bu tek detay kanıtında **DEĞİŞKEN**, para birimi ise **KISMİ** kanıttır.

### 3.2 Detay sayfası alanları

| Ortak alan | DOM / bileşen yolu | HAR’da görülen kısa örnek / semantik |
|---|---|---|
| Ürün kimliği | URL’de `/dp/{ASIN}`; öğelerde `[data-asin]`; ayrıntı tablosunda `ASIN` | `B0764HS4SL` |
| Kanonik URL | `link[rel="canonical"]@href` | Ürün slug’ı + `/dp/B0764HS4SL` |
| Başlık | `#productTitle` | Ürün başlığı |
| Kategori/kırıntı | `#wayfinding-breadcrumbs_feature_div` | Altı düzeyli kırıntı |
| Fiyat | Standartta `#corePrice_desktop .a-price .a-offscreen`; bu snapshot’ta boş. Yardımcı encoded içerikte `productDetails.fullPrice` | Yardımcı içerikte `49.99`; ana DOM’dan doğrulanmadı |
| Para birimi | `.a-price-symbol` veya yardımcı encoded içerikte `productDetails.currencySymbol` | `$`; açık ISO kodu ana DOM’da kanıtlanmadı |
| Varyant boyutları | `[id^="inline-twister-row-"]`; boyut adı `[id^="inline-twister-expanded-dimension-text-"]` | `Color`, `Size` |
| Varyant/SKU bağı | Varyant swatch öğelerinde `data-asin`; metin varyant değeridir | Seçenek → ASIN; fiyat her swatch’ta yok |
| Bulunabilirlik | Swatch `data-csa-c-content-id` değerinde available/unavailable; ayrıca `#availability` | Seçenek bazlı kısmi kanıt |
| Özellikler | `#productOverview_feature_div table tr`; `#feature-bullets` | Marka, renk, ürün ölçüsü vb. |
| Galeri | `#landingImage`; `#altImages`; `#landingImage@data-a-dynamic-image` | Ana görsel ve alternatifler |
| Video | `#videoCount`, `#video-outer-container` | `19 VIDEOS` |
| Puan | `#averageCustomerReviews` | `4.5 out of 5 stars` |
| Değerlendirme sayısı | `#acrCustomerReviewText` | `(95,639)` |
| Ürün ölçüsü | `#productOverview_feature_div table tr` içinde `Product Dimensions` | `8"L x 3"W x 4.48"H`; koli ölçüsü değildir |
| Ürün ağırlığı | Aynı tabloda `Item Weight` | `0.99 kg`; brüt paket ağırlığı değildir |

Satıcı kimliği/adı, yayın tarihi, koli içi adet, gerçek koli ölçüsü, CBM, MOQ, kademe, özel üretim ve tedarik terminini bu `/dp/` snapshot’ı güvenilir biçimde göstermedi. Bu alanlar için “YOK” önerisi üretilmedi; kanıtlanmadı kaldı.

### 3.3 Video/lazy uçlar ve çıkarım önerisi

Video öğeleri için `vap/ew/...` ve `dram/renderLazyLoaded` POST çağrıları görülür. Bunlar tarayıcı bileşen bağlamına bağlıdır ve temiz HAR’da POST gövdeleri yoktur. Video varlığı DOM’dan okunabilir; gerçek video URL’si gerekiyorsa yalnız kullanıcı sayfayı açtığında doğal olarak yakalanmış yanıt kullanılmalıdır. [AMZ-VIDEO]

Önerilen sıra:

1. `/dp/` DOM → ASIN, kanonik URL, başlık, kırıntı, varyant, bulunabilirlik, özellik, görsel, puan ve değerlendirme sayısı.
2. Sayfa içi `data-a-state` / `P.register` → DOM’da bulunmayan varyant bağlantıları için kontrollü tamamlayıcı.
3. Encoded yardımcı bileşen → yalnız kanıt kalitesi `KISMİ`; ana fiyat kaynağı yapılmaz.
4. Lazy POST yanıtı → yalnız yakalanmış fikstürde video ayrıntısı; doğrudan yeniden çağrı yok.

### 3.4 Arama sonucundan yakalanabilir alanlar

Amazon arama sayfaları detay kanıtı yerine kullanılmamıştır. US arama HTML’lerinde `div[data-component-type="s-search-result"][data-asin]` kartları bulundu. Karttan şu alanlar yakalanabilir: `data-asin`, `h2` başlık, varsa `.a-price .a-offscreen`, `.a-icon-alt` puan, değerlendirme bağlantısı sayısı ve `img.s-image`. Fiyat her kartta yoktur; örnek US sayfalarından birinde 51 kartın yalnız 3’ünde bu seçiciyle fiyat yakalandı. [AMZ-SEARCH]

TR arama cevabında aynı kart seçicisiyle pozitif kart kanıtı çıkmadı. Bu, Amazon TR’de alanın bulunmadığı anlamına gelmez; yalnız bu snapshot kanıt üretmedi.

## 4. Tmall

### 4.1 Render mimarisi ve ana kaynak

Tmall ürün detayı SSR DOM’a ürün başlığı, görünür fiyat, satış etiketi, satıcı kartı, varyant ve gönderim taahhüdünü basıyor. Tek bir tam ürün durum nesnesi kanıtlanmadı. Ana adaptör `#SkuPanel_tbpcDetail_ssr2025`, `#tbpcDetail_SkuPanelBody`, `#skuOptionsArea`, `#mainPicImageEl` ve `[data-spm="shop_block"]` çevresindeki DOM’u okumalıdır. [TML-HTML]

Açıklama görselleri ayrıca imzalı `mtop.taobao.detail.getdesc` JSONP yanıtında yapılandırılmıştır. `mtop.alibaba.fc.api.maoxland.containerfacade.singleview` çağrılarının incelenen yanıtları kampanya/popup, görünürlük ve davranış stratejileri içerir; ana ürün verisi değildir. [TML-DESC] [TML-FC]

### 4.2 Alan yolları

| Ortak alan | DOM / JSON yolu | HAR’da görülen kısa örnek / semantik |
|---|---|---|
| Ürün kimliği | `TML-DESC data.components.componentData.detail_pic_tmallPriceDesc_1.model.itemId` | Değer rapora alınmadı |
| Başlık | `#tbpcDetail_SkuPanelBody [class^="mainTitle--"]@title` | Çince ürün başlığı |
| Fiyat/para birimi | `#tbpcDetail_SkuPanelBody` içindeki fiyat grubunda sembol ve sayı | `￥ 10.01` |
| Satış toplamı | Aynı panelde `已售` etiketi | `已售 100+` |
| Varyant boyutu | `#skuOptionsArea`; yerel etiket `商品规格` | Ürün spesifikasyonu |
| Varyant değeri | `#skuOptionsArea span[title]` | Tek görünen seçenek |
| Satıcı adı | `[data-spm="shop_block"]` içindeki mağaza adı | Değer rapora alınmadı |
| Satıcı karnesi | `[data-spm="shop_block"]` metni | `4.7`, `好评率83%`, ortalama gönderim/yanıt süreleri |
| Termin | `#tbpcDetail_SkuPanelBody` gönderim satırı | `预计明天发货｜承诺48小时内发货` |
| Ana görsel | `#mainPicImageEl@src` | Görsel URL’i |
| Açıklama galerisi | `TML-DESC data.components.componentData.detail_rich_text_1.model.text` içindeki `img@src` | Birden çok açıklama görseli |

Görünür fiyat kampanya bandıyla birlikte sunuluyor; normal fiyat/kampanya fiyatı ayrımı için ek sayfa tipi gerekir. Tek seçenekli sayfa, tam SKU × fiyat × stok matrisi kanıtlamaz. Ürün puanı/değerlendirme sayısı, stok sayısı, paket bilgisi ve video bu HAR’dan doğrulanmadı.

### 4.3 İmzalı MTOP notu ve çıkarım önerisi

Tmall MTOP çağrılarında zaman, `appKey`, `sign` ve ek anti-bot parametre adları vardır; hiçbir değer rapora alınmamıştır. `getdesc` ürün açıklamasını tamamlar fakat başlık/fiyat/varyant için zorunlu değildir. [TML-DESC]

Önerilen sıra:

1. SSR DOM → başlık, fiyat, satış etiketi, satıcı kartı, varyant, gönderim ve ana görsel.
2. Tarayıcının doğal olarak aldığı `getdesc` yanıtı → açıklama görselleri ve ürün kimliği tamamlayıcısı.
3. Canlı MTOP imza üretimi veya çağrıyı tarayıcı dışından yineleme → kalıcı RET.

Tmall kanıtı, alan ve alan adı benzerliği olsa da mevcut 17A `Taobao` hücrelerinin kanıtı sayılmadı. PM ayrı platform satırı açmaya veya Tmall’ı Taobao adaptör ailesinin doğrulanmış alt türü ilan etmeye karar verene kadar bu ayrım korunmalıdır.

## 5. Yiwugo

### 5.1 Render mimarisi ve ana kaynak

Yiwugo ürün HTML’i `window.__INITIAL_STATE__` içinde yaklaşık 20 KB yapılandırılmış durum taşır. Ürün, fiyat, MOQ, varyant, stok, satıcı ve kırıntı verilerinin ana kaynağı bu nesnedir. Puan, değerlendirme sayısı ve navlun için ayrı, imzasız GET uçları görülür. [YWG-HTML] [YWG-FREIGHT] [YWG-RATE]

### 5.2 Ana nesne yolları

| Ortak alan | JSON yolu | HAR’da görülen kısa örnek / semantik |
|---|---|---|
| Ürün kimliği | `c.productId` veya `c.detail.productDetailVO.id` | `983676960` |
| Başlık | `c.detail.productDetailVO.title` | Çince ürün başlığı |
| Kırıntı | `c.breadcrumb[].title`, `.link` | Altı elemanlı kırıntı |
| Görünür fiyat | `c.detail.productDetailVO.sellPrice` | Ham değer `410`; para ölçeği kanıtlanmadı |
| Kademe yapısı | `c.detail.sdiProductsPriceList[].startNumber`, `.endNumber`, `.sellPrice`, `.conferPrice` | Bir satırlı kademe yapısı |
| MOQ | `c.detail.productDetailVO.startNumber` | `240` |
| Birim | `c.detail.productDetailVO.metric` | `套` |
| Varyant boyutu | `c.detail.productPropertyDimensionOneList[].specificationName` | `颜色` |
| Varyant değeri | `c.detail.productPropertyDimensionTwoList[].specificationName` | Tek değer |
| SKU bağı/stok | `c.detail.productPropertySkuBoList[].propertyValues`, `.price`, `.stock`, `.picture` | Varyant → fiyat/stok/görsel |
| Özellikler | `c.propertyList[].pvalue`, `.cvalueList[]` | Yerel özellik/değer |
| Galeri | `c.detail.sdiProductsPicList[].picture` | Dört öğe |
| Satıcı kimliği | `c.shop.shopId`, `.supplierId`, `.shopUrlId` | Değerler rapora alınmadı |
| Satıcı adı | `c.shop.shopName` | Firma adı mevcut |
| Satıcı konumu | `c.shop.factoryAddress`, `c.shopbooth.marketInfo` | Fabrika/pazar konumu metni mevcut |
| Satıcı karnesi | `c.shop.credit`, `.integrityFraction`, `.starCredit`, `.shoplicenseStatus` | Birden çok skor/işaret |
| Yayın tarihi | `c.detail.productDetailVO.creatTime` | Platform tarih kodu |
| Termin | `c.detail.productDetailVO.deliveryPromise` | `3`; birimin gün olduğu ayrıca kanıtlanmadı |
| Puan | `YWG-RATE content.data.avgscore` | Bu üründe `0`; “platform alanı yok” anlamına gelmez |
| Değerlendirme sayısı | `YWG-RATE content.data.totalNum` | Bu üründe `0` |
| Navlun/ağırlık | `YWG-FREIGHT content.data.freight`, `.weightInfo` | Navlun `0.00`; ağırlık metni boş |

`productDetailVO.videoId` ve `c.detail.video` bu fixture’da video sağlamadı; `c.detail.productPackingBo` null’dır. Bu tek örnekten video veya paketleme için “YOK” sonucu çıkarılmadı. `sellPrice=410` için para birimi/ondalık ölçeği gömülü nesnede açık değil; formatter kanıtı gelene kadar `4.10 CNY` gibi dönüşüm yapılmamalıdır.

### 5.3 API koruması ve çıkarım önerisi

İncelenen puan/navlun uçlarında MTOP benzeri imza parametresi görülmedi. Yine de ana ürün çıkarımı bu uçlara bağımlı değildir.

Önerilen sıra:

1. `window.__INITIAL_STATE__` → ürün, fiyat ham değeri, MOQ, birim, varyant, stok, satıcı ve galeri.
2. Doğal sayfa yükünde alınmış puan/değerlendirme ve navlun yanıtları → tamamlayıcı.
3. Para formatter’ı ve ölçek kanıtlanana kadar fiyatı ham değer + `kanıtlanmadı` ölçekle sakla.

Yiwugo mevcut 17A matrisinde platform sütunu değildir; bu nedenle rapor Yiwugo verisini herhangi bir mevcut hücreye taşımamıştır.

## 6. Trendyol — yurtiçi fiyat kaynağı adayı

Trendyol, Görev #17 platform matrisi kapsamına sokulmamıştır. Buradaki amaç İthalat Avantajı/17D için yurtiçi net fiyat snapshot’ının hangi kanaldan güvenilir alınabileceğini belirlemektir.

### 6.1 Render mimarisi ve ana kaynak

Ürün detay HTML’inde hem SSR DOM hem iki yapılandırılmış kaynak vardır:

- `window.__envoy__.product`: fiyat, satıcı, varyant, stok, kategori, görsel, puan ve özellikleri taşır.
- `application/ld+json` türündeki Product ve WebPage: başlık, SKU, marka, offer, para birimi, bulunabilirlik, görseller, özellikler, puan ve kanonik URL için arama motoru uyumlu yedektir.

`window.__PRODUCT_DETAIL__` yalnız sürüm/teknik işaret taşır; ürün verisi kaynağı değildir. [TRY-HTML] [TRY-STATE]

### 6.2 Yurtiçi fiyat/satıcı/varyant yolları

| İhtiyaç | Birincil yol | Yedek / doğrulama |
|---|---|---|
| Ürün kimliği | `window.__envoy__.product.id` | Product JSON-LD `.sku` |
| Kanonik URL | WebPage JSON-LD `.url` | İstek URL’i |
| Başlık | `window.__envoy__.product.name` | Product JSON-LD `.name` |
| Kategori | `window.__envoy__.product.category.hierarchy` ve `.categoryTree[]` | WebPage JSON-LD `.breadcrumb.itemListElement[]` |
| Para birimi | `window.__envoy__.storefront.currency` | Product JSON-LD `.offers.priceCurrency` |
| Yurtiçi brüt fiyat | `window.__envoy__.product.merchantListing.winnerVariant.price.discountedPrice.value` | Product JSON-LD `.offers.price` |
| Liste/orijinal fiyat | `...winnerVariant.price.originalPrice.value` | Yoksa null; indirim uydurulmaz |
| Satıcı kimliği/adı | `...merchantListing.merchant.id`, `.name` | Kimlik değeri rapora alınmadı |
| Satıcı konumu | `...merchantListing.merchant.cityName`, `.countryName` | — |
| Satıcı karnesi | `...merchantListing.merchant.sellerScore.value`, `.merchantBadges[]` | — |
| Varyantlar | `window.__envoy__.product.variants[]` | `...merchantListing.variants[]` |
| Varyant fiyatı/stok | `product.variants[].price.value`, `.inStock`; seçili SKU’da `winnerVariant.quantity` | Product JSON-LD `.offers.availability` yalnız ürün düzeyi yedek |
| Özellikler | `window.__envoy__.product.attributes[].key.name`, `.value.name` | Product JSON-LD `.additionalProperty[]` |
| Görseller | `window.__envoy__.product.images[]` | Product JSON-LD `.image[]` |
| Video varlığı | `...merchantListing.merchant.videoContentId` | Doğal sayfa çağrısı `TRY-VIDEO`; video kimliği çıktıya taşınmaz |
| Puan/sayım | `window.__envoy__.product.ratingScore.averageRating`, `.commentCount`, `.totalCount` | Product JSON-LD `.aggregateRating` |
| Teslimat vekili | `...winnerVariant.rushDeliveryDuration` | Semantik/saat birimi fixture’da ayrıca kanıtlanmadı |

17D açısından `discountedPrice.value` yurtiçi **brüt** fiyat snapshot’ıdır. NET’e dönüşüm bu raporun işi değildir; 17D’de ürünün gerçek KDV oranı uygulanmalı, veri yoksa varsayım açıkça kaydedilmelidir. Satıcı ve seçili varyant bağlamı fiyatla aynı snapshot’ta saklanmalıdır; farklı satıcı veya varyantın fiyatı birleştirilmemelidir.

### 6.3 Ayrı değerlendirme çağrısı

`product-reviews/detailed` değerlendirme ayrıntısını tamamlar. Yurtiçi fiyat defteri için zorunlu değildir; puan ve sayım zaten gömülü durumda vardır. [TRY-REV]

## 7. Çapraz platform çıkarım ilkeleri

| İlke | Uygulama |
|---|---|
| Gömülü durum önceliği | Alibaba/Yiwugo/Trendyol’da tam sayfa yükünde gelen durum nesnesi temel kaynaktır. |
| DOM önceliği | Amazon ve Tmall’da kullanıcıya gösterilen SSR DOM temel kaynaktır. |
| İmzalı API sınırı | MTOP imzası üretilmez; yalnız tarayıcının doğal olarak aldığı, temizlenmiş yanıt fikstüre alınır. |
| Alan kanıt kalitesi | Doğrudan yapılandırılmış yol `TAM`; DOM’dan/yardımcı bileşenden türetilen veya bağlama göre kaybolan alan `KISMİ/DEĞİŞKEN`; tek örnekte görünmeme `YOK` değildir. |
| Fiyat bağlamı | Fiyat; para birimi, varyant, satıcı, bölge/dil ve yakalama tarihiyle birlikte snapshot’tır. |
| Paket lojistiği | Ürün ölçüsü/ağırlığı “koli ölçüsü/brüt koli ağırlığı” diye yükseltilmez. Açık koli semantiği yoksa `kanıtlanmadı` kalır. |
| Gizlilik | Cookie, oturum, imza, token, anti-bot ve kişi/hesap kimliği değerleri çıktıya taşınmaz. |

## 8. Sonuç

PARTİ 1’de ana çıkarım kanalı Alibaba için `window.detailData`, Amazon için `/dp/` DOM’u, Tmall için SSR SKU paneli, Yiwugo için `window.__INITIAL_STATE__`, Trendyol için `window.__envoy__` olarak kapandı. Amazon fiyatı ile Yiwugo fiyat ölçeği özellikle belirsiz işaretlendi. Tmall ve Yiwugo’nun mevcut Görev #17 matrisinde satırı olmadığı doğrulandı; bu iki kanıt hiçbir mevcut platform hücresine varsayımla yazılmadı.
