# Görev 25A — Müşteri Paneli Taslağı

**Belge türü:** Araştırma destekli arayüz ve içerik taslağı  
**Hazırlık amacı:** TedarikApp V3-N öncesi PM şartnamesine girdi  
**Gözlem/taslak tarihi:** 28 Ağustos 2026  
**Karar statüsü:** Bu belge nihai şartname değildir.

## 1. Ana amaç ve sınır

Müşteri paneli, stoktan satış veya e-ticaret mağazası değildir. Ürün sahibinin müşteriye özel gönderdiği **özel/ön sipariş teklif listelerinin** incelendiği; müşterinin ürün bazında niyetini, miktarını ve notunu bildirdiği; kendi ürün isteğini ve kapora dekont bildirimini ilettiği kalıcı çalışma alanıdır.

### Şartname — kesin çerçeve

- Erişim kişiye özel kalıcı bağlantı + 6 haneli anahtarla sağlanır; üyelik, şifre ve kayıt formu yoktur.
- Rol sunucuda token üzerinden çözülür; panel ve bütün çıktılar yalnız müşteri whitelist'inden beslenir.
- Teklif listesinin gönderilmiş ürün verileri değiştirilemez. Müşterinin tercih, miktar ve notları ayrı yanıt katmanıdır.
- Ürün bazında üç niyet seçeneği vardır: **İlgileniyorum / Kararsızım / İstemiyorum**.
- Liste onayı, kesin sipariş değil **ön sipariş niyet beyanıdır**.
- Müşteri kendi istek listesini bağlantı, fotoğraf ve açıklamayla oluşturabilir. Sunucu bağlantıdan otomatik veri çekmez.
- Kapora panelde tahsil edilmez; yalnız dekont bildirimi/yüklemesi yapılır.
- Fiyat sunumu liste düzeyinde TRY veya USD ve KDV dahil ya da KDV hariç olarak sabittir.
- Maliyet, kâr, kaynak site/bağlantı ve tedarikçi adı hiçbir ekranda ve hiçbir çıktıda görünmez.
- HTML, PDF, Excel ve yazdırma çıktıları işletmenin antediyle, aynı müşteri süzgeciyle ve seçilen tek dilde eksiksiz üretilir.
- E-posta/SMS akışı yoktur.

### Vizyon — PM'nin doğrulayacağı tasarım yönü

- Ana sayfa “alışverişe başla” dili yerine bekleyen kararları ve son hareketleri gösteren sakin bir çalışma özeti olmalıdır.
- Ürün kartları görsel ağırlıklı; karar alanı ise sabit ve kısa olmalıdır. Fiyat, teslim beklentisi ve niyet seçimi aynı bakış alanında tutulmalıdır.
- Onay öncesi özet ekranı, müşterinin yanlışlıkla kesin sipariş verdiğini düşünmesini engellemelidir.
- Dışa aktarılan belge panel ekran görüntüsü gibi değil; antetli, sayfalanabilir bir teklif/niyet özeti gibi görünmelidir.

## 2. Menü ve bilgi mimarisi

Menü beş öğeyle sınırlıdır.

| Menü | Doğrudan hizmet ettiği amaç | İçerik kapsamı |
|---|---|---|
| Genel Bakış | Bekleyen müşteri işini görünür kılmak | Bekleyen listeler, taslak yanıtlar, son hareketler |
| Tekliflerim | Gönderilen ön sipariş listelerini incelemek ve yanıtlamak | Liste dizini, liste detayı, ürün niyeti, liste onayı |
| İstek Listem | “Şunu getir” talebini yapılandırılmış biçimde iletmek | İstek ekleme, taslaklar, iletilen istekler |
| Dekontlarım | Haricen yapılan kapora için kanıt bildirmek | Dekont yükleme, ilişkilendirme, inceleme durumu |
| Geçmiş | Önceki teklif, istek ve dekont hareketlerine erişmek | Salt okunur zaman çizgisi ve belgeler |

Arama, dil seçimi, çıktı alma ve erişim yardımı menü öğesi değildir; sayfa üst çubuğunda bağlamsal araçtır.

## 3. Ekran envanteri

### 3.1 Genel Bakış

**Amaç:** Müşterinin paneli açtığında hangi işlemin beklediğini tek bakışta anlaması.

**Ana bloklar**

