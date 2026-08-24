# TedarikApp — Ayarlar Bilgi Mimarisi

**Sürüm:** 1.0  
**Hedef:** V3-B için en fazla 16 sekmeli, aranabilir ve denetlenebilir ayar yapısı  
**Kural:** Liste durum adları ayar değildir; yalnız `cikti-terimleri.json` içindeki `status.*` kayıtlarından okunur.

## 1. Bilgi mimarisi özeti

| No | Sekme | Kapsam |
|---:|---|---|
| 1 | Genel | Uygulama adı, yerel ayar, saat dilimi ve görünüm temelleri |
| 2 | Panorama | Brifing yoğunluğu, öncelik ve anomali görünümü |
| 3 | Yakalama & Eklenti | Platform, önizleme, çevrimdışı kuyruk ve seçici sağlığı |
| 4 | Gelen Kutusu & Kurallar | Deste modu, toplu işlem ve otomatik kural davranışı |
| 5 | Keşif & Skor | Varsayılan filtreler, karşılaştırma, kümeler ve skor gösterimi |
| 6 | Listeler & İş Akışı | HAZIR kapısı, revizyon, durum akışı ve liste varsayılanları |
| 7 | Kur & Para Birimleri | Kur sağlayıcısı, onay, yaş ve sapma eşikleri |
| 8 | Çeviri Sağlayıcısı | Sağlayıcı, model, gizli anahtar, kota ve bağlantı testi |
| 9 | Diller & Sözlük | Hedef diller, kaynak alan, sözlük CSV ve sürümleme |
| 10 | Çıktılar & Antet | Excel/PDF/paylaşım anteti, K55 ve tek dil kapısı |
| 11 | Paylaşım & WhatsApp | Link, anahtar, geçerlilik, WhatsApp numarası ve köprü metni |
| 12 | Firma Portalı | Firma yanıtı, otomatik kayıt, kısmi gönderim ve DDP teyidi |
| 13 | Bildirimler | Bildirim merkezi, birleştirme, saklama ve kritik görünürlük |
| 14 | Güvenlik & API | Token, oturum, hız sınırı, IP ve denetim kaydı |
| 15 | Kuyruk & Zamanlayıcı | Cron, batch, lease, retry, dead-letter ve kapasite |
| 16 | Veri & Bakım | Arşiv, saklama, yedekleme, dışa aktarma ve demo modu |

Sekme sayısı **16** olarak tutuldu. “Diller” ile “Sözlük” aynı çeviri yaşam döngüsünü; “Gelen Kutusu” ile “Kurallar” aynı karar verme akışını; “Veri” ile “Bakım” aynı saklama sorumluluğunu paylaştığı için ayrı sekmelere bölünmedi.

## 2. Mevcut ayarların taşınma haritası

| Mevcut ayar | Yeni sekme | Yeni grup | Taşıma notu |
|---|---|---|---|
| Kur değeri ve kur getir/onayla akışı | **7. Kur & Para Birimleri** | Aktif kur / Sağlayıcı ve onay | Eski tek değer, sürümlü kur snapshot yapısına taşınır; iletilen liste kilidi değişmez. |
| Çeviri sağlayıcısı/modeli/API anahtarı | **8. Çeviri Sağlayıcısı** | Sağlayıcı / Kimlik / Kota | Anahtar yalnız secret store'da tutulur; GET ve DOM'a açık dönmez. |
| Hedef dil listesi | **9. Diller & Sözlük** | Dil kapsamı | Mevcut TR/EN/ZH sırası korunur; dili kaldırmak eski alanları silmez. |
| Sözlük CSV | **9. Diller & Sözlük** | Sözlük içe aktarımı | Her başarılı aktarım yeni sözlük sürümü üretir ve çeviri cache anahtarını değiştirir. |
| Antet/logo/firma iletişim bilgileri | **10. Çıktılar & Antet** | Kurumsal antet | Excel, PDF ve paylaşım aynı snapshot'tan beslenir. |
| Eklenti/panel güvenlik tokenı | **14. Güvenlik & API** | Eklenti tokenları | Maskeli gösterim, iptal ve rotasyon kaydı zorunludur. |
| Paylaşım WhatsApp numarası | **11. Paylaşım & WhatsApp** | WhatsApp köprüsü | E.164 biçimine normalize edilir; mesaj link içerir, erişim anahtarı içermez. |

