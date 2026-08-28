# TedarikApp Müşteri Portalı V2

## Araştırma, ürün mimarisi ve arayüz şartname taslağı

**Sürüm:** 1.0  
**Gözlem tarihi:** 28 Ağustos 2026  
**Kapsam:** Halka açık tanıtım, işletme hesabı başvurusu, yönetici onayı, özel/ön sipariş müşteri çalışma alanı, güvenlik ve içerik ilkeleri  
**Teslim eki:** `musteri-paneli-demo.html`

> Bu belge nihai teknik şartname veya hukuk görüşü değildir. Ürün kararlarını, emsal gözlemlerini ve kabul ölçütlerini PM, tasarım ve geliştirme ekibinin nihai şartnamesine girdi olacak şekilde ayırır.

---

## 1. Yönetici özeti

Müşteri tarafında **kişiye özel kalıcı link + 6 haneli anahtar** modelinden **gerçek işletme hesabı** modeline geçilmesi doğru yöndedir. Çünkü yeni hedef sadece davet edilen kişinin bir listeye yanıt vermesi değil; yeni işletmelerin TedarikApp'i bulması, kendini tanıtması, istek bırakması ve zaman içinde aynı çalışma alanına dönmesidir.

Ancak sistem açık bir e-ticaret mağazasına dönüşmemelidir. Önerilen çekirdek ilke:

> **Kayıt herkese açık; ticari içerik yalnız doğrulanmış ve onaylanmış işletmelere açık.**

Bu nedenle ziyaretçi landing sayfasını ve başvuru formunu görebilir; fakat yönetici onayına kadar ürün, fiyat, teklif, liste, belge veya müşteri geçmişi göremez. Bu kural yalnız arayüzde saklama değildir: API, dosya çıktısı, ön yükleme verisi, arama, hata mesajı, analiz olayı ve önbellek katmanlarında da uygulanmalıdır.

Önerilen deneyim dört ayrı yüzeyden oluşur:

1. **Tanıtım yüzeyi:** Değer önerisi, üç adımlı çalışma biçimi, güven açıklaması, SSS ve kayıt/giriş çağrıları.
2. **Hesap ve başvuru yüzeyi:** Kayıt, e-posta doğrulama, işletme bilgileri ve başvuru takibi.
3. **Onay bekleme yüzeyi:** Referans numarası, durum, sıradaki adım, eksik bilgi talebi ve iletişim.
4. **Onaylı müşteri çalışma alanı:** Teklifler, istek listeleri, dekont bildirimleri, belgeler ve geçmiş.

Bu ayrım güvenlik kadar satış psikolojisini de iyileştirir: ziyaretçiye “mağaza” değil, **özel tedarik masası**; müşteriye “satın al” değil, **ön sipariş niyeti ve teklif üzerinde kontrollü iş birliği** sunulur.

---

## 2. Kesin kararlar ve vizyon ayrımı

### 2.1 Kesin karar / şartname girdisi

| Konu | Karar |
|---|---|
| Erişim | Üye ol, giriş yap ve şifremi unuttum akışları olan gerçek hesap sistemi |
| Kayıt | İşletme hesabı başvurusu herkese açık |
| Ticari görünürlük | Yönetici onayına kadar hiçbir ürün, fiyat, teklif veya liste görünmez |
| Hesap türü | Bireysel tüketici hesabı değil, işletme hesabı |
| Kanal | Özel/ön sipariş; stoktan perakende satış değil |
| Landing | Herkese açık satış ve açıklama sayfası |
| E-posta | Yalnız zorunlu işlemsel hesap e-postaları; pazarlama/bülten/yeni teklif bildirimi yok |
| Ödeme | Panelde sanal POS veya tahsilat yok; yalnız kapora dekontu bildirimi |
| Teklif yanıtı | İlgileniyorum / kararsızım / istemiyorum + miktar + not; gönderilen liste değişmez, yanıt ayrı katmandır |
| İstek listesi | Müşteri link, fotoğraf ve açıklamayla “şunu getir” isteği oluşturabilir |
| Gizli alanlar | Maliyet, kâr, kaynak site/link ve tedarikçi adı hiçbir müşteri yüzeyinde görünmez |
| Fiyat | Liste düzeyinde TL/USD ve KDV dahil/hariç tanımı |
| Çıktı | HTML/PDF/Excel/yazdırma; işletme anteti, aynı müşteri süzgeci, seçilen dilde tamamen tek dil |

### 2.2 Vizyon — bu sürümün zorunlu kapsamı değil

