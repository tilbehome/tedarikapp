Ne işe yarar: 1688 ortak şemasındaki 32 alanı PARTİ 1 HAR’larında görülen yerel etiket, JSON yolu ve DOM seçicileriyle eşler.
Hangi fazda kullanılır: V3-E adaptör fikstür sözleşmesi, altın çıktı üretimi ve Görev #17 alan sözlüğü bakımında kullanılır.
Kapsam: Alibaba.com, Amazon, Tmall ve Yiwugo; Trendyol ayrı yurtiçi fiyat kaynağı adayı sütunudur.
Kanıt ilkesi: Yalnız HAR’da görülen pozitif yollar yazılmış, görünmeyen veya semantiği belirsiz alanlar “kanıtlanmadı” bırakılmıştır.
Kapsam dışı: PARTİ 2 platformları, mevcut matrisi değiştirmek, GTİP, mevzuat, gümrük vergisi/oran hesabı ve ince ekran tasarımıdır.

# Görev #19C — Fikstür alan eşleme önerileri

## 1. Kullanım notu

Bu tablo `fikstur-envanteri.md` içindeki 32 alanlık 1688 şemasını temel alır. Kanıt kodları:

- `ALI-STATE`: `alibaba.har` kayıt 175, `window.detailData`.
- `AMZ-DP`: `amazon-tr-us.har` kayıt 1.566, `https://www.amazon.com/dp/B0764HS4SL` SSR DOM/bileşen durumu.
- `TML-DOM`: `tmall.har` kayıt 14, `https://detail.tmall.com/item.htm` SSR DOM.
- `TML-DESC`: `tmall.har` kayıt 129, `https://h5api.m.tmall.com/h5/mtop.taobao.detail.getdesc/7.0/` JSONP yanıtı.
- `YWG-STATE`: `yiwugo.har` kayıt 0, `window.__INITIAL_STATE__`.
- `YWG-RATE`: `yiwugo.har` kayıt 34/37, puan ve değerlendirme sayısı GET yanıtları.
- `TRY-STATE`: `trendyol.har` kayıt 73, `window.__envoy__` ve Product/WebPage JSON-LD.

Tmall, mevcut 17A `Taobao` sütununa otomatik eşlenmemiştir. Yiwugo’nun da mevcut 17A matrisinde sütunu yoktur. Aşağıdaki Tmall/Yiwugo yolları adaptör ve fikstür önerisidir; sahte `mevcut_durum` üretmez.

## 2. Alan eşleme tablosu

