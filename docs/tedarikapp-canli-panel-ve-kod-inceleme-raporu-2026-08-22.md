# TedarikApp Canlı Panel ve Kod İnceleme Raporu

**Tarih:** 22 Ağustos 2026  
**İncelenen sistem:** `https://tedarikapp.tilbehometoptan.com/panel`  
**Kod deposu:** `tilbehome/tedarikapp`, `main`, `v0.11.3`, commit `0d8bc81`  
**Belgenin niteliği:** Talimat veya iş emri değil; ürün sahibi ve Claude için değerlendirme, öncelik ve geliştirme tavsiyesidir.

## 1. Yönetici özeti

TedarikApp'in çekirdeği kötü durumda değildir. Ürün yakalama, Gelen Kutusu, listeler, kur hesabı, çıktı, paylaşım, medya arşivi, yedekleme ve güvenlik için ciddi bir altyapı kurulmuştur. Son `v0.11.3` PR'sinin CI hattında PHPUnit, PHPStan, kod biçimi, bağımlılık güvenliği, sır taraması, MySQL entegrasyonu, PHP 8.1 uyumu, Playwright, panel ve Chrome eklentisi kontrollerinin tamamı başarılıdır.

Bugünkü ana risk yazılım kalitesinden çok **ürün kapsamı ve canlıya çıkarma disiplini**dir. Canlıda görünen geniş V3 kabuğu, ana daldaki panel kaynağıyla aynı değildir. Depodaki karar kaydı ve PR #34, v0.11.2 paketine `v3-faz1` dalında derlenmiş onaylanmamış panelin yanlışlıkla girdiğini açıkça doğrular. Eklenen build damgası olayı kayda geçirir; fakat damganın commit'i mevcut backend commit'iyle zorunlu olarak karşılaştırılmadığı, temiz çalışma ağacı şart koşulmadığı ve `--panel-dal` kontrolü isteğe bağlı olduğu için koruma henüz tam değildir.

İkinci ana sorun, sistemin bugün çözmesi gereken iş ile arayüzün anlattığı işin ayrışmasıdır. Güncel ihtiyaç şudur:

> 1688'den ürünleri güvenilir biçimde toplamak, veriyi temizlemek, uygun ürünleri listede düzenlemek ve yetkili arkadaşa profesyonel bir dosya veya bağlantı halinde teslim etmek.

Bu aşamada GTİP, TAREKS, gümrük mevzuatı, ithalat avantajı, sevkiyat, konteyner ve tam sipariş operasyonu TedarikApp'in işi değildir. Bu alanların menüde veya aktif yol haritasında durması ürünü olduğundan büyük ve tamamlanmamış gösterir; asıl veri kalitesi işini geri plana iter.

**Önerilen karar:** Yeni modül eklemek yerine önce canlı paneli tek, doğrulanmış kaynağa döndürmek; V3 kapsamını dondurmak; ürünü “Yakalama → İnceleme → Temizleme → Listeye alma → Yetkiliye gönderme” akışında mükemmelleştirmek gerekir.

## 2. İnceleme kapsamı ve kanıt düzeyleri

### Canlıda doğrulananlar

- Giriş ve 2FA sonrası Panorama, Gelen Kutusu, Listeler, Ayarlar, Arşiv ve Aktivite ekranları.
- Canlı liste detayında 18 ürünlü gerçek tablo, kur ve toplamlar, durumlar, çıktı/paylaşım eylemleri ve export geçmişi.
- Canlı menüde çok sayıda “Yakında” modülü.
- Panelin genel görsel kalitesi, masaüstü yerleşimi ve mevcut veri kalitesi.

### Koddan doğrulananlar

- PHP/Slim/MySQL backend, React/Vite panel ve WXT/TypeScript MV3 eklenti.
- API, veri modeli, güvenlik katmanları, çıktı/paylaşım, medya ve yedekleme.
- 1688 parser'ı, yakalama şeması, Gelen Kutusu ve ürün oluşturma akışı.
- Kaynak panel ile canlı panel arasındaki fark ve yanlış dal build'i olayı.
- GitHub CI sonucu: PR #34 için CI run `32568398721`, tüm sekiz iş başarılı.

