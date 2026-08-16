# F30 — GTİP / Gümrük Sınıflandırma Motoru (fikir havuzu taslağı)

> Durum: **FİKİR** — uygulanmadı, iş emri kapsamına alınmadı. Kaynak: Ürün Sahibi analizi (16 Ağustos 2026).
> Havuz kaydı: docs/08 §3, F30. Belge ayrıca yakalama şemasının genişletilmesini öneriyor (F28 ile ilişkili) —
> docs/04 §2c SABİT şemadır (K18/K25); değişiklik PM kararı gerektirir, bu belge tek başına yetki değildir.

---

Evet. 1688 ürün sayfasına TedarikApp açısından baktığımda, bizim şu an düşündüğümüzden çok daha fazla veri var. Güncel 2026 sayfa yapısı ve DOM/network üzerinden çalışan extractor örneklerinde 50+ ayrı alan alınabiliyor: kademeli fiyatlar, tam SKU matrisi, stok, ağırlık/ölçüler, satıcı puanları, tekrar satın alma oranı, lojistik, garanti/hizmetler, promosyonlar ve bazı cross-border özellikleri dahil.

Bence Chrome eklentisini yalnız “başlık + fiyat + görsel + video” yakalayan bir araç olarak bırakmayalım. 1688 ürün sayfasını tek seferde mümkün olduğunca zengin biçimde ürünün dijital pasaportu olarak yakalamak çok daha doğru.

1. Ürünün temel kimliği — kesinlikle alınmalı

1688 ürün detayından ürünün benzersiz offerId'si, başlığı, URL'si, satış birimi, kategori/özellik bilgileri, fiyat aralığı ve görseller alınabiliyor. Güncel extractorlar offerId, başlık, fiyat aralığı, satış sayısı, ana görseller ve satıcı nesnesini ayrı alanlar olarak çıkarabiliyor.

Veri	Örnek	Bizde
offer_id	927875250705	🔴 Zorunlu
Ürün URL	detail.1688...	🔴
Orijinal Çince başlık	304不锈钢...	🔴
Satış birimi	adet/set/koli	🔴
1688 kategori ID	postCategoryId	🟡
Üst kategori ID	—	🟡
Yakalama zamanı	tarih/saat	🔴
Platform	1688	🔴

offer_id özellikle çok değerli. Ürünün linki veya başlığı değişse bile ürünün ana eşleştirme anahtarı olarak kullanabiliriz.

Ben ayrıca bizim tarafımızdan:

capture_id
captured_at
extension_version
parser_version

eklerdim.

2. Fiyat sistemi — en önemli alanlardan biri

1688'in en değerli tarafı yalnızca “ürün ¥9” bilgisi değil.

Bir ürün:

1–9 adet → ¥12,00
10–99 adet → ¥10,50
100+ → ¥8,90

gibi kademeli fiyatlandırılabiliyor. Güncel ürün detay yapılarından genel min/max fiyat, minimum sipariş miktarı, miktar kırılımları ve SKU bazlı fiyatlar ayrı ayrı alınabiliyor.

Dolayısıyla:

Fiyat verisi	Önem
Minimum fiyat	🔴
Maksimum fiyat	🔴
MOQ / başlangıç adedi	🔴
Bütün fiyat kademeleri	🔴🔴🔴
SKU özel fiyat	🔴
Birim	🔴
Kampanyalı fiyat	🟡
Normal/eski fiyat	🟡
Kupon/indirim	🟡

Bizim şimdiki sistemde sadece:

price_yuan

tutmak yetersiz.

Bence:

"pricing": {
  "currency": "CNY",
  "min_price": "8.90",
  "max_price": "12.00",
  "moq": 1,
  "unit": "个",
  "tiers": [
    {"min_qty": 1, "max_qty": 9, "price": "12.00"},
    {"min_qty": 10, "max_qty": 99, "price": "10.50"},
    {"min_qty": 100, "max_qty": null, "price": "8.90"}
  ]
}

şeklinde saklanmalı.

Bu ileride “500 adet alırsam en uygun fiyat hangi tedarikçide?” hesabını otomatik yaptırır.

3. SKU / varyasyon matrisi — kesinlikle tam alınmalı

Burası çok kritik.

1688'de ürün:

Renk
   Beyaz
   Siyah
   Pembe


Boy
   S
   M
   L


Model
   iPhone 16
   iPhone 16 Pro
   iPhone 16 Pro Max

şeklinde olabilir.

