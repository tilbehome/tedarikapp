# Görev #27C — V3-N Müşteri Paneli Kabul Senaryoları

**Sürüm:** 1.0  
**Tarih:** 28 Ağustos 2026  
**Kaynak:** Görev #26 raporu §2.1, §7, §10 ve §15; Görev #27 PM şerhleri  
**Kural:** Aşağıdaki senaryolar mevcut kararları doğrular; yeni ürün kuralı tanımlamaz.

---

## KT-N-001 — Ziyaretçinin halka açık alanlara erişimi

**Ön koşul:** Kullanıcının oturumu yoktur.  
**Adımlar:**

1. Landing sayfası açılır.
2. Nasıl çalışır, Güvenli erişim ve SSS bölümleri görüntülenir.
3. Giriş yap ve İşletme hesabı oluştur eylemleri açılır.

**Beklenen sonuç:** Halka açık tanıtım ve hesap başlangıç yüzeyleri kullanılabilir; hiçbir ürün, fiyat, teklif, liste veya müşteri belgesi sayfaya ya da sayfanın veri yüküne eklenmez.

## KT-N-002 — Oturumsuz doğrudan ticari sayfa isteği

**Ön koşul:** Kullanıcının oturumu yoktur; geçerli bir teklif adresi bilinmektedir.  
**Adımlar:**

1. Teklif detay adresi doğrudan açılır.
2. Aynı içeriğin veri isteği doğrudan çağrılır.

**Beklenen sonuç:** Erişim reddedilir. Yanıtta ürün, fiyat, miktar, liste özeti, dosya adresi veya ticari içeriğin varlığını doğrulayan ayırt edici veri bulunmaz.

## KT-N-003 — Onay bekleyen hesabın arayüz kapısı

**Ön koşul:** E-posta doğrulanmış, işletme başvurusu incelemede olan bir hesapla giriş yapılmıştır.  
**Adımlar:**

1. Hesap ana sayfası açılır.
2. Menü ve ağ yanıtları incelenir.

**Beklenen sonuç:** Yalnız başvuru referansı, durum, başvuru bilgileri ve destek yolu görünür. Teklif, fiyat, ürün, istek geçmişi, dekont veya belge menüleri ticari veri göstermez.

## KT-N-004 — Onay bekleyen hesabın API ve dışa aktarım kapısı

**Ön koşul:** İşletme hesabı onay beklemektedir.  
**Adımlar:**

1. Teklif listeleme ve detay veri istekleri yapılır.
2. HTML, PDF, Excel ve yazdırma uçlarına doğrudan istek yapılır.
3. Bilinen bir belge adresi açılmaya çalışılır.

**Beklenen sonuç:** Bütün isteklerde erişim reddedilir; ticari veri veya dosya üretilmez. Arayüzde gizleme yapılmış olması tek güvenlik önlemi olarak kullanılmaz.

## KT-N-005 — Onaylı hesabın kendi şirket kapsamı

**Ön koşul:** Kullanıcı, A işletmesine bağlı onaylı ve aktif hesaba sahiptir; A işletmesine atanmış teklifler vardır.  
**Adımlar:**

1. Teklifler, belgeler ve geçmiş açılır.
2. A işletmesine atanmış kayıtlar görüntülenir.

**Beklenen sonuç:** Kullanıcı yalnız A işletmesine atanmış kayıtları ve izinli müşteri alanlarını görür.

## KT-N-006 — Şirketler arası nesne erişiminin engellenmesi

**Ön koşul:** Kullanıcı A işletmesine bağlıdır; B işletmesine ait geçerli teklif ve belge kimlikleri bilinmektedir.  
**Adımlar:**

1. B işletmesinin teklif kimliğiyle detay isteği yapılır.
2. B işletmesinin dosya ve dışa aktarım adresi çağrılır.

**Beklenen sonuç:** B işletmesine ait içerik verilmez; ad, ürün, fiyat, tahmini toplam, durum, dosya adresi veya başka bir ticari alan sızmaz.

## KT-N-007 — Askıya alma işleminin aktif oturuma yansıması

**Ön koşul:** Onaylı kullanıcı açık oturumla bir teklif sayfasındadır. Hesap yönetici tarafından askıya alınır.  
**Adımlar:**