### Hosting engeli nedeniyle canlıda tamamlanamayanlar

Cloud Browser ile ürün detayına geçildiğinde hosting ağı Cloud Browser çıkışını engelledi ve `502 Bad Gateway / [Errno 111] Connection refused` döndürdü. Aynı sırada bağımsız normal HTTP kontrolü giriş sayfasından 200 aldı. Bu nedenle 502 **TedarikApp yazılım hatası değildir**; hosting/WAF/ağ rotası kısıtıdır.

Bu engel yüzünden ürün düzenleme, gerçek export dosyalarının görsel kontrolü, paylaşım sayfası ve eklentiden canlı 1688 yakalaması sonuna kadar canlı test edilemedi. Bu bölümler kod ve testler üzerinden incelendi. Güvenlik duvarını genel olarak gevşetmek önerilmez.

## 3. Canlı sistemde güçlü taraflar

1. **Temiz ve profesyonel temel görünüm:** Renk, boşluk, kart, tablo ve buton düzeni genel olarak güven veriyor.
2. **Gelen Kutusu yaklaşımı doğru:** Ürünlerin doğrudan nihai listeyi kirletmeden önce kuyruğa düşebilmesi önemli bir tasarım kararıdır.
3. **Orijinal veri korunuyor:** Yakalama gövdesinde `source`, `raw` ve `normalized` katmanları var; hatalı normalize edilse bile ham veri kaybolmuyor.
4. **Para hesabı güvenli tasarlanmış:** Para değerleri string/DECIMAL olarak taşınıyor, TL hesapları backend'de yapılıyor, taslak ve kilitli kur ayrımı bulunuyor.
5. **Mükerrer ürün uyarısı var:** `platform + external_id` üzerinden daha önce eklenen ürün bulunuyor; bilinçli tekrar yine mümkün.
6. **Çıktı ve paylaşım altyapısı olgun:** XLSX/PDF/CSV, snapshot mantığı, “çıktı güncel değil” uyarısı ve süreli/hash'li paylaşım bağlantıları mevcut.
7. **Görsel dayanıklılığı düşünülmüş:** SSRF kontrolü, alan adı beyaz listesi, indirme/hotlink modu, kırık medya onarımı ve galeri kayıtları var.
8. **Güvenlik temeli güçlü:** Argon2id, opsiyonel TOTP, CSRF, CSP/HSTS, oturum yönetimi, token hash'i, giriş hız sınırlaması, sır taraması ve log redaksiyonu mevcut.
9. **Yedekleme ve bütünlük kontrolü var:** Şifreli yedek, saklama politikası, isteğe bağlı uzak hedef, manifest ve release bütünlük denetimi bulunuyor.
10. **CI hattı kapsamlı:** Birim, entegrasyon, üretim profili, gerçek MySQL, Playwright, eklenti fixture ve güvenlik kontrolleri birlikte çalışıyor.

Bu güçlü altyapı korunmalıdır; sorun yeni sistem yazmak değil, mevcut sistemi doğru işe daraltıp veri kalitesini görünür hale getirmektir.

## 4. Önceliklendirilmiş bulgular

