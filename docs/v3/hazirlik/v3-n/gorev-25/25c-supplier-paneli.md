# Görev 25C — Çinli Üretici Paneli Taslağı

**Belge türü:** Araştırma destekli arayüz ve içerik taslağı  
**Hazırlık amacı:** TedarikApp V3-N öncesi PM şartnamesine girdi  
**Gözlem/taslak tarihi:** 28 Ağustos 2026  
**Karar statüsü:** Bu belge nihai şartname değildir.

## 1. Ana amaç ve sınır

Çinli üretici paneli, kendisine atanmış RFQ listelerinde her ürün için **bulundu / bulunamadı / alternatif**, EXW veya FOB fiyat, MOQ, termin ve paket/koli bilgisi verdiği hafif, ZH öncelikli kalıcı çalışma alanıdır. Pazar yeri, mesajlaşma ağı veya sipariş/ödeme sistemi değildir.

### Şartname — kesin çerçeve

- Erişim kişiye özel kalıcı bağlantı + 6 haneli anahtarla sağlanır; üyelik, şifre ve kayıt formu yoktur.
- Rol sunucuda token üzerinden çözülür. Panel ve bütün çıktılar yalnız üretici whitelist'inden beslenir.
- Gönderilen RFQ ürün/veri katmanı değiştirilemez; üretici yanıtı ayrı katmandadır.
- Her ürün yanıtı bulundu, bulunamadı veya alternatif durumlarından birini kullanır.
- Bulundu/alternatif yanıtında uygun alanlar: EXW/FOB fiyat, para birimi, MOQ, termin, paketleme, koli içi adet, koli ölçüleri ve ağırlık.
- Arayüz ZH-first'tür; seçilen dilde ekran ve çıktı bütünüyle tek dilde olmalıdır.
- Sayfa hafif ve kesintili bağlantıya toleranslı olmalıdır.
- Sunucu kaynak bağlantıdan otomatik veri çekmez.
- Panelde ödeme/sanal POS, üyelik, e-posta ve SMS yoktur.
- HTML, PDF, Excel ve yazdırma çıktıları aynı üretici süzgeciyle ve seçilen tek dilde eksiksiz üretilir.

### Vizyon — PM'nin doğrulayacağı tasarım yönü

- Üretici, ana sayfada kendisine atanmış RFQ'ları ve kalan ürünleri görmeli; kurumsal dashboard kalabalığı oluşmamalıdır.
- RFQ detayı masaüstünde yoğun liste, mobilde ürün ürün ilerleyen form olarak çalışabilmelidir.
- Çevrimdışı tolerans “gönderildi” izlenimi vermemeli; yerel taslak, senkron bekliyor ve sunucuya ulaştı durumları açıkça ayrılmalıdır.
- WeChat/QR yalnız erişimi kolaylaştıran dağıtım biçimi olmalı; WeChat hesabı, mini program veya QR ile kimlik doğrulama zorunluluğuna dönüşmemelidir.
- Dışa aktarılan belge panel görünümü değil, sade RFQ yanıtı olmalıdır.

## 2. Menü ve bilgi mimarisi

Menü dört öğeyle sınırlıdır. ZH arayüzünde Çince etiket birincil; dil seçildiğinde diğer diller karışmadan tamamen dönüşür.

| ZH menü | TR karşılığı | Doğrudan hizmet ettiği amaç |
|---|---|---|
| 工作台 | Genel Bakış | Açık RFQ ve kalan işi görmek |
| 询价单 | RFQ'lar | Ürünleri incelemek ve fiyat/üretim yanıtı vermek |
| 历史记录 | Geçmiş | Gönderilmiş yanıt ve revizyonları görmek |
| 我的概览 | Kendi Özetim | Yalnız kendi RFQ hacmi ve tamamlanmasını görmek |

Arama, dil, QR/bağlantı paylaşımı ve çıktı araçları menü öğesi değildir. “Alternatif ürün” ayrı menü değil, ilgili ürünün yanıt akışıdır.

