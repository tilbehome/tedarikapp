# TedarikApp — 1688 tam ürün verisi envanteri

**Belge durumu:** Şartname önerisi; uygulama sırası PM + Ürün Sahibi kararıdır.  
**Araştırma kesiti:** 29 Ağustos 2026  
**Kapsam:** `detail.1688.com/offer/{id}.html` masaüstü ürün detayı; giriş yapılmış ve yapılmamış görünüm. Yorum içeriği V3-F kapsamındadır. Ham HAR, çerez, MTOP token/imza, alıcı hesabı ve oturum verisi kapsam dışıdır.

## 1. Karar özeti

1. Ürün kartı yalnız bugün kullanılan 16+ alanı değil, ürün/ilan/satıcı/sevkiyat/uyum ile ilgili gözlenebilen bütün alanları saklar. Liste, paylaşım ve dışa aktarımlar bu genişlemeden otomatik olarak etkilenmez.
2. Kimliği ve sorgu değeri bilinen alanlar tipli kolonlara; kategoriye göre değişen `产品属性` satırları ve yeni/bilinmeyen alanlar `raw_attributes` içine kayıpsız yazılır.
3. Kaynak önceliği **gömülü JSON → görünür DOM → sayfanın kendi yüklediği MTOP yanıtının pasif gözlemi** şeklindedir. Eklenti özel MTOP isteği kurmaz; çerez, token veya imza üretmez.
4. `buyerModel`, `traceId`, SPM/analitik kimlikler, alıcı adı/adresi/telefonu, kullanıcı yorum rumuzu ve diğer kişisel/oturum verileri yakalanmaz.
5. “Bulunamadı” sıfır değildir. Alan gözlenemediyse `null` saklanır ve arayüzde **—** gösterilir. Girişsiz veya kısmi yakalamada eksik alan, “kaynakta silindi” sayılmaz.

## 2. Okuma anahtarı

| Sütun | Değerler |
|---|---|
| Kapsam | **Ürün**: bütün ilan için; **SKU**: varyasyon/kombinasyon için; **Satıcı**: mağaza için; **İlan**: bu satıcının bu yayını için |
| Kaynak | **JSON**: `window.context.result.global.globalData.model` veya `result.data.*.fields`; **DOM**: kullanıcının açık sayfada gördüğü içerik; **MTOP**: sayfanın zaten yüklediği yanıtın pasif kopyası |
| Giriş | **Hayır**: oturumsuz görünümde beklenir; **Koşullu**: hesap, bölge, A/B şablonu veya teklif tipine göre; **Evet**: yalnız oturum/üyelik bağlamında beklenir |
| Değişkenlik | **Sabit**: ürün/SKU kimliği gibi; **Günlük**: satıcı/yorum/özellik gibi; **Anlık**: fiyat/stok/kampanya/ilan durumu gibi |
| Modül | Kart, CBM-lojistik, Satıcı karnesi, Skor, İzleme veya **Hiçbiri**. “Hiçbiri” saklanmaması değil, bugün hesap tüketicisi olmaması demektir. |

5B terim tablosunda bulunan karşılıklar aynen korunmuştur: Ürün Adı, Ürün Detayları, Kategori, Miktar, Minimum Sipariş, Varyasyon MOQ, Birim Fiyat, Hacim (CBM), Brüt Ağırlık, Net Ağırlık, Satıcı, Satıcı Yılı, Yorum Sayısı, Video, Ürün Görseli, Ürün Kodu, Ürün Ölçüleri, Beyan Stok, Koli Ölçüleri ve Birim Net Ağırlık. 5B'de bulunmayan kart-içi alanlar bu belgede aday terimdir; 5B'ye eklenmeden çıktı sözlüğü sayılmaz.

## 3. Veri envanteri

### 3.1 Kimlik, yayın ve kategori

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 商品ID / `offerId` | Kaynak İlan Kimliği | metin | İlan | JSON + URL | Hayır | Sabit | Kart, İzleme |
| 商品链接 | Kaynak Bağlantısı | metin | İlan | URL | Hayır | Sabit | Kart, İzleme |
| 商品标题 / `subject` | Ürün Adı (orijinal) | metin | İlan | JSON, DOM | Hayır | Günlük | Kart, Skor, İzleme |
| 商品状态 / `status` | İlan Durumu | metin/enum | İlan | JSON | Hayır | Anlık | Kart, İzleme |
| 是否在售 / `selling` | Satışta mı | boolean | İlan | JSON | Hayır | Anlık | İzleme |
| 商品类型 / `offerType` | İlan Türü | metin/enum | İlan | JSON | Hayır | Günlük | Kart |
| 货号 | Ürün Kodu | metin | Ürün | JSON (`产品属性`) | Hayır | Sabit | Kart |
| 型号 | Model | metin | Ürün | JSON (`产品属性`) | Hayır | Sabit | Kart |
| 商品条码 | Barkod | metin | Ürün/SKU | JSON (`产品属性`) | Koşullu | Sabit | Kart |
| 类目ID / `leafCategoryId` | Kaynak Kategori Kimliği | metin | İlan | JSON | Hayır | Günlük | Kart, Skor |
| 类目 / `leafCategoryName` | Kategori | metin | İlan | JSON | Hayır | Günlük | Kart, Skor |
| 一级/二级类目ID | Üst Kategori Kimlikleri | liste | İlan | JSON | Hayır | Günlük | Skor |
| 类目路径 / 面包屑 | Kategori Yolu | liste | İlan | JSON, DOM yedek | Koşullu | Günlük | Kart |
| 单位 / `unit` | Satış Birimi | metin | İlan | JSON | Hayır | Günlük | Kart, CBM-lojistik |
| 供货类型 / `offerSupply` | Tedarik Türü | metin/enum | İlan | JSON | Koşullu | Günlük | Kart |
| 渠道类型 / `channelType` | Platform Kanalı | metin/enum | İlan | JSON | Koşullu | Günlük | Hiçbiri |
| 最早上架时间 / `createDate` | İlk Yayın Tarihi | tarih | İlan | JSON, DOM | Hayır | Sabit | Kart, Skor |
| 发布时间 / `postDate` | Yayın Tarihi | tarih | İlan | JSON, DOM | Hayır | Günlük | Kart, Skor |
| 最新发布时间 / `modifyDate` | Son Güncellenme Tarihi | tarih | İlan | JSON, DOM | Hayır | Anlık | Kart, İzleme |
| 审核时间 / `approveDate` | Onay Tarihi | tarih | İlan | JSON | Koşullu | Günlük | Hiçbiri |
| 到期时间 / `expireDate` | İlan Bitiş Tarihi | tarih | İlan | JSON | Koşullu | Günlük | İzleme |
| 页面模板 / `GL_PAGE_ID` | Sayfa Şablonu | metin | İlan | DOM/JS global | Hayır | Günlük | Yakalama sağlığı |

