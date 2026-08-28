# Görev #29 — Navlungo Domestic API fizibilite incelemesi

**Kapsam:** tedarikapp V3-D iç nakliye maliyeti ve ileride müşteri yurtiçi sevkiyatı  
**İnceleme tarihi:** 28 Ağustos 2026  
**İncelenen sürüm:** Domestic API v2.1  
**Belge türü:** Araştırma/fizibilite; şartname veya uygulama kararı değildir. Nihai karar PM’dedir.

## 1. Yönetici sonucu

| Sonuç | Dayanak | Etkilediği modül |
|---|---|---|
| **Gönderi oluşturmadan fiyat veren bir v2.1 API ucu bulunamadı.** | V2.1 içindekiler ve resmi Postman koleksiyonunda token, gönderi, adres, taşıyıcı ve barkod uçları var; `rate`, `quote` veya fiyat teklifi ucu yok. | V3-D landed cost |
| `post.price` taşıma ücreti değildir. | Gönderi oluşturma belgesinde bu alan “kapıda ödeme tutarı” olarak tanımlanır. | İkisi |
| Oluşturulmuş gönderi sorgusunda `post_price` ve `calculated_price` görülür; anlamları ayrıca tanımlanmamıştır. | Alanlar `post/check` örnek cevabındadır, oluşturma isteği/cevabında taşıma teklifi olarak sunulmaz. | İkisi |
| Web panelinde teklif karşılaştırma bulunduğu kamuya açık olarak ifade edilir. | Domestic sayfası, taşıyıcıların teslimat türü/fiyat/lokasyona göre seçilebildiğini; kullanıcı sözleşmesi ise hizmet talebi ardından tekliflerin listelendiğini söyler. | İkisi |
| V2.1, **sevkiyat yürütme ve takip** için geniş bir yüzey sunar. | Oluşturma, güncelleme, sorgu, iptal, iade alımı, adres defteri, taşıyıcı ve barkod uçları belgelenmiştir. | Müşteri sevkiyatı |

**Fizibilite yorumu:** Kamuya açık kanıtlarla Navlungo Domestic, V3-D’de “gönderi yaratmadan iç nakliye fiyatı üretme kaynağı” olarak henüz doğrulanmış değildir. Aynı ürün, müşteri sevkiyatının oluşturma/takip/iade tarafı için teknik adaydır. Bu iki kullanım amacı ayrı değerlendirilmelidir.

**TedarikApp’te etkilediği modül:** ikisi.

## 2. Yöntem ve kanıt sınırı

### 2.1 İncelenen birincil kaynaklar

