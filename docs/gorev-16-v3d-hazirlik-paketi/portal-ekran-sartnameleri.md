# TedarikApp V3-C Firma Portalı Ekran Şartnameleri

**Bakış açısı:** Dış firma  
**Erişim:** Hesap yok; link açılır, ayrı kanaldan alınan 6 haneli anahtar girilir  
**Başlangıç noktası:** Kilit ekranı başarıyla geçildikten sonraki karşılama  
**Öncelik:** Mobil telefon; masaüstü aynı sözleşmenin geniş yerleşimidir  
**Dil:** TR / EN / ZH; ZH ticari belge üslubudur.

## 1. Değişmez ürün ilkeleri

1. Firma yalnız kendisine açılmış `liste + firma + tur` görüntüsünü görür; başka firma, fiyat, sıralama veya karşılaştırma bilgisi asla görünmez.
2. Kaynak ürün, varyant, talep miktarı, RFQ dipnotları ve satır kimliği salt okunurdur.
3. Firma için seçilebilir nihai satır cevapları: **Bulundu / Bulunamadı / Alternatif var**. `Yanıtlanmadı` taslak başlangıcı, `Tamamlandı` hesaplanan rozet, `Ek bilgi gerekli` ise satır sohbeti/ara bekleme işaretidir.
4. DDP birim fiyat etiketi her dilde Türkiye KDV dahil anlamını açık taşır. Para birimi ayrı alandır.
5. Otomatik kayıt nihai gönderim değildir. Kısmi gönderim nihai gönderim değildir. Nihai gönderimden sonra tur salt okunurdur.
6. Çevrimdışı iken girilen taslak bu tarayıcı kökeninde geçici saklanır; erişim anahtarı, ham token ve başka firmanın verisi yerel taslağa yazılmaz.
7. Tur kapanınca/anahtar yenilenince/yerel saklama süresi dolunca ilgili cihaz taslağı temizlenir. Kullanıcı “Bu cihazdaki taslağı sil” eylemine sahip olmalıdır.
8. Firma portalında iç maliyet, kilitli kur değeri, hedef satış fiyatı, TedarikApp Skoru veya Ürün Sahibinin iç notu gösterilmez.

## 2. Ortak mobil kabuk

```text
┌──────────────────────────────┐
│ TedarikApp       TR EN 中文  │
│ Liste adı · Firma · R1       │
│ 18/25 tamamlandı  █████░░    │
├──────────────────────────────┤
│ Ekran içeriği                │
│                              │
├──────────────────────────────┤
│ [Taslağı kaydet] [Devam]     │
└──────────────────────────────┘
```

- 360 px genişlikte yatay kaydırma zorunlu olmaz; tablo kartlara dönüşür.
- Dil, ilerleme ve bağlantı/kayıt durumu yapışkan üst alanda; birincil eylem güvenli alt çubukta kalır.
- Dokunma hedefleri en az 44×44 px; sayı alanlarında uygun mobil klavye (`inputmode=decimal|numeric`) açılır.
- Para birimi ve birim değerleri yalnız renkle anlatılmaz; metin ve sembol birlikte gösterilir.
- Sayfa yenilendiğinde sunucu taslağı ve varsa cihazdaki daha yeni taslak karşılaştırılır; sessiz “son yazan kazanır” yoktur.

Ortak sistem anahtarları bütün düzenlenebilir ekranlarda kullanılır:

`portal.system.autosaving`, `portal.system.saved_at`, `portal.system.save_failed`, `portal.system.offline`, `portal.system.offline_queued`, `portal.system.reconnected`, `portal.system.syncing`, `portal.system.sync_complete`, `portal.system.conflict`.

## 3. Ekran 1 — Karşılama ve tur özeti

### Amaç

Firma doğru anahtarı girdikten sonra hangi alıcı, liste ve teklif turunu açtığını teyit eder; ticari/gizli kullanım ve doldurma beklentisini görür.

### İçerik ve eylemler

- Başlık, alıcı firma, liste adı, ürün sayısı, teklif son tarihi, Incoterm+teslim yeri ve tur numarası.
- DDP Türkiye KDV dahil, MOQ, termin ve koli bilgilerinin beklendiği kısa açıklama.
- Kaynak alanların salt okunur, listenin ticari ve gizli olduğu uyarısı.
- Dil değiştirme yardımı.
- “Devam et” ile liste görünümüne geçiş; geri çıkma yalnız güvenli kapatma uyarısıyla.