1. İşletme antedi/markası, müşteri adı ve seçili dil.
2. “Sizi bekleyenler”: yanıt bekleyen liste, tamamlanmamış taslak, açıklama bekleyen dekont.
3. “Son teklifler”: liste adı, gönderim tarihi, geçerlilik/yanıt tarihi, para birimi ve KDV sunumu.
4. “Son hareketler”: müşterinin kendi yanıt, istek ve dekont kayıtları.
5. Tek birincil eylem: bekleyen en eski teklifi incele.

**Boş durum:** “Şu anda incelemeniz için gönderilmiş bir teklif yok. Dilerseniz İstek Listem bölümünden aradığınız ürünü bize iletebilirsiniz.”

**Hata durumu:** “Özet şu anda yüklenemedi. Kaydedilmiş yanıtlarınız korunur; sayfayı yeniden deneyin.” Hata, listelerin bulunmadığı izlenimini vermemelidir.

### 3.2 Tekliflerim — liste dizini

**Amaç:** Birden çok teklif listesini durum ve tarihle ayırt ederek açmak.

**Ana bloklar**

1. Liste kartı/satırı: liste adı, teklif tarihi, yanıt için son tarih varsa tarih, ürün sayısı.
2. Liste düzeyi fiyat etiketi: ör. “TRY · KDV dahil” veya “USD · KDV hariç”. Ürün satırlarında farklı para birimi/KDV dili gösterilmez.
3. İlerleme özeti: yanıtlanan ürün / toplam ürün.
4. Müşteri yanıt durumu: başlamadı, taslak, niyet beyanı iletildi, revizyon açıldı gibi yalnız müşteri için izinli ifadeler.
5. Arama ve temel filtre: açık/tamamlanan; menüye dönüşmez.

**Boş durum:** “Henüz size gönderilmiş bir teklif listesi yok.”

**Hata durumu:** “Teklif listeleri alınamadı. Yeniden deneyin; sorun sürerse bağlantıyı size ileten kişiyle paylaşın.”

### 3.3 Teklif detayı ve liste onayı

**Amaç:** Gönderilmiş ürünleri değiştirmeden incelemek; her ürün için niyet, miktar ve not toplamak; listeyi niyet beyanıyla iletmek.

**Ana bloklar**

1. Sabit liste başlığı: liste adı/kodu, tarih, geçerlilik, para birimi, KDV sunumu ve ön sipariş açıklaması.
2. Ürün gezgini veya liste: görsel, ürün adı/kodu, müşteriye açık varyant/özellik, birim fiyat, varsa beklenen teslim aralığı.
3. Salt okunur veri işareti: “Teklif bilgileri işletme tarafından hazırlanmıştır ve bu ekranda değiştirilemez.”
4. Yanıt alanı: İlgileniyorum / Kararsızım / İstemiyorum; miktar; müşteri notu.
5. Sabit ilerleme çubuğu: yanıtlanan, kalan, zorunlu eksikler.
6. Liste özeti: niyetlere göre ürün ve miktar sayıları; varsa gösterilebilir teklif toplamı. “Kararsızım” veya “İstemiyorum” seçilen satırlar sipariş toplamı gibi sunulmaz.
7. Niyet beyanı kutusu ve “Niyet beyanımı ilet” eylemi.
8. İletim sonrası salt okunur onay özeti; ürün sahibi revizyon açarsa yalnız yeni yanıt turu düzenlenebilir.

**Boş durum:** Liste mevcut fakat ürün yoksa “Bu teklif listesine henüz ürün eklenmemiş. Yanıt gönderemezsiniz.”

**Hata durumu:** Ürünler kısmen gelirse onay kapatılır: “Listenin tüm ürünleri yüklenemedi. Eksik görünümle niyet beyanı iletilemez.” Kaydedilmiş taslak korunur.

### 3.4 İstek Listem

**Amaç:** Müşterinin teklif listesi beklemeden “şunu getir” talebini asgari ve anlaşılır alanlarla iletmesi.

**Ana bloklar**

1. Yeni istek eylemi.
2. Asgari alanlar: ürün bağlantısı **veya** fotoğraf; kısa açıklama. Müşteri isterse tahmini miktar ve not ekler.
3. Açık uyarı: “Bağlantı yalnız referanstır; ürün bilgileri bu siteden otomatik alınmaz.”
4. Önizleme: müşterinin girdiği bağlantı/fotoğraf/açıklama aynen gösterilir.
5. Taslak ve iletilmiş istek listeleri; iletilmiş kayıt salt okunurdur.

**Boş durum:** “Henüz ürün isteği eklemediniz. Aradığınız ürünü bağlantı, fotoğraf veya kısa bir açıklamayla iletebilirsiniz.”