| 1688 şema alanı | Alibaba.com | Amazon | Tmall | Yiwugo | Trendyol — yurtiçi aday |
|---|---|---|---|---|---|
| `platform_product_id` | `globalData.product.productId` [ALI-STATE] | `/dp/{ASIN}`, `[data-asin]`, `ASIN` ayrıntı satırı [AMZ-DP] | `data.components.componentData.detail_pic_tmallPriceDesc_1.model.itemId` [TML-DESC] | `c.productId` / `c.detail.productDetailVO.id` [YWG-STATE] | `product.id`; JSON-LD `sku` [TRY-STATE] |
| `canonical_url` | `globalData.seo.globalSeoUrl` [ALI-STATE] | `link[rel="canonical"]@href` [AMZ-DP] | İstek URL’i + `itemId`; gerçek kanonik link kanıtlanmadı [TML-DOM/TML-DESC] | Ürün istek URL’i `.../product/detail/{id}.html` [YWG-STATE] | WebPage JSON-LD `.url` [TRY-STATE] |
| `title` | `globalData.product.subject` [ALI-STATE] | `#productTitle` [AMZ-DP] | `#tbpcDetail_SkuPanelBody [class^="mainTitle--"]@title`; yerel başlık [TML-DOM] | `c.detail.productDetailVO.title` [YWG-STATE] | `product.name`; Product JSON-LD `.name` [TRY-STATE] |
| `category_path` | `globalData.seo.breadCrumb.pathList[].hrefObject.name` [ALI-STATE] | `#wayfinding-breadcrumbs_feature_div` [AMZ-DP] | kanıtlanmadı | `c.breadcrumb[].title` / `.link` [YWG-STATE] | `product.category.hierarchy`, `.categoryTree[]`; WebPage JSON-LD breadcrumb [TRY-STATE] |
| `currency` | `globalData.global.productPriceCurrencySimpleName` (`US $`) [ALI-STATE] | yardımcı bileşende `productDetails.currencySymbol` (`$`); ISO kod ana DOM’da kanıtlanmadı [AMZ-DP] | fiyat panelinde `￥`; açık ISO kod kanıtlanmadı [TML-DOM] | kanıtlanmadı; ham fiyat ölçeği/para kodu yok [YWG-STATE] | `storefront.currency`; JSON-LD `offers.priceCurrency` [TRY-STATE] |
| `base_price` | `globalData.product.price.formatLadderPrice`; sayısal kademeler [ALI-STATE] | standart `.a-price .a-offscreen` bu snapshot’ta boş; yardımcı encoded içerikte `productDetails.fullPrice` [AMZ-DP] | `#tbpcDetail_SkuPanelBody` fiyat grubu, sembol + sayı; kampanya bağlamı değişken [TML-DOM] | `c.detail.productDetailVO.sellPrice`; ham `410`, ölçek kanıtlanmadı [YWG-STATE] | `product.merchantListing.winnerVariant.price.discountedPrice.value` [TRY-STATE] |
| `price_tiers` | `globalData.product.price.productLadderPrices[].{min,max,price}` [ALI-STATE] | kanıtlanmadı | kanıtlanmadı; tek kampanya fiyatı kademe değildir | `c.detail.sdiProductsPriceList[].{startNumber,endNumber,sellPrice,conferPrice}` [YWG-STATE] | kanıtlanmadı |
| `moq` | `globalData.product.moq` [ALI-STATE] | kanıtlanmadı | kanıtlanmadı | `c.detail.productDetailVO.startNumber` [YWG-STATE] | kanıtlanmadı |
| `unit` | `globalData.product.price.unit` (`Adet`) [ALI-STATE] | yardımcı bileşende `productDetails.buyBoxPricePerUnit.displayUnitValueDescription` (`Item`) [AMZ-DP] | `数量` yalnız miktar etiketidir; satış birimi kanıtlanmadı [TML-DOM] | `c.detail.productDetailVO.metric` (`套`) [YWG-STATE] | kanıtlanmadı |
| `variant_dimensions` | `globalData.product.sku.skuAttrs[].name`; `.values[].name` [ALI-STATE] | `[id^="inline-twister-row-"]`; boyutlar `Color`, `Size` [AMZ-DP] | `#skuOptionsArea`; yerel etiket `商品规格` [TML-DOM] | `c.detail.productPropertyDimensionOneList[].specificationName`; `...DimensionTwoList[]` [YWG-STATE] | `product.variants[]`; tek fixture’da boş varyant adı olduğundan KISMİ [TRY-STATE] |
| `sku_matrix` | `sku.skuInfoMap` + `sku.skuSample[]` + `inventory.skuInventory` [ALI-STATE] | swatch metni + `data-asin`; tüm varyantların fiyat/stoku birlikte yok [AMZ-DP] | tek seçenek görüldü; tam matris kanıtlanmadı [TML-DOM] | `c.detail.productPropertySkuBoList[].{propertyValues,price,stock,picture}` [YWG-STATE] | `product.variants[]` ve `merchantListing.variants[]`; seçili `winnerVariant` [TRY-STATE] |
| `stock` | `globalData.inventory.skuInventory.{sku_id}.warehouseInventoryList[].inventoryCount` [ALI-STATE] | swatch `swatchAvailable/swatchUnavailable`; sayısal stok yok [AMZ-DP] | kanıtlanmadı | `c.detail.productPropertySkuBoList[].stock` [YWG-STATE] | `product.inStock`, `product.variants[].inStock`, `winnerVariant.quantity` [TRY-STATE] |
| `attributes` | `globalData.product.productBasicProperties[].{attrName,attrValue}` [ALI-STATE] | `#productOverview_feature_div table tr`; `#feature-bullets` [AMZ-DP] | kanıtlanmadı | `c.propertyList[].{pvalue,cvalueList}` [YWG-STATE] | `product.attributes[].key.name` / `.value.name`; JSON-LD `additionalProperty[]` [TRY-STATE] |
| `gallery_images` | `globalData.product.mediaItems[?type=='image'].imageUrl.big` [ALI-STATE] | `#landingImage@data-a-dynamic-image`; `#altImages` [AMZ-DP] | `#mainPicImageEl@src`; açıklama görselleri `detail_rich_text_1.model.text` içindeki `img@src` [TML-DOM/TML-DESC] | `c.detail.sdiProductsPicList[].picture` [YWG-STATE] | `product.images[]`; JSON-LD `.image[]` [TRY-STATE] |
| `video` | `globalData.product.mediaItems[?type=='video'].videoUrl.{quality}.videoUrl` [ALI-STATE] | `#videoCount`, `#video-outer-container`; gerçek kaynak doğal lazy yanıtına bağlı [AMZ-DP] | kanıtlanmadı | `c.detail.video` null ve `videoId` sıfır; kanıtlanmadı [YWG-STATE] | `merchantListing.merchant.videoContentId` + doğal video-content yanıtı; değer gizlendi [TRY-STATE] |
| `seller_id` | `globalData.seller.companyId`; değer gizlendi [ALI-STATE] | kanıtlanmadı | `detail_pic_tmallPriceDesc_1.model.sellerId`; değer gizlendi [TML-DESC] | `c.shop.shopId` / `.supplierId` / `.shopUrlId`; değerler gizlendi [YWG-STATE] | `product.merchantListing.merchant.id`; değer gizlendi [TRY-STATE] |
| `seller_name` | `globalData.seller.companyName` [ALI-STATE] | kanıtlanmadı | `[data-spm="shop_block"]` içindeki mağaza adı [TML-DOM] | `c.shop.shopName` [YWG-STATE] | `product.merchantListing.merchant.name` [TRY-STATE] |
| `seller_location` | `globalData.seller.companyRegisterCountry` (`CN`) [ALI-STATE] | kanıtlanmadı | kanıtlanmadı; `至 中国` teslim hedefidir, satıcı konumu değildir | `c.shop.factoryAddress`; `c.shopbooth.marketInfo` [YWG-STATE] | `merchant.cityName`, `.countryName` [TRY-STATE] |
| `seller_badges` | `globalData.seller.verifiedManufactruers`; `authCards.value.authGroupInfoList[]` [ALI-STATE] | kanıtlanmadı | mağaza adındaki `旗舰店` ve `可开发票` işareti; rozet taksonomisi KISMİ [TML-DOM] | `c.shop.shoplicenseStatus`; yerel anlamı KISMİ [YWG-STATE] | `merchant.merchantBadges[]`, `.merchantMarkers[]` [TRY-STATE] |
| `seller_scorecard` | `supplierRatingReviews`, `responseTimeText`, `supplierOnTimeDeliveryRate` [ALI-STATE] | kanıtlanmadı | `[data-spm="shop_block"]`: mağaza puanı, `好评率`, ortalama gönderim ve yanıt süresi [TML-DOM] | `c.shop.credit`, `.integrityFraction`, `.starCredit` [YWG-STATE] | `merchant.sellerScore.value` [TRY-STATE] |
| `sales_30d` | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı |
| `sales_total` | `globalData.trade.salesVolume` (`3555 satıldı`) [ALI-STATE] | kanıtlanmadı | `已售 100+` [TML-DOM] | `c.detail.sellCounts` alanı var; bu fixture’da `0`, semantik ayrıca kanıtlanmadı [YWG-STATE] | kanıtlanmadı |
| `rating` | `globalData.review.productReview.averageStar` [ALI-STATE] | `#averageCustomerReviews` [AMZ-DP] | kanıtlanmadı; görülen `4.7` mağaza puanıdır | ayrı GET `content.data.avgscore`; bu fixture’da `0` [YWG-RATE] | `product.ratingScore.averageRating`; JSON-LD `aggregateRating.ratingValue` [TRY-STATE] |
| `review_count` | `globalData.review.productReview.totalReviewCount` [ALI-STATE] | `#acrCustomerReviewText` [AMZ-DP] | kanıtlanmadı | ayrı GET `content.data.totalNum`; bu fixture’da `0` [YWG-RATE] | `product.ratingScore.commentCount` / `.totalCount`; JSON-LD aggregate count [TRY-STATE] |
| `listed_at` | kanıtlanmadı | kanıtlanmadı; `Date First Available` bu snapshot’ta bulunmadı | kanıtlanmadı | `c.detail.productDetailVO.creatTime` [YWG-STATE] | kanıtlanmadı |
| `packaging_text` | `globalData.trade.logisticInfo.{unitSize,unitWeight,productPackagingProperties}`; KISMİ [ALI-STATE] | kanıtlanmadı; ürün ölçüsü/ağırlığı paket açıklaması değildir | kanıtlanmadı | `c.detail.productPackingBo` null; kanıtlanmadı [YWG-STATE] | kanıtlanmadı |
| `units_per_carton` | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı |
| `gross_weight` | `globalData.trade.logisticInfo.unitWeight`; açık brüt koli semantiği yok [ALI-STATE] | `Item Weight`; ürün ağırlığı, brüt koli değildir [AMZ-DP] | kanıtlanmadı | `productDetailVO.weightetc` boş; kanıtlanmadı [YWG-STATE] | kanıtlanmadı |
| `carton_dimensions` | `globalData.trade.logisticInfo.unitSize`; açık koli semantiği yok [ALI-STATE] | `Product Dimensions`; ürün ölçüsü, koli değildir [AMZ-DP] | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı |
| `carton_cbm` | `unitVolume` bu fixture’da `0`; pozitif CBM kanıtlanmadı [ALI-STATE] | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı | kanıtlanmadı |
| `custom_order` | `supportFastCustomization`, `supportLightCustomization`, `productLightCustomizationList[]` [ALI-STATE] | kanıtlanmadı | kanıtlanmadı | `c.specialProduct` alanı var fakat semantiği/false değeri yeterli değil; kanıtlanmadı [YWG-STATE] | `merchantListing.displayCustomizableProductInformation`; bu fixture’da false, destek yeteneği kanıtlanmadı [TRY-STATE] |
| `lead_time` | `globalData.trade.leadTimeInfo.ladderPeriodList[].{minQuantity,maxQuantity,processPeriod}` [ALI-STATE] | kanıtlanmadı | gönderim satırı `预计明天发货｜承诺48小时内发货` [TML-DOM] | `c.detail.productDetailVO.deliveryPromise`; `3`, birim kanıtlanmadı [YWG-STATE] | `winnerVariant.rushDeliveryDuration`; birim/semantik ayrıca kanıtlanmadı [TRY-STATE] |

