# 1688 Ürün Sayfası — Parser Teknik Raporu

**İncelenen sayfa:** `https://detail.1688.com/offer/895133432293.html`
**Ürün:** 跨境榨汁机便携式小质家用小型水果机多功能迷你电动果汁机榨汁杯
**Sayfa sürümü:** `GL_PAGE_ID = "PC-DEFAULT-2026"`, `body.od-version-2026`, `context.version = "0.26.12"`
**İnceleme tarihi:** 16 Ağustos 2026

---

## 0. ÖZET — Mimarî

Sayfa **SSR (server-side rendered) + React hydration** mimarisiyle çalışıyor.
Ürün verisinin **%90'ı sayfa kaynağında hazır**, tek bir dev inline `<script>` içinde.

| Katman | Ne var |
|---|---|
| `window.context` (inline script, ~78 KB) | Başlık, fiyat, SKU, stok, görseller, kategori, satıcı, kargo, spec tablosu, koli bilgisi, promosyon |
| `window.gallery` (ikinci küçük script) | Görsel listesinin kısayolu |
| `detailUrl` (ayrı CDN dosyası) | Açıklama HTML'i + 21 detay görseli |
| MTOP XHR (`h5api.m.1688.com`) | Yorumlar, dağıtım/代发 paneli, benzer ürün önerileri |

> **Kritik uyarı:** Ürün açıklaması **Shadow DOM** içinde (`<v-detail-5>`). Normal `document.querySelectorAll('img')` detay görsellerini **görmez**. Detay bölümü ayrıca `_.webp` sonekli lazy-load kullanıyor.

---

# A) PARSER İÇİN KRİTİK ALANLAR

## A.0 Kök erişim — Gömülü JSON

### Nerede?

```
document.scripts[20]   // inline, ~78.188 karakter
```

İçerik şu iki atamayla başlıyor:

```js
window.contextPath = "/default";
window.context={ ... 78 KB JSON ... }
```