| Öncelik | Bulgu | Kanıt | Etki | Tavsiye |
|---|---|---|---|---|
| **P0** | Canlı panel build'i ana dal kaynak paneliyle uyuşmuyor | Canlıda Panorama ve geniş V3 menüsü; `main/frontend/src/components/Layout.tsx` yalnız Ana Ekran, Listeler, Gelen Kutusu, Ayarlar, Çöp ve Aktivite içeriyor. PR #34 yanlış `v3-faz1` build'ini doğruluyor | Hangi kodun canlıda olduğu belirsizleşir; test edilen UI ile kullanılan UI farklı olabilir | Doğrulanmış tek release artefaktı kullanılsın; canlı `BUILD.json` sürüm/commit bilgisi yönetici ekranında gösterilsin; backend ve panel commit'i zorunlu eşleşsin |
| **P0** | İş kapsamı V3 menüsü nedeniyle dağılıyor | Keşif, Teklifler, Siparişler, Sevkiyat, İthalat Avantajı, İzleme, Raporlar, Takvim, Belgeler, Firmalar “Yakında” | Ürün tamamlanmamış görünür, ekip kendine gereksiz iş çıkarır | “Yakında” öğeleri ana menüden kaldırılıp fikir havuzuna alınsın; GTİP/TAREKS/İthalat Avantajı aktif yol haritasından çıkarılsın |
| **P0** | Toplanan zengin veri ürün kaydında düzenlenebilir ve görünür değil | Gelen Kutusu detayı fiyat kademesi, SKU, özellikler ve galeri gösteriyor; ürün formu yalnız tek fiyat, tek görsel URL, adet, kategori ve sınırlı alanları düzenliyor | Listeye taşındıktan sonra araştırma verisi fiilen saklı kalıyor; yetkiliye giden liste temizlenemiyor | Ürün inceleme/düzenleme ekranı fiyat kademeleri, seçilmiş varyant, MOQ, birim, satıcı, galeri ve öznitelikleri göstermeli |
| **P1** | Mevcut durum makinesi güncel işe göre fazla lojistik odaklı | `Verilecek → Verildi → Yolda → Geldi`; liste `Taslak → İletildi → Sipariş Verildi → Tamamlandı` | Yetkili arkadaşın yaptığı ithalat operasyonunu uygulamaya taşır, kapsam yeniden büyür | Araştırma durumları önerilir: `Yeni → İncelenecek → Hazır → Yetkiliye gönderildi → Arşiv`; lojistik alanları pasif/ileri ayara alınsın |
| **P1** | Canlı ürün veri kalitesi düşük | 18 ürünün çoğu kategorisiz, bazı başlıklar Çince, bazı Türkçe adlar makine çevirisi gibi, bütün adetler 1, DDP değerleri 0 | Profesyonel teslim listesi oluşmaz; yanlış ürün/varyant/fiyat riski artar | “Hazır” olmadan önce zorunlu kalite kapısı: Türkçe ad, kaynak linki, ana görsel, seçilmiş varyant, miktar, birim fiyat, kategori ve inceleme onayı |
| **P1** | Ürün ana tablosu araştırma için eksik, fiyat/lojistik için fazla geniş | Ana tabloda 12 sütun; SKU, MOQ, satıcı, kaynak, birim, fiyat kademesi yok; DDP ve durum sütunları baskın | Yatay kaydırma artar, karar bilgisi görünmez | Varsayılan tablo sadeleştirilsin; araştırma sütunları gösterilsin; maliyet/operasyon sütunları açılır “Ek bilgiler” görünümüne alınsın |
| **P1** | Build damgası koruması tamamlanmamış | `release.php` dalı yalnız `--panel-dal` verilirse denetliyor; damga commit'i HEAD ile eşleştirilmiyor; `temiz=false` sadece yazdırılıyor | Yanlış veya kirli build yine paketlenebilir | Release; `panel.commit == backend commit`, `temiz == true`, beklenen dal ve CI artefakt kimliğini zorunlu kılsın; üretim zip'i yalnız CI'dan alınsın |
| **P1** | Ürün düzenleme tüm listeyi çekip tek ürünü buluyor | `ProductFormScreen` düzenlemede `productsApi.forList(listId)` sonrası `.find()` yapıyor; tekil GET endpoint yok | Büyük listelerde gereksiz veri, yavaş açılış ve hata yüzeyi | `GET /api/products/{id}` eklenip düzenleme yalnız tek ürünü çeksin |
| **P1** | Ürün listesi sayfasız ve her aramada yeniden tam sorgulanıyor | `ProductRepository::forList()` tüm satırları döndürüyor; arama her tuşta API çağırıyor | Liste büyüdükçe panel ve sunucu yavaşlar | 50 satırlık sayfalama, 250–300 ms debounce, isteği iptal etme ve server-side sort eklenmesi önerilir |
| **P1** | Eklenti yalnız 1688 ürün detayını destekliyor | Manifest host izni yalnız `detail.1688.com`; Alibaba/Taobao vb. yalnız fikir belgesinde | “Çin sitelerinden toplama” beklentisi yanlış anlaşılabilir | Şimdilik arayüzde açıkça “1688 desteklenir” yazsın; ikinci platform ancak gerçek ihtiyaç ve örnek sayfalarla ayrı modül olarak alınsın |
| **P1** | 1688 entegrasyonunun canlı sözleşme testi yok | Parser fixture testleri var; CI yorumunda canlı istek yok ve MV3 E2E kapsam dışı | 1688 sayfa yapısı değiştiğinde bütün testler yeşilken yakalama bozulabilir | Haftalık manuel/canary kontrolü: örnek ürünlerde ad, fiyat, görsel, SKU, satıcı ve MOQ doğrulansın; parser sağlık göstergesi eklensin |
| **P2** | Gelen Kutusu seçimi filtre/sayfa değişiminde eski kimlikleri tutabilir | `selected` yalnız taşıma/silme sonrası temizleniyor; filtre ve sayfa değişiminde sıfırlanmıyor | Görünmeyen eski kayıt toplu işleme girebilir | Filtre, arama veya sayfa değişince seçim temizlensin ya da “tüm sayfalarda N seçili” açıkça gösterilsin |
| **P2** | Mobil ve masaüstü ürün görünümleri aynı anda DOM'da | `md:hidden` kartlar ile `hidden md:block` tablo birlikte render ediliyor | Erişilebilirlik ağacında çift bağlantı/kontrol ve gereksiz DOM yükü olabilir | CSS ile gizlemek yerine breakpoint'e göre tek görünüm render edilsin veya erişilebilirlik açısından gizli bölüm tamamen devre dışı bırakılsın |
| **P2** | Gelen Kutusu detay çekmecesinde tam klavye yönetimi yok | `role=dialog` ve Escape var; focus trap, ilk odak ve kapanınca odak iadesi yok | Klavye/screen-reader kullanımında arka sayfaya kaçış | Dialog odak kilidi, odak geri dönüşü ve kaydırma kilidi eklenmesi önerilir |
| **P2** | Bazı hatalar kullanıcıdan gizleniyor | Çeviri isteği, görsel onarımı ve export geçmişi hatası sessizce yok sayılıyor | Kullanıcı “veri yok” ile “işlem bozuldu”yu ayıramaz | Zorunlu olmayan özellikler akışı kesmesin; fakat küçük ve anlaşılır “yeniden dene / neden olmadı” geri bildirimi versin |
| **P2** | Tek eklenti token'ı bütün cihazlarda ortak | Token yenileme/iptal tüm cihazları düşürüyor; cihaz bazlı liste ve son kullanım yok | Bir cihaz kaybolduğunda tüm entegrasyon yenilenir, denetim zayıflar | Cihaz adı, kapsam, son kullanım ve ayrı iptal desteği olan çoklu token modeli önerilir |
| **P2** | Dokümantasyon güncel gerçeklikle çelişiyor | README hâlâ Faz 1/v0.1–v0.3 yol haritasını anlatıyor; CLAUDE/TECH-BASELINE eklentiyi vanilla ve sıfır bağımlılık diye tanımlarken gerçek eklenti WXT+TypeScript | Claude yanlış teknoloji ve kapsam kararı verebilir | README, CLAUDE, TECH-BASELINE ve yol haritası tek güncel kapsamla eşitlenmeli; V3 belgesi “donduruldu/superseded” olarak işaretlenmeli |
| **P2** | Yedek varlığı geri dönüş garantisi değildir | Şifreli yedek ve off-site destek var; canlıda uzak hedef yapılandırılmamış görünüyordu | Sunucu kaybında yalnız aynı sunucudaki yedek de kaybolabilir | Günlük uzak yedek + aylık otomatik/manuel restore testi ve son başarılı restore tarihi gösterilsin |