## 3. Sekme ayrıntıları

### 1 — Genel

**Gruplar:** Uygulama kimliği, bölgesel ayarlar, görünüm.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Uygulama görünen adı | Metin | `TedarikApp` | Panel başlığı ve sistem üretimi metinlerde kullanılacak bitişik marka adı. |
| Varsayılan panel dili | Tek seçim | `tr-TR` | Yönetim panelinin arayüz dili; çıktı diliyle karıştırılmaz. |
| Saat dilimi | Saat dilimi | `Europe/Istanbul` | Tarih, cron, audit ve geçerlilik hesaplarının ortak saat dilimi. |
| Tarih biçimi | Tek seçim | `dd.MM.yyyy` | Panelde insan gözüne gösterilen tarih biçimi. |
| Saat biçimi | Tek seçim | `24 saat` | Panelde 24 saatlik saat gösterimini kullanır. |
| Yoğunluk | Tek seçim | `Rahat` | Tablo ve kart aralıklarını Rahat/Kompakt seçenekleriyle belirler. |

### 2 — Panorama

**Gruplar:** Günlük brifing, öncelik, anomali görünümü.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Panorama etkin | Aç/Kapat | `Açık` | “Bugün ne var” brifing alanını gösterir. |
| Gösterilecek en fazla brifing | Sayı | `7` | Aynı anda sıralanacak koşul cümlesi üst sınırı; 3–12 arası. |
| En düşük görünür öncelik | Tek seçim | `5 — Bilgi` | Bu seviyeye kadar bütün brifingleri gösterir. |
| Aksiyon kartı üst sınırı | Sayı | `6` | Brifing altındaki önerilen eylem kartı sayısı. |
| Anomali kartlarını sabitle | Aç/Kapat | `Açık` | Kritik ve uyarı anomalilerini normal brifinglerin üstünde tutar. |
| Boş gün cümlesini döndür | Aç/Kapat | `Açık` | Hiçbir koşul yokken sakin cümle varyantlarını sırayla gösterir. |

### 3 — Yakalama & Eklenti

**Gruplar:** Platform erişimi, kullanıcı tetiklemesi, çevrimdışı kuyruk, seçici sağlığı.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Etkin platformlar | Çoklu seçim | `1688` | Eklentinin yakalama sunacağı paketli platform adaptörleri. |
| Göndermeden önce önizleme | Aç/Kapat | `Açık` | Kullanıcı ürün verisini görmeden panel API'sine gönderim yapılmasını engeller. |
| Otomatik gönderim | Aç/Kapat | `Kapalı` | Kullanıcı onayı olmadan gönderimi açmaz; v1.0'da kapalı tutulur. |
| Çevrimdışı kuyruk | Aç/Kapat | `Açık` | Ağ yokken yakalamayı kalıcı yerel kuyruğa yazar. |
| Yakalama sessizlik eşiği | Süre | `72 saat` | Bu süre başarılı yakalama olmazsa Panorama ve bildirim uyarısı üretir. |
| Asgari 24 saatlik başarı oranı | Yüzde | `%90` | Yeterli örnek varken parser sağlık uyarısını tetikler. |
| Sağlık hesabı asgari örneği | Sayı | `10` | Başarı oranı alarmından önce gereken yakalama sayısı. |
| Seçici paket kanalı | Salt okunur seçim | `Kararlı` | Paketli adaptör sürüm kanalını gösterir; uzak çalıştırılabilir mantık yüklenmez. |

### 4 — Gelen Kutusu & Kurallar