### 3.2 Ürün özellikleri — `产品属性` / `商品参数`

Her `featureAttributes[]` öğesi `{fid, name, value, values[], decisionValues[], isSpecial, outputType}` olarak kaydedilir. `values[]` varsa kanonik ham değer odur; birleşik `value` yalnız gösterim yedeğidir. Aşağıdaki satırlar yaygın ve doğrulanmış örneklerdir; kategoriye özel hiçbir yeni etiket elenmez.

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 产品属性 / 商品参数 / `featureAttributes[]` | Ürün Özellikleri — tam tablo | tablo | Ürün | JSON, DOM yedek | Hayır | Günlük | Kart, Skor |
| `fid` | Kaynak Özellik Kimliği | metin | Ürün | JSON | Hayır | Sabit | Hiçbiri |
| 品牌 | Marka | metin/liste | Ürün | JSON | Hayır | Günlük | Kart, Skor |
| 材质 / 机身材质 / 内胆材质 | Malzeme / Gövde / İç Hazne Malzemesi | metin/liste | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 是否为食品级材质 | Gıdaya Uygun Malzeme | boolean/metin | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 功率 | Güç | sayı+birim/metin | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 电压 | Voltaj | sayı+birim/liste | Ürün/SKU | JSON | Hayır | Günlük | Kart, Uyum |
| 容量 | Kapasite | sayı+birim/metin | Ürün/SKU | JSON | Hayır | Günlük | Kart |
| 功能 / 附加功能 | İşlev / Ek İşlev | liste | Ürün | JSON | Hayır | Günlük | Kart, Skor |
| 尺寸 / 规格 | Ürün Ölçüleri / Teknik Ölçü | metin/liste | Ürün/SKU | JSON | Hayır | Günlük | Kart, CBM-lojistik |
| 重量 / 净重 / 毛重 | Ağırlık / Net Ağırlık / Brüt Ağırlık | sayı+birim | Ürün/SKU | JSON | Hayır | Günlük | Kart, CBM-lojistik |
| 颜色 | Renk | liste | Ürün/SKU | JSON | Hayır | Günlük | Kart |
| 款式 / 款式分类 | Model/Stil Sınıfı | metin/liste | Ürün | JSON | Hayır | Günlük | Kart |
| 包装清单 | Paket İçeriği | liste/metin | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 包装方式 / 包装规格 | Paketleme Biçimi | metin | Ürün/SKU | JSON | Koşullu | Günlük | Kart, CBM-lojistik |
| 产地 / 原产地 / 货源地 | Menşe / Kaynak Bölge | metin | Ürün | JSON | Koşullu | Günlük | Kart, Uyum |
| 上市时间 | Piyasaya Çıkış Zamanı | tarih/metin | Ürün | JSON | Hayır | Günlük | Kart, Skor |
| 售后服务 | Satış Sonrası Hizmet | metin | Ürün | JSON | Hayır | Günlük | Kart, Satıcı karnesi |
| 主要下游平台 | Başlıca Alt Pazar Platformları | liste | Ürün | JSON | Hayır | Günlük | Kart, Skor |
| 主要销售地区 / 销售地区 | Başlıca Satış Bölgeleri | liste | Ürün | JSON | Koşullu | Günlük | Kart, Skor |
| 是否跨境出口专供货源 | Sınır Ötesi İhracata Özel Tedarik | boolean | Ürün | JSON | Hayır | Günlük | Kart, Skor |
| 有可授权的自有品牌 | Yetkilendirilebilir Kendi Markası | boolean | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 是否专利货源 | Patentli Tedarik | boolean | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 定制 / 加工定制 | Özelleştirme / Üretim Özelleştirme | boolean/metin | Ürün | JSON, DOM | Koşullu | Günlük | Kart |
| `offerIDatacenterSellInfo` | Platform Satış Noktaları | anahtar-değer | Ürün | JSON | Koşullu | Günlük | Skor |
| Bilinmeyen yeni `name` | Kaynak Etiketiyle Yeni Özellik | metin/liste/tablo | Ürün/SKU | JSON, DOM | Koşullu | Günlük | Kart veya Hiçbiri |