- [Domestic API v2.1 içindekiler ve ortamlar](https://domestic-docs.navlungo.com/tr/v2-1)
- [Domestic API v2.1 Postman koleksiyonu](https://domestic-docs.navlungo.com/Domestic-v2.1.json)
- V2.1’in gönderi, adres, taşıyıcı, barkod ve hata kodu alt sayfaları
- [Navlungo Domestic ürün sayfası](https://navlungo.com/domestic)
- [Navlungo kullanıcı ve üyelik sözleşmesi](https://navlungo.com/kullanici-sozlesmesi)
- Alternatif sağlayıcıların kendi ürün/API belgeleri

**TedarikApp’te etkilediği modül:** ikisi.

### 2.2 Kanıt sınıfları

| İşaret | Anlam | Etkilediği modül |
|---|---|---|
| **Kesin bulgu** | Kamuya açık resmi belge, API örneği veya sağlayıcının kendi ürün metninde açıkça yer alır. | İkisi |
| **Doğrulanamadı** | İncelenen açık kaynaklarda cevap yoktur veya metin uygulama davranışını kanıtlamaz. | İkisi |
| **Yorum** | Bulguların tedarikapp kullanımına etkisidir; Navlungo taahhüdü değildir. | İkisi |

## 3. Kritik soru: gönderi oluşturmadan fiyat sorgusu var mı?

### 3.1 Kesin bulgular

| Bulgu | Kaynak/kanıt | Etkilediği modül |
|---|---|---|
| V2.1 içindekilerde bağımsız fiyat/teklif ucu yoktur. | [V2.1](https://domestic-docs.navlungo.com/tr/v2-1) yalnız token; gönderi oluşturma, iade, güncelleme, sorgu, iptal; adres; taşıyıcı ve barkod gruplarını listeler. | V3-D landed cost |
| Resmi Postman koleksiyonunda da fiyat/teklif isteği yoktur. | [Domestic-v2.1.json](https://domestic-docs.navlungo.com/Domestic-v2.1.json) içindeki istek envanteri dokümanla aynıdır; `quote`, `rate` ve `webhook` isteği bulunmaz. | V3-D landed cost |
| Gönderi oluşturma için `POST post/create` çağrılır. | [Gönderi oluşturma](https://domestic-docs.navlungo.com/tr/v2-1/posts/create-post) | Müşteri sevkiyatı |
| Oluşturma isteğindeki `post.price`, navlun/kargo fiyatı değil kapıda ödeme tutarıdır. | Aynı belgede alan açıklaması “Kapıda ödeme tutarı”; yalnız COD türü 1 veya 2 seçilince gönderilir. | İkisi |
| Başarılı oluşturma örnek cevabı gönderi ve takip/barkod bilgisi verir; taşıma ücreti alanı göstermez. | [Gönderi oluşturma örnek cevabı](https://domestic-docs.navlungo.com/tr/v2-1/posts/create-post) | V3-D landed cost |
| Mevcut gönderiyi sorgulayan `GET post/check/{post_number|reference_id}` örneğinde `post_price`, `calculated_price`, `measured_dominant_weight` ve `measured_box_count` alanları vardır. | [Gönderi sorgulama](https://domestic-docs.navlungo.com/tr/v2-1/posts/check-post) | İkisi |
| `post_price` ile `calculated_price` alanlarının sözlük tanımı, KDV durumu, para birimi ve hangi aşamada kesinleştiği belgede açıklanmaz. | Aynı sayfanın “Servis Çıktısı Anlamları” bölümünde bu iki alan tanımlanmamıştır. | İkisi |
| Genel ürün sayfası panelde teslimat türü, fiyat ve lokasyona göre taşıyıcı seçilebildiğini söyler. | [Navlungo Domestic](https://navlungo.com/domestic) | İkisi |
| Kullanıcı sözleşmesi, kullanıcının hizmet talebi oluşturup taşıyanların ücret tekliflerini platformda listeleyerek seçim yapabildiğini belirtir. | [Kullanıcı ve üyelik sözleşmesi, Madde 3](https://navlungo.com/kullanici-sozlesmesi) | İkisi |
| Eski v2 sayfası, v2 kullanımının 1 Ocak 2025’te sona ereceğini belirtir; sayfadaki Postman bağlantısı 404 döner. | [Eski v2 sayfası](https://domestic-docs.navlungo.com/tr/v2) | İkisi |

### 3.2 Doğrulanamayanlar

| Konu | Durum | Etkilediği modül |
|---|---|---|
| Paneldeki teklif sorgusunun API ile erişilebilen, belgelenmemiş özel bir ucu var mı? | **Doğrulanamadı.** | V3-D landed cost |
| `post_price` tahmini fiyat mı, hesap tarifesi mi, faturalanan tutar mı? | **Doğrulanamadı.** | İkisi |
| `calculated_price` ile `post_price` farklıysa hangisi borç/fatura tutarıdır? | **Doğrulanamadı.** | İkisi |
| Bu alanlara KDV ve ek hizmet bedelleri dahil mi? | **Doğrulanamadı.** | İkisi |
| Sözleşme tarifesi API istemcisince önceden indirilebiliyor mu? | **Doğrulanamadı.** | V3-D landed cost |
| Teklifin geçerlilik süresi veya fiyat rezervasyonu var mı? | **Doğrulanamadı.** | V3-D landed cost |

### 3.3 Fizibilite yorumu

1. Panelde teklif karşılaştırması bulunması, aynı yeteneğin v2.1 API’de bulunduğunu kanıtlamaz. V3-D için gerekli olan “yan etkisiz fiyat sorgusu” kamuya açık API yüzeyinde yoktur.  
   **TedarikApp’te etkilediği modül:** V3-D landed cost.

2. Sırf fiyat öğrenmek için gerçek gönderi oluşturmak; iptal edilecek operasyon kaydı, barkod/taşıyıcı işlemi ve olası faturalama riski doğurabileceğinden fiyat sorgusu yerine kabul edilmemelidir.  
   **TedarikApp’te etkilediği modül:** V3-D landed cost.

3. `post_price` ve `calculated_price`, sağlayıcı yazılı olarak anlamlarını teyit etmeden “teklif” veya “kesin maliyet” adıyla eşlenemez.  
   **TedarikApp’te etkilediği modül:** ikisi.

## 4. İş modeli

### 4.1 Kesin bulgular

| Bulgu | Kaynak/kanıt | Etkilediği modül |
|---|---|---|
| QA erişimi için QA portalında üyelik oluşturmak, firma kaydını tamamlamak ve Entegrasyonlar bölümünden API bilgilerini almak gerekir. | [Token oluşturma](https://domestic-docs.navlungo.com/tr/v2-1/create-token) | İkisi |
| Genel üyelik sözleşmesi, kullanıcıyı kişi ve/veya firma olarak tanımlar; kişi/unvan, MERSİS/T.C. kimlik, vergi dairesi/no gibi alanlar içerir. | [Kullanıcı sözleşmesi](https://navlungo.com/kullanici-sozlesmesi) | İkisi |
| Domestic pazarlama metni kargo şirketleriyle ayrı anlaşma gerekmediğini söyler. | [Navlungo Domestic](https://navlungo.com/domestic) | İkisi |
| Domestic sayfasındaki kamuya açık SSS verisi, ürün kullanımının ücretsiz; ücretin gönderi başına olduğunu belirtir. | [Navlungo Domestic](https://navlungo.com/domestic) | İkisi |
| Aynı SSS verisi faturaların Domestic üzerinden düzenlenip takip edildiğini ve uygun belgelerle vadeli cari açılabileceğini belirtir. | [Navlungo Domestic](https://navlungo.com/domestic) | İkisi |
| API’de `carrier/my-carriers`, hesaba atanan veya “Kargo Anlaşması” olarak eklenen taşıyıcıları döndürür. | [Kayıtlı taşıyıcılarım](https://domestic-docs.navlungo.com/tr/v2-1/carriers/my-carriers) | Müşteri sevkiyatı |
| Gönderi oluşturma belgesinde Sürat, HepsiJet, Kolay Gelsin, Scotty, Aras, PTT, Hepsijet XL ve Yurtiçi Kargo kimlikleri listelenir; `carrier_id=1` otomatik/kapsam alanına göre seçimdir. | [Gönderi oluşturma](https://domestic-docs.navlungo.com/tr/v2-1/posts/create-post) | Müşteri sevkiyatı |

### 4.2 Doğrulanamayanlar

| Konu | Durum | Etkilediği modül |
|---|---|---|
| Domestic API üretim hesabı yalnız şirketlere mi açıktır; şahıs hesabı kabul edilir mi? | **Doğrulanamadı.** QA belgesi “firma kaydı” ister; genel sözleşmenin kişi/firma kapsaması API uygunluğunu kanıtlamaz. | İkisi |
| İstenen şirket türü, vergi levhası ve imza/sözleşme belgelerinin kesin listesi | **Doğrulanamadı.** | İkisi |
| Üretim erişimi için ayrıca satış/onay süreci veya hacim taahhüdü var mı? | **Doğrulanamadı.** | İkisi |
| Ön yüklü bakiye/kredi modeli kullanılıyor mu? | **Doğrulanamadı.** Kamuya açık Domestic metni fatura ve olası vadeli cariyi açıklar, bakiye modelini açıklamaz. | İkisi |
| Fatura kesim dönemi, vade, teminat ve gecikme hükümleri | **Doğrulanamadı.** | İkisi |
| API hesabına hangi taşıyıcıların fiilen atanacağı ve her birinin bölge/COD/iade kapsamı | Hesaba bağlıdır; üretim hesabı açılmadan **doğrulanamadı**. | Müşteri sevkiyatı |

### 4.3 Fizibilite yorumu

Kamuya açık iş modeli, “Navlungo’nun anlaşmaları üzerinden gönderi başı ücret + faturalama” görünümü verir. Ancak tedarikapp bütçe/fatura akışına yazılacak tarife, KDV, vade ve mutabakat davranışı ticari teyit gerektirir.

**TedarikApp’te etkilediği modül:** ikisi.

## 5. Teknik yüzey

### 5.1 Ortam ve kimlik doğrulama

| Kesin bulgu | Ayrıntı | Etkilediği modül |
|---|---|---|
| QA ve üretim taban URL’leri ayrıdır. | QA: `https://domestic-api-qa.navlungo.com/v2.1/`; prod: `https://domestic-api.navlungo.com/v2.1/`. | İkisi |
| Token kullanıcı adı/şifreyle alınır. | `POST auth/api`; cevap Bearer `access_token` ve `expires_in` içerir. | İkisi |
| Token ömrü 8 saattir. | Süre dolunca aynı uçtan yeni token alınması gerektiği açıkça yazılır. | İkisi |
| Ayrı refresh-token ucu belgelenmemiştir. | V2.1 içindekiler ve Postman’da refresh isteği yoktur. | İkisi |

### 5.2 Gönderi yaşam döngüsü

| İşlem | Uç ve kesin davranış | Etkilediği modül |
|---|---|---|
| Oluştur | `POST post/create`; standart (`post_type=2`) ve aynı gün (`1`). Gönderici `addressId` ile seçilir. | Müşteri sevkiyatı |
| Sorgula | `GET post/check/{post_number|reference_id}`; durum, taşıyıcı, takip, barkod, ölçüm ve logları döndürür. | İkisi |
| Çok ölçütlü sorgula | `POST post/check`; numara/referans ve gönderici-alıcı adı, telefon, e-posta ile arar; en fazla 50 sonuç. | Müşteri sevkiyatı |
| Güncelle | `POST post/update`; yalnız “Önizleme” veya “Teslim Alınacak” durumunda; `inProgress=1` iken reddedilir. | Müşteri sevkiyatı |
| İptal | `POST post/cancel`; yalnız “Önizleme” veya “Teslim Alınacak” durumunda; `inProgress=1` iken reddedilir. | Müşteri sevkiyatı |
| İade alımı | `POST post/return`; `post_type=3`; dönüş deposu `recipient.addressId` ile verilir ve taşıyıcı iade desteklemelidir. | Müşteri sevkiyatı |
| Adres | Oluşturma, güncelleme, listeleme, detay ve silme uçları vardır; gönderici/alıcı türü tutulur. | Müşteri sevkiyatı |
| Taşıyıcı | Tüm aktif taşıyıcılar ve hesaba atanmış taşıyıcılar ayrı uçlarla listelenir; cevapta hizmet türleri ve COD desteği bulunur. | Müşteri sevkiyatı |

Kaynaklar: [oluşturma](https://domestic-docs.navlungo.com/tr/v2-1/posts/create-post), [sorgulama](https://domestic-docs.navlungo.com/tr/v2-1/posts/check-post), [detaylı sorgulama](https://domestic-docs.navlungo.com/tr/v2-1/posts/check-post-multiple), [güncelleme](https://domestic-docs.navlungo.com/tr/v2-1/posts/update-post), [iptal](https://domestic-docs.navlungo.com/tr/v2-1/posts/cancel-post), [iade](https://domestic-docs.navlungo.com/tr/v2-1/posts/return-post).

**TedarikApp’te etkilediği modül:** ikisi.

### 5.3 Push, polling, barkod ve hatalar

| Kesin bulgu | Sınır/yorum | Etkilediği modül |
|---|---|---|
| Sorgu uçlarıyla polling mümkündür. | Tekil ve çok ölçütlü sorgu açıkça belgelenmiştir. | Müşteri sevkiyatı |
| Sorgu loglarında `webhook_delivered`, `webhook_in_transit` gibi aksiyon adları vardır. | Bunlar Navlungo’nun taşıyıcıdan aldığı olay adları olabilir; müşterinin webhook URL’si kaydetmesini veya Navlungo’dan push almasını kanıtlamaz. | Müşteri sevkiyatı |
| Müşteriye yönelik webhook kayıt/secret/retry sözleşmesi belgelenmemiştir. | Resmi içindekiler ve Postman koleksiyonunda webhook ucu yoktur; **push desteği doğrulanamadı**. | Müşteri sevkiyatı |
| Barkod sonradan `POST barcode/getBarcode` ile üretilebilir. | Belgelenen tipler `pdf`, `zpl`, `zpl-10`; sayfa ZPL isteklerinde dahi PDF/base64 çıktısı tarif eder ve taşıyıcıya göre kısıt koyar. | Müşteri sevkiyatı |
| Oluşturma/güncelleme sayfalarında farklı barkod format listeleri görülür. | `pdf-A5`, koleksiyonda ayrıca `html`/`zpl`; güncelleme yorumunda `pdf-A6`, `pdf-A6Y`, `pdf-A7` geçer. Format sözleşmesi tutarsızdır. | Müşteri sevkiyatı |
| Genel HTTP hata kataloğu 400, 401, 404, 405, 422 ve 500’dür. | Doğrulama hatalarında alan bazlı dizi; 500 örneklerinde destek için `eventId` bulunur. | İkisi |
| `reference_id` tekrarında 422 örneği vardır. | Ayrı bir idempotency header/anahtarı belgelenmemiştir. | Müşteri sevkiyatı |
| Oran limiti, kota ve `Retry-After` davranışı açıklanmaz. | **Doğrulanamadı.** | İkisi |

Kaynaklar: [barkod](https://domestic-docs.navlungo.com/tr/v2-1/barcode/get-barcode), [hata kodları](https://domestic-docs.navlungo.com/tr/error-codes), [sorgu ve webhook aksiyon adları](https://domestic-docs.navlungo.com/tr/v2-1/posts/check-post).

**TedarikApp’te etkilediği modül:** ikisi.

## 6. Veri eşlemesi

### 6.1 Kesin alan eşlemesi ve boşluklar

| Navlungo alanı/yüzeyi | Tedarikapp karşılığı | Durum | Etkilediği modül |
|---|---|---|---|
| `reference_id` | Tedarikapp sipariş/sevkiyat dış referansı | Uygun; benzersizlik gerekir. | Müşteri sevkiyatı |
| `carrier_id`, `post_type` | Taşıyıcı ve hizmet türü | Uygun; kullanılabilir taşıyıcı hesap atamasına bağlıdır. | Müşteri sevkiyatı |
| `sender.addressId` | Depo/çıkış adresi | Önce Navlungo adres defteri kaydı gerekir. | Müşteri sevkiyatı |
| Alıcı adı, telefon, adres, ülke, il, ilçe | Müşteri teslimat adresi | Temel alanlar doğrudan örtüşür. | Müşteri sevkiyatı |
| Alıcı e-posta ve posta kodu | Teslimat yardımcı alanları | API’de opsiyoneldir. | Müşteri sevkiyatı |
| Adres enlem/boylam, geocode durumu ve hatalı adres bayrağı | Adres kalite sonucu | Cevapta görülür; gönderi oluşturma girdisindeki kullanımı tüm sayfalarda tutarlı değildir. | Müşteri sevkiyatı |
| `post.desi` | Desi/dominant weight | Tek ondalık alan; doküman bunu “gönderinin ağırlığı” diye adlandırır. Kg ve desi ayrımı belirsizdir. | İkisi |
| `post.package_count` | Koli/paket adedi | Doğrudan örtüşür. | İkisi |
| Uzunluk × genişlik × yükseklik | Tedarikapp koli ölçüleri/CBM | V2.1 oluşturma isteğinde ayrı ölçü alanları yoktur; doğrudan örtüşme yoktur. | İkisi |
| CBM → desi dönüşümü | Tedarikapp maliyet girdisi | Bölen, yuvarlama ve taşıyıcı farkı API belgesinde yoktur; **doğrulanamadı**. | İkisi |
| `post.price` | Kapıda ödeme tahsilat tutarı | İç nakliye maliyetine eşlenmemelidir. | İkisi |
| `post_price`, `calculated_price` | Olası hesaplanan/işlenen taşıma bedeli | Alanlar var; semantik, KDV ve kesinleşme anı **doğrulanamadı**. | İkisi |
| `measured_dominant_weight`, `measured_box_count` | Taşıyıcı ölçümü/mutabakat girdisi | Sorgu örneğinde vardır; maliyet farkının kuralı açıklanmaz. | İkisi |
| `post_number`, taşıyıcı takip kodu/URL’si, barkod URL’si | Operasyon ve müşteri takip bilgisi | Doğrudan kullanılabilir yüzey vardır. | Müşteri sevkiyatı |
| Durum kodu, alınma/teslim/iptal tarihleri, loglar | Sevkiyat zaman çizelgesi | Geniş durum seti belgelenmiştir. | Müşteri sevkiyatı |
| Tahmini teslim süresi | Müşteriye ETA | Sorgu açıklaması ETA’dan söz eder; örnek cevapta ayrı ETA alanı gösterilmez. **Dönen alan doğrulanamadı.** | Müşteri sevkiyatı |
| `custom_data_1..4` | Dahili ilişkilendirme alanları | Dört opsiyonel alan vardır; uzunluk/karakter sınırı belgelenmemiştir. | Müşteri sevkiyatı |

### 6.2 Fizibilite yorumu

Navlungo v2.1’in paket girdisi, tedarikapp’in CBM/koli ölçüsü modelinden daha dardır. Doğrudan entegrasyon değerlendirmesinde desi/kg anlamı, ölçü dönüşümü, KDV ve ölçüm sonrası fiyat farkı yazılı olarak netleştirilmeden landed-cost eşlemesi güvenilir kabul edilemez.

**TedarikApp’te etkilediği modül:** ikisi.

## 7. Alternatif kısa tarama — kriter karşılaştırması

Bu bölüm sıralama veya sağlayıcı önerisi değildir. Yalnız Navlungo’daki kritik fiyat sorgusu boşluğunu aynı sınıftaki kamuya açık API yüzeyleriyle karşılaştırır.

| Sağlayıcı | Gönderi oluşturmadan/önce fiyat | Taşıyıcı kapsam modeli | Webhook/test | Kamuya açık ödeme işareti | Etkilediği modül |
|---|---|---|---|---|---|
| **Navlungo Domestic v2.1** | Bağımsız rate/quote ucu bulunamadı. Panelde teklif karşılaştırma ifade edilir; API’de ancak mevcut gönderi sorgusunda fiyat benzeri alanlar görünür. | Hesaba atanan veya kargo anlaşması eklenen taşıyıcılar; oluşturma belgesinde sekiz taşıyıcı kimliği. | QA var; müşteriye push webhook doğrulanamadı. | Gönderi başı ücret, fatura ve belgeyle olası vadeli cari; bakiye doğrulanamadı. | İkisi |
| **Geliver** | Resmi ürün sayfası anlık fiyat teklifleri alıp en uygun seçeneği seçmeyi API özelliği olarak açıklar. | Geliver fiyatları veya kullanıcının kendi anlaşması; 10+ taşıyıcı ifadesi. | Webhook desteği ve OpenAPI/SDK’lar açıklanır. | Ücretsiz kayıt/API token; ayrı kargo anlaşması gerekmeyebilir. Ayrıntılı tahsilat akışı bu kısa taramada incelenmedi. | İkisi |
| **Kargonomi** | `GET shipment-price-comparison/{id}` fiyatları döndürür; önce taslak gönderi kaydı kimliği gerekir, taşıyıcı seçimi `confirm-shipping-price` ile sonradan yapılır. | Çoklu taşıyıcı cevabı; hizmet dışı bölge sonucu da örneklenir. | API belgesi var; webhook/test ayrıntısı bu kısa taramada doğrulanmadı. | `GET user/credit` bakiye sorgusu belgelenmiştir. | İkisi |
| **Shipink** | `POST /rates`, paket ölçüleri ve alıcı/depo bilgisiyle sevkiyat oluşturmadan tahmini fiyat, TRY ve ETA döndürür. | 15+ taşıyıcı beyanı; tek normalize API. | Webhook ve ayrı test ortamı belgelenmiştir. | API tüm planlarda; ücret gönderi başına olarak açıklanır. | İkisi |

Kaynaklar: [Geliver Kargo API](https://geliver.io/kargo-api), [Kargonomi API](https://www.kargonomi.com.tr/help/api-dokumantasyonu/kargonomi-api/), [Shipink API](https://docs.shipink.io/api), [Shipink Rates](https://docs.shipink.io/api/rates).

**TedarikApp’te etkilediği modül:** ikisi.

### 7.1 Seçme/seçmeme kriterleri — sıralamasız

| Kriter | Navlungo için kapanması gereken soru | Kabul kanıtı | Etkilediği modül |
|---|---|---|---|
| Yan etkisiz fiyat sorgusu | Belgelenmiş rate/quote ucu sağlanacak mı? | QA’da gönderi kaydı/barkod/fatura oluşturmadan taşıyıcı bazlı fiyat cevabı | V3-D landed cost |
| Fiyat semantiği | Tahmini, hesaplanan ve faturalanan tutar nasıl ayrılıyor? | Alan sözlüğü + örnek fatura mutabakatı | İkisi |
| Vergi ve ek ücret | KDV, COD komisyonu, uzak bölge ve ölçüm farkı hangi alanlarda? | Yazılı tarife/cevap şeması | İkisi |
| Paket ölçüsü | Kg, desi, CBM dönüşümü ve yuvarlama nedir? | Taşıyıcı bazlı yazılı hesap kuralı veya ölçü alanlı API | İkisi |
| ETA | Müşteriye gösterilebilir alan gerçekten dönüyor mu? | QA cevabında belgelenmiş ETA alanı | Müşteri sevkiyatı |
| Olay bildirimi | Müşteri webhook’u var mı; imza ve retry davranışı nedir? | Webhook kayıt belgesi ve QA olayı | Müşteri sevkiyatı |
| Dayanıklılık | Kota, rate limit, idempotency ve retry sınırları nedir? | Teknik işletim belgesi | İkisi |
| Ticari erişim | Şirket belgesi, sözleşme, taşıyıcı ataması, fatura/vade nedir? | Navlungo’nun yazılı ticari teklifi/sözleşmesi | İkisi |

## 8. PM için açık sorular

1. Navlungo, paneldeki Domestic teklif motoruna üretim destekli bir API ucu sağlayacak mı? İstek alanları ve örnek cevap nedir?  
   **TedarikApp’te etkilediği modül:** V3-D landed cost.

2. `post_price` ile `calculated_price` tam olarak neyi, hangi para birimi/KDV durumunda ve hangi yaşam döngüsü anında gösterir?  
   **TedarikApp’te etkilediği modül:** ikisi.

3. Gerçek gönderi oluşturmadan fiyat alınamıyorsa sözleşme tarifesi makinece okunabilir biçimde veriliyor mu ve güncelleme nasıl bildirilir?  
   **TedarikApp’te etkilediği modül:** V3-D landed cost.

4. Desi alanı dominant ağırlık mı, fiziksel kg mı; çok kolide ölçü ve yuvarlama nasıl yapılır?  
   **TedarikApp’te etkilediği modül:** ikisi.

5. Fatura hangi fiyat alanına göre kesilir; ölçülen desi farkı ve itiraz/mutabakat kaydı nasıl sunulur?  
   **TedarikApp’te etkilediği modül:** ikisi.

6. Müşteri sistemine push veren webhook var mı; yoksa önerilen polling sıklığı ve kota nedir?  
   **TedarikApp’te etkilediği modül:** müşteri sevkiyatı.

7. Üretim API hesabı için gerekli şirket/vergi belgeleri, sözleşme, ödeme tipi ve atanacak taşıyıcılar nelerdir?  
   **TedarikApp’te etkilediği modül:** ikisi.

## 9. Son fizibilite notu

Navlungo Domestic v2.1’in açık belgeleri, **operasyon API’si** olduğunu güçlü biçimde gösterir; ancak V3-D’nin kritik ihtiyacı olan **gönderi oluşturmadan taşıyıcı bazlı fiyat sorgusunu** göstermemektedir. PM kararı öncesindeki belirleyici kanıt, Navlungo’dan alınacak belgelenmiş fiyat ucu veya güvenilir/makinece işlenebilir tarife ve fiyat-mutabakat açıklamasıdır.

**TedarikApp’te etkilediği modül:** ikisi.