### Bağlanan 7B anahtarları

`portal.welcome.title`, `portal.welcome.subtitle`, `portal.welcome.company`, `portal.welcome.list`, `portal.instruction.title`, `portal.instruction.summary`, `portal.instruction.source_readonly`, `portal.instruction.confidential`, `portal.instruction.language_help`, `portal.action.continue`, `portal.action.cancel`.

### Üç dilli davranış

Dil değişince bütün kabuk ve dipnotlar anında değişir. Liste/ürün adında seçili dil karşılığı yoksa kaynak ZH değeri `Kaynak / Source / 原文` etiketiyle gösterilir; iki dil aynı hücrede karıştırılmaz.

### Kayıt ve çevrimdışı

Bu ekranda iş alanı değişmez; yalnız dil tercihi ve onaylanan yönerge zamanı kaydedilebilir. Çevrimdışı ilk açılış desteklenmez; daha önce doğrulanmış aktif oturum ve yerel RFQ snapshot varsa salt okunur önbellek gösterilebilir, yeni doğrulama yapılamaz.

### Hatalar

- Tur iptal/sona ermişse içerik açılmaz; destek iletişimi ve referans gösterilir.
- Liste snapshot'ı eksikse “yeniden dene” ve güvenli referans verilir; ham hata/SQL ayrıntısı gösterilmez.

## 4. Ekran 2 — Liste görünümü ve ilerleme

### Amaç

Firma 25 ürün gibi bir listeyi hızlı tarar, tamamlanan/eksik satırları görür ve ilgili satır formuna gider.

### Mobil yerleşim

Her ürün bir karttır: ürün kodu, küçük görsel, ad, talep varyantı, talep miktarı, cevap durumu ve eksik alan özeti. Yapışkan filtreler: `Tümü / Yanıtlanmayan / Tamamlanan / Hatalı`. Arama ürün kodu ve adında çalışır. Bu filtre metinleri 7B'de yoktur ve yeni öneri listesindedir.

### Bağlanan 7B anahtarları

`portal.progress.completed`, `portal.progress.answered`, `portal.progress.remaining`, `portal.progress.current`, `portal.progress.required`, `portal.field.source_product`, `portal.field.source_variation`, `portal.field.requested_quantity`, `portal.field.response_status`, `portal.status.unanswered`, `portal.status.found`, `portal.status.not_found`, `portal.status.alternative`, `portal.status.needs_information`, `portal.status.complete`, `portal.action.open_source`, `portal.action.save_draft`, `portal.action.submit_partial`, `portal.action.submit_quote`, `portal.validation.remaining_rows`.

### Üç dilli davranış

Kart başlıkları seçili dilde bütündür. Firma girdiği serbest metni dil değişince otomatik çevrilmiş sanmaz; metin aynen korunur ve “Firma girişi” olarak işaretlenir. Sayısal fiyat/birim değerleri dil formatında gösterilebilir ancak kayıtta noktalı makine ondalığına normalize edilir.

### Otomatik kayıt ve çevrimdışı

- Kart durum değişikliği 600–1000 ms debounce ile taslak kayda girer.
- Başarılı kayıt zamanı üst kabukta görünür.
- Çevrimdışı değişiklikler satır+alan+yerel sıra numarasıyla kuyruğa alınır; kullanıcı kapatsa da geri açılışta bekleyen sayı görünür.
- Çevrimdışı iken “Kısmi gönder” ve “Teklifi gönder” çalışmaz; yalnız taslak korunur.

### Hatalar

Bir kartın yüklenememesi bütün listeyi kör etmez; kart hata durumu ve yeniden dene sunar. RFQ snapshot sürümü değişmişse listeye yazma kapatılır ve sürüm karşılaştırma ekranı açılır.

## 5. Ekran 3 — Satır yanıt formu

### Amaç

Firma tek ürün/varyant için bulunabilirlik, KDV dahil DDP fiyat, kademeler, MOQ, termin, koli/CBM/ağırlık, ambalaj, not ve alternatifi eksiksiz girer.

### Bölüm sırası