### 3.3 SKU, stok ve `商品件重尺`

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 规格 / `skuProps[]` | Varyasyon Eksenleri | liste | Ürün | JSON | Hayır | Günlük | Kart |
| 规格值 / `value[]` | Varyasyon Değerleri | liste | SKU | JSON | Hayır | Günlük | Kart |
| `skuId` | SKU Kimliği | metin | SKU | JSON | Hayır | Sabit | Kart, İzleme |
| `specId` | Spesifikasyon Kimliği | metin | SKU | JSON | Hayır | Sabit | İzleme |
| `specAttrs` | SKU Adı / Özellik Birleşimi | metin | SKU | JSON | Hayır | Günlük | Kart |
| 规格图片 / `imageUrl` / `pic` | SKU Görseli | metin/URL | SKU | JSON | Hayır | Günlük | Kart, İzleme |
| 规格价 / `price` | SKU Birim Fiyatı | para | SKU | JSON | Hayır | Anlık | Kart, İzleme |
| 促销价 / `discountPrice` | SKU İndirimli Fiyatı | para | SKU | JSON | Koşullu | Anlık | Kart, İzleme |
| 规格起订量 | Varyasyon MOQ | sayı | SKU | JSON | Koşullu | Anlık | Kart, İzleme |
| 可订购数量 / `canBookCount` | Beyan Stok | sayı | SKU | JSON | Hayır | Anlık | Kart, İzleme |
| SKU销量 / `saleCount` | SKU Satış Adedi | sayı | SKU | JSON | Koşullu | Günlük | Skor |
| 热销款 / `hotSaleSkuId` | Öne Çıkan SKU | metin/boolean | SKU | JSON | Koşullu | Günlük | Kart, Skor |
| 总库存 / `canBookedAmount` | Toplam Beyan Stok | sayı | İlan | JSON | Hayır | Anlık | Kart, İzleme |
| 商品件重尺 / `pieceWeightScaleInfo[]` | SKU Ölçü-Ağırlık Tablosu | tablo | SKU | JSON, DOM yedek | Hayır | Günlük | Kart, CBM-lojistik |
| 长 / `length` | Uzunluk | sayı+birim | SKU | JSON, DOM | Hayır | Günlük | CBM-lojistik |
| 宽 / `width` | Genişlik | sayı+birim | SKU | JSON, DOM | Hayır | Günlük | CBM-lojistik |
| 高 / `height` | Yükseklik | sayı+birim | SKU | JSON, DOM | Hayır | Günlük | CBM-lojistik |
| 单件重量 / `weight` | Birim Net Ağırlık | sayı+birim | SKU | JSON, DOM | Hayır | Günlük | CBM-lojistik |
| 体积 / `volume` | Hacim (CBM) | sayı+birim | SKU | JSON, DOM | Hayır | Günlük | CBM-lojistik |
| 运费计重 / `unitWeight` / `skuWeight` | Kargo Hesap Ağırlığı | sayı+birim/tablo | SKU | JSON | Koşullu | Günlük | CBM-lojistik |
| 装箱数量 | Koli İçi Adet | sayı | SKU/Ürün | `产品属性` veya açıklama; yapılandırılmış alan garanti değil | Koşullu | Günlük | CBM-lojistik |

`pieceWeightScaleInfo[]` boş dizi gelebilir. Bu durum “0 kg / 0 cm” değildir. Koli içi adet ayrı, güvenilir bir çekirdek alan değildir; özellik tablosu/açıklama içinde bulunursa ham değer olarak alınır, aksi durumda kullanıcı girişi korunur.