## 3. Ekran envanteri

### 3.1 工作台 / Genel Bakış

**Amaç:** Üreticinin yeni/açık RFQ'ları, son tarihi ve kalan ürünleri hızlıca görmesi.

**Ana bloklar**

1. Alıcı işletme adı, üretici için görünen kimlik ve seçili dil.
2. Açık işler: yanıt bekleyen RFQ, taslak, senkron bekleyen değişiklik, revizyon.
3. RFQ kartları: RFQ adı/kodu, ürün sayısı, yanıtlanan/kalan, varsa son tarih.
4. Son yerel/sunucu kayıt zamanı; çevrimdışıysa açık durum bandı.
5. Birincil eylem: eksik RFQ'ya devam et.

**Boş durum:** “当前没有待回复的询价单。您可以在历史记录中查看已提交的回复。” / “Şu anda yanıt bekleyen RFQ yok. Gönderilmiş yanıtlarınızı Geçmiş'te görebilirsiniz.”

**Hata durumu:** “工作台加载失败。已保存的草稿不会被标记为已提交，请重试。” / “Genel bakış yüklenemedi. Kaydedilmiş taslaklar gönderilmiş sayılmaz; yeniden deneyin.”

### 3.2 询价单 / RFQ dizini

**Amaç:** Birden çok RFQ'yu durum, tarih ve tamamlanma düzeyiyle ayırt etmek.

**Ana bloklar**

1. RFQ satırı: değişmez referans, başlık, gönderim/son tarih, ürün sayısı.
2. Durum ve ilerleme: yanıt bekliyor, taslak, kısmi, gönderildi-kilitli, revizyon açık.
3. Hafif filtre: açık/tamamlanan ve metin araması.
4. Bağlamsal eylem: devam et, görüntüle, güncel Excel'i indir.

**Boş durum:** “未分配询价单。” / “Size atanmış RFQ bulunmuyor.”

**Hata durumu:** “询价单列表加载失败。请重试。” / “RFQ listesi yüklenemedi. Yeniden deneyin.”

### 3.3 RFQ detayı ve ürün yanıtı

**Amaç:** Alıcının ürün talebini değiştirmeden üretilebilirlik, fiyat ve lojistik yanıtı vermek.

**Ana bloklar**

1. Sabit başlık: RFQ referansı, yanıt turu/durumu, son tarih ve ilerleme.
2. Alıcı talebi: ürün görseli, ad/kod, varyant/özellik, talep miktarı ve izinli ekler salt okunur.
3. Ürün durumu:
   - **可供 / Bulundu:** EXW veya FOB, birim fiyat, para birimi, MOQ, termin, paket/koli alanları;
   - **无法提供 / Bulunamadı:** açıklama zorunlu;
   - **可提供替代品 / Alternatif:** alternatif bağlantı veya fotoğraf, açıklama ve ardından fiyat/MOQ/termin/paket alanları.
4. Alan sırası: durum → ticari teslim şekli/fiyat → MOQ → termin → paket/koli → açıklama. Bu sıra RFQ kararını önce, ayrıntıyı sonra toplar.
5. Ürünler arası önceki/sonraki geçiş ve görünür kaydetme durumu.
6. Eksik/hatalı yanıt özeti ve ilgili ürüne gitme.
7. Gönderim özeti ve açık “提交报价 / Teklifi gönder” eylemi.
8. Gönderim sonrası salt okunur kayıt; revizyon yeni tur olarak açılır.

**Boş durum:** RFQ'da ürün yoksa “该询价单没有可回复的产品，无法提交。” / “Bu RFQ'da yanıtlanacak ürün yok; gönderim yapılamaz.”

**Hata durumu:** Ürünlerin bir kısmı yüklenemezse gönderim engellenir: “产品未完全加载，不能提交不完整的报价。” / “Ürünlerin tamamı yüklenmedi; eksik görünümle teklif gönderilemez.”

### 3.4 Alternatif ürün önerme — ürün içi akış