1. **Salt okunur talep:** Ürün, görsel, kaynak link, varyant, talep miktarı, alıcı notu.
2. **Yanıt durumu:** Bulundu / Bulunamadı / Alternatif var.
3. **Fiyat:** KDV dahil DDP birim fiyat + para birimi + Türkiye KDV dahil onayı.
4. **Kademeli fiyat:** min adet, isteğe bağlı max adet, birim fiyat; ekle/sil/sırala.
5. **MOQ ve termin:** MOQ+birim, başlangıç, süre, takvim/iş günü/hafta.
6. **Koli ve ambalaj:** koli içi, L×W×H cm, CBM, brüt/net kg, ambalaj.
7. **Not/alternatif:** firma notu; alternatif link ve fark açıklaması.
8. **Satır sohbeti:** sekiz hazır cevap ve serbest iletişim bağlantısı.

### Duruma bağlı alanlar

| Durum | Açık ve zorunlu | Kapanan/temizlenmesi teyit edilen |
|---|---|---|
| Bulundu | Fiyat, para birimi, KDV onayı, MOQ, termin başlangıç+süre | Alternatif alanları isteğe bağlı kapalı |
| Bulunamadı | Kısa firma notu | Fiyat, kademe, MOQ, termin alanları çelişki varsa temizleme teyidi ister |
| Alternatif var | Alternatif link veya açıklama + fiyat, para birimi, KDV onayı, MOQ, termin | Yok; kaynak satır salt okunur kalır |

Yüksek MOQ hata değildir. Talep miktarı MOQ'nun altındaysa sarı uyarı gösterilir, firma gerçek MOQ'yu kaydedebilir.

### Bağlanan 7B alan/eylem anahtarları

`portal.field.source_product`, `portal.field.source_variation`, `portal.field.requested_quantity`, `portal.field.response_status`, `portal.field.ddp_unit_price_vat_included`, `portal.field.currency`, `portal.field.tier_pricing`, `portal.field.tier_min_quantity`, `portal.field.tier_max_quantity`, `portal.field.tier_unit_price`, `portal.field.moq`, `portal.field.lead_time`, `portal.field.lead_time_unit`, `portal.field.lead_time_start`, `portal.field.units_per_carton`, `portal.field.carton_dimensions`, `portal.field.carton_length_cm`, `portal.field.carton_width_cm`, `portal.field.carton_height_cm`, `portal.field.carton_cbm`, `portal.field.carton_gross_weight_kg`, `portal.field.carton_net_weight_kg`, `portal.field.packaging`, `portal.field.note`, `portal.field.alternative_proposal`, `portal.field.alternative_link`, `portal.field.alternative_details`, `portal.action.add_tier`, `portal.action.previous_product`, `portal.action.next_product`, `portal.action.back`, `portal.action.open_source`, `portal.action.save_draft`.

### Bağlanan 7B doğrulama anahtarları

`portal.validation.status_required`, `portal.validation.found_price_required`, `portal.validation.found_moq_required`, `portal.validation.found_lead_time_required`, `portal.validation.ddp_vat_confirmation_required`, `portal.validation.currency_required`, `portal.validation.positive_number`, `portal.validation.tier_incomplete`, `portal.validation.tier_order`, `portal.validation.tier_overlap`, `portal.validation.carton_dimensions_together`, `portal.validation.alternative_details_required`, `portal.validation.not_found_note_required`, `portal.validation.quantity_below_moq`.

### Satır sohbeti hazır cevapları

`portal.chat.checking_supplier`, `portal.chat.price_pending`, `portal.chat.need_specs`, `portal.chat.unavailable`, `portal.chat.alternative_proposed`, `portal.chat.moq_confirmation`, `portal.chat.lead_time_confirmation`, `portal.chat.carton_pending`.

### Üç dilli davranış

Alan etiketi/yardım/hata bütünüyle seçili dilde kalır. Para simgesi tek başına kullanılmaz; ISO kodu gösterilir. ZH'de `阶梯价`, `起订量`, `交期`, `含土耳其增值税` ticari terimleri korunur. Firma notu otomatik çevrilmez.

### Kayıt ve hata davranışı

- Alan kaybında yalnız ilgili alanın eski değeri geri gösterilir; bütün satır sıfırlanmaz.
- Kademeli fiyat satırı tamamlanmadan yeni kademe eklenebilir fakat satır tamamlandı sayılmaz.
- L/W/H birlikte girilir; CBM otomatik hesap önerisi beyan CBM'nin üzerine sessizce yazılmaz.
- Brüt < net, termin >365, geçersiz URL ve CBM farkı gibi 7B'de olmayan hatalar yeni anahtar önerilerindedir.

## 6. Ekran 4 — Kısmi gönderim

### Amaç

Firma tamamlanan 18/25 satırı Ürün Sahibine iletir; 7 eksik satır taslakta kalır.

