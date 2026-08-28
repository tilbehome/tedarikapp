# Görev 25B — İthalatçı Paneli Taslağı

**Belge türü:** Araştırma destekli arayüz ve içerik taslağı  
**Hazırlık amacı:** TedarikApp V3-N öncesi PM şartnamesine girdi  
**Gözlem/taslak tarihi:** 28 Ağustos 2026  
**Karar statüsü:** Bu belge nihai şartname değildir.

## 1. Ana amaç ve sınır

İthalatçı paneli, kendisine gönderilen birden çok ürün listesine ürün satırı bazında **DDP Türkiye + KDV dahil fiyat**, açıklama veya “bulunamadı” yanıtı verdiği kalıcı fiyatlama çalışma alanıdır. Tek listelik, tek kullanımlık cevap sayfası değil; açık işler, revizyon turları, Excel gel-git geçmişi ve yalnız kendi performans özetini bir arada tutan rol panelidir.

### Şartname — kesin çerçeve

- Erişim kişiye özel kalıcı bağlantı + 6 haneli anahtarla sağlanır; üyelik, şifre ve kayıt formu yoktur.
- Rol sunucuda token üzerinden çözülür. Panel, arama, geçmiş ve bütün çıktılar yalnız ithalatçı whitelist'inden beslenir.
- Gönderilen liste/ürün verisi değiştirilemez; DDP yanıtı ayrı katmandadır.
- Her ürün için DDP + KDV fiyat, açıklama veya “bulunamadı” işareti verilebilir.
- Yanıt tur mantığıyla ilerler: taslak düzenlenebilir; gönderimle kilitlenir; değişiklik yalnız ürün sahibinin açtığı yeni revizyon turunda yapılır.
- Excel dışa aktarım/içe aktarım desteklenir; çevrim içi ekran ile aynı rol ve tur kuralları geçerlidir.
- Panel ödeme, sanal POS, üyelik, otomatik site verisi çekme ve e-posta/SMS içermez.
- HTML, PDF, Excel ve yazdırma çıktıları aynı ithalatçı süzgeciyle ve seçilen tek dilde eksiksiz üretilir.

### Vizyon — PM'nin doğrulayacağı tasarım yönü

- Ana görünüm “kaç liste var?”dan çok “hangi listede ne eksik ve hangi tur açık?” sorusunu yanıtlamalıdır.
- Liste detayı, yoğun elektronik tabloyu taklit etmek yerine sabit ürün kimliği + dar fiyatlama formu düzeninde olmalıdır; Excel büyük hacim için tamamlayıcı kanal kalmalıdır.
- Kendi özeti, puanlama veya başka firmalarla kıyas değil, ithalatçının kendi işlem kayıtlarının tanımlı hesabı olmalıdır.
- Dışa aktarılan belge uygulama ekranı değil; tur ve referans bilgili fiyat yanıtı belgesi olmalıdır.

## 2. Menü ve bilgi mimarisi

Menü dört öğeyle sınırlıdır.

| Menü | Doğrudan hizmet ettiği amaç | İçerik kapsamı |
|---|---|---|
| Genel Bakış | Açık fiyatlama işlerini ve eksikleri görmek | Açık listeler, yaklaşan tarihler, taslaklar, son turlar |
| Fiyat Talepleri | Çoklu listeyi açmak ve DDP+KDV yanıtlamak | Liste dizini, ürün satırları, tur, Excel, gönderim |
| Geçmiş | Gönderilmiş ve revize edilmiş yanıtları izlemek | Liste/tur zaman çizgisi, salt okunur yanıtlar ve çıktılar |
| Kendi Özetim | Kendi iş hacmini ve yanıt durumunu görmek | Tanımlı, izlenebilir öz metrikler; kıyas/ranking yok |

Arama, dil, çıktı ve erişim yardımı üst çubuk aracıdır. “Excel” ayrı menü değildir; ilgili liste/tur içinde çalışır.

## 3. Ekran envanteri

### 3.1 Genel Bakış

**Amaç:** İthalatçının açık işlerini öncelik ve tamamlanma düzeyiyle görmesi.

**Ana bloklar**

1. İşletme adı, ithalatçı kimliği ve seçili dil.
2. “Açık işler”: yanıt bekleyen liste sayısı, açık tur, taslak, son tarihi yaklaşan liste.
3. Öncelikli liste kartları: liste adı/kodu, tur no, gönderim/son yanıt tarihi, yanıtlanan/kalan satır.
4. Son hareketler: gönderim, revizyon açılması, Excel içe aktarımı ve doğrulama sonucu.
5. Birincil eylem: en yakın tarihli eksik listeyi aç.