**Amaç:** Asıl ürün sağlanamıyorsa karşılaştırılabilir bir alternatifi asgari, elle girilmiş kanıtla sunmak.

**Ana bloklar**

1. “Alternatif” durumu seçildiğinde asıl ürün salt okunur üstte kalır.
2. En az bir referans: alternatif ürün bağlantısı veya fotoğraf.
3. Zorunlu kısa açıklama: hangi özelliğin eşdeğer, hangi özelliğin farklı olduğu.
4. Alternatifin EXW/FOB fiyatı, para birimi, MOQ, termin ve paket/koli bilgileri.
5. Uyarı: “链接仅供参考；系统不会自动读取网站数据。” / “Bağlantı yalnız referanstır; sistem siteden otomatik veri almaz.”
6. Önizleme: asıl talep ve alternatif yan yana, ama alıcının talebi değişmeden.

**Boş durum:** “请添加替代产品链接或图片，并说明差异。” / “Alternatif ürün bağlantısı veya fotoğrafı ve fark açıklamasını ekleyin.”

**Hata durumu:** Fotoğraf yüklenemezse bağlantı ve metin korunur; referans olmadan alternatif tamamlandı sayılmaz.

### 3.5 历史记录 / Geçmiş

**Amaç:** Gönderilmiş RFQ yanıtlarını ve revizyon turlarını salt okunur görmek.

**Ana bloklar**

1. RFQ/tur zaman çizgisi ve gönderim referansı.
2. Bulundu, bulunamadı, alternatif ve yanıtsız ürün sayıları.
3. O turda iletilen ürün yanıtı; sonraki tur bunu değiştirmez.
4. Rol-süzgeçli HTML/PDF/Excel/yazdırma eylemleri.

**Boş durum:** “暂无已提交记录。” / “Henüz gönderilmiş yanıt yok.”

**Hata durumu:** “历史记录未完整加载，暂时不能导出。” / “Geçmiş tam yüklenmedi; şu anda çıktı alınamaz.”

### 3.6 我的概览 / Kendi Özetim

**Amaç:** Üreticinin yalnız kendi RFQ yanıt hacmini ve açık işini görmesi.

**Ana bloklar**

1. Dönem seçimi.
2. Alınan/gönderilen/açık RFQ sayıları.
3. Bulundu, bulunamadı, alternatif ve açık ürün satırı sayıları.
4. Tamamlama oranı: `(bulundu + bulunamadı + alternatif) / açık turdaki toplam ürün`; payda görünür.
5. Revizyon turu sayısı.
6. Hesap açıklaması; başka üreticiyle kıyas, puan veya ölçülmemiş “başarı” iddiası yok.

**Boş durum:** “所选期间没有可汇总的数据。” / “Seçilen dönemde özetlenecek veri yok.”

**Hata durumu:** “部分记录无法计算，因此不显示比例。” / “Bazı kayıtlar hesaplanamadığı için oran gösterilmiyor.”

## 4. Temel akışlar

### 4.1 RFQ yanıtlama

1. Üretici kalıcı bağlantıyı açar, 6 haneli anahtarı girer.
2. 工作台 veya 询价单 içinden açık RFQ'yu seçer.
3. Alıcının ürün verisini salt okunur inceler.
4. Bulundu / bulunamadı / alternatif seçer.
5. Duruma göre zorunlu alanları tamamlar.
6. Taslağı kaydeder; bağlantı yoksa “yerelde kaydedildi, senkron bekliyor” görünür.
7. Gönderim özetinde ürün sayılarını ve eksikleri kontrol eder.
8. Çevrim içiyken gönderir; yalnız sunucu teyidinden sonra “gönderildi” ve referans görünür.

### 4.2 Alternatif ürün önerme

1. Asıl ürün için “Alternatif” seçilir.
2. Bağlantı veya fotoğraf eklenir; sunucu otomatik veri çekmez.
3. Eşleşen ve farklı özellikler açıklanır.
4. Alternatifin EXW/FOB fiyat, MOQ, termin ve paket bilgileri girilir.
5. Asıl talep + alternatif önizlenir ve RFQ yanıtının parçası olarak gönderilir.

