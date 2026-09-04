# TedarikApp V3 P–Z Faz Haritası Araştırması ve Aday Fazlar

**Görev:** #31  
**Rapor tarihi:** 2026-08-29  
**Kaynak erişim tarihi:** 2026-08-29 (UTC)  
**Karar statüsü:** **Nihai — PM + Ürün Sahibi kararları işlendi.**  
**Revizyon:** 31-R  
**Kapsam:** V3 A–O sonrasında, tek kişilik ithalatçı-satıcı için P–Z yetenek haritası.

## REVİZYON 31-R

### Nihai karar tablosu

| Harf | Nihai ad | Tür | Sıra / bağ / tetik | Boy |
|---|---|---|---|:---:|
| P | Operatör Hızı & Sayfa Olgunluğu | Tam faz | 1 | L |
| Q | Şartname Stüdyosu | Bağlı blok | V3-C/N RFQ bloğu: beklenti/ölçü/malzeme şartı | M |
| R | Senaryo & Karar Laboratuvarı | Tam faz | 4 | M |
| S | Ekip & Karar Defteri | Tetikli | İkinci hesap fiilen açılır | M |
| T | Ambalaj & Yük Mühendisliği | Tam faz | 2; T1→T3 kademeli | L |
| U | Kalite Güvence & CAPA | Bağlı blok | V3-D mal kabul bloğu | L |
| V | Sipariş Değişiklik Kontrolü + İstisna & Müdahale Merkezi | Tam faz | 5 | L |
| W | Mobil Saha Akışları | Tetikli | Mal kabulde telefon ihtiyacı fiilen doğar | M |
| X | Tekrar Sipariş & Ürün Hafızası | Tam faz | 3 | M |
| Y | Kural/Otomasyon Atölyesi + panel içi AI asistanı | Tetikli | Aynı elle işlemin en az üç ay düzenli tekrarı kanıtlanır | L |
| Z | Özel Alanlar & Uyarlanabilir Veri Katmanı + V3 Kapanış | Tam faz | 6 | L |

### Nihai yürütme sırası

`P → T → X → R → V → Z`

Q ve U kendi geliştirme emrini almaz; bağlı oldukları bloklarda yürür. S, W ve Y sıra dışında, yalnız tanımlı tetik gerçekleştiğinde emir alır.

### Eski → yeni eşlemesi

| #31 araştırma kaydı | 31-R nihai karşılığı | Değişiklik |
|---|---|---|
| P — Operatör Hızı ve Sayfa Olgunluğu | P tam faz | Kapsam ve boy korundu; sıra 1 |
| Q — İhtiyaç ve Teknik Şartname Stüdyosu | Q bağlı blok | V3-C/N RFQ içine bağlandı; kendi emri kaldırıldı |
| R — Tedarik Senaryosu ve Karar Laboratuvarı | R tam faz | Elle çoklu ithalatçı bölmesi kesinleşti; otomatik optimum dışarıda |
| S — Karar ve Kanıt Defteri | S tetikli faz | İkinci hesap yaşam döngüsü, görev atama ve tek kaynak izin sınırı eklendi |
| T — Ambalaj ve Yük Mühendisliği | T tam faz | T1→T3 kademesi ile iki saha problemi birlikte bağlandı |
| U — Kalite Güvence ve Düzeltici Faaliyet | U bağlı blok | V3-D mal kabule bağlandı; numune süreci çıkarıldı; “Geçti” kararı yalnız Ürün Sahibi |
| V — Sipariş Değişiklik Kontrolü | V tam faz | Eski W'nin istisna/müdahale kapsamıyla birleşti |
| W — İstisna ve Müdahale Merkezi | V tam faz | Ayrı kayıt olmaktan çıktı; kapsam V'ye taşındı |
| Mobil saha girişi — önce ayrı faz değildi | W tetikli faz | Mal kabulde telefonla fotoğraf ve sayım kapsamıyla faz oldu |
| X — Tekrar Sipariş ve Ürün Hafızası | X tam faz | Kapsam ve boy korundu; sıra 3 |
| Y — Kural ve Otomasyon Atölyesi | Y tetikli faz | Whitelist eylemler ve panel içi AI asistanı sınırı kesinleşti |
| Z — Uyarlanabilir Veri Katmanı | Z tam faz | Özel alan sınırı kesinleşti; V3 Kapanış son bloğu eklendi |