> `window.__INIT_DATA`, `__NEXT_DATA__`, `__NUXT__` **yok**. Değişken adı **`window.context`**.
> Hemen sonraki script (#21) şunu yapıyor: `try { var gallery = window.context.result.data.gallery.fields; window.gallery = gallery } catch(e){}`

### Kök çıkarma (content script)

```js
// 1) Canlı sayfada (en kolay — MAIN world content script gerekir)
const ctx = window.context;

// 2) HTML kaynağından regex ile (ISOLATED world / sunucu tarafı fetch için)
const html = document.documentElement.outerHTML;   // veya fetch(url).then(r=>r.text())
const m = html.match(/window\.context\s*=\s*(\{[\s\S]*?\})\s*;?\s*<\/script>/);
const ctx = JSON.parse(m[1]);
```

> Chrome eklentisinde `world: "MAIN"` content script en temizi; `window.context`'e doğrudan erişirsiniz.
> ISOLATED world'de kalmak isterseniz sayfaya `<script>` enjekte edip `postMessage` ile taşıyın.

### Ağaç yapısı

```
window.context
├── version            "0.26.12"
├── panelConfig        {appVersion, appBaseVersion, module}
├── module             { "@ali/od-title": {...}, "@ali/od-main-price": {...}, ... }  // 25 modül tanımı
└── result
    ├── endpoint
    ├── reload
    ├── hierarchy      { root:"Root", structure:{...} }        // layout ağacı
    ├── global
    │   ├── name, version, systemParam, renderData
    │   └── globalData
    │       ├── traceId
    │       ├── parametersMap  ← offerId, loginId, hotSaleSkuId, spm
    │       └── model          ← ★★★ HAM İŞ VERİSİ — PARSER BURAYI KULLANMALI
    └── data           ← 35 UI modülü: { moduleId: {id, type, tag, position, meta, fields} }
```

**Bundan sonra kısaltmalar:**

```js
const M = window.context.result.global.globalData.model;   // ham iş modeli
const D = window.context.result.data;                      // UI modülleri
const P = window.context.result.global.globalData.parametersMap;
```

### ⚠️ `$ref` döngüleri (fastjson)

JSON içinde **fastjson dairesel referansları** var:

```json
"skuWeight": { "$ref": "$.result.data.shippingServices.fields.freightInfo.skuWeight" }
"extraInfo": { "$ref": "$.result.data.discountCoupon.fields.promotionModel.promotionList[0].extraInfo" }
```

`JSON.stringify` etmeden önce bunları çözmelisiniz. Basit çözücü:

```js
function resolveRefs(root, node = root) {
  if (!node || typeof node !== 'object') return node;
  for (const k of Object.keys(node)) {
    const v = node[k];
    if (v && typeof v === 'object') {
      if (typeof v.$ref === 'string') {
        // "$.result.data.x.fields.y" → root.result.data.x.fields.y
        const path = v.$ref.replace(/^\$\.?/, '').replace(/\[(\d+)\]/g, '.$1').split('.').filter(Boolean);
        node[k] = path.reduce((o, s) => (o == null ? o : o[s]), root);
      } else resolveRefs(root, v);
    }
  }
  return node;
}
```

---

## A.1 ÜRÜN KİMLİĞİ (offerId)

| Kaynak | Yol | Örnek değer | Tip |
|---|---|---|---|
| URL | `/offer/(\d+)\.html` | `895133432293` | string |
| Ana model | `M.offerDetail.offerId` | `895133432293` | **number** |
| Parametre haritası | `P.offerId` | `"895133432293"` | **string** |
| Kupon modülü | `D.discountCoupon.fields.offerId` | `895133432293` | number |
| Galeri modülü | `D.gallery.fields.offerId` | `895133432293` | number |
| Kargo modeli | `M.detailDescription.freightInfo.deliveryFulfillmentSolution.offerId` | `895133432293` | number |

```js
const offerId = String(M.offerDetail.offerId);
// fallback:
const offerId = location.pathname.match(/offer\/(\d+)/)?.[1];
```

> ⚠️ `number` olarak geldiği için JS'te 895133432293 güvenli aralıkta (< 2^53) ama **her zaman String()'e çevirin** — bazı offerId'ler daha uzun olabilir.

**İlgili ID'ler:**

| Alan | Yol | Değer |
|---|---|---|
| Satıcı üye ID | `M.sellerModel.memberId` | `"b2b-2213973763094859b8"` |
| Satıcı user ID | `M.sellerModel.userId` | `2213973763094` |
| Öne çıkan SKU | `M.detailBusiness.hotSaleSkuId` / `P.hotSaleSkuId` | `"5745692521572"` |
| Kargo şablonu | `D.shippingServices.fields.templateId` | `17818683` |

---

## A.2 BAŞLIK (orijinal Çince)

| Kaynak | Yol | Değer |
|---|---|---|
| **Birincil** | `M.offerDetail.subject` | `跨境榨汁机便携式小质家用小型水果机多功能迷你电动果汁机榨汁杯` |
| Modül | `D.productTitle.fields.title` | aynı |
| Galeri | `window.gallery.subject` / `D.gallery.fields.subject` | aynı |
| DOM | `#productTitle` içindeki başlık elemanı | aynı |
| `<title>` | `document.title` | `... - 阿里巴巴` (son ek var, temizlenmeli) |

```js
const title = M.offerDetail.subject;   // ★ en temiz, son ek yok
```

---

## A.3 FİYAT

Bu üründe fiyat **SKU bazlı** (kademeli değil). Ama 1688'de **her iki model de** olabilir — parser ikisini de desteklemeli.

### Fiyat tipini belirleyen alan

```js
M.tradeModel.offerPriceModel.priceDisplayType   // "skuPrice"
```

| Değer | Anlamı |
|---|---|
| `"skuPrice"` | Fiyat **SKU'ya göre** değişir (bu ürün) |
| `"rangePrice"` / kademeli | `currentPrices[]` içinde birden fazla `beginAmount` olur (adet kademesi) |

### Özet fiyat alanları

```js
M.tradeModel.minPrice       // "48.00"   (string!)
M.tradeModel.maxPrice       // "58.00"
M.tradeModel.priceDisplay   // "48.00-58.00"
M.tradeModel.unit           // "个"      (birim)
M.tradeModel.beginAmount    // 1         (MOQ)
M.tradeModel.hasPromotion   // true
```

### Kademe/fiyat dizisi (ladder)

```js
M.tradeModel.offerPriceModel = {
  priceDisplayType: "skuPrice",
  originalPriceDisplay: "48.00-58.00",
  currentPrices: [                                  // ← indirimli / geçerli fiyatlar
    { beginAmount: 1, price: "48.00" },
    { beginAmount: 1, price: "58.00" }
  ],
  originalPrices: [                                 // ← üstü çizili fiyatlar
    { beginAmount: 1, displayStyle: "line_price", price: "48.00" },
    { beginAmount: 1, displayStyle: "line_price", price: "58.00" }
  ]
}
```

> **Kademeli (adet aralıklı) üründe** `currentPrices` şöyle görünür:
> `[{beginAmount:2, price:"12.50"}, {beginAmount:100, price:"11.00"}, {beginAmount:1000, price:"9.80"}]`
> Yani `beginAmount` = "bu adetten itibaren bu fiyat". Üst sınır bir sonraki `beginAmount - 1`.

### UI modülü (aynı veri + ekstra)

```js
D.mainPrice.fields.priceModel = {
  originalPriceDisplay: "48.00-58.00",
  currentPrices: [...], originalPrices: [...],
  priceDisplayType: "skuPrice",
  specificSkuId: "5745692521572",
  specificSkuPriceModel: {
    price: "58.00", pricePrefix: "58", priceSuffix: "00",
    priceBeforeText: "热销款", skuType: "hotSaleSkuId"
  },
  priceLabel: ""
}
D.mainPrice.fields.finalPriceModel.tradeWithoutPromotion.skuMapOriginal[]  // promosyonsuz SKU fiyatları
D.mainPrice.fields.unit                    // "个"
D.mainPrice.fields.isVipPrice / isPlusPrice / isBmPrice / isPrivatePrice
D.mainPrice.fields.originalPricesWithoutPromotion
```

### Para birimi

- **JSON'da para birimi işareti YOK** — sadece sayı string'i (`"48.00"`). Para birimi her zaman **CNY (¥ / 人民币)**.
- DOM'da `¥` işareti ayrı bir `<span class="currency">` içinde render ediliyor → DOM'dan parse ederseniz `¥`, `.00`, `~` gibi karakterleri temizlemeniz gerekir.
- **Öneri:** JSON'dan alın, `currency: "CNY"` sabitini kendiniz ekleyin.

```js
const price = {
  currency: "CNY",
  min: parseFloat(M.tradeModel.minPrice),
  max: parseFloat(M.tradeModel.maxPrice),
  display: M.tradeModel.priceDisplay,
  unit: M.tradeModel.unit,
  moq: M.tradeModel.beginAmount,
  type: M.tradeModel.offerPriceModel.priceDisplayType,
  tiers: M.tradeModel.offerPriceModel.currentPrices.map(t => ({ from: t.beginAmount, price: parseFloat(t.price) }))
};
```

---

## A.4 SKU / VARYANT MATRİSİ

İki parça var: **eksen tanımı** (`skuProps`) + **kombinasyon listesi** (`skuMap`).

### 4.1 Eksen tanımı — `M.offerDetail.skuProps`

```json
[
  {
    "fid": 3216,
    "prop": "颜色",
    "value": [
      { "name": "Z03榨汁杯（7.4V双电池）",
        "imageUrl": "https://cbu01.alicdn.com/img/ibank/O1CN01ORJXMN1Yj6SuIhtX9_!!2213973763094-0-cib.jpg" },
      { "name": "Z03榨汁杯（3.7V单电池）",
        "imageUrl": "https://cbu01.alicdn.com/img/ibank/O1CN01ekuQ4E1Yj6SuYDCgj_!!2213973763094-0-cib.jpg" }
    ]
  }
]
```

> - `fid` = özellik ID'si (3216 = 颜色/renk). Beden olan üründe ikinci bir obje olur (`fid: 3151`, `prop: "尺码"` gibi).
> - **`imageUrl` sadece ilk eksende (genelde renk) bulunur** — varyant görselleri buradan gelir.
> - Tek eksenli üründe dizi uzunluğu 1, iki eksenli (renk+beden) üründe 2 olur.

### 4.2 Kombinasyon listesi — `M.tradeModel.skuMap` ★ EN ÖNEMLİ

```json
[
  {
    "skuId": 5745692521573,
    "specId": "08fb47ceaad8acc757d047a69f891e32",
    "specAttrs": "Z03榨汁杯（3.7V单电池）",
    "price": "48.00",
    "discountPrice": "48.00",
    "retailPrice": "",
    "canBookCount": 9998,
    "saleCount": 0,
    "promotionSku": true
  },
  {
    "skuId": 5745692521572,
    "specId": "424c46efdb6986e1d186e1354943de04",
    "specAttrs": "Z03榨汁杯（7.4V双电池）",
    "price": "58.00",
    "discountPrice": "58.00",
    "retailPrice": "",
    "canBookCount": 9972,
    "saleCount": 0,
    "promotionSku": true
  }
]
```

| Alan | Anlamı |
|---|---|
| `skuId` | Benzersiz SKU ID (number) |
| `specId` | Spesifikasyon hash'i (MD5) — sipariş API'sinde kullanılır |
| `specAttrs` | Varyant adı. **Çok eksenliyse `&gt;` veya `&` ile birleşik gelir** (örn. `"红色&gt;XL"`) → böl ve `skuProps` sırasına göre eşleştir |
| `price` | Liste fiyatı (string) |
| `discountPrice` | İndirimli fiyat (string) |
| `canBookCount` | **Stok** (bu varyant için) |
| `saleCount` | Bu varyantın satış adedi |
| `promotionSku` | Promosyona dahil mi |

**Evet — her varyantın kendi fiyatı ve kendi stoğu var.**

### 4.3 Promosyonsuz alternatif liste

```js
D.mainPrice.fields.finalPriceModel.tradeWithoutPromotion.skuMapOriginal
// aynı yapı + isPromotionSku: false
```

### 4.4 SKU ekstra bayrakları

```js
M.offerDetail.skuFeatures = {
  "5745692521572": { "cbu_hot_type": "skuprice_v1", "cbu_hot_sale_type": "热销款" }   // "çok satan" etiketi
}
```

### 4.5 Varyant birleştirme örneği

```js
const axes = M.offerDetail.skuProps;                 // eksenler
const skus = M.tradeModel.skuMap.map(s => {
  const parts = s.specAttrs.split(/&gt;|&/).map(x => x.trim());
  const attrs = {};
  axes.forEach((ax, i) => {
    attrs[ax.prop] = parts[i];
    const v = ax.value.find(v => v.name === parts[i]);
    if (v?.imageUrl) attrs[ax.prop + '__image'] = v.imageUrl;
  });
  return {
    skuId: String(s.skuId), specId: s.specId,
    name: s.specAttrs, attrs,
    price: parseFloat(s.discountPrice || s.price),
    listPrice: parseFloat(s.price),
    stock: s.canBookCount,
    sold: s.saleCount
  };
});
```

---

## A.5 GÖRSELLER

### 5.1 Ana görseller — zengin format

```js
M.offerDetail.imageList       // 7 adet — galeride gösterilen TÜM görseller (varyant görselleri dahil)
M.offerDetail.mainImageList   // 5 adet — sadece ana ürün görselleri (varyantlar hariç)
```

Her eleman:

```json
{
  "imageURI":            "img/ibank/O1CN01MNNNmK1Yj6SvhDPuo_!!2213973763094-0-cib.jpg",
  "fullPathImageURI":    "https://cbu01.alicdn.com/img/ibank/O1CN01MNNNmK1Yj6SvhDPuo_!!2213973763094-0-cib.jpg",
  "size220x220ImageURI": "https://cbu01.alicdn.com/.../....220x220.jpg",
  "size310x310ImageURI": "https://cbu01.alicdn.com/.../....310x310.jpg",
  "searchImageURI":      "https://cbu01.alicdn.com/.../....search.jpg",
  "summImageURI":        "https://cbu01.alicdn.com/.../....summ.jpg"
}
```

### 5.2 Düz URL listesi (kısayol)

```js
window.gallery.offerImgList   // 7 × düz https URL (string[])
window.gallery.mainImage      // 5 × düz https URL
// aynısı: D.gallery.fields.offerImgList / .mainImage
```

### 5.3 Varyant görselleri

```js
M.offerDetail.skuProps[0].value[i].imageUrl      // her renk için 1 görsel
```

### 5.4 Açıklama (detay) görselleri — 21 adet

`detailUrl`'den gelir (A.6'ya bakın) **veya** Shadow DOM'dan:

```js
document.querySelector('#description .html-description').shadowRoot.querySelectorAll('img')
```

### 5.5 ★ URL kalıbı ve ORİJİNAL BOYUT

Base URL kalıbı:

```
https://cbu01.alicdn.com/img/ibank/{HASH}_!!{sellerUserId}-0-cib.jpg
                                    ▲ orijinal / tam boyut — SONEK YOK
```

**Alibaba CDN boyut sonekleri** (test edildi, hepsi 200 döndü):

| Sonek | Örnek | Boyut |
|---|---|---|
| *(yok)* | `...-cib.jpg` | **220.348 B — ORİJİNAL** ★ |
| `.220x220.jpg` | `...-cib.220x220.jpg` | 25.853 B |
| `.310x310.jpg` | `...-cib.310x310.jpg` | — |
| `.400x400.jpg` | `...-cib.400x400.jpg` | 71.160 B |
| `_q60.jpg` | `...-cib.jpg_q60.jpg` | 76.195 B (kalite 60) |
| `_.webp` | `...-cib.jpg_.webp` | 129.982 B (webp) |
| `.search.jpg` / `.summ.jpg` | — | küçük önizleme |

**Orijinal boyutu elde etme kuralı — sonekleri sil:**

```js
function toOriginal(url) {
  return url
    .replace(/_\.webp$/i, '')                  // _.webp
    .replace(/_q\d{2,3}\.jpg$/i, '')           // _q60.jpg, _q90.jpg
    .replace(/\.\d+x\d+(?:q\d+)?\.jpg$/i, '')  // .220x220.jpg, .400x400q90.jpg
    .replace(/\.(search|summ)\.jpg$/i, '')     // .search.jpg / .summ.jpg
    .split('?')[0];                            // query string
}
// "https://.../O1CN01MNNNmK...-cib.jpg_.webp" → "https://.../O1CN01MNNNmK...-cib.jpg"
```

> ⚠️ **DOM'dan görsel toplarsanız `_.webp` sonekiyle gelirler** (sayfa lazy-load'da webp kullanıyor). JSON'dan alırsanız zaten temizdir → **JSON'u tercih edin.**

---

## A.6 AÇIKLAMA (商品详情) — Shadow DOM + harici CDN

### Kaynak URL

```js
M.offerDetail.detailUrl
// "https://itemcdn.tmall.com/1688offer/icoss215119754646c2e52eb9831372"
// aynısı: D.description.fields.detailUrl
```

### Dönen içerik (test edildi)

```
HTTP 200, 4.946 byte
var offer_details={"content":" ...HTML... "}
```

- JSONP benzeri bir **JS atama** (`var offer_details={...}`), saf JSON değil.
- İçinde **21 adet `<img>`** var (detay görselleri).

```js
const raw = await fetch(M.offerDetail.detailUrl).then(r => r.text());
const json = JSON.parse(raw.replace(/^\s*var\s+[\w$]+\s*=\s*/, '').replace(/;\s*$/, ''));
const descHtml = json.content;
const descImgs = [...descHtml.matchAll(/<img[^>]+src=["']([^"']+)["']/gi)].map(m => m[1]);
```

### Sayfadaki render yeri — ⚠️ SHADOW DOM

```
#description  (div[data-module="od_product_description"])
 └── .collapse-body
      └── .html-description  →  <v-detail-5>   ← CUSTOM ELEMENT
           └── #shadow-root (open)             ← ★ 6.753 karakter HTML, 21 <img>
```

```js
const host = document.querySelector('#description .html-description');  // <v-detail-5>
const imgs = [...host.shadowRoot.querySelectorAll('img')].map(i => i.src);
```

> `host.innerHTML.length === 0` — light DOM boş. Shadow root `open` olduğu için erişilebilir, ama **normal selector'lar shadow sınırını geçmez.**

---

## A.7 VİDEO

### Bu üründe video YOK

```js
M.offerDetail.wirelessVideo   // { "videoId": 0, "state": 0 }
D.gallery.fields.video        // { "videoId": 0, "state": 0 }
window.gallery.video          // aynı
```

### Video olan üründe ne beklenir

| Alan | Not |
|---|---|
| `M.offerDetail.wirelessVideo.videoId` | `0` değilse video var (Taobao video ID) |
| `M.offerDetail.wirelessVideo.state` | `0` = yok / hazır değil |
| Player | Sayfa `@ali/videox.js` + `react-player.js` yüklüyor (`g.alicdn.com/odt/web-based/18.0.1/??...`) — yani oynatıcı hazır bekliyor |
| Poster | Galeri ilk görseli (`mainImage[0]`) kullanılıyor |

### ⚠️ mp4/m3u8 doğrudan URL'si SSR JSON'da YOK

- `videoId` bir **referans**; gerçek dosya URL'si `cloud.video.taobao.com` üzerinden **ayrı bir istekle** çözülüyor.
- Video varsa `<video>` elementi mount olur → `document.querySelector('#gallery video')?.src` veya `.currentSrc` en pratik yol.
- Alternatif: video mount olduktan sonra Network'te `cloud.video.taobao.com/.../*.mp4` isteği görünür.
- **Birden fazla video:** `wirelessVideo` tekil bir obje — PC detay sayfasında **tek ana video** destekleniyor. Ek videolar açıklama HTML'i içinde `<video>` tag'i olarak gömülü olabilir (bu üründe yok).

**Parser stratejisi:**

```js
const hasVideo = M.offerDetail.wirelessVideo?.videoId && M.offerDetail.wirelessVideo.videoId !== 0;
// varsa DOM'dan yakala (galeri sekmesine tıklanınca mount olur)
const videoUrl = document.querySelector('#gallery video')?.currentSrc || null;
const poster   = document.querySelector('#gallery video')?.poster || window.gallery.mainImage[0];
```

---

## A.8 KATEGORİ

