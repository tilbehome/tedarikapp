# TedarikApp V3 P–Z Faz Haritası Araştırması ve Aday Fazlar

**Görev:** #31  
**Rapor tarihi:** 2026-08-29  
**Kaynak erişim tarihi:** 2026-08-29 (UTC)  
**Karar statüsü:** Araştırma ve öneri; PM denetler, Ürün Sahibi karar verir.  
**Kapsam:** V3 A–O sonrasında, tek kişilik ithalatçı-satıcı için ürün yeteneği boşlukları.

## 1. Yönetici özeti

Araştırma sonucunda P–Z için zorlamadan gruplanabilen 11 aday faz çıktı. Bunların hepsi bir sourcing/ERP ürünündeki özelliği kopyalamak için değil, TedarikApp'in özgün sınırlarına uyarlanmış iş problemlerini çözmek için önerildi:

1. **P — Operatör Hızı ve Sayfa Olgunluğu:** Ürün Sahibi'nin açık talebidir ve sonraki bütün ekranların ortak etkileşim altyapısıdır.
2. **Q — İhtiyaç ve Teknik Şartname Stüdyosu:** “Ben ne istiyorum?” sorusunu firmaya gönderilebilir ve sürümlenebilir veriye çevirir.
3. **R — Tedarik Senaryosu ve Karar Laboratuvarı:** DDP tekliflerini sepet/miktar/termin/kalite kısıtlarıyla değerlendirir; karar vermez.
4. **S — Karar ve Kanıt Defteri:** Hangi kararın hangi veri ve varsayıma dayandığını korur.
5. **T — Ambalaj ve Yük Mühendisliği:** D fazındaki CBM hesabını ambalaj şartnamesi ve uygulanabilir yük planına derinleştirir.
6. **U — Kalite Güvence ve Düzeltici Faaliyet:** I fazındaki numune/AQL ile D fazındaki mal kabul-rücu arasındaki önleyici kalite boşluğunu kapatır.
7. **V — Sipariş Değişiklik Kontrolü:** Yayımlanmış siparişteki değişiklikleri satır düzeyinde teklif–kabul–revizyon zincirine alır.
8. **W — İstisna ve Müdahale Merkezi:** Normal akıştan sapan işleri tek, öncelikli vaka kuyruğuna taşır.
9. **X — Tekrar Sipariş ve Ürün Hafızası:** Geçmiş doğrulanmış siparişi kör kopyalamadan güvenli başlangıç noktası yapar.
10. **Y — Kural ve Otomasyon Atölyesi:** Uygulama içi tekrar eden işleri dry-run, günlük ve geri alma sınırlarıyla otomatikleştirir.
11. **Z — Uyarlanabilir Veri Katmanı:** Çekirdek alanları bozmadan kategoriye özgü alan ve formüller ekler; veri modeli oturduğu için en sona bırakılır.

En yüksek doğrudan değer kümesi **P, Q, V, W, U ve X**'tir. **R ve T** sipariş hacmi/çeşitliliği arttıkça daha çok değer üretir. **Y ve Z** güç çarpanıdır fakat erken yapılırsa karmaşıklık çarpanına dönüşür. **S**, bağımsız faz olabilecek kadar çapraz kesen bir yetenektir; PM, kapsamı küçük tutarsa G'ye bir genişleme olarak da paketleyebilir.

Araştırılan ürünlerde sık görülen fakat kalıcı RET'lere çarpan ERP, stok, muhasebe, marketplace entegrasyonu, supplier due diligence ve resmî platform API işlevleri ayrı bölümde tutuldu; P–Z adaylarına alınmadı.

## 2. Araştırma yöntemi ve yorum sınırı

### 2.1 İncelenen ürün/desen grupları

- Alibaba Trade Assurance satın alma şartı–ödeme–koruma–uyuşmazlık akışı.
- Anvyl/Sage Supply Chain Intelligence, Sourcify, SourceDay, Coupa, SAP Ariba ve ServiceNow sourcing/PO işbirliği desenleri.
- Jungle Scout Supplier Database/Tracker, AiPrice, SellerSprite ve Amazon FBA araçları.
- Zentail ve Çin tarafında 甄云、领星、店小秘、马帮、鲸采云、商越 gibi procurement/cross-border ERP ürünleri.
- Specright, EasyCargo, QIMAone, Inspectorio ve Lifecycle PLM ile şartname, ambalaj, yük ve kalite desenleri.
- Linear, Attio, Airtable ve Notion ile yoğun veri ekranı mikro etkileşimleri.

Kaynak kataloğunda 54 doğrudan sayfa vardır. Büyük çoğunluk üreticinin resmî ürün sayfası, yardım merkezi veya dokümantasyonudur. Üretici sayfasındaki özellik iddiası **“emsalde bu desen var”** kanıtı olarak kullanıldı; ürünün ROI veya üstünlük iddiası bağımsız gerçek kabul edilmedi.

### 2.2 Değer derecelendirmesi

- **Yüksek:** Tek kişinin bugün yaptığı tekrarlı/hata açık işi belirgin azaltır veya pahalı ithalat hatasını siparişten önce yakalar.
- **Orta:** Belirli hacim, çok teklif, çok SKU ya da sık tekrar sipariş olduğunda anlamlıdır.
- **Düşük:** Kurumsal ekip, çoklu onay veya büyük işlem hacmi gerektirir; Tilbe Home bağlamında yakın dönem getirisi sınırlıdır.

### 2.3 Bağlayıcı ürün sınırları

- Sitelerden alım yoktur; platform sayfaları ürün bilgisi ve talep sinyali kaynağıdır.
- Gerçek satın alma fiyatı yalnız ithalatçı firmanın DDP teklifidir.
- Kullanıcı tek kişilik iç operatördür; N fazındaki portallar dış katmandır.
- Platform verisi, kullanıcının tarayıcıda gördüğü veya elle verdiği içerikten gelir; resmî platform API'si önerilmez.
- Veri eksikse sonuç uydurulmaz; yüksek MOQ tek başına cezalandırılmaz.
- `status.*` ve çıktı terimleri mevcut tek kaynaklardan gelir; yeni fazlar paralel durum sözlüğü üretmez.
- Bildirimler uygulama içi Panorama/Gelen Kutusu içinde kalır; e-posta/push açılmaz.

## 3. Emsal ürünlerden çıkarılan işlev desenleri

### 3.1 Sourcing ve sipariş yürütme

Alibaba Trade Assurance, üzerinde uzlaşılan sipariş şartlarını, ödeme korumasını ve şartlar karşılanmadığında kanıtlı çözüm/iadeyi tek zincirde ele alır [K01]. TedarikApp platformdan ödeme yapmayacağı için escrow kopyalanamaz; alınması gereken desen, **dondurulmuş şart–kanıt–sapma–çözüm** zinciridir.