**Boş durum:** “Şu anda fiyatlamanız için açık bir liste yok. Önceki yanıtlarınıza Geçmiş bölümünden ulaşabilirsiniz.”

**Hata durumu:** “Açık işler özeti yüklenemedi. Taslaklarınızın durumu doğrulanmadan yeni işlem başlatmayın; yeniden deneyin.”

### 3.2 Fiyat Talepleri — liste dizini

**Amaç:** Birden çok listeyi birbirine karıştırmadan bulmak, filtrelemek ve doğru açık tura girmek.

**Ana bloklar**

1. Liste satırı: liste adı, değişmez liste referansı, güncel tur, gönderim tarihi, varsa son tarih.
2. Durum: yanıt bekliyor, taslak, eksik, gönderildi/kilitli, revizyon açık, kapandı.
3. İlerleme: fiyatlanan + bulunamadı işaretlenen satır / toplam satır.
4. Temel arama ve filtre: açık/kapalı, eksik/tamam, tarih.
5. Bağlamsal eylemler: turu aç, salt okunur yanıtı gör, geçerli tur Excel şablonunu indir.

**Boş durum:** “Size atanmış fiyat talebi bulunmuyor.”

**Hata durumu:** “Fiyat talepleri alınamadı. Liste bulunamadı sonucu varsayılmadı; yeniden deneyin.”

### 3.3 Fiyat talebi detayı — çevrim içi yanıt

**Amaç:** Gönderilen ürün satırını değiştirmeden DDP Türkiye KDV dahil fiyat veya bulunamadı yanıtı vermek.

**Ana bloklar**

1. Sabit başlık: liste referansı, tur no, açık/kilitli durumu, son tarih, fiyat para birimi.
2. Tur şeridi: önceki turlar salt okunur; yalnız açık tur düzenlenebilir.
3. Ürün satırı: ürün kodu/görseli, ad/varyant, istenen miktar ve ithalatçıya izinli diğer kaynak bilgiler salt okunur.
4. Yanıt alanı:
   - bulundu: DDP Türkiye KDV dahil birim fiyat + para birimi + açıklama;
   - bulunamadı: işaret + açıklama;
   - henüz yanıtlanmadı: taslakta kalabilir, gönderim kontrolünde eksik sayılır.
5. Satır ve liste doğrulamaları: pozitif fiyat, para birimi, bulunamadı açıklaması, eksik satırlar.
6. İlerleme ve hata özeti; hatalı satıra doğrudan gitme.
7. Önizleme ve “Yanıt turunu gönder” eylemi.
8. Gönderim sonrası kilitli görünüm: referans, tarih-saat, satır sayıları ve çıktı eylemleri.

**Boş durum:** Liste mevcut fakat ürün yoksa “Bu fiyat talebinde yanıtlanacak ürün yok. Tur gönderilemez.”

**Hata durumu:** Liste kısmen yüklenirse gönderim ve Excel oluşturma kapatılır: “Tüm ürünler yüklenemedi. Eksik görünümle yanıt turu gönderilemez.”

### 3.4 Excel gel-git — liste içi çalışma yüzeyi

**Amaç:** Çok satırlı fiyatlamayı çevrim dışı hazırlayıp doğru liste ve açık tura güvenli biçimde geri almak.

**Ana bloklar**

1. “Geçerli tur şablonunu indir”: dosyada liste referansı, tur no ve yalnız izinli ürün/yanıt sütunları.
2. Kısa kullanım notu: gönderilen ürün hücreleri değiştirilemez; yalnız yanıt sütunları doldurulur.
3. Dosya yükleme alanı ve dosya adı/tarih bilgisi.
4. İçe aktarma ön kontrolü:
   - doğru liste mi;
   - doğru ve hâlâ açık tur mu;
   - satır kimlikleri eşleşiyor mu;
   - zorunlu alan/biçim hatası var mı;
   - yinelenen veya tanınmayan satır var mı.
5. Sonuç özeti: uygulanabilir, hatalı, değişmeden kalan satırlar. Hatalar satır bazında indirilebilir raporda gösterilir.
6. “Doğrulanan yanıtları taslağa uygula”; bu işlem turu göndermez.

**Boş durum:** Dosya seçilmeden “Bu açık tur için dışa aktardığınız şablonu doldurup buraya yükleyin.”

**Hata durumu:** Eski/başka listeye ait dosya reddedilir; hiçbir satır sessizce uygulanmaz. “Dosya bu açık tura ait değil. Güncel şablonu yeniden indirin.”