**Sayfada görsel breadcrumb YOK** (2026 layout'unda kaldırılmış). Kategori sadece JSON'da:

| Alan | Yol | Değer |
|---|---|---|
| Yaprak kategori ID | `M.offerDetail.leafCategoryId` | `122700009` |
| Yaprak kategori adı | `M.offerDetail.leafCategoryName` | `榨汁机` (meyve sıkacağı) |
| 2. seviye kategori ID | `M.offerDetail.secondCategoryId` | `653` |
| Üst kategori ID | `M.offerDetail.topCategoryId` | `6` |
| Tekrar (açıklama modülü) | `D.description.fields.leafCategoryId` | `122700009` |
| CPV gösterimi | `M.detailBusiness.showCpvCategory` | `true` |

> Kategori **adları** sadece yaprak için var. Üst kategori adlarını istiyorsanız ID→ad eşlemesini kendi tarafınızda tutmalısınız.

---

## A.9 SATICI

```js
M.sellerModel = {
  companyName: "中山市初遇电器厂",
  loginId:     "中山市初遇电器厂",
  memberId:    "b2b-2213973763094859b8",
  userId:      2213973763094,
  winportUrl:  "https://shop388f6392493n6.1688.com",
  sellerIdentity: "default",
  sellerWinportUrlMap: {
    defaultUrl:       "https://shop388f6392493n6.1688.com",
    indexUrl:         "https://shop388f6392493n6.1688.com/page/index.html",
    offerlistUrl:     "https://shop388f6392493n6.1688.com/page/offerlist.html",
    newofferlistUrl:  "https://shop388f6392493n6.1688.com/page/newofferlist.html",
    contactinfoUrl:   "https://shop388f6392493n6.1688.com/page/contactinfo.html",
    creditdetailUrl:  "https://shop388f6392493n6.1688.com/page/creditdetail.html",
    shopdynamicUrl:   "https://shop388f6392493n6.1688.com/page/shopdynamic.html"
  },
  sellerSign: {
    tp: true,                    // 诚信通 üyesi
    signs: { isTp: true, isEaseBuyDealer: true, isFactoryDealer: true,
             isIndustrySeller: false, isSlsj: false, isChtMember: false }
  },
  sellerFeature: { brandShopTypeName: "" }
}
```

### Mağaza kartı / puanlar

```js
D.productTitle.fields.shopInfo = {
  companyName:           "中山市初遇电器厂",
  authCompanyName:       "中山市初遇电器厂",
  cardType:              "factory",        // ← FABRİKA (üretici) rozeti
  sellerSlrServiceScore: "4.0分",           // satıcı hizmet puanı
  byrRepeatRate3m:       "48.09%",          // 3 aylık alıcı tekrar oranı
  isPm: false, isPmPlus: false, isFollow: false
}
// aynısı: M.detailBusiness.shopBaseInfo
```

### Konum

```js
M.detailDescription.freightInfo.location             // "广东省中山市"
M.detailDescription.freightInfo.locationCode         // "33820112"
M.detailDescription.freightInfo.locationDivisionCode // "442000"   (resmi ilçe kodu)
D.shippingServices.fields.location                   // "广东省中山市"
D.shippingServices.fields.sendAddressCode            // "33820112"
```

---

## A.10 MOQ / STOK / KOLİ / AĞIRLIK

### MOQ (minimum sipariş)

```js
M.tradeModel.beginAmount   // 1
M.tradeModel.unit          // "个"
D.shippingServices.fields.startAmount   // 1
M.tradeModel.personLimitCount           // -1  (kişi başı limit yok)
M.tradeModel.promotionLimitCount
M.tradeModel.mixModel      // { supportMix: false, mixOneForSale: false }  ← karışık sipariş
```

### Stok

```js
M.tradeModel.canBookedAmount        // 19970  ← TOPLAM stok
M.tradeModel.skuMap[i].canBookCount // 9998 / 9972  ← VARYANT bazında stok
```

### Koli / ağırlık / ölçü — `productPackInfo`

```js
D.productPackInfo.fields.pieceWeightScale = {
  columnList: [
    { name: "sku1",   label: "颜色",     fid: 3216, precision: 0 },
    { name: "length", label: "长(cm)",   precision: 2 },
    { name: "width",  label: "宽(cm)",   precision: 2 },
    { name: "height", label: "高(cm)",   precision: 2 },
    { name: "volume", label: "体积(cm³)", precision: 3 },
    { name: "weight", label: "重量(g)",  precision: 0 }
  ],
  pieceWeightScaleInfo: [
    { skuId: 5745692521572, sku1: "Z03榨汁杯（7.4V双电池）", weight: 1000, length: 0, width: 0, height: 0, volume: 0 },
    { skuId: 5745692521573, sku1: "Z03榨汁杯（3.7V单电池）", weight: 1000, length: 0, width: 0, height: 0, volume: 0 }
  ]
}
D.productPackInfo.fields.unitWeight       // 0
D.productPackInfo.fields.pieceWeightScale // yukarıdaki
```

> Bu satıcı **ölçüleri girmemiş** (hepsi 0), sadece ağırlık 1000 g. Parser `0` değerlerini "veri yok" saymalı.

```js
M.detailDescription.freightInfo.skuWeight  // { "5745692521573": 1, "5745692521572": 1 }  ← KG cinsinden
```

> ⚠️ `pieceWeightScaleInfo.weight` **gram**, `freightInfo.skuWeight` **kilogram**. Karıştırmayın.

### Kargo

```js
D.shippingServices.fields = {
  location:          "广东省中山市",
  postFeeValue:      2,                // ¥2 kargo
  totalCost:         2,
  postFree:          false,
  freeDeliverFee:    false,
  freeEndAmount:     -1,
  deliveryFee:       "TEMPLATED",      // şablon bazlı
  templateId:        17818683,
  deliveryLimitText: "承诺48小时发货",   // 48 saatte gönderim taahhüdü
  targetLocation:    "选择配送地址为您计算运费及送达时间",
  isShowLogistics:   true,
  unitWeight: 0, minWeight: 0, volume: 0
}

M.detailBusiness.deliveryLimitTimeModel = {
  limitTimeDay: 2, limitTimeDesc: "承诺48小时发货", expectSendHour: 19,
  attrs: { offerUnit: "个", ptsOfferStepModels: [
    { selectedStartQuantity: 1,
      selectedPtsOfferTagModel: { serviceCode: "ssbxsfh", serviceName: "48小时发货", agreeDeliveryHours: 48 } }
  ]}
}
```

---

# B) SAYFADA BULUNAN DİĞER TÜM VERİLER (ENVANTER)

## B.1 `window.context` — üst düzey anahtarlar (4)

| Anahtar | İçerik | Örnek |
|---|---|---|
| `result` | ★ Tüm sayfa verisi | `{data, endpoint, global, hierarchy, reload}` |
| `panelConfig` | Render motoru sürüm bilgisi | `{appVersion, appBaseVersion, module}` |
| `module` | 25 UI modülünün npm paket tanımı | `"@ali/od-title"`, `"@ali/od-main-price"` … |
| `version` | Şablon sürümü | `"0.26.12"` |

## B.2 `context.result` — 5 anahtar

| Anahtar | İçerik |
|---|---|
| `data` | 35 UI modülü verisi (aşağıda) |
| `global.globalData` | `traceId`, `parametersMap`, `model` ★ |
| `hierarchy` | `{root:"Root", structure:{...}}` — layout ağacı |
| `endpoint` | Boş/servis meta |
| `reload` | Yeniden yükleme bayrağı |

**`hierarchy.structure` (sayfa iskeleti):**

```json
{
  "Root":   ["headers","screen","bottom"],
  "screen": ["product","cart"],
  "product":["shopNavigation","gallery","navTabs"],
  "navTabs":["chromePlugin","productEvaluation","officialInspection","productAttributes",
             "productWarning","sizeChart","productPackInfo","description"],
  "cart":   ["productTitle","promotionBanner","mainPrice","Row","shippingServices",
             "mainServices","skuSelection","customMade","submitOrder","consign"],
  "Row":    ["discountCoupon","userRights"],
  "bottom": ["sameProduct","shopProductCombine","shopProductRecommend","widgets"]
}
```

## B.3 `globalData` — 3 anahtar

| Anahtar | İçerik | Örnek |
|---|---|---|
| `traceId` | İzleme ID'si | (rastgele) |
| `parametersMap` | Sayfa parametreleri | `{offerId:"895133432293", loginId:"tb688704032941", offerLoginId:"中山市初遇电器厂", hotSaleSkuId:"5745692521572", spm:"a260k.home2025/2026.recommendpart.216"}` |
| `model` | ★ Ham iş modeli (14 anahtar) | aşağıda |

## B.4 `globalData.model` — 14 üst düzey anahtar ★ TAM LİSTE

| # | Anahtar | İçeriği | Örnek değer / alt anahtarlar |
|---|---|---|---|
| 1 | `offerDetail` | Ürünün kendisi | `offerId, subject, status:"PUBLISHED", offerType:"fashion", imageList[7], mainImageList[5], skuProps[1], skuFeatures, featureAttributes[31], offerSystemAttributes, leafCategoryId, leafCategoryName, secondCategoryId, topCategoryId, detailUrl, wirelessVideo, offerSign, offerMemberTags[113], xoneMaterial{}` |
| 2 | `tradeModel` | Fiyat + SKU + stok + satış | `minPrice"48.00", maxPrice"58.00", priceDisplay"48.00-58.00", beginAmount 1, unit"个", saleCount 10, canBookedAmount 19970, skuMap[2], offerPriceModel, skuTradeSupported true, hasPromotion true, mixModel, personLimitCount -1, promotionLimitCount, priceDisplay, tradeWithoutPromotion, offerIDatacenterSellInfo, offerId, supportWirelessOnly` |
| 3 | `sellerModel` | Satıcı | `companyName, loginId, memberId, userId, winportUrl, sellerWinportUrlMap, sellerSign, sellerIdentity, sellerFeature` |
| 4 | `detailBusiness` | İş metrikleri | `favorCount 7, hotSaleSkuId, shopBaseInfo, rateInfo, otherDiamondDataMap, deliveryLimitTimeModel, buyerProtectionModel, buyerProtectionScene"dsc", offerSupply"xh", showCpvCategory true, matchAiMateCategory` |
| 5 | `detailDescription` | Açıklama + kargo + banner | `images[], texts[], urls{}, dataDesc{payOrder30DayStr:"0"}, freightInfo, pieceWeightScale, deliveryLimit 2, crossBorderInfos{kjPCDistributionName:"跨境铺货"}, banners{5 slot}, financeInfo, protectionInfoList, officialDocInfos, abTestInfo, exposeInfo, shareActivityInfo` |
| 6 | `promotionModel` | Promosyon | `aggregatePromotionInfo, aggregateNewPromotionInfo, bigPromotionInfo, countdown, startTime, endTime, hotTime, disStr, promotionExtendInfo, promotionExtends, promotionFeature, promotionSign` |
| 7 | `consignModel` | Dropshipping / dağıtım | `consignOffer false, hasConsignPrice false, supportDistribution true, distributeChannels[8]: 淘宝/抖音/快手/微信/Lazada/Amazon/Shopify/Shopee` |
| 8 | `buyerModel` | Giriş yapan kullanıcı | `loginId, memberId, userId, buyerLevel, gradeLevel, vipBuyer, memberRightModel, buyerOnlineCrowds, buyerOfflineCrowds, buyerExtendInfo, buyerSign, mainAccountLoginId` |
| 9 | `channelType` | Kanal | `"dsc"` |
| 10 | `channelBizType` | Kanal iş tipi | — |
| 11 | `sceneType` | Sahne | `"default"` |
| 12 | `sceneBizType` | Sahne iş tipi | — |
| 13 | `verticalType` | Dikey pazar | `"default"` |
| 14 | `verticalBizType` | Dikey iş tipi | — |

## B.5 `context.result.data` — 35 UI modülü ★ TAM LİSTE

| # | Modül ID | `type` (data-module) | `fields` içeriği | SSR'da veri var mı? |
|---|---|---|---|---|
| 1 | `productTitle` | `od_title` | title, saleNum "10+", newSaleCount "已售", saleCountDate "一年内", unit, labels ["一件代发"], tagList[], scrollInfo[], shopInfo, rateInfo, isBestOffer, editUrl, officialActivityIcon | ✅ |
| 2 | `mainPrice` | `od_main_price` | priceModel, finalPriceModel, originalPricesWithoutPromotion, unit, displayType, isVipPrice/isPlusPrice/isBmPrice/isPrivatePrice, activityModel, promotionData, bigPromotion, bigPromotionInfo, memberRightModel, marketScene, secondaryAtmosphere, trackInfo | ✅ |
| 3 | `skuSelection` | `od_sku_selection` | *(sadece uiType, label)* — veri `M.tradeModel.skuMap`'ten okunuyor | ⚠️ modül boş, veri model'de |
| 4 | `gallery` | `od_picture_gallery` | offerImgList[7], mainImage[5], subject, video{videoId:0}, canShowAllImage true, offerId, isBestOffer, isBestOfferUrl, consignAiMate, CpvEnhance | ✅ |
| 5 | `shippingServices` | `od_shipping_services` | location, targetLocation, postFeeValue 2, totalCost 2, postFree, deliveryFee "TEMPLATED", templateId, deliveryLimitText, deliveryLimitTimeModel, freightInfo, logistics, officialLogistics, protectionInfos[4], buyerProtectionModel, buyerProtectionScene, startAmount, freeEndAmount, unitWeight, skuWeight($ref), volume, unit, price, trackInfo | ✅ |
| 6 | `productPackInfo` | `od_product_pack_info` | pieceWeightScale{columnList[6], pieceWeightScaleInfo[2]}, unitWeight, pieceWeightScale | ✅ |
| 7 | `productAttributes` | `od_product_attributes` | *(sadece uiType, label "商品参数")* — veri `M.offerDetail.featureAttributes` | ⚠️ modül boş, veri model'de |
| 8 | `description` | `od_product_description` | detailUrl, leafCategoryId, isControlKnifeOffer false, bigPromotionBanner | ✅ (HTML harici) |
| 9 | `productEvaluation` | `od_product_evaluation` | *(sadece uiType, label)* | ❌ **XHR** |
| 10 | `discountCoupon` | `od_discount_coupon` | couponInfoList[1] "红包最高减15元" ¥15, promotionModel.promotionList[], couponList[], newCouponList[], buttonName, linkUrl, bgColor, style, offerId, pageName, trackInfo | ✅ |
| 11 | `userRights` | `od_user_rights` | isShow true, message **"0元下单，货到满意再付款"**, targetUrl, operateType, hidePlusMonthCard, testParam, trackInfo | ✅ |
| 12 | `mainServices` | `od_services` | guaranteeList[4]: `thbyf_s`(退货包运费), `qtwlybt`(7天无理由退货), `ssbxsfh`(晚发必赔), `lsjst_s`(极速退款) — her biri {serviceName, description, serviceCode, serviceLink, textColor}, trackInfo | ✅ |
| 13 | `submitOrder` | `od_submit_order` | isSkuOffer true, pstatus "onsale", operateItems[3]: 立即下单 / 加采购车 / 跨境铺货, businessType, marketScene, isPicPrivate false, isPricePrivate false, freightInfo, remindText, remindSceneKey, isAddCartPromotion, channelType, componentVersion | ✅ |
| 14 | `consign` | `od_consign` | *(sadece uiType, label "分销组件")* → DOM'daki 密文代发 paneli | ❌ **XHR** |
| 15 | `productWarning` | `od_product_warning` | isCautionOffer, cautionText, officialDocInfos | ✅ |
| 16 | `promotionBanner` | `od_promotion_banner` | bannerImage | ✅ |
| 17 | `sizeChart` | `od_size_chart` | *(uiType, label)* | ❌ XHR / boş |
| 18 | `officialInspection` | `od_official_inspection` | *(uiType, label)* | ❌ XHR / boş |
| 19 | `customMade` | `od_custom_made` | *(uiType, label)* | ❌ XHR / boş |
| 20 | `sameProduct` | `od_same_product` | *(uiType, label)* → "benzer ürünler" | ❌ **XHR (recommend API)** |
| 21 | `shopProductCombine` | `od_shop_product_combine` | *(uiType, label)* → satıcının diğer ürünleri | ❌ **XHR** |
| 22 | `shopProductRecommend` | `od_shop_product_recommend` | *(uiType, label)* | ❌ **XHR** |
| 23 | `shopNavigation` | `od_shop_navigation` | *(uiType, label)* → mağaza başlığı, puanlar | ⚠️ kısmen model'den |
| 24 | `chromePlugin` | `od_chrome_plugin` | *(uiType, label)* | — |
| 25 | `widgets` | `od_widgets` | *(uiType, label)* → sağdaki yüzen çubuk | — |
| 26 | `navTabs` | `od_nav_tabs` | *(uiType, label)* → sekme çubuğu | — |
| 27 | `headers` | `od_header` | uiType, label, experimentGroup | — |
| 28 | `cart` | `od_cart_sider` | partition [2,1] → sağ sütun grid | — |
| 29 | `orderPanel` | — | layout | — |
| 30 | `orderGallery` | — | layout | — |
| 31 | `screen` | `od_region` | className, label | layout |
| 32 | `product` | `od_region` | — | layout |
| 33 | `bottom` | `od_region` | — | layout |
| 34 | `Row` | `od_row` | — | layout |
| 35 | `Root` | — | kök | layout |

## B.6 İSTEDİĞİNİZ ÖZEL ALANLAR — nerede bulundu

| İstenen | Yol | Değer |
|---|---|---|
| **Satış adedi** | `M.tradeModel.saleCount` | `10` |
| Satış adedi (gösterim) | `D.productTitle.fields.saleNum` | `"10+"` |
| Satış dönemi | `D.productTitle.fields.saleCountDate` | `"一年内"` (son 1 yıl) |
| Satış etiketi | `D.productTitle.fields.newSaleCount` | `"已售"` |
| 30 günlük ödenmiş sipariş | `M.detailDescription.dataDesc.payOrder30DayStr` | `"0"` |
| **Favori sayısı** | `M.detailBusiness.favorCount` | `7` |
| **Değerlendirme puanı** | `D.productTitle.fields.rateInfo.goodsGrade` | `5` (5.0 yıldız) |
| **İyi yorum oranı** | `D.productTitle.fields.rateInfo.goodRates` | `99.4` (%) |
| **Yorum sayısı** | `rateInfo.commonTagNodeList[]` | `全部:538, 有图:176, 好评:538, 最新:16` |
| Yorum etiketleri | `rateInfo.impressionTagNodeList[]` | `质量很好:45, 服务不错:36, 性价比高:23, 发货很快:22, 操作简单:16, 质感很好:15, …` (12 etiket) |
| Yorum metinleri | ❌ SSR'da yok | DOM: `#productEvaluation` → `"凉**爱 … 外观很好看，颜值巨高…"` |
| **Spec tablosu** | `M.offerDetail.featureAttributes[]` | 31 alan (aşağıda) |
| **Koli ölçü/ağırlık** | `D.productPackInfo.fields.pieceWeightScale` | ağırlık 1000 g, ölçüler 0 (girilmemiş) |
| SKU ağırlık (kg) | `M.detailDescription.freightInfo.skuWeight` | `{5745692521572:1, 5745692521573:1}` |
| **Kargo — nereden** | `freightInfo.location` / `locationDivisionCode` | `广东省中山市` / `442000` |
| **Kargo — süre** | `deliveryLimitTimeModel.limitTimeDesc` | `承诺48小时发货` (48 saat) |
| **Kargo — ücret** | `D.shippingServices.fields.postFeeValue` | `2` (¥2) |
| **Stok (toplam)** | `M.tradeModel.canBookedAmount` | `19970` |
| **Stok (varyant)** | `M.tradeModel.skuMap[i].canBookCount` | `9998` / `9972` |
| **Kademeli fiyat tablosu** | `M.tradeModel.offerPriceModel.currentPrices[]` | `[{beginAmount:1, price:"48.00"}, {beginAmount:1, price:"58.00"}]` |
| **Satıcı hizmet puanı** | `shopInfo.sellerSlrServiceScore` | `"4.0分"` |
| **Satıcı tekrar alım oranı** | `shopInfo.byrRepeatRate3m` | `"48.09%"` |
| Satıcı tipi | `shopInfo.cardType` | `"factory"` (fabrika) |
| 诚信通 üyeliği | `M.sellerModel.sellerSign.signs.isTp` | `true` |
| Satıcı yılı (TP年限) | ❌ SSR'da yok | DOM: `.shop-tp-year` |
| **Diğer ürün önerileri** | ❌ SSR'da yok | XHR: `mtop.relationrecommend.wirelessrecommend.recommend` |
| **Promosyon** | `D.discountCoupon.fields.promotionModel.promotionList[]` | `红包最高减15元` (¥15 kupon), `startTime:"Sun Aug 16 19:15:11 CST 2026"` |
| Ön ödemesiz sipariş | `D.userRights.fields.message` | `"0元下单，货到满意再付款"` |
| **Yayın tarihi** | `M.offerDetail.offerSystemAttributes.createDate` | `1740974210000` → **2025-03-03** (DOM: `2025年3月`) |
| Güncellenme | `offerSystemAttributes.modifyDate` | `1786272687000` |
| Onay tarihi | `offerSystemAttributes.approveDate` | `1740974212000` |
| Yayın tarihi | `offerSystemAttributes.postDate` | `1740974212000` |
| Son kullanma | `offerSystemAttributes.expireDate` | `2056334212000` |
| Satışta mı | `offerSystemAttributes.selling` / `M.offerDetail.status` | `true` / `"PUBLISHED"` |
| **Sınır ötesi (kargo)** | `M.detailDescription.crossBorderInfos` | `{kjPCDistributionName: "跨境铺货"}` |
| Dağıtım kanalları | `M.consignModel.distributeChannels[]` | 淘宝, 抖音, 快手, 微信, Lazada, Amazon, Shopify, Shopee |
| Ürün etiketleri | `D.productTitle.fields.labels` | `["一件代发"]` (tek parça dropship) |
| Öne çıkan SKU | `M.detailBusiness.hotSaleSkuId` | `"5745692521572"` (`skuFeatures` → `热销款`) |
| Satış noktası (CPV) | `M.tradeModel.offerIDatacenterSellInfo` | `{机身材质:"塑料", 是否内置电池:"内置电池", 刀头类型:"五刀头以上", 款式分类:"榨汁杯", 附加功能:"快速出浆", 加料口形状:"圆形", 内胆材质:"食品级PC"}` |
| Üye etiketleri | `M.offerDetail.offerMemberTags` | 113 adet sayısal etiket ID'si |
| Elmas veri | `M.detailBusiness.otherDiamondDataMap.knife[]` | 26 kategori kodu |

### Spec tablosu — `M.offerDetail.featureAttributes` (31 alan, tam liste)

```
fid 2176      品牌            = 总裁小姐
fid 1134      功率            = 40W/80W
fid 6311483   机身材质         = 塑料
fid 2340      容量            = 350ml
fid 122276388 是否内置电池      = 内置电池
fid 229106382 刀头类型         = 五刀头以上
fid 2061      内胆材质         = PC食品级塑料
fid 235544054 是否为食品级材质   = 是
fid 1141      功能            = 榨汁
fid 3660      转速            = 18000转/分
fid 203002001 附加功能         = 充电便携,快速出浆
fid 7119      上市时间         = 2023年夏
fid 9910      售后服务         = 店铺三包
fid 716       电压            = 3.7v|7.4v
fid 182318189 主要下游平台      = 其他
fid 3216      颜色            = Z03榨汁杯（7.4V双电池）,Z03榨汁杯（3.7V单电池）
fid 1398      货号            = GC-Z03
fid 148054856 一次性最大出油量   = 350ml
fid 100024874 一次性最大出汁量   = 350ml
fid 115996079 果肉渣滓盒容量     = 无
fid 6314148   加料口形状        = 圆形
fid 100933664 榨汁机最高转速     = 18000转/分
fid 100273971 包装清单         = 榨汁杯+吸管+充电线+说明书
fid 148406271 智能类型         = 不支持智能
fid 193290003 有可授权的自有品牌  = 是
fid 182282223 是否跨境出口专供货源 = 是
fid 143220385 是否专利货源      = 否
fid 250112493 外壳工艺         = ABS+PC塑料
fid 250459509 有无显示屏        = 无
fid 4926      款式分类         = 榨汁杯
```

Her eleman şu yapıda:

```json
{ "fid": 2176, "name": "品牌", "value": "总裁小姐", "values": ["总裁小姐"],
  "decisionValues": ["总裁小姐"], "itemCpvDecision": true,
  "isSpecial": false, "lectotype": false, "outputType": 0 }
```

> `value` string (çoklu değerde virgülle birleşik), `values` dizi. **Diziyi kullanın.**

## B.7 Diğer global değişkenler (window)

| Değişken | İçerik | Parser için değeri |
|---|---|---|
| `window.context` | ★ Ana veri | **YÜKSEK** |
| `window.gallery` | Galeri kısayolu (`context.result.data.gallery.fields`) | Orta |
| `window.contextPath` | `"/default"` | Düşük |
| `window.lib.mtop` | MTOP istemcisi (`request()` fonksiyonu var) | XHR replay için faydalı |
| `window.GL_PAGE_ID` | `"PC-DEFAULT-2026"` | Şablon sürüm tespiti |
| `window.od_global_config` | `{request:{logger:"none"}}` | Düşük |
| `window.preRequestPromiseMap` | `{getShopcard: Promise}` | Prefetch edilen istek |
| `window.g_config`, `FE_GLOBALS`, `goldlog`, `dmtrack`, `g_SPM`, `ali_analytics`, `__launch__tracker__`, `__user_vitals_sdk__` | Analytics / izleme | Yok |
| `window.__SECRET_MANIFEST_INTERNAL_USE_ONLY` | Modül manifest'i | Yok |

---

## 14. SAYFA DIŞI VERİ — XHR / API ÇAĞRILARI

Sayfa yüklendikten sonra **6 veri isteği** yapılıyor (75 network isteğinin geri kalanı JS/CSS/görsel):

| # | Metod | Endpoint | Ne getiriyor |
|---|---|---|---|
| 1 | **POST** | `https://h5api.m.1688.com/h5/mtop.1688.mmga.offerdetail.service/1.0/` | Ürün detay ek verileri (yorumlar, 复购率, 代发 paneli) |
| 2 | **GET (JSONP)** | `.../mtop.1688.mmga.offerdetail.service/1.0/?...&data={"mmgaRequest":{"serviceName":"profileReadFnService","channel":"DESIGN","loginId":"中山市初遇电器厂"}}` | **Mağaza kartı** (satıcı profili) |
| 3 | **POST** | `https://h5api.m.1688.com/h5/mtop.relationrecommend.wirelessrecommend.recommend/2.0/` | Benzer ürün önerileri (`sameProduct`) |
| 4 | **POST** | (aynı endpoint, 2. çağrı) | Satıcının diğer ürünleri (`shopProductCombine` / `shopProductRecommend`) |
| 5 | **GET** | `https://itemcdn.tmall.com/1688offer/icoss215119754646c2e52eb9831372` | **Açıklama HTML'i** (`var offer_details={"content":"…"}`, 21 görsel) |
| 6 | **GET** | `https://systemjs.1688.com/krump/schema/2753.json` | Şema/config |

### MTOP çağrısının anatomisi

```
https://h5api.m.1688.com/h5/{api}/{v}/
  ?jsv=2.7.4
  &appKey=12574478                ← sabit
  &t=1786893307036                ← timestamp (ms)
  &sign=ac6470d402bfa26f42a3791fe138a2b6   ← MD5(token&t&appKey&data)
  &api=mtop.1688.mmga.offerdetail.service
  &v=1.0
  &type=originaljson | jsonp
  &dataType=json | jsonp
  &timeout=20000
  &data={"mmgaRequest":{"serviceName":"...", ...}}
```

- `sign`, **cookie'deki `_m_h5_tk` token'ından** türetiliyor → dışarıdan taklit etmek zor.
- Eklenti içinden çağırmak isterseniz **sayfanın kendi istemcisini kullanın**:

```js
// MAIN world content script
const res = await window.lib.mtop.request({
  api: 'mtop.1688.mmga.offerdetail.service', v: '1.0',
  type: 'originaljson', dataType: 'json',
  data: { mmgaRequest: JSON.stringify({ serviceName: '...', offerId: '895133432293' }) }
});
```

> `serviceName` değerlerini bulmak için bundle'ı incelemek gerekiyor; tespit edilen tek örnek `profileReadFnService`.

### SSR'da OLMAYAN, sadece XHR/DOM'dan gelen veriler

| Veri | Nereden alınır |
|---|---|
| Yorum metinleri + yazar + tarih | DOM `#productEvaluation` (`.module-od-product-evaluation`) |
| `商品复购率 66.67%` | DOM `.module-od-title .sell-point-list span` |
| 密文代发 paneli: `商家代发热度 875`, `近30天代发数量 100以内`, `近7天代发数量 100以内`, `下游铺货数 100以内`, `铺货分销商数 7000+`, `代发品质达标率 100.00%`, `代发买家留货率 100.00%`, `商品发布时间 2025年3月` | DOM `#consign` |
| Benzer ürünler / mağaza önerileri | DOM `#sameProduct`, `#shopProductCombine`, `#shopProductRecommend` |
| Mağaza yılı, takipçi sayısı | DOM `#shopNavigation` (`.shop-data-item`) |
| Detay açıklama HTML'i | `M.offerDetail.detailUrl` fetch → Shadow DOM |

---

# 11. STRATEJİ ÖNERİSİ

## Karar: **Gömülü JSON birincil, DOM ikincil** — hibrit

| Kriter | Gömülü JSON (`window.context`) | DOM seçicileri |
|---|---|---|
| Kapsam | ~%90 (fiyat, SKU, stok, görsel, spec, kargo, satıcı) | %100 ama zahmetli |
| Kararlılık | **Yüksek** — alan adları API sözleşmesi | Orta |
| Hız | Tek okuma, senkron | Render bekleme gerekir |
| Temizlik | Ham değerler (`"48.00"`, sonek yok) | `¥`, `~`, `.00`, `_.webp` temizliği gerekir |
| Giriş gerektirir mi | Hayır (SSR'da geliyor) | Hayır |
| Eksikler | Yorum metni, 代发 paneli, öneriler | Bu boşlukları doldurur |

### Önerilen katmanlı akış

```
1. window.context oku (MAIN world)          → ürün çekirdeği (%90)
   └─ başarısız? → HTML'den regex ile çıkar  → aynı veri, JS'siz
2. detailUrl fetch et                        → açıklama HTML + 21 görsel
3. Shadow DOM oku (#description .html-description)  → alternatif detay görselleri
4. DOM oku (#productEvaluation, #consign, #shopNavigation)  → XHR-only alanlar
```

## Class adları kırılgan mı? — **HAYIR, iyi durumda**

Sayfada **251 farklı class** var. İnceleme sonucu:

✅ **Rastgele/hash'li CSS-module adı YOK.** Şu tarz isimler görülmedi: `css-1a2b3c`, `styles__wrapper--x7f9d`, `sc-bdVaJa`.

Class adları **semantik ve okunabilir**:

```
module-od-title, module-od-main-price, module-od-sku-selection,
module-od-picture-gallery, module-od-product-evaluation, module-od-cart-sider,
module-od-submit-order, module-od-product-description
od-picture-gallery-list, od-picture-gallery-section, od-collapse-module
shop-company-name, shop-data-item-value, shop-tp-year, sell-point-list
feature-item, feature-item-label, expand-view-item, item-price-stock
collapse-header, collapse-body, html-description, header-label-desc
```

### ★ EN SAĞLAM DOM ÇAPALARI: `id` + `data-module`

Her modülün kök elemanında **hem `id` hem `data-module`** var — bunlar SSR JSON'daki anahtar adlarıyla **birebir aynı**:

```html
<div id="productTitle"      data-module="od_title"                data-spm="...">
<div id="mainPrice"         data-module="od_main_price">
<div id="skuSelection"      data-module="od_sku_selection">
<div id="gallery"           data-module="od_picture_gallery">
<div id="productEvaluation" data-module="od_product_evaluation">
<div id="productAttributes" data-module="od_product_attributes">
<div id="productPackInfo"   data-module="od_product_pack_info">
<div id="description"       data-module="od_product_description">
<div id="shippingServices"  data-module="od_shipping_services">
<div id="consign"           data-module="od_consign">
<div id="shopNavigation"    data-module="od_shop_navigation">
<div id="sameProduct"       data-module="od_same_product">
```

```js
// class yerine bunu kullanın — çok daha dayanıklı:
document.querySelector('[data-module="od_product_evaluation"]')
document.querySelector('#productEvaluation')
```

### ⚠️ Dikkat edilecek kırılganlıklar

| Risk | Detay | Azaltma |
|---|---|---|
| **Sürüm damgalı isimler** | `body.od-version-2026`, `GL_PAGE_ID="PC-DEFAULT-2026"`, `v-sonoma`, `antd-sonoma`, `context.version="0.26.12"` | Yıl/sürüm içeren class'lara **bağlanmayın**; `data-module` kullanın |
| **Ant Design class'ları** | `ant-input-number`, `ant-rate-star`, `ant-image-img`, `anticon` | Kütüphane sürümüyle değişebilir; sadece son çare |
| **Shadow DOM** | `#description .html-description` → `<v-detail-5>` (custom element adı **sürümle değişir**: `v-detail-5` → `v-detail-6`) | Element adına değil, **`.shadowRoot` varlığına** bak: `host.shadowRoot ?? host` |
| **Lazy-load webp** | Detay görselleri `_.webp` sonekiyle DOM'a giriyor | `toOriginal()` fonksiyonuyla temizle |
| **`$ref` döngüleri** | JSON'da fastjson referansları | `resolveRefs()` çalıştır |
| **`number` offerId** | JSON'da number, URL'de string | Her zaman `String()` |
| **Boş modül `fields`** | `skuSelection`, `productAttributes` boş — veri `globalData.model`'de | **Her zaman `model`'i tercih edin**, `data.*.fields`'ı değil |
| **Bot koruması** | `baxia-entry-gray`, `AWSC_fyModule`, `AWSC_etModule` yükleniyor | Content script (gerçek tarayıcı oturumu) sorun yaşamaz; **sunucu tarafı scraping engellenebilir** |

---

## 12. HAZIR PARSER İSKELETİ

```js
// content_script.js  —  manifest: { "world": "MAIN", "matches": ["*://detail.1688.com/offer/*"] }

function parse1688() {
  const ctx = window.context;
  if (!ctx?.result?.global?.globalData?.model) return null;

  const M = resolveRefs(ctx, ctx.result.global.globalData.model);
  const D = ctx.result.data;
  const G = ctx.result.global.globalData;

  const toOriginal = u => (u || '')
    .replace(/_\.webp$/i, '')
    .replace(/_q\d{2,3}\.jpg$/i, '')
    .replace(/\.\d+x\d+(?:q\d+)?\.jpg$/i, '')
    .replace(/\.(search|summ)\.jpg$/i, '')
    .split('?')[0];

  const axes = M.offerDetail.skuProps || [];

  return {
    source: '1688',
    offerId: String(M.offerDetail.offerId),
    url: `https://detail.1688.com/offer/${M.offerDetail.offerId}.html`,
    status: M.offerDetail.status,                            // "PUBLISHED"

    title: M.offerDetail.subject,

    category: {
      leafId:   M.offerDetail.leafCategoryId,
      leafName: M.offerDetail.leafCategoryName,
      secondId: M.offerDetail.secondCategoryId,
      topId:    M.offerDetail.topCategoryId
    },

    price: {
      currency: 'CNY',
      min: parseFloat(M.tradeModel.minPrice),
      max: parseFloat(M.tradeModel.maxPrice),
      display: M.tradeModel.priceDisplay,
      type: M.tradeModel.offerPriceModel.priceDisplayType,   // "skuPrice" | ladder
      tiers: (M.tradeModel.offerPriceModel.currentPrices || [])
               .map(t => ({ from: t.beginAmount, price: parseFloat(t.price) })),
      original: (M.tradeModel.offerPriceModel.originalPrices || [])
               .map(t => ({ from: t.beginAmount, price: parseFloat(t.price) }))
    },

    moq: M.tradeModel.beginAmount,
    unit: M.tradeModel.unit,
    stockTotal: M.tradeModel.canBookedAmount,
    soldCount: M.tradeModel.saleCount,
    favorCount: M.detailBusiness?.favorCount,

    skuAxes: axes.map(a => ({
      fid: a.fid, name: a.prop,
      values: a.value.map(v => ({ name: v.name, image: toOriginal(v.imageUrl) }))
    })),

    skus: (M.tradeModel.skuMap || []).map(s => {
      const parts = String(s.specAttrs).split(/&gt;|&/).map(x => x.trim());
      const attrs = {};
      axes.forEach((ax, i) => { attrs[ax.prop] = parts[i]; });
      const w = M.detailDescription?.freightInfo?.skuWeight?.[s.skuId];
      const pk = D.productPackInfo?.fields?.pieceWeightScale?.pieceWeightScaleInfo
                   ?.find(p => p.skuId === s.skuId);
      return {
        skuId: String(s.skuId), specId: s.specId, name: s.specAttrs, attrs,
        price: parseFloat(s.discountPrice || s.price),
        listPrice: parseFloat(s.price),
        stock: s.canBookCount, sold: s.saleCount,
        isHot: String(s.skuId) === String(M.detailBusiness?.hotSaleSkuId),
        weightKg: w ?? (pk ? pk.weight / 1000 : null),
        dims: pk ? { l: pk.length, w: pk.width, h: pk.height, vol: pk.volume } : null
      };
    }),

    images: {
      main:    (window.gallery?.mainImage    || []).map(toOriginal),
      gallery: (window.gallery?.offerImgList || []).map(toOriginal),
      rich:    (M.offerDetail.imageList || []).map(i => ({
                 original: toOriginal(i.fullPathImageURI),
                 s220: i.size220x220ImageURI, s310: i.size310x310ImageURI
               }))
    },

    video: {
      hasVideo: !!(M.offerDetail.wirelessVideo?.videoId),
      videoId:  M.offerDetail.wirelessVideo?.videoId || null,
      state:    M.offerDetail.wirelessVideo?.state,
      // dosya URL'si için DOM'a bak:
      url:      document.querySelector('#gallery video')?.currentSrc || null,
      poster:   document.querySelector('#gallery video')?.poster || null
    },

    attributes: (M.offerDetail.featureAttributes || [])
      .map(a => ({ fid: a.fid, name: a.name, values: a.values || [a.value] })),

    seller: {
      companyName: M.sellerModel.companyName,
      memberId:    M.sellerModel.memberId,
      userId:      M.sellerModel.userId,
      shopUrl:     M.sellerModel.winportUrl,
      offerListUrl: M.sellerModel.sellerWinportUrlMap?.offerlistUrl,
      isTp:        !!M.sellerModel.sellerSign?.signs?.isTp,
      cardType:    D.productTitle?.fields?.shopInfo?.cardType,          // "factory"
      serviceScore: D.productTitle?.fields?.shopInfo?.sellerSlrServiceScore,
      repeatRate3m: D.productTitle?.fields?.shopInfo?.byrRepeatRate3m,
      location:    M.detailDescription?.freightInfo?.location
    },

    rating: {
      score:      D.productTitle?.fields?.rateInfo?.goodsGrade,
      goodRate:   D.productTitle?.fields?.rateInfo?.goodRates,
      counts:     D.productTitle?.fields?.rateInfo?.commonTagNodeList,
      impressions:D.productTitle?.fields?.rateInfo?.impressionTagNodeList
    },

    shipping: {
      from:       D.shippingServices?.fields?.location,
      fee:        D.shippingServices?.fields?.postFeeValue,
      freeShip:   D.shippingServices?.fields?.postFree,
      leadTime:   D.shippingServices?.fields?.deliveryLimitText,
      leadDays:   M.detailBusiness?.deliveryLimitTimeModel?.limitTimeDay,
      templateId: D.shippingServices?.fields?.templateId
    },

    promotions: D.discountCoupon?.fields?.promotionModel?.promotionList || [],

    dates: {
      created:  M.offerDetail.offerSystemAttributes?.createDate,
      modified: M.offerDetail.offerSystemAttributes?.modifyDate,
      posted:   M.offerDetail.offerSystemAttributes?.postDate,
      expires:  M.offerDetail.offerSystemAttributes?.expireDate
    },

    descriptionUrl: M.offerDetail.detailUrl,
    traceId: G.traceId
  };
}