1688 detail yapısında property grupları ile her kombinasyonu SKU'ya bağlayan sku_map; SKU fiyatı ve SKU stoku dahil alınabiliyor.

Örnek:

{
  "sku_id": "5833485852524",
  "attrs": {
    "颜色": "黑色",
    "适用型号": "iPhone 17"
  },
  "price": "7.99",
  "stock": 1092
}

Dolayısıyla mutlaka:

SKU verisi	Bizde
sku_id	🔴
spec_id	🟡
Varyant isimleri	🔴
Varyant değerleri	🔴
SKU fiyatı	🔴
SKU stoku	🔴
SKU görseli	🔴
Seçilen SKU	🔴

saklanmalı.

Çok önemli

Sadece kullanıcının seçtiği SKU'yu değil, bütün SKU matrisini saklayalım.

Bugün siyah seçtik.

Bir ay sonra:

“Bunun pembesi de var mıydı?”

diye baktığımızda tekrar 1688'e gitmeye gerek kalmasın.

4. SKU bazlı ağırlık ve ölçüler 😮

Benim burada özellikle dikkatimi çeken alanlardan biri bu.

Güncel 1688 extractorlarında SKU düzeyinde:

ağırlık,
uzunluk,
genişlik,
yükseklik,
hacim

gibi verilerin de alınabildiği örnekler var.

Ayrıca ürün-detail veri katmanlarında SKU bazlı packing/weight bilgileri ayrı olarak bulunabiliyor; fakat her satıcı doldurmadığı için boş gelmesi normal kabul edilmeli.

Bize çok lazım.

Çünkü Çin'den DDP alıyoruz.

Mesela:

1 ürün = 320 gram
1000 ürün = 320 kg

veya:

koli 60 × 40 × 50
100 koli = 12 CBM

hesaplayabiliriz.

Ben:

unit_weight
length
width
height
volume
packing_unit

alanlarını yüksek öncelik yaparım.

5. Stok bilgisi

Şu anda proje tasarımımızda stok çok ön planda değil.

Ama SKU bazında mevcut stok alınabiliyor.

Örneğin:

Beyaz / S → 1.092
Beyaz / M → 35
Siyah / M → 0
Bize faydası

Sipariş vermeden önce:

2.000 adet istiyoruz ama satıcının görünen stoğu 850.

uyarısı.

Sonra fiyat geçmişi gibi:

stok geçmişi

de tutulabilir.

16 Ağustos: 8.200
20 Ağustos: 4.100
25 Ağustos: 300

Bu ürünün talep gördüğünü bile gösterebilir.

6. Satış / talep verileri

Güncel kaynaklardan:

satış adedi,
görünen satış sayısı,
ilgi/“want buy” benzeri göstergeler

alınabiliyor.

Bize:

sold_count
display_sale_count
want_buy_count

gibi alanlar eklenebilir.

Bunun önemi şu:

A ürünü:

¥4,50
12 satış

B ürünü:

¥4,80
18.000 satış

ise sırf 30 fen ucuz diye A'yı seçmek mantıklı olmayabilir.

7. Ürün yorumları ve satın alınan varyant

Ürün yorumları da ayrı veri kaynağından yakalanabiliyor. Güncel 1688 review extractorlarında yorum başına:

yıldız,
yorum metni,
tarih,
satın alınan varyant,
sipariş miktarı,
tekrar müşteri olup olmadığı

gibi alanlar çıkarılabiliyor.

Ama v1 için bütün yorumları DB'ye doldurmazdım.

Onun yerine:

review_count
rating
good_review_rate
repeat_buyer_review_count

gibi özetleri alalım.

İleri fazda:

“Son 100 yorumda kırık/kalite problemi var mı?”

AI analizi bile yapılabilir.

8. Ürün özellikleri / teknik parametreler

1688 ürün sayfasında kategoriye bağlı olarak:

Malzeme
Marka
Menşei
Stil
Model
Ölçü
Renk
Kapasite
Fonksiyon
Uygun model
Üretim yöntemi

gibi onlarca özellik bulunabiliyor. Bunlar yapılandırılmış name/value çiftleri şeklinde çıkarılabiliyor.

Örneğin:

"attributes": {
   "材质": "304不锈钢",
   "容量": "1000ml",
   "产地": "浙江",
   "风格": "简约"
}
Bunları kesin almalıyız.

Fakat her kategori için ayrı DB kolonu açmayalım.

Şöyle:

product_attributes

veya JSON.

Sonra AI:

材质 → Malzeme
304不锈钢 → 304 Paslanmaz Çelik

