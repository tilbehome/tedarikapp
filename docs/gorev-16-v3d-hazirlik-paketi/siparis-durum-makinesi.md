> İşlev: V3-D sipariş yaşam döngüsünün kalıcı durum, geçiş, bölünme ve engel sözleşmesini tanımlar.  
> Faz: V3-D uygulama ve İE#24 öncesi hazırlık girdisidir.  
> Tek kaynak: `cikti-terimleri.json:status.*` ve uygulamadaki `config/durumlar.json`; bu belge yeni enum üretmez.  
> Çıktı: geçiş bekçileri, zaman damgaları, yetkiler, çok-parti türetimi ve sipariş öncesi engel kataloğudur.  
> Kapsam dışı: ince ekran tasarımı, GTİP, mevzuat ve gümrük vergisi/oran hesabıdır.

# TedarikApp V3-D Sipariş Durum Makinesi

## 1. Kaynak ve bağlama sözleşmesi

- 5B tek kaynağı `cikti-terimleri.json` içinde 185 terim ve 15 adet `status.*` anahtarı doğrulanmıştır.
- V3-D sipariş aşamalarının hiçbiri mevcut 15 anahtarın anlamıyla birebir örtüşmediğinden `terim_ref=null` bırakılır; yeni anahtar uydurulmaz.
- `config/durumlar.json` bu hazırlık girdileri arasında bulunmadı. Aşağıdaki `durum_kod_ref` alanları bu nedenle `null`, kaynak durumu **kanıtlanmadı**dır. Uygulamada gerçek enum/kod yalnız bu dosyadan çözümlenmelidir.
- Belge içindeki `liste_asama_no` yalnız yaşam döngüsü sırasıdır; enum veya veritabanı durum kodu değildir.
- “Türkiye limanında” yalnız görünür etikettir; ayrı iş akışı, ödeme serbest bırakma veya muhasebe etkisi oluşturmaz.

## 2. Aşamalar

| Sıra | Görünür durum | `durum_kod_ref` | `terim_ref` | `terim_onerisi` (anahtarsız) | İş anlamı |
|---:|---|---|---|---|---|
| 5 | Sipariş verildi | `null` | `null` | TR: Sipariş verildi · EN: Order placed · ZH: 订单已下达 | Onaylı kalem tahsislerinden sipariş snapshot'ı oluştu. |
| 6 | Üretim/tedarikte | `null` | `null` | TR: Üretim/tedarikte · EN: In production/sourcing · ZH: 生产/备货中 | Firma üretim veya tedarik sürecini başlattı. |
| 7 | Çin limanında | `null` | `null` | TR: Çin limanında · EN: At China port · ZH: 已到中国港口 | Yük liman/terminal teslim kanıtıyla ihracat ayağına ulaştı. |
| 8 | Gemide (ETA) | `null` | `null` | TR: Gemide · EN: On vessel · ZH: 已装船 | Konşimento/yükleme kanıtı var; ETA ayrı değişebilir alandır. |
| 9 | Türkiye limanında | `null` | `null` | TR: Türkiye limanında · EN: At Türkiye port · ZH: 已到土耳其港口 | Yalnız takip etiketi; fiilî varış kanıtına dayanır. |
| 10 | Teslim edildi | `null` | `null` | TR: Teslim edildi · EN: Delivered · ZH: 已交付 | Fiziksel teslim kaydı oluşur ve mal kabul süreci açılır. |
| terminal | Kapandı | `null` | `null` | TR: Kapandı · EN: Closed · ZH: 已关闭 | Mal kabul/rücu/ödeme açık işi kalmadığında salt okunur olur. |

## 3. İleri geçişler