Anvyl, PO oluştururken kayıtlı ürün/tedarikçi verisini doldurur; kilometre taşlarını, faaliyetleri, dosyaları ve iletişimi aynı sipariş altında izler [K02]. Kilometre taşı tarihleri durum mantığına bağlanır ve tarih değişikliğinde neden ister [K03]. Bölünmüş sevkiyat önerisi, bir siparişin parti veya taşıma biçimine göre bölünmesini açık bir iş nesnesi yapar [K04]. Bunlar C/D'deki temel akışın ötesinde **değişiklik kontrolü** ve **satır/parti taahhüdü** boşluğu olduğunu gösterir.

SourceDay, PO'nun “gönderildi” olmasını “tedarikçi taahhüdü var” ile eşitlemez; tarih, miktar ve fiyat değişikliklerini görünür istisna olarak işler [K44]. Değişiklik emri; neyin, kim tarafından ve neden değiştiğini ayrı sürüm olarak tutar [K45]. ServiceNow aynı deseni istisna, görev, öncelik ve müdahale planıyla genişletir [K43]. Coupa ise başlık veya satır düzeyinde kabul/ret ve bağlamsal konuşmayı destekler [K41].

### 3.2 Şartname, ambalaj ve kalite

Sourcify akışı, ciddi fabrika görüşmesinden önce ölçülebilir ürün gereksinimi/tech pack gerekliliğini açıkça vurgular; numune turları, üretim gözetimi ve sevkiyat öncesi kontrolü aynı ürün hazırlık zincirine bağlar [K10][K11]. Lifecycle PLM, canlı şartname, BOM, ölçüm, revizyon ve onayları tek ürün kaydında tutar [K52]. TedarikApp için BOM'un muhasebe/üretim ERP derinliği değil, **ürün–set–ambalaj bileşeni ve şartname sürümü** kısmı değerlidir.

Specright ürün ve ambalaj şartnamelerini ilişkili, sürümlü veri olarak ele alır [K46][K47]. EasyCargo, ölçü/ağırlık/kısıtlarla otomatik yerleşim, elle düzeltme, 3B görünüm ve paylaşılabilir yük planı üretir [K48]. D fazındaki CBM toplamı bu iki desenle “hesap”tan “uygulanabilir plan”a çıkabilir.

QIMAone, standart kontrol listesi, gerçek zamanlı kusur verisi, fotoğraf/video/belge kanıtı ve kalite görünürlüğü sunar [K49][K50][K51]. Inspectorio, kontrolleri yalnız sevkiyat sonunda değil üretimin farklı noktalarına dağıtır [K53]. TedarikApp için değerli olan bölüm compliance değil; **golden sample–kontrol maddesi–kusur–düzeltici faaliyet–yeniden kontrol** zinciridir.

### 3.3 Teklif ve karar desteği

Jungle Scout Supplier Tracker teklifleri kaydetme ve aynı/farklı kaynakların tekliflerini karşılaştırma deseni sağlar [K08][K09]. Supplier Database'in gümrük kayıtlarından tedarikçi keşfi kısmı due diligence/haricî veri sınırına çarpar [K07]; aday faza alınmadı.

SAP Ariba Guided Sourcing, miktarı bir veya birden çok kaynağa bölerek manuel/optimizasyonlu award senaryoları kurar [K38], fiyat dışı puanlama yapar [K39] ve senaryoları bid analysis ekranında karşılaştırır [K40]. TedarikApp uyarlamasında “award” otomatik sipariş anlamına gelmez; yalnız DDP teklif sepetini karşılaştıran açıklanabilir bir karar taslağıdır.

### 3.4 Mikro etkileşim ve uyarlanabilir veri modeli

Linear seçili kayıtlar için komut paleti ve sağ tık menüsünü aynı eylem modeline bağlar [K23]; filtreli listeyi kayıtlı görünüme dönüştürür [K24] ve klavye ile filtre/gezinti sağlar [K25]. Attio tabloyu doğrudan düzenleme, filtreleme, toplu güncelleme ve başka elektronik tablodan kopyala-yapıştır yüzeyi yapar [K27][K29]; hızlı eylemler bağlama göre değişir [K28]. Airtable çoklu görünüm, hücre/range kısayolları, kayıt revizyon geçmişi ve kayıt şablonlarını bir araya getirir [K31][K32][K33][K34]. Notion aynı veriyi tablo/board/gallery gibi görünümlerde işler ve seçili satırların özelliklerini toplu değiştirir [K35][K36].

Bu desenlerin TedarikApp'e taşınacak ortak ilkesi şudur: **tek bir eylem modeli; fare, klavye, sağ tık ve komut paletinden erişilebilir; kapsamı önceden görünür; sonucu geri alınabilir.**

Attio'nun özel/formül alanları [K30], Airtable'ın typed field ve görünümleri [K31], Notion'ın property/relations modeli [K35] kategori çeşitliliğine uyum sağlar. Ancak bu esneklik çekirdek durumları ve çıktı sözlüklerini gölgelememelidir; bu nedenle uyarlanabilir veri katmanı son fazdır.

### 3.5 Çin cross-border ERP ve Amazon araçlarından alınan sınır dersi

Çin procurement ürünlerinde talep–询比价–PO–到货–质检–入库–付款 zinciri yaygındır [K17][K18][K19][K20][K21][K22]. TedarikApp açısından kullanılabilir kısım; yapılandırılmış ihtiyaç, teklif karşılaştırma, sipariş değişikliği, kalite ve ilerleme görünürlüğüdür. 1688'e doğrudan sipariş, depo, stok, ödeme ve finans parçaları kalıcı RET'e çarpar.

AiPrice görselle arama, fiyat geçmişi ve görsel indirmeyi [K14]; SellerSprite ürün/anahtar kelime/talep analizini [K15] örnekler. Bunlar E/F/K/L alanlarının derinleştirmesidir; yeni P–Z fazı gerekçesi değildir. Amazon Restock [K16] ve Zentail stok/listing/order otomasyonu [K12][K13] doğrudan stok ve pazaryeri satış entegrasyonu RET'ine çarpar.

## 4. A–O'da olmayan yetenek envanteri ve yerleşim kararı