olarak Türkçeleştirebilir.

9. Ana görseller

Kesinlikle:

main image
gallery images
SKU images

alınmalı.

1688 detay servislerinde ana görseller ve alternatif boyutları ayrı alanlar olarak mevcut.

Biz:

original_url
downloaded_path
sha256
sort
type

saklayabiliriz.

10. Ürün detay sayfasındaki uzun görseller

1688'de sadece üstteki galeri yok.

Aşağıda uzun ürün tanıtımı:

kullanım görselleri,
ölçü diyagramları,
paketleme,
renk açıklamaları,
fabrika resimleri

olabiliyor.

Bunlar da ayrı detail images olarak çıkarılabiliyor.

Bence alalım.

Ama ana listeye göstermeyelim.

Ürün kartında:

Ana Görseller
Detay Görselleri

diye ayrı tutabiliriz.

Ürün Trendyol/Hepsiburada'ya konulacağı zaman inanılmaz işimize yarar.

11. Video

Kesinlikle alınmalı:

video_url
video thumbnail

ve mümkünse bizim sunucuya indirilip saklanmalı.

Çünkü 1688 linki zamanla:

oturum,
hotlink,
token,
CDN

nedeniyle çalışmayabilir.

Bu zaten TedarikApp'in paylaşım linki fikrinin güçlü taraflarından biri.

12. Tedarikçi kimliği — burada çok daha fazla veri var

Şu anda yalnız:

vendor_name
vendor_url

düşünüyoruz.

Ama alınabilenler daha fazla:

company_name
login_id
member_id
user_id
shop_url / winport_url
seller badges

Bunların hepsi alınmalı.

Çünkü aynı şirketin 50 ürününü yakalarsan:

50 ayrı satıcı

gibi değil:

supplier_id = XYZ
50 ürün

olarak bağlayabiliriz.

Bu da ileride Tedarikçi Kartları modülümüzün temelini hazırlar.

13. Satıcı güven / performans değerleri ⭐

Burada çok güzel veriler bulunabiliyor.

Güncel extractor örnekleri satıcı tarafında:

serviceScore
buyerRepeatRate
seller badge

ve daha geniş çıkarmalarda shop/reputation skorlarını alabiliyor.

Sayfa örneklerinde de:

tekrar satın alma oranı,
kalite,
lojistik,
iade deneyimi,
anlaşmazlık çözümü,
tedarik danışmanlığı

gibi skorların gösterildiği görülüyor.

Bunları mutlaka yakalayalım.

Örneğin:

"supplier_metrics": {
  "service_score": "4.5",
  "repeat_purchase_rate": "65.82",
  "seller_tenure_years": 8
}

Bununla ileride:

Tedarikçi Güven Puanı

üretebiliriz.

14. Mağaza yaşı

Bence çok değerli.

Örneğin:

1688'de 1 yıl

ile

1688'de 13 yıl

aynı değil.

Tek başına kalite garantisi değildir ama supplier scoring için güçlü bir sinyaldir.

Güncel 1688 veri çıkarımlarında sellerTenure gibi alanların kullanılabildiği görülüyor.

15. Tekrar satın alma oranı

Bence en değerli satıcı metriklerinden biri.

Örneğin:

buyer repeat rate = %65,82

müşterilerin tekrar o satıcıdan sipariş verdiğini gösterir.

1688 detail extractorları bu metriği supplier comparison için özellikle kullanıyor.

Ben bunu 🔴 yüksek öncelikli yaparım.

16. Satıcının bulunduğu bölge

Alınabilir:

province
city
dispatch location

Güncel extraction örneklerinde location, lojistik çıkış bölgesi ve şirket konumu alınabiliyor.

Örneğin:

Guangdong / Guangzhou
Zhejiang / Yiwu
Bize neden lazım?

Çünkü tedarikçiler aynı bölgede ise:

konsolidasyon maliyeti azalabilir.

İleride:

Bu siparişteki 36 ürünün 27'si Guangzhou bölgesinden.

diyebiliriz.

Bu harika bir tedarik özelliği olur.

17. Sevkiyat / çıkış süresi

Sayfada:

48 saat içinde gönderim
72 saat içinde gönderim
3 gün

gibi hizmet taahhütleri bulunabiliyor. Güncel extractionlarda lojistik alanında çıkış yeri ve dispatch SLA alınabiliyor.

Alalım:

dispatch_sla_hours
dispatch_text

İleride gecikme tahminlerinde kullanılabilir.

18. Çin içi kargo bilgileri

