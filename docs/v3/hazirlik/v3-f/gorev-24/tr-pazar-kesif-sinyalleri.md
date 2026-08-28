# TR Pazar Siteleri Keşif-Sinyali Araştırması

**Görev:** #24 — V3-E/F hazırlık  
**Amaç:** Trendyol ve Hepsiburada'da talep kanıtı taşıyan ürünleri bulmak ve 1688'de aynı/aynı kalıp ürünü aramaya geçirmek; satın alma kararı vermek değil.  
**Gözlem tarihi:** 2026-08-28 (UTC)  
**Kapsam:** Oturum açmadan erişilebilen kamuya açık ürün, arama, kategori, kampanya ve sıralama yüzeyleri. N11 bu teslimin dışında, sonraki adaydır.  
**Kanıt ilkesi:** Görülmeyen satış adedi üretilmemiştir. Platformun yayımladığı değer, üçüncü taraf tahmini ve bu raporun çıkarımı ayrı tutulmuştur.

## 0. Yönetici özeti

| Karar | Sonuç | Gözlem tarihi |
|---|---|---|
| Keşif için ilk entegrasyon | **Trendyol P0**: oturumsuz sayfalarda 3 günlük satış eşiği/adedi, sepetteki kişi, favori, 24 saatlik görüntülenme ve kategori içi “En Çok Satan/Favorilenen/Ziyaret Edilen/Değerlendirilen” sıraları seçici olarak görünür. | 2026-08-28 |
| İkinci entegrasyon | **Hepsiburada P1**: doğrudan satış adedi doğrulanamadı; buna karşılık “Alışverişin top listesi”, “en çok tekrar alınanlar”, “çok satanlar” sıralaması ve kategori içi resmi sıralama mantığı güçlü göreli kanıttır. | 2026-08-28 |
| Çekirdek veri yaklaşımı | Görünen kanıtı olduğu gibi sakla: `3 günde 100+`, `En Çok Satan #2`, `4,6 (1.703)` gibi. Tahmini adet üretilecekse ayrı bir `estimate` nesnesinde, formülü ve güven aralığıyla tutulmalı; kanıt alanına yazılmamalı. | 2026-08-28 |
| En güvenilir ortak sinyal | Kategori kapsamı belli resmi satış sırası + zaman içinde sıralama kalıcılığı/değişimi. Ardından değerlendirme sayısı ve yeni değerlendirme ivmesi gelir. | 2026-08-28 |
| 1688 eşleştirme | Birincil ürün görseliyle `以图搜款` + normalize edilmiş Çince özellik anahtarları birlikte kullanılmalı. Tek başına başlık çevirisi veya barkod yeterli değildir. | 2026-08-28 |
| Hukuki/teknik sınır | Yalnız kullanıcının açık sayfasındaki görünür DOM; kullanıcı başlatmalı tekil yakalama; çerez/token dışa aktarma, gizli API, CAPTCHA aşma ve toplu arka plan taraması yok. | 2026-08-28 |

### Kanıt sınıfları

| Sınıf | Anlam | Örnek | Gözlem tarihi |
|---|---|---|---|
| A — doğrudan | Platform açıkça işlem veya zaman penceresi bildiriyor. | `3 günde 100+ ürün satıldı`; resmi açıklamayla son 30 günün çok satanları | 2026-08-28 |
| B — güçlü göreli | Platform sırası veya davranış listesi var; adet ya da tam formül yok. | `En Çok Satan 2. Ürün`, `En çok tekrar alınanlar` | 2026-08-28 |
| C — dolaylı | Talebe yakın fakat satış olmayan davranış. | değerlendirme, favori, sepet, görüntülenme, soru sayısı | 2026-08-28 |
| D — bağlamsal | Satışı tek başına kanıtlamaz; dönüşüm veya arz durumunu açıklar. | kampanya, düşük stok, hızlı teslimat, satıcı puanı | 2026-08-28 |
| X — doğrulanamadı | İncelenen oturumsuz örneklerde platform metriği olarak görülmedi. | Hepsiburada ürününde favori/sepetteki kişi; kümülatif kesin satış | 2026-08-28 |

## 1. Sinyal envanteri

### 1.1 Trendyol — ürün detay sayfası

Örneklerde değerlendirme ve soru sayıları, favori/sepet/görüntülenme sosyal kanıtları, varyant/son stok uyarıları ve kampanyalar oturumsuz görüldü. Ancak her ürün aynı sosyal kanıt bloklarını göstermiyor; eşik, kategori, deney grubu, cihaz ve anlık görünürlük etkili olabilir.