### Zorunlu yeniden değerlendirme kapısı

- V3-F kapanışında P–Z haritası gerçek kullanım verisiyle zorunlu olarak yeniden gözden geçirilir.
- Her tam faz emri derlenmeden önce **“Bu faz hangi ölçülebilir kaybı azaltır?”** sorusuna ölçü, başlangıç değeri ve hedef değerle cevap verilir.
- Cevap üretilemeyen tam faz yürütmeye alınmaz; harf ve karar kaydı korunur, PM değerlendirmesine döner.

## 1. Yönetici özeti

**31-R ile değişti:** Araştırmadaki 11 bağımsız aday faz varsayımı kaldırıldı; 11 harf korunarak altı tam faz, iki bağlı blok ve üç tetikli faz olarak sınıflandırıldı. Nihai yürütme sırası `P → T → X → R → V → Z` oldu. Q, V3-C/N RFQ bloğuna; U, V3-D mal kabul bloğuna bağlandı. S, W ve Y yalnız bağlayıcı tetikleri gerçekleştiğinde emir alır.

Tam fazların amacı sırasıyla operatör kaybını azaltmak, fiziksel ambalaj/yük sapmalarını yönetmek, doğrulanmış ürün hafızasını tekrar siparişe taşımak, çoklu ithalatçı bölmelerinin etkisini göstermek, sipariş sapmalarını tek değişiklik/istisna zincirinde çözmek ve çekirdek hesabı bozmayan özel alan katmanıyla V3'ü kapatmaktır.

**31-R ile değişti:** Önceki değer kümesi ve olası birleştirme önerileri yürütme kararı değildir. P, T, X, R, V ve Z sıralı emir alır; Q/U bağlı blok, S/W/Y tetikli kayıttır.

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

Attio'nun özel/formül alanları [K30], Airtable'ın typed field ve görünümleri [K31], Notion'ın property/relations modeli [K35] kategori çeşitliliğine uyum sağlar. Ancak bu esneklik çekirdek durumları ve çıktı sözlüklerini gölgelememelidir. **31-R ile değişti:** Z yalnız bilgi taşıyan özel alanları kapsar; formül/rollup motoru V4 adayına taşınmıştır. Z tam fazların sonuncusudur.

### 3.5 Çin cross-border ERP ve Amazon araçlarından alınan sınır dersi

Çin procurement ürünlerinde talep–询比价–PO–到货–质检–入库–付款 zinciri yaygındır [K17][K18][K19][K20][K21][K22]. TedarikApp açısından kullanılabilir kısım; yapılandırılmış ihtiyaç, teklif karşılaştırma, sipariş değişikliği, kalite ve ilerleme görünürlüğüdür. 1688'e doğrudan sipariş, depo, stok, ödeme ve finans parçaları kalıcı RET'e çarpar.

AiPrice görselle arama, fiyat geçmişi ve görsel indirmeyi [K14]; SellerSprite ürün/anahtar kelime/talep analizini [K15] örnekler. Bunlar E/F/K/L alanlarının derinleştirmesidir; yeni P–Z fazı gerekçesi değildir. Amazon Restock [K16] ve Zentail stok/listing/order otomasyonu [K12][K13] doğrudan stok ve pazaryeri satış entegrasyonu RET'ine çarpar.

## 4. A–O'da olmayan yetenek envanteri ve yerleşim kararı