### 3.5 Geçmiş

**Amaç:** Her listenin bütün gönderim ve revizyon turlarını değişmez kayıt olarak görmek.

**Ana bloklar**

1. Liste/tur zaman çizgisi: tur açıldı, taslak, gönderildi-kilitlendi, revizyon istendi, yeni tur açıldı, kapandı.
2. Her tur için gönderim zamanı, referans, yanıtlanan/bulunamadı/eksik sayıları.
3. Önceki yanıtın salt okunur satır ayrıntısı; yeni turla fark görünümü bir vizyon seçeneğidir.
4. O tur için üretilen rol-süzgeçli HTML/PDF/Excel/yazdırma çıktıları.

**Boş durum:** “Henüz gönderilmiş bir fiyat yanıtı yok.”

**Hata durumu:** “Tur geçmişinin tamamı yüklenemedi. Eksik geçmiş üzerinden çıktı oluşturulamaz.”

### 3.6 Kendi Özetim

**Amaç:** İthalatçının yalnız kendi TedarikApp fiyatlama hareketlerini tanımlı hesaplarla izlemesi.

**Ana bloklar**

1. Dönem seçimi.
2. Liste hacmi: alınan liste, açık liste, en az bir turu gönderilmiş liste.
3. Satır kapsamı: alınan ürün satırı, fiyatlanan satır, bulunamadı satır, açık turda yanıtsız satır.
4. Tamamlama oranı: `(fiyatlanan + bulunamadı) / açık turdaki toplam satır`; payda ve dönem görünür.
5. Zamanında gönderim: yalnız son tarih tanımlı listelerde, son tarihe kadar gönderilmiş ilk tur / son tarihli liste. Payda yoksa oran gösterilmez.
6. Revizyon hacmi: açılan revizyon turu ve gönderilen revizyon turu sayısı.
7. “Nasıl hesaplandı?” açıklaması; kıyas, puan, sıralama veya “iyi/kötü” etiketi yok.

**Boş durum:** “Seçilen dönemde özet oluşturacak işlem bulunmuyor.”

**Hata durumu:** “Bazı kayıtlar hesaplamaya katılamadı. Eksik veriyle oran gösterilmedi.”

## 4. Temel akışlar

### 4.1 Çevrim içi DDP+KDV fiyatlama

1. İthalatçı kalıcı bağlantıyı açar ve 6 haneli anahtarı girer.
2. Genel Bakış veya Fiyat Talepleri'nden açık liste/turu seçer.
3. Gönderilmiş ürün bilgilerini salt okunur inceler.
4. Her satırda DDP Türkiye KDV dahil fiyat ve para birimi girer ya da “bulunamadı” seçip açıklama yazar.
5. Taslağı kaydeder; eksik/hatalı satır özetinden düzeltir.
6. Gönderim önizlemesinde liste referansı, tur no ve sayıları kontrol eder.
7. Turu gönderir; tur kilitlenir ve referans üretilir.
8. Ürün sahibi revizyon açarsa önceki tur salt okunur kalır, yeni tur düzenlenebilir olur.

### 4.2 Excel gel-git

1. Açık turdan güncel Excel şablonunu indirir.
2. Salt okunur ürün sütunlarına dokunmadan yanıt sütunlarını doldurur.
3. Aynı açık tura dosyayı yükler.
4. Sistem liste/tur/satır eşleşmesini ve alanları doğrular; hataları uygulamadan önce gösterir.
5. İthalatçı doğrulanan verileri taslağa uygular.
6. Çevrim içi önizlemede sonucu kontrol eder ve ayrıca gönderir.

**Kritik koruma:** Excel yüklemek gönderim değildir; eski tur şablonu yeni tura sessizce taşınmaz; tanınmayan satır en yakın ürüne eşlenmez.

### 4.3 Revizyon turu

1. Gönderilmiş tur kilitlidir.
2. Ürün sahibi revizyon açar; kapsam ve açıklama görünür.
3. Yeni tur önceki yanıtı referans olarak gösterebilir; düzenlenebilir alan yalnız yeni turdur.
4. Yeni tur gönderilince o da kilitlenir; iki tur geçmişte ayrı tarih/referansla saklanır.

### 4.4 Kendi özeti

1. Dönem seçilir.
2. Her metrik pay/payda ve kapsamıyla gösterilir.
3. Kullanıcı bir metriği açarak hangi listelerin hesaba katıldığını görür.
4. Seçilen tek dilde kendi özet çıktısı alınır.