| # | Yetenek | Ne işe yarar | Tek kişilik değer | RET durumu | Yerleşim önerisi |
|---:|---|---|---|---|---|
| 1 | Komut paleti + bağlamsal eylem | Ekran değiştirmeden kayıt üzerinde işlem | **Yüksek:** yoğun günlük kullanımda tıklamayı azaltır | Hayır | **P yeni faz** |
| 2 | Çoklu seçim + toplu eylem | Aynı değişikliği kontrollü gruba uygular | **Yüksek:** tekrarlı satır işini azaltır | Hayır | **P** |
| 3 | Sağ tık menüsü | Seçili kayıt için uygun eylemi yakınlaştırır | **Orta:** hız sağlar, tek başına değer üretmez | Hayır | **P** |
| 4 | Kayıtlı görünüm/favori | Günlük kuyrukları tek tıkla geri getirir | **Yüksek:** tek operatörün çalışma hafızası olur | Hayır | **P** |
| 5 | Tip güvenli akıllı yapıştırma | Excel/WhatsApp verisini önizlemeyle alanlara dağıtır | **Yüksek:** saha köprüsündeki elle girişi azaltır | Hayır | **P**, C/K köprüsünü kullanır |
| 6 | Sürükle-bırak ve dosyayı kayda bırakma | Öncelik, sıra ve belge bağlamını hızlandırır | **Orta:** özellikle görsel ürünlerde faydalı | Hayır | **P** |
| 7 | Etki önizlemeli undo | Toplu işlemi güvenli yapar | **Yüksek:** veri kaybı korkusunu azaltır | Hayır | G altyapısı + **P yüzeyi** |
| 8 | Yapılandırılmış sourcing brief | Firma görüşmesinden önce ihtiyacı tamamlar | **Yüksek:** yanlış/karşılaştırılamaz teklif riskini düşürür | Hayır | **Q yeni faz** |
| 9 | Zorunlu/tercih/ret şartları | Alternatif ürünün nerede saptığını gösterir | **Yüksek:** ürün eşleşmesini fiyatın önüne koyar | Hayır | **Q** |
| 10 | Şartname sürümü ve alan farkı | Her tarafın aynı ürün tanımıyla çalışmasını sağlar | **Yüksek:** pahalı sipariş yanlışını önler | Hayır | **Q**, G audit altyapısı |
| 11 | Hazırlık/eksik veri kapısı | Eksik şartnameyi firmaya göndermeden gösterir | **Yüksek:** geri dönüş turunu azaltır | Hayır | **Q**, U kapıları |
| 12 | Ürün–ambalaj–etiket–numune ilişkisi | Dağınık bilgiyi tek teknik bağlama toplar | **Yüksek:** J/I/D parçalarını bağlar | Hayır | **Q/T/U**, Z veri ilişkileri |
| 13 | Teklif birim normalizasyonu | Paket/adet ve kademeleri eş düzleme getirir | **Yüksek:** yanlış ucuzluk algısını önler | Hayır | C derinleşmesi + **R** |
| 14 | Açıklanabilir fiyat dışı puanlama | Termin, şartname uyumu ve veri eksikliğini görünür kılar | **Orta/Yüksek:** çok teklif olduğunda güçlü | Sınır: due diligence puanı değil | **R** |
| 15 | Miktar/teklif bölme senaryosu | Sepeti farklı tekliflere dağıtıp sonucu gösterir | **Orta:** teklif ve ürün sayısı arttıkça değerli | Hayır | **R** |
| 16 | Senaryo kısıtları | Bütçe, MOQ, CBM ve hedef adedi birlikte denetler | **Yüksek:** ithalat kararının gerçek kısıtlarını birleştirir | Hayır | **R**, D hesaplarını kullanır |
| 17 | Karar gerekçesi ve varsayım | Sonradan “neden seçildi?” sorusunu cevaplar | **Yüksek:** tek kişinin zihinsel yükünü dışarı alır | Hayır | **S yeni faz** veya G genişlemesi |
| 18 | Karar anı snapshot'ı | Eski teklif/kur/şartnameye dayalı kararı yeniden kurar | **Yüksek:** uyuşmazlık ve tekrar siparişte kanıt sağlar | Hayır | **S** |
| 19 | Kanıt türü ve bayatlık | Görsel/belgenin neyi kanıtladığını ve güncelliğini gösterir | **Orta/Yüksek:** dosya çöplüğünü önler | Hayır | **S** |
| 20 | Ambalaj seviye modeli | Birim–iç kutu–ana koli ayrımını kurar | **Yüksek:** CBM ve hasar maliyetini etkiler | Hayır | **T yeni faz** |
| 21 | Ambalaj sürümü/proof | Baskı, koli ve etiket onayını izler | **Yüksek:** yanlış ambalaj üretimini önler | Hayır | **T**, J ile bağlı |
| 22 | Kısıtlı yük yerleşimi | Kolilerin konteynıra gerçekten sığıp sığmadığını gösterir | **Orta/Yüksek:** büyük sevkiyatta tasarruf/risk önleme | Hayır | **T** |
| 23 | Tedarikçi–numune–mal kabul ölçü farkı | Beyan ile gerçekleşeni karşılaştırır | **Yüksek:** sonraki landed cost'u düzeltir | Hayır | **T**, D mal kabul |
| 24 | Dijital kalite checklist'i | Kontrolün herkesçe aynı adımla yapılmasını sağlar | **Yüksek:** kaliteyi sonuçtan önce yönetir | Sınır: compliance değil | **U yeni faz** |
| 25 | Zengin kalite kanıtı | Foto/video/ölçümü kontrol maddesine bağlar | **Yüksek:** rücu ve yeniden kontrolü güçlendirir | Hayır | **U** |
| 26 | Kusur sözlüğü + CAPA | Kök neden, düzeltme ve kapanış kanıtını izler | **Yüksek:** tekrarlayan kusuru azaltır | Sınır: supplier due diligence değil | **U** |
| 27 | Golden sample farkı | Üretim örneğini onaylı referansla kıyaslar | **Yüksek:** numune onayının anlamını korur | Hayır | **U**, I ile bağlı |
| 28 | Satır düzeyi PO teyidi | Bir siparişte riskli satırı başlıktan ayırır | **Yüksek:** gizli kısmi gecikmeyi bulur | Hayır | C/D derinleşmesi + **V** |
| 29 | Resmî change order | Tarih/miktar/fiyat/şartname değişimini sürümler | **Yüksek:** WhatsApp sapmasını denetlenebilir yapar | Hayır | **V yeni faz** |
| 30 | Split shipment önerisi | Parti/taşıma ayrımını karşılıklı onaya alır | **Orta/Yüksek:** üretim gecikmesinde esneklik sağlar | Hayır | **V**, D sevkiyat |
| 31 | İstisna vaka nesnesi | Sapmayı önem, sahip, yaş ve çözümle yönetir | **Yüksek:** yalnız sorunlu işe odaklanmayı sağlar | Hayır | **W yeni faz** |
| 32 | Müdahale oyun kitabı | Benzer sapmada tutarlı seçenekler sunar | **Yüksek:** karar hızını ve kaliteyi artırır | Hayır | **W** |
| 33 | Tekrar sipariş known-good dosyası | Geçmiş doğru yapılandırmayı güvenli başlangıç yapar | **Yüksek:** Tilbe Home'un tekrar ürünlerinde doğrudan değer | Hayır | **X yeni faz** |
| 34 | Son alıma göre fark | DDP/MOQ/termin/ambalaj değişimini görünür kılar | **Yüksek:** kör kopyalamayı önler | Hayır | **X** |
| 35 | Olay–koşul–eylem otomasyonu | Tekrarlı uygulama içi kontrolleri yürütür | **Orta/Yüksek:** akışlar oturunca zaman kazandırır | Sınır: dış bildirim/API/sipariş yok | **Y yeni faz** |
| 36 | Dry-run/idempotency/kural günlüğü | Otomasyonu açıklanabilir ve güvenli yapar | **Yüksek:** sessiz veri bozulmasını önler | Hayır | **Y** |
| 37 | Özel typed alanlar | Yeni kategori ayrıntısını kod değişmeden tutar | **Orta:** kategori çeşitliliğinde değerli | Hayır | **Z yeni faz** |
| 38 | Deterministik formül alanları | Basit türetilmiş değerleri merkezileştirir | **Orta:** esnek, fakat yanlış formül riski var | Hayır | **Z** |
| 39 | Süreç KPI/bottleneck | Teklif, sipariş ve istisna çevrim sürelerini gösterir | **Orta:** iyileştirme sağlar | Hayır | Yeni faz değil; **F Raporlar** |
| 40 | Belge/tekliften taslak veri çıkarma | Kullanıcının yüklediği PDF/Excel/metni taslağa çevirir | **Yüksek:** elle giriş yükünü azaltır | Sınır: insan onayı ve resmî API yok | **P/C/K**; ayrı faz gerektirmez |