| # | Yetenek | Ne işe yarar | Tek kişilik değer | RET durumu | Yerleşim önerisi |
|---:|---|---|---|---|---|
| 1 | Komut paleti + bağlamsal eylem | Ekran değiştirmeden kayıt üzerinde işlem | **Yüksek:** yoğun günlük kullanımda tıklamayı azaltır | Hayır | **P tam faz** — **31-R ile değişti** |
| 2 | Çoklu seçim + toplu eylem | Aynı değişikliği kontrollü gruba uygular | **Yüksek:** tekrarlı satır işini azaltır | Hayır | **P** |
| 3 | Sağ tık menüsü | Seçili kayıt için uygun eylemi yakınlaştırır | **Orta:** hız sağlar, tek başına değer üretmez | Hayır | **P** |
| 4 | Kayıtlı görünüm/favori | Günlük kuyrukları tek tıkla geri getirir | **Yüksek:** tek operatörün çalışma hafızası olur | Hayır | **P** |
| 5 | Tip güvenli akıllı yapıştırma | Excel/WhatsApp verisini önizlemeyle alanlara dağıtır | **Yüksek:** saha köprüsündeki elle girişi azaltır | Hayır | **P**, C/K köprüsünü kullanır |
| 6 | Sürükle-bırak ve dosyayı kayda bırakma | Öncelik, sıra ve belge bağlamını hızlandırır | **Orta:** özellikle görsel ürünlerde faydalı | Hayır | **P** |
| 7 | Etki önizlemeli undo | Toplu işlemi güvenli yapar | **Yüksek:** veri kaybı korkusunu azaltır | Hayır | G altyapısı + **P yüzeyi** |
| 8 | Yapılandırılmış sourcing brief | Firma görüşmesinden önce ihtiyacı tamamlar | **Yüksek:** yanlış/karşılaştırılamaz teklif riskini düşürür | Hayır | **Q bağlı blok — 31-R ile değişti** |
| 9 | Zorunlu/tercih/ret şartları | Alternatif ürünün nerede saptığını gösterir | **Yüksek:** ürün eşleşmesini fiyatın önüne koyar | Hayır | **Q** |
| 10 | Şartname sürümü ve alan farkı | Her tarafın aynı ürün tanımıyla çalışmasını sağlar | **Yüksek:** pahalı sipariş yanlışını önler | Hayır | **Q**, G audit altyapısı |
| 11 | Hazırlık/eksik veri kapısı | Eksik şartnameyi firmaya göndermeden gösterir | **Yüksek:** geri dönüş turunu azaltır | Hayır | **Q**, U kapıları |
| 12 | Ürün–ambalaj–etiket–numune ilişkisi | Dağınık bilgiyi tek teknik bağlama toplar | **Yüksek:** J/I/D parçalarını bağlar | Hayır | **Q/T/U**; Z yalnız bilgi taşıyan özel alan katmanıdır — **31-R ile değişti** |
| 13 | Teklif birim normalizasyonu | Paket/adet ve kademeleri eş düzleme getirir | **Yüksek:** yanlış ucuzluk algısını önler | Hayır | C derinleşmesi + **R** |
| 14 | Açıklanabilir fiyat dışı puanlama | Termin, şartname uyumu ve veri eksikliğini görünür kılar | **Orta/Yüksek:** çok teklif olduğunda güçlü | Sınır: due diligence puanı değil | **R** |
| 15 | Miktar/teklif bölme senaryosu | Sepeti farklı tekliflere dağıtıp sonucu gösterir | **Orta:** teklif ve ürün sayısı arttıkça değerli | Hayır | **R** |
| 16 | Senaryo kısıtları | Bütçe, MOQ, CBM ve hedef adedi birlikte denetler | **Yüksek:** ithalat kararının gerçek kısıtlarını birleştirir | Hayır | **R**, D hesaplarını kullanır |
| 17 | Karar gerekçesi ve varsayım | Sonradan “neden seçildi?” sorusunu cevaplar | **Yüksek:** tek kişinin zihinsel yükünü dışarı alır | Hayır | **S tetikli faz**; G izin matrisi tek kaynaktır — **31-R ile değişti** |
| 18 | Karar anı snapshot'ı | Eski teklif/kur/şartnameye dayalı kararı yeniden kurar | **Yüksek:** uyuşmazlık ve tekrar siparişte kanıt sağlar | Hayır | **S** |
| 19 | Kanıt türü ve bayatlık | Görsel/belgenin neyi kanıtladığını ve güncelliğini gösterir | **Orta/Yüksek:** dosya çöplüğünü önler | Hayır | **S** |
| 20 | Ambalaj seviye modeli | Birim–iç kutu–ana koli ayrımını kurar | **Yüksek:** CBM ve hasar maliyetini etkiler | Hayır | **T tam faz** — **31-R ile değişti** |
| 21 | Ambalaj sürümü/proof | Baskı, koli ve etiket onayını izler | **Yüksek:** yanlış ambalaj üretimini önler | Hayır | **T**, J ile bağlı |
| 22 | Kısıtlı yük yerleşimi | Kolilerin konteynıra gerçekten sığıp sığmadığını gösterir | **Orta/Yüksek:** büyük sevkiyatta tasarruf/risk önleme | Hayır | **T** |
| 23 | Tedarikçi–numune–mal kabul ölçü farkı | Beyan ile gerçekleşeni karşılaştırır | **Yüksek:** sonraki landed cost'u düzeltir | Hayır | **T**, D mal kabul |
| 24 | Dijital kalite checklist'i | Mal kabulün aynı adımla yapılmasını sağlar | **Yüksek:** kabul kanıtını standardize eder | Sınır: compliance değil | **U, V3-D mal kabul bağlı bloğu** — **31-R ile değişti** |
| 25 | Zengin kalite kanıtı | Foto/video/ölçümü kontrol maddesine bağlar | **Yüksek:** rücu ve yeniden kontrolü güçlendirir | Hayır | **U** |
| 26 | Kusur sözlüğü + CAPA | Kök neden, düzeltme ve kapanış kanıtını izler | **Yüksek:** tekrarlayan kusuru azaltır | Sınır: supplier due diligence değil | **U** |
| 27 | Golden sample farkı | Mal kabul bulgusunu mevcut onaylı referansla kıyaslar | **Yüksek:** kabul kanıtını güçlendirir | Hayır | **I mevcut numune kaynağı; U yalnız mal kabul** — **31-R ile değişti** |
| 28 | Satır düzeyi PO teyidi | Bir siparişte riskli satırı başlıktan ayırır | **Yüksek:** gizli kısmi gecikmeyi bulur | Hayır | C/D derinleşmesi + **V** |
| 29 | Resmî change order | Tarih/miktar/fiyat/şartname değişimini sürümler | **Yüksek:** WhatsApp sapmasını denetlenebilir yapar | Hayır | **V tam faz** — **31-R ile değişti** |
| 30 | Split shipment önerisi | Parti/taşıma ayrımını karşılıklı onaya alır | **Orta/Yüksek:** üretim gecikmesinde esneklik sağlar | Hayır | **V**, D sevkiyat |
| 31 | İstisna vaka nesnesi | Sapmayı önem, sahip, yaş ve çözümle yönetir | **Yüksek:** yalnız sorunlu işe odaklanmayı sağlar | Hayır | **V birleşik tam faz** — **31-R ile değişti** |
| 32 | Müdahale oyun kitabı | Benzer sapmada tutarlı seçenekler sunar | **Yüksek:** karar hızını ve kaliteyi artırır | Hayır | **V birleşik tam faz** — **31-R ile değişti** |
| 33 | Tekrar sipariş known-good dosyası | Geçmiş doğru yapılandırmayı güvenli başlangıç yapar | **Yüksek:** Tilbe Home'un tekrar ürünlerinde doğrudan değer | Hayır | **X tam faz** — **31-R ile değişti** |
| 34 | Son alıma göre fark | DDP/MOQ/termin/ambalaj değişimini görünür kılar | **Yüksek:** kör kopyalamayı önler | Hayır | **X** |
| 35 | Olay–koşul–eylem otomasyonu | Tekrarlı uygulama içi kontrolleri yürütür | **Orta/Yüksek:** akışlar oturunca zaman kazandırır | Sınır: dış bildirim/API/sipariş yok | **Y tetikli faz** — **31-R ile değişti** |
| 36 | Dry-run/idempotency/kural günlüğü | Otomasyonu açıklanabilir ve güvenli yapar | **Yüksek:** sessiz veri bozulmasını önler | Hayır | **Y** |
| 37 | Özel typed alanlar | Yeni kategori ayrıntısını kod değişmeden tutar | **Orta:** kategori çeşitliliğinde değerli | Hayır | **Z tam faz** — **31-R ile değişti** |
| 38 | Deterministik formül alanları | Basit türetilmiş değerleri merkezileştirir | **Orta:** esnek, fakat yanlış formül riski var | Hayır | **Z kapsamı dışı; V4 adayı** — **31-R ile değişti** |
| 39 | Süreç KPI/bottleneck | Teklif, sipariş ve istisna çevrim sürelerini gösterir | **Orta:** iyileştirme sağlar | Hayır | Yeni faz değil; **F Raporlar**, V birleşik vaka verisini kullanır — **31-R ile değişti** |
| 40 | Belge/tekliften taslak veri çıkarma | Kullanıcının yüklediği PDF/Excel/metni taslağa çevirir | **Yüksek:** elle giriş yükünü azaltır | Sınır: insan onayı ve resmî API yok | **P/C/K**; ayrı faz gerektirmez |

