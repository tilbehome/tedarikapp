# Görev #10 — VDS/VPS Karşılaştırma Tablosu

**Amaç:** V3-G taşınma kararı için karşılaştırılabilir veri üretmek. Bu belge seçim veya sağlayıcı tavsiyesi vermez; nihai seçim Ürün Sahibi'nindir.

**Fiyatların son bakış tarihi:** **23 Ağustos 2026, Europe/Istanbul**

## 1. Kapsam ve okuma kuralları

Hedef, panelsiz AlmaLinux/Ubuntu üzerinde tek sunucuda üç ayrı sistem kullanıcısı ve üç ayrı PHP-FPM havuzu ile şu iş yüklerini barındırmaktır:

- TedarikApp: PHP 8.3, MySQL/MariaDB, cron, SSH ve büyüyen yaklaşık 5–20 GB medya.
- Hafif kurumsal WordPress sitesi.
- Kurban döneminde yoğunlaşan, yılın kalanında düşük yükte çalışan PHP uygulaması.

Fiyat tablosu şu kurallarla normalize edilmiştir:

- Sağlayıcının para birimi korunmuştur; kur dönüşümü yapılmamıştır.
- Fiyatın KDV durumu sağlayıcı sayfasında açık değilse **“KDV belirsiz”** yazılmıştır.
- 12 ve 24 aylık sütunlarda mümkünse hem aylık eşdeğer hem dönem toplamı gösterilmiştir. Sağlayıcı bu dönemi yayımlamıyorsa **“belirsiz”** yazılmış, aylık fiyat mekanik olarak çoğaltılmamıştır.
- Kampanya fiyatı ile yenileme fiyatı aynı kabul edilmemiştir. Açık yenileme hükmü yoksa **“belirsiz”** yazılmıştır.
- Gecikme canlı ping ile ölçülmemiştir. “Türkiye” veya “yurt dışı” notu yalnız veri merkezi konumuna dayalıdır; satın alma öncesi test IP'siyle ölçüm gerekir.
- “Snapshot/yedek” satırı sağlayıcının felaket kurtarma imajını, müşterinin erişebildiği yedeği ve yalnızca boş yedek alanını birbirinden ayırır.

### Karşılaştırılabilirlik uyarısı

Referans aday **Hosting.com.tr VPS Medium**, 4 GB RAM'li ve kaynak havuzlu VPS sınıfındadır. Diğer satırlar 8 GB RAM çevresinde seçilmiş; gerçek VDS/izole kaynak, bulut-VPS ve tahsisli vCPU farkları ayrıca yazılmıştır. Dolayısıyla fiyat sıralaması tek başına eşdeğer kapasite sıralaması değildir.

## 2. Teknik kapasite ve dönem fiyatları

