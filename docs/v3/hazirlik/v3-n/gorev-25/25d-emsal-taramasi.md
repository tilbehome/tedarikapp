# Görev 25D — Üç Rol Paneli Emsal Taraması

**Belge türü:** Kamuya açık emsal taraması  
**Amaç:** TedarikApp V3-N müşteri, ithalatçı ve Çinli üretici panel taslaklarına ekran deseni girdisi  
**Gözlem tarihi:** 28 Ağustos 2026  
**Karar statüsü:** Bulgular nihai şartname değildir.

## 1. Yöntem ve iddia sınırı

- İnceleme, gözlem tarihinde kamuya açık ürün/yardım/dokümantasyon sayfalarıyla sınırlıdır. Üyelik arkasındaki tüm ekranlar test edilmemiştir.
- Her satırda **gözlem**, **TedarikApp'e uyan desen** ve **uymayan/kopyalanmayacak bölüm** ayrılmıştır.
- Kaynakların pazarlama iddiaları performans kanıtı olarak alınmamıştır. Sayısal başarı, hız veya dönüşüm iddiası bu rapora taşınmamıştır.
- Bir desenin emsalde bulunması, TedarikApp için otomatik gereksinim olduğu anlamına gelmez.
- TedarikApp'in kesin kararları bu emsallerden üstündür: kalıcı bağlantı + 6 haneli anahtar, sunucu tarafı rol çözümü, whitelist, değişmez gönderilen veri, ayrı yanıt katmanı, tek dilde çıktı, ödeme/üyelik/otomatik çekim/e-posta/SMS olmaması.

## 2. 25A Müşteri paneli emsalleri

### 2.1 Faire — Preorders

**Kamuya açık kaynaklar:**