### 4.3 Kesintili bağlantıda çalışma

1. Bağlantı kesilince üst bantta çevrimdışı durumu ve son başarılı sunucu kaydı görünür.
2. Metin/sayısal taslak değişiklikleri cihazda bekleyen olarak işaretlenir.
3. Bağlantı gelince senkron başlar; başarılı/başarısız sonucu açıkça gösterilir.
4. Sunucudaki tur bu sırada kilitlenmiş/değişmişse otomatik üzerine yazılmaz; kullanıcı mevcut tur durumunu görür.
5. Gönderim yalnız çevrim içi sunucu teyidiyle tamamlanır.

### 4.4 Geçmiş ve çıktı

1. Geçmişten RFQ/tur seçilir.
2. Salt okunur yanıt açılır.
3. Seçilen dil ve üretici whitelist'i korunarak HTML/PDF/Excel/yazdırma çıktısı oluşturulur.

## 5. ZH-first tasarım notları

### Dil ve terminoloji

- Varsayılan dil ürün sahibinin kişi/link atamasında ZH olabilir; kullanıcı dili değiştirdiğinde bütün ekran ve çıktı yeniden tek dilde üretilir.
- Basitleştirilmiş Çince kullanılır. Ticari kısaltmalar açıklamayla verilir: **EXW（工厂交货）**, **FOB（船上交货）**, **MOQ（最小起订量）**. Bunlar karışık arayüz değil, ZH metindeki sektör kısaltmalarıdır.
- Çince metin, İngilizce cümle yapısının kelime kelime çevirisi olmamalıdır. Eylemler kısa ve doğrudan olmalıdır: 保存草稿, 提交报价, 提供替代品.
- Sayı alanlarında birim etiketi alanın hemen yanında bulunmalıdır; yalnız renk veya yer tutucuya bırakılmamalıdır.

### Düzen

- Mobil öncelikli tek sütunda ürün talebi → yanıt durumu → fiyat → MOQ/termin → paket sırası korunur.
- Masaüstünde ürün referansı solda, üretici yanıtı sağda sabit iki bölmeli düzen kullanılabilir.
- Uzun formda sabit ilerleme ve “son kaydedildi” göstergesi bulunur.
- Büyük dekoratif görsel, otomatik video ve gereksiz animasyon kullanılmaz. Ürün görselleri iş için gerektiği ölçüde gösterilir.
- Hata, boş durum ve çevrimdışı metinleri ZH arayüzde tamamen Çince olmalıdır; TR/EN yedek metni aynı anda gösterilmez.

### İçerik güvenliği ve açıklık

- “Teklif gönderildi” yalnız sunucu teyidinden sonra kullanılır.
- EXW/FOB seçimi fiyat alanından önce gelir; teslim şekli seçilmeden fiyat tamamlanmış sayılmaz.
- Paket/koli alanları kullanıcıya bölünmüş ve birimli verilir; tek serbest metin içine gömülmez.
- Asıl ürün ile alternatif ürün görsel olarak ayrılır; alternatif, asıl talebin üzerine yazmaz.

## 6. WeChat ve QR paylaşım gerçekleri

### Şartnameye uygun öneri

- QR kod, **aynı kişiye özel kalıcı panel bağlantısını** kodlar. Yeni bir erişim yöntemi veya WeChat girişi değildir.
- 6 haneli anahtar QR içine gömülmez; farklı kanaldan veya ayrı metin olarak paylaşılır. Böylece QR görselinin tek başına ele geçirilmesi anahtarı da taşımaz.
- WeChat konuşmasında hem tıklanabilir bağlantı hem QR görseli paylaşılabilir. QR, masaüstünde bağlantıyı telefona aktarmak için; bağlantı ise aynı telefonda doğrudan açmak için yararlıdır.
- Açılış ekranında işletme adı ve beklenen alan adı görünür olmalıdır. Kullanıcı QR hedefini doğrulayabilmelidir.
- “WeChat'te açılmazsa bağlantıyı kopyala / sistem tarayıcısında aç” yedeği bulunmalıdır.
- WeChat Mini Program, Official Account, kişi ekleme veya WeChat ile oturum açma bu sürümün parçası değildir.