## 5. Yeni faz açmaması gereken bulgular

| Emsal yetenek | Neden yeni faz değil | Yerleşim |
|---|---|---|
| Görselle aynı ürünü arama, fiyat geçmişi, görsel indirme | E/K ve F/L'nin mevcut keşif/izleme kapsamı | E/F/K/L'ye küçük genişleme |
| Amazon anahtar kelime, yorum ve talep sinyali analizi | F zekâ ve L trend keşfinin çekirdeği | F/L |
| Temel RFQ, teklif turu, kör kıyas | C'de zaten var | R yalnız kısıtlı sepet senaryosu ekler |
| PO oluşturma, ödeme planı, CBM, landed cost, mal kabul | D'de zaten var | T/U/V/W yalnız D sonrası derinlik |
| Numune ve AQL planı | I'de zaten var | U kalite yürütmesini ekler |
| SKU/etiket üretimi | J'de zaten var | T/U yalnız sürüm ve kanıt bağlantısı ekler |
| Çok dilli çıktı | M'de zaten var | Q/Z yeni etiketleri M sözlüğüne bağlar |
| Rol/portal/haricî taraf | N/O ve G'de zaten var | Yeni çoklu onay veya tenant açılmaz |
| Genel dashboard/rapor | B/F'de zaten var | W istisna kuyruğu, F süreç KPI'sı olur |

## 6. RET çakışmaları — aday listesine alınmayanlar

Bu bölüm yalnız araştırmada görülen fakat bağlayıcı ürün kararlarıyla çakışan yetenekleri kaydeder.

| RET çakışması | Emsallerde görülen desen | Neden aday değil |
|---|---|---|
| **GTİP/gümrük compliance** | Jungle Scout HS/import kayıtları; QIMA compliance; ERP gümrük modülleri | Kalıcı RET; TedarikApp kalite kontrolü mevzuat uygunluk motoruna dönüşmez |
| **Muhasebe/cari/fatura** | Procurify three-way match; Çin ERP'lerde 对账/开票/付款 | Kalıcı RET; D yalnız operasyonel ödeme kaydı/snapshot tutar |
| **Stok/depo/replenishment** | Amazon Restock, Zentail inventory, 领星/店小秘/马帮 WMS | Kalıcı RET; X tekrar sipariş hafızası stok önerisi üretmez |
| **Pazaryeri satış API'leri** | Zentail ve cross-border ERP listing/order sync | Kalıcı RET; pazar siteleri yalnız keşif/talep sinyalidir |
| **Supplier due diligence/risk** | Jungle Scout import records, QIMA supplier score, SRM onboarding/risk | Kalıcı RET; U yalnız sipariş/ürün kalite kanıtını tutar |
| **Çok kiracılık/SaaS satışı** | Kurumsal procurement tenant/organizasyon yapıları | Tek kullanıcı ve belirli dış portallar yeterlidir |
| **E-posta/push bildirim** | Anvyl, Coupa, SourceDay e-posta teyitleri | Kalıcı RET; W/Y yalnız uygulama içi Gelen Kutusu/Panorama kullanır |
| **Resmî platform API'si** | ERP–1688 doğrudan sipariş/ödeme; marketplace entegrasyonları | Kalıcı RET ve “sitelerden alım yok” ilkesi |
| **Alibaba escrow/Trade Assurance ödeme akışı** | Platform içi ödeme ve koruma | TedarikApp gerçek işlemi ithalatçı firma üzerinden yürütür; yalnız şart/kanıt deseni alınır |
| **Kurumsal çok kademeli onay** | Coupa/SAP/Procurify approval routing | G/N rol sınırını büyütür; tek iç operatöre değer düşük |

Not: Gerçekleşmiş siparişin zamanında teslim, teklif doğruluğu veya kusur tekrarını **sipariş bağlamında raporlamak**, kimlik/risk taraması yapmadığı sürece supplier due diligence değildir. Yine de bu metrikler yeni supplier score fazına dönüştürülmemeli; gerekiyorsa F raporlarına sınırlı operasyon metriği olarak eklenmelidir.

## 7. P–Z aday fazlarının önerilen ana sırası

| Harf | Faz | Boy | Önce olamaz | Tek kişilik değer | Bu sıranın gerekçesi |
|---|---|:---:|---|---|---|
| P | Operatör Hızı ve Sayfa Olgunluğu | L | B, G | **Yüksek** | Sonraki bütün ekranların ortak etkileşim ve güvenlik tabanıdır |
| Q | İhtiyaç ve Teknik Şartname Stüdyosu | M | A, C, I, J, M | **Yüksek** | Karar, kalite ve değişiklik akışlarının karşılaştıracağı başlangıç doğrusunu kurar |
| R | Tedarik Senaryosu ve Karar Laboratuvarı | M | C, D, Q | **Orta/Yüksek** | Teklif ve maliyet verisi hazır olmadan senaryo anlamlı değildir |
| S | Karar ve Kanıt Defteri | M | G, Q, R | **Yüksek** | Karar üreten Q/R'nin gerekçe ve snapshot'ını kalıcılaştırır |
| T | Ambalaj ve Yük Mühendisliği | L | D, J, Q | **Orta/Yüksek** | Şartnameyi fiziksel koli/yük planına çevirir ve U'nun kontrol maddelerini besler |
| U | Kalite Güvence ve Düzeltici Faaliyet | L | D, I, J, Q | **Yüksek** | Numune/etiket/şartname hazır olduğunda üretim öncesi kalite zinciri kurulabilir |
| V | Sipariş Değişiklik Kontrolü | M | C, D, Q | **Yüksek** | Sağlam bir ilk şartname ve sipariş modeli olmadan “değişiklik” tanımlanamaz |
| W | İstisna ve Müdahale Merkezi | L | B, D, U, V | **Yüksek** | U/V'nin ürettiği standart olaylar olmadan istisna merkezi soyut kalır |
| X | Tekrar Sipariş ve Ürün Hafızası | M | D, S, U, V | **Yüksek** | Tamamlanmış, kanıtlı ve revizyonlu geçmiş olmadan known-good dosya üretilemez |
| Y | Kural ve Otomasyon Atölyesi | L | G, P, W | **Orta/Yüksek** | Önce akış ve istisnalar elle doğru çalışmalı, sonra otomatikleşmelidir |
| Z | Uyarlanabilir Veri Katmanı | L | A, G, M, P, Y | **Orta** | Alan modeli ve gerçek kullanım oturduktan sonra güvenli esneklik eklenmelidir |