**Gruplar:** Deste modu, klavye, toplu işlem, kural şeffaflığı.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Varsayılan görünüm | Tek seçim | `Deste modu` | Gelen Kutusu açıldığında kullanılacak görünüm. |
| Klavye kısayolları | Aç/Kapat | `Açık` | J/K/Space ve ok tuşlarıyla deste akışını etkinleştirir. |
| Filtre değişince seçimi sıfırla | Aç/Kapat | `Açık` | Görünmeyen satırlara toplu işlem uygulanmasını engeller. |
| Sayfa değişince seçimi sıfırla | Aç/Kapat | `Açık` | Sayfalar arası gizli seçimi temizler. |
| Geri al süresi | Süre | `10 saniye` | Çöpe/Havuza/Listeye işlemlerinin hızlı geri alma penceresi. |
| Kural rozeti göster | Aç/Kapat | `Açık` | Otomatik veya yarı otomatik kararın kaynak kuralını kartta gösterir. |
| Toplu işlem üst sınırı | Sayı | `100` | Tek kullanıcı işleminde ele alınabilecek ürün üst sınırı. |

### 5 — Keşif & Skor

**Gruplar:** Sonuç görünümü, skor, 同款 kümeleri, karşılaştırma.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Varsayılan sonuç boyutu | Sayı | `50` | Sanal kaydırma/sayfalama veri dilimi. |
| Varsayılan sıralama | Tek seçim | `TedarikApp Skoru — yüksekten düşüğe` | Skoru görünen ürünleri ticari skorla sıralar. |
| Yetersiz veride skoru gizle | Aç/Kapat | `Açık` | Eksik metrics/karne için sayısal skor veya sahte sıfır göstermez. |
| 同款 küme kartları | Aç/Kapat | `Açık` | Aynı ürün ailesi üyelerini küme kartında toplar. |
| Karşılaştırma üst sınırı | Sayı | `6` | Matrise en fazla altı ürün alınmasını sağlar. |
| Kaydedilmiş görünüm sınırı | Sayı | `20` | Kullanıcı başına saklanabilecek görünüm sayısı. |
| Arama alanları | Çoklu seçim | `TR, ZH, EN, satıcı, kategori` | Çift dilli ve kaynak dil aramasına katılan alanlar. |

### 6 — Listeler & İş Akışı

**Gruplar:** Liste varsayılanları, geçiş kapıları, revizyon ve durum sözlüğü.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Yeni liste başlangıç durumu | Salt okunur | `status.preparing — Hazırlanıyor` | Durum adı yalnız 5B `cikti-terimleri.json` kaynağından gelir. |
| HAZIR kapısı | Aç/Kapat | `Açık` | Zorunlu alanları eksik ürünün Siparişe hazır olmasını engeller. |
| Boş liste tamamlanamaz | Aç/Kapat | `Açık` | En az bir aktif ürün satırı olmadan tamamlanma/iletim yan etkisini engeller. |
| İletim sonrası kur kilidi | Aç/Kapat | `Açık` | Gönderilmiş listede bağlı kur snapshot'ını değiştirilemez tutar. |
| İletim sonrası değişiklik | Tek seçim | `Yeni revizyon oluştur` | Önceki revision'ı salt okunur bırakır. |
| Uyarı çipi filtre uygulasın | Aç/Kapat | `Açık` | Eksiklik çipine basınca ilgili satır filtresini ve URL durumunu kurar. |
| Varsayılan fiyat geçerliliği | Sayı | `7 gün` | Yeni teklif turu için önerilen geçerlilik; firma onayı ayrıca alınır. |
| Durum sözlüğü kaynağı | Salt okunur | `cikti-terimleri.json:status.*` | Yeni serbest durum adı tanımlanmasını engeller. |

### 7 — Kur & Para Birimleri