### 3.4 Fiyat, MOQ, kampanya ve dağıtım

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 起订量 / `beginAmount` | Minimum Sipariş | sayı | İlan | JSON | Hayır | Anlık | Kart, İzleme |
| 阶梯价 / `currentPrices[]` | Kademeli Fiyat | tablo | İlan/SKU | JSON, DOM yedek | Hayır | Anlık | Kart, İzleme |
| 最低价 / `minPrice` | En Düşük Birim Fiyat | para | İlan | JSON | Hayır | Anlık | Kart, İzleme |
| 最高价 / `maxPrice` | En Yüksek Birim Fiyat | para | İlan | JSON | Hayır | Anlık | Kart, İzleme |
| 原价 / `originalPrices` | İndirimsiz Fiyat | para/tablo | İlan/SKU | JSON | Koşullu | Anlık | Kart, İzleme |
| 币种 | Para Birimi | metin/ISO | İlan | Platform + JSON | Hayır | Sabit | Kart |
| 混批 / `mixModel` | Karışık Sipariş Koşulları | tablo | İlan | JSON | Koşullu | Anlık | Kart |
| 限购数量 / `personLimitCount` | Kişi Başına Limit | sayı | İlan | JSON | Koşullu | Anlık | Kart |
| 活动限购 / `promotionLimitCount` | Kampanya Adet Limiti | sayı | İlan/SKU | JSON | Koşullu | Anlık | Kart, İzleme |
| 限时折扣 | Süreli İndirim | boolean/metin | İlan/SKU | JSON, DOM | Koşullu | Anlık | Kart, İzleme |
| 活动价 / `promotionPrices` | Kampanya Fiyatı | para/tablo | İlan/SKU | JSON | Koşullu | Anlık | Kart, İzleme |
| 活动开始/结束时间 | Kampanya Başlangıç/Bitiş | tarih aralığı | İlan | JSON | Koşullu | Anlık | Kart, İzleme |
| 倒计时 / `countdown` | Kampanya Geri Sayımı | sayı/tarih | İlan | JSON, DOM | Koşullu | Anlık | Kart |
| 优惠券 / `couponInfoList[]` | Kuponlar | liste/tablo | İlan | JSON | Koşullu | Anlık | Kart |
| 红包最高减 | Azami İndirim Tutarı | para | İlan | JSON | Koşullu | Anlık | Kart |
| 会员价 / PLUS价 / 私密价 | Üye / Plus / Özel Fiyat | para | İlan/SKU | JSON, DOM | Evet | Anlık | Kart, İzleme |
| 0元下单 / 先采后付 | Ön Ödemesiz Sipariş Hakkı | metin/boolean | İlan | JSON, DOM | Evet | Anlık | Kart |
| 一件代发 / `consignOffer` | Tekli Dropshipping | boolean | İlan | JSON, DOM | Koşullu | Günlük | Kart, Skor |
| 分销代发 / `consignModel` | Dağıtım ve Dropshipping | nesne | İlan | JSON + MTOP + DOM | Koşullu | Günlük | Kart, Skor |
| 商家代发热度 | Satıcı Dropshipping Popülerliği | sayı/metin | İlan/Satıcı | MTOP, DOM | Evet | Günlük | Skor |
| 近30天代发数量 | Son 30 Gün Dropshipping Adedi | sayı/aralık | İlan | MTOP, DOM | Evet | Günlük | Skor |
| 近7天代发数量 | Son 7 Gün Dropshipping Adedi | sayı/aralık | İlan | MTOP, DOM | Evet | Günlük | Skor |
| 下游铺货数 | Alt Kanallara Listelenme Sayısı | sayı/aralık | İlan | MTOP, DOM | Evet | Günlük | Skor |
| 铺货分销商数 | Dağıtıcı Sayısı | sayı/aralık | İlan | MTOP, DOM | Evet | Günlük | Skor |
| 代发品质达标率 | Dropshipping Kalite Başarı Oranı | yüzde | İlan/Satıcı | MTOP, DOM | Evet | Günlük | Satıcı karnesi, Skor |
| 代发买家留货率 | Dropshipping Alıcı Elde Tutma Oranı | yüzde | İlan/Satıcı | MTOP, DOM | Evet | Günlük | Satıcı karnesi, Skor |
| 分销渠道 / `distributeChannels[]` | Dağıtım Kanalları | liste | İlan | JSON | Koşullu | Günlük | Kart, Skor |
| 跨境铺货 / `crossBorderInfos` | Sınır Ötesi Listeleme | metin/nesne | İlan | JSON | Koşullu | Günlük | Kart, Skor |

### 3.5 Satış, değerlendirme ve ilgi sinyalleri

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 已售 / `saleCount` | Satış Adedi | sayı | İlan | JSON | Hayır | Günlük | Kart, Skor, İzleme |
| 销量展示 / `saleNum` | Gösterilen Satış Metni | metin | İlan | JSON, DOM | Hayır | Günlük | Kart |
| 销售周期 / `saleCountDate` | Satış Dönemi | metin | İlan | JSON | Hayır | Günlük | Skor |
| 近30天支付订单 / `payOrder30DayStr` | Son 30 Gün Ödenen Sipariş | sayı/metin | İlan | JSON | Koşullu | Günlük | Skor |
| 收藏数 / `favorCount` | Favori Sayısı | sayı | İlan | JSON | Koşullu | Günlük | Skor |
| 评价 / `goodsGrade` | Değerlendirme Puanı | sayı | İlan | MTOP özeti | Koşullu | Günlük | Kart, Skor |
| 评价数 / 全部 | Yorum Sayısı | sayı | İlan | MTOP özeti | Koşullu | Günlük | Kart, Skor |
| 好评率 / `goodRates` | Olumlu Yorum Oranı | yüzde | İlan | MTOP özeti | Koşullu | Günlük | Kart, Skor |
| 评价标签 / `impressionTagNodeList` | Değerlendirme Etiketleri | liste+adet | İlan | MTOP özeti | Koşullu | Günlük | Kart, Skor |
| 有图/最新等标签 | Değerlendirme Dağılımı | tablo | İlan | MTOP özeti | Koşullu | Günlük | Kart |
| 评价内容 / `queryItemRatedListV2` | Yorum İçeriği | liste | İlan | MTOP | Evet/Koşullu | Günlük | **V3-F; bu görevde saklanmaz/işlenmez** |

Gömülü `rateInfo` bazı şablonlarda bulunabilse de ilanlar arası kirli/bayat veri riski nedeniyle doğrulanmış kaynak sayılmaz. Puan ve yorum sayısı için yalnız sayfanın ilgili ilan adına yüklediği MTOP özet yanıtı kabul edilir. Yorum rumuzu ve yorum içeriği Görev #32 yüküne dahil edilmez.