## 5. Yeni faz açmaması gereken bulgular

| Emsal yetenek | Neden yeni faz değil | Yerleşim |
|---|---|---|
| Görselle aynı ürünü arama, fiyat geçmişi, görsel indirme | E/K ve F/L'nin mevcut keşif/izleme kapsamı | E/F/K/L'ye küçük genişleme |
| Amazon anahtar kelime, yorum ve talep sinyali analizi | F zekâ ve L trend keşfinin çekirdeği | F/L |
| Temel RFQ, teklif turu, kör kıyas | C'de zaten var | R yalnız kısıtlı sepet senaryosu ekler |
| PO oluşturma, ödeme planı, CBM, landed cost, mal kabul | D'de zaten var | T/U/V yalnız D sonrası derinlik — **31-R ile değişti** |
| Numune ve AQL planı | I'de zaten var | U kalite yürütmesini ekler |
| SKU/etiket üretimi | J'de zaten var | T/U yalnız sürüm ve kanıt bağlantısı ekler |
| Çok dilli çıktı | M'de zaten var | Q/Z yeni etiketleri M sözlüğüne bağlar |
| Rol/portal/haricî taraf | N/O ve G'de zaten var | Yeni çoklu onay veya tenant açılmaz |
| Genel dashboard/rapor | B/F'de zaten var | V birleşik istisna kuyruğu, F süreç KPI'sı olur — **31-R ile değişti** |
| Mal kabulde telefonla fotoğraf ve sayım | Masaüstü/PWA genel yüzeyinin parçası sayılmıştı | **W tetikli faz oldu — 31-R ile değişti** |