**Gruplar:** Kur çifti, sağlayıcı, önizleme/onay, yaş ve sapma. **[MEVCUT KUR AYARI BURAYA TAŞINIR]**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Kaynak para birimi | Tek seçim | `CNY` | 1688 kaynak fiyatlarının para birimi. |
| Hedef para birimi | Tek seçim | `TRY` | Panel maliyet özetinin varsayılan para birimi. |
| Kur kaynağı | Tek seçim | `Manuel` | Manuel veya yapılandırılmış sağlayıcı adapter'ı; dış yanıt doğrudan aktif olmaz. |
| Kur getir bağlantısı | URL | `Boş` | Yapılandırılmış kur adapter'ının adresi; kaydetmeden önce doğrulanır. |
| Kullanıcı onayı zorunlu | Aç/Kapat | `Açık` | Getirilen değerin önizlemeden sonra yeni aktif sürüm olmasını sağlar. |
| Kur eskime eşiği | Süre | `24 saat` | Aktif kur bu yaşı geçince uyarı üretir. |
| Kilitli liste sapma eşiği | Yüzde | `%3` | Güncel kur ile kilitli liste kuru arasındaki ticari fark uyarısı. |
| Ondalık hassasiyeti | Sayı | `4` | Kur saklama ve hesaplama hassasiyeti; finansal toplamlar Decimal kullanır. |

### 8 — Çeviri Sağlayıcısı

**Gruplar:** Sağlayıcı, model, gizli kimlik, bağlantı testi ve kota. **[MEVCUT ÇEVİRİ AYARI BURAYA TAŞINIR]**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Çeviri etkin | Aç/Kapat | `Açık` | Arka plan çeviri işlerini etkinleştirir; kaynak ZH alanı değişmez. |
| Sağlayıcı | Tek seçim | `Yapılandırılmadı` | Paketli adapter listesinden sağlayıcı seçer. |
| Model | Metin | `Boş` | Sağlayıcının model kimliği; cache anahtarına girer. |
| API anahtarı | Gizli metin | `Boş` | Kaydedildikten sonra yalnız maskeli gösterilir; GET yanıtında geri dökülmez. |
| Bağlantı testi örnek metni | Metin | `测试产品` | Ürün oluşturmadan sağlayıcı bağlantısını sınar. |
| Kota uyarı eşiği | Yüzde | `%20` | Kalan kota bu seviyeye indiğinde uyarı üretir. |
| Kota kritik eşiği | Yüzde | `%10` | Devre kesici ve kritik bildirim için eşik. |
| Prompt sürümü | Salt okunur | `Paket sürümü` | Çeviri cache anahtarında kullanılan paketli prompt sürümü. |

### 9 — Diller & Sözlük

**Gruplar:** Dil kapsamı, kaynak alan, sözlük içe aktarımı ve sürüm. **[MEVCUT DİL/SÖZLÜK AYARLARI BURAYA TAŞINIR]**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Çıktı hedef dilleri | Sıralı çoklu seçim | `TR, EN, ZH` | Excel/PDF/paylaşım dil seçenekleri; kaldırma eski ürün alanlarını silmez. |
| Kaynak ürün dili | Tek seçim | `ZH` | Orijinal ürün başlığı, varyant ve notların kaynak dili. |
| Kaynak alanı koru | Aç/Kapat | `Açık` | ZH/orijinal değerlerin çeviri tarafından üzerine yazılmasını engeller. |
| Sözlük CSV kodlaması | Tek seçim | `UTF-8` | CSV içe aktarımının zorunlu kodlaması. |
| Çakışmada davranış | Tek seçim | `Önizle ve onay iste` | Aynı terimde farklı karşılık varsa sessiz üzerine yazmaz. |
| Aktif sözlük sürümü | Salt okunur | `Son onaylı sürüm` | Model/prompt ile birlikte çeviri cache anahtarına girer. |
| Yasak terim kontrolü | Aç/Kapat | `Açık` | Görev #4A kalite kapısında yasak karşılıkları kritik hata sayar. |
| Korunacak terim kontrolü | Aç/Kapat | `Açık` | Marka, ölçü, kod ve K55/orijinal satır öğelerini korur. |

### 10 — Çıktılar & Antet