| Firma | Paket ve kaynak sınıfı | vCPU / RAM / disk | 1 ay | 12 ay | 24 ay | Yenileme fiyatı |
|---|---|---|---:|---:|---:|---|
| **Hosting.com.tr** | **VPS Medium — referans**; kaynak havuzlu VPS, tahsisli CPU değil | 4 Core / 4 GB / 100 GB **NVMe** | **$11,99/ay** | **$9,99/ay**, toplam $119,88 | **$7,99/ay**, toplam $191,76 | Paket sayfasındaki dönem tablosu “Yenileme Fiyatları” olarak etiketli ve aynı tutarları veriyor. KDV durumu sayfada açık değil. [Paket](https://www.hosting.com.tr/server/vps-server/) |
| **Turhost** | **VDS Plus 4**; sayfa “izole kaynak” diyor, altyapı Hollanda'da TransIP | 4 vCPU / 8 GB / 200 GB **SSD**; genel özellik metni NVMe altyapı dese de paket kartı yalnız SSD yazıyor | Kampanya **$24,99**; liste $42,99 | Kampanya **$29,99/ay**, toplam $359,88; liste toplam $515,88 | **Belirsiz**; yayımlanan tabloda 24 ay yok | **Belirsiz**; $42,99 standart liste fiyatı var ancak yenileme taahhüdü olarak yazılmamış. KDV hariç. [Paket](https://www.turhost.com/sunucu/vps-server/) |
| **Veridyen** | **pnCloud S-5 PRO Bulut**; VMware ESXi izolasyonu, fakat CPU'nun tahsisli/dedicated olduğu açıkça yazmıyor | 4 Core AMD EPYC 76xx / 8 GB DDR4 ECC / 160 GB **NVMe** | **1.882,66 TL/ay** | **Belirsiz**; yalnız aylık fiyat yayımlanmış | **Belirsiz** | **Belirsiz**; KDV durumu da paket sayfasında açık değil. [Paket](https://www.veridyen.com/sunucu/pro-bulut-sunucu) |
| **Güzel Hosting** | **Private Cloud 3**; ortak kernel kullanmayan, donanım düzeyinde izole VDS sınıfı | 6 Core / 8 GB / 100 GB **SSD** | **2.104,90 TL/ay** | **Belirsiz**; yalnız aylık fiyat yayımlanmış | **Belirsiz** | **Belirsiz**. İlan fiyatlarına %20 KDV dahil değil. [Paket](https://www.guzel.net.tr/private-cloud.php) |
| **İnetmar** | **VDS Sunucu 8GB**; VMware ESXi, sağlayıcı beyanıyla %100 izole/garantili kaynak | 8 Core Xeon Gold / 8 GB / 250 GB **NVMe** | **$49,99/ay** | Kampanya kuralıyla **$41,66/ay**, hesaplanan toplam **$499,90** (12 ay yerine 10 ay ödeme); sepette teyit edilmeli | **Belirsiz**; yayımlanan seçeneklerde 24 ay yok | **Belirsiz**; yıllık kampanyanın yenilemede sürmesi taahhüt edilmiyor. KDV durumu paket sayfasında açık değil. [Paket](https://www.inetmar.com/sunucu/vds-sunucu/) |
| **Hetzner** | **CCX13 General Purpose**; **2 dedicated vCPU**, saatlik faturalı cloud. **23.08.2026'da ürün sayfası yeni kurulum için “currently unavailable” gösteriyor.** | 2 AMD dedicated vCPU / 8 GB / 80 GB **NVMe** | Sunucu €42,99 + Primary IPv4 €0,50 = **€43,49/ay tavan**, KDV hariç | Taahhütlü 12 ay ürünü yok; saatlik kullanım, bugünkü aylık tavan geçerli | Taahhütlü 24 ay ürünü yok; saatlik kullanım, bugünkü aylık tavan geçerli | Yenileme kavramı yok; kullanılan saat kadar, aylık tavana kadar faturalama. Fiyat değişebilir. [Paket](https://www.hetzner.com/cloud/general-purpose/) · [15.06.2026 fiyatı](https://docs.hetzner.com/general/infrastructure-and-availability/price-adjustment/) · [IPv4](https://docs.hetzner.com/cloud/servers/primary-ips/overview/) |

### Fiyat tablosu için önemli açıklamalar

1. Hosting.com.tr'nin 1/12/24 ay tutarları aynı sayfadaki “Yenileme Fiyatları” tablosundan alınmıştır; sayfanın ana kartında görülen $7,99, 24 aylık alımın aylık eşdeğeridir.
2. Turhost kampanya tablosu 1, 3, 6 ve 12 ay yayımlar; 24 ay varsayılmamıştır. Sağlayıcı 30 gün önceden bildirimle fiyat değiştirme hakkını sözleşmede saklı tutar.
3. İnetmar'ın yıllık toplamı, resmî “12 ay yerine 10 ay ödeyin” kuralının $49,99 aylık pakete uygulanmasıyla hesaplanmıştır; bu bir sepet ekranı alıntısı değildir.
4. Hetzner CCX13 için Almanya/Finlandiya sunucu bedeli €42,99'dur; kamuya açık IPv4 ayrıca €0,50/aydır. Ürün sayfasının sipariş uygunluğu değişebileceğinden satın alma gününde yeniden kontrol gerekir.

## 3. Operasyon, yedek, erişim ve sözleşme karşılaştırması

| Firma | Snapshot / yedek politikası | Türkiye lokasyon / gecikme notu | SSH-root | IPv4 | Destek dili / kanalı | Taahhüt, iptal ve iade |
|---|---|---|---|---|---|---|
| **Hosting.com.tr** | Haftalık otomatik imaj yalnız felaket kurtarma içindir; kullanıcı doğrudan erişemez. Sağlayıcı, en güncel 1–7 günlük kopyadan geri yükleme yazıyor. Snapshot talep üzerine var; ücret yayımlanmamış. Bu, bağımsız uygulama/DB yedeğinin yerine geçmez. | Türkiye seçilebilir; Mars Veri Merkezi. Canlı gecikme ölçülmedi; TR kullanıcıları için satın alma öncesi test IP'si istenmeli. | Evet, tam root; AlmaLinux ve Ubuntu var. | 1 özel IP paket dahil. | Türkçe; 7/24 ticket, telefon, e-posta ve bilgi bankası. [Destek](https://www.hosting.com.tr/servisler/sunucu-destek/) | 1/12/24 ay peşin dönemler. Sunucuya özel iade sayfası VPS/VDS iadesini dışlıyor; yalnız sağlayıcı kaynaklı çözülemeyen sorunda iade diyor. Genel mesafeli satış sayfasıyla çelişki bulunduğundan sipariş sözleşmesi saklanmalı. [İade](https://www.hosting.com.tr/sozlesmeler/iade-kosullari) |
| **Turhost** | Son güne ait **1 otomatik yedek** panelden geri yüklenebilir. Ek yedek ve snapshot özellikleri var; ek ücret tutarı yayımlanmamış. | **Türkiye değil:** Amsterdam/Hollanda, altyapı TransIP. TR'ye gecikme yerel TR lokasyondan yüksek olacaktır; canlı ölçüm yok. | Evet, tam root. | Sunucuya IP tahsis edildiği sözleşmede anlaşılıyor; paket kartında adet/IPv4 açık değil, satış öncesi teyit gerekir. | Türkçe; 7/24 e-posta/ticket, telefon. Altyapı sağlayıcısı Hollanda'da. | 1/3/6/12 ay ürün döngüleri; 24 ay yayımlanmıyor. Koşulsuz iade politikası yalnız yeni hosting paketleri için; VPS Plus sözleşmesi VPS için açık bir koşulsuz iade vermiyor. Fiyat değişikliğinde 30 gün bildirim hükmü var. [VPS sözleşmesi](https://www.turhost.com/yasal/vps-plus-satis-ve-kullanim-sozlesmesi/) |
| **Veridyen** | Sağlayıcının günlük teknik kopyası kullanıcı yedeği sayılmıyor. Kullanıcı snapshot'ı/backup ek hizmet olarak alınabiliyor; erişim süresi ve ücret yayımlanmamış. Sağlayıcı ayrıca müşterinin kendi yedeğini tutmasını öneriyor. | İstanbul, Mars Veri Merkezi; sağlayıcının teknik altyapı sayfasına göre 2026'da İzmir'den taşınmış. Canlı gecikme ölçülmedi; test IP'si istenmeli. [Altyapı](https://www.veridyen.com/teknik-altyapi) | Evet; yönetimsiz sunucu, root erişimli sanal sunucu modeli. AlmaLinux ve Ubuntu destekleniyor. | Bir sunucu IP'si olduğu, buna ek en çok 3 özel IP alınabildiği yazıyor; IPv4 türü paket sayfasında açık değil. | Türkçe; 7/24 telefon, e-posta ve ticket. Temel destek ücretsiz, yönetimsel SLA ayrıca satılıyor. | Aylık ilan; uzun dönem yayımlanmamış. İade sayfası **Sunucu ve SLA'yı 7 günlük iade garantisinin dışında** bırakıyor. Panelden iptal talebi sonraki faturayı durduruyor. [İptal/iade](https://www.veridyen.com/kurumsal/iptal-ve-iade-kosullari) |
| **Güzel Hosting** | Pakette **50 GB FTP yedek alanı** var; bu yalnız alan tahsisidir. Hizmet sözleşmesi VDS'lerin sağlayıcı tarafından yedeklenmediğini ve sorumluluğun müşteride olduğunu açıkça söyler. Yönetilen snapshot dâhil değil/ücreti belirsiz. | Türkiye; Mars TIER3 / Netinternet TIER2+ altyapı beyanı. Canlı gecikme ölçülmedi. | Evet, root/Administrator; AlmaLinux 8/9 ve Ubuntu 20.04/24.04. | 1 IP dâhil, en çok 4 adede çıkabilir. | Türkçe; 7/24 destek sistemi ve telefon. | Aylık ilan; 12/24 ay fiyatı yayımlanmamış. VPS/VDS iade garantisi dışında ve iade edilmiyor. [İade PDF](https://www.guzel.net.tr/sozlesmeler/iade.pdf) · [Hizmet sözleşmesi](https://www.guzel.net.tr/sozlesme.php) |
| **İnetmar** | Yedekleme **opsiyonel**; ek yedek alanı ve VMware imajı talep edilebiliyor. Dâhil kapasite, erişim şekli ve ücret yayımlanmamış; ayrıca bağımsız uygulama/DB yedeği gerekir. | İzmir ve İstanbul, Türkiye; sağlayıcı Tier III ve 100 Mbit/s paylaşımsız hat beyan ediyor. Canlı gecikme ölçülmedi. | Evet, SSH ile tam root; Ubuntu/AlmaLinux destekleniyor. | Standart paketlerde “genellikle 1 IP”; ek IP ücretli. Kesin adet sipariş özetinde doğrulanmalı. | Türkçe; telefon, e-posta ve 7/24 panel ticket. Sağlayıcı ortalama 5 dk destek yanıtı beyan ediyor; bağımsız SLA ölçümü değil. [İletişim](https://www.inetmar.com/iletisim/) | Aylık veya yıllık; yıllıkta 2 ay ücretsiz kampanya. Sunucu/cloud hizmetlerinde iade yok. [Sunucu iade notu](https://www.inetmar.com/sunucu/) |
| **Hetzner** | Otomatik günlük backup isteğe bağlı: **7 slot**, sunucu fiyatının **%20'si**; CCX13 baz bedelinde yaklaşık €8,60/ay. Manuel snapshot kalıcı ve ayrıca depolama üzerinden ücretli; güncel GB fiyatı incelenen sayfada açık görünmediği için **belirsiz**. Ek volume'ler backup/snapshot'a dâhil değil. | Türkiye lokasyonu yok; en yakın aday Nuremberg/Falkenstein, Almanya. TR'ye yurt dışı gecikmesi vardır; canlı test şart. | Evet, cloud sunucu root erişimli. | Primary IPv4 ayrı kaynak, €0,50/ay; tabloda toplam fiyata eklenmiştir. IPv6 ücretsiz. | Almanca/İngilizce; cloud için 7/24 ticket/e-posta. Genel telefon desteği hafta içi; veri merkezi 24/7 telefon hattı dedicated/colocation içindir. [Destek](https://www.hetzner.com/support/) | Saatlik, aylık tavanlı ve kullanım kadar faturalı; uzun taahhüt yok. Cloud silinince fatura durur ve veri hemen silinebilir; kullanılmayan uzun dönem iadesi yerine orantılı faturalama vardır. [İptal](https://docs.hetzner.com/general/billing-and-account-management/cancellation/cancellations-overview/) |

## 4. Aday başına kısa güçlü/zayıf yan ve kullanıcı yorumu sinyali

> **Yöntem notu:** Kullanıcı yorumları doğrulanmış uptime ölçümü değildir; tekil ve seçilim yanlılığı taşıyan sinyallerdir. Paket/şart gerçekleri için yukarıdaki resmî sayfalar esas alınmıştır.

### Hosting.com.tr — VPS Medium

Güçlü tarafı düşük 24 aylık birim maliyet, TR lokasyon, NVMe, tam root ve ücretsiz felaket-kurtarma imajıdır; zayıf tarafı 4 GB RAM, kaynak havuzlu VPS sınıfı ve kullanıcı erişimsiz yedektir. [Trustpilot sayfasında](https://www.trustpilot.com/review/hosting.com.tr) hızlı destek ve sorunsuz kullanım bildiren yorumların yanında VPS ağ/uptime ve destek memnuniyetsizliği bildiren yorumlar da vardır; bu karışık sinyal, test IP'si ve kısa yük testi gerektirir.

### Turhost — VDS Plus 4

Güçlü tarafı 8 GB RAM, 200 GB disk, panelden geri yüklenebilen bir günlük yedek, root ve izole kaynak beyanıdır; zayıf tarafı Hollanda lokasyonu, 24 ay fiyatının yokluğu ve yenilemenin belirsizliğidir. [Şikayetvar'daki sunucu/kesinti örnekleri](https://www.sikayetvar.com/turhost/kesinti) kesinti ve destek gecikmesi iddiaları taşırken [Trustpilot örneklemi](https://www.trustpilot.com/review/turhost.com) yalnız dört yorumdur; ikisi de plan özelinde güvenilir uptime oranı üretmez.

### Veridyen — pnCloud S-5

Güçlü tarafı AMD EPYC, ECC RAM, 160 GB NVMe, VMware izolasyonu ve telefon/e-posta/ticket kanallarıdır; zayıf tarafı 12/24 ay ve yenileme fiyatlarının, snapshot ücretinin ve CPU tahsis türünün açık olmamasıdır. Yakın tarihli bir [R10 kullanıcı başlığında](https://www.r10.net/hosting-sirketleri/4671252-veridyen-coktumu-3.html) uzun bir kesinti anlatılırken aynı kullanıcı hızlı ticket dönüşünü ve uzun dönem genel memnuniyetini de belirtiyor; sağlayıcının [19 Nisan 2026 olay raporu](https://www.veridyen.com/duyurular/94) da Mars backbone router arızasını ve iki erişim kesintisini doğruluyor.

### Güzel Hosting — Private Cloud 3

Güçlü tarafı 8 GB RAM, donanım düzeyinde izolasyon, TR lokasyon, 1 IP ve 50 GB ayrı FTP alanıdır; zayıf tarafı NVMe yerine SSD ve FTP alanının otomatik/yönetilen yedek olmamasıdır. [Şikayetvar'da](https://www.sikayetvar.com/guzel-hosting) 2026 tarihli VDS erişim ve destek gecikmesi iddiaları bulunurken [R10 başlığında](https://www.r10.net/nasil-bilirsiniz/4563502-guzel-hosting-sikinti-yaratmaya-basladi.html) uzun süre sorunsuz kullanım bildiren karşı örnek vardır; sinyal tek yönlü değildir.

### İnetmar — VDS Sunucu 8GB

Güçlü tarafı 8 izole çekirdek beyanı, 250 GB NVMe, TR lokasyon ve yıllık iki ay ücretsiz kuralıdır; zayıf tarafı otomatik yedeğin pakete dâhil olmaması, KDV/yenileme/backup ücretlerinin yayımlanmamasıdır. Sağlayıcının [Google yorumlarına yönlendirdiği sayfa](https://www.inetmar.com/google-reviews) 4,8/5 beyanı taşır; buna karşılık araştırmada güncel ve plan özelinde bağımsız kesinti/destek örneklemi bulunamadığından kullanıcı sinyalinin güveni **düşük** tutulmuştur.

### Hetzner — CCX13

Güçlü tarafı tahsisli vCPU, öngörülebilir saatlik/aylık tavan, NVMe ve açık backup fiyatlama modelidir; zayıf tarafı TR lokasyonunun ve Türkçe desteğin olmaması, backup'ın ek ücretli olması ve 23.08.2026'da CCX13 yeni kurulumunun mevcut görünmemesidir. [Trustpilot kullanıcı özetleri](https://www.trustpilot.com/review/hetzner.com) fiyat/hız övgülerinin yanında destek erişimi ve kesinti şikâyetleri de içeriyor; resmî sayfa artık cloud için 7/24 e-posta/ticket desteği verse de telefon kapsamı daha dardır.

## 5. Satın alma öncesi aynı soruların tüm adaylara gönderilmesi

Karar verilmeden önce satış ekibinden aşağıdaki cevapların yazılı alınması, tabloda “belirsiz” kalan alanları kapatır:

1. İlan edilen CPU çekirdekleri dedicated/garantili mi, fair-use veya burst sınırı var mı?
2. Fiyata KDV ve bir adet statik public IPv4 dâhil mi?
3. 1, 12 ve 24 aylık **ilk alım toplamı** ile aynı dönemlerin **yenileme toplamı** nedir?
4. Snapshot kullanıcı panelinden alınabiliyor ve geri yüklenebiliyor mu; adet, saklama, RPO ve aylık ücret nedir?
5. Sağlayıcı felaket-kurtarma imajı müşteri hatasıyla silinen tek dosya/DB için kullanılabilir mi?
6. AlmaLinux 9 ve Ubuntu 24.04 LTS güncel imajları var mı; özel ISO/kurtarma konsolu sunuluyor mu?
7. PTR/rDNS, port 25/465/587 ve giden e-posta politikası nedir?
8. Test IPv4, looking-glass veya indirme dosyası veriliyor mu?
9. DDoS koruma kapsamı, 100 Mbit/1 Gbit port ve aylık trafik/fair-use sınırı nedir?
10. Paket büyütme sırasında kesinti olur mu; disk daha sonra küçültülebilir mi?

## 6. V3-G taşınma kontrol listesi taslağı

Taslak sıra, düşük riskli siteyi prova olarak önce taşır: **(1) kurumsal WordPress → (2) sezonluk PHP uygulaması → (3) TedarikApp**. TedarikApp son kesimde taşınır; ilk iki taşıma yeni Nginx/PHP-FPM/MariaDB düzenini gerçek trafikle doğrular.

1. **Envanter çıkar:** Üç sitenin alan adları, A/AAAA/CNAME/MX/TXT/CAA kayıtları, PHP sürüm/uzantıları, veritabanı boyutları, disk/inode kullanımı, cron'lar, queue worker'lar, harici API izin listeleri ve TLS sertifikalarını kaydet.
2. **RPO/RTO ve geri dönüş eşiği yaz:** Kabul edilebilir veri kaybı, azami kesinti, geri dönüşü tetikleyecek hata sayısı ve karar yetkilisini belirle.
3. **DNS TTL'yi düşür:** Kesimden 24–48 saat önce değişecek A/AAAA kayıtlarını 300 saniyeye indir; mevcut DNS zone'u dışa aktar.
4. **E-posta topolojisini netleştir:** Aynı sunucuda posta varsa posta kutuları, alias/forwarder, catch-all, kota, autoresponder, SPF, DKIM, DMARC, MX ve rDNS listesini çıkar; harici posta hizmeti varsa MX kayıtlarına dokunma.
5. **Yeni sunucuyu aç:** Seçilen AlmaLinux/Ubuntu sürümünü güncelle, saat dilimini/chrony'yi ayarla ve sağlayıcı konsol/kurtarma erişimini test et.
6. **SSH'ı sertleştir:** Anahtar tabanlı erişim, ayrı sudo yöneticisi, root parola girişinin kapatılması, daraltılmış firewall, fail2ban ve acil erişim prosedürünü kur; eski oturumu kapatmadan ikinci oturumla doğrula.
7. **İzolasyonu kur:** Her site için ayrı sistem kullanıcısı, grup, kök dizin, PHP-FPM pool/socket, `open_basedir`/dosya izinleri, ayrı log ve kaynak sınırları tanımla.
8. **Uygulama katmanını hazırla:** Nginx veya Apache, PHP 8.3 ve gereken uzantılar, MariaDB/MySQL, Redis varsa Redis, cron, logrotate ve queue servislerini sürüm sabitleyerek kur.
9. **Veritabanını sertleştir:** Yalnız localhost/private socket dinleme, ayrı DB/kullanıcı/parola, minimum yetki, uygun `utf8mb4`, slow-query log ve bellek sınırlarını ayarla.
10. **Yedeklemeyi taşıma öncesi çalıştır:** Şifreli uzak hedefe günlük DB dump + dosya/media yedeği kur; saklama politikasını belirle ve boş sunucuda gerçek geri yükleme testi yap. Sağlayıcı snapshot'ını tek yedek kabul etme.
11. **Gözlemlemeyi kur:** HTTP/HTTPS dış izleme, disk/RAM/CPU, inode, MariaDB, PHP-FPM queue, cron/queue başarısızlığı, TLS süresi ve yedek başarısı alarmlarını etkinleştir.
12. **Geçici test adlarını hazırla:** Mümkünse parola/IP kısıtlı test subdomain'i kullan; değilse yerel `hosts` dosyasıyla gerçek alan adını yeni IP'ye çöz.
13. **Kurumsal WordPress'i ilk prova olarak taşı:** Dosyaları ilk `rsync` ile kopyala, veritabanını dump/import et, `wp-config.php` gizli bilgilerini ve dosya sahipliğini yeni kullanıcıya göre ayarla.
14. **WordPress özel kontrollerini yap:** WP-CLI ile gerekiyorsa serileştirilmiş veriyi güvenli `search-replace` et; permalink, medya, form/e-posta, wp-cron veya sistem cron'u, cache/object cache, yönetici girişi, güncelleme ve REST API'yi test et.
15. **WordPress kesimini tamamla:** Kısa içerik dondurma penceresinde son DB dump ve delta `rsync` yap, A/AAAA'yı değiştir; erişim/log/hata oranı kabulden sonra sıradaki siteye geç.
16. **Sezonluk PHP uygulamasını taşı:** Kurban yoğun dönemi dışında dosya+DB aktarımı yap; cron'ları, tarih/saat dilimi, ödeme/SMS/entegrasyon dönüş URL'lerini ve IP izin listelerini test et.
17. **Sezonluk uygulamayı yükle doğrula:** Üretime benzer sentetik eşzamanlılıkla PHP-FPM, DB ve disk I/O davranışını ölç; kaynak sınırlarının diğer iki siteyi boğmadığını doğrula.
18. **TedarikApp ön kopyasını al:** Büyük medya dizinini ve statik dosyaları servis açıkken ilk `rsync` ile taşı; uygulama kodu, `.env` anahtarları, cron ve worker servis dosyalarını kontrollü kopyala.
19. **TedarikApp kesim penceresini başlat:** Yeni yazımı kısa süre dondur veya bakım kipine al; eski queue worker/cron'ları durdur, son DB dump'ı ve medya delta `rsync`ini al, sonra yeni DB'ye import et.
20. **TedarikApp servislerini kontrollü aç:** Önce web'i, sonra cron'u, en son queue worker'ları tek örnekle aç; idempotency, `locked_at/locked_by`, `attempts`, `available_at` ve `last_error` alanlarıyla aynı işin iki kez çalışmadığını kontrol et.
21. **TLS/SSL'yi tamamla:** DNS yeni sunucuya ulaştığında Let's Encrypt sertifikalarını üret; otomatik yenileme, HTTP→HTTPS, HSTS kararı ve tam zinciri doğrula. DNS-01 kullanılabiliyorsa sertifikayı kesimden önce hazırla.
22. **Posta varsa ayrı taşı:** Posta kutularını önceden oluştur, ilk IMAP senkronizasyonunu yap, kesimde delta `imapsync` çalıştır; MX/SPF/DKIM/DMARC/PTR'ı güncelle ve gönderme-alma/karaliste testlerini tamamla.
23. **Kabul ve geri dönüş kapısı uygula:** Üç sitede oturum, form, yükleme, medya, arama, cron, kuyruk, yönetim ekranı, DB yazma ve dış entegrasyon smoke testlerini çalıştır. Kritik test kalırsa DNS'i eski IP'ye döndür, yeni yazıları geri birleştirme planını uygula.
24. **Yakın izleme yap:** İlk 2 saat yoğun, sonraki 48 saat düzenli olarak 4xx/5xx, PHP fatal, DB slow query, disk doluluk, FPM saturation, cron/queue ve e-posta teslimini izle; eski sunucuyu salt-okunur ve erişilebilir tut.
25. **Kapatma ve TTL normalleştirme:** 48 saat hatasız kabul sonrası TTL'yi normal değere yükselt. Eski sunucuyu ancak en az bir bağımsız geri yükleme kanıtı, son yedek ve gerekiyorsa yasal kayıt saklama kontrolünden sonra iptal et; silme tarihini ve geri döndürülemezliği kayda geçir.

## 7. Kaynakça

### Resmî paket, altyapı ve fiyat kaynakları

- Hosting.com.tr, [VPS Server paketleri](https://www.hosting.com.tr/server/vps-server/): Medium fiyat/spec, NVMe, root, 1 IP, lokasyon, haftalık felaket-kurtarma yedeği ve SLA.
- Turhost, [VPS/VDS Plus paketleri](https://www.turhost.com/sunucu/vps-server/): VDS Plus 4 fiyatları, kaynaklar, Amsterdam, root, otomatik yedek ve snapshot.
- Turhost, [VPS Plus müşteri aydınlatması](https://www.turhost.com/yasal/vps-plus-musteri-aydinlatma-metni/): fiziksel altyapının TransIP/Hollanda olması.
- Veridyen, [PRO Bulut Sunucu](https://www.veridyen.com/sunucu/pro-bulut-sunucu) ve [teknik altyapı](https://www.veridyen.com/teknik-altyapi): pnCloud S-5, VMware, OS, destek, snapshot, IP ve İstanbul/Mars lokasyonu.
- Güzel Hosting, [Private Cloud](https://www.guzel.net.tr/private-cloud.php): Private Cloud 3, IP, FTP yedek alanı, root ve OS seçenekleri.
- İnetmar, [VDS Sunucu](https://www.inetmar.com/sunucu/vds-sunucu/): 8 GB paket, yıllık kampanya, izolasyon, lokasyon, root, IP ve yedek opsiyonu.
- Hetzner, [General Purpose Cloud](https://www.hetzner.com/cloud/general-purpose/): CCX13 teknik kaynakları, dedicated vCPU, lokasyon ve anlık sipariş uygunluğu.
- Hetzner Docs, [15 Haziran 2026 fiyat ayarlaması](https://docs.hetzner.com/general/infrastructure-and-availability/price-adjustment/): CCX13 €42,99/ay, KDV hariç.
- Hetzner Docs, [Primary IPv4](https://docs.hetzner.com/cloud/servers/primary-ips/overview/): €0,50/ay IPv4.
- Hetzner Docs, [Backup ve snapshot FAQ](https://docs.hetzner.com/cloud/servers/backups-snapshots/faq/) ve [backup fiyatı](https://docs.hetzner.com/cloud/billing/faq/): yedi slot, günlük kopya ve sunucu fiyatının %20'si.

### Resmî sözleşme, destek ve iade kaynakları

- Hosting.com.tr, [İade koşulları](https://www.hosting.com.tr/sozlesmeler/iade-kosullari) ve [sunucu desteği](https://www.hosting.com.tr/servisler/sunucu-destek/).
- Turhost, [VPS Plus satış/kullanım sözleşmesi](https://www.turhost.com/yasal/vps-plus-satis-ve-kullanim-sozlesmesi/), [sunucu kiralama sözleşmesi](https://www.turhost.com/yasal/sunucu-kiralama-hizmet-sozlesmesi/) ve [genel iade politikası](https://www.turhost.com/yasal/iade-sartlari/).
- Veridyen, [İptal ve iade koşulları](https://www.veridyen.com/kurumsal/iptal-ve-iade-kosullari).
- Güzel Hosting, [hizmet sözleşmesi](https://www.guzel.net.tr/sozlesme.php) ve [iade şartları PDF](https://www.guzel.net.tr/sozlesmeler/iade.pdf).
- İnetmar, [sunucu hizmetleri/iade SSS](https://www.inetmar.com/sunucu/) ve [iletişim](https://www.inetmar.com/iletisim/).
- Hetzner, [destek](https://www.hetzner.com/support/), [cloud 7/24 ticket notu](https://docs.hetzner.com/general/infrastructure-and-availability/support-team-opening-hours/) ve [iptal/faturalama](https://docs.hetzner.com/general/billing-and-account-management/cancellation/cancellations-overview/).

### Bağımsız kullanıcı sinyali kaynakları

- [Hosting.com.tr — Trustpilot](https://www.trustpilot.com/review/hosting.com.tr)
- [Turhost kesinti — Şikayetvar](https://www.sikayetvar.com/turhost/kesinti) ve [Turhost — Trustpilot](https://www.trustpilot.com/review/turhost.com)
- [Veridyen kesinti başlığı — R10](https://www.r10.net/hosting-sirketleri/4671252-veridyen-coktumu-3.html)
- [Güzel Hosting — Şikayetvar](https://www.sikayetvar.com/guzel-hosting) ve [R10 kullanıcı başlığı](https://www.r10.net/nasil-bilirsiniz/4563502-guzel-hosting-sikinti-yaratmaya-basladi.html)
- [İnetmar — Google yorum yönlendirmesi](https://www.inetmar.com/google-reviews)
- [Hetzner — Trustpilot](https://www.trustpilot.com/review/hetzner.com)

---

**Belge sınırı:** Bu çalışma teklif değildir. Fiyat, stok, vergi, IPv4, yenileme ve yedek ücretleri sipariş gününde değişebilir; satın alma ekranı ve imzalanacak sözleşme son kontrol kaynağıdır.