### Akış

1. Tamamlanmış satırlar ve kalan sayı hesaplanır.
2. Hatalı satır gönderilebilir sayıya katılmaz.
3. Firma “18 satır gönderilecek, 7 satır taslak kalacak” özetini onaylar.
4. Tek idempotency anahtarıyla kısmi snapshot oluşturulur.
5. Başarı sonrası liste görünümünde gönderilmiş sürüm ve kalanlar açık gösterilir.

### Bağlanan 7B anahtarları

`portal.partial.title`, `portal.partial.description`, `portal.partial.ready_count`, `portal.partial.remaining_count`, `portal.partial.button`, `portal.partial.confirm`, `portal.partial.success`, `portal.action.submit_partial`, `portal.progress.completed`, `portal.validation.remaining_rows`.

### Dil, kayıt, çevrimdışı ve hata

Onay cümlesi seçili dilde tek parça olur; yer tutucular aynı sayılara bağlanır. Kısmi gönderim çevrimdışı kuyruğa “gönderilmiş” diye yazılmaz; çevrimiçi bağlantı zorunludur. Yanıt kaybolursa aynı idempotency anahtarıyla sonuç sorgulanır. 18 satırdan biri sunucuda çakışmışsa 17'si sessiz uygulanmaz; önizleme yeniden açılır ve kapsam açıkça gösterilir.

## 7. Ekran 5 — Nihai gönderim onayı

### Amaç

Firma tüm satırları kontrol eder, fiyat geçerliliğini ve Türkiye KDV dahil DDP niteliğini onaylar; turu salt okunur hale getirir.

### Bağlanan 7B anahtarları

`portal.submit.title`, `portal.submit.confirm_intro`, `portal.submit.validity_checkbox`, `portal.submit.ddp_checkbox`, `portal.submit.completeness_checkbox`, `portal.submit.button`, `portal.submit.sending`, `portal.submit.blocked_incomplete`, `portal.action.submit_quote`, `portal.action.back`, `portal.validation.remaining_rows`.

### Kapı

- 25/25 satır tamamlanmış ve geçerli olmalı.
- Fiyatlı her satırda para birimi, KDV onayı, MOQ ve termin tamam olmalı.
- Üç onay kutusu işaretli olmalı.
- Çevrimdışı değişiklik sayısı sıfır ve sunucu sürümü güncel olmalı.
- Gönderim çift tıklamada tek snapshot üretmeli.

### Dil ve hata

Firma onay ekranında dili değiştirebilir; onayların iş anlamı değişmez ve kayıtta gösterilen metin sürümü tutulur. Gönderim başarısızsa form düzenlenebilir taslak olarak kalır; “gönderildi” başarı ekranı yalnız sunucu teyidiyle açılır.

## 8. Ekran 6 — Başarı ve salt-okunur teklif

### Amaç

Firma teslimin alındığını doğrular; referans ve zaman damgasını görür; aynı turu değiştiremez.

### Bağlanan 7B anahtarları

`portal.success.title`, `portal.success.body`, `portal.success.reference`, `portal.success.sent_at`, `portal.success.read_only`, `portal.success.revision_notice`, `portal.success.close`.

### Davranış

- Gönderilen bütün RFQ ve cevap alanları salt okunur kartlar halinde gösterilir.
- Fiyat geçerlilik son tarihi ve tur numarası görünür.
- “Revizyon istenirse yeni tur açılır” açıklaması bulunur.
- Çevrimdışı yerel kuyruk temizlenir; salt okunur snapshot kısa süreli önbelleğe alınabilir.
- Sayfayı kapatmak güvenlidir; tekrar açılış aynı salt okunur veriyi verir, anahtar geçerliyse.

Hata halinde gönderilmiş snapshot asla boş form gibi gösterilmez. İçerik geçici yüklenemiyorsa referans, gönderim zamanı ve “yeniden dene” eylemi görünür.

## 9. Ekran 7 — Revizyon turu açıldı

Önceki tur salt okunur kalır; yeni tur `R2`, `R3` olarak açılır. Firma değişen satırları ve Ürün Sahibinin revizyon notunu görür. Önceki cevaplar yalnız başlangıç taslağı olarak kopyalanmışsa “önceki turdan kopyalandı” rozeti taşır; yeniden onaylanmadan gönderilmiş sayılmaz.