Bazı ürünlerde:

kargo ücreti
kargo şirketi
nereden gönderiliyor

görülebiliyor. Güncel extraction örneklerinde lojistik sağlayıcısı/dispatch bilgisi de raporlanabiliyor.

Biz DDP çalışsak da tedarikçiden forwarder'a giden Çin içi hareket için faydalı olabilir.

19. Hizmet ve garanti şartları

Ürünlerde:

7 gün sebepsiz iade
geç gönderimde tazminat
hızlı iade
hasar tazmini
alıcı koruması

gibi servisler bulunabiliyor.

Bunları:

"services": [
   "7天无理由退货",
   "晚发必赔"
]

gibi ham haliyle saklayabiliriz.

Sonra Türkçesi:

7 gün koşulsuz iade
geç sevkiyat güvencesi
20. Buyer Protection / satıcı rozetleri

Extractor yapılarında:

buyer protection
seller identity badges
sellerSign

de mevcut.

Alalım.

Örneğin supplier kartında:

✓ Alıcı Koruması
✓ Güçlü Satıcı
✓ Doğrulanmış Firma

gibi gösterebiliriz.

Ama bunları bizim uydurduğumuz dereceler olarak değil, 1688 kaynaklı rozet olarak saklarız.

21. Dropshipping / dağıtım özellikleri

Bazı ürünler:

consignOffer
hasConsignPrice

ve farklı dağıtım kanallarını destekleyebiliyor; mevcut detay veri katmanlarında bu bilgiler ayrı çıkarılabiliyor.

Tilbe Home açısından doğrudan sipariş için çok önemli değil.

🟢 Alabiliriz ama düşük öncelik.

22. Cross-border özellikler

Bazı güncel extractorlar:

yabancı dil ambalajı,
kutu işareti,
FBA etiketi

gibi cross-border yeteneklerini de bulabiliyor.

Bu bizim için ilginç olabilir.

Özellikle ileride:

Amazon FBA
özel paketleme
özel etiket

çalışırsak.

23. Promosyon ve kuponlar

1688 ürünlerinde:

indirim
kupon
ilk sipariş indirimi
kampanya
toplu alım indirimi

gibi veriler bulunabiliyor; 2026 extractorlar promosyon/kupon verilerini ayrı veri grubu olarak yakalıyor.

Bunlar ürünün temel fiyatı ile karıştırılmamalı.

Şöyle:

base_price
promotion_price
promotion_start
promotion_end
coupon

ayrı tutulmalı.

24. Benim TedarikApp için önem sıralamam
Veri	Öncelik
Offer ID	🔴🔴🔴
Orijinal başlık	🔴🔴🔴
Ürün URL	🔴🔴🔴
Tam SKU matrisi	🔴🔴🔴
SKU ID	🔴🔴🔴
SKU fiyatı	🔴🔴🔴
SKU stok	🔴🔴🔴
Kademeli fiyatlar	🔴🔴🔴
MOQ	🔴🔴🔴
Ana görseller	🔴🔴🔴
SKU görselleri	🔴🔴🔴
Video	🔴🔴🔴
Satıcı Company/Member ID	🔴🔴🔴
Tekrar satın alma oranı	🔴🔴
Mağaza yaşı	🔴🔴
Satıcı skorları	🔴🔴
Satış miktarı	🔴🔴
Malzeme/özellikler	🔴🔴
Ağırlık	🔴🔴🔴
Ölçü/hacim	🔴🔴🔴
Gönderim yeri	🔴🔴
Gönderim süresi	🔴🔴
Hizmet/garantiler	🔴
Detay görselleri	🔴🔴
Promosyonlar	🔴
Cross-border kabiliyet	🟡
Dropshipping bilgisi	🟢
25. Mevcut TedarikApp capture şemamızı büyütelim

Şu an tasarımımız kabaca:

{
  "platform": "1688",
  "external_id": "...",
  "title_original": "...",
  "url": "...",
  "vendor": {},
  "price_yuan": "...",
  "sku_matrix": [],
  "images": [],
  "video_url": "..."
}

seviyesinde.

Ben bunu yaklaşık şöyle yapardım:

{
  "schema_version": 2,
  "capture_id": "UUID",
  "captured_at": "...",


  "source": {
    "platform": "1688",
    "offer_id": "...",
    "url": "...",
    "category_id": "..."
  },


  "product": {
    "title_original": "...",
    "unit": "个",
    "attributes": {},
    "sales_count": 1200
  },


  "pricing": {
    "currency": "CNY",
    "min": "7.79",
    "max": "7.99",
    "moq": 30,
    "tiers": []
  },


  "variants": {
    "properties": [],
    "skus": []
  },


  "media": {
    "main_images": [],
    "sku_images": [],
    "detail_images": [],
    "video_url": null
  },


  "packing": {
    "weight": null,
    "length": null,
    "width": null,
    "height": null
  },


  "supplier": {
    "company_name": "...",
    "login_id": "...",
    "member_id": "...",
    "user_id": "...",
    "shop_url": "...",
    "province": "...",
    "city": "...",
    "repeat_purchase_rate": null,
    "service_score": null,
    "badges": []
  },


  "logistics": {
    "dispatch_location": "...",
    "dispatch_sla": "...",
    "carrier": null
  },


  "services": [],
  "promotions": [],


  "extension": {
    "version": "...",
    "parser_version": "..."
  }
}

Ve bence ham yakalama verisini de ayrıca saklamalıyız:

raw_capture_json

Çünkü bugün parser'da kullanmadığımız bir 1688 alanının 6 ay sonra değerli olduğunu fark edersek, geçmiş ürünlerde elimizde ham veri olur.

En önemli görüşüm

TedarikApp'in eklentisi:

“kullanıcı ekle dediğinde yalnız ihtiyacımız olan 10 alanı çek”

mantığında olmamalı.

Daha doğrusu:

“ürün sayfasında güvenilir biçimde ulaşabildiğimiz mümkün olan bütün yapısal veriyi bir defa yakala; TedarikApp o verinin ihtiyacı olan kısmını kullansın.”

olmalı.

Çünkü ürün sayfasına zaten erişmişiz. Aynı anda fiyat geçmişinin, tedarikçi puanlamasının, MOQ analizinin, SKU stok takibinin, CBM hesabının ve gelecekteki AI özelliklerinin temel datasını bedavaya toplamış oluruz.

Özellikle SKU + kademeli fiyat + ağırlık/ölçüler + supplier ID + tekrar satın alma oranı + mağaza yaşı mevcut planımıza eklenirse, TedarikApp'in değeri ciddi biçimde artar. Bunların çoğu 1688 ürün detayında veya ürün sayfasının yüklediği veri katmanlarında mevcut.

İstersen bir sonraki adımda bunu daha da ileri götürüp 1688 ürün sayfasından alınabilecek tüm alanların 80–100 maddelik eksiksiz veri envanterini çıkarayım ve her birine “al / alma / zorunlu / ileri faz / hangi DB tablosuna yazılacak” etiketi koyayım. Bu, doğrudan TedarikApp'in Chrome eklentisi için veri sözleşmesi olur.

buna gtip bağlayabilir miyiz bir araştırma yap bu gtip nasıl çalışır nasıl bağlanır

Evet. Hatta GTİP'i TedarikApp'e bağlamak çok değerli olur. Ama bunu basit bir “1688 ürün adı → 12 haneli GTİP” eşleştirmesi olarak değil, ürünün teknik özelliklerinden GTİP adaylarını bulan, gerekçelendiren, doğrulatan ve mevzuat sürümünü takip eden bir sınıflandırma motoru olarak kurmak gerekir.

Türkiye'de GTİP, 12 haneli Gümrük Tarife İstatistik Pozisyonu kodudur. İlk 6 hane uluslararası HS/Armonize Sistem kodudur; 7–8. haneler AB Kombine Nomanklatürüyle uyumludur; 9–10 Türkiye'nin millî alt açılımı, 11–12 ise istatistik kodudur.

GTİP aslında nasıl çalışıyor?

Örneğin yapı kabaca şöyledir:

Seviye	Hane	Anlam
Fasıl	2	Genel ürün grubu
Tarife pozisyonu	4	Daha dar ürün grubu
HS alt pozisyon	6	Uluslararası ortak sınıflandırma
CN/KN	8	AB ile uyumlu alt sınıflandırma
Millî açılım	10	Türkiye'ye özgü ayrım
GTİP	12	Türkiye'deki tam kod

Dolayısıyla Çin'de veya başka bir ülkede bulunan bir 6 haneli HS kodu bize ciddi ipucu verebilir; çünkü ilk altı hane uluslararası ortaktır. Fakat Çin'deki 8/10 haneli bir kodu alıp Türkiye'nin 12 haneli GTİP'i diye kullanamayız. Türkiye'ye özgü son açılımları ayrıca çözmemiz gerekir.

Ve önemli nokta şu:

GTİP ürünün adına göre değil, eşyanın gerçekte ne olduğuna göre belirlenir.

Ticaret Bakanlığı da tarife sınıflandırmasının teknik/uzmanlık gerektirdiğini özellikle belirtiyor. Genel olarak 1–83. fasıllardaki birçok ürün mamul olduğu maddeye, 84–96. fasıllardaki birçok ürün ise işlevine göre sınıflandırılıyor; ancak kesin sınıflandırmada tarife metinleri, açıklama notları ve Genel Yorum Kuralları birlikte değerlendirilir.

1688 ile neden çok güzel birleşiyor?

Az önce konuştuğumuz 1688 yakalama sistemi tam burada işe yarıyor.

GTİP tahmini için ürün başlığından çok şu veriler değerli:

1688'den yakalanacak veri	GTİP açısından önemi
Orijinal Çince ürün adı	Yüksek
Türkçe normalize edilmiş ürün adı	Yüksek
Malzeme	🔴 Çok yüksek
Malzeme oranları	🔴 Çok yüksek
Ürünün asıl işlevi	🔴 Çok yüksek
Kullanım amacı	🔴 Çok yüksek
Elektrikli / elektriksiz	🔴
Güç / Watt / Voltaj	🔴
Mekanik çalışma biçimi	🔴
Ürün yapısı	🔴
Tek ürün / set	🔴
SKU/varyant özellikleri	🔴
Boyut	Orta/Yüksek
Ağırlık	Orta
Kapasite	Orta/Yüksek
Tekstil ise lif bileşimi	🔴
Örme/dokuma bilgisi	🔴
Cam/plastik/çelik/seramik vb.	🔴
Teknik parametreler	🔴
Ürün görselleri	Yardımcı
Detay açıklaması	Yüksek
Satıcının verdiği model/no	Yardımcı

Mesela sadece:

“Saklama kabı”

yazması GTİP için yeterli değil.

Çünkü ürün:

plastik, cam, paslanmaz çelik, seramik, hatta farklı malzemelerden oluşan bir set olabilir.

Aynı ticari isim olmasına rağmen sınıflandırma farklılaşabilir.

Sistem tam olarak şöyle çalışmalı

Ben TedarikApp'e ayrı bir:

GTİP & Gümrük Sınıflandırma Motoru

eklerdim.

Akış:

1688 ürün sayfası
        ↓
Chrome Extension
        ↓
Başlık + özellikler + malzeme + SKU + teknik bilgi
        ↓
Çince → normalize Türkçe
        ↓
Ürün teknik profili
        ↓
GTİP sınıflandırma motoru
        ↓
3–5 GTİP adayı
        ↓
Tarife metni + açıklama notu + geçmiş BTB karşılaştırması
        ↓
Eksik bilgi varsa kullanıcıya soru
        ↓
Güven puanı
        ↓
İnsan / gümrük müşaviri doğrulaması
        ↓
DOĞRULANMIŞ GTİP

Buradaki kritik kelime “aday”.

AI veya algoritmanın tahminini doğrudan “kesin GTİP” yapmamalıyız.

Türkiye'nin bütün GTİP cetvelini sisteme koyabilir miyiz?

Evet. Ben tam olarak bunu yapardım.

2026 yılı Türk Gümrük Tarife Cetveli, Karar No. 10781 ile 30 Aralık 2025 tarihli Resmî Gazete'de yayımlandı. Ticaret Bakanlığı bunun Excel formatını da resmi olarak yayımlıyor.

Bu çok güzel.

TedarikApp:

Ticaret Bakanlığı resmi 2026 GTİP Excel
                ↓
         Import / Parser
                ↓
      TedarikApp MariaDB

şeklinde kendi yerel tarife veritabanını oluşturabilir.

Örneğin:

tariff_versions
gtip_codes
gtip_descriptions
gtip_hierarchy
Veritabanını nasıl tasarlardım?
tariff_versions
Alan	Örnek
id	3
year	2026
decision_no	10781
effective_from	2026-01-01
source	Ticaret Bakanlığı
source_hash	SHA256
imported_at	...
gtip_codes
Alan
id
tariff_version_id
code_12
hs_6
cn_8
national_10
description_tr
unit
parent_code
chapter
heading
active

Örneğin TedarikApp içinde:

39
└─ 3923
   └─ 3923.xx
      └─ ...
         └─ 12 haneli GTİP

ağaç şeklinde dolaşabiliriz.

Fakat yıllık sürüm şart