1. Sayfa yenilenir veya yeni ticari veri isteği yapılır.
2. Dışa aktarım denenir.

**Beklenen sonuç:** Aktif oturum ticari erişim sağlamaya devam etmez. Teklif, dosya ve dışa aktarım kapatılır; askı durumu ve destek yolu gösterilir.

## KT-N-008 — Halka açık işletme hesabı başvurusu

**Ön koşul:** Ziyaretçinin hesabı yoktur.  
**Adımlar:**

1. İşletme hesabı oluştur seçilir.
2. Hesap, işletme ve ihtiyaç adımları tamamlanır.
3. Başvuru gönderilir.

**Beklenen sonuç:** Başvuru oluşturulabilir. Başvurunun yapılması ürün/fiyat erişimi açmaz ve hesabın onaylandığı anlamına gelmez.

## KT-N-009 — E-posta doğrulaması olmadan inceleme kuyruğu

**Ön koşul:** Kayıt formu gönderilmiş, e-posta doğrulanmamıştır.  
**Adımlar:**

1. Başvuru durumu kontrol edilir.
2. Yönetici inceleme kuyruğu kontrol edilir.

**Beklenen sonuç:** Kullanıcı e-posta doğrulama bekliyor durumundadır; işletme inceleme kaydı aktif kuyruğa girmez ve ticari erişim açılmaz.

## KT-N-010 — E-posta doğrulamasından sonra başvuru durumu

**Ön koşul:** Kullanıcının doğrulanmamış başvurusu vardır.  
**Adımlar:**

1. Geçerli doğrulama bağlantısı kullanılır.
2. Başvuru durum ekranı açılır.

**Beklenen sonuç:** E-posta doğrulanır; başvuru işletme incelemesi durumuna geçer. Başvuru referansı ve sıradaki durum gösterilir; ticari içerik kapalı kalır.

## KT-N-011 — Ek bilgi talebi

**Ön koşul:** İşletme başvurusu incelemededir.  
**Adımlar:**

1. Yönetici başvuruyu Ek bilgi gerekli durumuna alır ve talebi yazar.
2. Kullanıcı durum ekranını açar.
3. Yalnız istenen bilgi veya belgeyi güvenli portal üzerinden iletir.

**Beklenen sonuç:** Talebin açıklaması görünür, gönderim başvuruya eklenir ve işlem denetim kaydına alınır. Ürün ve fiyat erişimi açılmaz.

## KT-N-012 — Hesap onayı

**Ön koşul:** E-postası doğrulanmış başvuru incelemededir.  
**Adımlar:**

1. Yönetici başvuruyu onaylar.
2. Kullanıcı yeniden giriş yapar veya oturum yetkisi yenilenir.

**Beklenen sonuç:** Kullanıcı yalnız kendi işletmesine atanmış müşteri çalışma alanına erişir. Onay kararı denetim kaydına alınır ve tanımlı onay e-postası pazarlama içermeden gönderilebilir.

## KT-N-013 — Başvuru reddi

**Ön koşul:** İşletme başvurusu incelemededir.  
**Adımlar:**

1. Yönetici başvuruyu reddeder.
2. Kullanıcı durum ekranını ve ticari adresleri açar.

**Beklenen sonuç:** Ret durumu ve iletişim yolu gösterilir. Ürün, fiyat, teklif, liste, belge ve dışa aktarım erişimi açılmaz; karar denetim kaydına alınır.

## KT-N-014 — Aydınlatma metni ile Kullanım Koşulları’nın ayrılması

**Ön koşul:** Kayıt formunun üçüncü adımı açıktır.  
**Adımlar:**

1. Aydınlatma Metni bağlantısı ve form kontrolleri incelenir.
2. Kullanım Koşulları kabul alanı incelenir.

**Beklenen sonuç:** Aydınlatma Metni ayrı belge olarak erişilebilir; “KVKK’yı kabul ediyorum” veya aydınlatmaya rıza veren zorunlu kutu yoktur. Kullanım Koşulları ayrı ad ve ayrı kabul kontrolüyle sunulur.

## KT-N-015 — Kullanım Koşulları kabul edilmeden başvuru gönderimi

**Ön koşul:** Kayıt formunun bütün zorunlu alanları dolu, Kullanım Koşulları kutusu işaretli değildir.  
**Adımlar:**