**Gruplar:** Firma anteti, dosya üretimi, dil bütünlüğü ve K55. **[MEVCUT ANTET AYARLARI BURAYA TAŞINIR]**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Firma görünen adı | Metin | `Tilbe Home` | Excel, PDF ve paylaşım üstbilgisindeki firma adı. |
| Logo | Dosya/Görsel | `Boş` | Kurumsal antette kullanılacak yüksek çözünürlüklü logo. |
| Antet adresi | Çok satırlı metin | `Boş` | Belge üstbilgisindeki ticari adres. |
| Antet telefonu | Telefon | `Boş` | Belge iletişim alanındaki telefon. |
| Antet e-postası | E-posta | `Boş` | Belge iletişim alanındaki e-posta. |
| Altbilgi notu | Çok satırlı metin | `Boş` | Excel/PDF/paylaşım için ortak ticari dipnot. |
| K55 orijinal satırı | Aç/Kapat | `Açık` | Seçilen dil ne olursa olsun kaynak/orijinal satırı belgede korur. |
| Tek dil kalite kapısı | Aç/Kapat | `Açık` | Sistem başlıkları ve alan değerlerinde karışık dili kırmızı hata yapar. |
| Dosya adı şablonu | Metin | `{liste_adi}-{dil}-{revizyon}` | Excel/PDF dosya adını güvenli karakterlerle üretir. |

### 11 — Paylaşım & WhatsApp

**Gruplar:** Paylaşım erişimi, anahtar, geçerlilik ve WhatsApp köprüsü. **[MEVCUT PAYLAŞIM NUMARASI BURAYA TAŞINIR]**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Paylaşım etkin | Aç/Kapat | `Açık` | Liste revision'ına bağlı erişim sayfası üretimini açar. |
| Varsayılan geçerlilik | Süre | `7 gün` | Yeni paylaşımın erişim süresi. |
| Erişim anahtarı uzunluğu | Salt okunur | `6 hane` | Portal giriş sözleşmesi; kullanıcı tarafından değiştirilemez. |
| Link ve anahtarı ayrı ilet | Salt okunur | `Zorunlu` | 7A güvenlik akışını korur; aynı mesajda birleşmesine izin vermez. |
| Anahtar yenilemede eskisini iptal et | Aç/Kapat | `Açık` | Yenileme işlemini atomik yapar ve eski anahtarı geçersiz kılar. |
| WhatsApp numarası | Telefon (E.164) | `Boş` | Köprü linkinde yalnız rakamlara normalize edilecek hedef numara. |
| WhatsApp varsayılan dili | Tek seçim | `TR` | 7A mesaj şablonundan seçilecek ilk dil. |
| WhatsApp metninde anahtar | Salt okunur | `Yasak` | Köprü mesajı liste adı ve link taşır; altı haneli anahtar taşımaz. |
| Geçersiz deneme hız sınırı | Sayı/Süre | `5 deneme / 10 dakika` | Kilit ekranında kaba kuvvet denemelerini sınırlar. |

### 12 — Firma Portalı

**Gruplar:** Teklif geçerliliği, kayıt, çevrimdışı davranış, zorunlu alanlar.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Varsayılan teklif geçerliliği | Sayı | `7 gün` | Firma onay kutusunda gösterilen başlangıç değeri. |
| Otomatik kayıt aralığı | Süre | `5 saniye` | Değişiklik sonrası taslağın sunucuya yazılma gecikmesi. |
| Çevrimdışı taslak | Aç/Kapat | `Açık` | Bağlantı yokken firma yanıtını yerel taslakta korur. |
| Kısmi gönderim | Aç/Kapat | `Açık` | Yalnız tamamlanan satırların ara gönderimini sağlar. |
| Nihai gönderimde bütünlük | Aç/Kapat | `Açık` | Tüm zorunlu satırlar tamamlanmadan “Teklifi gönder” eylemini kapatır. |
| DDP KDV dahil teyidi | Aç/Kapat | `Açık` | Fiyatın Türkiye KDV'si dahil olduğunu firma onay kutusuyla doğrular. |
| Kaynak alanlar salt okunur | Salt okunur | `Zorunlu` | Ürün, varyant, miktar ve kaynak linkinin firma tarafından değiştirilmesini engeller. |
| Sürüm çatışması davranışı | Tek seçim | `Durdur ve karşılaştır` | Sessiz üzerine yazma yerine iki sürümü kullanıcıya gösterir. |

