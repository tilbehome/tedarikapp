# GÖREV #30-EK — Prototip bağlama haritası

## Kapsam ve değişmezlik

Bu belge, onaylı `docs/v3/hazirlik/v3-c/gorev-30/firma-portali-prototip.html` dosyasının üretim koduna aktarım haritasıdır. HTML ve `OKUBENI.md` değiştirilmemiştir. Konum adları prototipteki JavaScript fonksiyonlarını gösterir.

## Bölüm 11 anahtarları

| Yeni anahtar | Prototipteki mevcut yer / davranış | Blok C bağlaması |
|---|---|---|
| `portal.field.product_code` | `productList()` ve `sourceCard()` içinde `p.code` etiketsiz sabit veri | Ürün kodunun görünür/erişilebilir alan etiketinde `t()` ile kullan |
| `portal.field.buyer_note` | Alıcı satır notu prototipte gösterilmiyor | `sourceCard()` salt-okunur alıcı notu etiketi |
| `portal.filter.all` | Ayrı “Tümü” düğmesi yok; seçili çipe ikinci basış `state.filter=null` yapıyor | Filtre çubuğunda açık “Tümü” seçeneği |
| `portal.filter.unanswered` | Filtre çubuğu `t(statusKey('unanswered'))` kullanıyor | Yalnız filtre bağlamında bu anahtara geçir |
| `portal.filter.invalid` | Hatalı satır filtresi yok; alan hataları yalnız `state.errors` içinde | Hata taşıyan satırları süzen filtre etiketi |
| `portal.action.clear_local_draft` | Kalıcı yerel taslak ve temizleme eylemi simüle edilmiyor | Yerel taslak bulunduğunda açık kullanıcı eylemi |
| `portal.action.retry` | Kart/snapshot hata yüzeyi prototipte yok | Kart, liste ve güvenli snapshot hata eylemleri |
| `portal.system.local_draft_restored` | Yerel taslak geri yükleme bildirimi yok | Cihaz taslağı sunucu taslağıyla karşılaştırılmadan önce bildirim |
| `portal.system.offline_submit_blocked` | `bottomBar()` düğmeleri devre dışı; denemede genel `portal.system.offline` kullanılıyor | Kısmi ve nihai gönderim engelinin özel uyarısı |
| `portal.system.session_expired` | Gerçek oturum süresi simüle edilmiyor | Hesapsız kısa ömürlü oturum sona erdiğinde hata yüzeyi |
| `portal.system.round_revoked` | Tur iptali simüle edilmiyor | İdari erişim iptalinde salt-okunur hata yüzeyi |
| `portal.conflict.compare` | Yalnız `portal.system.conflict` mesajı var | Sürüm karşılaştırma ekranını açan eylem |
| `portal.conflict.keep_server` | Çakışma çözüm eylemi yok | Sunucu sürümünü koruma seçeneği |
| `portal.conflict.keep_device` | Çakışma çözüm eylemi yok | Cihaz sürümünü yeni taslak olarak alma seçeneği |
| `portal.validation.lead_time_max` | `validate()` yalnız pozitif termin kontrolü yapıyor | `termin_suresi > 365` alan hatası |
| `portal.validation.gross_below_net` | Brüt/net çapraz kontrolü prototipte yok | `koli_brut_kg < koli_net_kg` alan hatası |
| `portal.validation.cbm_mismatch` | Ölçü/CBM fark kontrolü prototipte yok | Hesaplanan ve beyan CBM farkı %5’i aşınca teyit uyarısı |
| `portal.validation.url_invalid` | Alternatif/kaynak URL şeması doğrulanmıyor | Kaynak ve alternatif URL doğrulama hatası |
| `portal.revision.title` | `revision()` başlığı `t('portal.success.revision_notice')` kullanıyor | Revizyon ekranı başlığını bu anahtara geçir |
| `portal.revision.changed_rows` | `revision()` içinde üç fark satırı sabit; sayı etiketi yok | Değişen satır sayısını hesaplayıp yer tutucuyla göster |
| `portal.revision.copied_from_previous` | Kopyalanan değer rozeti yok | Önceki turdan başlangıç taslağına kopyalanan alan rozeti |
| `portal.readonly.valid_until` | Geçerlilik tarihi karşılama metasındaki `18.09.2026` sabitinde; salt-okunur kartta yok | `success()` / salt-okunur teklif geçerlilik etiketi |

## Enum anahtarları

| Enum grubu | Kaynak kodlar | Prototipteki mevcut yer / davranış | Blok C bağlaması |
|---|---|---|---|
| `portal.enum.termin_baslangici.*` | `order_confirmation`, `deposit_received`, `sample_approval`, `artwork_approval`, `custom` | `pricingFields()` kodları doğrudan gösteriyor; `custom` seçenek listesinde eksik | Seçenek değerini kanonik kod bırak; etiketi `t('portal.enum.termin_baslangici.' + code)` ile üret |
| `portal.enum.termin_birimi.*` | `calendar_day`, `working_day`, `week` | `pricingFields()` ve `readonlyCards()` ham kod gösteriyor | Etiketi `t('portal.enum.termin_birimi.' + code)` ile üret |
| `portal.enum.miktar_birimi.*` | `adet`, `set`, `paket`, `koli`, `kg`, `m`, `m2`, `ozel` | `unit()` yalnız adet/set için yerel sabit kullanıyor; diğer birimler seçenek olarak yok | Miktar/MOQ gösterimi ve seçiminde kanonik kod + üç dilli etiket kullan |
| Ambalaj türü | RFQ v1’de enum yok; `ambalaj` tipi `string|null`. Prototip başlangıç değeri `white_box`, alan ise serbest metin | `packageFields()` içindeki serbest metin alanı | Kaynak enum tanımlanana kadar `portal.enum.ambalaj_turu.*` üretilmez; seçenek uydurulmaz |

## Alternatif modelinin prototipten üretime bağlanması

| Prototip yapısı | V2 üretim bağı |
|---|---|
| `state.form.status='alternative_available'` | Kaldırılır; asıl cevap `not_found` kalır |
| `altLink` | Ayrı alternatif cevap nesnesi `kaynak.url` |
| Ürün başlığı / alternatif açıklaması | Ayrı nesne `ad` ve `not` |
| `tiers` + `currency` | Ayrı nesne `fiyat_kademeleri` |
| `moq` | Ayrı nesne `moq` |
| `statusPill('alternative_available')` | Asıl satıra bağlı alternatif nesnesi varsa `portal.status.alternative` / `status.alternative_available` etiketiyle türetilen rozet |

## status.viewed geçişi

| Dosya ve konum | Mevcut ödünç anahtar | Yeni bağ |
|---|---|---|
| `firma-portali-prototip.html` — gömülü `status-terms` veri bloğu | `status.waiting_supplier` katalog girdisi | 5B birleştirilirken `status.viewed` girdisini de yükle |
| `firma-portali-prototip.html` — `workflow` dizisindeki `VIEWED` adımı | `['VIEWED','status.waiting_supplier']` | `['VIEWED','status.viewed']` |
| `OKUBENI.md` — durum şeridi açıklaması | `status.waiting_supplier` VIEWED için ödünç kullanım olarak açıklanıyor | Blok C uygulama notunda `status.viewed` olarak güncelle; onaylı #30-R dosyasına bu görevde dokunma |
