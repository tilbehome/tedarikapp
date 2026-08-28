# Görev #28 — V3-C Firma Döngüsü Saha Gerçekleri Araştırması

**Durum:** Araştırma + mevcut V3-C kararlarına bağlama  
**Gözlem tarihi:** 28 Ağustos 2026  
**Kapsam:** Türkiye’de çalışan Çin tedarik/ithalat aracıları öncelikli; genel B2B satın alma sistemleri ikincil emsal  
**Bağlayıcı zemin:** Firma portalı, WhatsApp/Excel yapıştır-ayrıştır köprüsü, kilitli kur, teklif geçerliliği, kademeli fiyat, revizyon turları ve `Bulunamadı/Alternatif` yanıtı  
**Kapsam dışı:** Yeni ürün özelliği, veritabanı şeması, hukuki mütalaa ve süre/oran taahhüdü

> Bu rapor “kusursuz portal” varsayımı kurmaz. Döngünün nerede kırıldığını, eldeki V3-C araçlarından hangisinin bu kırılmayı telafi edeceğini belirler.

## 1. Yönetici özeti

1. Türkiye sahasında tek kanal yoktur. Kamuya açık firma sayfaları WhatsApp, WeChat, telefon, e-posta, web formu ve rapor/teklif belgesinin birlikte kullanıldığını gösterir. Portal dışı dönüş istisna değil, tasarım girdisidir.
2. Buna karşılık “yanıtların yüzde kaçı Excel/WhatsApp/PDF gelir?” ve “kısmi dönüş oranı nedir?” sorularına güvenilir, Türkiye geneli bir kamu verisi bulunamadı. Oran yazmak ölçülmemiş iddia olur.
3. Günlük mesaj ile bağlayıcı karar kaydı ayrışır: hızlı iletişim mesajlaşmada yürür; fiyat, termin, şart ve revizyonun yazılı teklif/proforma veya e-posta dosyasında kayda alınması tavsiye edilir.
4. Kısmi dönüş gerçek bir satın alma davranışıdır. Büyük satın alma ürünleri de tedarikçiye satırların yalnız bir bölümünü yanıtlama ve çevrimdışı tabloyla dönme olanağı tanır. Bu nedenle 25 satırdan 18’inin fiyatlanması “hatalı dosya” değil, kısmi tur cevabıdır.
5. DDP teslim şekli kur riskini çözmez. Incoterms görev, maliyet ve teslim riskini dağıtır; ödeme para birimi, kur kaynağı, kur zamanı ve fiyat geçerliliği ayrıca yazılmalıdır.
6. Teklif geçerliliği ile kur kilidi aynı kavram değildir. Biri ticari şartların kabul penceresi, diğeri TL dönüşümünün garanti penceresidir. Değişmeden kabul edilebilir TL toplam için iki koşulun da aynı anda yürürlükte olması gerekir; aksi sözleşmede açıkça kararlaştırılabilir.
7. `500/1000/2000` kademeleri birer miktar eşiği/aralığı olarak verilmişse 700 adet, 500 kademesinin aralığına girer. Yalnız tam miktarlara verilmiş üç ayrı teklifse 700 fiyatı türetilmez; yeniden sorulur. Doğrusal interpolasyon için saha standardı bulunamadı.
8. Revizyon turu için güvenilir bir “sektör ortalaması” bulunamadı. Tur sayısına yapay üst sınır koymak yerine her turun değişmez istek ve yanıt görüntüsü korunmalıdır.
9. Alternatif ürün, asıl satırı ezmemelidir. Genel B2B emsalinde alternatif, asıl satıra bağlı ayrı bir yanıt satırıdır; ikisi birlikte değerlendirilir. V3-C’de de `Bulunamadı` asıl ürünün sonucu, önerilen benzer ürün ise ona bağlı ayrı alternatif cevap olmalıdır.

## 2. Araştırma yöntemi ve kanıt sınırı

### 2.1 İncelenen kanıt türleri

- Türkiye’de hizmet veren Çin tedarik/ithalat aracılarının kamuya açık talep formları, süreç anlatımları ve iletişim kanalları.
- Türkiye’de dış ticaret/proforma belgesi ve kur koşulu konusunda kamuya açık kurumsal örnekler.
- ICC’nin Incoterms açıklamaları.
- Oracle Procurement, Coupa Sourcing ve Alibaba’nın kısmi cevap, çevrimdışı tablo, alternatif satır, revizyon ve miktar kademesi desenleri.

### 2.2 Kanıt sınıfları

- **Kesin bulgu:** Kaynakta doğrudan görülen alan, kanal, belge veya davranış.
- **Sınırlı saha göstergesi:** Bir veya birkaç firmanın kamuya açık uygulaması; Türkiye geneline oranlanamaz.
- **Yorum/öneri:** Kesin bulgunun mevcut V3-C kararına uygulanması.
- **Ölçülemeyen alan:** Güvenilir kamu verisi bulunmadığından sıklık veya oran verilemeyen konu.