### 13 — Bildirimler

**Gruplar:** Merkez, önem düzeyi, birleştirme ve saklama.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Bildirim merkezi | Aç/Kapat | `Açık` | Olay kataloğundaki kayıtları panel içinde görünür kılar. |
| Bilgi olaylarını göster | Aç/Kapat | `Açık` | Tamamlanan işlemlerin sessiz kaybolmasını engeller. |
| Varsayılan birleştirme penceresi | Süre | `5 dakika` | Olay özelinde pencere yoksa yüksek frekanslı olayların toplama süresi. |
| Kritik bildirimi sabitle | Aç/Kapat | `Açık` | Okunana veya çözülene kadar kritik kaydı üstte tutar. |
| Okundu bildirim saklama | Süre | `90 gün` | Okunan panel bildirimlerinin saklanma süresi. |
| Çözülen kritik saklama | Süre | `365 gün` | Kritik olay geçmişinin denetim amacıyla saklanma süresi. |
| Panorama ile ilişkilendir | Aç/Kapat | `Açık` | Aynı anomalinin Panorama kartından ilgili bildirime gitmesini sağlar. |

### 14 — Güvenlik & API

**Gruplar:** Eklenti tokenları, oturum, hız sınırı, IP ve audit. **[MEVCUT TOKEN AYARI BURAYA TAŞINIR]**

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Eklenti API tokenı | Gizli metin | `Kurulumda üretilir` | Kullanıcının panelinden alınır; açık metin olarak geri gösterilmez. |
| Token son dört hanesini göster | Aç/Kapat | `Açık` | Hangi tokenın bağlı olduğunu sırrı açmadan ayırt eder. |
| Token rotasyon süresi | Süre | `180 gün` | Eski tokenın kontrollü yenileme hatırlatması. |
| Token iptalinde anında kes | Aç/Kapat | `Açık` | İptal edilen tokenla yeni yakalama kabul edilmesini engeller. |
| Panel oturum zaman aşımı | Süre | `30 dakika` | Hareketsiz yönetim oturumunu kapatır. |
| API hız sınırı | Sayı/Süre | `120 istek / dakika` | Kullanıcı/token bazlı panel API sınırı. |
| IP izin listesi | CIDR listesi | `Boş — kısıt yok` | Doldurulduğunda yönetim/API erişimini tanımlı ağlarla sınırlar. |
| Audit saklama süresi | Süre | `365 gün` | Ayar, durum, güvenlik ve replay işlemlerinin değişmez iz süresi. |

### 15 — Kuyruk & Zamanlayıcı

**Gruplar:** Çalıştırıcı, batch, lease, retry, dead-letter ve adalet.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Cron çalışma aralığı | Süre | `1 dakika` | Hazır işleri tarayan tek cron worker sıklığı. |
| Batch boyutu | Sayı | `25 iş` | Bir claim turunda alınabilecek en fazla iş. |
| Worker süre bütçesi | Süre | `30 saniye` | Cron sürecinin güvenli çıkıştan önceki çalışma bütçesi. |
| Lease süresi | Süre | `5 dakika` | İş sahibinin claim hakkı; uzun işte güvenli heartbeat ile uzatılır. |
| En fazla deneme | Sayı | `8` | Geçici hata sonrası dead durumundan önceki deneme bütçesi. |
| İlk retry beklemesi | Süre | `60 saniye` | Üstel backoff başlangıç gecikmesi. |
| En uzun retry beklemesi | Süre | `24 saat` | Tek retry için üst gecikme sınırı. |
| Jitter oranı | Yüzde | `%20` | Aynı hatadaki işlerin aynı anda yeniden başlamasını önler. |
| Dead-letter görünürlüğü | Aç/Kapat | `Açık` | Duran işi panelde neden, düzeltme ve replay işlemleriyle gösterir. |
| İş türü başına adil sıra | Aç/Kapat | `Açık` | Çeviri, medya ve zenginleştirme işlerinin birbirini aç bırakmasını engeller. |