## 6. RET çakışmaları — aday listesine alınmayanlar

Bu bölüm yalnız araştırmada görülen fakat bağlayıcı ürün kararlarıyla çakışan yetenekleri kaydeder.

| RET çakışması | Emsallerde görülen desen | Neden aday değil |
|---|---|---|
| **Gümrük sınıflandırma/compliance** | Jungle Scout HS/import kayıtları; QIMA compliance; ERP gümrük modülleri | Kalıcı RET; TedarikApp kalite kontrolü mevzuat uygunluk motoruna dönüşmez. Ayrıntılı V4 aday kaydı yalnız `v4-adaylari.md` dosyasındadır — **31-R gösterim notu** |
| **Muhasebe/cari/fatura** | Procurify three-way match; Çin ERP'lerde 对账/开票/付款 | Kalıcı RET; D yalnız operasyonel ödeme kaydı/snapshot tutar |
| **Stok/depo/replenishment** | Amazon Restock, Zentail inventory, 领星/店小秘/马帮 WMS | Kalıcı RET; X tekrar sipariş hafızası stok önerisi üretmez |
| **Pazaryeri satış API'leri** | Zentail ve cross-border ERP listing/order sync | Kalıcı RET; pazar siteleri yalnız keşif/talep sinyalidir |
| **Supplier due diligence/risk** | Jungle Scout import records, QIMA supplier score, SRM onboarding/risk | Kalıcı RET; U yalnız sipariş/ürün kalite kanıtını tutar |
| **Çok kiracılık/SaaS satışı** | Kurumsal procurement tenant/organizasyon yapıları | Tek kullanıcı ve belirli dış portallar yeterlidir |
| **E-posta/push bildirim** | Anvyl, Coupa, SourceDay e-posta teyitleri | Kalıcı RET; V/Y yalnız uygulama içi Gelen Kutusu/Panorama kullanır — **31-R ile değişti** |
| **Resmî platform API'si** | ERP–1688 doğrudan sipariş/ödeme; marketplace entegrasyonları | Kalıcı RET ve “sitelerden alım yok” ilkesi |
| **Alibaba escrow/Trade Assurance ödeme akışı** | Platform içi ödeme ve koruma | TedarikApp gerçek işlemi ithalatçı firma üzerinden yürütür; yalnız şart/kanıt deseni alınır |
| **Kurumsal çok kademeli onay** | Coupa/SAP/Procurify approval routing | G/N rol sınırını büyütür; tek iç operatöre değer düşük |

Not: Gerçekleşmiş siparişin zamanında teslim, teklif doğruluğu veya kusur tekrarını **sipariş bağlamında raporlamak**, kimlik/risk taraması yapmadığı sürece supplier due diligence değildir. Yine de bu metrikler yeni supplier score fazına dönüştürülmemeli; gerekiyorsa F raporlarına sınırlı operasyon metriği olarak eklenmelidir.