### 7.1 P — Operatör Hızı ve Sayfa Olgunluğu

**Amaç:** Her yoğun veri ekranını Linear/Attio sınıfı, hızlı ve hataya dayanıklı çalışma yüzeyine dönüştürmek.

**Çekirdek kapsam:** satır içi düzenleme; çoklu seçim; toplu eylem; sağ tık; komut paleti; klavye gezintisi; kayıtlı görünüm/favori; akıllı yapıştırma; sürükle-bırak; etki önizlemesi; kısmi hata raporu; undo. Emsal kanıtları [K23]–[K36].

**Neden P:** Bu özellikler tek bir sayfaya sonradan serpiştirilirse her ekran farklı davranır. Ortak selection/action/undo/view altyapısı ilk kurulmalıdır.

**Özel kabul sınırı:** Bir eylem fare, sağ tık ve komut paletinden çağrıldığında aynı yetki, doğrulama ve audit yolunu kullanmalı; üç ayrı iş mantığı olmamalıdır.

### 7.2 Q — İhtiyaç ve Teknik Şartname Stüdyosu

**Amaç:** Ürün fikrini ölçülebilir, sürümlü ve firmalar arasında karşılaştırılabilir satın alma şartnamesine dönüştürmek.

**Çekirdek kapsam:** kategori şablonu; zorunlu/tercih/ret maddeleri; ölçü-malzeme-tolerans-set içeriği; MOQ/termin/DDP hedefi; referanslar; ambalaj/etiket/numune ilişkileri; hazırlık kapısı; sürüm/fark; firma yanıt uygunluk matrisi. Emsaller [K10][K11][K42][K46][K47][K52].

**Neden Q:** R'deki teklif senaryosu, U'daki kalite kontrolü ve V'deki değişiklik farkı ortak bir başlangıç şartnamesi olmadan güvenilir değildir.

**Özel kabul sınırı:** Hedef DDP, gerçek fiyat değildir; kullanıcı hedefi olarak işaretlenir. Gerçek fiyat yalnız firma teklifinden gelir.

### 7.3 R — Tedarik Senaryosu ve Karar Laboratuvarı

**Amaç:** Onaylı firma DDP tekliflerini sepet kısıtları altında karşılaştırmak; kullanıcı adına sipariş veya award vermemek.

**Çekirdek kapsam:** birim/kademe normalizasyonu; teklif geçerliliği; açıklanabilir ağırlık; miktar bölme; bütçe/MOQ/CBM/hedef adet kısıtları; hazır fakat düzenlenebilir senaryolar; senaryo farkı; bayatlık; karar taslağı. Emsaller [K09][K38][K39][K40].

**Neden R:** C teklif verisini, D maliyet/CBM verisini, Q şartname uygunluğunu sağlar. Bunlar olmadan “optimizasyon” yalnız fiyat sıralamasıdır.

**Özel kabul sınırı:** Eksik değer ne sıfır, ne ortalama, ne de olumlu kabul edilir; senaryoda “hesaplanamadı/eksik” olarak kalır.

### 7.4 S — Karar ve Kanıt Defteri

**Amaç:** Seçim ve değişiklik kararını veri, varsayım, sürüm ve kanıtıyla birlikte yeniden kurulabilir yapmak.

**Çekirdek kapsam:** kısa gerekçe; karar snapshot'ı; varsayım/güven; sonradan değişen alan farkı; kanıt türü; karar zinciri; bayatlık; iş zaman çizelgesi; rol/çıktı filtresi. Emsaller [K01][K02][K33].

**Neden S:** Q/R karar yüzeylerinden sonra gelirse karar anı otomatik snapshot alınabilir. Daha erken yapılırsa genel bir not/audit ekranına dönüşür.

**Paketleme alternatifi:** PM bunu ayrı faz için küçük bulursa G'nin “iş kararı izi” alt fazı olabilir. Ancak teknik audit ile iş gerekçesi ayrı veri türü kalmalıdır.

### 7.5 T — Ambalaj ve Yük Mühendisliği

**Amaç:** Koli ve konteyner kararlarını basit CBM toplamından uygulanabilir fiziksel plana çıkarmak.

**Çekirdek kapsam:** ambalaj seviyeleri; koli içi adet; kırılganlık/yön/istif; proof ve sürüm; beyan-numune-kabul ölçüsü; CBM/landed cost etki simülasyonu; konteyner yerleşimi; elle sabitleme; doluluk/ağırlık raporu; paylaşılabilir plan. Emsaller [K46][K47][K48].

**Neden T:** D maliyet tabanı ve Q/J şartnamesi hazırdır. U, ambalaj kontrol maddelerini T'den alabilir.

**Kademeli teslim:** T1 ölçü/sürüm/etki, T2 2B katman planı, T3 gerekirse 3B ve optimizasyon. İlk sürümün 3B gösterişli fakat doğrulanamaz olmaması önemlidir.

### 7.6 U — Kalite Güvence ve Düzeltici Faaliyet

**Amaç:** Onaylı numuneyi üretim ve sevkiyat öncesi kontrollerde yaşayan kalite referansına dönüştürmek.

**Çekirdek kapsam:** checklist şablonu; kusur sınıfı ve görsel örnek; golden sample karşılaştırması; foto/video/ölçüm; AQL bağlantısı; karar; kök neden/CAPA; yeniden kontrol; ders aktarımı. Emsaller [K49][K50][K51][K53].

**Neden U:** I/AQL ve J/etiket tek başına kalite yürütme sistemi değildir. Q/T'den ölçülebilir kriterler geldikten sonra U uygulanabilir olur.

**Özel kabul sınırı:** Test/denetim kanıtının kaynağı açıkça “kullanıcı”, “firma”, “üretici” veya “bağımsız kontrol” olarak etiketlenir; güven seviyeleri eşitlenmez.

### 7.7 V — Sipariş Değişiklik Kontrolü

**Amaç:** İlk sipariş ile güncel mutabakat arasındaki her önemli farkı resmî, satır düzeyli revizyona dönüştürmek.