### Araştırma notu

Tencent'in kamuya açık WeChat MSDK belgesi, QR ile giriş ile arkadaşlara/Moments'a paylaşımı ayrı yetenekler olarak tanımlar. Buradan çıkarılan tasarım sonucu şudur: TedarikApp, genel bir URL QR'ını “WeChat kimlik doğrulaması” gibi sunmamalıdır; QR yalnız bağlantının taşınma biçimidir. Bu bir ürün entegrasyonu iddiası değil, kapsamı daraltan bir tasarım çıkarımıdır. Kaynak ve gözlem ayrıntısı 25D'dedir.

## 7. Çevrimdışı toleransın kullanıcıya görünen sözleşmesi

| Görünen durum | Anlamı | İzin verilen eylem |
|---|---|---|
| 已保存到本机 / Bu cihazda kaydedildi | Değişiklik henüz sunucuya ulaşmadı | Düzenlemeye devam; gönderildi sayılmaz |
| 等待同步 / Senkron bekliyor | Bağlantı gelince iletim denenecek | Yeniden dene; sayfayı kapatma uyarısı PM kararı |
| 正在同步 / Senkronlanıyor | Sunucuya taslak aktarılıyor | Durumu bekle |
| 已同步 / Senkronlandı | Taslak sunucuda doğrulandı | Çevrim içi gönderime geçilebilir |
| 同步失败 / Senkron başarısız | Sunucu teyidi yok | Yeniden dene; yerel taslağı koru |
| 已提交 / Gönderildi | Sunucu gönderim referansı döndürdü | Salt okunur görüntüle |

Dosya/fotoğraf yüklemesi için başarı teyidi ayrı gösterilir. Yerel metin taslağının korunması, ek dosyanın yüklendiği anlamına gelmez.

## 8. Çıktı davranışı

| Çıktı | Sunum | Değişmez kurallar |
|---|---|---|
| HTML | Sade RFQ yanıt belgesi | Üretici whitelist'i, seçilen tek dil |
| PDF | RFQ/tur başlığı, ürün yanıtları, ticari terim ve paket özeti | Uygulama menüsü yok; başka rolün alanı yok |
| Excel | İzinli RFQ sütunları + üretici yanıtları | Kaynak ürün hücreleri salt okunur; satır eşleşmesi doğrulanır |
| Yazdırma | PDF ile aynı bilgi hiyerarşisi | Çince karakterleri destekleyen gömülü yazı tipi; tek dil |

Çıktıda fiyatın EXW mi FOB mu olduğu her satırda açık görünür; para birimi, MOQ ve termin fiyatın bağlamından koparılmaz.

## 9. PM'nin şartnameye bağlaması gereken açık kararlar

1. Kısmi gönderimin açık olup olmayacağı; mevcut çekirdekte `portal.partial.*` metinleri bulunduğu için davranış ayrıca kararlaştırılmalıdır.
2. FOB seçildiğinde liman bilgisinin zorunlu yeni alan olup olmayacağı.
3. Termin başlangıcının mevcut `portal.field.lead_time_start` ile hangi seçenekleri kullanacağı.
4. Alternatifte bağlantı veya fotoğraftan en az birinin zorunlu olacağı önerisinin kesinleştirilmesi.
5. Yerel taslağın cihazda ne kadar süre tutulacağı ve ortak cihaz uyarısı.
6. WeChat içi tarayıcı için desteklenen asgari sürüm/test matrisi; test edilmeden “tam uyumlu” iddiası yazılmamalıdır.

Bu kararlar verilene kadar ilgili noktalar **vizyon/taslak**, kesin çerçeve ise **şartname girdisi** olarak ele alınmalıdır.