- Bir işletmeye birden fazla kullanıcı ekleme; şirket sahibi, satın almacı ve salt-okuyucu rolleri.
- Passkey ve isteğe bağlı MFA.
- Başvurularda risk sinyallerine göre otomatik önceliklendirme; nihai onay yine insan kararı.
- Müşterinin istek listesine ekip içi yorum veya görev ataması.
- Onaylı şirket için birden fazla teslimat/fatura profili.
- Teklif karşılaştırma ve yeniden sipariş şablonları.

Bu maddeler çekirdek modelin genişletilebilirliğini gösterir; ilk sürümün menüsünü veya formunu şişirmemelidir.

---

## 3. Neden hesap + onay modeli?

### 3.1 İş hedefiyle uyum

Kalıcı link modeli kontrollü bir tek liste paylaşımında etkilidir; fakat organik müşteri kazanımı, işletme geçmişi, birden fazla istek ve uzun süreli ilişki için yetersizdir. Hesap modeli şu sorunları çözer:

- Ziyaretçiden işletme başvurusu alma.
- Aynı işletmenin teklif, istek, dekont ve geçmişini tek bağlamda toplama.
- Teklif listesini kişisel bir URL'ye değil, doğrulanmış şirket erişimine bağlama.
- Rakip veya doğrulanmamış kullanıcının fiyatlara erişimini sunucu tarafında engelleme.
- Müşterinin tekrar geldiğinde “hangi linkti?” sorusunu ortadan kaldırma.

### 3.2 Emsal desteği