**Hata durumu:** Yükleme başarısızsa form verisi korunur; “Fotoğraf yüklenemedi. İsteğiniz gönderilmedi; yeniden deneyin veya bağlantı ve açıklamayla devam edin.”

### 3.5 Dekontlarım

**Amaç:** Panel dışında ödenen kaporaya ilişkin dekontu doğru teklif/listeyle ilişkilendirerek bildirmek.

**Ana bloklar**

1. İlgili teklif/listenin seçimi; yalnız müşteriye açık ve ilişkilendirilebilir kayıtlar.
2. Dekont dosyası, ödeme tarihi, beyan edilen tutar/para birimi ve kısa açıklama.
3. Uyarı: “Bu ekran ödeme almaz. Yükleme, kaporanın kabul edildiği anlamına gelmez.”
4. Önizleme ve kişisel/hesap bilgilerinin dosyada görünebileceğine dair farkındalık metni.
5. Bildirim durumu: iletildi, inceleniyor, açıklama istendi, doğrulandı veya reddedildi; yalnız yetkili süreçte üretilen durum.

**Boş durum:** “Henüz dekont bildiriminiz yok.”

**Hata durumu:** “Dekont kaydedilemedi. Dosyayı yeniden seçmeden önce form bilgilerinizin korunduğunu kontrol edin.” Sunucu başarı teyidi olmadan kayıt ‘iletildi’ gösterilmez.

### 3.6 Geçmiş

**Amaç:** Müşterinin kendi teklif, niyet beyanı, istek ve dekont geçmişini kanıtlanabilir zaman bilgisiyle görmesi.

**Ana bloklar**

1. Zaman çizgisi: kayıt türü, liste/istek adı, işlem, tarih-saat, referans.
2. Tür ve tarih filtreleri.
3. Salt okunur ayrıntı: o anda iletilen müşteri yanıtı ve ilgili görünür belge.
4. Seçilen dilde HTML/PDF/Excel/yazdırma eylemleri.

**Boş durum:** “Henüz geçmiş kaydınız oluşmadı.”

**Hata durumu:** “Geçmişin tamamı yüklenemedi. Eksik sonuçlarla belge oluşturulamaz.”

## 4. Temel akışlar

### 4.1 Teklif inceleme → niyet beyanı

1. Müşteri kalıcı bağlantıyı açar, 6 haneli anahtarı girer.
2. Genel Bakış'tan bekleyen listeyi veya Tekliflerim'den bir listeyi açar.
3. Liste düzeyi TRY/USD ve KDV dahil/hariç bilgisini görür.
4. Her ürün için niyet seçer; **İlgileniyorum** için miktar zorunlu, diğerlerinde miktar isteğe bağlı/kapalı olacak şekilde PM kararı verilir; not isteğe bağlıdır.
5. Taslak kaydedilir. Gönderilmiş liste verisi değişmez.
6. Özet ekranında ilgilenilen, kararsız ve istenmeyen ürünleri ayrı gruplarda kontrol eder.
7. Niyet beyanı metnini onaylar ve listeyi iletir.
8. Yanıt kilitlenir; tarih-saat ve referans gösterilir. Değişiklik ancak ürün sahibi revizyon açtığında yeni turda yapılır.

**Kritik koruma:** Eksik ürün yüklenmesi, geçersiz miktar veya seçilmemiş zorunlu niyet varken iletim yapılmaz.

### 4.2 İstek listesi oluşturma

1. İstek Listem → Yeni istek.
2. En az bir referans ekler: bağlantı veya fotoğraf.
3. Kısa açıklama girer; isterse tahmini miktar ve not ekler.
4. Önizlemede yalnız kendi girdiği bilgiyi görür; siteden otomatik ürün/fiyat getirildiği izlenimi oluşmaz.
5. İletir; kayıt salt okunur olur ve geçmişe eklenir.

### 4.3 Dekont bildirimi

1. Dekontlarım → Yeni bildirim.
2. İlgili teklif/listeyi seçer.
3. Dosya, ödeme tarihi, tutar/para birimi ve açıklama ekler.
4. “Bu bir ödeme ekranı değildir” metnini görür ve bildirimi iletir.
5. Sunucu teyidinden sonra referans ve inceleme durumu görünür; kayıt geçmişe eklenir.

### 4.4 Geçmiş

1. Geçmiş'e girer; tür/tarih filtresi uygular.
2. Bir hareketin o anki salt okunur ayrıntısını açar.
3. Rol süzgeci ve seçili dil korunarak antetli belgeyi görüntüler veya indirir.

## 5. Ön sipariş psikolojisine uygun metin sistemi

### Ana çerçeve