1. Başvuruyu gönder seçilir.

**Beklenen sonuç:** Başvuru gönderilmez; Kullanım Koşulları alanıyla ilişkili, nasıl düzeltileceğini söyleyen hata gösterilir. Hata, Aydınlatma Metni için rıza talebine dönüştürülmez.

## KT-N-016 — Şifremi unuttum hesap keşfine dayanıklı yanıt

**Ön koşul:** Biri kayıtlı, biri kayıtsız iki e-posta adresi vardır.  
**Adımlar:**

1. Kayıtlı adresle sıfırlama istenir.
2. Kayıtsız adresle sıfırlama istenir.
3. Her iki istemcinin görebildiği durum, gövde, yönlendirme ve alanlar karşılaştırılır.

**Beklenen sonuç:** Her iki durumda aynı güvenli mesaj gösterilir: “Bu e-posta için uygun bir hesap varsa sıfırlama bağlantısını gönderdik.” Hesap varlığını doğrulayan ayırt edici metin, kod veya yönlendirme bulunmaz; aynı yanıt şeması kullanılır.

## KT-N-017 — Şifre sıfırlama bağlantısının tek kullanımlı ve süreli olması

**Ön koşul:** Geçerli bir sıfırlama bağlantısı üretilmiştir.  
**Adımlar:**

1. Bağlantıyla yeni parola belirlenir.
2. Aynı bağlantı yeniden kullanılır.
3. Geçerliliği sona ermiş başka bir bağlantı açılır.

**Beklenen sonuç:** İlk başarılı kullanımdan sonra aynı bağlantı çalışmaz. Geçerliliği sona ermiş bağlantı parola değiştirmez; yeni sıfırlama isteği oluşturma yolu gösterilir.

## KT-N-018 — Şifre sıfırlama kötüye kullanım koruması

**Ön koşul:** Aynı kaynak veya hesap hedefi için art arda sıfırlama denemeleri yapılabilmektedir.  
**Adımlar:**

1. Tekrarlı sıfırlama istekleri gönderilir.
2. Kullanıcıya gösterilen sonuç ve gönderilen işlemsel iletiler gözlemlenir.

**Beklenen sonuç:** Hız sınırlama veya risk tabanlı koruma devreye girer; hesap kilitlenmez veya parola kendiliğinden değişmez. Yanıt hesap varlığını ifşa etmez.

## KT-N-019 — İşlemsel e-posta beyaz listesi

**Ön koşul:** E-posta hizmeti test ortamında yakalanabilmektedir.  
**Adımlar:**

1. Doğrulama, şifre sıfırlama, başvuru alındı, ek bilgi, onay, ret/askı ve parola/e-posta değişikliği olayları tetiklenir.
2. Yeni teklif, kampanya, bülten ve stok olayları tetiklenir.

**Beklenen sonuç:** Yalnız tanımlı yedi işlemsel e-posta ailesi gönderilir. Gövdelerde kampanya, ürün önerisi, indirim, yeni teklif, stok veya çapraz satış bölümü bulunmaz. Yeni teklif, kampanya, bülten ve stok olayları e-posta/SMS üretmez.

## KT-N-020 — Teklif kaynağının salt okunur olması

**Ön koşul:** Onaylı müşteriye atanmış aktif teklif vardır.  
**Adımlar:**

1. Ürün adı, açıklama, birim fiyat, para birimi ve KDV alanları arayüzden değiştirilmeye çalışılır.
2. Değiştirilmiş kaynak alanlarla istek gönderilir.

**Beklenen sonuç:** Teklif satırları düzenlenemez. İstemci tarafından değiştirilmiş kaynak alanlar kabul edilmez; kanonik teklif verisi değişmeden kalır.

## KT-N-021 — Müşteri yanıtının ayrı katmanda saklanması

**Ön koşul:** Onaylı müşteri bir teklifi yanıtlayabilir.  
**Adımlar:**

1. Ürünlere niyet, miktar ve not girilir.
2. Liste yanıtı gönderilir.
3. Teklif kaydı ile müşteri yanıt kaydı karşılaştırılır.