GTİP'i products.gtip = "xxxxxxxxxxxx" şeklinde tek alan olarak tutmak yanlış olur.

Çünkü tarife cetveli değişebiliyor.

Bakanlığın bilgilendirmesine göre Türk Gümrük Tarife Cetveli her yıl yayımlanıyor ve 1 Ocak'ta yürürlüğe giriyor.

Üstelik 2026 içerisinde bile ara değişiklikler yapıldı; Temmuz 2026 düzenlemelerinde bazı ürünlerin istatistik pozisyonları yeniden düzenlendi.

Bu nedenle üründe:

GTİP: 123456789012
Tarife sürümü: 2026
Geçerlilik başlangıcı: 2026-01-01

saklanmalı.

Ürünün GTİP bağlantı tablosu

Ben:

product_tariff_classifications

kullanırdım.

Alan	Açıklama
product_id	Ürün
sku_id	gerekirse varyant
tariff_code_id	GTİP
status	suggested / reviewed / verified / btb
confidence	0–100
classification_reason	neden
source_type	AI/manual/müşavir/BTB
classifier_version	motor sürümü
verified_by	doğrulayan
verified_at	tarih
btb_reference	varsa
effective_from	geçerlilik

Böylece ekranda:

Önerilen GTİP: 3924....
Güven: %84
Durum: ⚠️ Doğrulanmadı

veya:

GTİP: ...
Durum: ✅ Gümrük müşaviri tarafından doğrulandı

gösterebiliriz.

SKU bazında bile GTİP gerekebilir

Bu önemli.

1688'de tek ürün sayfasında:

Varyant 1 → Plastik
Varyant 2 → Paslanmaz çelik

gibi farklı malzemeler varsa teorik olarak aynı listing içindeki SKU'ların sınıflandırması bile farklılaşabilir.

Bu nedenle:

ürün GTİP'i

yanında gerektiğinde:

SKU GTİP'i

desteklenmeli.

Varsayılan:

tüm SKU'lar ürün GTİP'ini devralır.

Ama gerektiğinde SKU seviyesinde override yapılır.

Sistem GTİP'i nasıl bulacak?

Burada yalnız yapay zekâ kullanmazdım.

Hibrit motor yapardım:

1. Resmî Türk Gümrük Tarife Cetveli
2. Tarife açıklama notları
3. Genel Yorum Kuralları
4. Sınıflandırma kararları
5. Geçmiş BTB kararları
6. Ürün teknik özellikleri
7. AI semantik eşleştirme
8. Kural motoru

Ticaret Bakanlığı da sınıflandırma için Türk Gümrük Tarife Cetveli yanında açıklama notları, Armonize Sistem sınıflandırma görüş/kararları ve AB sınıflandırma kararlarının kullanılabileceğini belirtiyor.

BTB'yi de bağlayabiliriz ⭐

Bu sistemin en profesyonel taraflarından biri olabilir.

BTB = Bağlayıcı Tarife Bilgisi.

Ticaret Bakanlığı'nın verdiği, belirli eşyanın hangi tarifede sınıflandırıldığına ilişkin idari karardır. Güncel Bakanlık bilgisinde BTB'nin veriliş tarihinden itibaren 6 yıl geçerli olduğu; ancak tarife veya uluslararası sınıflandırma kararları değişirse geçerliliğini yitirebileceği belirtiliyor.

BTB veri tabanında kamuya açık olarak:

GTİP
eşya tanımı
BTB referans numarası
sınıflandırma gerekçesi
geçerlilik başlangıcı

görülebiliyor.

Bu bizim motor için altın değerinde veri.

Örneğin sistem:

1688 ürünü
↓
"304 stainless steel ...."
↓
Benzer BTB kararlarını ara
↓
BTB #XXXX:
çok benzer ürün → XXXXXXXX...

deyip:

Benzer resmi BTB bulundu

gösterebilir.

Ama başkasının BTB'sini kendi bağlayıcı kararımızmış gibi kullanamayız; BTB'nin bağlayıcılığı hak sahibi ve kararda tanımlanan eşya ile ilişkilidir.

Kesinlik gerekiyorsa

Örneğin yılda:

50.000 adet

getireceğimiz bir ürün var ve yanlış GTİP ciddi vergi farkı doğuracak.

TedarikApp:

🔴 Yüksek mali risk — BTB önerilir

uyarısı verebilir.