## 5. Önerilen temiz ürün akışı

### 5.1 Tek iş akışı

1. **Yakalama:** Kullanıcı 1688 ürün sayfasında eklentiye basar; veri Gelen Kutusu'na düşer.
2. **İnceleme:** Orijinal başlık, görseller, satıcı, fiyat kademeleri, MOQ, SKU/varyant ve özellikler birlikte görülür.
3. **Temizleme:** Türkçe kısa ad, kategori, seçilecek varyant, sipariş adedi, birim fiyat ve ana görsel belirlenir.
4. **Hazır kontrolü:** Eksik zorunlu alan varsa ürün “Hazır” yapılamaz; sistem hangi alanın eksik olduğunu gösterir.
5. **Listeleme:** Hazır ürünler belirlenen listeye girer; listede karar vermeye yarayan bilgiler görünür.
6. **Teslim:** Yetkili arkadaşa firma kopyası PDF/XLSX veya salt okunur bağlantı gönderilir.
7. **Geri bildirim:** Yetkili arkadaşın notu varsa ürün/listede tek not alanına işlenir; ithalat operasyonu uygulamaya taşınmaz.

### 5.2 Ürün kaydında önerilen alanlar

**Zorunlu kalite alanları**

- Türkçe kısa ürün adı
- Orijinal başlık
- Kaynak platform, ürün ID ve tıklanabilir URL
- Ana görsel ve galeri
- Satıcı/mağaza adı
- Seçilmiş varyant/SKU
- Sipariş adedi ve satış birimi
- Birim Yuan fiyatı ve kullanılan fiyat kademesi
- MOQ
- Kategori
- İnceleyen kişi/tarih veya basit “kontrol edildi” işareti