- [Faire Help Center — Preorders (retailer)](https://www.faire.com/support/articles/360038557671)
- [Faire Help Center — Apparel Preorders](https://www.faire.com/support/articles/9203429758363)

**Gözlem — 28 Ağustos 2026:** Faire, preorder'ı henüz stokta olmayan ürün için belirli sipariş tarihi ve gelecekteki sevk tarihiyle sunuyor. Üretici tarafı sayfası preorder etkinleştirme, sevk tarihi/aralığı ve son sipariş tarihi gibi ayrı alanlar gösteriyor. Bu desen, “hemen teslim stok ürünü” ile “gelecekte karşılanacak preorder” beklentisini metin ve tarihle ayırıyor.

**Neden bu ekran desenini kullanıyor:** Preorder'ın zaman belirsizliğini azaltmak ve alıcıya ürünün normal stok siparişinden farklı olduğunu daha işlem öncesinde anlatmak için ürün/liste bağlamında açık tarih bilgisi taşıyor.

**TedarikApp'e uyan:**

- Liste üstünde özel/ön sipariş türünün açık yazılması.
- Geçerlilik/yanıt tarihi ile beklenen teslim/sevk bilgisinin farklı alanlar olması.
- Ürün kartı ve özet belgede preorder açıklamasının korunması.

**Uymayan/kopyalanmayacak:**

- Faire gerçek sipariş, sepet, ödeme koşulu ve pazar yeri akışına bağlanır. TedarikApp müşteri panelinde bunlar yoktur.
- “Preorder ver” eylemi kopyalanmaz; TedarikApp'te müşteri yalnız **ön sipariş niyet beyanı** iletir.
- Hazır stok ile preorder'ı aynı sepet/işlemde birleştirme mantığı TedarikApp kapsamı dışıdır.

### 2.2 Shopify B2B — siparişi taslak olarak inceleme ve geçmiş

**Kamuya açık kaynaklar:**

- [Shopify Help — Overview of B2B features](https://help.shopify.com/en/manual/b2b/getting-started/features)
- [Shopify Help — Sign-in and customer accounts in B2B](https://help.shopify.com/en/manual/b2b/customer-login-and-accounts)

**Gözlem — 28 Ağustos 2026:** Shopify B2B, müşteri siparişinin doğrudan kesinleşmesi yerine inceleme için taslak olarak iletilmesi seçeneğini; müşteri hesabında sipariş geçmişini ve geçmiş siparişi yeniden görüntüleme/yineleme işlevlerini anlatıyor.

**Neden bu ekran desenini kullanıyor:** B2B işleminde müşteri eylemi ile satıcı teyidini ayırmak ve tekrar gelen müşteriye önceki hareketlerini tek yerde göstermek için “inceleme öncesi taslak” ve “hesap geçmişi” desenlerini kullanıyor.

**TedarikApp'e uyan:**

- Müşterinin ilettiği tercihi kesin sonuç gibi sunmadan önce özet/inceleme adımı.
- İletilen yanıtın sonradan salt okunur geçmişte görünmesi.
- Kişiye/kuruma özel görünen listelerin ortak katalogdan ayrılması fikri.

**Uymayan/kopyalanmayacak:**

- Shopify'ın hesapla giriş, checkout, ödeme yöntemi, sipariş, iade ve yeniden sipariş işlevleri TedarikApp'e alınmaz.
- “Taslak sipariş” terimi de müşteri psikolojisi için kopyalanmaz; TedarikApp metni “niyet beyanı”dır.
- Müşteri gönderilmiş liste ürünlerini değiştiremez; yalnız ayrı yanıt alanlarını doldurur.

### 2.3 JOOR — dijital line sheet

**Kamuya açık kaynaklar:**

- [JOOR — Fashion Line Sheet Software](https://www.joor.com/line-sheet-software)
- [JOOR — What is a Line Sheet?](https://www.joor.com/insights/what-is-a-line-sheet-and-how-you-can-maximize-its-effectiveness)

**Gözlem — 28 Ağustos 2026:** JOOR, dijital line sheet'i görsel, ürün özellikleri ve fiyatı bir araya getiren ürün-öncelikli sunum olarak tanımlıyor; koleksiyon içinde gezinme ve ürün ayrıntısına erişimi öne çıkarıyor.

**Neden bu ekran desenini kullanıyor:** Toptan alıcı çok sayıda ürünü karşılaştırırken salt fiyat tablosundan önce ürün görseli ve temel niteliklerle seçim yapabilsin diye “ürün-öncelikli katalog” düzeni kullanıyor.

**TedarikApp'e uyan:**

- Müşteri teklifinde ürün görseli + kısa özellik + fiyat + niyet alanının birlikte görünmesi.
- Liste çıktısının uygulama ekran görüntüsü yerine düzenli, ürün-öncelikli teklif belgesi olması.
- Her müşteri/lista için izinli ürün ve fiyat sunumunun ayrılması.

**Uymayan/kopyalanmayacak:**

- JOOR'un doğrudan sipariş, canlı envanter, marka ağı ve kullanıcı hesabı işlevleri kapsam dışıdır.
- TedarikApp bir moda line sheet ürünü değildir; kategoriye özgü showroom özellikleri genelleştirilmez.
- Ürün sahibinin gizli kaynak/tedarikçi/maliyet alanları müşteri line sheet'ine taşınmaz.

### 2.4 Ankorstore — wishlist sinyali (tamamlayıcı emsal)

**Kamuya açık kaynak:** [Ankorstore Privacy Policy](https://support.ankorstore.com/articles/9453845165-ankorstore-privacy-policy?lang=en)

**Gözlem — 28 Ağustos 2026:** Kamuya açık gizlilik metni, perakendecinin bir ürünü wishlist veya benzeri özelliğe eklemesi halinde ilgili markaya ürün referansı ve mağaza adı düzeyinde bilgi gösterilebildiğini söylüyor.

**Neden bu ekran desenini kullanıyor:** “Satın alma”dan daha düşük taahhütlü bir ilgi sinyalini ayrı davranış olarak kaydetmek için.

**TedarikApp'e uyan:** İlgileniyorum/kararsızım/istemiyorum seçeneklerinin siparişten ayrı niyet verisi olması ve ürün referansına bağlı tutulması.

**Uymayan/kopyalanmayacak:** Ankorstore mesajlaşma/ağ yapısı ve marka-perakendeci pazar yeri ilişkisi alınmaz. TedarikApp müşteri “İstek Listem”i serbest pazar yeri wishlist'i değil, link/foto/açıklamalı “şunu getir” talebidir.

### Müşteri paneli sentezi

| Desen | Kaynak dayanağı | TedarikApp kararı |
|---|---|---|
| Ön siparişin stok siparişinden açık ayrımı | Faire | Liste üstü özel/ön sipariş açıklaması ve niyet beyanı dipnotu |
| Göndermeden önce inceleme, sonra geçmiş | Shopify B2B | Ürün niyetleri → liste özeti → kilitli niyet beyanı → geçmiş |
| Görsel ve ürün-öncelikli teklif | JOOR | Kart/liste hibriti; fiyat ve niyet aynı bağlamda |
| Satın almadan düşük taahhütlü ilgi sinyali | Ankorstore | Üçlü niyet seçimi; kesin sipariş dili yok |

## 3. 25B İthalatçı paneli emsalleri

### 3.1 Coupa Supplier Portal — sourcing event, revizyon ve kendi performansı

**Kamuya açık kaynaklar:**

- [Coupa Compass — Participate in a Sourcing Event](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/participate-in-a-sourcing-event)
- [Coupa Compass — Sourcing FAQ](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/sourcing-faq)
- [Coupa Compass — View Business Performance Data](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/business-performance/view-business-performance-data)

**Gözlem — 28 Ağustos 2026:** Coupa, tedarikçiye birden çok sourcing event'i gösteren portal yaklaşımını; önceki event yanıtının yeni aşamada taslağa taşınabilmesini; revize event için yeni Excel dışa/içe aktarma gereğini ve tedarikçinin kendi işlem eğilimlerini/özetlerini gördüğü Business Performance alanını belgeliyor.

**Neden bu ekran desenini kullanıyor:** Tek seferlik form yerine bir tedarikçinin farklı talepleri, değişen event sürümlerini ve dikkat gerektiren kendi işlerini kalıcı çalışma alanında yönetmesi için.

**TedarikApp'e uyan:**

- Çoklu liste dizini + açık iş özeti.
- Revize edilen talepte eski şablonu kabul etmeyip güncel tur şablonunu isteme.
- Önceki yanıtı referans alırken yeni turun ayrı taslak olması.
- Kendi özetinin yalnız kendi işlemlerine dayanması.

**Uymayan/kopyalanmayacak:**

- Coupa üyelik/hesap, mesajlaşma, bildirim, sipariş, fatura ve ödeme ekosistemi alınmaz.
- “Business performance” değer yargısı veya başka tedarikçilerle kıyas olarak kullanılmaz; TedarikApp yalnız tanımlı öz metrikleri gösterir.
- Coupa event/auction kapsamı TedarikApp'in dar DDP+KDV fiyatlama amacına genişletilmez.

### 3.2 SAP Ariba Guided Sourcing — çok turlu teklif ve çevrimdışı Excel

**Kamuya açık kaynaklar:**

- [SAP Help — Email Bidding for Multi-Round Events](https://help.sap.com/docs/strategic-sourcing/managing-events-with-guided-sourcing/email-bidding-for-multi-round-events)
- [SAP Help — Event Options](https://help.sap.com/docs/strategic-sourcing/event-management/event-options-127602568a954ac3a76378210ffa5a09)
- [SAP Help — Participating in Sourcing Events (PDF)](https://help.sap.com/doc/6788e52005c34f85bb5862bfe9b04b6f/2511/en-US/EventParticipation_1.pdf)

**Gözlem — 28 Ağustos 2026:** SAP Ariba dokümanı çok turlu bidding'i, çevrimdışı Excel response sheet'lerini, taslak/gönderim/revizyon hareketlerini ve gönderilen teklif geçmişini anlatıyor. PDF kılavuzunda gönderimden sonra revize etme bağlantısı ve güncellenmiş Excel ile yeniden sunma akışı yer alıyor.

**Neden bu ekran desenini kullanıyor:** Çok satırlı fiyat taleplerinin çevrim dışı hazırlanabilmesi, her turun izlenmesi ve gönderilmiş yanıtın sonraki revizyondan ayrılması için.

**TedarikApp'e uyan:**

- Tur no ve tur durumu her liste/çıktıda görünür.
- Excel şablonu belirli liste + açık tura bağlıdır.
- Excel yüklemek taslak günceller; ayrıca gözden geçirip gönderme gerekir.
- Gönderim kilitlenir, revizyon ayrı tur açar.

**Uymayan/kopyalanmayacak:**

- SAP'nin e-posta ile teklif gönderme kanalı TedarikApp'e alınmaz; kesin kararda e-posta yoktur.
- Auction, rakip teklif/ranking ve iyileştirme baskısı yoktur.
- SAP Business Network hesabı/izin yapısı yerine TedarikApp'in kalıcı bağlantı + 6 haneli anahtarı korunur.

### 3.3 Oracle Supplier Portal — spreadsheet response ve revizyon geçmişi

**Kamuya açık kaynaklar:**

- [Oracle — Respond by Spreadsheet in Supplier Portal](https://docs.oracle.com/en/cloud/saas/readiness/scm/25b/proc25b/25B-procurement-wn-f36585.htm)
- [Oracle — View Response History in Supplier Portal](https://docs.oracle.com/en/cloud/saas/readiness/scm/25b/proc25b/25B-procurement-wn-f36588.htm)
- [Oracle — Respond to a Large Number of Requirements](https://docs.oracle.com/en/cloud/saas/readiness/scm/25b/proc25b/25B-procurement-wn-f36579.htm)

**Gözlem — 28 Ağustos 2026:** Oracle, supplier portal'da RFQ/RFI response şablonunu dışa aktarma, çevrimdışı doldurup içe alma; yanıt revizyon geçmişinde zaman, tutar, referans ve durum gösterme; yüksek hacimli içerikte bölüm bazlı ilerleme ve tamamlanma işaretleri kullanma desenlerini belgeliyor.

**Neden bu ekran desenini kullanıyor:** Çok satırlı formda kullanıcıya eksik bölümü buldurmak, elektronik tabloyla toplu giriş sağlamak ve her revizyonu geriye dönük görünür tutmak için.

**TedarikApp'e uyan:**

- Yükleme öncesi liste/tur/satır doğrulaması ve içe aktarma özeti.
- Büyük listede yanıtlanan/kalan ilerlemesi ve eksik satıra gitme.
- Tur geçmişinde referans, zaman, durum ve satır özeti.

**Uymayan/kopyalanmayacak:**

- Oracle'ın karmaşık negotiation requirements, ranking, award ve rekabet ekranları alınmaz.
- Yüksek hacim desteği “tek sayfaya daha fazla kontrol ekleme” gerekçesi olmaz; TedarikApp yalnız DDP+KDV görev alanlarını gösterir.
- Üyelik/kurumsal kullanıcı yönetimi kapsam dışıdır.

### İthalatçı paneli sentezi

| Desen | Kaynak dayanağı | TedarikApp kararı |
|---|---|---|
| Tek event yerine kalıcı çoklu iş alanı | Coupa | Genel Bakış + Fiyat Talepleri + Geçmiş + Kendi Özetim |
| Çok tur ve her turda yeni Excel | SAP Ariba, Coupa | Gönder → kilitle → ürün sahibi revizyon açar → yeni tur/şablon |
| Spreadsheet ön kontrolü ve büyük liste ilerlemesi | Oracle | Doğru liste/tur/satır eşleşmeden taslağa uygulama yok |
| Yanıt geçmişi | Oracle, SAP Ariba | Her tur salt okunur; tarih/referans ve sayılar görünür |
| Kendi özeti | Coupa | Yalnız kendi TedarikApp kayıtları; tanımlı pay/payda; sıralama yok |

## 4. 25C Çinli üretici paneli emsalleri

### 4.1 Alibaba.com RFQ — talebi bul, uygunluğu değerlendir, ayrıntılı teklif ver

**Kamuya açık kaynaklar:**

- [Alibaba.com Seller Central — Request for Quotation](https://seller.alibaba.com/rfq)
- [Alibaba.com Official Class — What is the RFQ tool?](https://seller.alibaba.com/learningcenter/content/detail/PX2U9ID5.htm)
- [Alibaba.com RFQ Market](https://rfq.alibaba.com/rfq.html)

**Gözlem — 28 Ağustos 2026:** Alibaba Seller Central, üreticinin RFQ ilanlarını görmesi, karşılayabileceği talebi seçmesi ve alıcının ihtiyacına ayrıntılı teklif göndermesi akışını açıklar. RFQ, bir alıcının belirli tek satıcı yerine pazara ayrıntılı ihtiyacını ilettiği yapı olarak tanımlanır.

**Neden bu ekran desenini kullanıyor:** Üreticinin önce talebin uygunluğunu değerlendirmesi, sonra genel fiyat listesi yerine talebe özgü yanıt vermesi için.

**TedarikApp'e uyan:**

- RFQ dizini → RFQ detayı → ürün bazlı yapılandırılmış yanıt.
- Önce uygunluk durumu, sonra fiyat/MOQ/termin/paket ayrıntısı.
- Talebe özgü alternatif ürün açıklaması.

**Uymayan/kopyalanmayacak:**

- Alibaba açık RFQ pazarı, lead bulma, satıcı üyeliği, mesajlaşma ve çok tedarikçili rekabet yapısı alınmaz.
- TedarikApp'te üretici yalnız kendisine atanmış whitelist RFQ'ları görür.
- “24 saatte teklif” gibi pazarlama iddiaları hedef veya SLA olarak kullanılmaz.

### 4.2 Made-in-China Easy Sourcing — yapılandırılmış buying request

**Kamuya açık kaynaklar:**

- [Made-in-China — Easy Sourcing for Buyers](https://sourcing.made-in-china.com/)
- [Made-in-China — Easy Sourcing for Suppliers](https://sourcing.made-in-china.com/suppliers.html)
- [Kamuya açık RFQ örneği](https://sourcing.made-in-china.com/request/inItVvTEJKoh/Request-for-Quotation.html)

**Gözlem — 28 Ağustos 2026:** Easy Sourcing, alıcının sourcing request yayımlaması ve üreticilerin quotation vermesi akışını; kamuya açık örnek RFQ ise geçerlilik tarihi, talep miktarı, trade term, açıklama ve üreticiden MOQ, birim fiyat ve üretim süresi gibi bilgilerin beklendiği desenini gösteriyor.

**Neden bu ekran desenini kullanıyor:** Alıcının ihtiyacını karşılaştırılabilir bir brief halinde vermek ve üreticinin yalnız fiyat değil, üretilebilirlik/teslim ayrıntısını da yanıtlamasını sağlamak için.

**TedarikApp'e uyan:**

- RFQ başlığında talep miktarı, özellik, son tarih ve izinli ekler.
- Üretici formunda trade term + fiyat + MOQ + termin + paket/koli alanlarının ayrı olması.
- Süresi geçen/kapanan RFQ'da yanıt alanının kilitlenmesi.

**Uymayan/kopyalanmayacak:**

- Açık pazar, quote kotası, verified supplier eşleştirmesi ve alıcı iletişim bilgisi katmanları alınmaz.
- Ödeme koşulu ve sevkiyat yöntemi bu görevde zorunlu yeni alan olarak önerilmez; PM'nin mevcut modeline bağlıdır.
- Kullanıcı üyeliği/giriş yapısı alınmaz.

### 4.3 Global Sources RFQ — RFQ gelen kutusu ve son tarih

**Kamuya açık kaynaklar:**

- [Global Sources — RFQ help](https://www.globalsources.com/STM/HELP/PSCEHELP/FILES/USERGUIDE_12.HTM)
- [Global Sources — RFQ Guide](https://www.globalsources.com/knowledge/rfq/)

**Gözlem — 28 Ağustos 2026:** Global Sources yardım sayfası RFQ'ların Message Center içinde listelenmesini, kategoriye göre görülmesini, bir RFQ'nun son kullanma tarihi olmasını ve süresi geçtiğinde yanıtlanamamasını anlatıyor. Rehber, RFQ'nun tanımlı ürün/hizmet için fiyat ve şart topladığını vurguluyor.

**Neden bu ekran desenini kullanıyor:** Üreticinin gelen RFQ'ları tek kuyruğa toplaması ve yalnız açık/geçerli taleplere yanıt vermesini sağlamak için.

**TedarikApp'e uyan:**

- 工作台/Genel Bakış'ta açık RFQ kuyruğu ve son tarih.
- Kapanmış RFQ'nun salt okunur geçmişte kalması.
- Yanıtlanmamış ürün ve açık RFQ sayısının görünmesi.

**Uymayan/kopyalanmayacak:**

- E-posta uyarıları ve Message Center sohbeti kesin kapsam dışıdır.
- Üyelik, alt hesap, reklamveren ayrıcalıkları ve açık RFQ eşleştirmesi alınmaz.
- “İlk yanıtlayan önceliklidir” gibi pazar rekabeti dili kullanılmaz.

### 4.4 WeChat/QR için tamamlayıcı gözlem

**Kamuya açık kaynak:** [Tencent MSDK — WeChat Developer Reference](https://docs.msdk.qq.com/v5/en/Channel/WeChat/)

**Gözlem — 28 Ağustos 2026:** Tencent'in kamuya açık MSDK belgesi “WeChat QR Code ile giriş” ile “arkadaşa/Moments'a içerik paylaşma” işlevlerini ayrı başlıklar altında ele alıyor.

**Tasarım çıkarımı:** TedarikApp'in standart URL içeren QR'ı, WeChat login veya Mini Program özelliği gibi adlandırılmamalıdır. En düşük bağımlılıklı desen: aynı kalıcı bağlantıyı hem tıklanabilir metin hem standart URL QR'ı olarak vermek; 6 haneli anahtarı QR dışında tutmak; normal tarayıcıda açma yedeği sunmaktır.

**İddia sınırı:** Bu kaynak TedarikApp sayfasının WeChat içi tarayıcıda uyumlu olduğunu kanıtlamaz. Uyum ancak hedef cihaz/sürüm test matrisiyle doğrulanabilir; rapor “tam uyum” iddiasında bulunmaz.

### Çinli üretici paneli sentezi

| Desen | Kaynak dayanağı | TedarikApp kararı |
|---|---|---|
| RFQ kuyruğu ve talebe özgü teklif | Alibaba, Global Sources | 工作台 + 询价单; yalnız atanmış talepler |
| Yapılandırılmış ticari/üretim alanları | Made-in-China | Durum → EXW/FOB fiyat → MOQ → termin → paket/koli |
| Son tarih ve kapanan talebin kilidi | Made-in-China, Global Sources | Açık RFQ düzenlenir; kapalı RFQ geçmişte salt okunur |
| Alternatif teklif | Alibaba'nın talebe özgü teklif yaklaşımı; RFQ pratikleri | Asıl ürün değişmez, alternatif ayrı referans/fark/fiyat seti olur |
| QR ile düşük sürtünmeli dağıtım | Tencent belgesinden sınırlı çıkarım | URL QR + tıklanabilir link; anahtar ayrı; WeChat hesabı zorunlu değil |

## 5. Roller arası ortak desenler ve sınırlar

| Ortak desen | Müşteri | İthalatçı | Çinli üretici |
|---|---|---|---|
| Kalıcı iş kuyruğu | Bekleyen teklifler/istek/dekont | Açık DDP listeleri/turlar | Açık RFQ'lar |
| Gönderilen veri | Teklif ürünleri salt okunur | Liste ürünleri salt okunur | RFQ ürünleri salt okunur |
| Ayrı yanıt katmanı | Niyet + miktar + not | DDP+KDV + açıklama/bulunamadı | Durum + EXW/FOB + MOQ/termin/paket |
| Gönderim sonrası | Niyet beyanı kilitli | Tur kilitli | RFQ yanıtı kilitli |
| Geçmiş | Teklif/istek/dekont | Liste ve tur | RFQ ve tur |
| Çıktı | İşletme antetli müşteri belgesi | Tur referanslı fiyat yanıtı | Sade RFQ yanıtı |
| Kesinlikle yok | Sipariş/ödeme, gizli ticari alan | Ödeme, auction/ranking | Açık pazar, mesaj merkezi/ödeme |

## 6. Şartnameye taşınabilecek bulgular

Bu maddeler mevcut kesin kararlarla uyumludur ve PM'nin şartnamesine davranış gereksinimi olarak taşınabilir:

1. Her rolün ana sayfası yalnız açık görevlerini ve son kendi hareketlerini gösterir.
2. Gönderilen ürün/liste/RFQ verisi ekranda açıkça salt okunur işaretlenir.
3. Her gönderim öncesinde rolüne uygun özet ve eksik/hata kontrolü bulunur.
4. Gönderimden sonra yanıt kilitlenir; değişiklik geçmişi bozmak yerine yeni revizyon turunda yapılır.
5. Excel dosyası liste + tur bağlamına bağlıdır; eski/yanlış dosya sessizce uygulanmaz.
6. Geçmişte zaman, referans, tur ve o anda gönderilen yanıt salt okunur görünür.
7. Ekran arayüzü ve belge çıktısı ayrı görsel sistemlerdir; çıktı uygulama kabuğunu taşımaz.
8. Seçilen dil panel ve çıktının tamamına uygulanır; karışık dil kabul edilmez.

## 7. Vizyon olarak kalması gereken çıkarımlar

Bu maddeler faydalı tasarım yönleridir; kullanıcı testi/PM kararı olmadan kesin ürün iddiasına dönüşmemelidir:

- Müşteri ürün kartında görsel ile niyet alanının en iyi masaüstü/mobil yerleşimi.
- İthalatçıda yeni turun önceki değerleri otomatik taslağa taşıması.
- Çok büyük listede kart, satır veya bölüm bazlı gezginin eşik davranışı.
- Üreticinin yerel taslağının tutulma süresi ve dosya çevrimdışı davranışı.
- WeChat içi tarayıcı için desteklenen sürüm/cihaz matrisi.
- Kendi özetindeki dönem seçenekleri ve hangi oranların kullanıcı için gerçekten yararlı olduğu.

## 8. Sonuç

Emsallerin ortak ve TedarikApp'e uygun özü “daha büyük portal” değil; **rolün tek işini görünür kuyruk, değişmez talep, yapılandırılmış yanıt, kontrollü gönderim ve kanıtlanabilir geçmiş** halinde sunmaktır. Müşteri paneli pazar yeri/sepet dilinden; ithalatçı paneli kurumsal procurement kalabalığı ve rekabetten; üretici paneli açık RFQ pazarı ve mesajlaşma ağından bilinçli olarak ayrılmalıdır.