**Çekirdek kapsam:** satır teyidi; değişiklik talebi; eski-yeni fark; kabul/ret/karşı öneri; iç/firma teyidi ayrımı; split shipment; etki analizi; değişmez revizyon; çakışma kontrolü; insan onaylı WhatsApp/Excel taslağı. Emsaller [K04][K05][K41][K43][K44][K45].

**Neden V:** D sipariş durumları ve Q dondurulmuş şartname, değişikliğin başlangıç doğrusudur. V bu iki yapının üstüne oturur.

**Özel kabul sınırı:** V yeni `status.*` ana durumları üretmez; değişiklik talebi ve sipariş revizyonu ayrı alt nesnelerdir.

### 7.8 W — İstisna ve Müdahale Merkezi

**Amaç:** Normal akıştan sapan işi bulmak, önemlendirmek ve çözüm kanıtıyla kapatmak.

**Çekirdek kapsam:** kural tabanlı sapma; önem/yaş/etki; tek vaka kuyruğu; sahip/sonraki eylem; müdahale oyun kitabı; vaka birleştirme; etki analizi; kapanış/yeniden açma; öncelik yükseltme; açıklanabilir tetik. Emsaller [K01][K43][K44].

**Neden W:** U kalite olaylarını, V sipariş değişikliklerini standartlaştırdıktan sonra W gerçek vaka tiplerini yönetir. Önce yapılırsa ikinci bir genel bildirim kutusu olur.

**Özel kabul sınırı:** B'deki Panorama ve Gelen Kutusu kullanıcı yüzeyidir; W ayrı bir bildirim kanalı değil, onların arkasındaki vaka modelidir.

### 7.9 X — Tekrar Sipariş ve Ürün Hafızası

**Amaç:** Önceki doğrulanmış ürünü, değişiklikleri yeniden teyit ederek yeni siparişe hazırlamak.

**Çekirdek kapsam:** known-good dosya; şartname/ambalaj/etiket/golden sample bağlantısı; geçmiş gerçekleşen maliyet ve termin; aynen kullan/yeniden doğrula/değişti seçenekleri; son alıma göre fark; kusur/istisna dersi; kayıp sayfadan yeniden keşif; kontrol listesi; sürümlü referans. Emsaller [K02][K34][K44].

**Neden X:** Yalnız tamamlanmış ve kanıtlı geçmiş anlamlı ürün hafızasıdır. D/S/U/V tamamlanmadan “tekrar sipariş” kör kopyadır.

**Özel kabul sınırı:** Sistem satış, stok veya replenishment miktarı hesaplamaz; kullanıcı miktarı girer, sistem geçmiş ve farkı gösterir.

### 7.10 Y — Kural ve Otomasyon Atölyesi

**Amaç:** Sık tekrarlanan iç kontrolleri otomatikleştirmek fakat satın alma ve kritik durum kararını insanda tutmak.

**Çekirdek kapsam:** olay-koşul-eylem; iç tetikleyiciler; görev/taslak/kuyruk eylemleri; dry-run; insan onayı kapısı; idempotency; sürüm ve çalışma günlüğü; duraklat/geri al/yeniden çalıştır; oyun kitabı şablonları. Emsaller [K34][K43][K44].

**Neden Y:** Otomasyon, yanlış veya değişken süreci yalnız daha hızlı bozar. P ve W ile eylem modeli ve istisna semantiği oturduktan sonra gelir.

**Özel kabul sınırı:** İlk sürümde whitelist eylem seti; serbest kod, webhook, e-posta, push, resmî platform API çağrısı veya otomatik sipariş yok.

### 7.11 Z — Uyarlanabilir Veri Katmanı

**Amaç:** Ev/mutfak/banyo kategorilerinin değişen ayrıntılarını çekirdek şemayı çatallamadan yönetmek.

**Çekirdek kapsam:** özel typed alan; sınırlı formül; kategori alan seti; protected core; çok dilli etiket; arşiv/yeniden adlandırma/tip değişikliği etki analizi; paste/CSV eşleme; filtre/rapor/çıktı katılımı; şema sağlığı. Emsaller [K30][K31][K35].

**Neden Z:** Alan ve ekran ihtiyaçları P–Y gerçek kullanımında görülür. Önce yapılırsa ürün tasarımı yerine “her şeyi kullanıcı tanımlar” kaçışına dönüşür.

**Özel kabul sınırı:** `status.*`, kimlikler, fiyat gerçeklik kaynağı, kur snapshot'ı ve kritik ilişki alanları özel alanla değiştirilemez/gölgelenemez.

## 8. Alternatif sıra

### Ana öneri

`P → Q → R → S → T → U → V → W → X → Y → Z`

Bu sıra önce operatör ve karar kalitesini, sonra fiziksel/kalite derinliğini, ardından değişiklik/istisna/tekrar ve en sonda otomasyon-esnekliği kurar.

### Alternatif: operasyonel risk önce

`P → Q → V → W → U → T → X → R → S → Y → Z`

**Ne zaman seçilir:** Siparişler hâlihazırda yürüyor ve en büyük günlük kayıp geç teyit, WhatsApp değişikliği, gecikme veya kalite sapmasından geliyorsa.

**Gerekçe:**

- V/W erken gelerek mevcut siparişlerde kontrol sağlar.
- U/T fiziksel kalite ve yük riskini kapatır.
- X, önceki sipariş derslerini hızlıca tekrar siparişe taşır.
- R/S karar optimizasyonu sonraya kalır; bu nedenle erken dönemde teklif karşılaştırması C/D'nin mevcut düzeyiyle yürür.

**Bedel:** R/S geciktiği için yeni ürün seçimi ve teklif sepeti kararlarında daha az açıklanabilir destek olur. Ayrıca W, U tamamlanmadan kalite istisnalarının yalnız sınırlı bir alt kümesini yönetir.

## 9. Faz birleştirme seçenekleri

PM kaynak veya takvim nedeniyle 11 ayrı fazı fazla bulursa aşağıdaki birleşimler sınırı bozmadan yapılabilir:

- **S → G genişlemesi:** Teknik audit/undo'dan ayrı “iş kararı izi” alt paketi olarak.
- **T + U:** “Fiziksel Ürün Hazırlığı” adıyla tek L/XL faz; ambalaj ve kalite ekipleri aynı değilse yönetimi zorlaşır, fakat tek operatörde kabul edilebilir.
- **V + W:** Önce change order, sonra exception vakası olmak üzere aynı L fazın iki dikeyi; veri modeli yine ayrı tutulmalıdır.
- **Y + Z birleştirilmemeli:** Hem otomasyon hem şema esnekliği aynı fazda açılırsa hata alanı katlanır.