## 3. Fikstür üretim önerileri

1. Her fixture beklenen çıktıda `source_channel` alanını `embedded_state`, `ssr_dom`, `json_ld` veya `captured_api` olarak belirtmelidir.
2. Fiyat fixture’ı `raw_value`, `formatted_value`, `currency_evidence`, `variant_context`, `seller_context` ve `evidence_quality` alanlarını ayrı tutmalıdır.
3. Amazon fiyatı, Yiwugo fiyat ölçeği ve tüm “ürün ölçüsü ≠ koli ölçüsü” ayrımları negatif altın beklenti olarak saklanmalıdır.
4. Tmall MTOP ve Alibaba değerlendirme MTOP uçları canlı olarak yeniden çağrılmamalı; yalnız tarayıcının doğal yakaladığı temiz yanıt fixture’a eklenmelidir.
5. `kanıtlanmadı` hücreleri `0`, boş string veya `YOK` statüsüne dönüştürülmemelidir.

## 4. Açık kararlar

- PM, Tmall’ı ayrı platform mu yoksa Taobao adaptör ailesinin açıkça tanımlanmış alt türü mü sayacağını belirlemelidir; bu karar gelmeden 17A Taobao hücreleri değişmemelidir.
- PM, Yiwugo için platform matrisi sütunu açılıp açılmayacağını belirlemelidir.
- Yiwugo ham fiyatının para birimi ve ondalık ölçeği için formatter kanıtı içeren ikinci fixture gereklidir.
- Amazon fiyat alanı için standart Buy Box fiyatı dolu en az bir ek `/dp/` fixture’ı gereklidir.