**İsteğe bağlı alanlar**

- Koli içi adet, paket ölçüsü, net/brüt ağırlık, hacim
- Numune notu, kalite notu, renk/malzeme bilgisi
- Hedef iç piyasa fiyatı
- Yetkili arkadaşın DDP veya başka maliyet notu

GTİP, TAREKS ve gümrük mevzuatı alanları bu şemaya eklenmemelidir. İleride ihtiyaç kesinleşirse ayrı bir entegrasyon/uyum projesi olarak ele alınabilir.

### 5.3 Ana liste ekranı için sade sütun önerisi

Varsayılan görünüm:

| Görsel | Türkçe ürün adı | Varyant | Satıcı | Adet | MOQ | ¥ birim | ₺ yaklaşık mal bedeli | Durum |
|---|---|---|---:|---:|---:|---:|---:|---|

“Ek bilgiler” görünümü açıldığında kaynak ID, kategori, koli bilgileri, DDP ve hedef fiyat görülebilir. Böylece kullanıcı ilk bakışta karar tablosunu görür; nadir bilgiler ekranı boğmaz.

## 6. Maliyet gösteriminin sınırı

Uygulama bugün `Yuan fiyatı × kur` hesabını doğru yapıyor. Bu değer **yaklaşık ürün bedelidir**, ithal edilmiş toplam maliyet değildir. DDP girilmemişken “maliyet” veya “kâr” gibi kesin ifadeler kullanıcıyı yanıltabilir.

Önerilen etiketler:

- `Yaklaşık ürün bedeli (₺)` — Yuan × güncel/kilitli kur
- `Yetkili DDP teklifi ($)` — yalnız elle girilmişse
- `Toplam ithalat maliyeti` — bu aşamada gösterilmemeli
- Eksik fiyat `0,00` yerine `—` ve “bilgi girilmedi” olarak gösterilmeli

Bu yaklaşım uygulamayı GTİP/TAREKS hesabına sürüklemeden yaklaşık bütçe karşılaştırması yapmayı sağlar.

## 7. Güvenlik ve operasyon değerlendirmesi

### Güçlü bulunanlar

