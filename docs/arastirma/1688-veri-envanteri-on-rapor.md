# 1688 Ürün Detay Sayfası — Tam Veri Envanteri (Ön Rapor / Doğrulanacak Taslak)

**Tarih:** 16 Ağustos 2026
**Amaç:** tedarikapp parser modülü tasarımı için 1688 ürün sayfasının veri haritası
**Kapsam:** Ek kapsam 12 (video detayı), 13 (tüm veri envanteri), 14 (sayfa dışı XHR)

---

## ⚠️ DURUM NOTU — BU RAPOR NASIL ÜRETİLDİ

Bu oturumda **Claude in Chrome tarayıcı köprüsü koptu**, açık sekme de zaten 1688 anasayfasıydı (ürün detay sayfası değil). Sunucu tarafından `detail.1688.com` doğrudan çekilemiyor (anti-bot + JS-render → boş dönüyor).

Bu yüzden rapor **canlı sayfa okumasıyla değil**, belgelenmiş kaynaklardan üretildi:

| Kaynak tipi | Ne verdi | Güvenilirlik |
|---|---|---|
| Açık kaynak parser'ların **gerçek kod satırları** (11 GitHub reposu) | JSON yolları, global değişken adları | **En yüksek** — kod birebir okundu |
| Ticari API dokümanları (Onebound, Parse.bot, Apify) | "Hangi veri var" envanteri | Orta — alan adları kendi normalize şemaları, ham sayfa yollarıyla aynı değil |
| Reverse-engineering repoları + Çince teknik makaleler | mtop endpoint'leri, imza mekanizması | Yüksek — canlı network capture kanıtlı |

**Hiçbir satır tahmin değildir.** Kanıt bulunamayan her madde açıkça `BULUNAMADI` işaretlidir. Her satırda güven seviyesi vardır:

- **YÜKSEK** = birden fazla bağımsız projenin kodunda aynı yol/alan görüldü
- **ORTA** = tek kaynakta veya sadece dokümanda geçiyor
- **BULUNAMADI** = kanıt yok