Bu araştırma “V3 P–Z'de bitmeli” demeyi gerektiren bir kıtlık bulmadı; 11 adayın her biri özgün bir iş problemi çözüyor. Bununla birlikte **Z'nin V4'e ertelenmesi**, özel alan ihtiyacı P–Y kullanımıyla doğrulanmazsa mantıklıdır. Z sırf alfabe tamamlamak için yapılmamalıdır.

## 10. Çapraz kesen mimari ve kabul notları

1. **Tek eylem altyapısı:** P, Y ve portallar aynı command/action sözleşmesini kullanmalı.
2. **İş olayı ≠ ana durum:** V değişiklikleri ve W istisnaları 5B `status.*` listesini çoğaltmamalı.
3. **Snapshot standardı:** Q şartname, C teklif, D sipariş, S karar ve X repeat-order aynı snapshot kimlik/sürüm sözleşmesini kullanmalı.
4. **Kanıt kökeni:** Her görsel/belgede kimden geldiği, neyi kanıtladığı ve hangi sürüme bağlı olduğu bulunmalı.
5. **Eksik veri semantiği:** R/T/Y hiçbir zaman eksik değeri sıfıra veya varsayılan başarıya çevirmemeli.
6. **İnsan onayı:** Akıllı yapıştırma, belge çıkarma, otomasyon ve senaryo sonucu taslak/öneridir.
7. **PWA/offline sınırı:** Büyük medya/3B yük planı dışında Q/U/V saha girişleri bağlantı kesintisine dayanıklı tasarlanabilir; çakışma çözümü açık olmalı.
8. **Dil:** Yeni sistem etiketleri M'nin TR/EN/ZH tek kaynak yaklaşımına eklenmeden UI/çıktıya çıkmamalı.
9. **Gözlenebilirlik:** Toplu işlem, kural ve veri göçü için ölçülebilir başarı/kısmi hata/atlandı sonuçları gerekir.
10. **Kademeli açılış:** P etkileşimleri ve Y kuralları feature flag ve gerçek veri kopyası olmayan test fikstürüyle kabul edilmelidir.

## 11. Kaynak kataloğu

Tüm kaynaklara 2026-08-29 tarihinde erişildi. Sayfa üzerindeki pazarlama/performans iddiaları değil, belgelenen işlev deseni esas alındı.

| Kod | Kaynak | Kullanılan kanıt |
|---|---|---|
| [K01] | Alibaba — Trade Assurance | Uzlaşılan sipariş, escrow, şart ihlali ve çözüm akışı |
| [K02] | Anvyl — Issuing a purchase order | PO, faaliyet, belge, mesaj, kilometre taşı ve split shipment |
| [K03] | Anvyl — How order milestones work | Tarih mantığı, otomatik kontrol ve değişiklik nedeni |
| [K04] | Anvyl — Suggest a split shipment | Parti ve taşıma biçimine göre bölme önerisi |
| [K05] | Anvyl — Purchase order collaboration | Mesaj, dosya, görev ve split shipment işbirliği |
| [K06] | Anvyl/Sage Supply Chain Intelligence | PO'dan teslimata görünürlük ve audit izi |
| [K07] | Jungle Scout — Supplier Database | Gümrük kayıtlı supplier discovery; RET sınırı |
| [K08] | Jungle Scout — Supplier Tracker | PO ve supplier tracker işlevleri |
| [K09] | Jungle Scout — Creating Quotes | Aynı/farklı kaynak tekliflerini saklama ve karşılaştırma |
| [K10] | Sourcify — Product Manufacturing | Şartname, numune, QC ve DDP teslim zinciri |
| [K11] | Sourcify — How it works | Gereksinimden teslimata üretim akışı |
| [K12] | Zentail — Platform | Listing, inventory, order ve pricing otomasyonu; RET sınırı |
| [K13] | Zentail — Inventory Management | Çoklu depo/stok senkronu; RET sınırı |
| [K14] | AiPrice (AliPrice) | Görsel arama, fiyat geçmişi ve görsel indirme |
| [K15] | SellerSprite — Product sourcing for Amazon FBA | Talep, niş, yorum ve ürün araştırması |
| [K16] | Amazon Seller Central — Restock Inventory | Stok/replenishment önerisi; RET sınırı |
| [K17] | 甄云科技 — 采购工具 | Fiyat, karar ve uçtan uca procurement yönetimi |
| [K18] | 领星 ERP — 供应链管理 | Plan–PO–到货–质检–入库–付款 zinciri; RET ayrımı |
| [K19] | 店小秘 — Help Center | 采购建议, 1688采购, değişiklik ve takip; RET ayrımı |
| [K20] | 马帮 ERP — Product | Seçim, procurement, warehouse, finance; RET ayrımı |
| [K21] | 鲸采云 SRM | 需求, 询比价, order, quality, finance collaboration |
| [K22] | 商越科技 | SRM/e-procurement ve çevrimiçi procurement akışı |
| [K23] | Linear — Select issues | Çoklu seçim, komut paleti ve sağ tık |
| [K24] | Linear — Custom views | Filtreli görünümü kaydetme |
| [K25] | Linear — Filters | Klavye ile filtre ve dinamik sonuç |
| [K26] | Linear — Project milestones | Sürükle-bırak sıralama ve kilometre taşı |
| [K27] | Attio — Table views | Doğrudan düzenleme, filtre, sütun ve toplu update |
| [K28] | Attio — Navigating workspace | Bağlama duyarlı quick actions ve kısayollar |
| [K29] | Attio — Bulk update lists and records | Hücre aralığı ve elektronik tabloyla copy/paste |
| [K30] | Attio — Formula attributes | Özel/türetilmiş alan modeli |
| [K31] | Airtable — Views | Grid, form, calendar, gallery, kanban, timeline, list, gantt |
| [K32] | Airtable — Keyboard shortcuts | Range seçimi, copy/paste ve kayıt açma |
| [K33] | Airtable — Record revision history | Kayıt düzeyi değişiklik geçmişi |
| [K34] | Airtable — Buttons/record templates | Mevcut kayda kontrollü şablon uygulama |
| [K35] | Notion — Database views | Aynı verinin filtre/sort/group ile farklı görünümleri |
| [K36] | Notion — Table view | Çoklu satır seçme ve toplu özellik düzenleme |
| [K37] | Notion — Keyboard shortcuts | Klavye navigasyonu |
| [K38] | SAP Ariba — Manual award scenarios | Miktarı bir veya çok kaynağa paylaştırma |
| [K39] | SAP Ariba — Grading and scoring | Fiyat dışı objektif karşılaştırma modeli |
| [K40] | SAP Ariba — Bid analysis | Teklif ve split award senaryosu karşılaştırma |
| [K41] | Coupa — PO collaboration | Başlık/satır teyidi, kabul-ret ve mesaj |
| [K42] | ServiceNow — Sourcing intake playbook | Gereksinim toplama, açıklama ve sourcing kararı |
| [K43] | ServiceNow — PO exceptions | İstisna, görev, öncelik ve müdahale |
| [K44] | SourceDay — PO collaboration | Teyit, görünür değişiklik/istisna ve sahiplik |
| [K45] | SourceDay — PO change orders | Tarih/miktar/fiyat değişikliğini sürümleme |
| [K46] | Specright — Specification management | Ürün ve ambalaj şartname yönetimi |
| [K47] | SAP/Specright — Specification Data Management | Sürümlü şartname, ilişki ve audit izi |
| [K48] | EasyCargo — Container loading | Ölçü/ağırlık/kısıt, 3B plan, manuel düzeltme ve paylaşım |
| [K49] | QIMAone — Platform | Standardize kalite akışı, kusur ve görünürlük |
| [K50] | QIMAone — Features | Kalite analitiği, işbirliği ve gerçek zamanlı kontrol |
| [K51] | QIMAone — Inspector App | Foto/video/belgeyle yönlendirilmiş kontrol |
| [K52] | Lifecycle PLM — Tech Pack Studio | Canlı şartname, revizyon, ölçüm, BOM ve onay |
| [K53] | Inspectorio — Production line quality checks | Üretimin farklı noktalarında dijital kalite kontrolü |
| [K54] | Procurify — Purchase to receive | Receipt/packing slip/three-way match; muhasebe RET sınırı |