## 7. P–Z nihai haritası

**31-R ile değişti:** Bu bölüm artık aday sıra değil, bağlayıcı tür/sıra/bağ/tetik kaydıdır.

| Harf | Tür | Sıra | Bağlı faz / tetik | Boy | Emir davranışı |
|---|---|---:|---|:---:|---|
| P | tam | 1 | — | L | Sıralı emir alır |
| Q | blok | — | V3-C/N RFQ bloğu | M | Kendi emri yok |
| R | tam | 4 | — | M | Sıralı emir alır |
| S | tetikli | — | İkinci hesap fiilen açılır | M | Tetikten önce emir derlenmez |
| T | tam | 2 | — | L | Sıralı emir alır; T1→T3 kademeli |
| U | blok | — | V3-D mal kabul bloğu | L | Kendi emri yok |
| V | tam | 5 | — | L | Sıralı emir alır; değişiklik ve istisna birleşik |
| W | tetikli | — | Mal kabulde telefon ihtiyacı fiilen doğar | M | Tetikten önce emir derlenmez |
| X | tam | 3 | — | M | Sıralı emir alır |
| Y | tetikli | — | Aynı elle işlemin en az üç ay düzenli tekrarı kanıtlanır | L | Tetikten önce emir derlenmez |
| Z | tam | 6 | — | L | Sıralı emir alır; son blok V3 Kapanış |

### 7.1 P — Operatör Hızı & Sayfa Olgunluğu

**31-R ile değişti:** Araştırma kapsamı ve L boy korundu; tam faz sırası 1 olarak kesinleşti.

**Çekirdek kapsam:** satır içi düzenleme; çoklu seçim; bağlamsal menü; komut paleti/kısayol; kayıtlı görünüm; akıllı yapıştırma; sürükle-bırak; etki özeti/geri alma; odak ve URL durumu; masaüstü/PWA/erişilebilirlik/performans kabulü.

**Ölçülebilir kayıp kapısı:** yoğun ekranlarda işlem süresi, tekrar tıklama, hatalı toplu işlem veya veri giriş düzeltmesi ölçülmeden emir derlenmez.

### 7.2 Q — Şartname Stüdyosu

**31-R ile değişti:** Bağımsız M faz olmaktan çıktı; V3-C/N RFQ bloğundaki “beklenti/ölçü/malzeme şartı” bölümü oldu.

**Amaç ve kapsam:** Çin ekibinin yanlış ürünü bulmasını engellemek için beklenti, ölçü, malzeme, renk/set/tolerans, zorunlu-tercih-ret şartları, referans görsel ve firmaya giden dondurulmuş sürüm aynı RFQ içinde tutulur. Kendi emri, ayrı durum sözlüğü veya ayrı süreç kapısı yoktur.

### 7.3 R — Senaryo & Karar Laboratuvarı

**31-R ile değişti:** M boy korundu; tam faz sırası 4 oldu. Aynı ürün veya sepet için birden çok ithalatçıdan kısmi alım gerçek saha davranışı olarak kesinleşti.

**Çekirdek kapsam:** paket/adet ve kur snapshot normalizasyonu; kademeli fiyat/MOQ/termin/veri eksikliği karşılaştırması; kullanıcı tarafından elle miktar bölme; DDP, landed cost, CBM, termin ve karşılanmayan koşul etkisi; bayat senaryo işareti; görünür karar gerekçesi. **Otomatik optimum, otomatik kazanan ve otomatik sipariş yoktur.**

**Ölçülebilir kayıp kapısı:** elle bölme ve yeniden hesaplama süresi ile yanlış bölmeden doğan DDP/CBM/termin sapması ölçülür.

### 7.4 S — Ekip & Karar Defteri

**31-R ile değişti:** Orijinal karar/kanıt defteri korunarak ikinci hesap yaşam döngüsü ve görev atama kapsamıyla tetikli M faz oldu. Tetik, ikinci hesabın fiilen açılmasıdır.

**Çekirdek kapsam:** davet, askıya alma, şifre ve 2FA politikası; kişiye ve vadeye bağlı görev; Panorama “sana atananlar”; operatör görünürlük sınırı; kısa karar gerekçesi, snapshot, varsayım, kanıt kökeni, bayatlık ve karar zinciri. Operatör görünürlüğü G'deki izin matrisiyle **tek kaynaktır**; ikinci izin modeli kurulmaz.