### 3.6 Satıcı ve mağaza karnesi

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 公司名称 / `companyName` | Satıcı | metin | Satıcı | JSON, DOM | Hayır | Günlük | Kart, Satıcı karnesi |
| 店铺链接 / `winportUrl` | Mağaza Bağlantısı | metin/URL | Satıcı | JSON | Hayır | Günlük | Kart |
| `loginId` / `memberId` / `userId` | Platform Satıcı Kimlikleri | metin | Satıcı | JSON | Hayır | Sabit | Satıcı karnesi, İzleme |
| 商家类型 / `cardType` | Satıcı Türü | metin/enum | Satıcı | JSON | Koşullu | Günlük | Satıcı karnesi, Skor |
| 工厂 / 真实工厂 | Fabrika / Doğrulanmış Fabrika Sinyali | boolean/rozet | Satıcı | JSON, DOM, MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 诚信通 / `isTp` | Chengxintong Üyeliği | boolean | Satıcı | JSON, DOM | Hayır | Günlük | Satıcı karnesi, Skor |
| 入驻年限 / TP年限 | Satıcı Yılı | sayı | Satıcı | DOM, MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 服务分 / `sellerSlrServiceScore` | Satıcı Hizmet Puanı | sayı | Satıcı | JSON, MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 回头率 / `byrRepeatRate3m` | Tekrar Alım Oranı | yüzde | Satıcı | JSON, MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 按时发货率 | Zamanında Sevkiyat Oranı | yüzde | Satıcı | MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 好评率 | Satıcı Olumlu Yorum Oranı | yüzde | Satıcı | MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 响应率 | Yanıt Oranı | yüzde | Satıcı | MTOP | Koşullu | Günlük | Satıcı karnesi, Skor |
| 交易勋章/交易分 | İşlem Sinyali / Puanı | metin/sayı | Satıcı | MTOP, DOM | Koşullu | Günlük | Satıcı karnesi, Skor |
| 关注人数 | Mağaza Takipçi Sayısı | sayı | Satıcı | MTOP, DOM | Koşullu | Günlük | Satıcı karnesi, Skor |
| 所在地区 | Satıcı Bölgesi | metin | Satıcı | JSON, DOM | Hayır | Günlük | Satıcı karnesi, CBM-lojistik |
| 店铺标签 / `sellerFeature` | Mağaza Etiketleri | liste/nesne | Satıcı | JSON | Koşullu | Günlük | Satıcı karnesi, Skor |

Satıcı iletişim kişisi, telefon, e-posta, WangWang görüşmesi veya alıcı hesabıyla ilişkili bilgi bu envantere girmez.

### 3.7 Sevkiyat, teslimat ve hizmet taahhütleri

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 发货地 / `location` | Gönderim Yeri | metin | İlan | JSON, DOM | Hayır | Günlük | Kart, CBM-lojistik |
| 发货地编码 / `locationDivisionCode` | Gönderim Bölge Kodu | metin | İlan | JSON | Hayır | Günlük | CBM-lojistik |
| 收货地 / `targetLocation` | Hedef Bölge | metin | İlan | JSON, DOM | Evet/Koşullu | Anlık | CBM-lojistik |
| 运费 / `postFeeValue` | Çin İçi Kargo Ücreti | para | İlan/SKU | JSON veya sayfanın MTOP yanıtı | Evet/Koşullu | Anlık | CBM-lojistik, İzleme |
| 总费用 / `totalCost` | Kaynak Sayfa Toplamı | para | İlan | JSON | Evet/Koşullu | Anlık | Hiçbiri |
| 包邮 / `postFree` | Ücretsiz Kargo | boolean | İlan | JSON, DOM | Koşullu | Anlık | CBM-lojistik |
| 运费模板 / `templateId` | Kargo Şablonu Kimliği | metin | İlan | JSON | Koşullu | Günlük | CBM-lojistik |
| 发货时效 / `deliveryLimitText` | Sevkiyata Verme Süresi | metin/süre | İlan | JSON, DOM | Hayır | Anlık | Kart, Satıcı karnesi |
| 物流说明 / `logisticsText` | Lojistik Açıklaması | metin | İlan | JSON, DOM | Koşullu | Anlık | Kart, CBM-lojistik |
| 官方物流 | Resmî Lojistik Desteği | boolean/nesne | İlan | JSON | Koşullu | Günlük | CBM-lojistik |
| 7天无理由退货 | 7 Gün Koşulsuz İade | hizmet/boolean | İlan | JSON, DOM | Hayır | Günlük | Kart, Skor |
| 晚发必赔 | Geç Sevkiyat Tazmini | hizmet/boolean | İlan | JSON, DOM | Hayır | Günlük | Kart, Skor |
| 退货包运费 | İade Kargo Güvencesi | hizmet/boolean | İlan | JSON, DOM | Koşullu | Günlük | Kart |
| 极速退款 | Hızlı İade | hizmet/boolean | İlan | JSON, DOM | Koşullu | Günlük | Kart |
| 交期保障 | Termin Güvencesi | hizmet/boolean | İlan | JSON, DOM | Koşullu | Günlük | Kart, Satıcı karnesi |
| 破损包赔 | Hasar Tazmini | hizmet/boolean | İlan | JSON, DOM | Koşullu | Günlük | Kart |
| 尺寸不符赔 | Ölçü Uyuşmazlığı Tazmini | hizmet/boolean | İlan | JSON, DOM | Koşullu | Günlük | Kart |
| 买家保障 / `buyerProtectionModel` | Alıcı Güvence Hizmetleri | liste/nesne | İlan | JSON, DOM | Koşullu | Günlük | Kart, Skor |
| 温馨提示 / `remindText` | Sipariş Uyarısı | metin | İlan | JSON, DOM | Koşullu | Anlık | Kart |