| # | Önce → sonra | Tetik | Koşul / bekçi | Yan etki | Tarih damgası | İzin | Gerekçeli tek-adım geri kuralı |
|---:|---|---|---|---|---|---|---|
| 1 | 5 → 6 | Ürün Sahibi “Üretim başladı” kaydı veya doğrulanmış firma kanıtı | Sipariş snapshot'ı kilitli; en az bir etkin parti; kapora gerekiyorsa kanıtlı ödeme serbest bırakılmış | Parti üretim başlangıcı ve kanıt bağı yazılır; liste durumu yeniden türetilir | `production_started_at`, `state_changed_at` | Ürün Sahibi; sistem yalnız doğrulanmış olayla | Yalnız 6→5; zorunlu gerekçe; üretim ödemesi/kanıtı iptal edilmemişse geri dönülmez, düzeltme olayı açılır. |
| 2 | 6 → 7 | Liman/terminal teslim kanıtı kaydı | Her ilgili partide koli ve brüt ağırlık snapshot'ı; yükleme referansı; engel yok | Çin limanı kilometre taşı ve belge referansı yazılır | `china_port_at`, `state_changed_at` | Ürün Sahibi | Yalnız 7→6; liman kanıtı hatalı/geri çekilmiş olmalı; gerekçe ve kanıt sürümü zorunlu. |
| 3 | 7 → 8 | Yükleme/konşimento kanıtı onayı | Gemi/voyage veya taşıma referansı; `eta_at` mevcut; kanıt dosyası mevcut | `on_vessel` kilometre taşı ödeme planını serbest bırakabilir; ETA geçmişi tutulur | `loaded_on_vessel_at`, `eta_at`, `state_changed_at` | Ürün Sahibi | Yalnız 8→7; konşimento/yükleme kaydı iptal kanıtı ister; serbest bırakılmış ödeme varsa geri dönülmez, istisna kaydı açılır. |
| 4 | 8 → 9 | Fiilî varış/terminal bildirimi | Varış kanıtı; parti hâlâ etkin | Yalnız görünür takip etiketi ve fiilî varış zamanı; muhasebe/ödeme etkisi yok | `turkiye_port_at`, `state_changed_at` | Ürün Sahibi | Yalnız 9→8; yanlış varış bildirimi gerekçesi ve kanıt düzeltmesi zorunlu. |
| 5 | 9 → 10 | Fiziksel teslim teyidi | Teslim belgesi; teslim alan; tarih; parti/konşimento eşleşmesi | Mal kabul kaydı açılır; beklenen satırlar kopyalanır; rücu penceresi iş akışı başlar | `delivered_at`, `goods_receipt_opened_at`, `state_changed_at` | Ürün Sahibi | Yalnız 10→9; hiçbir sayım/imza/foto/rücu kaydı oluşmamışsa ve teslim kanıtı iptal edilmişse mümkündür. |
| 6 | 10 → Kapandı | Ürün Sahibi “Kapat” | Tüm mal kabul satırları sonuçlu; açık rücu/iadeye konu satır yok veya karar kaydı var; açık ödeme/kur farkı yok | Sipariş/parti salt okunur; kapanış snapshot'ı ve özet hash'i üretilir | `closed_at`, `state_changed_at` | Yalnız Ürün Sahibi | Kapandı durumundan durum geri alınmaz. Yeniden açma yerine önceki snapshot'a bağlı ayrı düzeltme/uyuşmazlık kaydı oluşturulur. |

### Ortak geri-geçiş değişmezi

1. İleri akış tek yönlüdür; normal kullanıcı eyleminde atlama yapılamaz.
2. Geri geçiş en fazla bir önceki aşamaya yapılır; `rollback_reason`, yapan, tarih ve önce/sonra snapshot kimliği zorunludur.
3. Geri alma geçmiş zaman damgasını silmez; `reverted_at` ve yeni geçiş kaydı ekler.
4. Ödeme serbest bırakma, imzalı mal kabul veya kapanış gibi geri döndürülemez yan etki varsa durum geri alınmaz; düzeltme/istisna olayı açılır.
5. Firma hesabı olmadığı için dış firma durum değiştiremez; kanıt yükler, kararı Ürün Sahibi verir.