| Sinyal | Durum / sınıf | Nerede görünür | Gizlenme veya değişme koşulu | Güvenilirlik notu | Kaynak | Gözlem tarihi |
|---|---|---|---|---|---|---|
| Ortalama ürün puanı | Doğrulandı / C | Başlığın altında, değerlendirme sayısıyla | Yorum yoksa “Henüz Yorum Yazılmamış” biçimine döner | Platform içi memnuniyet göstergesi; satış adedi değildir. | [Trendyol ürün örneği](https://www.trendyol.com/p-parti-oyunevi/funny-blocks-mikro-blok-500-parca-plastik-kutulu-yapi-bloklari-p-366630898) | 2026-08-28 |
| Değerlendirme sayısı | Doğrulandı / C | Puan yanında (`676 Değerlendirme`) | Değerlendirme yoksa sayı gösterilmez | Satın alma sonrası yorum bırakma oranı bilinmediği için satışa bire bir çevrilemez; varyant/ürün birleştirmeleri kontrol edilmelidir. | [Trendyol ürün örneği](https://www.trendyol.com/p-parti-oyunevi/funny-blocks-mikro-blok-500-parca-plastik-kutulu-yapi-bloklari-p-366630898) | 2026-08-28 |
| Fotoğraflı değerlendirme işareti | Doğrulandı / C | Değerlendirme sayısının yanında veya liste kartında | Görsel/video yorum yoksa görünmez | İçeriğin gerçek kullanım kanıtını güçlendirir; adet tek başına talep değildir. | [Trendyol kategori örneği](https://www.trendyol.com/viko-x-b109155) | 2026-08-28 |
| Soru-cevap sayısı | Doğrulandı / C | Puan bloğunun altında (`110 Soru-Cevap`) | Soru yoksa veya blok deneysel olarak kapalıysa görünmeyebilir | İlgi ve satın alma öncesi belirsizlik karışımıdır; yüksek sayı hem talebi hem kötü açıklamayı gösterebilir. | [Trendyol ürün örneği](https://www.trendyol.com/p-parti-oyunevi/funny-blocks-mikro-blok-500-parca-plastik-kutulu-yapi-bloklari-p-366630898) | 2026-08-28 |
| Favorileyen kişi sayısı | Doğrulandı / C | Sosyal kanıt bloğu (`344 kişi favoriledi`, `2,9B kişi favoriledi`) | Her üründe yok; eşik/A-B testi/cihaz etkisi olabilir | Güçlü ilgi sinyali; stokta bekleme, fiyat alarmı veya koleksiyon amacıyla da kullanılır. `B` Türkçe arayüzde “bin” olarak normalize edilmelidir. | [Favori örneği](https://www.trendyol.com/p-ora-premium-kitchen-products/gold-fincan-kupesi-aksesuarlari-p-1136202873) | 2026-08-28 |
| Sepetteki kişi sayısı | Doğrulandı / C | Sosyal kanıt bloğu (`350 kişinin sepetinde`) | Her üründe yok; değer kısa ömürlü, eşikli ve olası deneysel gösterimdir | Satın alma niyetine favoriden daha yakındır; terk edilmiş sepet ve aynı kişinin tekrarları nedeniyle satış değildir. | [Sepet örneği](https://www.trendyol.com/p-parti-oyunevi/funny-blocks-mikro-blok-500-parca-plastik-kutulu-yapi-bloklari-p-366630898) | 2026-08-28 |
| Son 24 saat görüntülenme | Doğrulandı / C | `Popüler ürün! Son 24 saatte X kişi görüntüledi` | Yalnız seçili/yeterli trafikli ürünlerde görünür | Ziyaret talebini verir; reklam, dış trafik ve haber etkisiyle satıştan kopabilir. Pencere açıkça 24 saattir. | [Görüntülenme örneği](https://www.trendyol.com/stevig/1-hand-leak-proof-sizdirmaz-celik-termos-500-ml-icy-pink-st-222-p-928039169) | 2026-08-28 |
| Ürün detayında son 3 gün satış sayısı | **Doğrulanamadı / X** | İncelenen ürün detaylarının ana bloğunda görülmedi | Liste/butik/koleksiyon kartlarında görülebiliyor; PDP'de seçici olabilir | Satıcı soru-cevap cevabındaki “X adet sattık” beyanı platform metriği değildir ve alınmamalıdır. | [Trendyol ürün örneği](https://www.trendyol.com/lisso-home/nesta-3-lu-zigon-sehpa-traverten-gri-tekerlekli-46x41-h-59-p-1142690211) | 2026-08-28 |
| Kesin/kümülatif toplam satış | **Doğrulanamadı / X** | Oturumsuz PDP'de resmi toplam sayaç görülmedi | Satıcı paneline özel olabilir veya hiç sunulmayabilir | Tahmin edilip “gerçek” diye kaydedilmemeli. | [Trendyol ürün örneği](https://www.trendyol.com/p-parti-oyunevi/funny-blocks-mikro-blok-500-parca-plastik-kutulu-yapi-bloklari-p-366630898) | 2026-08-28 |
| `Son N Ürün`, `Tükeniyor`, `Tükendi` | Doğrulandı / D | Fiyat/sepete ekle alanı ve varyant seçimi | Stok yükselirse kalkar; satıcı/renk/beden bazında değişir | Arz baskısıdır. Satış ivmesiyle birlikte değerlidir; tek başına talep kanıtı değildir. | [Son 3 ürün örneği](https://www.trendyol.com/p-ora-premium-kitchen-products/gold-premium-5-cayi-takimi-p-1050776195) | 2026-08-28 |
| Kampanya fiyatı stoğu `<5` | Doğrulandı / D | Ürün bilgi/açıklama alanında yasal fiyat notu | Kampanya fiyatı ve satıcıya bağlı | Sadece kampanyalı teklifin kalan miktarıdır; toplam stok/satış değildir. | [Stok notu örneği](https://www.trendyol.com/sr-sazanrig/sesli-isikli-luks-kamis-alarmi-p-887674600) | 2026-08-28 |
| Varyant bulunabilirliği | Doğrulandı / D | Renk/beden/ölçü seçimlerinde etkin, pasif veya tükenmiş seçenek | Seçilen satıcı ve varyanta göre değişir | Varyant bazında talep açığı tespitine yarar; anlık stok snapshot'ıdır. | [Varyantlı ürün örneği](https://www.trendyol.com/p-papuccum-ortopedi-cocuk-ayakkabilari/kiz-cocuk-ekru-cicek-nakisli-kaydirmaz-taban-ortopedik-babet-p-1160883033) | 2026-08-28 |
| Kampanya/kupon/barem etiketi | Doğrulandı / D | Fiyat üstü ve “Ürünün Kampanyaları” | Üyelik, sepet tutarı, adet, Plus veya süre koşuluna bağlı | Talep yaratıcı/bozucu değişkendir; satış sinyalinden ayrı saklanmalı. | [Kampanya örneği](https://www.trendyol.com/p-h-panayir-home/luks-gumus-platin-yaldiz-dantel-desen-mesrubat-su-bardak-takimi-6-adet-sofra-masa-icecek-ceyizlik-p-811853416) | 2026-08-28 |
| Son 10/14/30 günün en düşük fiyatı | Doğrulandı / D | Ürün ve liste kartındaki fiyat etiketi | Ürün/fiyat geçmişi ve kampanya koşuluna bağlı | Fiyat şoku/ivme açıklayıcısıdır; talep kanıtı değildir ama sıçramanın nedenini açıklar. | [Trendyol klima listesi](https://www.trendyol.com/klima-x-c104080) | 2026-08-28 |
| Flaş Ürün ve geri sayım | Doğrulandı / D | Ürün/listede `Flaş Ürün` + sayaç | Belirli 3 veya 24 saatlik kampanya aralıklarında | Seçilme mantığında fiyat esnekliği vardır; organik trend ile karıştırılmamalı. | [Resmi kampanya açıklaması](https://www.trendyol.com/s/kampanya-detaylari) | 2026-08-28 |
| Satıcı puanı ve takipçi sayısı | Doğrulandı / D | Satıcı kartında | Satıcıya göre; yeni satıcıda sınırlı | Ürün talebinden çok satıcı güveni/dağıtım gücüdür. Aynı ürünün çok satıcılı yapısında kontrol değişkenidir. | [Trendyol ürün örneği](https://www.trendyol.com/p-ora-premium-kitchen-products/gold-fincan-kupesi-aksesuarlari-p-1136202873) | 2026-08-28 |
| Resmi/Yetkili/Başarılı satıcı rozeti | Doğrulandı / D | Satıcı adı ve liste kartı | Uygun satıcı statüsü yoksa görünmez | Dönüşümü etkileyebilir; talep göstergesi değildir. | [Trendyol Samsung listesi](https://www.trendyol.com/samsung-cep-telefonu-x-b794-c103498) | 2026-08-28 |
| Hızlı teslimat/Bugün kargoda/Kargo bedava | Doğrulandı / D | Fiyat ve ürün kartı çevresi | Adres, saat, satıcı ve stok değiştirir | Dönüşüm açıklayıcısı; çekirdek talep skoru dışında bağlamsal tutulmalı. | [Trendyol cep telefonu listesi](https://www.trendyol.com/cep-telefonu-x-c103498) | 2026-08-28 |
| “Ürünü satın aldı” doğrulaması | Doğrulandı / C | Soru/yorum yazarının yanında | Satın alma ilişkisi doğrulanmamış içerikte görünmez | İçerik kanıt kalitesini artırır; kullanıcı adı/kişisel içerik toplanmamalı. | [Trendyol soru-cevap örneği](https://www.trendyol.com/hc-care/complex-dokulme-karsiti-yogun-onarici-bitkisel-sac-bakim-kompleksi-saglikli-uzama-etkili-p-7103578) | 2026-08-28 |

### 1.2 Trendyol — listeleme, butik, mağaza ve koleksiyon kartları

| Sinyal | Durum / sınıf | Nerede görünür | Gizlenme veya değişme koşulu | Güvenilirlik notu | Kaynak | Gözlem tarihi |
|---|---|---|---|---|---|---|
| `3 günde X/X+ ürün satıldı` | Doğrulandı / A | Ana sayfa, butik, mağaza veya koleksiyon ürün kartı sosyal kanıtı | Her kartta görünmez; eşik/bucket kullanılır (`100+` gibi) | En değerli yakın dönem doğrudan sinyal. Metin ve bucket aynen saklanmalı; `100+` değeri 100'e eşitlenmemeli. | [Trendyol ana sayfa](https://www.trendyol.com/) | 2026-08-28 |
| Kartta sepetteki kişi | Doğrulandı / C | Butik/koleksiyon/mağaza kartında (`X kişinin sepetinde`) | Seçici sosyal kanıt; kısa ömürlü | Satın alma niyeti, satış değil. Zaman penceresi metinde belirtilmediyse `period=null`. | [Erkek butik listesi](https://www.trendyol.com/butik/liste/2/erkek) | 2026-08-28 |
| Kartta favorileyen kişi | Doğrulandı / C | Butik/koleksiyon/mağaza kartı | Seçici gösterim | İlgi havuzu. Satış ile aynı ölçeğe sokulmamalı. | [Kadın butik listesi](https://www.trendyol.com/butik/liste/1/kadin) | 2026-08-28 |
| Kartta 24 saat görüntülenme | Doğrulandı / C | Butik/koleksiyon/mağaza kartı | Seçici gösterim | Güncel trafik ivmesini verir; reklam ayrıştırması yoktur. | [Kadın butik listesi](https://www.trendyol.com/butik/liste/1/kadin) | 2026-08-28 |
| `En Çok Satan N. Ürün` | Doğrulandı / B | Kategori, marka ve mağaza liste kartlarında | Sıra kapsamı alt kategori/özellik kümesine göre değişebilir; aynı geniş sayfada birden fazla `1.` görülebilir | Resmi açıklamaya göre ilgili kategori/marka/mağazada son 30 gün satışına dayanır. Mutlaka `rank_scope` saklanmalı. | [Resmi kampanya açıklaması](https://www.trendyol.com/s/kampanya-detaylari) | 2026-08-28 |
| `En Çok Favorilenen N. Ürün` | Doğrulandı / B | Kategori/marka/mağaza kartı | Seçili ürünlerde; kapsam görünür bağlama bağlı | Resmi açıklamadaki “En Favoriler/Favori Ürünler” yüzeyleri son 30 gün favorilerine dayanır. Satıştan ayrı tutulmalı. | [Trendyol Samsung listesi](https://www.trendyol.com/samsung-cep-telefonu-x-b794-c103498) | 2026-08-28 |
| `En Çok Ziyaret Edilen N. Ürün` | Doğrulandı / B | Kategori/marka/mağaza kartı | Seçili ürünlerde; zaman penceresi kartta yazmıyor | Göreli trafik sırası; satış dönüşümü bilinmez. | [Trendyol klima listesi](https://www.trendyol.com/klima-x-c104080) | 2026-08-28 |
| `En Çok Değerlendirilen N. Ürün` | Doğrulandı / B/C | Kategori/marka/mağaza kartı | Seçili ürünlerde | Göreli birikimli sosyal kanıt; yeni trendleri geç yakalar. | [Trendyol cep telefonu listesi](https://www.trendyol.com/cep-telefonu-x-c103498) | 2026-08-28 |
| Puan + değerlendirme sayısı | Doğrulandı / C | Hemen her ürün kartında | Yorum yoksa boş | Ürünleri aynı kategori ve yaş kohortunda karşılaştırmak gerekir. | [Trendyol mobilya listesi](https://www.trendyol.com/mobilya-x-c1119) | 2026-08-28 |
| Kampanya/kupon/çok al az öde/Plus etiketi | Doğrulandı / D | Ürün kartı | Kampanya ve kullanıcı koşuluna bağlı | Talep sıçramasını açıklayan özellik; organik popülerlik değildir. | [Trendyol takı listesi](https://www.trendyol.com/taki-mucevher-x-c28) | 2026-08-28 |
| Flaş Ürün | Doğrulandı / D | Ürün kartı + sayaç | 3/24 saatlik aralıkla güncellenen kampanya | Trend değil, promosyon yüzeyidir; ayrı bayrak. | [Flaş ürünler](https://www.trendyol.com/flas-indirimler) | 2026-08-28 |
| `Son N Ürün`/`Tükeniyor` | Doğrulandı / D | Kart veya hızlı bakış | Satıcı/varyant stokuna bağlı | Arz daralması; satış ivmesiyle birlikte yorumlanmalı. | [Trendyol ürün örneği](https://www.trendyol.com/p-ora-premium-kitchen-products/gold-premium-5-cayi-takimi-p-1050776195) | 2026-08-28 |
| Sponsorlu ürün açıklaması | Doğrulandı / D | Liste alanında sponsorlu blok/uyarı | Reklam yerleşimi varsa | Organik sıra ile reklamı ayırmak zorunludur. Sponsorlu pozisyon trend kanıtı olarak kullanılmamalı. | [Trendyol kategori örneği](https://www.trendyol.com/klima-x-c104080) | 2026-08-28 |
| Liste pozisyonu | Kısmi / C | Arama/kategori kart sırası | Kişiselleştirme, reklam, stok, teslimat, fiyat ve algoritma değiştirir | Tek snapshot'ta zayıf; aynı anonim bağlamda zaman serisi olarak yararlı. | [Trendyol kategori örneği](https://www.trendyol.com/mobilya-x-c1119) | 2026-08-28 |
| Sonuç sayısı/rekabet yoğunluğu | Doğrulandı / D | Arama/kategori başlığı veya sayfa sayısı | Filtre ve kategoriye göre | Talep değil arz/rekabet göstergesidir; “yüksek talep” diye puanlanmamalı. | [Trendyol mobilya listesi](https://www.trendyol.com/mobilya-x-c1119) | 2026-08-28 |

### 1.3 Hepsiburada — ürün detay sayfası

| Sinyal | Durum / sınıf | Nerede görünür | Gizlenme veya değişme koşulu | Güvenilirlik notu | Kaynak | Gözlem tarihi |
|---|---|---|---|---|---|---|
| Ortalama ürün puanı | Doğrulandı / C | Ürün başlığı ve değerlendirme bölümünde | Yorum yoksa `Henüz değerlendirilmemiş` | Memnuniyet göstergesi; satış değildir. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Değerlendirme sayısı | Doğrulandı / C | Puan yanında ve `Tüm Değerlendirmeler (N)` | Değerlendirme yoksa sayı yok | Bir ürün ailesi/varyantları aynı değerlendirme havuzunu paylaşabilir; teklif/satıcı satışına eşit değildir. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| 1–5 yıldız dağılımı | Doğrulandı / C | Tüm değerlendirmeler alanı | Yeterli yorum yoksa anlamsız veya gizli | Kalite/şikâyet riskini gösterir; keşif talebinden ayrı kalite vektörü olmalı. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Özellik bazlı puan ve oy sayısı | Doğrulandı / C | `Öne çıkan özellikler` (`Batarya 4,8 (88)` gibi) | Kategori ve yeterli değerlendirmeye bağlı | Ürün-kaynak eşleşmesinde malzeme/fonksiyon doğrulamasına yardımcı; satış sinyali değildir. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Kullanıcı fotoğraf/video varlığı | Doğrulandı / C | Değerlendirme bölümünde | Medyalı yorum yoksa görünmez | Gerçek kullanım ve ürün biçimi için güçlü eşleştirme kanıtı; kullanıcı medyası yeniden yayımlanmamalı. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Soru-cevap sayısı | Doğrulandı / C | Ürün bilgileri sekmesinde (`Soru Cevap 2`) | Soru yoksa sayı yok | İlgi + içerik belirsizliği karışımıdır. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/irc-ic-lastik-samyel-motosiklet-3-50-4-00-12-irc-egri-sibop-pm-HBC000031HXC8) | 2026-08-28 |
| Stok adedi eşiği | Doğrulandı / D | Ürün bilgileri (`20/50/500/1.000/10.000 adetten az`) | Ürün/satıcı/veri alanına göre görünmez; kaba bucket'tır | Arz sinyali. `10.000'den az` talep veya fiili stok doğruluğu sağlamaz; satıcı teklif/varyant kapsamı belirsizdir. | [Hepsiburada stok örneği](https://www.hepsiburada.com/irc-ic-lastik-samyel-motosiklet-3-50-4-00-12-irc-egri-sibop-pm-HBC000031HXC8) | 2026-08-28 |
| Satış adedi (`X adet satıldı`) | **Doğrulanamadı / X** | İncelenen oturumsuz ürün ana bloklarında görülmedi | Seçici bir deney varsa gözleme girmemiş olabilir | Emsal araçların tahmini, Hepsiburada'nın yayımladığı satış adedi sayılmamalı. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Favori sayısı | **Doğrulanamadı / X** | İncelenen oturumsuz ürün metninde sayaç görülmedi | Oturum/uygulama/deneysel bağlamda bulunabilir | Görülmeden alan uydurulmamalı. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Sepetteki kişi sayısı | **Doğrulanamadı / X** | İncelenen oturumsuz PDP'de görülmedi | Oturum/uygulama/deneysel bağlam olası | Görülmeden tahmin edilmemeli. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| Görüntülenme sayısı | **Doğrulanamadı / X** | İncelenen oturumsuz PDP'de görülmedi | Oturum/uygulama/deneysel bağlam olası | Görülmeden tahmin edilmemeli. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | 2026-08-28 |
| `En çok satan #N` | Kısmi / B | Ürün sayfası çevresindeki kategori önerileri ve bazı sonuçlarda | Etiket hedef ürüne değil önerilen karta ait olabilir | DOM bağlamı doğrulanmadan PDP hedef ürününe yazılmamalı. Liste kartındaki ürün bağlantısıyla birlikte yakalanırsa güvenilir. | [Hepsiburada kategori örneği](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | 2026-08-28 |
| Kampanya/kupon/Premium/sepete özel | Doğrulandı / D | Fiyat ve satıcı teklifinde | Üyelik, kart, sepet veya süre koşuluna bağlı | Dönüşümü ve fiyatı etkiler; talep kanıtı değildir. | [Hepsiburada top listesi](https://www.hepsiburada.com/dv/alisverisin-top-listesi) | 2026-08-28 |
| Satılamıyor/tükendi | Doğrulandı / D | Sepete ekle alanında | Stok/teklif açılırsa kalkar | Arz yokluğu; yüksek talep sonucu olduğu kanıtlanmadıkça trend sayılmaz. | [Satılamayan ürün örneği](https://www.hepsiburada.com/cok-satanlar-paketi-2-pm-HBC0000619QZP) | 2026-08-28 |
| Satıcı puanı/Buybox teklifi | Doğrulandı / D | Satıcı ve teklif alanında | Çok satıcılı ürünlerde değişir | Dönüşüm/güven ve fiyat açıklayıcısıdır; ürün talebi değildir. | [Hepsiburada ürün örneği](https://www.hepsiburada.com/sulhul-21-6v-v6-v7-ektrikli-supurge-icin-li-ion-sarj-pcb-devre-karti-yurt-disindan-pm-HBC00007AOL47) | 2026-08-28 |

### 1.4 Hepsiburada — arama ve kategori listeleme

| Sinyal | Durum / sınıf | Nerede görünür | Gizlenme veya değişme koşulu | Güvenilirlik notu | Kaynak | Gözlem tarihi |
|---|---|---|---|---|---|---|
| Puan + değerlendirme sayısı | Doğrulandı / C | Liste kartında | Yorum yoksa görünmez | Aynı ürün ailesinde tekrarlanabilir; satış adedi değildir. | [Bluetooth kulaklık kategorisi](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | 2026-08-28 |
| `Çok satanlar` sıralaması | Doğrulandı / A/B | Kategori/arama sıralama menüsünde | Kullanıcı seçimi; zaman penceresi platformca değişebilir | Hepsiburada resmi açıklamasına göre belirli zaman aralıklarında en çok sipariş alan ürünleri öne çıkarır. Adet ve tam pencere açıklanmamıştır. | [Resmi sıralama açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | 2026-08-28 |
| `En çok satan #N` kart rozeti/sırası | Doğrulandı / B | Bazı kategori ve öneri kartlarında | Kategori ve kart tasarımına bağlı | Göreli sıralama kanıtıdır; `rank_scope` ve ürün URL'si birlikte saklanmalı. | [Hepsiburada akıllı saat kategorisi](https://www.hepsiburada.com/akilli-saatler-c-60003676) | 2026-08-28 |
| `Çok değerlendirilenler` | Doğrulandı / C | Sıralama menüsü | Kullanıcı seçimi | Resmi açıklamada yorum sayısına göre azalan sıralamadır; birikimli olduğu için yeni trendi geciktirir. | [Resmi sıralama açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | 2026-08-28 |
| `Yüksek puanlılar` | Doğrulandı / C | Sıralama menüsü | Kullanıcı seçimi | Kalite sinyali; az yorumlu ürünlerde oynaktır. | [Resmi sıralama açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | 2026-08-28 |
| `Yeni eklenenler` | Doğrulandı / D | Sıralama menüsü | Kullanıcı seçimi | Resmi açıklamada listeye en yeni çıkan ürünleri öne alır; tazelik var, talep kanıtı yok. | [Resmi sıralama açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | 2026-08-28 |
| `Önerilen sıralama` | Doğrulandı / karma | Varsayılan liste | Yeni ve sponsorlu ürünler öncelik alabilir | Resmi açıklama en çok satış yapılanları öne çıkardığını, ancak yeni/sponsorlu ürünlerin de önceliklenebileceğini söyler. Organik satış sırası olarak kullanılamaz. | [Resmi sıralama açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | 2026-08-28 |
| Fotoğraflı değerlendirme filtresi | Doğrulandı / C | Kategori filtresi (`Fotoğraflı Değerlendirme`) | Kategoriye göre | Ürün seçme filtresidir; adet veya trend değildir. | [Bluetooth kulaklık kategorisi](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | 2026-08-28 |
| Güncel değerlendirme filtresi | Doğrulandı / C | Kategori filtresi (`Güncel Değerlendirme`) | Kategoriye göre | Yeni sosyal kanıt varlığını işaretleyebilir; tanımı ve pencere açıklanmadı. | [Bluetooth kulaklık kategorisi](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | 2026-08-28 |
| Kampanya/kupon/Premium/indirim | Doğrulandı / D | Liste kartında | Üyelik ve kampanya koşuluna bağlı | Talep değişiminin açıklayıcısı; çekirdek talep değil. | [Hepsiburada top listesi](https://www.hepsiburada.com/dv/alisverisin-top-listesi) | 2026-08-28 |
| Favori/sepet/görüntülenme sayaçları | **Doğrulanamadı / X** | İncelenen oturumsuz kategori kartlarında görünmedi | Uygulama/oturum/deneysel bağlam olası | Yokmuş gibi değil, “gözlemde yok” olarak tutulmalı. | [Bluetooth kulaklık kategorisi](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | 2026-08-28 |
| Liste pozisyonu | Kısmi / C | Arama/kategori kart sırası | Sıralama seçimi, sponsor, yeni ürün, kişiselleştirme etkiler | Yalnız seçili `Çok satanlar` sıralamasında ve anonim bağlam sabitlenince anlamlıdır. | [Resmi sıralama açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | 2026-08-28 |
| Sonuç sayısı | Doğrulandı / D | Kategori başlığı (`10.000+ ürün`) | Filtre ve kategoriye göre | Arz/rekabet yoğunluğu; talep değildir. | [Bluetooth kulaklık kategorisi](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | 2026-08-28 |

## 2. Trend tespit yüzeyleri

### 2.1 Trendyol

| Yüzey | Adres kalıbı / örnek | Kategori kırılımı | Gözlemlenebilen sıralama mantığı | Durum / sınırlama | Gözlem tarihi |
|---|---|---|---|---|---|
| Çok Satanlar ana yüzeyi | `/cok-satanlar?type=bestSeller&webGenderId={id}` — [örnek](https://www.trendyol.com/cok-satanlar?type=bestSeller&webGenderId=1) | Üst kategori/cinsiyet bağlamı | Resmi kampanya açıklamasına göre ilgili kategori, marka veya mağazanın son 30 günlük satışları | Sayfa dinamik; ürün gövdesi her istemcide aynı biçimde açılmayabilir. `webGenderId` anlamları kodda sabit varsayılmamalı. | 2026-08-28 |
| Kategori listesi | `/{slug}-x-c{categoryId}` — [mobilya](https://www.trendyol.com/mobilya-x-c1119) | Kategori ve alt kategoriler | Kartlarda `En Çok Satan/Favorilenen/Ziyaret Edilen/Değerlendirilen N. Ürün` | Aynı geniş sayfada farklı alt kapsamlar nedeniyle birden fazla `1.` olabilir; scope etiketi görünmüyorsa güven düşürülmeli. | 2026-08-28 |
| Marka + kategori | `/{brand}-{category}-x-b{brandId}-c{categoryId}` — [Samsung telefon](https://www.trendyol.com/samsung-cep-telefonu-x-b794-c103498) | Marka × kategori | Marka/kategori bağlamında göreli rozetler | Marka adı/slug değişebilir; sayısal kimlik kanonikleştirilmelidir. | 2026-08-28 |
| Mağaza | `/magaza/{slug}-m-{merchantId}` — [Braun Shop](https://www.trendyol.com/magaza/braun-shop-m-194191) | Mağaza ürünleri | Popüler ürünler, 3 günlük satış/sepet ve 24 saatlik görüntülenme sosyal kanıtı; kart sıraları | Mağaza performansı ürün talebine karışır; ürün bazında ayrıştırılmalı. | 2026-08-28 |
| Arama | `/sr?q={query}` ve ek filtre parametreleri | Sorgu + filtre | Kart rozetleri ve sosyal kanıtlar görülebilir | Trendyol robots dosyası `/sr` ve pek çok sorgu parametresini taramaya kapatıyor; arka plan taraması yapılmamalı. Kullanıcının açık sayfasından tekil okuma sınırı korunmalı. | 2026-08-28 |
| Butik/kategori vitrinleri | `/butik/liste/{id}/{slug}` — [kadın](https://www.trendyol.com/butik/liste/1/kadin) | Üst kategori ve kampanya vitrinleri | Kartlarda seçici 3 günlük satış, sepet, favori ve 24 saat görüntülenme | Vitrin/merchandising etkisi yüksek; organik trend değildir ama erken sinyal üretir. | 2026-08-28 |
| Flaş Ürünler | `/flas-indirimler` → tarih/saat etiketli dinamik `/sr?tag=...` | Kampanyaya giren ürünler | Resmi açıklamaya göre fiyat esnekliği yüksek ürünler, 3 veya 24 saatlik aralıklarla güncellenir | **Trend yüzeyi değil, promosyon yüzeyi.** Yalnız talep sıçraması açıklayıcısı olarak alınmalı. | 2026-08-28 |
| Trend Ürünler | Kampanya/marka/kategori içinde adlandırılmış yüzey; kararlı tek URL doğrulanamadı | İlgili kategori/marka/mağaza | Resmi açıklama: belirlenen bir periyotta en çok satılan ve görüntülenenler | Periyot açıklanmadı; `window_unknown=true`. | 2026-08-28 |
| En Favoriler / Favori Ürünler | Kampanya/marka/kategori içinde; kararlı tek URL doğrulanamadı | İlgili kategori/marka/mağaza | Son 30 gün en çok favorilenenler | Satış değil ilgi sinyali. | 2026-08-28 |
| Haftanın/Ayın Favorileri | Kampanya yüzeyi; kararlı tek URL doğrulanamadı | İlgili kategori/marka/mağaza | Son 7 gün veya ilgili ay favorileri | Dönem adı görünürse kullanılmalı; URL tahmin edilmemeli. | 2026-08-28 |
| En Çok Sepete Eklenenler | Kampanya yüzeyi; kararlı tek URL doğrulanamadı | İlgili kategori/marka/mağaza | Resmi açıklama: son 30 gün sepete eklenme | Satışa yakın niyet sinyali; sepet terkini içerir. | 2026-08-28 |
| Yeni Ürünler | Kategori/marka/mağaza kampanya yüzeyi; üst menüde `Yeni` görüldü, kararlı hedef URL doğrulanamadı | İlgili kapsam | `Yeni Ürün` etiketi alan ürünler | Tazelik sinyali; talep değildir. | 2026-08-28 |

### 2.2 Hepsiburada

| Yüzey | Adres kalıbı / örnek | Kategori kırılımı | Gözlemlenebilen sıralama mantığı | Durum / sınırlama | Gözlem tarihi |
|---|---|---|---|---|---|
| Alışverişin top listesi | [`/dv/alisverisin-top-listesi`](https://www.hepsiburada.com/dv/alisverisin-top-listesi) | `Tümü` ve sayfadaki kategori seçimi | Sekmeler: `En çok bakılanlar`, `En çok tekrar alınanlar`, `En çok satanlar`, `En popüler ürünler` | En güçlü hazır keşif yüzeyi. Zaman pencereleri ve “popüler” bileşimi açıklanmadı; sekme metniyle birlikte snapshot alınmalı. | 2026-08-28 |
| Çok Satanlar | [`/dv/cok-satanlar`](https://www.hepsiburada.com/dv/cok-satanlar) | Dinamik/tema veya kategori içeriği | Başlık doğrulandı | Ürün gövdesi ve dönem mantığı gözlemde netleşmedi; top listesi kadar güvenilir entegrasyon hedefi değil. | 2026-08-28 |
| Kategori listesi | `/{slug}-c-{categoryId}` — [Bluetooth kulaklık](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | Kategori ve filtreler | `Çok satanlar`, `Çok değerlendirilenler`, `Yüksek puanlılar`, `Yeni eklenenler`, fiyat ve indirim sıraları | Resmi tanımlar mevcut. URL sorgu parametresi uydurulmamalı; kullanıcı arayüzü seçimi sonrası görünen sayfa okunmalı. | 2026-08-28 |
| Çok satanlar sıralaması | Kategori/arama sıralama seçeneği | Aktif kategori veya sorgu | Belirli zaman aralıklarında en çok sipariş oluşturulan ürünler üstte | Tam dönem/adet açıklanmadı; sıralama göreli kanıttır. | 2026-08-28 |
| En çok tekrar alınanlar | Top listesi sekmesi | Tümü/kategori | Tekrar satın alma davranışına dayalı olduğu sekme adından açık | Formül, tekrar penceresi ve müşteri tekilleştirmesi açıklanmadı; sarf/tüketim ürünlerini kayırır. | 2026-08-28 |
| En çok bakılanlar | Top listesi sekmesi | Tümü/kategori | Görüntülenme/popülerlik odaklı göreli liste | Görüntülenme sayısı ve pencere yayımlanmıyor; reklam trafiği ayrıştırılamaz. | 2026-08-28 |
| Yeni eklenenler | Kategori sıralaması | Aktif kategori | Resmi açıklama: listeye en yeni çıkan ürünler üstte | Talep değil tazelik havuzu; değerlendirme ivmesiyle birleştirilmeli. | 2026-08-28 |
| Trend Ürünler ve Aramalar | Sayfa altı bağlantı kümesi; [sıralama açıklaması sayfasında örnek](https://www.hepsiburada.com/staticpage/64572361330497) | Popüler kategori/arama bağlantıları | Seçim mantığı yayımlanmadı | Keşif sözlüğü üretir; ürün talep puanına doğrudan girmemeli. | 2026-08-28 |

## 3. TR üründen 1688 adayına eşleştirme köprüsü

### 3.1 Yöntem değerlendirmesi

Başarı beklentileri **nitel** verilmiştir; ölçülmüş başarı yüzdesi yoktur.

| Yöntem | Uygulama | Başarı beklentisi | Güçlü olduğu ürünler | Sınırlar / yanlış eşleşme riski | Kaynak | Gözlem tarihi |
|---|---|---|---|---|---|---|
| 1688 görselle arama (`以图搜款`) | Ana ürün fotoğrafını indir/kırp; ürünü merkezle; arka plan, Türkçe yazı, filigran ve aksesuar kalabalığını azalt; mümkünse 2–3 farklı açıyla ayrı arama | **Yüksek**: markasız, aynı kalıp, yaygın fabrika ürünü. **Orta/düşük**: özel tasarım, yerel montaj, yoğun lifestyle görseli | Ev/mutfak/banyo organizerleri, küçük aksesuar, plastik/metal genel ürünler | Benzer görünüm aynı malzeme/ölçü/kalite demek değildir; ters çevrilmiş/kırpılmış kopya görseller ve satıcıların birbirinden aldığı fotoğraflar bulunur. | [1688 görsel arama giriş açıklaması](https://alibaba.cn/) | 2026-08-28 |
| Başlık çevirisi + özellik anahtarları | Pazarlama sözcüklerini at; `ürün türü + işlev + malzeme + yapı + ölçü + paket adedi` sırasıyla sade Çince sorgular üret | **Orta-yüksek**: doğru ürün ismi ve ayırt edici özellik varsa | Teknik adı olan ev gereçleri, aparat, organizer, elektrikli küçük cihaz | Türkçe marka/SEO başlığı bire bir çevrilirse gürültü artar. Eş anlamlı Çince terimler ve fabrika jargonları gerekir. | Yöntem çıkarımı; 1688 metin araması ve bu araştırmadaki ürün alanları | 2026-08-28 |
| Görsel + Çince metin hibriti | İlk görsel sonuçlardan ortak Çince isim/özellikleri çıkar; ikinci turu bu terimlerle daralt; sonra görsel benzerliğiyle yeniden sırala | **En yüksek pratik beklenti** | Markasız ve çok satıcılı ürünler | İlk görsel yanlışsa metin sorgusu da yanlış kümeye kilitlenebilir; en az iki bağımsız ipucu şart. | Yöntem çıkarımı | 2026-08-28 |
| Model/üretici kodu | Başlık, teknik özellik, gövde baskısı, kılavuz ve ambalajdan model/seri kodunu aynen ara | **Yüksek**: gerçek ve ayırt edici kod varsa; aksi halde **düşük** | Elektronik, yedek parça, cihaz aksesuarı | TR satıcısı kendi SKU'sunu yazabilir; kod renk/ambalaj varyantına özgü olabilir; sahte kod tekrarları mümkündür. | Yöntem çıkarımı | 2026-08-28 |
| Barkod/GTIN/EAN | Ürün veya ambalajdaki barkodu düz metin ve görsel OCR ile ara | **Orta**: markalı aynı SKU; **düşük**: OEM/private-label 1688 kaynağı | Markalı tüketim ürünü, elektronik, paketli ürün | Çin fabrika ilanı uluslararası barkodu yazmayabilir; ithalatçı yeni barkod basabilir; aynı kalıp farklı barkoda sahip olabilir. | Yöntem çıkarımı | 2026-08-28 |
| OCR: ambalaj/gövde yazısı | Görsellerden Çince/İngilizce marka, patent/model, voltaj, hacim ve kalıp işaretlerini çıkar | **Orta-yüksek**: okunaklı ayırt edici iz varsa | Elektronik, şarjlı ürün, aparat, kalıplı plastik | Görsel çözünürlüğü, dekoratif yazı ve yeniden markalama hata üretir; OCR sonucu insan doğrulamasından geçmeli. | Yöntem çıkarımı | 2026-08-28 |
| Ölçü/geometri parmak izi | Boyut oranı, delik/raf/göz sayısı, bağlantı biçimi, hacim, parça sayısı ve ağırlığı yapılandırılmış eşleştir | **Orta-yüksek** doğrulama gücü; aday üretmekte tek başına **düşük** | Organizer, mobilya aparatı, mutfak seti, yedek parça | İlan ölçüleri yuvarlanabilir; fotoğraf perspektifi yanıltır; aynı kalıp farklı malzeme olabilir. | Yöntem çıkarımı | 2026-08-28 |
| Video kareleri | Ürünün temiz göründüğü 2–4 ana kare çıkarıp ayrı görsel arama yap | **Orta**; ana fotoğrafın lifestyle olduğu durumlarda yükselir | Mekanizmalı/katlanır/dönen ürünler | Sıkıştırma, hareket bulanıklığı ve yazı bindirmeleri; video telifi ve saklama sınırı. | Yöntem çıkarımı | 2026-08-28 |

### 3.2 Önerilen eşleştirme akışı

| Adım | İşlem | Zorunlu çıktı | Gözlem tarihi |
|---|---|---|---|
| 1 | TR sayfasından yalnız görünür ürün verisini yakala | kanonik URL, platform ürün kimliği, başlık, marka, kategori, ana görseller, varyant, ölçü/malzeme/parça adedi, model/barkod izleri | 2026-08-28 |
| 2 | Görselleri arama için hazırla | `original`, ürün odaklı `crop`, varsa ikinci açı; görsel kaynağı ve kullanım amacı | 2026-08-28 |
| 3 | Çince sorgu üret | ana isim + 3–6 ayırt edici özellik; `同款` yalnız ek varyant sorgu olarak | 2026-08-28 |
| 4 | Üç bağımsız aday kanalı çalıştır | görsel arama adayları, Çince metin adayları, model/barkod adayları | 2026-08-28 |
| 5 | Adayları sınıflandır | `EXACT_SKU`, `SAME_MOLD`, `FUNCTIONAL_SIMILAR`, `REJECTED` | 2026-08-28 |
| 6 | Zorunlu fark kontrolü | ölçü, malzeme, paket içeriği, renk/varyant, elektrik değerleri, logo/marka, aksesuarlar, sertifika iddiaları | 2026-08-28 |
| 7 | İnsan onayı | en az iki bağımsız eşleşme kanıtı + açık fark listesi; otomatik “aynısı” kararı yok | 2026-08-28 |

### 3.3 Eşleşme güveni için önerilen kanıt kuralları

| Sonuç | Minimum şart | Gözlem tarihi |
|---|---|---|
| `EXACT_SKU` | Aynı doğrulanabilir model/GTIN **ve** görsel/teknik özellik uyumu | 2026-08-28 |
| `SAME_MOLD` | Geometri, ölçü, parça yapısı ve en az iki görsel açı uyuşuyor; marka/paket farklı olabilir | 2026-08-28 |
| `FUNCTIONAL_SIMILAR` | İşlev aynı; ölçü, malzeme, form veya aksesuar farkı var | 2026-08-28 |
| `REJECTED` | Kritik ölçü, mekanizma, elektrik değeri, paket içeriği veya marka/hak durumu uyuşmuyor | 2026-08-28 |

## 4. Emsal taraması — kamuya açık yöntem düzeyi

**Önemli:** Ticari araçların çoğu hesap formülünü yayımlamıyor. Aşağıdaki tabloda yalnız açıkladıkları giriş/çıktılar yazılmış, açıklanmayan formül açıkça işaretlenmiştir.

| Emsal | Kapsam ve kamuya açık yöntem | Kullandığı/açıkladığı sinyaller | Satış tahminini nasıl kullanıyor | Şeffaflık ve not | Kaynak | Gözlem tarihi |
|---|---|---|---|---|---|---|
| Trendyol Ürün İstatistikleri (açık kaynak) | Trendyol liste kartlarının görünür DOM alanlarını okur | favori sayısı; sosyal kanıt içindeki sepete ekleme değeri; fiyat | Açık kaynak kodda `salesEstimate = addedToCart * 0.16` sabit çarpanı kullanıyor; ürünleri favoriye göre sıralıyor | **Düşük güven:** 0,16 kalibrasyonu belgelenmemiş; DOM seçicileri kırılgan; kodda sayı kısaltması dönüşüm hatası da görülüyor. TedarikApp için “yapılmaması gereken sabit oran” emsalidir. | [Kaynak kod](https://github.com/mebularts/trendyol-stats/blob/main/content.js) | 2026-08-28 |
| NeSatılır.com | Trendyol, Hepsiburada ve N11 arama sonucunda uzantı çalıştırılır; sayfadaki ürünlere metrik getirir | tahmini aylık satış/ciro, yorum sayısı; gün gün fiyat ve yorum tarihçesi; mağaza puanı gibi filtreler | Aylık satış/ciro ve kârlılık araştırmasına dönüştürür | Formül ve kalibrasyon seti kamuya açık değil. Tarihçe tutması, tek snapshot yerine delta yaklaşımının emsalidir. | [Chrome ürün araştırma](https://nesatilir.com/chrome-urun-arastirma) | 2026-08-28 |
| Emparator | Trendyol ve Hepsiburada ürün bağlantısı/eklenti; web panelinde tahmini günlük satış | tahmini satış, fiyat geçmişi, rakip mağaza, kategori trendi, stok, Buybox | Ürün bulma, günlük satış tahmini ve kâr hesabında kullanır | Hangi sinyalin hangi ağırlıkla tahmine girdiği açıklanmamış. “Tahmini” etiketi korunuyor. | [Emparator](https://www.emparator.com/) | 2026-08-28 |
| SatışAnaliz | Trendyol ürün, arama ve kategori sayfalarında anlık tahmin | Kamuya açık mağaza sayfası yalnız anlık satış tahmini/rakip analizi kapsamını açıklıyor | Sayfa üstünde anlık satış tahmini | Formül/sinyal listesi açıklanmamış; Trendyol'un resmi ürünü olmadığını belirtiyor. | [Chrome Web Store](https://chromewebstore.google.com/detail/sat%C4%B1%C5%9Fanaliz-trendyol-%C3%BCr%C3%BCn/epjipdbaijiceeeafgapgpcngopajhje) | 2026-08-28 |
| TPro360 | Trendyol PDP, kategori ve arama sayfalarında analiz; panelde takip | tahmini aylık satış, stok, fiyat geçmişi, rakip/reklam analizi, komisyon/kargo/KDV | Aylık satış ve kârlılık/ürün araştırmasına çevirir | Tahmin formülü açıklanmamış; satış tahmini ile gerçek stok/fiyat takibi aynı üründe yan yana sunuluyor. | [Chrome Web Store](https://chromewebstore.google.com/detail/tpro360-trendyol-sat%C4%B1c%C4%B1-a/cgjaahlokendogeedjdphhoimdigkllb) | 2026-08-28 |
| Markentegra ürün analizi | Ürün linki yapıştırılır; sayfadan veri okuduğunu ve tahminlerin yaklaşık olduğunu açıkça belirtir | puan, fiyat, sepetteki kişi, görüntülenme, favori, stok; son 3 gün/1 ay satış ve ciro çıktısı | Görünür davranış/arz verilerinden yakın dönem satış ve ciro çıktısı üretir | Giriş alanları açık, formül kapalı. “Sayfadan okunur, tahminler yaklaşıktır” ayrımı iyi emsaldir. | [Markentegra](https://markentegra.com/urun-analizi-public) | 2026-08-28 |
| PriceRest | Pazaryeri sayfalarını periyodik izleyip rakip fiyat/stok değişimini ve eşleştirmeyi takip eder | fiyat, stok, rakip/satıcı, ürün eşleştirme ve zaman serisi; satış analizi iddiası | Esas kullanım fiyat izleme/dinamik fiyatlama; talep tahmininden çok değişim takibi | Satış tahmin formülü açıklanmamış. TedarikApp için fiyatın ana sinyal değil bağlamsal zaman serisi olması bakımından emsal. | [PriceRest](https://www.pricerest.com/tr/) | 2026-08-28 |

### Emsallerden çıkan tasarım dersi

| Ders | TedarikApp karşılığı | Gözlem tarihi |
|---|---|---|
| Sabit `sepet × oran = satış` formülü kanıtsızdır | Böyle bir dönüşüm çekirdeğe alınmamalı; kategori bazlı gerçek siparişle kalibre edilmeden yalnız deneysel alan olabilir. | 2026-08-28 |
| Tek snapshot yerine tarihçe daha değerlidir | Değerlendirme, sıralama, sosyal kanıt ve fiyat için `observed_at` zaman serisi tutulmalı. | 2026-08-28 |
| Tahmin ile gerçek veri etiketi ayrılmalıdır | `observed`, `platform_claim`, `derived`, `third_party_estimate` kaynak türleri zorunlu olmalı. | 2026-08-28 |
| Fiyat ve stok satışın kendisi değildir | Talep skoru ile fiyat/arz/açıklayıcı değişkenler ayrı vektörlerde tutulmalı. | 2026-08-28 |

## 5. Risk notu ve uyum sınırları

Bu bölüm hukuki görüş değildir; ürünleştirme öncesinde kullanım koşulları ve uygulanacak mevzuat için hukuk incelemesi gerekir.

### 5.1 Oturum ve erişim

| Kural | Gerekçe | Uygulama | Gözlem tarihi |
|---|---|---|---|
| Yalnız kullanıcının açık sekmesindeki görünür DOM | Görevin yasal/etik sınırı budur | Uzantı, kullanıcının `Yakalama` eylemiyle aktif sekmeyi bir kez okur. | 2026-08-28 |
| Çerez/token/yerel depolama dışa aktarılmaz | Oturum yetkisini başka sisteme taşımak kapsam dışı ve risklidir | `cookies`, auth header, localStorage/sessionStorage ve servis-worker token alanlarına erişim yok. | 2026-08-28 |
| Gizli/özel API çağrısı yok | Sayfada görünen veri ile dahili uç nokta aynı şey değildir | Ağ trafiğinden endpoint keşfetme, imza/token taklidi, mobil API veya satıcı API'sini izinsiz kullanma yok. | 2026-08-28 |
| CAPTCHA/403/429 aşılmaz | Açık erişim sınırının işaretidir | Yakalama durur, `blocked_by_platform` olayı kaydedilir; proxy/hesap rotasyonu yapılmaz. | 2026-08-28 |
| Arka plan/toplu tarama yok | Kullanıcının gördüğü sayfa verisi ilkesini ve sunucu yükü sınırını korur | Sonsuz kaydırma otomasyonu, sayfalama botu, kategori baştan sona tarama ve periyodik sunucu crawler'ı yok. | 2026-08-28 |

### 5.2 Robots.txt gözlemi

| Platform | Gözlem | Sonuç | Kaynak | Gözlem tarihi |
|---|---|---|---|---|
| Trendyol | `/sr`, `?sst=`, pek çok arama/filtre parametresi, yorum/satıcı alt yolları, `/flas-indirimler`, `/gw/` gibi yollar `Disallow` listesinde | Arama/kategori otomatik taraması yapılmamalı. Kullanıcı açık sayfasında tekil DOM okuma dahi düşük hız, açık eylem ve veri minimizasyonuyla sınırlandırılmalı. | [Trendyol robots.txt](https://www.trendyol.com/robots.txt) | 2026-08-28 |
| Hepsiburada | `/api/`, sıralama/filtre sorguları ve bazı eski çok-satan/kategori yolları `Disallow`; ürün/kategori sitemap'leri yayımlanıyor | API ve sıralama parametrelerini botla dolaşmak yok. Sitemap'in varlığı veri yeniden kullanım lisansı değildir. | [Hepsiburada robots.txt](https://www.hepsiburada.com/robots.txt) | 2026-08-28 |
| Genel yorum | Robots.txt tarayıcı trafiği yönetir; erişim kontrolü veya tek başına hukuki izin/yasak mekanizması değildir | Robots'a uymak gerekli koruyucu adımdır ama tek başına uyum sağlamaz; sözleşme, fikri hak, KVKK ve haksız rekabet ayrıca değerlendirilir. | [Google robots açıklaması](https://developers.google.com/search/docs/crawling-indexing/robots/intro) | 2026-08-28 |

### 5.3 Veri ve fikri hak riski

| Risk | Değerlendirme | Önerilen sınır | Gözlem tarihi |
|---|---|---|---|
| Ürün görselleri | Kamuya açık olması yeniden yayımlama hakkı vermez; marka/satıcı/fotoğrafçı hakları olabilir | Görsel yalnız kullanıcının 1688 eşleştirme eylemi için geçici işlenmeli; kaynak URL ve amaç kaydı tutulmalı; firma portalında yeniden yayımlama ayrı izin/inceleme gerektirir. | 2026-08-28 |
| Yorum/soru kullanıcı verisi | Kullanıcı adı, avatar, şehir, metin ve medya kişisel veri/fikri içerik doğurabilir | Talep keşfinde yalnız toplam sayılar, puan dağılımı ve anonim konu etiketleri; ad/rumuz/avatar/ham metin depolama yok. | 2026-08-28 |
| Veritabanının sistematik kopyalanması | Tekil görünür olgular ile platform kataloğunun önemli bölümünü kopyalamak aynı riskte değildir | Kullanıcı seçtiği ürünleri tekil kaydeder; kategori kataloğu topluca çoğaltılmaz veya yeniden servis edilmez. | 2026-08-28 |
| Marka/aynı ürün araması | `同款` bulmak sahte marka veya tasarım hakkı ihlaline yol açabilir | Marka/logolu sonuçlar otomatik `IP_REVIEW_REQUIRED`; markasız OEM aynı kalıp ile sahte markalı ürün ayrılır. | 2026-08-28 |
| Platform kullanım koşulları | Oturumsuz görünürlük otomasyon izni anlamına gelmez; koşullar değişebilir | Yayın öncesi güncel alıcı/kullanım koşulları hukukça incelenir; değişiklik izleme ve kill-switch bulunur. | 2026-08-28 |

## 6. TedarikApp için önerilen veri modeli

```json
{
  "platform": "trendyol|hepsiburada",
  "surface": "product|search|category|brand|store|top_list|campaign",
  "source_url": "https://...",
  "observed_at": "2026-08-28T00:00:00Z",
  "visibility": "logged_out_visible_dom",
  "product": {
    "platform_product_id": "...",
    "title": "...",
    "brand": "...",
    "category_path": ["..."],
    "variant_context": "..."
  },
  "signal": {
    "key": "recent_sales|bestseller_rank|rating_count|favorite_count|cart_count|view_count|stock_bucket",
    "value_text": "3 günde 100+ ürün satıldı",
    "value_numeric": 100,
    "operator": ">=",
    "period_hours": 72,
    "rank": null,
    "rank_scope": null,
    "source_type": "platform_claim|observed|derived|third_party_estimate",
    "evidence_class": "A|B|C|D|X",
    "definition_known": true
  },
  "capture": {
    "user_initiated": true,
    "authenticated_data_used": false,
    "hidden_api_used": false,
    "parser_version": "..."
  }
}
```

### Zorunlu model kuralları

| Kural | Neden | Gözlem tarihi |
|---|---|---|
| `value_text` her zaman korunur | `100+`, `B` ve dönem sözcüklerinin anlam kaybını önler. | 2026-08-28 |
| Bucket değerinde `operator` zorunludur | `100+` tam 100 değildir. | 2026-08-28 |
| `rank_scope` olmadan sıralama puanı düşürülür | Geniş sayfada farklı alt kategoriler aynı sıra numarasını üretebilir. | 2026-08-28 |
| `source_type` karıştırılamaz | Platform beyanı, gözlenen alan, türetilen metrik ve üçüncü taraf tahmini ayrılır. | 2026-08-28 |
| Eksik alan `0` değil `null` | Görünmeyen favori/satış sıfır değildir. | 2026-08-28 |
| Değerlendirme deltası snapshot'tan hesaplanır | Yeni talep ivmesini birikimli toplamdan ayırır. | 2026-08-28 |
| Fiyat/kampanya ayrı açıklayıcı vektördür | Keşif amacı “satış/talep”; fiyat yan bilgidir. | 2026-08-28 |

## 7. Önerilen keşif kararı

### 7.1 Platform bazlı sinyal önceliği

| Platform | Birincil | İkincil | Yalnız bağlam | Kullanılmamalı | Gözlem tarihi |
|---|---|---|---|---|---|
| Trendyol | 3 günlük platform satış sosyal kanıtı; son 30 günlük çok-satan sırası | sepet/favori/24 saat görüntülenme; değerlendirme ve soru deltası | kampanya, fiyat düşüşü, stok, teslimat, satıcı rozeti | satıcı cevabındaki satış iddiası; kanıtsız sepet→satış katsayısı | 2026-08-28 |
| Hepsiburada | Çok satanlar sıralaması; top listesi üyeliği; tekrar alınanlar | değerlendirme/puan deltası; karttaki kategori sırası | stok bucket'ı, kampanya, fiyat, Buybox/satıcı | görünmeyen favori/sepet/görüntülenme; emsal aracın tahminini platform gerçeği saymak | 2026-08-28 |

### 7.2 Nihai ürün kararı

1. **Trendyol P0 olarak eklenmeli.** En az `recent_sales_bucket`, `bestseller_rank`, `favorite_rank/count`, `cart_count`, `view_24h`, `rating_count`, `question_count`, `stock_hint`, `promo_context` alanları seçici ve `null`-güvenli ayrıştırılmalı. **Gözlem: 2026-08-28.**
2. **Hepsiburada P1 olarak eklenmeli.** İlk sürüm `top_list_type`, `category_sort=best_seller`, `bestseller_rank`, `rating_count`, `question_count`, `stock_bucket`, `promo_context` ile yetinmeli. Doğrudan satış adedi alanı ekranda kanıtlanana kadar `null` kalmalı. **Gözlem: 2026-08-28.**
3. **Trend kararı tek snapshot'tan verilmemeli.** Aynı anonim görünürlük bağlamında 1/3/7 günlük sıralama ve değerlendirme deltası; Trendyol'da ayrıca 3 günlük satış bucket değişimi izlenmeli. Bu izleme sunucu crawler'ı ile değil, kullanıcının gördüğü/yakaladığı snapshot'ların karşılaştırılmasıyla yapılmalı. **Gözlem: 2026-08-28.**
4. **1688 adayları otomatik satın alma veya “kesin aynı” sonucuna gitmemeli.** `EXACT_SKU/SAME_MOLD/FUNCTIONAL_SIMILAR` ayrımı ve insan onayı zorunlu olmalı. **Gözlem: 2026-08-28.**
5. **N11 sonraki doğrulama turuna bırakılmalı.** Aynı kanıt standardı uygulanmadan Trendyol/Hepsiburada alanları N11'e kopyalanmamalı. **Gözlem: 2026-08-28.**

## 8. Kaynak listesi

### Birincil platform kaynakları

| Kaynak | Kullanım | Gözlem tarihi |
|---|---|---|
| [Trendyol kampanya detayları](https://www.trendyol.com/s/kampanya-detaylari) | Son 30 gün çok satan/favori/sepet tanımları; Trend Ürünler ve Flaş Ürünler mantığı | 2026-08-28 |
| [Trendyol Çok Satanlar](https://www.trendyol.com/cok-satanlar?type=bestSeller&webGenderId=1) | Hazır çok-satan yüzeyi ve adres kalıbı | 2026-08-28 |
| [Trendyol mobilya kategorisi](https://www.trendyol.com/mobilya-x-c1119) | Kategori kartı sıralama rozetleri, puan, kampanya | 2026-08-28 |
| [Trendyol klima kategorisi](https://www.trendyol.com/klima-x-c104080) | Çok satan/favorilenen/ziyaret edilen/değerlendirilen rozetleri ve fiyat geçmişi etiketi | 2026-08-28 |
| [Trendyol ürün sosyal kanıt örneği](https://www.trendyol.com/stevig/1-hand-leak-proof-sizdirmaz-celik-termos-500-ml-icy-pink-st-222-p-928039169) | 24 saat görüntülenme ve sepetteki kişi | 2026-08-28 |
| [Trendyol butik örneği](https://www.trendyol.com/butik/liste/1/kadin) | Kart bazlı 3 günlük satış/sepet/favori/görüntülenme | 2026-08-28 |
| [Trendyol robots.txt](https://www.trendyol.com/robots.txt) | Tarama tercihleri ve kapalı yollar | 2026-08-28 |
| [Hepsiburada Alışverişin top listesi](https://www.hepsiburada.com/dv/alisverisin-top-listesi) | En çok bakılan/tekrar alınan/satan/popüler sekmeleri | 2026-08-28 |
| [Hepsiburada sıralama algoritması açıklaması](https://www.hepsiburada.com/staticpage/64572361330497) | Çok satanlar, değerlendirme, yeni eklenenler, önerilen sıralama tanımları | 2026-08-28 |
| [Hepsiburada Bluetooth kulaklık kategorisi](https://www.hepsiburada.com/bluetooth-kulakliklar-c-16218) | Liste sıralama seçenekleri, puan/değerlendirme, kampanya | 2026-08-28 |
| [Hepsiburada ürün değerlendirme örneği](https://www.hepsiburada.com/cmf-by-nothing-phone-1-128-gb-8-gb-nothing-turkiye-garantili-siyah-p-HBCV00008UNYVH) | Yıldız dağılımı, özellik puanları, kullanıcı medyası | 2026-08-28 |
| [Hepsiburada stok/soru örneği](https://www.hepsiburada.com/irc-ic-lastik-samyel-motosiklet-3-50-4-00-12-irc-egri-sibop-pm-HBC000031HXC8) | Stok bucket'ı ve soru-cevap | 2026-08-28 |
| [Hepsiburada robots.txt](https://www.hepsiburada.com/robots.txt) | Tarama tercihleri, API/sıralama/filtre yolları ve sitemap'ler | 2026-08-28 |
| [1688/Alibaba Çin ana sayfası](https://alibaba.cn/) | `以图搜款`: yerel yükleme, yapıştırma ve eklentiyle görsel arama seçenekleri | 2026-08-28 |

### Emsal ve yöntem kaynakları

| Kaynak | Kullanım | Gözlem tarihi |
|---|---|---|
| [Trendyol Stats açık kaynak kodu](https://github.com/mebularts/trendyol-stats/blob/main/content.js) | Sepet sosyal kanıtını sabit 0,16 ile satışa çeviren şeffaf fakat kalibrasyonsuz örnek | 2026-08-28 |
| [NeSatılır Chrome ürün araştırma](https://nesatilir.com/chrome-urun-arastirma) | Tahmini aylık satış/ciro, yorum ve fiyat/yorum tarihçesi | 2026-08-28 |
| [Emparator](https://www.emparator.com/) | Tahmini günlük satış, fiyat geçmişi, stok/Buybox ve kategori trendi | 2026-08-28 |
| [SatışAnaliz Chrome eklentisi](https://chromewebstore.google.com/detail/sat%C4%B1%C5%9Fanaliz-trendyol-%C3%BCr%C3%BCn/epjipdbaijiceeeafgapgpcngopajhje) | Trendyol ürün/arama/kategori anlık satış tahmini | 2026-08-28 |
| [TPro360 Chrome eklentisi](https://chromewebstore.google.com/detail/tpro360-trendyol-sat%C4%B1c%C4%B1-a/cgjaahlokendogeedjdphhoimdigkllb) | Tahmini aylık satış, stok/fiyat/rakip takibi | 2026-08-28 |
| [Markentegra ürün analizi](https://markentegra.com/urun-analizi-public) | Görünür sayfa sinyalleri ve yaklaşık 3 gün/1 ay satış çıktısı | 2026-08-28 |
| [PriceRest](https://www.pricerest.com/tr/) | Periyodik fiyat/stok/rakip izleme ve ürün eşleştirme | 2026-08-28 |
| [Google robots.txt açıklaması](https://developers.google.com/search/docs/crawling-indexing/robots/intro) | Robots'un trafik yönetimi amacı ve sınırları | 2026-08-28 |

---

**Son hüküm:** Trendyol ve Hepsiburada TedarikApp'e “TR talep kanıtı/keşif kaynağı” olarak eklenebilir; fakat veri sözlüğünde hiçbir zaman tedarik veya satış kanalı gibi davranmamalıdır. Trendyol'da görünen yakın dönem adet/bucket metni birinci sınıf kanıt; Hepsiburada'da resmi göreli liste/sıra birinci sınıf göreli kanıttır. Görünmeyen metrik `0` değil `null`, tahmin ise gerçek değil açıkça `estimate` olmalıdır.