### 7.5 T — Ambalaj & Yük Mühendisliği

**31-R ile değişti:** L boy korundu; tam faz sırası 2 oldu. Konteyner doluluk/yerleşim sorunu ile koli ölçüsünün teklif→numune→mal kabul arasında değişmesi birlikte ele alınır.

- **T1:** birim/iç kutu/ana koli/set ölçü-ağırlık modeli, sürüm, teklif-numune-mal kabul farkı ve CBM/landed cost etkisi.
- **T2:** doğrulanabilir 2B/katman yerleşimi, yön/kırılganlık/istif kısıtları, elle düzeltme ve yerleşemeyen koli nedeni.
- **T3:** kanıtlanmış ihtiyaçta gelişmiş 3B yük planı ve gerçekleşen sevkiyat sapması.

**Ölçülebilir kayıp kapısı:** doluluk kaybı, yerleşemeyen koli, yanlış ölçü nedeniyle maliyet farkı ve mal kabul sapması izlenir.

### 7.6 U — Kalite Güvence & CAPA

**31-R ile değişti:** Bağımsız L faz olmaktan çıktı; V3-D mal kabul bloğuna bağlandı. Numune süreci yoktur; tek kanıt kaynağı mal kabuldür.

**Çekirdek kapsam:** mal kabul kontrol listesi; kritik/majör/minör kusur; fotoğraf/video/belge/ölçüm kanıtı; geçti/şartlı/kaldı/yeniden kontrol kayıtları; kök neden, düzeltici faaliyet, sorumlu, hedef tarih ve kapanış kanıtı; rücu/yeniden işleme/değişim/indirim/bekletme operasyon kaydı. **“Geçti” kararını yalnız Ürün Sahibi verebilir.**

### 7.7 V — Sipariş Değişiklik Kontrolü + İstisna & Müdahale Merkezi

**31-R ile değişti:** #31'deki V ve W tek L tam fazda birleşti; sıra 5 oldu. Ana durum makinesi değişmez.

**Çekirdek kapsam:** satır düzeyi değişiklik talebi ve eski-yeni farkı; kabul/ret/karşı öneri/kısmi kabul; firma teyidi ve iç onay ayrımı; bölünmüş sevkiyat; etki hesabı; değişmez sipariş revizyonu; insan onaylı WhatsApp/Excel taslağı; istisna türü/önem/yaş/etki; Panorama/Gelen Kutusu vaka kuyruğu; sahip/sonraki eylem/hedef tarih; müdahale oyun kitabı; çözüm kanıtı/kapanış/yeniden açma.

**Ölçülebilir kayıp kapısı:** açık sapma yaşı, teyitsiz değişiklik, yanlış revizyonla işlem, geç müdahale ve tekrar açılan vaka oranı ölçülür.

### 7.8 W — Mobil Saha Akışları

**31-R ile değişti:** Önce ayrı faz sayılmayan mobil saha ihtiyacı tetikli M faz oldu. Tetik, mal kabulde telefon ihtiyacının fiilen doğmasıdır.

**Çekirdek kapsam:** telefonda mal kabul kaydı açma; barkod/ürün/sipariş satırı bulma; fotoğrafı doğru kontrol maddesine bağlama; hızlı sayım ve koli/adet farkı; ölçüm/not; bağlantı kesintisinde taslak; yeniden bağlanınca açık çakışma çözümü. Tetik yoksa mobil yalnız inceleme yüzeyidir.

### 7.9 X — Tekrar Sipariş & Ürün Hafızası

**31-R ile değişti:** #31 kapsamı ve M boy korundu; tam faz sırası 3 oldu.

**Çekirdek kapsam:** değişmez known-good ürün dosyası; son şartname/varyant/ambalaj/etiket/kalite bağlantıları; önceki DDP/MOQ/kur/termin/koli/CBM/landed cost özeti; kontrollü tekrar sipariş taslağı; son alım farkı; istisna/kusur/rücu/CAPA dersi; 同款 yeniden arama başlangıcı; yeniden teyit tarihleri; sürümlü belge referansı; stok veya otomatik replenishment üretmeme.