- Shopify B2B'de halka açık şirket hesabı talep formu kullanılabilir; başvuru şirket/müşteri kaydı oluşturur ve varsayılan olarak yönetici onayına kadar B2B fiyatlarına veya siparişe erişim vermez. Bu, TedarikApp için en yakın “açık başvuru + kapalı ticari içerik” desenidir. [Shopify — company account requests](https://help.shopify.com/en/manual/b2b/companies-and-customers/company-account-requests)
- Faire, perakendeci erişimini işletme doğrulamasına bağlar ve doğrulama sırasında ek işletme bilgisi isteyebilir. Bize uyan taraf işletme meşruiyetinin kontrolüdür; uymayan taraf, TedarikApp'in bir pazar yeri değil özel tedarik hizmeti olmasıdır. [Faire — retailer verification](https://www.faire.com/support/articles/360035578692), [Faire — pending verification](https://www.faire.com/support/articles/4415920212123)
- Ankorstore toptan fiyatları ve sipariş erişimini kayıtlı profesyonel perakendecilere bağlar. Bize uyan desen, halka açık tanıtım ile korumalı ticari katmanın ayrılmasıdır. [Ankorstore — starting as a retailer](https://support.ankorstore.com/articles/3353966746-starting-as-a-retailer)
- WooCommerce B2B çözümleri rol bazlı fiyat, fiyat/ürün gizleme, teklif isteme ve manuel hesap onayı desenlerini bir arada sunar. Bize uyan kısım rol ve onay kapısıdır; genel katalog/sepete dayalı mağaza modeli uymamaktadır. [WooCommerce — B2B for WooCommerce](https://woocommerce.com/document/b2b-for-woocommerce/), [WooCommerce — B2B Wholesale Suite](https://woocommerce.com/document/b2b-wholesale-suite/)

**Çıkarım:** Emsaller “gizli fiyat + işletme doğrulama” modelini destekliyor. TedarikApp'in farkı, onay sonrasında bile klasik katalog/checkout değil; teklif, niyet beyanı ve özel tedarik akışı sunmasıdır.

---

## 4. Bilgi mimarisi

### 4.1 Halka açık landing — menü

Maksimum beş bağlantı:

1. Nasıl çalışır
2. Güvenli erişim
3. Sık sorulanlar
4. Giriş yap
5. İşletme hesabı oluştur

Landing üzerinde ürün kataloğu, fiyat listesi, stok sayısı veya “hemen satın al” bulunmaz.

### 4.2 Onaylı müşteri paneli — maksimum 6 ana menü

| Menü | Ana amaç | Birincil içerik | Boş durum | Hata durumu |
|---|---|---|---|---|
| Genel Bakış | Müşteriye bugünkü durumu ve sıradaki işi göstermek | Aktif teklifler, taslak istek, dekont durumu, son hareketler | “Henüz açık bir işlem yok. Yeni bir istek listesi oluşturabilirsiniz.” | Veriler alınamazsa kartların yerine yeniden deneme ve destek referansı |
| Teklifler | Gönderilen özel teklifleri incelemek ve niyet bildirmek | Liste kartları, son tarih, para/KDV türü, ürün yanıtları, liste onayı | “Size gönderilmiş bir teklif yok.” | Teklif kapsamı doğrulanamazsa içerik hiç gösterilmez; güvenli hata ekranı |
| İstek Listelerim | “Şunu getir” talepleri oluşturmak ve izlemek | Link/foto/açıklama, taslak/gönderildi/inceleniyor durumu | Örneklerle yönlendiren ilk istek çağrısı | Yükleme/form hatası alan yanında açık düzeltme metni |
| Dekontlar | Kapora dekontunu bildirmek ve durumunu görmek | İlgili teklif, tutar, tarih, dosya, inceleniyor/doğrulandı/uyuşmazlık | “Henüz dekont bildirimi yok.” | Dosya reddedilirse neden, izin verilen tür/boyut ve yeniden yükleme |
| Belgeler | Müşteriye açılmış ticari belgeleri indirmek | Teklif PDF/Excel/HTML, antetli çıktı, dil ve sürüm | “Henüz paylaşılmış belge yok.” | Erişim süresi veya yetki problemi güvenli ve açıklayıcı biçimde gösterilir |
| Geçmiş | Tüm müşteri hareketlerinin izini göstermek | Teklif yanıtı, liste onayı, istek gönderimi, dekont durumu | “İlk işleminiz burada görünecek.” | Filtre yüklenemezse tüm içerik değil, filtre özelinde hata |

Profil, şirket bilgileri, güvenlik ve çıkış menü şişirmemek için sağ üst hesap menüsünde yer alır.

---

## 5. Landing sayfası taslağı

### 5.1 Ana mesaj

**Başlık:**  
“İstediğiniz ürünü Çin'den sizin için getirelim.”

**Alt metin:**  
“Ürün linkini, fotoğrafını veya kısa tarifini paylaşın. Ekibimiz tedarik ve ithalat seçeneklerini çalışsın; size özel teklifinizi güvenli hesabınızdan inceleyin.”

**Birincil CTA:** “İşletme hesabı oluştur”  
**İkincil CTA:** “Nasıl çalıştığını gör”  
**Mevcut kullanıcı:** “Hesabım var — giriş yap”

### 5.2 Üç adım

1. **İşletmenizi tanıtın** — Kısa başvuruyu tamamlayın. Ticari içerik yalnız onaylı hesaplara açılır.
2. **İstek listenizi bırakın** — Link, fotoğraf ve açıklamayla aradığınız ürünleri iletin.
3. **Özel teklifinizi değerlendirin** — Fiyatları ve koşulları inceleyin; ürün bazında niyet ve miktar bildirin.

### 5.3 Güven açıklaması

“Fiyatlar ve teklifler herkese açık değildir. Her işletme hesabı ekibimiz tarafından incelenir; onay öncesinde ticari içerik gösterilmez.”

Bu metin korku üretmeden korumanın nedenini açıklar. “VIP”, “gizli fırsat”, “stok tükeniyor” gibi perakende baskı kalıpları kullanılmamalıdır.

### 5.4 SSS çekirdeği

- Hesap açınca fiyatları hemen görebilir miyim?
- Başvurunun değerlendirilmesi ne kadar sürer?
- Ürün linkim yoksa yalnız fotoğrafla istek gönderebilir miyim?
- Panelden ödeme yapabilir miyim?
- Teklif onayı sipariş sözleşmesi midir?

Yanıtlar ölçülmemiş süre taahhüdü içermemelidir. Süre göstermek gerekiyorsa gerçek operasyon verisiyle belirlenen servis seviyesi kullanılmalıdır.

---

## 6. Hesap ve başvuru yaşam döngüsü

### 6.1 Durumlar

| Durum | Kullanıcının görebildiği | Kullanıcının yapabildiği | Ticari içerik |
|---|---|---|---|
| Ziyaretçi | Landing, SSS, giriş/kayıt | Başvuru başlatma | Yok |
| E-posta doğrulama bekliyor | Doğrulama yönergesi | Yeniden doğrulama isteği, e-posta düzeltme | Yok |
| İşletme incelemesi bekliyor | Başvuru özeti, referans, süreç | İletişim/şirket bilgisini düzeltme | Yok |
| Ek bilgi gerekli | İstenen alan/belge ve gerekçe | Yalnız isteneni tamamlama | Yok |
| Onaylandı / aktif | Tam müşteri paneli | Yetkili bütün müşteri işlemleri | Kendi şirket kapsamı |
| Reddedildi | Nötr sonuç ve iletişim yolu | Destek talebi / yeniden başvuru politikası | Yok |
| Askıya alındı | Askı nedeni kategorisi ve iletişim | Güvenlik/iletişim adımları | Yok |

### 6.2 Önerilen başvuru akışı

**Adım 1 — Hesap**

- İş e-postası
- Parola
- Parola tekrarı
- E-posta doğrulama

**Adım 2 — İşletme**

- Ticari unvan
- Vergi numarası / vergi dairesi
- Şehir
- Yetkili ad soyad
- Telefon
- Web sitesi veya sosyal işletme profili (opsiyonel)

**Adım 3 — İhtiyaç ve beyan**

- Ürün/iş alanı
- Kısa tedarik ihtiyacı
- Kullanım koşullarını kabul
- Aydınlatma metnini okuma bağlantısı

İlk başvuruda ticaret sicil belgesi gibi yüksek sürtünmeli dosyalar herkesten istenmemelidir. Operasyon veya risk değerlendirmesi gerektirirse **“ek bilgi gerekli”** durumunda istenmelidir. Bu, veri minimizasyonu ve başvuru tamamlama oranı arasında dengeli bir yaklaşımdır.

### 6.3 Onay bekleme ekranı

Ekran “boşluk” hissi vermemelidir. Şunları içerir:

- “Başvurunuz alındı” başlığı.
- Başvuru referans numarası.
- Tamamlanan ve sıradaki adımı gösteren kısa durum çizgisi.
- “Onaylanana kadar ürün ve fiyatlar kapalıdır” açıklaması.
- Başvuru bilgilerini görüntüle/düzelt.
- Gerekirse istenen ek bilgiyi yükle.
- İletişim kanalı.

GOV.UK onay sayfası deseni, bir referans numarası, sonraki adım ve iletişim bilgisinin açıkça verilmesini önerir; bu desen başvuru bekleme ekranına uyarlanabilir. [GOV.UK — confirmation pages](https://design-system.service.gov.uk/patterns/confirmation-pages/)

---

## 7. Giriş, parola ve işlemsel e-posta

### 7.1 Giriş ekranı

- E-posta ve parola.
- Parolayı göster/gizle.
- “Şifremi unuttum.”
- “İşletme hesabınız yok mu?” bağlantısı.
- Hata mesajı hesabın varlığını veya onay durumunu gereksiz yere ifşa etmemeli.

Parolayı göster/gizle kontrolü kullanıcı hatasını azaltmaya yardımcı olan erişilebilir bir desendir. [GOV.UK — password input](https://design-system.service.gov.uk/components/password-input/)

### 7.2 Şifremi unuttum

Formun sonucu, e-posta sistemde olsun veya olmasın aynı olmalıdır:

> “Bu e-posta için uygun bir hesap varsa sıfırlama bağlantısını gönderdik.”

OWASP, hesap keşfini önlemek için aynı yanıtı ve tutarlı yanıt süresini; sıfırlama anahtarının rastgele, tek kullanımlık ve süreli olmasını; sıfırlama tamamlanana kadar hesapta değişiklik yapılmamasını önerir. [OWASP — Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)

### 7.3 Parola politikası

- Uzun parola/parola cümlelerini destekle.
- Bilinen zayıf veya ele geçirilmiş parolaları engelle.
- Parola yöneticisi, yapıştırma ve otomatik doldurmaya izin ver.
- Keyfi büyük harf/rakam/sembol ritüeli ve periyodik zorunlu değişim uygulama; ihlal şüphesinde sıfırlama iste.
- Başarısız girişlerde hız sınırlama ve risk tabanlı bot koruması kullan.

Bu yaklaşım güncel NIST ve OWASP rehberleriyle uyumludur. [NIST SP 800-63B — passwords](https://pages.nist.gov/800-63-4/sp800-63b/passwords/), [OWASP — Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

### 7.4 E-posta kapsamı

| E-posta | Durum | İçerik sınırı |
|---|---|---|
| E-posta doğrulama | İzinli / gerekli | Doğrulama bağlantısı ve güvenlik açıklaması |
| Şifre sıfırlama | İzinli / gerekli | Tek kullanımlık bağlantı, süre, kullanıcı başlatmadıysa uyarı |
| Başvuru alındı | İzinli | Referans ve portal bağlantısı; ürün/fiyat yok |
| Ek bilgi gerekli | İzinli | Talep edilen bilgi ve güvenli portal bağlantısı |
| Hesap onaylandı/reddedildi/askıya alındı | İzinli | Durum ve güvenli giriş/iletişim yolu |
| Parola veya e-posta değişti | İzinli / güvenlik | Değişiklik bilgisi ve itiraz yolu |
| Yeni teklif, kampanya, bülten, stok | **Yasak** | Mevcut ürün kararı gereği e-posta/SMS bildirimi yapılmaz |

Ticari İletişim mevzuatında devam eden üyelik, satın alma/teslimat ve benzeri durum bildirimleri, tanıtım içermemek koşuluyla ticari elektronik ileti onayından ayrı ele alınır. Yine de kesin e-posta şablonları ve saklama süreçleri hukuk danışmanıyla doğrulanmalıdır. [T.C. Ticaret Bakanlığı — Ticari İletişim Yönetmeliği](https://kayseri.ticaret.gov.tr/yayinlar/tuketici/ticari-iletisim-ve-ticari-elektronik-iletiler-hakkinda-yonetmelik), [İYS — Yönetmelik](https://iys.org.tr/iys/yonetmelik)

---

## 8. Onaylı müşteri çalışma alanı

### 8.1 Genel bakış

Ekran bir rapor panosu değil, **sıradaki işi söyleyen çalışma masasıdır**. Üst bölüm:

- “Yanıt bekleyen teklif” varsa birincil görev kartı.
- Devam eden istek listesi.
- İncelenen dekont.
- Son üç hareket.

Gelir, tasarruf, başarı yüzdesi gibi ölçülmemiş veya müşteriye faydası belirsiz metrikler gösterilmez.

### 8.2 Teklif inceleme ve onay

Akış:

1. Müşteri teklif listesini açar.
2. Liste başında para birimi, KDV durumu, geçerlilik ve teslim/ön sipariş açıklamasını görür.
3. Her ürüne `İlgileniyorum`, `Kararsızım` veya `İstemiyorum` yanıtı verir.
4. İlgileniyorum için miktar; tüm durumlar için opsiyonel not girebilir.
5. Yanıt özetini inceler.
6. Aşağıdaki niyet beyanını görüp liste yanıtını gönderir.

**Önerilen niyet beyanı:**

> “Bu yanıtlar satın alma veya kesin sipariş değildir; ön sipariş değerlendirmesi için ticari niyetimi ve talep miktarlarımı bildirir. Kesin koşullar ayrıca teyit edilir.”

**Gönderim sonrası:**

> “Yanıtınız kaydedildi. Bu listeye ait ürün ve fiyatlar değiştirilmedi; beyanınız ayrı bir yanıt kaydı olarak saklandı.”

Liste içeriği muhatapça değiştirilemez. Düzeltme gerekiyorsa yeni yanıt sürümü veya geri çekme talebi oluşur; teklif satırları yerinde düzenlenmez.

### 8.3 İstek listesi

İstek kartı minimum şu alanları destekler:

- Ürün bağlantısı (opsiyonel fakat doğrulanmış protokol/alan kurallarıyla).
- Fotoğraf (opsiyonel).
- Açıklama (zorunlu).
- Tahmini miktar.
- Varyant/ölçü/renk notu.

Sunucu kaynak siteyi otomatik çekmez. Link yalnız referanstır; başlık, fiyat veya görsel otomatik ithal edilmez. Müşterinin fotoğrafı ile kaynak tedarikçi görseli/maliyeti aynı veri alanında karıştırılmaz.

### 8.4 Dekont bildirimi

Bu ekran ödeme almaz. Müşteri yalnız daha önce dışarıda yaptığı kapora ödemesine ait dekontu bildirir:

- İlgili teklif/iş.
- Tarih ve tutar.
- Dosya.
- Opsiyonel açıklama.
- İnceleniyor, doğrulandı veya uyuşmazlık durumu.

“Ödeme yap” veya kart alanı bulunmaz. “Dekont yüklemek ödemenin otomatik onaylandığı anlamına gelmez” açıklaması görünür.

---

## 9. Yetkilendirme ve gizlilik mimarisi

### 9.1 Temel ilke

**Kimlik doğrulama, yetkilendirme değildir.** Kullanıcının giriş yapmış olması ticari içeriğe erişim için yeterli değildir. Her hassas istekte sunucu en az şu soruları cevaplamalıdır:

1. Oturum geçerli mi?
2. E-posta doğrulanmış mı?
3. İşletme hesabı onaylı ve aktif mi?
4. Kullanıcı bu şirkete bağlı mı?
5. İstenen teklif/liste/belge bu şirkete atanmış mı?
6. İstenen işlem müşterinin rolüne izinli mi?

OWASP, erişim kontrolünün her istekte sunucu tarafında uygulanmasını ve en az ayrıcalık ilkesini önerir. [OWASP — Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)

### 9.2 “Görünmez” ne demektir?

Onaylanmamış hesaba ürün ve fiyatlar:

- HTML içinde gizli olarak yazılmaz.
- API yanıtına eklenmez.
- Sayfa ön yükleme/hydration verisine konmaz.
- İstemci önbelleği veya localStorage'a yazılmaz.
- PDF/Excel dışa aktarım uçlarına erişilemez.
- Arama önerisi, sayaç, hata metni veya analiz olayında sızdırılmaz.
- Paylaşılabilir dosya URL'siyle doğrudan açılamaz.

Hassas token ve içerikler localStorage'da tutulmamalıdır. [OWASP — HTML5 Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/HTML5_Security_Cheat_Sheet.html)

### 9.3 Oturum

- `Secure`, `HttpOnly`, uygun `SameSite` özellikli çerez.
- Girişte ve yetki değişiminde oturum kimliğini yenileme.
- Mutlak ve hareketsizlik zaman aşımı.
- Parola/e-posta değişiminde yeniden kimlik doğrulama.
- Kullanıcıya diğer oturumları kapatma imkânı.
- Onay/askı değişikliğini aktif oturumlara gecikmeden yansıtma.

[OWASP — Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)

### 9.4 Dosya yükleme

İstek fotoğrafları ve dekontlar için:

- İzin verilen uzantı ve MIME türü listesi.
- Dosya imzası doğrulaması.
- Sunucu üretimli dosya adı.
- Boyut/adet sınırı.
- Zararlı içerik taraması.
- Web kökü dışında veya ayrı güvenli depoda saklama.
- Yetkili yükleyici ve şirket kapsamı kontrolü.
- CSRF koruması ve erişim denetimli indirme.

[OWASP — File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)

### 9.5 Kayıt ve izleme

Başarılı/başarısız girişler, parola sıfırlama girişimleri, onay kararları, yetki değişimleri, hassas belge erişimi ve yasak erişim denemeleri denetim kaydına alınmalıdır. Parola, sıfırlama anahtarı, oturum çerezi, dekont içeriği ve gereksiz kişisel veri loglanmamalıdır. [OWASP — Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)

---

## 10. KVKK ve form metinleri

### 10.1 Aydınlatma ve açık rıza ayrımı

KVKK'nın 18 Şubat 2026 tarihli ilke kararı, aydınlatma metni ile açık rızanın ayrı düzenlenmesini; aydınlatma yapıldığını doğrulamak için kullanıcıdan “rıza/onay” alınmamasını vurgular. Bu nedenle:

- “KVKK metnini kabul ediyorum” şeklinde zorunlu, toplu kutu kullanılmaz.
- Aydınlatma metni kayıt alanlarının yanında erişilebilir bağlantıyla sunulur.
- Açık rıza gerçekten gerekli ayrı bir işleme amacı varsa ayrı, belirli ve özgür iradeli alınır.
- Mevcut MVP'de pazarlama yapılmayacağı için pazarlama izni kutusu eklenmez.
- Kullanım koşulları kabulü, aydınlatma metninden ayrı bir hukuki işlemdir ve ayrı adlandırılır.

[KVKK — 18.02.2026 tarihli 2026/347 sayılı İlke Kararı duyurusu](https://www.kvkk.gov.tr/Icerik/8710/veri-sorumlulari-tarafindan-acik-riza-ve-aydinlatma-metinlerinin-ayri-ayri-duzenlenmesi-gerektigi-hakkinda-kisisel-verileri-koruma-kurulunun-18-02-2026-tarihli-ve-2026-347-sayili-ilke-kararina-iliskin-kamuoyu-duyurusu), [KVKK — Açık rıza alırken dikkat edilecek hususlar](https://www.kvkk.gov.tr/Icerik/2037/Acik-Riza-Alirken-Dikkat-Edilecek-Hususlar)

### 10.2 Taslak form altı metni

> “Kişisel verilerinizin nasıl işlendiğini Aydınlatma Metni'nden inceleyebilirsiniz. ‘Başvuruyu gönder’ düğmesi, Kullanım Koşulları'nı kabul ettiğinizi belirtir; pazarlama iletisi izni vermez.”

Nihai metin, veri sorumlusunun gerçek kimliği, işleme amaçları, hukuki sebepleri, alıcı grupları, saklama ve başvuru kanallarıyla hukuk danışmanı tarafından tamamlanmalıdır.

---

## 11. Form ve erişilebilirlik ilkeleri

- Formlar masaüstünde bile tek ana sütunla ilerler; ilişkili kısa alanlar dışında yan yana alan kullanılmaz.
- Etiketler alanın üstünde kalıcıdır; placeholder etiket yerine geçmez.
- Zorunlu ve opsiyonel alanlar açıkça işaretlenir.
- Hata yalnız kırmızı renkle değil, alan yanında neyin nasıl düzeltileceğini söyleyen metinle gösterilir.
- İlk hataya odak taşınır; form başında erişilebilir hata özeti verilir.
- Klavye odağı görünürdür; modal açıldığında odak içine taşınır ve kapanınca tetikleyiciye döner.
- Dokunma hedefleri ve renk kontrastı WCAG 2.2 AA hedefiyle doğrulanır.
- Parola yöneticisi ve otomatik doldurma engellenmez.
- Kimlik doğrulamada ezber/bulmaca gerektiren yöntemler yerine kopyalama, parola yöneticisi ve erişilebilir alternatifler desteklenir.

[W3C — WCAG 2.2](https://www.w3.org/TR/WCAG22/), [W3C — Accessible Authentication (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html), [Baymard — form design](https://baymard.com/learn/form-design), [GOV.UK — error message](https://design-system.service.gov.uk/components/error-message/)

---

## 12. Görsel tasarım yönü

### 12.1 Tasarım tezi

Arayüz “alışveriş sitesi” veya “veri dolu admin dashboard” gibi görünmemelidir. Önerilen ifade:

> **Özel tedarik masası:** sakin, kontrollü, ticari, belgeli ve güven veren.

### 12.2 Sistem

- **Renk:** koyu lacivert/slate temel, sıcak kırık beyaz zemin, kontrollü turuncu vurgu.
- **Tipografi:** güçlü başlık hiyerarşisi; gövde metninde yüksek okunabilirlik; sayı ve referanslarda tabular rakam.
- **Geometri:** 10–16 px köşe yarıçapı; her şeyi “hap” yapan aşırı yuvarlaklık yok.
- **Kart kullanımı:** yalnız bilgi grubu veya eylem sınırı gerektiğinde; her metin ayrı kart değildir.
- **Durumlar:** renk + ikon + metin beraber; sadece renge güvenilmez.
- **Yoğunluk:** landing ferah; portal görev odaklı ve kompakt.
- **Mobil:** teklif satırları yatay tabloya zorlanmaz; ürün kartına dönüşür ve eylemler başparmak alanına yaklaşır.

### 12.3 Kaçınılacak desenler

- Üst üste çok sayıda KPI kartı.
- Büyük degrade alanları ve dekoratif, anlamsız grafikler.
- Perakende dili: sepet, kupon, fırsat, stokta son ürün, hemen al.
- Onay öncesi “örnek fiyat” veya bulanıklaştırılmış ürün kataloğu; bu da ticari bilgiyi ifşa eder ve yanlış beklenti yaratır.
- Her rolü aynı sayfaya sığdırmak; bu demo yalnız müşteri rolüdür.

---

## 13. Çıktı ve tek dil kuralı

Onaylı müşteri portalındaki her çıktı:

- Aynı sunucu tarafı müşteri whitelist'inden üretilir.
- Maliyet, kâr, kaynak site/link ve tedarikçiyi içermez.
- İşletmenin antet, adres ve iletişim bilgisiyle oluşturulur.
- Liste düzeyinde para birimi ve KDV durumunu açıkça yazar.
- Kullanıcının seçtiği tek dilde komple üretilir; kolon başlığı, durum, dipnot ve tarih ifadeleri başka dilden kalmaz.
- HTML, PDF, Excel ve yazdırmada aynı veri/sürüm kimliğini taşır.

Arayüzde bir kolonu gizlemek, dışa aktarım güvenliği değildir. Dışa aktarım sunucuda aynı whitelist ile yeniden üretilmelidir.

---

## 14. Ölçüm önerisi — iddia değil

Aşağıdaki metrikler hedef değil, karar vermek için ölçüm önerisidir:

- Landing → başvuru başlatma oranı.
- Başvuru başlatma → e-posta doğrulama oranı.
- Doğrulama → tamamlanmış başvuru oranı.
- Başvuru inceleme bekleme süresi (medyan ve yüzdelikler).
- Ek bilgi istenen başvuru oranı ve tamamlanma oranı.
- Onay sonrası ilk istek listesine kadar geçen süre.
- Teklif açma → liste yanıtı gönderme oranı.
- Ürün yanıtlarının dağılımı: ilgili/kararsız/istemiyor.
- Form hatası, yükleme reddi ve şifre sıfırlama başarısızlık oranı.

“Çoğu müşteri”, “hızlı onay”, “daha yüksek dönüşüm” gibi ifadeler bu ölçümler yapılmadan ürün metnine veya rapora gerçekmiş gibi yazılmamalıdır.

---

## 15. Kabul ölçütleri

### 15.1 Erişim

- [ ] Ziyaretçi landing, SSS, kayıt ve girişi görebilir.
- [ ] Onaysız hesap hiçbir ürün/fiyat/teklif/liste/belge verisi alamaz.
- [ ] Onaylı müşteri yalnız kendi şirketine atanmış içeriği alabilir.
- [ ] Askıya alma aktif oturumlarda da ticari erişimi keser.
- [ ] Direkt URL, dışa aktarım ve dosya adresi erişim kapısını aşamaz.

### 15.2 Kayıt ve onay

- [ ] E-posta doğrulaması tamamlanmadan işletme inceleme kuyruğu oluşmaz.
- [ ] Kullanıcı başvuru referansını ve durumunu görebilir.
- [ ] Yönetici ek bilgi isteyebilir; kullanıcı yalnız talebi tamamlayabilir.
- [ ] Onay/ret/askı kararları denetim kaydına alınır.
- [ ] Aydınlatma ile kullanım koşulları ayrı sunulur; toplu KVKK rızası yoktur.

### 15.3 Parola ve e-posta

- [ ] Şifremi unuttum yanıtı hesap varlığını ifşa etmez.
- [ ] Sıfırlama bağlantısı tek kullanımlık ve sürelidir.
- [ ] Oran sınırlama ve kötüye kullanım koruması vardır.
- [ ] Yalnız tanımlı işlemsel hesap e-postaları gönderilir; pazarlama içermez.

### 15.4 Müşteri akışları

- [ ] Teklif satırları müşteri tarafından değiştirilemez.
- [ ] Ürün niyeti, miktar ve not ayrı yanıt katmanında tutulur.
- [ ] Liste gönderiminde ön sipariş niyet beyanı görünür.
- [ ] İstek listesi link olmadan fotoğraf + açıklamayla gönderilebilir.
- [ ] Dekont yükleme bir ödeme/tahsilat gibi sunulmaz.
- [ ] Tüm çıktılar müşteri whitelist'i ve tek dil kuralına uyar.

### 15.5 Erişilebilirlik ve hata

- [ ] Temel akışlar klavyeyle tamamlanabilir.
- [ ] Odak görünür, modal odağı kontrollüdür.
- [ ] Hatalar alanla ilişkilendirilir ve düzeltme önerir.
- [ ] Renk kontrastı ve dokunma hedefleri WCAG 2.2 AA doğrulamasından geçer.

---

## 16. Demo kapsamı ve kullanım

Ekteki `musteri-paneli-demo.html` tek dosyalık, çevrimdışı açılabilir bir **ön yüz kavram demosudur**. Gerçek kimlik doğrulama, e-posta, dosya yükleme, veri saklama veya sunucu yetkilendirmesi yapmaz.

Demo görünüm seçicisiyle şu sahneler incelenebilir:

- Halka açık landing.
- Üç adımlı işletme hesabı başvurusu.
- Giriş ve şifre sıfırlama.
- Onay bekleme ekranı.
- Onaylı müşteri genel bakış.
- Teklif listesi ve ürün niyeti.
- İstek listesi.
- Dekont ve geçmiş.

Demo, geliştirme ekibine görsel yön ve davranış referansı verir; güvenlik kuralları yalnız raporda değil gerçek backend/API yetkilendirmesinde uygulanmalıdır.

---

## 17. Emsal özeti

| Emsal | Gözlenen desen | Bize uyan | Bize uymayan |
|---|---|---|---|
| Shopify B2B | Halka açık şirket hesabı talebi; onaya kadar B2B fiyat/sipariş kapalı | En yakın erişim modeli | Shopify checkout/katalog yapısı |
| Faire | İşletme doğrulaması ve gerekirse ek bilgi | Güven kapısı, durum takibi | Çok satıcılı pazar yeri yapısı |
| Ankorstore | Profesyonel kayıt olmadan toptan fiyat yok | Landing/ticari katman ayrımı | Katalogdan doğrudan sipariş |
| WooCommerce B2B | Rol bazlı fiyat/ürün gizleme, manuel onay, teklif | Yetki ve gizlilik deseni | Eklenti merkezli mağaza mimarisi |
| GOV.UK Design System | Onay, parola ve hata mesajı desenleri | Açıklık, erişilebilirlik | Ticari ürün mimarisi sunmaz |
| OWASP / NIST | Kimlik, parola, sıfırlama, yetki ve dosya güvenliği | Teknik güvenlik tabanı | Ürün tasarımı/operasyon kararı vermez |

Tüm emsal gözlemleri 28 Ağustos 2026 tarihinde kamuya açık kaynaklardan yapılmıştır. Canlı ürünler ve belgeler değişebileceği için nihai geliştirme öncesi bağlantılar tekrar doğrulanmalıdır.

---

## 18. Nihai ürün cümlesi

TedarikApp müşteri portalı bir mağaza değildir. Halka açık bir tanıtım ve işletme başvuru kapısının arkasında çalışan; yalnız onaylı şirketlerin kendi özel tekliflerini, isteklerini, dekont bildirimlerini ve geçmişini yönettiği **güvenli bir ön sipariş ve özel tedarik çalışma alanıdır**.