Kargo ücreti alıcının hedef bölgesine bağlıdır; bu yüzden aynı ilanın iki oturum/snapshot değeri farklı olabilir. TedarikApp bunu DDP fiyatı sanmaz ve `targetLocation` ile birlikte saklar.

### 3.8 Görseller, video ve Ürün Detayları

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 主图 / `mainImageList[]` | Ana Ürün Görselleri | liste/URL | İlan | JSON | Hayır | Günlük | Kart, İzleme |
| 商品图片 / `imageList[]` | Ürün Görselleri | liste/URL | İlan | JSON | Hayır | Günlük | Kart, İzleme |
| 规格图片 | SKU Görselleri | liste/URL | SKU | JSON | Hayır | Günlük | Kart, İzleme |
| 详情图片 | Detay Görselleri | liste/URL | İlan | detay CDN + görünür DOM | Hayır | Günlük | Kart, İzleme |
| 商品详情 / `detailUrl` | Ürün Detayları Kaynağı | metin/URL | İlan | JSON | Hayır | Günlük | Kart |
| 详情文本 | Ürün Detayları Metni | metin | İlan | görünür/sanitize DOM | Hayır | Günlük | Kart, Uyum |
| 视频 / `wirelessVideo.videoId` | Video Kimliği | metin | İlan | JSON | Koşullu | Günlük | Kart, İzleme |
| 视频封面 | Video Kapağı | URL | İlan | JSON, DOM | Koşullu | Günlük | Kart, İzleme |
| 视频地址 / `<video src>` | Oynatılabilir Video | URL | İlan | görünür DOM | Koşullu | Günlük | Kart, İzleme |
| 视频时长/分辨率 | Video Süresi/Çözünürlüğü | sayı+birim | İlan | görünür `<video>` metadata | Koşullu | Günlük | Kart |

Görseller için kaynak URL, gösterim sırası, kullanım türü ve yakalama anı saklanır. İçerik hash'i ancak medya zaten TedarikApp'e indirildiyse üretilir; eklenti sırf hash almak için arka plan indirmesi yapmaz.

### 3.9 Sertifika, uyum, uyarı ve platform etiketleri

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 3C证书 / CCC证书 | 3C/CCC Sertifikası | belge/kimlik/liste | Ürün | sayfanın MTOP yanıtı + DOM | Koşullu | Günlük | Kart, Uyum |
| 官方资质 / `officialDocInfos[]` | Resmî Belgeler | liste/nesne | Ürün/İlan | JSON + MTOP | Koşullu | Günlük | Kart, Uyum |
| 商品警告 / `productWarning` | Ürün Uyarıları | liste/metin | Ürün/İlan | JSON, DOM | Hayır | Günlük | Kart, Uyum |
| 管制刀具 / `isControlKnifeOffer` | Kontrollü Kesici Alet Uyarısı | boolean | Ürün | JSON | Hayır | Günlük | Kart, Uyum |
| 官方验货 | Resmî İnceleme | hizmet/nesne | İlan | MTOP, DOM | Koşullu | Günlük | Kart, Uyum |
| 品牌授权 | Marka Yetkisi | belge/boolean | Ürün/Satıcı | MTOP, DOM | Koşullu | Günlük | Kart, Uyum |
| 专利 / 知识产权提示 | Patent/Fikrî Hak Sinyali | metin/belge | Ürün | JSON, DOM | Koşullu | Günlük | Kart, Uyum |
| 商品标签 / `labels[]` | Ürün Etiketleri | liste | İlan | JSON | Hayır | Günlük | Kart, Skor |
| 会员标签 / `offerMemberTags[]` | Platform Üye Etiket Kimlikleri | liste | İlan | JSON | Koşullu | Günlük | Hiçbiri |
| 活动图标 / `officialActivityIcon` | Resmî Kampanya Etiketi | metin/URL | İlan | JSON | Koşullu | Anlık | Kart |
| `offerSign` / `sellerSign` | Platform İşaretleri | nesne | İlan/Satıcı | JSON | Koşullu | Günlük | Ham kanıt; Hiçbiri |

Sertifika görüntüsü veya numarası “doğrulanmış uygunluk” değildir. Kaynak ilan beyanı olarak saklanır; VERIFIED katmanı ancak insan/kurum doğrulamasıyla oluşur.

### 3.10 Sayfa içi bağlantılı ürün sinyalleri

| Çince etiket / ham alan | TR karşılık | Tip | Kapsam | Kaynak | Giriş | Değişkenlik | Beslediği modül |
|---|---|---:|---|---|---|---|---|
| 同款 / `sameProduct` | Aynı Ürün Önerileri | liste | İlan | sayfanın MTOP yanıtı + DOM | Koşullu | Anlık | Skor/Keşif |
| 店铺搭配 / `shopProductCombine` | Mağaza Ürün Kombinleri | liste | Satıcı | sayfanın MTOP yanıtı + DOM | Koşullu | Anlık | Hiçbiri |
| 店铺推荐 / `shopProductRecommend` | Mağaza Ürün Önerileri | liste | Satıcı | sayfanın MTOP yanıtı + DOM | Koşullu | Anlık | Keşif |
| 热销SKU | Öne Çıkan SKU | metin | SKU | JSON | Koşullu | Günlük | Kart, Skor |