Bu ekran mevcut 7B'deki `portal.success.revision_notice`, karşılama, ilerleme, alan ve eylem anahtarlarını yeniden kullanır. Revizyona özgü başlık, fark ve “önceki turdan kopyalandı” metinleri aşağıdaki yeni önerilerde yer alır.

## 10. 7B bağlama manifesti

Bu şartname 111 anahtarın tamamını aşağıdaki ekran ailelerine bağlar:

| 7B öneki | Sayı | Birincil ekran |
|---|---:|---|
| `portal.welcome.*` | 4 | Karşılama |
| `portal.instruction.*` | 5 | Karşılama + ortak kabuk |
| `portal.action.*` | 10 | Liste, satır, kısmi ve nihai gönderim |
| `portal.progress.*` | 5 | Liste + mobil üst kabuk |
| `portal.field.*` | 27 | Satır formu |
| `portal.status.*` | 6 | Liste kartı + satır durumu |
| `portal.validation.*` | 15 | Satır formu + gönderim kapıları |
| `portal.system.*` | 9 | Bütün düzenlenebilir ekranlar |
| `portal.partial.*` | 7 | Kısmi gönderim |
| `portal.submit.*` | 8 | Nihai gönderim |
| `portal.chat.*` | 8 | Satır sohbeti |
| `portal.success.*` | 7 | Başarı/salt-okunur |
| **Toplam** | **111** | **111/111 bağlı** |

## 11. YENİ ÖNERİ — 7B'de bulunmayan anahtarlar

Bu görev `portal-metinleri.json` dosyasını değiştirmez. 7B'de karşılığı bulunmadığı için bağlayıcı 7B manifestine alınmayan altı doğrudan portal bağı `portal_anahtar_onerisi` alanında tutulur:

| `portal_anahtar_onerisi` | Kullanım |
|---|---|
| `portal.field.product_code` | Salt-okunur ürün kodu etiketi |
| `portal.field.buyer_note` | Firma notundan ayrı, salt-okunur alıcı satır notu |
| `portal.filter.all` | Liste filtresi: Tümü |
| `portal.filter.unanswered` | Liste filtresi: Yanıtlanmayan |
| `portal.filter.invalid` | Liste filtresi: Hatalı |
| `portal.action.clear_local_draft` | Bu cihazdaki taslağı sil |

İE#23 sırasında PM onayıyla değerlendirilecek diğer yeni öneriler:

| Önerilen anahtar | Kullanım |
|---|---|
| `portal.action.retry` | İnsan dilinde yeniden dene |
| `portal.system.local_draft_restored` | Cihazdaki taslak geri getirildi |
| `portal.system.offline_submit_blocked` | Çevrimdışı kısmi/nihai gönderim engeli |
| `portal.system.session_expired` | Hesapsız oturum süresi doldu |
| `portal.system.round_revoked` | Tur erişimi iptal edildi |
| `portal.conflict.compare` | Sürümleri karşılaştır |
| `portal.conflict.keep_server` | Sunucu sürümünü koru |
| `portal.conflict.keep_device` | Cihaz sürümünü yeni taslak olarak al |
| `portal.validation.lead_time_max` | Termin 365 günü aşıyor |
| `portal.validation.gross_below_net` | Brüt ağırlık netten küçük |
| `portal.validation.cbm_mismatch` | Ölçü ile beyan CBM farkı |
| `portal.validation.url_invalid` | Alternatif/kaynak URL geçersiz |
| `portal.revision.title` | Yeni teklif turu başlığı |
| `portal.revision.changed_rows` | Değişen satır sayısı |
| `portal.revision.copied_from_previous` | Önceki turdan kopyalanan değer rozeti |
| `portal.readonly.valid_until` | Salt-okunur teklifte geçerlilik sonu |

## 12. Erişilebilirlik ve güvenlik kabul notları

- Dil seçici ekran okuyucu adı taşır; seçili dil `aria-current` ile belirtilir.
- Hata özeti ilk hataya bağlantı verir; odak hata alanına taşınır fakat kullanıcının girdiği değer silinmez.
- SMS/WhatsApp anahtarı URL query, referrer, analytics, console veya hata paketine yazılmaz.
- Sayfada analitik/reklam/üçüncü taraf izleyici çalışmaz.
- Kaynak ürün yeni sekmede `noopener,noreferrer` ile açılır.
- Firma serbest metni HTML olarak işlenmez; görünür metin olarak kaçışlanır.
- Anahtar denemeleri hız sınırına tabidir; sabit hata metni hesap/tur varlığını sızdırmaz.