**Ölçülebilir kayıp kapısı:** tekrar siparişi hazırlama süresi, eski şartı kör kopyalama ve geçmiş kusuru atlama vakası ölçülür.

### 7.10 Y — Kural/Otomasyon Atölyesi + panel içi AI asistanı

**31-R ile değişti:** L boy korundu; tetikli faz oldu. Tetik, aynı elle işlemin en az üç ay düzenli tekrarının kanıtlanmasıdır.

**Çekirdek kapsam:** olay-koşul-eylem kuralı; yalnız whitelist iç eylemler; dry-run ve etki önizlemesi; insan onayı ayrımı; idempotency; sürüm ve çalışma günlüğü; duraklatma/geri alma; hazır oyun kitapları; panel içi AI asistanının aynı whitelist üzerinden taslak, açıklama ve öneri üretmesi. Serbest kod, webhook, dış bildirim, resmî platform çağrısı ve otomatik sipariş yoktur.

### 7.11 Z — Özel Alanlar & Uyarlanabilir Veri Katmanı + V3 Kapanış

**31-R ile değişti:** L boy korundu; tam faz sırası 6 oldu. Formül motoru çıkarıldı; son blok “V3 Kapanış” olarak kesinleşti.

**Çekirdek kapsam:** ürün/liste/firma/sipariş kayıtlarında metin, sayı, tarih, seçim listesi, evet/hayır ve dosya tipli özel alan; alan grubu/sıra; varsayılan/zorunlu; Keşif ve listelerde sütun/filtre/sıralama; Excel/PDF/paylaşım çıktısı; V3-N rol whitelist matrisi; Excel gel-git eşleşmesi; K56 çeviri hattı; eklentide “sayfa alanı → özel alan” eşlemesi. Özel alanlar ana durum makinesine, kur/fiyat hesabına veya skora girmez; yalnız bilgi taşır.

**V3 Kapanış:** sağlamlaştırma turu, belgelerin tamamlanması ve V4 vizyon kaydıdır; ayrı harf değildir.

**Ölçülebilir kayıp kapısı:** kod değişikliği gerektiren gerçek bilgi alanı sayısı, dışa aktarımda kaybolan alan ve Excel gel-git eşleme hatası ölçülür.

## 8. Sıra kararı

**31-R ile değişti:** #31'deki ana ve alternatif sıralar yürürlükten kalktı. Tek bağlayıcı tam faz sırası `P → T → X → R → V → Z`'dir. Q/U bağlı bloklar ile S/W/Y tetikli fazlar bu dizinin elemanı değildir.

## 9. Paketleme kararı

**31-R ile değişti:** Kaynak/takvim için önerilen birleşim seçenekleri karar olmaktan çıktı. Yalnız V ile eski W kapsamı birleşmiştir. Q ve U bağlı blok; S, W ve Y tetikli faz; Z ise V3 Kapanış alt bloğunu taşıyan tam fazdır. Boy tahminleri yeniden yapılmamıştır.

## 10. P–Z Anayasası

**31-R ile değişti:** #31'de “çapraz kesen mimari ve kabul notları” olan on madde artık bağlayıcı **P–Z Anayasasıdır**. Kural, gerekçe, ihlal örneği ve sınanacağı fazlar `p-z-anayasa.md` dosyasında 10/10 olarak tutulur. Faz emirleri bu anayasa maddelerine aykırı derlenemez.

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
| [K28] | Attio — UI navigation | Bağlama duyarlı quick actions ve kısayollar |
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

- **31-R ile değişti:** Nihai kararlar uygulanmıştır; bu bölüm yeni kapsam veya sıra önermez.
- P yatay ürün kabiliyeti olarak korunmuş, fakat emri ölçülebilir kayıp kapısına bağlanmıştır.
- Q ve U ayrı emir üretmez; sırasıyla V3-C/N RFQ ve V3-D mal kabul içinde uygulanır.
- V, değişiklik ile istisna/müdahale kapsamını tek kayıtta taşır; ana durum makinesi değişmez.
- W yalnız telefonla mal kabul ihtiyacı fiilen doğduğunda açılır; aksi durumda mobil yüzey inceleme amaçlıdır.
- X stok veya otomatik sipariş miktarı üretmez. Y yalnız whitelist eylemler kullanır. Z yalnız bilgi taşır; durum, kur/fiyat ve skor hesaplarına girmez.

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
[K28]: https://attio.com/help/
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