## 12. Araştırma sonucu PM notu

- **P ürün borcu değil ürün kabiliyetidir:** tek seferlik “UI polish” olarak küçültülmemeli; seçim, eylem, görünüm, paste ve undo sözleşmeleri olan bir platform fazıdır.
- **Q olmadan R/U/V zayıftır:** ölçülebilir başlangıç doğrusu kurulmazsa karşılaştırma, kalite ve değişiklik yalnız serbest nota dayanır.
- **W ikinci bildirim merkezi olmamalıdır:** B'nin mevcut yüzeylerini besleyen vaka/mitigasyon modeli olmalıdır.
- **X stok fazı değildir:** yeniden sipariş miktarını sistem hesaplamaz; yalnız doğrulanmış geçmiş ve fark sunar.
- **Y/Z acele edilmemelidir:** önce elle doğru süreç, sonra otomasyon; önce sabit çekirdek, sonra esnek şema.
- **RET sınırları şemaya yazılmalıdır:** yalnız dokümanda kalan sınırlar zamanla özelliğe sızar. Y action whitelist'i, Z protected-core listesi ve U compliance-dışı kalite türleri teknik kabul kriteri olmalıdır.

[K01]: https://tradeassurance.alibaba.com/
[K02]: https://support.anvyl.com/hc/en-us/articles/25186866570381-Guide-4-Issuing-a-purchase-order
[K03]: https://support.anvyl.com/hc/en-us/articles/14713222189965-How-order-milestones-work
[K04]: https://support.anvyl.com/hc/en-us/articles/360051792872-Suggest-a-split-shipment
[K05]: https://support.anvyl.com/hc/en-us/sections/15006471678733-Purchase-order-collaboration
[K06]: https://anvyl.com/
[K07]: https://support.junglescout.com/hc/en-us/articles/360019317034-Supplier-Database-Feature-Overview
[K08]: https://support.junglescout.com/hc/en-us/articles/360020394834-Supplier-Tracker-Feature-Overview
[K09]: https://support.junglescout.com/hc/en-us/articles/360026812453-Supplier-Tracker-Creating-Quotes
[K10]: https://sourcify.com/solutions/product-manufacturing/
[K11]: https://sourcify.com/how-it-works/
[K12]: https://www.zentail.com/
[K13]: https://www.zentail.com/solutions/inventory-management
[K14]: https://aliprice.com/
[K15]: https://www.sellersprite.com/en/blog/product-sourcing-for-amazon-fba
[K16]: https://sellercentral.amazon.com/help/hub/reference/external/G201634550
[K17]: https://www.going-link.com/product/zpsl
[K18]: https://www.lingxing.com/help/article/gongyinglianguanli
[K19]: https://help.dianxiaomi.com/
[K20]: https://www.mabangerp.com/main_productErp.htm
[K21]: https://jingcaiyun.net/
[K22]: https://www.sunyur.com/
[K23]: https://linear.app/docs/select-issues
[K24]: https://linear.app/docs/custom-views
[K25]: https://linear.app/docs/filters
[K26]: https://linear.app/docs/project-milestones
[K27]: https://attio.com/help/reference/managing-your-data/views/create-and-manage-table-views
[K28]: https://attio.com/help/reference/productivity-collaborating/navigating-your-workspace
[K29]: https://attio.com/help/reference/managing-your-data/lists/bulk-updating-lists
[K30]: https://attio.com/help/reference/managing-your-data/attributes/formula-attributes
[K31]: https://support.airtable.com/articles/5189551686-getting-started-with-airtable-views
[K32]: https://support.airtable.com/articles/7980233311-airtable-keyboard-shortcuts
[K33]: https://support.airtable.com/articles/3516802427-record-level-revision-history-in-airtable
[K34]: https://support.airtable.com/articles/2099494420-using-buttons-in-interfaces
[K35]: https://www.notion.com/help/category/database-views
[K36]: https://www.notion.com/help/tables
[K37]: https://www.notion.com/help/keyboard-shortcuts
[K38]: https://help.sap.com/docs/strategic-sourcing/managing-events-with-guided-sourcing/using-manual-award-scenarios-to-award-guided-sourcing-events-using-manual-scenarios
[K39]: https://help.sap.com/docs/strategic-sourcing/managing-events-with-guided-sourcing/grading-and-scoring-in-guided-sourcing-events-7cb7a4675a5a4afca47477f513ceea4f
[K40]: https://help.sap.com/docs/strategic-sourcing/managing-events-with-guided-sourcing/using-bid-analysis-to-award-guided-sourcing-events
[K41]: https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/purchase-orders/purchase-order-collaboration-with-buyers
[K42]: https://www.servicenow.com/docs/r/source-to-pay-operations/sourcing-and-procurement-operations/sourcing-intake-guided-exp.html
[K43]: https://www.servicenow.com/docs/r/source-to-pay-operations/resolving-purchase-order-exceptions.html
[K44]: https://sourceday.com/purchase-order-collaboration/
[K45]: https://sourceday.com/blog/po-change-order/
[K46]: https://www.specright.com/
[K47]: https://www.sap.com/products/scm/partners/specright-inc-specright-specification-data-management-and-plm.html
[K48]: https://www.easycargo3d.com/en/try-online-for-free/
[K49]: https://www.qimaone.com/
[K50]: https://www.qima.com/qimaone/features
[K51]: https://www.qima.com/qimaone/inspector-app
[K52]: https://www.lifecycleplm.com/platform/techpack-studio
[K53]: https://www.inspectorio.com/press-release/inspectorio-expands-to-support-production-line-quality-checks-and-fabric-inspections/
[K54]: https://www.procurify.com/platform/purchase-to-receive/