Bağlantılı öneriler ana ürünün typed kolonlarını şişirmez; snapshot içinde `related_items` olarak kaynak kimliği/URL/görsel/fiyat sinyaliyle tutulur. Kişiselleştirilmiş öneri olduğu anlaşılan kayıtlar genel ürün gerçeği sayılmaz ve skor girdisi olmadan önce `personalized=true` işareti alır.

## 4. Giriş yapılmış / yapılmamış davranış matrisi

| Alan grubu | Giriş yapılmamış | Giriş yapılmış | Eksiklik yorumu |
|---|---|---|---|
| Kimlik, başlık, kategori, temel görseller | Beklenir | Beklenir | Yoksa parser/şablon hatası olasıdır. |
| Standart fiyat, MOQ, SKU, stok | Çoğu ilanda beklenir; fiyat gizli olabilir | Beklenir; üyeye göre fiyat değişebilir | Oturumsuz gizlilik silinme değildir. |
| Üye/Plus/özel fiyat, 0 Yuan sipariş hakkı | Beklenmez | Koşullu | `auth_hidden` olarak işaretlenir. |
| Hedef bölge kargosu | Varsayılan/eksik olabilir | Hesap adresine göre dolabilir | Değer hedef bölgeyle birlikte kıyaslanır. |
| Değerlendirme özeti | Koşullu | Daha yüksek olasılıkla görünür | MTOP özeti gelmediyse `not_observed`. |
| Dropshipping ayrıntılı metrikleri | Çoğunlukla eksik | Yetki/ürün tipine göre | Eksik metrik satıcıyı cezalandırmaz. |
| Satıcı karnesi ayrıntıları | Temel kart görülebilir | Daha tam olabilir | Sağlık profili auth durumuna göre beklenen alan kullanır. |
| 3C/sertifika ayrıntısı | Rozet/özet olabilir | Detay çağrısı gelebilir | Sertifika yokluğu ile yakalanamama ayrılır. |

## 5. Yakalama sağlığına yansıma

Her snapshot şu durumları alan bazında taşır:

- `observed`: değer görüldü;
- `confirmed_absent`: sayfa açıkça yok/uygulanamaz dedi;
- `not_observed`: modül/yanıt bu yakalamada gelmedi;
- `auth_hidden`: giriş veya üyelik gerektiği için görünmedi;
- `template_unsupported`: sayfa şablonu seçici setince tanınmadı;
- `parse_error`: kaynak görüldü fakat güvenle çözülemedi.

Sağlık puanı auth profiline göre hesaplanır; girişli yakalamadan sonra girişsiz yakalamadaki alan kaybı ilan değişikliği sayılmaz. `offerId` veya başlık yoksa yakalama **başarısız**; fiyat/SKU beklenirken ikisi de yoksa **kısmi**; ham kaynak hash'i, parser/seçici sürümü ve alan kapsama oranı her snapshot'a yazılır.

## 6. Veri hacmi ilkesi

- Ham HAR, response header, cookie ve istek gövdesi saklanmaz.
- Snapshot `raw_payload` yalnız ürünle ilgili seçilmiş JSON/DOM/MTOP parçalarını içerir; buyer/analytics dalları kaynakta ayıklanır.
- Medya dosyası snapshot içine gömülmez; URL + metadata + mevcutsa yerel `media_asset_id` kullanılır.
- Büyük açıklama HTML'i sanitize edilir, sıkıştırılır ve içerik hash'iyle tekilleştirilir.
- Değişmeyen snapshot'lar da tarihçe için korunur; aynı payload hash'inde alan kopyaları içerik-adresli blob ile tekilleştirilebilir.

## 7. Kaynaklar ve kanıt zinciri

### Repo içi birincil teknik kanıtlar