**Yapılacak:** Chrome bağlantısı gelince 2–3 farklı üründe canlı doğrulama (Görev #5). O zamana kadar bu rapor **taslak** statüsündedir.

---

# BÖLÜM A — PARSER İÇİN KRİTİK ALANLAR

## A.0 Önce şunu bil: veri nerede duruyor?

### Masaüstü sayfa (`detail.1688.com/offer/{id}.html`)

Veri, sayfa HTML'i içinde bir `<script>` bloğunda, **IIFE formunda** duruyor:

```js
window.context = (function(b,d){ ... })(window.contextPath, { ...ASIL VERİ... });
```

Parser'ın yapması gereken (kanıtlı çıkarma tekniği):

```
başlangıç işareti: 'window.context=(function(b,d){'
bitiş işareti:     '})(window.contextPath,'
```

> Kaynak: `jiyun/1688 → alibaba_parser.py`, `mohamadzayyat/1688_new_scrapper → offerContext.js`

### 🔴 KRİTİK UYARI: Bu JSON DEĞİL

Çıkan blok **JavaScript obje literali**dir, geçerli JSON değil. Bazı anahtarlar tırnaksız sayısal SKU ID'leridir:

```js
{ 5710481973202: 0.25 }   // ← JSON.parse / json.loads BUNDA PATLAR
```

**Zorunlu çözüm:** toleranslı parser kullan.
- JS tarafı: `JSON5` + sayısal anahtarları tırnaklayan ön işlem (`quoteNumericObjectKeys()`)
- PHP tarafı: önce `json_decode`, başarısızsa regex ile sayısal anahtarları tırnaklayıp tekrar dene

> Kaynak: `mohamadzayyat/1688_new_scrapper → offerContext.js` (JSON5), `jiyun/1688` (demjson3 fallback)

### Mobil sayfa (`m.1688.com/offer/{id}.html`)

**Tamamen farklı değişken ve farklı yapı:**

```js
window.__INIT_DATA__ = { globalData: {...}, data: {...} }
```

Aynı parser ikisine çalışmaz. > Kaynak: `mohamadzayyat → offerContext.js parseMobileOfferInitFromHtml()`

### Ek global: satıcı kimlikleri

```js
window.FE_GLOBALS = { offerLoginId, loginId, memberId }
```

> Kaynak: `superjack2050/1688-cli → src/commands/offer.ts`

---

## A.1 `window.context.result` — ÜÇ PARALEL DAL VAR (parser tasarımının kalbi)

Aynı bilgi (offerId, başlık, resim) **üç ayrı dalda, farklı adlarla** duruyor. Hangisinin "kanonik" olduğu net değil — muhtemelen sayfa render motorunun farklı nesilleri arasındaki köprü katmanı.

| Dal | Ne taşır | Örnek yol |
|---|---|---|
| `result.data.<modül>.fields` | Widget/React bileşen bazlı | `data.productTitle.fields.title` |
| `result.data.Root.fields.dataJson` | Eski/ham veri modeli (mobil ile paylaşılan) | `...dataJson.skuModel.skuInfoMapOriginal` |
| `result.global.globalData.model` | tradeModel / sellerModel / offerTitleModel | `...model.tradeModel.offerId` |

### 👉 Parser tasarım kuralı (İNCELENEN 11 PROJENİN HEPSİ BUNU YAPIYOR)

**Tek yola asla güvenme. Her alan için sıralı fallback zinciri yaz.**

```php
// Örnek: başlık
$title = $ctx['result']['data']['productTitle']['fields']['title']
      ?? $ctx['result']['data']['gallery']['fields']['subject']
      ?? $ctx['result']['data']['Root']['fields']['dataJson']['tempModel']['offerTitle']
      ?? domFallback('div.title-content h1');
```

---

## A.2 Kritik alan tablosu (JSON yolu + örnek + güven)

> Kısaltma: `CTX` = `window.context.result`
> `ROOT` = `CTX.data.Root.fields.dataJson`

### Kimlik ve başlık

| Alan | JSON yolu | Örnek | Güven |
|---|---|---|---|
| offerId | URL'den: `detail.1688.com/offer/{ID}.html` | `671336876454` | YÜKSEK |
| offerId (JSON) | `CTX.global.globalData.model.tradeModel.offerId` | `671336876454` | YÜKSEK |
| offerId (alt) | `ROOT.tempModel.offerId` | — | YÜKSEK |
| Başlık | `CTX.data.productTitle.fields.title` | `"2026新款..."` | YÜKSEK |
| Başlık (alt 1) | `CTX.data.gallery.fields.subject` | — | YÜKSEK |
| Başlık (alt 2) | `ROOT.tempModel.offerTitle` | — | YÜKSEK |
| Başlık (DOM fallback) | `div.title-content h1` / `h1.title-text` | — | YÜKSEK |
| Kategori ID | `CTX.data.description.fields.leafCategoryId` | — | YÜKSEK |

### 💰 Fiyat ve kademeli fiyat (阶梯价) — EN KRİTİK BLOK

| Alan | JSON yolu | Örnek | Güven |
|---|---|---|---|
| **Kademeli fiyat dizisi (ASIL)** | `ROOT.orderParamModel.orderParam.skuParam.skuRangePrices[]` | `[{beginAmount:2, price:"5.50"}, {beginAmount:100, price:"4.80"}]` | YÜKSEK |
| Kademeli fiyat (alt 1) | `CTX.data.mainPrice.priceModel.currentPrices[]` | aynı şekil | YÜKSEK |
| Kademeli fiyat (alt 2) | `CTX.data.mainPrice.originalPricesWithoutPromotion[]` | indirimsiz hâli | YÜKSEK |
| Kademeli fiyat (alt 3) | `CTX.global.globalData.model.tradeModel.offerPriceModel.currentPrices[]` | — | YÜKSEK |
| Fiyat aralığı metni | `ROOT.skuModel.skuPriceScale` | `"12.00-15.00"` | YÜKSEK |
| Min fiyat | `CTX.data.mainPrice.finalPriceModel.tradeWithoutPromotion.offerMinPrice` | `12.00` | YÜKSEK |
| Max fiyat | `...tradeWithoutPromotion.offerMaxPrice` | `15.00` | YÜKSEK |
| **MOQ (起订量)** | `ROOT.orderParamModel.orderParam.beginNum` | `2` | YÜKSEK |
| Karışık parti (混批) | `ROOT.orderParamModel.orderParam.mixParam.{mixAmount,mixBegin,mixNum,shopMixNum}` | — | YÜKSEK |

**Kademeli fiyat yapısı:** `[{beginAmount, price}, ...]` — yani her eleman "şu adetten itibaren şu fiyat". Üst sınır yok, bir sonraki elemanın `beginAmount`-1'i.

⚠️ **DOM'dan okursan farklı:** DOM'da (`.module-od-main-price .step-price .price-comp`) kademe aralığı **serbest Çince metin** olarak geliyor (`"2件起批"`, `"100-999件"`). JSON yolu daha temiz — DOM'u sadece fallback yap.

### 📦 SKU / Varyant matrisi

| Alan | JSON yolu | Örnek | Güven |
|---|---|---|---|
| **SKU haritası (ASIL)** | `ROOT.skuModel.skuInfoMapOriginal` | bkz. aşağı | YÜKSEK (5 bağımsız kaynak) |
| SKU haritası (alt) | `ROOT.skuModel.skuInfoMap` | yeni sayfalarda | YÜKSEK |
| Varyant eksenleri | `ROOT.skuModel.skuProps[]` | `[{prop:"颜色", fid, value:[{name:"红色", imageUrl, vid}]}]` | YÜKSEK |

**`skuInfoMapOriginal` yapısı** — anahtar birleşik string, değer obje:

```json
{
  "蓝色【F106】>内长12【鞋底标12.5】": {
    "skuId": 5330445855404,
    "specId": "abc123...",
    "specAttrs": "蓝色【F106】>内长12",
    "price": 15.00,
    "discountPrice": 12.50,
    "canBookCount": 340,
    "saleCount": 128
  }
}
```

🔴 **PARSER İÇİN KRİTİK:** SKU anahtarı **yapılandırılmamış birleşik string**. İki boyut (renk + beden) tek key içinde, ayraç `>` (bazen `;` veya `#`). Renk/beden ayrımı için key'i split etmen gerekiyor. Taobao'dan farklı davranış.

🔴 **Alan adı tutarsızlığı:** Aynı kavram için sayfa şablonuna göre farklı anahtarlar çıkabiliyor. Gerçek projelerden alınmış fallback zincirleri:

```js
// fiyat
price = v.price ?? v.salePrice ?? v.sale_price ?? v.skuPrice ?? v.sku_price
     ?? v.discountPrice ?? v.discount_price ?? v.priceMoney?.value
// stok
stock = v.canBookCount ?? v.amountOnSale ?? v.stock ?? v.stockQty
     ?? v.quantity ?? v.inventory ?? v.availableStock ?? 0
```
> Kaynak: `xplusyuz/XplusY → content-1688.js`, `cxa-maker/one → sku-helpers.ts`

### 📊 Stok

| Alan | JSON yolu | Güven |
|---|---|---|
| Varyant bazında stok | `skuInfoMap[key].canBookCount` | YÜKSEK |
| **Toplam stok** | `ROOT.orderParamModel.orderParam.canBookedAmount` | YÜKSEK |
| Toplam stok (alt) | `CTX.data.mainPrice.finalPriceModel.tradeWithoutPromotion.canBookedAmountOriginal` | YÜKSEK |

> ℹ️ **Terminoloji düzeltmesi:** Senin sorduğun `amountOnSale` adı 1688 ham sayfasında ana alan adı DEĞİL. Gerçek adlar: `canBookCount` (SKU seviyesi), `canBookedAmount` (toplam). `amountOnSale` sadece bazı şablonlarda fallback olarak geçiyor.

### 📈 Satış adedi

| Alan | JSON yolu | Örnek | Güven |
|---|---|---|---|
| Satış metni | `CTX.data.productTitle.fields.newSaleCount` | `"月销1000+"` (metin!) | YÜKSEK |
| Satış sayısı | `ROOT.orderParamModel.orderParam.saledCount` | `3786` | YÜKSEK |
| Satış sayısı (alt) | `ROOT.tempModel.saledCount` | — | YÜKSEK |
| Aylık/yıllık işlem | DOM: `月成交` / `年成交` / `月代销` panel metni | — | ORTA (tek kaynak, DOM) |

⚠️ `newSaleCount` **sayı değil metin** — `"月销1000+"` gibi. Sayısal iş için `saledCount` kullan.

### 🖼️ Görseller

| Alan | JSON yolu | Güven |
|---|---|---|
| Ana görseller | `CTX.data.gallery.fields.mainImage[]` | YÜKSEK |
| Ana görseller (alt 1) | `CTX.data.gallery.fields.offerImgList[]` | YÜKSEK |
| Ana görseller (alt 2) | `CTX.data.gallery.fields.wlImageInfos[]` (`{fullPathImageURI}`) | YÜKSEK |
| Ana görseller (alt 3) | `ROOT.images[]` (`[{fullPathImageURI}]`) | YÜKSEK |
| Varyant görseli | `skuProps[].value[].imageUrl` veya `skuInfoMap[key].pic` | YÜKSEK |
| **Detay/açıklama görselleri** | JSON'da güvenilir yol **BULUNAMADI** → DOM: `<div id="detail"> img[data-sf-original-src]` (alicdn `/img/ibank/` yolunda; `imgextra`, `_sum.jpg`, `.webp` hariç) | DOM: YÜKSEK / JSON: BULUNAMADI |

**Görsel URL formatı:** `//cbu01.alicdn.com/img/ibank/...jpg` — protokolsüz gelebilir, `https:` ön ekle. Boyut varyantları: `size220x220ImageURI`, `size310x310ImageURI`, `fullPathImageURI` (orijinal), `summImageURI`.

### 🎬 VİDEO — Ek kapsam 12'nin cevabı

| Alan | JSON yolu | Örnek | Güven |
|---|---|---|---|
| Video ID | `CTX.data.gallery.fields.video.videoId` | `521788642423` | YÜKSEK |
| Video ID (alt) | `CTX.data.description.fields.detailVideoId` | — | YÜKSEK |
| **Kapak (poster)** | `CTX.data.gallery.fields.video.coverUrl` | `//cbu01.alicdn.com/...jpg` | YÜKSEK |
| **Doğrudan mp4 URL** | Bazı sayfalarda hazır: `mainVideo` alanı | `https://cloud.video.taobao.com/play/u/1800607957/p/1/e/6/t/1/521788642423.mp4` | YÜKSEK (gerçek yakalanmış örnek) |
| mp4 URL (türetme) | `https://cloud.video.taobao.com/play/u/{sellerUserId}/p/2/e/6/t/1/{videoId}.mp4` — `sellerUserId` yoksa `u/0` | — | **ORTA** (türetilmiş formül, tek kaynak) |
| DOM fallback | `<video src>` veya `<video><source src>` | — | YÜKSEK |
| **Süre** | **BULUNAMADI** — hiçbir kaynakta yok | — | BULUNAMADI |
| **Çözünürlük** | **BULUNAMADI** | — | BULUNAMADI |

**Ek kapsam 12'nin diğer soruları:**

- **m3u8 mi mp4 mi?** → **mp4**, yüksek güvenle doğrulandı. m3u8/HLS kullanımına dair hiçbir kaynakta kanıt yok (`DOĞRULANMADI`).
- **Player içinde mi, JSON'da mı?** → **JSON'da (ve DOM'daki `<video>` etiketinde).** Ayrı bir "video çözümleme" API'si YOK. Taobao'daki `mtop.taobao.metadata.getUnionVideoById` benzeri bir uç 1688 için bulunamadı.
- **Birden fazla video olabilir mi?** → İncelenen **3 bağımsız kaynağın hepsinde video alanı TEKİL string** (dizi değil). Çoklu video desteği `DOĞRULANMADI`. Yine de parser'ı savunmacı yaz: alan dizi gelirse ilkini al.

⚠️ **Kritik nüans:** Bazı sayfalarda JSON sadece `videoId` veriyor, hazır URL vermiyor. O durumda ya türetme formülünü kullan (orta güven — canlı testte doğrula) ya da DOM'daki `<video>` etiketini oku. **En sağlam strateji: önce `mainVideo`/`videoUrl` ara → yoksa DOM `<video>` → yoksa formülle türet.**

### 🏷️ Ürün özellik/spec tablosu (材质/尺寸/重量)

| Alan | JSON yolu | Güven |
|---|---|---|
| Özellik listesi | `ROOT.tempModel.featureAttributes[]` = `[{name, value}]` | YÜKSEK |
| Özellik listesi (alt) | `ROOT.tempModel.productFeatureList[]` | YÜKSEK |
| Özellik listesi (alt) | `CTX.global.globalData.model.offerDetail.featureAttributes` | **ORTA** — yol sayfa versiyonuna göre değişiyor |
| DOM fallback | `div.antd-external-collapse.collapse-body` → `<th><span>ad</span>` + `<span class="field-value">değer</span>` | YÜKSEK |

⚠️ `superjack2050/1688-cli` kodu bu alan için **ağaç taraması** yapıyor (12 derinliğe kadar `{name, value}` şekilli dizi arıyor) çünkü yol garantili değil. Bizim parser da benzer bir "derin arama" fallback'i içermeli.

### 📐 Koli / paketleme / ağırlık

| Alan | JSON yolu | Güven |
|---|---|---|
| **Paket bilgisi (ASIL)** | `CTX.data.productPackInfo.fields.pieceWeightScale.pieceWeightScaleInfo[]` | YÜKSEK |
| — yapısı | `{skuId, sku1, length, width, height, weight, volume}` | YÜKSEK |
| Birim ağırlık (kargo) | SKU mtop yanıtı: `extraInfo.freightInfo.unitWeight`; mobilde `shipping.unitWeight` | YÜKSEK |
| DOM fallback | `#productPackInfo table` → td sütunları | YÜKSEK |
| **Koli içi adet (装箱数量)** | **BULUNAMADI** — hiçbir kaynakta ayrı alan yok | BULUNAMADI |

⚠️ **İki önemli uyarı:**
1. `pieceWeightScaleInfo` küçük ürünlerde (giysi vb.) 1688 tarafından **boş dizi** bırakılıyor. Parser bunu tolere etmeli.
2. **"Kaç adet 1 kolide" bilgisi 1688'de yapılandırılmış alan olarak YOK.** Bu bilgi genelde ürün açıklama görselinde veya satıcıyla yazışmada. → **tedarikapp'ta bu alan MANUEL giriş olmalı.** (Bu, İE tasarımını doğrudan etkileyen bir bulgu.)

### 🚚 Kargo / teslimat

| Alan | JSON yolu | Güven |
|---|---|---|
| Gönderim yeri | `CTX.data.shippingServices.fields.freightInfo.{sendAddress, sendProvinceText, sendCityText, sendArea}` | YÜKSEK |
| Teslimat süresi metni | `CTX.data.shipping.fields.deliveryLimitText` / `.logisticsText` | YÜKSEK |
| Süre (DOM regex) | `\d+\s*(?:小时\|天)(?:内)?发货` | YÜKSEK |
| **Kargo ücreti** | **Statik JSON'da YOK** — hedef adrese göre dinamik hesaplanıyor, ayrı XHR gerekiyor (bkz. Bölüm A.4) | YÜKSEK |
| Hesaplama parametreleri | `sendAddressCode`, `templateId`, `freeEndAmount`, `unitWeight` | YÜKSEK |

### 🏢 Satıcı

| Alan | JSON yolu | Güven |
|---|---|---|
| Şirket adı | `CTX.data.productTitle.fields.shopInfo.companyName` / `.authCompanyName` | YÜKSEK |
| Mağaza URL | `CTX.global.globalData.model.sellerModel.winportUrl` | YÜKSEK |
| Mağaza URL (alt) | `ROOT.tempModel.winportUrl` | YÜKSEK |
| Mağaza URL (türet) | `https://winport.m.1688.com/page/index.html?memberId={memberId}` | YÜKSEK |
| Satıcı kimlikleri | `window.FE_GLOBALS.{offerLoginId, loginId, memberId}` | YÜKSEK |
| Satıcı kimlikleri (alt) | `ROOT.tempModel.{sellerUserId, sellerLoginId, sellerMemberId}` | YÜKSEK |
| Hizmet puanı | `CTX.data.productTitle.fields.shopInfo.sellerSlrServiceScore` | **ORTA** (tek kaynak) |
| **诚信通 yıl sayısı** | JSON'da **BULUNAMADI** — DOM metin regex: `/入驻\d+年/` | DOM: YÜKSEK / JSON: BULUNAMADI |
| **回头率 (geri dönüş oranı)** | Detay sayfası JSON'unda **BULUNAMADI** → ayrı XHR (`shopcard`) gerekiyor | BULUNAMADI (JSON'da) |
| İşlem puanı / TP yılı / zamanında sevkiyat / olumlu yorum oranı | Ayrı XHR: `mtop.1688.moga.pc.shopcard` | YÜKSEK (XHR'da) |

### ⭐ Değerlendirme puanı ve yorum sayısı

🔴 **Sayfa gömülü JSON'unda YOK.** Ayrı XHR gerekiyor (bkz. Bölüm A.4).

⚠️ **ÇOK ÖNEMLİ TUZAK:** Sayfa state'inde `productTitle.fields.rateInfo` benzeri bir alan görünebiliyor ama **canlı sayfada başka üründen kalma kirli veri (串页脏数据) taşıdığı doğrulanmış.** Bu alanı asla gerçek kaynak sayma; her zaman `queryDsrRateDataV2` XHR'ını kullan.
> Kaynak: `xu-jssy/X → browser-sidepanel-1688-integration.md`

### 🎁 Promosyon / indirim

| Alan | JSON yolu | Güven |
|---|---|---|
| SKU indirim fiyatı | `skuInfoMap[key].discountPrice` (vs `price` = orijinal) | YÜKSEK |
| Promosyon fiyatı | `skuInfoMap[key].promotionPrices.{finalPrice, salePriceMoney}` | ORTA |
| Ayrı "promotions" bloğu | **BULUNAMADI** | BULUNAMADI |

### 📅 Yayın / güncellenme tarihi

🔴 **JSON'da BULUNAMADI.** Sadece DOM metninden regex ile:
- `div.update-time span` içinde `最早上架时间` (ilk yayın) / `最新发布时间` (son güncelleme), format `\d{4}-\d{2}-\d{2}`
- Alternatif: `上架时间<span>...</span>`

> Ticari API'lerde `created_time`, `modified_time`, `delist_time`, `onSaleTime` alanları var ama bunlar API'nin kendi normalize şeması, ham sayfada karşılığı doğrulanamadı.

### 🔗 Benzer ürün / satıcının diğer ürünleri

🔴 **Detay sayfası JSON'unda YOK.** Her zaman ayrı istek:
- Satıcının diğer ürünleri: `winport.m.1688.com/page/offerlist.html?memberId=...` (ayrı sayfa, HTML parse)
- Görsel benzerlik önerisi: `mtop.relationrecommend.WirelessRecommend.recommend` (appId `32517`)

---

## A.3 Ek kapsam 14 — SAYFA DIŞI VERİ (XHR/fetch)

**Gateway:** `https://h5api.m.1688.com/h5/{api}/{version}/`

| Endpoint | Ne döner | Metod | Parametreler | İmza | Güven |
|---|---|---|---|---|---|
| `mtop.1688.moga.pc.shopcard` | Mağaza kartı: TP yılı, hizmet puanı, **geri dönüş oranı**, zamanında sevkiyat oranı, olumlu yorum oranı, takipçi | GET | `offerId` | ✅ | YÜKSEK |
| `mtop.1688.trade.service.MtopRateService.queryDsrRateDataV2` | **Değerlendirme özeti**: `goodRates, goodsGrade, commonTagNodeList` (toplam yorum sayısı = `name:"全部"` etiketinin `count`'u) | GET/POST | `loginId, scene:"item", offerId, site:null` | ✅ | YÜKSEK |
| `mtop.1688.trade.service.MtopRateService.queryItemRatedListV2` | **Yorum listesi**: `content, gmtPublished, starLevel, specInfo, raterUserNick, images` | GET | `page, scene:"item", itemId, loginId` | ✅ | YÜKSEK |
| `mtop.1688.freightInfoService.getFreightInfoWithScene` | **Kargo ücreti** (hedefe göre) | POST | `offerId, sellerUserId, sendAddressCode, receiveAddressCode, freeEndAmount, extendMap:{templateId, unitWeight, skuWeight, amount}` | ✅ | YÜKSEK |
| `mtop.1688.mmga.offerdetail.service` (bus) | serviceName'e göre: `pcOfferCertificateService` (sertifika), `offerPCSellPointQueryService` (satış noktaları), `offerPCConsignInfoService` (dağıtım), `profileReadFnService` (mağaza profili); mobilde `wirelessLightOfferService` (tam fiyat/stok/SKU) | GET | `data:{mmgaRequest:{serviceName, offerId}}` | ✅ | YÜKSEK |
| `wosc.queryofferskuselectormodel` | **SKU seçici** — güncel fiyat/stok/MOQ: `data.skuSelectorBizModel.skuSelectorModel.tradeModel.offerPriceModel.currentPrices[]` | GET | `offerId` | ✅ | YÜKSEK |
| `itemcdn.tmall.com/1688offer/...` | **Detay açıklama görselleri** | GET | — | ❌ **İmza yok, düz CDN** | YÜKSEK |
| `mtop.1688.shop.data.get` | Mağaza verisi | GET | — | ✅ | DOĞRULANMADI (detay sayfası bağlamında) |

### Net ayrım: Gömülü mü, XHR mi?

**✅ HTML'de gömülü (ek istek gerekmez):**
başlık · kademeli fiyat · SKU + stok · ana görsel galerisi · satıcı temel kimliği · paketleme/ağırlık · kupon/promosyon · video URL/ID/poster · özellikler · gönderim yeri

**🌐 Ayrı XHR gerekiyor:**
değerlendirme puanı + yorumlar · mağaza skorları (geri dönüş oranı, TP yılı) · kargo ücreti · sertifikalar · detay açıklama görselleri · benzer ürünler · satıcının diğer ürünleri

---

## A.4 Anti-bot: imza mekanizması — ve neden UZANTI BÜYÜK AVANTAJ

Tüm `h5api.m.1688.com` çağrıları `_m_h5_tk` çerezine bağlı imza istiyor:

```
sign = MD5(token + "&" + t + "&" + appKey + "&" + JSON(data))
```

Harici scraper'ın yapmak zorunda olduğu: anonim token bootstrap → IP/UA sahteciliği → `x5sec` slider doğrulaması çözme.

### 🎯 tedarikapp Chrome uzantısı için sonuç — bu bizim mimarimizi doğruluyor

1. **İmza hesaplamana genelde GEREK YOK.** Sayfanın kendi JS'i bu istekleri zaten otomatik atıyor. Uzantı bunları **pasif dinleyerek** yakalayabilir:
   - content script'i `document_start`'ta `MAIN` world'e enjekte et
   - `window.fetch` + `XMLHttpRequest.prototype.open` üzerine monkey-patch koy
   - `h5api.m.1688.com` filtresiyle yanıtları topla
2. **Oturum çerezi zaten var.** Uzantı gerçek kullanıcı tarayıcısında, gerçek TLS/UA parmak iziyle çalıştığı için anti-bot sürtünmesinin tamamı bizim için geçersiz. **Bu, K15'teki "parser modülü" yaklaşımının uzantı tarafında yaşamasının en güçlü teknik gerekçesi.**
3. **Aktif çağrı sadece istisna durumda:** Örn. farklı hedef bölgeye kargo ücreti — sayfa varsayılan gönderici adresiyle çalışıyor. O zaman uzantı aynı sayfa bağlamında kendisi imzalı istek atar (çerez zaten oturumda, sadece `sign` hesabı gerekir).
4. **Listener'ı navigasyondan ÖNCE kur.** Sayfa tam yüklenmeden veya trafik temizlenirse pasif dinleme isteği kaçırır.

---

## A.5 Parser modülü için önerilen strateji (bulgulardan çıkan)

```
1. HTML çek  →  window.context bloğunu işaretlerle kes
2. JSON5/toleranslı parse (sayısal anahtar tuzağı!)
3. Her alan için fallback zinciri: data.<modül> → Root.dataJson → global.globalData → DOM
4. Değer boş/şüpheliyse (fiyat 0, stok 0) → pasif yakalanan XHR yanıtına bak
5. rating/yorum/kargo ücreti/detay görselleri → SADECE XHR'dan
6. Sayfa şablonu tanınmadıysa → sessizce boş dönme, "parse hatası" logla (K15 modülerlik)
```

**Zorunlu savunmalar:**
- `window.context` yoksa → mobil `__INIT_DATA__` dene → o da yoksa DOM parser
- `pieceWeightScaleInfo` boş dizi olabilir
- `newSaleCount` metin, `saledCount` sayı — karıştırma
- SKU key'i `>` / `;` / `#` ile ayrılabilir
- Görsel URL'leri protokolsüz (`//cbu01...`) gelebilir

---

# BÖLÜM B — SAYFADA BULUNAN DİĞER TÜM VERİLER (ENVANTER)

Ek kapsam 13: "sadece istediklerim değil, ne varsa."

## B.1 `window.context.result` üst düzey anahtar envanteri

| Anahtar | İçerik |
|---|---|
| `result.data` | Widget/modül haritası — her modül `<ad>.fields` altında |
| `result.data.productTitle.fields` | başlık, `newSaleCount`, `shopInfo`, `unit` (birim: 件/个/箱) |
| `result.data.gallery.fields` | `mainImage[]`, `offerImgList[]`, `wlImageInfos[]`, `video{videoId, coverUrl}`, `offerId`, `subject` |
| `result.data.description.fields` | `detailUrl` (detay HTML'i ayrı CDN'de), `leafCategoryId`, `detailVideoId` |
| `result.data.mainPrice` | `finalPriceModel`, `priceModel.currentPrices[]`, `originalPricesWithoutPromotion[]` |
| `result.data.productPackInfo.fields` | `pieceWeightScale.pieceWeightScaleInfo[]` (skuId, sku1, L/W/H, weight, volume) |
| `result.data.mainServices` | Hizmet rozetleri, `guaranteeList` (garanti/iade vaatleri) |
| `result.data.shippingServices.fields` | `freightInfo` (gönderim yeri, adres kodu, şablon ID) |
| `result.data.shipping.fields` | `deliveryLimitText`, `logisticsText` |
| `result.data.Root.fields.dataJson` | **Köprü modülü** — aşağıdaki alt modeller |
| `↳ .tempModel` | offerId, offerTitle, satıcı kimlikleri, kategori ID, `featureAttributes[]`, `saledCount`, `winportUrl` |
| `↳ .skuModel` | `skuProps[]`, `skuInfoMap`, `skuInfoMapOriginal`, `skuPriceScale` |
| `↳ .orderParamModel.orderParam` | `beginNum` (MOQ), `canBookedAmount` (stok), `skuParam.skuRangePrices[]` (kademeli fiyat), `mixParam`, `saledCount` |
| `↳ .mixModel` | Karışık parti kuralları |
| `↳ .images[]` | `[{fullPathImageURI}]` |
| `↳ .frontSellerMemberModel` | Satıcı üyelik modeli |
| `result.global.globalData.model` | Paralel model ağacı |
| `↳ .tradeModel` | offerId, fiyat gösterimi, birim, MOQ, `offerPriceModel.currentPrices`, satıcı veri merkezi |
| `↳ .sellerModel` | firma adı, `winportUrl`, memberId, cardType |
| `↳ .offerTitleModel` | başlık modeli |

## B.2 Sayfada bulunan ama JSON'da olmayan (sadece DOM) veriler

| Veri | Nereden |
|---|---|
| Detay/açıklama görselleri | `<div id="detail"> img[data-sf-original-src]` |
| Yayın / güncellenme tarihi | `div.update-time span` (`最早上架时间`, `最新发布时间`) |
| 诚信通 yıl sayısı | metin regex `入驻\d+年` |
| Aylık/yıllık işlem hacmi | panel metni `月成交`, `年成交`, `月代销` |
| Özellik tablosu (fallback) | `div.antd-external-collapse.collapse-body` |
| Kademe fiyat (fallback) | `.module-od-main-price .step-price .price-comp` |
| SKU (fallback) | `#skuSelection .expand-view-item` → `.item-label`, `.item-price-stock[0]`, `.item-price-stock[1]`, `.item-image-icon img` |
| Kargo modülü ham metni | `.module-od-shipping-services` textContent |
| Kategori breadcrumb | breadcrumb metni (ID yok, sadece isim) |

## B.3 Ticari API'lerde var olduğu görülen ama ham sayfada karşılığı doğrulanamayan alanlar

> Bunlar API sağlayıcılarının kendi normalize şemaları. Ham sayfada karşılığını canlı testte aramamız gerekir.

`total_sold` · `sale_num` · `bookedCount` · `num` (toplam stok) · `rate_average` · `good_percent` · `total_results` (yorum sayısı) · `serviceScore` · `repeatCustomerRate` · `collectCount` (favori sayısı) · `props_list` / `props_img` (özellik-görsel eşlemesi) · `item_size` · `item_weight` · `post_fee` / `express_fee` / `ems_fee` · `created_time` / `modified_time` / `delist_time` / `onSaleTime` · `creditLevel` · `desc_img[]` · `cat_id` / `root_cat_id` / `pid` / `sub[]`

## B.4 KESİN OLARAK BULUNAMAYAN veriler (parser'da manuel/hesaplanan olmalı)

| Veri | Durum | tedarikapp için sonuç |
|---|---|---|
| **Koli içi adet (装箱数量)** | 1688'de yapılandırılmış alan YOK | 🔴 **MANUEL giriş alanı olmalı** |
| Video süresi | Hiçbir kaynakta yok | Gerekiyorsa client-side `<video>.duration` ile ölç |
| Video çözünürlüğü | Hiçbir kaynakta yok | Gerekiyorsa `videoWidth/videoHeight` ile ölç |
| Çoklu video | Tüm kaynaklarda tekil | Savunmacı yaz (dizi gelirse ilkini al) |
| Tahmini teslim süresi (yapılandırılmış) | Sadece serbest metin | Metin olarak sakla, parse etme |
| Kargo/navlun şablonu detayı | Sadece `templateId` var, içeriği yok | Ücret için XHR şart |
| Ayrı promosyon bloğu | Yok — sadece `price` vs `discountPrice` | İndirim = fark hesapla |
| 30 günlük satış (ayrı alan) | Yok — en yakını `月成交` metni | Metin olarak sakla |
| CBM / hacim (her zaman) | `volume` alanı var ama her üründe dolu değil | L×W×H'den hesapla (fallback) |

## B.5 Sayfa versiyonu / şablon farkları (parser dayanıklılığı için)

| Fark | Etki |
|---|---|
| **Masaüstü vs mobil** | `window.context` vs `window.__INIT_DATA__` — tamamen farklı yapı, ayrı parser gerekir |
| **Üç paralel dal** | Aynı veri 3 yerde farklı adla — fallback zinciri zorunlu |
| **Eski (pre-SPA, ~2018-19) şablon** | JSON state YOK, saf DOM (`#mod-detail`, `data-range` attribute'ları). Bugün nadir ama eski kod referanslarına güvenme |
| **SKU alan adı tutarsızlığı** | Stok için 8+, fiyat için 8+ olası anahtar adı → çoklu-anahtar fallback |
| **Gömülü veri bayat olabilir** | Sayfa client-side'da XHR ile kendini güncelliyor. Fiyat/stok şüpheliyse XHR yanıtını dinle |
| **`rateInfo` kirli veri** | Gömülü rating alanı başka üründen kalma veri taşıyabiliyor — asla kullanma |

---

# SONRAKİ ADIM — CANLI DOĞRULAMA

Chrome bağlantısı gelince şunları 2–3 farklı üründe (farklı kategori: tekstil / ev eşyası / elektronik) teyit edeceğim:

1. `window.context` gerçekten var mı, işaretçiler tutuyor mu
2. Üç dalın hangisi bu üründe dolu
3. `skuInfoMapOriginal` gerçek anahtar formatı (ayraç `>` mi?)
4. Video: `mainVideo` hazır mı geliyor, yoksa sadece `videoId` mi → türetme formülü çalışıyor mu
5. `pieceWeightScaleInfo` dolu mu boş mu
6. Network'te hangi mtop çağrıları gerçekten tetikleniyor
7. **Kapsam 13'ün asıl cevabı:** gerçek üst düzey anahtar listesinin TAMAMI (bu raporda kaynaklardan derlenen liste eksik olabilir)

---

## KAYNAKLAR

**Açık kaynak parser'lar (kod birebir okundu):**
- [jiyun/1688](https://github.com/jiyun/1688) — `utils/parsers/alibaba_parser.py`
- [superjack2050/1688-cli](https://github.com/superjack2050/1688-cli) — `src/commands/offer.ts`, `docs/specs/supplier-inspect.md`
- [jackwener/OpenCLI](https://github.com/jackwener/OpenCLI) — `clis/1688/item.js`, `shared.js`, `store.js`
- [mohamadzayyat/1688_new_scrapper](https://github.com/mohamadzayyat/1688_new_scrapper) — `offerContext.js`, `mtopDetail.js`, `tmapiFormat.js`, `mtopExtra.js`
- [68110923/chrome_plugins](https://github.com/68110923/chrome_plugins) — `dxm_erp_review_assistant.user.js`
- [cxa-maker/one](https://github.com/cxa-maker/one) — `collector/src/providers/source1688/*`
- [xplusyuz/XplusY](https://github.com/xplusyuz/XplusY) — `chrome-extension-orzumall-1688/content-1688.js`
- [hanfeihu/temu-upin-system](https://github.com/hanfeihu/temu-upin-system) — `Alibaba1688HtmlParser.java`
- [oures1/socora-1688-shopify-system](https://github.com/oures1/socora-1688-shopify-system)
- [Linuxpizi/auto-ozon](https://github.com/Linuxpizi/auto-ozon) — `scraper_1688.py`
- [Jamie33/learngit](https://github.com/Jamie33/learngit) — eski şablon kanıtı

**XHR / imza / network:**
- [browser-act/skills — 1688-product-detail SKILL.md](https://github.com/browser-act/skills/blob/main/solutions/ecommerce/1688-product-detail/SKILL.md)
- [xu-jssy/X — browser-sidepanel-1688-integration.md](https://github.com/xu-jssy/X/blob/main/doc/specs/browser-sidepanel-1688-integration.md)
- [QuoVadis86/ai-reverse — 1688/core.py](https://github.com/QuoVadis86/ai-reverse/blob/main/1688/core.py)
- [0xAllenChen/spider_reverse — sign_1688.py](https://github.com/0xAllenChen/spider_reverse/blob/main/2023_06/demo5_1688/sign_1688.py)
- [mvanhorn/printing-press-library — internal/mtop/client.go](https://github.com/mvanhorn/printing-press-library)
- [zeshaoaaa/cross-border-data-capture — references/1688.md](https://github.com/zeshaoaaa/cross-border-data-capture/blob/main/references/1688.md)
- [zhoulingxiao1216/testworkspace](https://github.com/zhoulingxiao1216/testworkspace) — gerçek yakalanmış offer JSON örneği

**API dokümanları:**
- [Onebound 1688.item_get](https://open.onebound.cn/help/api/1688.item_get.html)
- [Onebound 1688.item_review](https://open.onebound.cn/help/api/1688.item_review.html)
- [Onebound 1688.item_cat_get](https://open.onebound.cn/help/api/1688.item_cat_get.html)
- [Parse.bot — detail-1688-com-api](https://parse.bot/marketplace/1b01e91b-99c9-4dca-81a5-56b81d9112c0/detail-1688-com-api)
- [Parse.bot — s-1688-com-api](https://parse.bot/marketplace/eceea1c8-5d3d-4edf-b744-6f1b60240aea/s-1688-com-api)
- [Apify — webdata_labs/1688-scraper](https://apify.com/webdata_labs/1688-scraper)
- [Apify — gio21/1688-product-scraper](https://apify.com/gio21/1688-product-scraper)

**Erişilemeyen:** `open.1688.com` (403 blocklisted), RapidAPI sayfaları (JS-render), Apify ecomscrape (deprecated)