- Hazır SQL sorguları ve merkezi doğrulama.
- CSRF ve oturum koruması.
- Hash'li eklenti ve paylaşım token'ları.
- Dar CORS ve medya alan adı beyaz listesi.
- SSRF, MIME ve dosya boyutu kontrolleri.
- CSP, HSTS, noindex ve tıklama çerçevesi engeli.
- Şifreli yedek, manifest ve migration koruması.

### İyileştirme önerileri

- 2FA canlıda açık tutulmalı; kurtarma kodları çevrimdışı saklanmalı.
- Eklenti token'ı cihaz bazlı olmalı ve kullanım geçmişi görünmeli.
- Uzak yedek zorunlu hale getirilmeli; yalnız aynı hosting'de yedek yeterli sayılmamalı.
- Ayda bir restore denemesi yapılmalı ve sonucu panelde görünmeli.
- Üretim panelindeki build bilgisi yalnız dosyada değil Ayarlar > Sistem'de görünmeli.
- Release yalnız başarılı CI artefaktından yapılmalı; cPanel'de yerel/elle build paketlenmemeli.
- Cloud Browser erişimi için WAF'ı genel olarak gevşetmek yerine, gerekirse geçici ve dar IP kuralı kullanılmalı. Hosting bunu sağlayamıyorsa kod + normal kullanıcı tarayıcısı kabul testi yeterlidir.

## 8. Test stratejisindeki boşluklar

CI güçlü olsa da şu boşluklar kapanmalıdır:

1. **Canlı 1688 değişikliği:** Fixture testleri geçmiş bir sayfa yapısını doğrular; sitenin bugün değişmediğini kanıtlamaz.
2. **Canlı build kaynağı:** Testler ana dal panelini test eder; yanlış dalda derlenmiş statik dosyanın üretime yüklenmesini bugün tam olarak engellemez.
3. **Gerçek çıktı görsel kabulü:** XLSX/PDF üretimi test edilir; gerçek kullanıcı verisiyle taşma, Çince başlık ve uzun varyantların periyodik görsel kontrolü gerekir.
4. **Büyük liste performansı:** 500–1.000 ürünlük liste için sayfalama, arama ve düzenleme yük testi görünmüyor.
5. **Erişilebilirlik:** Klavye, dialog odağı, çift DOM görünümü ve ekran okuyucu testi otomatik kapsamda değil.
6. **Yedekten dönüş:** Yedek üretmek test ediliyor; düzenli üretim restore tatbikatı ayrı bir operasyon kabul kriteri olmalı.

## 9. Tavsiye edilen geliştirme sırası

### Aşama A — Kapsamı ve canlı sürümü temizleme (1–2 kısa sprint)

- Canlı panel build kaynağını doğrulama ve doğru artefaktı yükleme.
- V3 “Yakında” menülerini kaldırma veya görünmez yapma.
- GTİP/TAREKS/İthalat Avantajı belgelerini aktif yol haritasından çıkarma.
- README, CLAUDE, TECH-BASELINE ve yol haritasını güncelleme.
- Release korumasını dal + commit + temiz çalışma ağacı + CI artefaktı zorunluluğuna çıkarma.

**Kabul ölçütü:** Canlı panelde görülen menü ve sürüm, `main` kaynağı ve CI artefaktıyla birebir eşleşir; kullanıcı yalnız çalışan modülleri görür.

### Aşama B — Ürün veri kalitesi (en yüksek iş değeri)

- Tekil ürün GET endpoint'i ve yeni inceleme/düzenleme ekranı.
- SKU/varyant seçimi, fiyat kademesi, MOQ, birim, satıcı, galeri ve özellik görünümü.
- Türkçe ad ve kategori için kullanıcı onaylı öneri; başarısızlıkta görünür ama sakin hata mesajı.
- “Hazır” kalite kapısı ve eksik alan sayacı.
- Araştırma odaklı basit durum makinesi.

**Kabul ölçütü:** Gelen Kutusu'ndaki zengin veri listeye taşındıktan sonra kaybolmaz; yetkiliye gönderilecek her ürün tek ekrandan kontrol edilip “Hazır” yapılabilir.

### Aşama C — Temiz liste ve teslim