## 4. Çok-parti ve liste görünümü türetme kuralı

- Her parti kendi `party_state_rank` değerini yalnız `config/durumlar.json` çözümlemesinden alır.
- Etkin partiler: iptal edilmemiş ve tamamen başka siparişe taşınmamış partilerdir.
- Liste görünür sırası `min(etkin_parti.party_state_rank)` olur; yani liste **en gerideki etkin partinin** durumunu gösterir.
- Aynı sırada birden çok parti varsa en eski `state_changed_at` yalnız açıklayıcı ikincil sıralamadır; durumu değiştirmez.
- Bir parti Kapandı, diğeri Gemide ise liste Gemide görünür. Bütün etkin partiler Kapandı olduğunda liste Kapandı görünür.
- İptal edilen parti minimum hesabından çıkarılır fakat audit ve miktar özeti içinde görünür.
- Parti yoksa durum türetilmez; bu veri hatasıdır ve sessizce “Kapandı” gösterilmez.

## 5. Bölünebilir liste → sipariş sözleşmesi

Tahsis birimi `liste_satiri_id + varyant_id + miktar`dır. Her miktar yalnız bir kez `order_line_allocation` kaydına bağlanabilir.

| Senaryo | İşlem | Kalan kalem davranışı | Durum makinesi bağı |
|---|---|---|---|
| Kısmi onay / ilk sipariş | Onaylanan satır veya miktar Sipariş-1 snapshot'ına tahsis edilir | Tahsis edilmeyen miktar listede karar bekler; liste kapanmaz | Sipariş-1 aşama 5'te başlar; liste görünümü siparişlerden bağımsız olarak kalan kararı da gösterir. |
| Aynı listeden ikinci sipariş | Kalan uygun miktar daha sonra Sipariş-2'ye tahsis edilir | İlk sipariş satırları değişmez; ikinci sipariş ayrı kur/teklif snapshot'ı taşır | Sipariş-2 aşama 5'te bağımsız başlar; çok-parti kuralı sipariş içinde uygulanır. |
| Kalan kalem | `remaining_quantity = requested - ordered - cancelled` | Negatif olamaz; sıfırsa yeni tahsis kapısı kapanır | Kalan miktar sipariş durumuna zorla bağlanmaz. |
| İptal edilen kalem | Ürün Sahibi gerekçe ile tahsis edilmemiş miktarı iptal eder | İptal miktarı tekrar siparişe alınamaz; yeniden açma ayrı gerekçeli karar kaydıdır | İptal mevcut sipariş akışını geri götürmez. |
| Sipariş içi kısmi iptal | Yalnız üretim/ödeme bekçileri izin verirse tahsis azaltma düzeltmesi | Eski tahsis audit'te kalır; yeni toplam ve ödeme planı sürümlenir | Durum geri alınmaz; miktar düzeltme olayı ve gerekiyorsa iade kaydı açılır. |

Değişmez: `requested_quantity = ordered_quantity + remaining_quantity + cancelled_quantity`. Bu eşitlik atomik işlem sonunda sağlanmıyorsa kayıt reddedilir.

## 6. Sipariş öncesi engel kontrolleri

5B'de bu hata metinleri için birebir anahtar yoktur. Bu nedenle her satırda `hata_metni_anahtari=null`; metinler yalnız anahtarsız `terim_onerisi`dir.