// Açıklama görselleri — iki yol
async function getDescription(detailUrl) {
  // A) CDN'den
  const raw = await fetch(detailUrl).then(r => r.text());
  const json = JSON.parse(raw.replace(/^\s*var\s+[\w$]+\s*=\s*/, '').replace(/;\s*$/, ''));
  const html = json.content;
  const images = [...html.matchAll(/<img[^>]+src=["']([^"']+)["']/gi)].map(m => m[1]);
  return { html, images };
}

function getDescriptionFromShadow() {
  // B) Shadow DOM'dan (sayfa zaten render ettiyse)
  const host = document.querySelector('#description .html-description');
  const root = host?.shadowRoot || host;
  if (!root) return null;
  return {
    html: root.innerHTML,
    images: [...root.querySelectorAll('img')].map(i => i.src || i.getAttribute('data-src'))
  };
}
```

---

## 13. HIZLI REFERANS — Alan → Yol tablosu

| Veri | Yol |
|---|---|
| offerId | `context.result.global.globalData.model.offerDetail.offerId` |
| Başlık | `…model.offerDetail.subject` |
| Min/max fiyat | `…model.tradeModel.minPrice` / `.maxPrice` |
| Fiyat kademeleri | `…model.tradeModel.offerPriceModel.currentPrices[]` |
| SKU listesi | `…model.tradeModel.skuMap[]` |
| SKU eksenleri | `…model.offerDetail.skuProps[]` |
| Toplam stok | `…model.tradeModel.canBookedAmount` |
| MOQ | `…model.tradeModel.beginAmount` |
| Birim | `…model.tradeModel.unit` |
| Satış adedi | `…model.tradeModel.saleCount` |
| Ana görseller | `…model.offerDetail.mainImageList[].fullPathImageURI` |
| Tüm görseller | `window.gallery.offerImgList[]` |
| Varyant görselleri | `…model.offerDetail.skuProps[0].value[].imageUrl` |
| Video | `…model.offerDetail.wirelessVideo` |
| Kategori | `…model.offerDetail.leafCategoryId` / `.leafCategoryName` |
| Özellikler | `…model.offerDetail.featureAttributes[]` |
| Satıcı | `…model.sellerModel` |
| Mağaza kartı | `context.result.data.productTitle.fields.shopInfo` |
| Puan/yorum | `context.result.data.productTitle.fields.rateInfo` |
| Kargo | `context.result.data.shippingServices.fields` |
| Koli/ağırlık | `context.result.data.productPackInfo.fields.pieceWeightScale` |
| Kupon | `context.result.data.discountCoupon.fields.promotionModel` |
| Açıklama URL | `…model.offerDetail.detailUrl` |
| Tarihler | `…model.offerDetail.offerSystemAttributes` |