- Ana tablonun araştırma sütunlarıyla sadeleştirilmesi.
- Yetkiliye giden firma kopyasında yalnız gerekli alanlar.
- Excel/PDF/link üzerinde aynı ürün adı, varyant, adet, fiyat, MOQ, satıcı ve kaynak doğruluğu.
- Liste için “hazır olmayan N ürün” uyarısı.
- Geri bildirim/not alanı ve revizyon tarihi.

**Kabul ölçütü:** Yetkili arkadaş ilave açıklama istemeden ürünleri, varyantları, adetleri, kaynakları ve notları anlayabilir.

### Aşama D — Ölçek, güvenilirlik ve operasyon

- Ürün sayfalama, debounce, istek iptali ve server-side sıralama.
- Cihaz bazlı eklenti token'ları.
- Parser sağlık/canary kontrolü.
- Uzak yedek ve restore tatbikat göstergesi.
- Klavye ve erişilebilirlik düzeltmeleri.

**Kabul ölçütü:** 1.000 ürünlük veri setinde liste ve arama kabul edilen sürede çalışır; bir cihaz token'ı diğerlerini etkilemeden iptal edilebilir; son restore doğrulaması görülebilir.

## 10. Şimdilik yapılmaması önerilenler

- GTİP motoru, TAREKS entegrasyonu ve otomatik mevzuat kararı.
- Gümrük vergisi/ithalat toplam maliyetini kesinmiş gibi hesaplama.
- Teklif, sipariş, sevkiyat, konteyner ve mal kabul modülleri.
- Firma portalı ve çok kullanıcılı karmaşık rol sistemi.
- Yeni platformları topluca destekleme iddiası.
- Yapay zekâyı kullanıcı onayı olmadan ürün verisini değiştirecek biçimde kullanma.
- Mevcut iş kanıtlanmadan dashboard, rapor ve grafik çoğaltma.

Bu maddeler kötü fikir olduğu için değil, mevcut hedefin dışında oldukları ve asıl veri kalitesi işini geciktirdikleri için bekletilmelidir.

## 11. Claude için tavsiye özeti

TedarikApp'in yeni dönemde “uçtan uca ithalat yönetimi” olarak değil, **ürün araştırma, veri temizleme ve profesyonel liste teslim sistemi** olarak ele alınması önerilir. Mevcut güvenlik, para hesabı, yakalama şeması, medya, çıktı ve yedekleme altyapısı korunabilir. Geliştirme enerjisinin yeni modüllere değil, yakalanan 1688 verisinin ürün ekranında tam görünmesine, varyant/MOQ/fiyat kademesi seçimine, Türkçe adlandırma ve kategori kalitesine, temiz firma çıktısına ve doğru release disiplinine ayrılması en yüksek iş değerini sağlayacaktır.

Canlıdaki V3 kabuğunun kaynak ve test edilen panelle uyuşmaması ilk çözülmesi gereken teknik konudur. Bundan sonra başarı ölçütü “kaç modül var?” değil, “yetkili arkadaşa gönderilen liste ne kadar temiz, eksiksiz ve yanlış anlaşılmaz?” olmalıdır.

## 12. Son karar önerisi

TedarikApp devam ettirilmeye değer ve mevcut çekirdek yeniden yazılmamalıdır. En doğru yaklaşım:

1. Canlı sürüm kaynağını düzeltmek ve release kapısını güçlendirmek.
2. V3/ithalat kapsamını dondurup menüden kaldırmak.
3. Gelen Kutusu ile ürün kaydı arasındaki veri kaybını/görünmezliği gidermek.
4. Listeyi yetkili arkadaşa temiz teslim edecek kalite kapısını kurmak.
5. Son olarak performans, cihaz token'ı, yedek ve erişilebilirlik cilasını tamamlamak.

Bu sıra korunursa uygulama karmaşıklaşmadan gerçek işin merkezine oturur ve ileride ihtiyaç değişirse mevcut sağlam çekirdek üzerinden kontrollü biçimde büyüyebilir.