| ID | Kural | Sınıf | Geçiş koşulu | `hata_metni_anahtari` | `terim_onerisi` (TR) |
|---|---|---|---|---|---|
| BLK-001 | Sipariş miktarı olan satırda varyant kimliği boş olamaz. | SERT ENGEL | Varyant seçilmeden geçilemez. | `null` | Sipariş vermeden önce varyantı seçin. |
| BLK-002 | Miktar, geçerli teklif MOQ'sunun altında olamaz. | AÇIK ONAYLA GEÇİLEBİLİR | Firma MOQ istisnası yazılı kanıtı + Ürün Sahibi gerekçesi gerekir. | `null` | Miktar MOQ'nun altında; firma istisna kanıtı olmadan devam edilemez. |
| BLK-003 | Miktar, zorunlu koli içinin tam katı olmalıdır. | AÇIK ONAYLA GEÇİLEBİLİR | Firma farklı paketlemeyi yazılı teyit eder; etkin koli içi snapshot'ı sürümlenir. | `null` | Miktar koli katına uymuyor; paketleme teyidi gerekiyor. |
| BLK-004 | Seçilen kademe, sipariş miktarını kapsamalı ve kullanılan fiyat o kademeden gelmelidir. | SERT ENGEL | Miktar veya fiyat kademesi düzeltilir. | `null` | Miktar ile fiyat kademesi uyuşmuyor. |
| BLK-005 | Karar kuru, yapılandırılmış azami yaş sınırını aşmamalıdır. | AÇIK ONAYLA GEÇİLEBİLİR | Yeni kur snapshot'ı veya eski kuru kullanma gerekçesi/açık onayı gerekir. | `null` | Kullanılan kur güncelliğini yitirdi. |
| BLK-006 | Teklif geçerlilik sonu sipariş onay zamanından önce olamaz. | SERT ENGEL | Firma geçerlilik yenilemesi veya yeni tur gerekir. | `null` | Teklifin geçerlilik süresi dolmuş. |
| BLK-007 | FCL/LCL planlanacak satırda koli CBM bilinmelidir. | AÇIK ONAYLA GEÇİLEBİLİR | Navlun rezervasyonu öncesi kapanacak eksik veri görevi ve Ürün Sahibi onayı gerekir. | `null` | Koli CBM bilgisi eksik; taşıma planı kesinleşemez. |
| BLK-008 | Aynı kaynak satır+varyant+firma+teklif turu aynı siparişte ikinci kez etkin olamaz. | SERT ENGEL | Birleştir veya yinelenen satırı kaldır. | `null` | Aynı sipariş satırı iki kez eklenmiş. |
| BLK-009 | Kaynak ilan kapanmışsa teklifin hâlen geçerli olduğu kanıtlanmalıdır. | AÇIK ONAYLA GEÇİLEBİLİR | Güncel firma teyidi + kanıt tarihi zorunludur. | `null` | Kaynak ilan kapalı; güncel firma teyidi gerekiyor. |

## 7. İzin ve atomiklik

- **Ürün Sahibi:** bütün ticari karar, açık onay, kanıt kabulü, tek-adım geri ve kapanış.
- **Sistem:** ETA uyarısı, kanıt bütünlüğü kontrolü ve liste durumunun türetilmesi; ticari kararı kendiliğinden vermez.
- **Firma:** hesapsız portal bağlantısıyla yalnız kendine açık kanıt/yanıt ekler; sipariş durumu değiştirmez.
- Her geçiş `expected_state_version + idempotency_key` ister. Çakışmada hiçbir yan etki uygulanmaz.
- Bildirim başarısızlığı durumu geri almaz; outbox tekrarlar.

## 8. Kaynaklar ve açık doğrulamalar

1. `cikti-terimleri.json` — 5B, 185 terim; mevcut `status.*` anahtarları için bağlayıcı kaynak.
2. `teklif-turu-durum-makinesi.md` — onaylı teklif snapshot'ı, kur kilidi ve tur bütünlüğü girdisi.
3. `portal-ekran-sartnameleri.md` — firma hesabı olmadığı ve dış aktör yetki sınırı girdisi.
4. `config/durumlar.json` — uygulama tek kaynağı olarak istendi fakat hazırlık paketinde bulunmadı; **kanıtlanmadı**.
5. Bu görev metni — V3-D aşama, çok-parti, bölünebilir liste ve engel sınıfları için bağlayıcı ürün kararı.