1. [1688 parser teknik raporu](../../../../arastirma/1688-parser-raporu.md) — canlı 2026 masaüstü model dalları, 35 UI modülü, `商品件重尺`, 31 özellik örneği, MTOP kanalları.
2. [1688 veri envanteri ön raporu](../../../../arastirma/1688-veri-envanteri-on-rapor.md) — fallback yolları ve alan-risk ayrımları; “ön rapor” niteliği korunmuştur.
3. [İmzalı seçici setinin repo fikstürü](../../../../../extension/tests/fixtures/selectors-1688.json) — üretimde kullanılan yol öncelikleri.
4. [1688 parser uygulaması](../../../../../extension/modules/m1688/parser.ts) ve [Capture v2 tipleri](../../../../../extension/core/types.ts) — mevcut 16+ alanın fiilî sözleşmesi.
5. [Görev #19 platform veri-kanalı raporu](../../v3-e/gorev-19/platform-veri-kanali-raporu.md) — platform bağımsız gömülü veri/DOM/MTOP ilkesi.
6. [5B çıktı terimleri](../../cikti-terimleri.json) — bu belgedeki kesin TR karşılıkların tek kaynağı.
7. [RAW / NORMALIZED / PROVENANCE veri modeli](../../../../v2/02-veri-modeli.md) — katman ve kaynak değeri ezmeme ilkesi.
8. [Chrome Web Store politika teyidi](../../store-politika-teyidi.md) — TedarikApp'in dar izin ve kullanıcı tetikleme kararı.

### Resmî 1688 / Alibaba kaynakları

9. [Alibaba hizmet şartları](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b201703271338_74297.html) — 1688 platform ve hizmet çerçevesi.
10. [Alıcı güvence hizmeti sözleşmesi](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b202002162159_27770.html) — `晚发必赔`, termin, hasar ve ölçü taahhütleri.
11. [1688 çok kanallı dağıtım sözleşmesi](https://terms.alicdn.com/legal-agreement/terms/product/20221019141431183/20221019141431183.html) — site içi/site dışı dağıtım ve tekli dropshipping tanımları.
12. [1688 kullanıcı deneyimi geliştirme planı](https://terms.alicdn.com/legal-agreement/terms/b_end_product_protocol/20240325150850953/20240325150850953.html) — başlık, fiyat, stok, SKU, detay, satış ve kategori gibi ürün verilerinin dağıtım bağlamı.
13. [Chengxintong hizmet sözleşmesi](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b201801191631_21393.html) — üye ve yayınlanan bilgi sorumluluğu.
14. [Güçlü satıcı hizmet koşulları](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b201802011754_50614.html) — tekrar alım, dağıtım, sınır ötesi ve hizmet yıldızı gibi mağaza ölçütleri.
15. [Derin doğrulama hizmeti sözleşmesi](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b201802011744_81460.html) — satıcı doğrulama hizmetinin resmî bağlamı.
16. [1688 resmî lojistik çözümü sözleşmesi](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b202104192035_47290.html) — lojistik, hasar ve gecikme hizmetleri.
17. [Alibaba (1688) gizlilik politikası](https://terms.alicdn.com/legal-agreement/terms/suit_bu1_b2b/suit_bu1_b2b201703271337_94551.html) — hesap, sipariş, teslimat ve değerlendirme kişisel verilerinin neden hariç tutulduğunun politika zemini.
18. [Alibaba fikrî haklar kuralları — 1688](https://ipp.alibabagroup.com/infoContent.htm?skyWindowUrl=rules%2Fcn-1688) — marka/patent/fikrî hak sinyallerinin uyum bağlamı.

### Resmî Chrome / Chrome Web Store kaynakları

19. [Kullanıcı gizliliğini koruma](https://developer.chrome.com/docs/extensions/develop/security-privacy/user-privacy) — en az veri ve en az izin.
20. [İzinleri bildirme](https://developer.chrome.com/docs/extensions/develop/concepts/declare-permissions) ve [`activeTab`](https://developer.chrome.com/docs/extensions/develop/concepts/activeTab) — kullanıcı eylemiyle sınırlı sekme erişimi.
21. [Content scripts ve isolated world](https://developer.chrome.com/docs/extensions/develop/concepts/content-scripts) — sayfa/veri köprüsü güvenlik sınırı.
22. [`chrome.scripting` API](https://developer.chrome.com/docs/extensions/reference/api/scripting) — `MAIN`/`ISOLATED` çalışma dünyaları.
23. [Manifest V3 ek gereksinimleri](https://developer.chrome.com/docs/webstore/program-policies/mv3-requirements) — uzaktan kod yasağı; uzaktan veri ile kod ayrımı.
24. [Limited Use](https://developer.chrome.com/docs/webstore/program-policies/limited-use), [açıklama gereksinimleri](https://developer.chrome.com/docs/webstore/program-policies/disclosure-requirements) ve [gizlilik politikası](https://developer.chrome.com/docs/webstore/program-policies/privacy) — web etkinliğinin yalnız açıklanmış kullanıcı özelliği için işlenmesi.
25. [Kalite / tek amaç SSS](https://developer.chrome.com/docs/webstore/program-policies/quality-guidelines-faq) — dar tedarik odağıyla ilişkili özellikler.
26. [MV3 service worker yaşam döngüsü](https://developer.chrome.com/docs/extensions/develop/concepts/service-workers/lifecycle) — kalıcı arka plan süreci varsaymama.
27. [`chrome.storage` kotaları](https://developer.chrome.com/docs/extensions/reference/api/storage) — ham veri ve medya hacmini eklenti depolamasında tutmama gerekçesi.
28. [Eklenti güvenliği](https://developer.chrome.com/docs/extensions/develop/security-privacy/stay-secure) — dar host erişimi, güvenilmeyen DOM girdisi ve mesaj doğrulama.

## 8. Açık doğrulamalar

- 1688 sayfa şablonu/A-B testi değiştikçe alan yolu ve giriş gereksinimi değişebilir; envanter “alan vardır” ile “her ilanda görünür”ü eşitlemez.
- 3C sertifika ayrıntısının tam MTOP `serviceName`/yanıt şeması üç farklı uygun ürün fikstüründe doğrulanmalıdır.
- `主要销售地区`, yapılandırılmış `装箱数量` ve bazı satıcı oranları kategori/hesap bazlıdır; typed kolona geçmeden önce en az üç ilan kanıtı gerekir.
- MTOP yanıtı yalnız sayfanın kendisi isteği başlatmışsa pasif yakalanır. Eksikliği gidermek için özel çağrı, token veya imza üretimi bu şartname ve TedarikApp'in yayın mimarisi gereği yasaktır.