### 16 — Veri & Bakım

**Gruplar:** Arşiv, saklama, yedekleme, dışa aktarma ve demo.

| Ad | Tip | Varsayılan | Açıklama |
|---|---|---|---|
| Otomatik arşiv | Aç/Kapat | `Kapalı` | Listeyi kullanıcı kararı olmadan arşive taşımaz. |
| Arşiv önerisi yaşı | Süre | `90 gün` | Tamamlanan/güncelliğini yitiren liste için arşiv önerisi üretir. |
| Tamamlanan iş logu saklama | Süre | `90 gün` | Başarılı kuyruk işlerinin ayrıntı saklama süresi. |
| Başarısız iş logu saklama | Süre | `365 gün` | Hata incelemesi ve tekrar için daha uzun saklama süresi. |
| Medya kaynağını koru | Aç/Kapat | `Açık` | İndirilen medya yanında kaynak URL/hash/provenance bilgisini tutar. |
| Günlük veritabanı yedeği | Aç/Kapat | `Açık` | Panel verisinin günlük yedek planına katılmasını sağlar. |
| Yedek saklama süresi | Süre | `30 gün` | Günlük yedeklerin saklama penceresi. |
| Ayarları dışa aktar | Eylem | `JSON indir` | Gizli değerleri içermeyen sürümlü yapılandırma çıktısı üretir. |
| Demo modu | Aç/Kapat | `Kapalı` | Açıldığında yalnız DM-001–DM-100 kurgusal veri setini kullanır; canlı veriye karışmaz. |

## 4. Meta katman — bütün sekmelerde ortak

Her ayar sekmesinde aynı üst katman bulunur:

1. **Ayar ara:** Ayar adı, açıklama, grup ve eş anlamlılar üzerinde çalışır; sonuç sekmeler arasında yönlendirir.
2. **Son değişiklik izi:** Her ayarda son değiştiren, zaman, kaynak ve değişiklik nedeni görünür. Gizli değerlerde eski/yeni açık metin gösterilmez; yalnız “değiştirildi” kaydı tutulur.
3. **Değişiklik geçmişi:** Yetkili kullanıcı önceki sürümleri karşılaştırabilir; geri alma yeni audit olayı ve yeni sürüm üretir.
4. **Kaydedilmemiş değişiklik uyarısı:** Sekmeden ayrılmadan önce kullanıcıyı uyarır; başarısız kayıt eski ayarı silmez.
5. **Varsayılana dön:** Tek ayarı veya grubu sıfırlar; etki önizlemesi ve onay gerektirir.
6. **Bağımlılık açıklaması:** Başka ekranı etkileyen ayar, etkilenecek alanları kaydetmeden önce listeler.

## 5. Uygulama notları

- Ayar anahtarları UI metninden bağımsız, kararlı ve sürümlü olmalıdır.
- Gizli alanların maskesi gerçek uzunluğu ele vermemeli; kayıttan sonra açık değer API/HTML/loglara dönmemelidir.
- Kur, sözlük, antet ve paylaşım ayarları gelecekteki üretimleri etkiler; eski liste/çıktı/paylaşım revision snapshot'ları yerinde değişmez.
- Ayar değişikliği kendi başına liste, çeviri, çıktı veya paylaşım üretmez; kullanıcı eylemi ya da açık zamanlanmış iş gerekir.
- Liste durumu ekleme/değiştirme bu ekranın yetkisi değildir. Durum sözlüğünün tek kaynağı Görev #5B `cikti-terimleri.json` dosyasıdır.