BTB başvurusu Bakanlığın yetkili bölge müdürlüklerine veya elektronik sistem üzerinden yapılabiliyor. Başvuruda ayrıntılı eşya tanımı ve sınıflandırmaya yardımcı eklerin sunulması gerekiyor. BTB talebinin kendisi ücretsiz; laboratuvar/ekspertiz gibi ek masraflar doğarsa bunlar başvuru sahibine ait.

1688 verisinden BTB başvuru dosyası bile hazırlayabiliriz

Bu daha da güzel.

TedarikApp zaten topluyor:

ürün adı
görseller
malzeme
ölçü
teknik özellikler
SKU
işlev
üretici/satıcı
ürün linki

Bunlardan otomatik:

GTİP Teknik Tanım Dosyası

oluşturabiliriz.

Örneğin:

Ticari adı:
Ürünün tam teknik tanımı:
Ana kullanım amacı:
Malzeme:
Malzeme oranları:
Çalışma prensibi:
Elektrikli mi:
Ölçüler:
Ağırlık:
Model:
Ürün fotoğrafları:
Üretici bilgisi:
1688 kaynak bağlantısı:
Önerilen GTİP:
Sınıflandırma gerekçesi:
Alternatif GTİP'ler:

Böylece gümrük müşavirine veya BTB sürecine çok temiz veri gider.

Vergiler de bağlanabilir mi?

Evet ama GTİP motorundan ayrı katman yapalım.

Ticaret Bakanlığı'nın Tarife Arama uygulaması GTİP bazında gümrük vergilerini sorgulamak için kullanılıyor.

Ancak yaptığım resmi kaynak araştırmasında TARA için kamuya açık belgelenmiş bir API bulamadım. Bu nedenle şu aşamada resmi uygulamayı scraping ile bağımlılık haline getirmem.

Daha sağlam mimari:

GTİP Motoru
      ↓
12 haneli kod
      ↓
İthalat Mevzuatı Motoru
      ↓
Normal gümrük vergisi
İGV
ÖTV
KDV
Gözetim
Anti-damping
TAREKS
Ürün güvenliği
İzin / uygunluk

Çünkü GTİP tek başına toplam ithalat maliyetini belirlemez. Menşe de çok önemlidir; Bakanlık, gümrük vergileri ve antidamping/telafi/korunma gibi ticaret politikası önlemlerinde menşe ile tarife sınıflandırmasının birlikte önem taşıdığını belirtiyor.

Yani TedarikApp'te ayrıca:

country_of_origin
country_of_dispatch

ayrı olmalı.

“Çin'den gönderildi” = “Çin menşeli” demek değildir.

Ben bunu proje yol haritasına kesin eklerdim

Adını:

M23 — GTİP, Gümrük ve İthalat Uygunluk Modülü

koyabiliriz.

İlk sürüm:

Özellik	Aşama
Resmî GTİP cetvelini sisteme alma	🔴
GTİP ağacı/arama	🔴
Ürüne GTİP bağlama	🔴
1688 özelliklerinden aday önerme	🔴
Eksik teknik bilgi soruları	🔴
Güven puanı	🔴
İnsan/müşavir doğrulaması	🔴
Tarife yılı/sürümü	🔴
BTB referansı	🔴
Eski GTİP tarihçesi	🔴
Yıllık tarife güncelleme motoru	🔴
Vergi/önlem analizi	sonraki
TAREKS eşleştirme	sonraki
AI sınıflandırma	sonraki
Otomatik BTB dosyası	sonraki
Benim özellikle ekleyeceğim bir özellik daha var

Her yeni yılın GTİP cetveli yüklenince TedarikApp otomatik karşılaştırır:

2026 → 2027 GTİP Değişiklik Analizi

ve der:

✅ 2.814 ürünün GTİP'i hâlâ geçerli
⚠️ 37 ürünün GTİP tanımı değişti
🔴 8 ürünün eski GTİP'i kaldırıldı

Sonra yalnız riskli ürünleri tekrar inceletir.

Bu özellikle önemli; çünkü 2026 içinde dahi istatistik pozisyonlarında ara değişiklikler yapıldı.

Sonuç: 1688 yakalama sistemine GTİP'i bağlamak yalnız mümkün değil; TedarikApp'in en değerli modüllerinden biri olabilir. En doğru yapı 1688 teknik veri → ürün profili → resmi tarife veritabanı → aday GTİP → sınıflandırma gerekçesi → insan/BTB doğrulaması → vergi ve ithalat önlemleri zinciri olur. Böyle kurarsak sistem “GTİP numarası tutan alan” değil, gerçek bir ithalat karar destek motoruna dönüşür.