- “Sipariş ver” yerine: **“Niyet beyanımı ilet”**
- “Sepet” yerine: **“Teklif özeti”**
- “Satın al” yerine: **“İlgileniyorum”**
- “Stokta” yerine yalnız gerçek anlam varsa: **“Ön sipariş için değerlendiriliyor”**
- “Ödeme yap” yerine: **“Dekont bildir”**

### Önerilen açıklamalar

**Liste üstü:**  
“Bu liste, sizin için hazırlanan özel/ön sipariş teklifidir. Ürün tercihlerinizi ve düşündüğünüz miktarları bildirmeniz tedarik planlamamıza yardımcı olur.”

**Niyet seçenekleri:**

- **İlgileniyorum:** “Bu ürünü belirtilen miktar için değerlendirmeye almak istiyorum.”
- **Kararsızım:** “Ürünle ilgilenebilirim; karar vermeden önce ek bilgi veya süreye ihtiyacım var.”
- **İstemiyorum:** “Bu teklif kapsamında bu ürünü değerlendirmiyorum.”

**Niyet beyanı — zorunlu ibare:**  
“Bu liste için bildirdiğim ürün tercihleri ve miktarlar **ön sipariş niyet beyanımdır**; kesin sipariş, stok ayırma veya ödeme işlemi oluşturmaz. Kesin koşullar ayrıca teyit edilir.”

**Dekont bildirimi:**  
“Bu ekran ödeme almaz. Yüklediğiniz dekont, panel dışında yaptığınız kaporanın incelenmesi için bildirimdir; yükleme tek başına kabul veya tahsilat teyidi değildir.”

**İstek listesi:**  
“Aradığınız ürünü bize gösterin. Bağlantı, fotoğraf veya kısa açıklama ekleyin; ürün bilgilerini kaynaktan otomatik çekmeyiz.”

## 6. Görsel ve etkileşim ilkeleri

- Müşteri arayüzü mağaza taklidi yapmamalı; temiz bir “özel teklif dosyası + karar formu” hissi vermelidir.
- Sayfa başına tek baskın eylem kullanılmalıdır. İkincil eylemler metin düğmesi seviyesinde kalır.
- Ürün kaynağı, tedarikçi ve iç maliyet alanları DOM, indirilen dosya, yazdırma görünümü ve hata metninde dahi bulunmamalıdır.
- Niyet renkleri tek başına anlam taşımamalı; metin ve simgeyle desteklenmelidir.
- Mobilde ürün bilgisi önce, müşteri yanıtı hemen ardından gelmeli; yatay geniş tablo zorlanmamalıdır.
- İletim sonrasında düzenleme alanları kaybolmak yerine salt okunur görünür; böylece neyin gönderildiği anlaşılır.

## 7. Çıktı davranışı

| Çıktı | Sunum | Değişmez kurallar |
|---|---|---|
| HTML paylaşım/görüntüleme | Antetli, ekransız belge düzeni | Müşteri whitelist'i, seçilen tek dil |
| PDF | Kapak/üst bilgi, ürün satırları, niyet özeti, dipnot ve sayfa numarası | Uygulama menüsü/düğmesi yok; gizli ticari alan yok |
| Excel | Görünür teklif satırları + müşterinin kendi yanıtları | İç maliyet/kaynak/tedarikçi sütunu yok |
| Yazdırma | PDF ile aynı bilgi hiyerarşisi | Arka plan efektine bağlı anlam yok |

Belgenin üstünde işletme antedi, müşteri/liste bilgisi, para birimi, KDV sunumu, belge tarihi ve referans bulunur. “Ön sipariş niyet beyanı — kesin sipariş değildir” dipnotu bütün formatlarda korunur.

## 8. PM'nin şartnameye bağlaması gereken açık kararlar

1. “Kararsızım” seçeneğinde miktarın açık mı kapalı mı olacağı.
2. Teklif toplamının yalnız “İlgileniyorum” satırlarından mı hesaplanacağı; gösterilecekse “tahmini” ibaresinin standardı.
3. Dekont için kabul edilen dosya türü/boyutu ve saklama/gizlilik metni.
4. Revizyon açıldığında önceki müşteri yanıtının kopyalanıp kopyalanmayacağı.
5. Müşteri isteğinde bağlantı veya fotoğraftan hangisinin zorunlu asgari alan olacağı; bu taslak “en az biri”ni önerir.

Bu kararlar verilene kadar ilgili metinler **vizyon/taslak**, geri kalan kesin çerçeve ise **şartname girdisi** olarak ele alınmalıdır.