**Beklenen sonuç:** Teklif ürün ve fiyatları değişmez. Niyet, tahmini miktar ve not ayrı yanıt kaydı olarak saklanır; gönderim sonrası ayrım kullanıcıya açıklanır.

## KT-N-022 — Tüm ürünlerde niyet seçimi

**Ön koşul:** Birden fazla ürün içeren yanıtlanmamış teklif açıktır.  
**Adımlar:**

1. Ürünlerden en az biri için niyet seçilmeden liste gönderilmeye çalışılır.

**Beklenen sonuç:** Gönderim yapılmaz. Yanıtsız ürün gösterilir ve her ürün için İlgileniyorum, Kararsızım veya İstemiyorum seçeneklerinden birinin seçilmesi istenir.

## KT-N-023 — Ön sipariş niyet beyanının zorunlu olması

**Ön koşul:** Bütün ürünlerin niyet ve miktar doğrulamaları tamamlanmıştır; niyet beyanı işaretli değildir.  
**Adımlar:**

1. Liste yanıtını gönder seçilir.

**Beklenen sonuç:** Gönderim engellenir. “Bu yanıtlar satın alma veya kesin sipariş değildir…” beyanı görünür ve ayrı onay kontrolü işaretlenmeden yanıt kaydı oluşmaz.

## KT-N-024 — “Tahmini toplam” etiketi

**Ön koşul:** Tutar toplamı bulunan teklif, teklif listesi ve çıktı önizlemesi vardır.  
**Adımlar:**

1. Teklif özeti, detay, PDF, Excel, HTML ve yazdırma metinleri incelenir.
2. Toplam değeri çevresindeki açıklama kontrol edilir.

**Beklenen sonuç:** Değer bütün müşteri yüzeylerinde **“Tahmini toplam”** olarak adlandırılır; tek başına “Toplam”, “Ödenecek toplam” veya kesin sipariş bedeli ifadesi kullanılmaz. Değerin ödeme talebi ya da kesin sözleşme oluşturmadığı açıklanır.

## KT-N-025 — İlgileniyorum seçildiğinde miktar zorunluluğu

**Ön koşul:** Teklif ürününde İlgileniyorum seçilmiştir.  
**Adımlar:**

1. Miktar boş bırakılır.
2. Liste gönderilmeye çalışılır.

**Beklenen sonuç:** Miktar alanı açık ve zorunludur. “İlgileniyorum seçildiğinde tahmini miktar zorunludur” davranış metni gösterilir; geçerli miktar girilmeden gönderim yapılmaz.

## KT-N-026 — Kararsızım seçildiğinde miktarın isteğe bağlı olması

**Ön koşul:** Teklif ürününde Kararsızım seçilmiştir.  
**Adımlar:**

1. Miktar boş bırakılır.
2. Diğer zorunlu yanıtlar ve niyet beyanı tamamlanır.
3. Liste gönderilir.

**Beklenen sonuç:** Miktar alanı isteğe bağlıdır; yalnız bu alanın boş olması gönderimi engellemez. Kararsızım yanıtı ayrı kayda alınır.

## KT-N-027 — İstemiyorum seçildiğinde miktar alanının kapatılması

**Ön koşul:** Bir üründe daha önce miktar girilebilmiştir.  
**Adımlar:**

1. İstemiyorum seçilir.
2. Miktar alanına veri girilmeye çalışılır.
3. Liste gönderilir.

**Beklenen sonuç:** Miktar alanı kapatılır ve “İstemiyorum seçildiği için miktar alanı kullanılamaz” metni gösterilir. Bu ürün için miktar değeri yanıt yüküne eklenmez.

## KT-N-028 — İstek listesinin bağlantı olmadan gönderilmesi

**Ön koşul:** Onaylı müşteri yeni istek listesi formundadır.  
**Adımlar:**

1. Ürün bağlantısı boş bırakılır.
2. Fotoğraf, açıklama ve formun zorunlu asgari alanları tamamlanır.
3. İstek gönderilir.

**Beklenen sonuç:** Geçerli istek oluşturulur. Ürün bağlantısı zorunlu hata üretmez.

## KT-N-029 — İstek bağlantısından otomatik veri çekilmemesi

**Ön koşul:** İstek formuna erişilebilir bir ürün bağlantısı yazılmıştır.  
**Adımlar:**