### 2.3 Önemli sınırlama

Firmalar müşteri yazışmalarını ve gerçek fiyat tablolarını ticari gizlilik nedeniyle yayımlamaz. Bu yüzden kamuya açık kaynaklar kanal ve alan yapısını kanıtlar; gerçek mesajların dağılımını veya hata oranını kanıtlamaz. Ek dosyadaki örnekler, gerçek kişisel/ticari yazışma kopyası değil, kaynaklarda görülen format sınıflarından türetilmiş güvenli ayrıştırma numuneleridir.

## 3. Araştırma sorusu 1 — Türkiye’de teklif dönüş pratiği

### 3.1 Kesin bulgular

#### Bulgu 1 — İlk talep tek bir standart dosyayla başlamıyor

Hesnaf Global’in teklif formunda firma/kişi/telefon/e-posta yanında ürün adı, miktar, açıklama, boyut-ağırlık, hedef fiyat, teslimat yeri ve not alanları bulunuyor; aynı sayfa telefon/WhatsApp kanalını da yayımlıyor. Bu, ürün talebinin hem yapılandırılmış formdan hem doğrudan mesaj kanalından başlayabildiğini gösterir.  
Kaynak: [Hesnaf Global teklif talep formu](https://hesnafglobal.com/teklif-talep-formu/) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Liste gönderimi ve yapıştır-ayrıştır köprüsü; satır eşlemede ürün adı tek başına yeterli kabul edilmemeli, miktar ve teslim yeri de bağlam olarak korunmalı.

#### Bulgu 2 — Türkiye–Çin çalışma hattında mesajlaşma ve rapor birlikte kullanılıyor

FimexAsia ön görüşme için WhatsApp ve e-posta sunuyor; süreç anlatımında Türkçe raporlama, fiyat/teslim şartlarının yazılı sözleşmesi ve kapı teslim maliyet tablosu yer alıyor. Bu, hızlı kanalın yanında daha biçimli rapor/teklif çıktısının kullanıldığını gösteren sınırlı bir firma örneğidir.  
Kaynak: [FimexAsia](https://cindengetir.com/) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Firma portalı tek zorunlu dönüş yolu olmamalı; mevcut WhatsApp/Excel köprüsü ve dosya eki aynı turun farklı giriş kanalları olarak ele alınmalı.

#### Bulgu 3 — Günlük iletişim ile karar kaydı ayrışıyor

Shanghai Trimpex’in Türkiye’ye yönelik rehberi, günlük takip için WeChat’i; karar, fiyat, termin, teknik şart ve revizyonlar için e-posta/teklif belgesini öneriyor. Aynı kaynak RFQ hazırlığında ürün linki/görsel, teknik özellik, varyasyon, paketleme, adet, MOQ ve termin bilgisini birlikte istiyor.  
Kaynak: [Çin’den Ürün Tedariki Başlangıç Rehberi](https://www.cindenyedekparca.com/news/cinden-%C3%BCr%C3%BCn-tedariki%C4%9F-baslang%C4%B1c-rehberi) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Yapıştırılan WhatsApp/WeChat metni cevap girdisi olabilir; turun gönderilmiş/nihai cevabı ise fiyat, termin ve şartları içeren kayda bağlanmalıdır.

#### Bulgu 4 — “Rapor” ve proforma, dönüşün kurumsal biçimlerindendir

Pentex, fiyat/üretici araştırması sonucunu rapor halinde sunduğunu ve proforma faturanın hazırlanmasını takip ettiğini açıklar. Orta Anadolu İhracatçı Birlikleri ise proformayı teklif belgesi olarak tanımlar; mal cinsi, miktar, birim fiyat, döviz, teslim ve ödeme şekli, ambalaj ve sevkiyat ayrıntılarının bulunabileceğini belirtir.  
Kaynaklar: [Pentex Çin danışmanlığı](https://www.gumrukdanismanligi.net/cinde-danismanlik-ve-ithalat-hizmetleri/comment-page-4/), [OAİB — Dış Ticarette Kullanılan Faturalar](https://oaib.org.tr/bilgi-ve-operasyon-merkezi/ihracat-belgeleri/dis-ticarette-kullanilan-faturalar) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** PDF/proforma, ham satır cevabının yerine geçmek zorunda değildir; aynı turun kaynak belgesi olarak saklanıp satır cevaplarıyla ilişkilendirilmelidir.

#### Bulgu 5 — Çevrimdışı Excel/CSV, kurumsal satın almada yerleşik bir geri dönüş yoludur

Oracle Procurement çok satırlı görüşmelerde tablonun dışa aktarılıp çevrimdışı doldurulmasını ve geri içe alınmasını destekler. Coupa da yanıtı Excel/CSV ile dışarıda doldurup içe aktarmayı önerir. Bu kaynaklar Türkiye’ye özgü değildir; fakat V3-C’de planlanan Excel gel-git deseninin genel B2B pratiğiyle uyumlu olduğunu doğrular.  
Kaynaklar: [Oracle — Response to Negotiations](https://docs.oracle.com/en/cloud/saas/procurement/26c/oaprc/response-to-negotiations.html), [Coupa — Sourcing FAQ](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/sourcing-faq) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Excel içe aktarma, portalın ikincil bir kolaylığı değil mevcut V3-C döngüsünün eşdeğer yanıt kanalıdır; yine aynı rol süzgeci ve tur kimliğiyle işlenmelidir.

#### Bulgu 6 — Kısmi cevap sistemsel olarak meşru bir durumdur

Oracle, tedarikçinin tüm satırlara cevap vermek zorunda olmayabileceğini ve talep edilen miktarın yalnız bir kısmını önerebileceğini açıkça destekler. Coupa’da bir satıra katılmamak için fiyatın boş bırakılması yönlendirilir. Bunlar Türkiye’de kısmi cevabın sıklığını ölçmez; çok kalemli B2B teklifin doğal olarak kısmi olabildiğini gösterir.  
Kaynaklar: [Oracle — Response Controls](https://docs.oracle.com/en/cloud/saas/procurement/26c/oaprc/response-to-negotiations.html), [Coupa — kısmi katılım](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/sourcing-faq) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Boş fiyat görülen satır otomatik `Bulunamadı` yapılmamalı; “cevap verilmedi” ile açıkça `Bulunamadı` cevabı ayrılmalıdır. Tur, kısmi yanıt olarak kalabilmelidir.

### 3.2 Ölçülemeyen alanlar

- Türkiye’de Çin tedarik aracılarına gelen/dönen tekliflerin Excel, WhatsApp ve PDF yüzdeleri yayımlanmıyor.
- 25 satırlık bir listede ortalama kaç satırın ilk turda fiyatlandığına ilişkin kamu verisi bulunamadı.
- “Bulamadım ama benzeri var” ifadesinin hangi sıklıkta kullanıldığı ölçülemiyor.

Bu üç başlıkta oran veya “çoğunlukla” ifadesi kullanılmamalıdır.

### 3.3 Yorum ve V3-C’ye bağlama

Yapıştır-ayrıştır köprüsü en az şu gerçek biçim sınıflarını karşılamalıdır; ayrıntılı numuneler ek dosyadadır:

1. Numaralı WhatsApp satırları; her satırda ürün adı/kod, fiyat, adet, MOQ, termin ve kısa not.
2. Tek mesajda toplu cevap; bazı satırlarda yalnız `yok`, `bakılıyor`, `benzeri var` gibi durum metni.
3. Alternatif link/görselin asıl ürün cevabının hemen altında gönderilmesi.
4. `500: 4,20 / 1000: 3,85 / 2000: 3,50` gibi yatay kademeli fiyat.
5. Excel’de sütunların farklı sırada olması ve `Adet fiyat`, `Birim`, `DDP`, `Kapı teslim`, `Termin`, `Lead time` gibi eş anlamlı başlıklar.
6. PDF/proformada yalnız biçimli toplamların bulunması; satır kimliğinin ürün açıklaması veya tedarikçi ürün koduna gömülmesi.
7. Ondalık ayraç ve para işaretinin değişmesi: `4,25 USD`, `$4.25`, `4.25$`, `₺215,00`.
8. KDV bilgisinin ayrı dipnotta yer alması veya hiç yazılmaması.

**Telafi ilkesi:** Ayrıştırıcı belirsizliği “kesin fiyat”a çevirmemelidir. Satır eşleşmesi, para birimi, KDV, DDP teslim noktası veya kademe anlamı belirsizse sonuç kesinleştirilmeden mevcut doğrulama adımına bırakılmalıdır.

**Etkilediği ekran/kural:** Yapıştır-ayrıştır önizlemesi, Excel içe aktarma sonucu, satır cevap durumu ve tur tamlık göstergesi.

## 4. Araştırma sorusu 2 — Kur kilidi ihtilafları

### 4.1 Kesin bulgular

#### Bulgu 7 — DDP, ödeme ve kur şartının yerine geçmez

ICC’ye göre DDP; satıcının taşıma, ihracat/ithalat işlemleri ve belirlenmiş varış noktasına kadar maliyet/risk sorumluluğunu düzenler. ICC ayrıca Incoterms’in satış sözleşmesindeki ödeme ve kalite şartlarının yerine geçmediğini açıkça belirtir.  
Kaynaklar: [ICC — EXW or DDP](https://academy.iccwbo.org/incoterms/article/incoterms-2020-exw-or-ddp/), [ICC — Incoterms 2020: New Rules, Old Problems](https://academy.iccwbo.org/incoterms/article/incoterms-2020-new-rules-old-problems/) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** `DDP + KDV dahil` etiketi, kur kaynağı/değeri/zamanı ve teklif geçerliliğinin yerine kullanılamaz; mevcut alanların her biri ayrıca dolu olmalıdır.

#### Bulgu 8 — Proforma üzerinde fiyatın kapsamı ve geçerliliği ayrıca yazılır

OAİB’nin proforma açıklamasında para birimi, miktar, birim/toplam fiyat, teslim ve ödeme şekli ile fiyatın ne zamana kadar geçerli olduğunun belgede bulunması gerektiği belirtilir.  
Kaynak: [OAİB — Proforma Fatura](https://oaib.org.tr/bilgi-ve-operasyon-merkezi/ihracat-belgeleri/dis-ticarette-kullanilan-faturalar) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Nihai firma cevabı, yalnız TL birim fiyatla tamamlanmış sayılmamalı; mevcut kur kilidi ve geçerlilik koşulları da cevap bağlamında tutulmalıdır.

#### Bulgu 9 — Türkiye’de kur farkı bakımından yazılı şart belirleyicidir

Türk Borçlar Kanunu m.26 tarafların sözleşme içeriğini kanuni sınırlar içinde belirleyebileceğini; m.99 yabancı para borcunun TL ile ödenmesinde sözleşme ve ödeme günündeki rayiç ilişkisini düzenler. Güncel kambiyo mevzuatı ayrıca Türkiye’de yerleşik kişiler arasındaki ödeme biçimini etkileyebilir. Yargıtay/BAM kararlarını derleyen hukuk incelemelerinde, kur farkı talebinde sözleşmedeki açık hüküm veya işlemin yabancı para temeli belirleyici görünmektedir.  
Kaynaklar: [Resmî Gazete — 6098 sayılı Türk Borçlar Kanunu](https://www.resmigazete.gov.tr/eskiler/2011/02/20110204-1.htm), [Hazine ve Maliye Bakanlığı duyurusu](https://www.hmb.gov.tr/duyuru/finansal-piyasalar-ve-kambiyo-genel-mudurlugunden-duyuru), [karar derlemesi](https://www.kilinc.av.tr/yargitay-kararlari-isiginda-kur-farki-alacagi/) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** V3-C’nin kur kaydı yalnız hesaplama metadatası değil, teklif şartının kanıtıdır. Kur kaynağı, türü, değeri, zaman damgası ve hangi tarihe kadar kilitli olduğu kaybolmamalıdır.

> Hukuk notu: Dövizli/dövize endeksli sözleşme ve TL ödeme kuralları değişebildiğinden, üretim şartnamesine girecek standart teklif hükmü hukuk danışmanı tarafından güncel mevzuatla doğrulanmalıdır.

#### Bulgu 10 — Türkiye’de satıcının kur riskini alıcıya aktardığı açık şart örneği vardır

BİRÇELİK’in kamuya açık satış koşullarında döviz teklifinin kısa süreli geçerli olduğu, TL tutarın ödeme anındaki satış kuruna göre hesaplandığı ve kur farkının alıcıdan istenebileceği yazılıdır. Bu tek firma örneğidir; Türkiye geneli teamül oranı değildir. Yine de riskin yazılı hükümle alıcıya aktarılabildiğini somut olarak gösterir.  
Kaynak: [BİRÇELİK Genel Satış ve Teslimat Koşulları](https://bircelik.com/tr/kategori/genel-satis-kosullari) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Firma cevabındaki “ödeme günündeki kur”, “teklif kuru”, “kur farkı ayrıca” veya “TL sabit” ifadeleri aynı anlama getirilmemeli; kilit şartı olduğu gibi korunmalıdır.

### 4.2 Yorum — risk kime yazılır?

Tek tip sektör cevabı yoktur; risk tarafların yazılı şartına göre dağıtılır. V3-C bağlamında açık okuma şöyledir:

- Firma `TL fiyat şu kura göre ve şu tarihe kadar sabittir` diyorsa, o pencere içinde koşulsuz sabitlenen kur hareketi teklif veren firmanın riskidir.
- Kilit süresi geçtikten sonra `ödeme günündeki kurla yeniden hesaplanır` şartı varsa sonraki kur hareketi alıcıya geçer.
- Teklif yalnız `7 gün geçerli` diyor, kur şartını söylemiyorsa kur riskinin nasıl dağıtıldığı kesin değildir; sistem bunu varsayımla tamamlamamalıdır.
- DDP yazması, kur riskini otomatik olarak ithalatçı firmaya veya alıcıya yüklemez.

**Etkilediği ekran/kural:** Kur kilidi özeti, teklif koşulları ve ödeme öncesi fiyat doğrulaması.

### 4.3 Yorum — teklif geçerliliği ile kur kilidi ayrışırsa hangisi esas?

İki süre farklı iş yapar:

| Süre | Cevapladığı soru | Süre dolunca |
|---|---|---|
| Teklif geçerliliği | Ticari teklif hangi tarihe kadar kabul edilebilir? | Teklifin kabulü için yeni onay/teklif gerekir. |
| Kur kilidi | Gösterilen TL toplam hangi tarihe kadar aynı kurla korunur? | Ticari şartlar hâlâ geçerli olsa bile TL fiyat yenilenebilir. |

Değişmeden kabul edilebilir TL toplam açısından iki koşulun birlikte yürürlükte olması gerekir. Örnek: teklif 30 gün, kur 7 gün kilitliyse 8. günde teklifin ürün/termin şartları sürebilir; fakat aynı TL toplamın sürdüğü varsayılamaz. Sözleşmede farklı ve açık bir öncelik hükmü varsa o hüküm uygulanır.

**Etkilediği ekran/kural:** Teklif/tur başlığı iki tarihi ayrı göstermeli; biri dolduğunda diğeri de dolmuş gibi gösterilmemeli. Aynı zamanda kilidi bitmiş TL fiyat “hala kesin” olarak kullanılmamalıdır.

## 5. Araştırma sorusu 3 — Kademeli fiyatın pazarlık gerçeği

### 5.1 Kesin bulgular

Oracle Procurement miktara dayalı fiyat kademelerini destekler ve tedarikçinin farklı fiyat kırılımları önerebilmesine izin verir. Alibaba’nın satıcı içeriğinde de kademeler `100–499`, `500–999`, `1000+` gibi miktar aralıkları halinde gösterilir.  
Kaynaklar: [Oracle — Negotiation Lines](https://docs.oracle.com/en/cloud/saas/procurement/25d/oaprc/negotiation-lines.html), [Alibaba — Tiered Pricing Display](https://seller.alibaba.com/blogs/2026/southeast-asia/bar-wine/moq-requirements-100-500-pieces-guide-alibaba-b2b) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Kademe kaydı yalnız `miktar + fiyat` dizisi olarak değil, tedarikçinin verdiği eşik/aralık anlamıyla gösterilmelidir; mevcut 阶梯价 alanı bu anlamı kaybetmemelidir.

### 5.2 Yorum — 500/1000/2000 verildi, alıcı 700 istedi

Üç durum ayrılmalıdır:

1. **Eşik/aralık açık:** `500–999`, `1000–1999`, `2000+` yazıyorsa 700 için 500–999 fiyatı uygulanır.
2. **“Başlangıç adedi” anlamı açık:** `500+`, `1000+`, `2000+` biçiminde ve üst eşikler bir sonraki kademeyi başlatıyorsa 700, 500 eşiğinin fiyatındadır.
3. **Yalnız üç tam miktara özel fiyat:** `500 adet için X; 1000 için Y; 2000 için Z` denmiş, ara aralık tanımlanmamışsa 700 fiyatı çıkarılamaz; firma yeniden fiyatlamalıdır.

Doğrusal interpolasyon önerilmez. Kaynaklarda ara miktarın iki fiyat arasında matematiksel olarak bölüştürüldüğünü gösteren genel bir B2B kural bulunmadı; üretim kurulum, ambalaj ve MOQ maliyetleri doğrusal olmak zorunda değildir.

700 adet için firma yeni fiyat verirse, ilk 500/1000/2000 kademeleri değiştirilmez; 700 cevabı sonraki turun teklifi olarak saklanır.

**Etkilediği ekran/kural:** Kademeli fiyat satırları, yapıştır-ayrıştır doğrulaması ve revizyon turu karşılaştırması.

## 6. Araştırma sorusu 4 — Revizyon turları

### 6.1 Kesin bulgular

- Coupa’da alıcı etkinliği değiştirdiğinde yeni revizyon oluşur; tedarikçi değişikliği kabul eder. Revize olay için önceki Excel yeniden kullanılamaz, güncel dosyanın tekrar dışa aktarılması gerekir.
- Coupa cevap geçmişinde cevap adı, toplam ve gönderim zamanı görülebilir.
- Oracle aynı turda birden fazla cevap, cevabı revize etme, çoklu tur ve ikinci tur fiyat kontrolü seçeneklerini destekler.
- SAP ve Oracle emsalleri çoklu turu tek satın alma olayı altında ele alır; yeni tur önceki olaydan kopuk yeni iş değildir.

Kaynaklar: [Coupa — Sourcing FAQ](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/sourcing-faq), [Oracle — Response to Negotiations](https://docs.oracle.com/en/cloud/saas/procurement/26c/oaprc/response-to-negotiations.html), [SAP — Guided Sourcing Event Features](https://help.sap.com/docs/strategic-sourcing/managing-events-with-guided-sourcing/guided-sourcing-event-features) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** Mevcut tur geçmişi, tur kilidi ve Excel gel-git; eski tur dosyasının yeni tura sessizce yazılması engellenmelidir.

### 6.2 Ölçülemeyen alan — pratikte kaç tur?

Türkiye’de Çin tedarik aracılarının teklif başına ortalama/medyan revizyon turu sayısını yayımlayan güvenilir bir kaynak bulunamadı. “Genelde iki turda biter” veya “en fazla üç tur” denemez.

**Etkilediği ekran/kural:** Tur sayısı için araştırma kanıtı olmayan sabit üst sınır veya performans hedefi konulmamalıdır.

### 6.3 Yorum — 25 üründen 18’i fiyatlandı, 7 eksik, 3 itirazlı

Mevcut V3-C tur mantığına uygun saha akışı:

1. **Tur 1 kilitlenir:** 25 satırlık gönderilen liste ve gelen cevap değişmeden korunur; 18 fiyatlı, 7 cevapsız/bulunamadı ayrımı görünür.
2. **Tur 2 kapsamı:** Kalan 7 satır ile itiraz edilen 3 satır açıkça yeniden gönderilir; hangi Tur 1 cevabına itiraz edildiği kaybolmaz.
3. **Tur 2 cevabı:** Yalnız bu 10 satıra dönüş gelse bile önceki 15 satırın Tur 1 cevabı sessizce kopyalanıp “Tur 2’de yeniden onaylandı” sayılmaz.
4. **Karşılaştırma:** Ürün bazında en son cevap gösterilebilir; fakat fiyatın hangi turdan geldiği her zaman görülebilir olmalıdır.

Her turda korunması gereken kanıt içeriği:

- Gönderilen liste sürümü ve satır kimlikleri.
- Tur numarası, gönderim ve dönüş zamanı.
- Dönüş kanalı ve ham kaynak dosya/metni.
- Her satırın cevap durumu, DDP+KDV fiyatı, para birimi, miktar/kademe, MOQ, termin ve açıklaması.
- Kur kaynağı, kur değeri, kilit zamanı/süresi ve teklif geçerliliği.
- İtiraz/değişiklik notu ve alternatifin asıl satır bağlantısı.
- Turun gönderilmiş/kilitli hali ve sonraki karar.

Bu liste bir veritabanı şeması önerisi değildir; anlaşmazlıkta aynı teklifin yeniden kurulabilmesi için korunacak iş kanıtıdır.

**Etkilediği ekran/kural:** Tur geçmişi, satır karşılaştırması, Excel içe aktarma ve gönderim kilidi.

## 7. Araştırma sorusu 5 — `Bulunamadı` ve alternatif önerme

### 7.1 Kesin bulgu

Oracle Procurement’da alternatif cevap satırı, alıcının asıl satırına ek olarak oluşturulur; asıl cevap ve alternatif birlikte değerlendirilir. Alternatif farklı fiyat kırılımı, maliyet, özellik veya ölçü birimi taşıyabilir. Oracle ayrıca sınırlı bulunabilirlikte tedarikçinin alternatif ürün önerebilmesini ayrı satırla destekler.  
Kaynaklar: [Oracle — Alternate Lines](https://docs.oracle.com/en/cloud/saas/procurement/25d/oaprc/negotiation-lines.html), [Oracle — Create Alternate Lines](https://docs.oracle.com/en/cloud/saas/readiness/scm/25b/proc25b/25B-procurement-wn-f36587.htm) — gözlem 28.08.2026.

**Etkilediği ekran/kural:** V3-C `Alternatif var` cevabı, asıl satırın ürün/fiyat alanlarını değiştirmemeli; asıl satıra bağlı ayrı alternatif cevap olarak gösterilmelidir.

### 7.2 Yorum — ayrı kayıt mı, asıl satır eki mi?

Doğru desen ikisinin birleşimidir:

- **İşlemsel olarak ayrı cevap:** Alternatifin kendi görseli/linki, açıklaması, DDP fiyatı, MOQ’su, termini ve paket bilgisi vardır.
- **Bağlamsal olarak asıl satıra bağlı:** Neye alternatif olduğu zorunlu olarak bilinir; listede asıl ürünün altında gösterilir.
- **Asıl cevap korunur:** Asıl ürün gerçekten bulunamadıysa asıl satır `Bulunamadı` kalır; alternatif onu `Bulundu`ya çeviremez.
- **Karar ayrıdır:** Alıcı alternatifi kabul etmeden asıl talep yerine geçmiş sayılmaz.

Bu desen, mevcut `Bulunamadı/Alternatif` kararının veri kaybı olmadan uygulanmasıdır; yeni bir satın alma özelliği değildir.

**Etkilediği ekran/kural:** Firma satır cevap formu, alternatif ürün görünümü, tur karşılaştırması ve yapıştır-ayrıştır eşlemesi.

## 8. Kırılma + telafi matrisi

| Kırılma | Saha belirtisi | Mevcut V3-C telafisi | Etkilenen ekran/kural |
|---|---|---|---|
| Firma portalı kullanmıyor | WhatsApp/WeChat mesajı veya kendi Excel’i geliyor | Yapıştır-ayrıştır / Excel içe aktarma | Firma dönüş alma ekranı |
| Satır sırası değişiyor | Cevap tablosu ürünleri yeniden sıralamış | Kimlik/kod/link öncelikli eşleme; belirsizi doğrulamaya bırakma | Ayrıştırma önizlemesi |
| Cevap kısmi | 25 satırdan 18’i dolu | Turu kısmi cevap olarak saklama; boşu `Bulunamadı` yapmama | Tur özeti |
| Boş, sıfır ve tire karışıyor | `0`, `-`, boş hücre, `yok` | Her işareti aynı duruma çevirmeme | Excel/yapıştır ayrıştırma |
| KDV belirsiz | Fiyat var, “dahil/hariç” yok | KDV dahil sonucunu varsaymama | DDP fiyat doğrulaması |
| DDP kapsamı belirsiz | Teslim noktası veya dahil kalemler yazmıyor | DDP+KDV ve teslim noktasını mevcut zorunlu doğrulamaya bırakma | Teklif satırı/tur başlığı |
| Kur şartı belirsiz | TL fiyat var, kur kaynağı/süresi yok | Kilitli kur alanlarını tamamlatmadan kesin fiyat saymama | Kur özeti |
| Kur kilidi tekliften önce bitiyor | Teklif açık, TL dönüşüm süresi dolu | Teklif ile kur süresini ayrı yorumlama; fiyatı yeniletme | Teklif geçerlilik alanı |
| Kademe aralığı belirsiz | Sadece 500/1000/2000 fiyatı yazılmış | 700 için interpolasyon yapmama; yeni turda sorma | Kademeli fiyat |
| Eski Excel yeni tura dönüyor | Firma Tur 1 dosyasını düzenleyip geri yolluyor | Dosyayı ait olduğu tura bağlama; yeni tura sessiz yazmama | Excel gel-git / tur geçmişi |
| Alternatif aslı eziyor | Yeni link asıl ürün hücresine yapıştırılıyor | Alternatifi ayrı cevap olarak asıl satıra bağlama | Alternatif görünümü |
| Mesaj ile belge çelişiyor | WhatsApp fiyatı ile proforma farklı | Kaynağı ve zamanı koruma; yeni olanı eski cevabın üstüne yazmama | Tur karşılaştırması |
| Birden çok para birimi | Ürün USD, DDP TL, navlun ayrı | Para birimi ve fiyat türünü satır bağlamında ayırma | Ayrıştırma / teklif kıyası |
| Firma “bakılıyor” diyor | Fiyat yok, süreç notu var | `Bulunamadı` veya nihai cevap saymama | Satır durumu |

## 9. Yapıştır-ayrıştır köprüsü için sonuç kuralları

Bu maddeler yeni özellik değil, mevcut köprünün saha metnini yanlış yorumlamaması için kabul sınırlarıdır:

1. Ham mesaj/tablo, ayrıştırılmış sonuçtan bağımsız olarak turun kanıtı olarak kalmalıdır.
2. Satır kimliği varsa ad benzerliğinden önce kullanılmalıdır.
3. Boş hücre `cevap yok`; `bulunamadı/yok/not available` ise açık olumsuz cevaptır.
4. `0` fiyat, bağlama göre ücretsiz veya yanıt verilmedi anlamına gelebilir; tek başına `Bulunamadı` değildir.
5. Para birimi açık değilse fiyat kesinleştirilmemelidir.
6. `DDP`, `kapı teslim` ve `her şey dahil` ifadeleri aynı mali kapsam varsayımıyla birleştirilmemelidir; KDV ve teslim noktası ayrıca doğrulanmalıdır.
7. Kademeler eşik/aralık olarak açık değilse ara miktar hesaplanmamalıdır.
8. Alternatif link/görsel ayrı cevap nesnesi olarak asıl satıra bağlanmalı, asıl ürün değişmemelidir.
9. Bir turda yalnız bazı satırlar döndüyse yanıt kısmi kalmalı; eksik satırlar kapanmamalıdır.
10. Eski tur fiyatı yeni turda yalnız karşılaştırma amacıyla görünür; yeni turun cevabıymış gibi çoğaltılmaz.

**Etkilediği ekran/kural:** Yapıştır-ayrıştır doğrulaması, Excel içe aktarma, firma satır cevabı, tur özeti ve geçmiş karşılaştırması.

## 10. Doğrudan karar cevapları

| Soru | Araştırma sonucu |
|---|---|
| Firma hangi formatta döner? | Tek standart kanıtlanmadı. WhatsApp/WeChat/e-posta + rapor/proforma + Excel/CSV birlikte görülen kanal sınıflarıdır. |
| Kısmi dönüş ne sıklıkta? | Türkiye geneli ölçüm yok. Kısmi satır cevabı genel B2B sistemlerinde açıkça desteklenen gerçek bir durumdur. |
| “Bulamadım, benzeri var” nasıl tutulmalı? | Asıl satır `Bulunamadı`; alternatif, asıl satıra bağlı ayrı cevap. Asıl ürün ezilmez. |
| Kur riski kime ait? | DDP belirlemez; açık teklif/sözleşme şartı belirler. Koşulsuz kilit penceresinde teklif veren, pencere sonrasında yeniden hesap şartı varsa alıcı taşır. |
| Teklif ve kur süresi farklıysa? | Ticari geçerlilik ve TL fiyat garantisi ayrı yürür. Değişmeyen TL toplam için ikisi de aktif olmalıdır; açık sözleşme hükmü saklıdır. |
| 700 adet hangi kademeden? | Aralık/eşik açık ise 500 kademesi; yalnız tam miktar teklifleri varsa yeniden fiyat sorulur. Interpolasyon yapılmaz. |
| Tur sayısı kaç olmalı? | Güvenilir saha ortalaması yok; sabit üst sınır önerilemez. Her tur sürüm olarak korunur. |
| 18/25 dönüşte ne yapılır? | Tur 1 kısmi kilitlenir; kalan 7 + itirazlı 3, Tur 2 kapsamı olur. Tur 1 satırları değişmez. |

## 11. Sonuç

V3-C’nin temel kararları saha gerçeğiyle uyumludur; en kritik risk portalın yeteneği değil, portal dışı cevabın anlam kaybetmeden aynı tur sistemine alınmasıdır. Kırılmaların çoğu yeni modül gerektirmiyor:

- WhatsApp/Excel için mevcut yapıştır-ayrıştır köprüsü,
- kısmi cevap için mevcut satır durumları,
- kur ihtilafı için mevcut kilitli kur + teklif geçerliliği,
- ara miktar için mevcut kademeli fiyatın eşik anlamı,
- itirazlar için mevcut tur sürümleme,
- benzer ürün için mevcut `Alternatif` cevabının asıl satıra bağlı ayrı tutulması.

V3-C şartnamesinde kaçınılması gereken dört varsayım şunlardır: her firma portal kullanır; boş satır `Bulunamadı`dır; DDP kur riskini çözer; kademeler arasında fiyat doğrusal hesaplanabilir. Kamuya açık saha ve kurumsal satın alma kanıtları bu dört varsayımı desteklememektedir.

## 12. Kaynak kayıtları

Tüm kaynaklar 28.08.2026 tarihinde gözlemlendi.

### Türkiye öncelikli

1. [Hesnaf Global — Teklif Talep Formu](https://hesnafglobal.com/teklif-talep-formu/)
2. [FimexAsia — Çin’den İthalat ve Tedarik Danışmanlığı](https://cindengetir.com/)
3. [Pentex — Çin’de Danışmanlık ve İthalat Hizmetleri](https://www.gumrukdanismanligi.net/cinde-danismanlik-ve-ithalat-hizmetleri/comment-page-4/)
4. [Shanghai Trimpex — Çin’den Ürün Tedariki Başlangıç Rehberi](https://www.cindenyedekparca.com/news/cinden-%C3%BCr%C3%BCn-tedariki%C4%9F-baslang%C4%B1c-rehberi)
5. [OAİB — Dış Ticarette Kullanılan Faturalar](https://oaib.org.tr/bilgi-ve-operasyon-merkezi/ihracat-belgeleri/dis-ticarette-kullanilan-faturalar)
6. [Resmî Gazete — 6098 sayılı Türk Borçlar Kanunu](https://www.resmigazete.gov.tr/eskiler/2011/02/20110204-1.htm)
7. [Hazine ve Maliye Bakanlığı — Kambiyo duyurusu](https://www.hmb.gov.tr/duyuru/finansal-piyasalar-ve-kambiyo-genel-mudurlugunden-duyuru)
8. [BİRÇELİK — Genel Satış ve Teslimat Koşulları](https://bircelik.com/tr/kategori/genel-satis-kosullari)
9. [Kara Kılınç Hukuk — Kur farkı karar derlemesi](https://www.kilinc.av.tr/yargitay-kararlari-isiginda-kur-farki-alacagi/)

### Genel B2B ve dış ticaret emsalleri

10. [ICC — Incoterms 2020: EXW or DDP?](https://academy.iccwbo.org/incoterms/article/incoterms-2020-exw-or-ddp/)
11. [ICC — Incoterms 2020: New Rules, Old Problems](https://academy.iccwbo.org/incoterms/article/incoterms-2020-new-rules-old-problems/)
12. [Oracle Procurement — Response to Negotiations](https://docs.oracle.com/en/cloud/saas/procurement/26c/oaprc/response-to-negotiations.html)
13. [Oracle Procurement — Negotiation Lines](https://docs.oracle.com/en/cloud/saas/procurement/25d/oaprc/negotiation-lines.html)
14. [Oracle Procurement — Create Alternate Lines](https://docs.oracle.com/en/cloud/saas/readiness/scm/25b/proc25b/25B-procurement-wn-f36587.htm)
15. [Coupa — Sourcing FAQ](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/sourcing-faq)
16. [SAP — Guided Sourcing Event Features](https://help.sap.com/docs/strategic-sourcing/managing-events-with-guided-sourcing/guided-sourcing-event-features)
17. [Alibaba — Tiered Pricing Display](https://seller.alibaba.com/blogs/2026/southeast-asia/bar-wine/moq-requirements-100-500-pieces-guide-alibaba-b2b)