## 5. Tek listelik portaldan kalıcı panele geçiş gereksinimleri

| Tek listelik görünümde eksik kalan | Kalıcı panel gereksinimi | Kullanıcıya görünür sonuç |
|---|---|---|
| Yalnız açılan listenin bağlamı | Kişiye özel kapsamda çoklu liste dizini | İthalatçı bütün açık ve geçmiş listelerini ayırt eder |
| Tek gönderim | Numaralı, tarihli ve kilitlenen revizyon turları | Hangi fiyatın hangi turda verildiği karışmaz |
| Geçici bağlantı hissi | Aynı kalıcı bağlantıda rol kapsamlı ana sayfa | Yeni liste geldikçe aynı çalışma alanı kullanılır |
| Satır bazında tek oturum | Sunucu teyitli taslak ve açık iş özeti | Yarım kalan çalışma görünür olur |
| Excel yalnız dosya alışverişi | Liste/tur bağlı şablon, ön doğrulama ve içe aktarma özeti | Yanlış dosyanın yanlış listeye uygulanması engellenir |
| Son gönderim görünümü | Liste + tur zaman çizgisi ve salt okunur geçmiş | Önceki yanıtlar kanıtlanabilir kalır |
| Genel dashboard iddiası | Yalnız kendi işlem verisinden öz metrik | Kıyas veya ölçülmemiş performans iddiası oluşmaz |
| Ekran odaklı çıktı | Rol süzgeçli, tur referanslı belge düzeni | HTML/PDF/Excel/yazdırma aynı kapsamı taşır |

Kalıcı panel, yeni bir üyelik sistemi anlamına gelmez. Kalıcılık bağlantının aynı kişiye tekrar tekrar atanmış listeleri göstermesidir; 6 haneli anahtar doğrulaması ve sunucu tarafı rol çözümü korunur.

## 6. Durum ve kilit dili

- **Taslak:** “Yanıtlarınız kaydedildi; henüz ürün sahibine iletilmedi.”
- **Eksik:** “Gönderim için zorunlu satır yanıtları tamamlanmadı.”
- **Gönderildi — kilitli:** “Bu tur iletildi ve değiştirilemez. Revizyon gerekirse ürün sahibi yeni tur açar.”
- **Revizyon açık:** “Önceki tur değişmeden korunur. Değişikliklerinizi yeni turda hazırlayın.”
- **Kapandı:** “Bu liste için açık fiyatlama turu yok.”

“Kaydet” ile “Gönder” görsel ve metinsel olarak kesin ayrılmalıdır.

## 7. Çıktı davranışı

| Çıktı | Sunum | Değişmez kurallar |
|---|---|---|
| HTML | Liste/tur başlıklı fiyat yanıtı | İthalatçı whitelist'i, seçilen tek dil |
| PDF | Belge üst bilgisi, satırlar, DDP+KDV açıklaması, tur ve imza/teyit alanı gerekiyorsa | Uygulama menüsü yok; başka rol alanı yok |
| Excel | O turun izinli ürün sütunları ve ithalatçı yanıtları | Liste/tur kimliği doğrulanır; yanlış ürüne fiyat yazılmaz |
| Yazdırma | PDF ile aynı bilgi hiyerarşisi | Kilit/tur ve para birimi/KDV bilgisi korunur |

Çıktıda “DDP Türkiye — KDV dahil” ifadesi fiyat kapsamının yanında görünür. “Bulunamadı” satırları sıfır fiyat gibi yorumlanmaz; ayrı durum ve açıklamayla çıkar.

## 8. PM'nin şartnameye bağlaması gereken açık kararlar

1. Bir turda bütün satırların yanıtlanması zorunlu mu, yoksa kısmi gönderim mevcut `portal.partial.*` akışıyla açık mı olacak?
2. Açıklamanın bulundu fiyatlarda zorunlu olup olmadığı; bulunamadıda mevcut çekirdek metin zorunlu kabul eder.
3. Excel içe aktarımında kısmen hatalı dosyada yalnız doğrulanan satırların uygulanıp uygulanmayacağı. Bu taslak kullanıcı onayıyla uygulanmasını önerir.
4. Yeni revizyon turunun önceki değerleri taslak olarak taşıyıp taşımayacağı.
5. Kendi özetinde gösterilecek dönem seçenekleri ve son tarih bulunmayan listelerin zamanında gönderim paydasından çıkarılma kuralı.

Bu kararlar verilene kadar ilgili noktalar **vizyon/taslak**, kesin çerçeve ise **şartname girdisi** olarak ele alınmalıdır.