1. Bağlantı alana girilir.
2. Form kaydedilir veya gönderilir.
3. Sunucu işlemleri ve kaydedilen alanlar incelenir.

**Beklenen sonuç:** Bağlantı referans olarak saklanır; harici siteden başlık, fiyat, görsel, çerez veya başka veri otomatik çekilmez. Kullanıcının girdiği asgari alanlar esas alınır.

## KT-N-030 — Dekont dosya türü ve boyut sınırı

**Ön koşul:** Onaylı müşteri Dekontlar ekranındadır.  
**Adımlar:**

1. PDF, JPG ve PNG dosyaları ayrı ayrı yüklenir.
2. Desteklenmeyen uzantı yüklenir.
3. 10 MB sınırını aşan dosya yüklenir.

**Beklenen sonuç:** Yalnız PDF, JPG ve PNG biçimleri, dosya 10 MB’ı aşmıyorsa kabul edilir. Desteklenmeyen veya sınırı aşan dosya için neden ve düzeltme yolu alanla ilişkili gösterilir.

## KT-N-031 — Dekont bildiriminin ödeme sayılmaması

**Ön koşul:** Geçerli dekont dosyası ve ilgili teklif seçilmiştir.  
**Adımlar:**

1. Dekont bildirimi gönderilir.
2. Teklif, ödeme ve dekont durumları incelenir.

**Beklenen sonuç:** Dekont “İnceleniyor” benzeri bildirim durumuna geçer; otomatik olarak ödeme, tahsilat, sipariş, stok ayırma veya teklif kabulü oluşmaz. Arayüzde kart/sanal POS alanı bulunmaz.

## KT-N-032 — Müşteri çıktısında whitelist ve gizli alanlar

**Ön koşul:** Onaylı müşteriye atanmış bir teklif için HTML, PDF, Excel ve yazdırma çıktısı alınabilir.  
**Adımlar:**

1. Dört çıktı türü oluşturulur.
2. Arayüz verileriyle çıktı alanları karşılaştırılır.

**Beklenen sonuç:** Bütün çıktılar aynı sunucu tarafı müşteri whitelist’inden üretilir. Maliyet, kâr, kaynak site/link, tedarikçi adı ve başka role ait alanlar hiçbir çıktıda bulunmaz.

## KT-N-033 — Çıktıda tek dil kuralı

**Ön koşul:** Aynı teklif için desteklenen çıktı dilleri seçilebilmektedir.  
**Adımlar:**

1. Her dil ayrı ayrı seçilir.
2. HTML, PDF, Excel ve yazdırma çıktıları oluşturulur.
3. Başlık, sütun, durum, buton dışı açıklama, dipnot, tarih ve para ifadeleri denetlenir.

**Beklenen sonuç:** Her çıktı seçilen dilde bütünüyle üretilir; başka dilden sistem etiketi veya yedek metin kalmaz. Veri kapsamı dil değişikliğiyle genişlemez.

## KT-N-034 — Erişilebilir hata ve modal davranışı

**Ön koşul:** Kullanıcı yalnız klavye kullanmaktadır; kayıt veya teklif yanıt formunda doğrulama hatası oluşturulabilir.  
**Adımlar:**

1. Modal klavyeyle açılır ve kapatılır.
2. Zorunlu alan boş bırakılarak form gönderilir.
3. Klavye odağı ve hata ilişkileri incelenir.

**Beklenen sonuç:** Odak modal içine taşınır, kapatıldığında tetikleyiciye döner ve görünür kalır. Hata yalnız renkle belirtilmez; sorunu ve düzeltme yolunu açıklar, alanla programatik olarak ilişkilidir ve ilk hataya odaklanılabilir.

---

## Kabul turu kapanış kontrolü

- Toplam senaryo: **34**
- Başlangıç/bitiş: **KT-N-001 — KT-N-034**
- Kapsanan bağlayıcı alanlar: erişim kapısı, kayıt/doğrulama/onay, KVKK ayrımı, işlemsel e-posta, şifre sıfırlama, teklif değişmezliği, niyet beyanı, Tahmini toplam, miktar matrisi, istek listesi, dekont, müşteri whitelist’i, tek dil ve erişilebilir hata.
- Senaryolar süre, dönüşüm oranı veya operasyon performansı taahhüdü içermez.

