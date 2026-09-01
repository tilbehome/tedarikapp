# Mikro Etkileşim Taksonomisi — K105 kaynak envanteri

> Durum: PM seçimine girdi olacak araştırma envanteri. Bu belge herhangi bir örüntüyü belirli tedarikapp ekranına atamaz; kategori, tür ve örüntü düzeyinde aday kapsamı tarif eder.

## Okuma anahtarı

Hiyerarşi **Katman 1 kategori → Katman 2 tür → Katman 3 somut örüntü** biçimindedir. Her örüntüde kabulde istenen yedi bilgi alanı eksiksizdir. Öğe tipi sözlüğü yalnız `satır`, `alan`, `tablo`, `sayfa`, `belge`; tetik sözlüğü hover, tık, klavye, sürükle ve otomatik eylemlerden oluşur.

B2B önceliği JSON kataloğundadır: 1 yoğun tablo iş uygulamasında vazgeçilmez, 2 yüksek değerli, 3 bağlama bağlı. Öncelik, ekran ataması veya uygulama kararı değildir.

## Taksonomi

## KAT-01 — Kayıt ve satır eylemleri

Tekil kaydı açma, değiştirme ve bağlamsal olarak yönetme davranışları.

### KAT-01-T01 — Erişim ve düzenleme

#### ME-001 — Kaydı aç (Open record)

- **Tanım:** Satırın birincil etkinleştirmesi kaydın ayrıntı bağlamını açar.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

#### ME-002 — Hızlı önizleme (Quick preview)

- **Tanım:** Kayıttan ayrılmadan sınırlı ayrıntıyı geçici bir panelde gösterir.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

#### ME-003 — Kaydı düzenle (Edit record)

- **Tanım:** Seçili kaydı yetkili düzenleme kipine geçirir.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

#### ME-004 — Kaydı çoğalt (Duplicate record)

- **Tanım:** Seçili kaydın açıkça kopya olarak işaretlenen yeni bir örneğini oluşturur.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

#### ME-005 — Kaydı taşı (Move record)

- **Tanım:** Kaydı geçerli kapsamdaki başka bir liste veya gruba taşır.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

#### ME-006 — Kaydı arşivle (Archive record)

- **Tanım:** Aktif kaydı silmeden günlük görünümden arşive kaldırır.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

#### ME-007 — Arşivden çıkar (Unarchive record)

- **Tanım:** Arşivlenmiş kaydı önceki ya da seçilen aktif bağlama geri getirir.
- **Öğe tipi:** satır, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Hedef kayıt vurgulanır; açılan bağlam, başlık ve durum değişimi görünür olur.
- **Yaygın hata:** Tıklama hedefinin belirsiz olması veya aynı eylemin farklı yüzeylerde başka sonuç vermesi.
- **Emsal:** Linear, Airtable

### KAT-01-T02 — Düzenleme ve işaretleme

#### ME-008 — Sabitle (Pin)

- **Tanım:** Sık kullanılan kaydı tanımlı sabit bölgeye tutturur.
- **Öğe tipi:** satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** İşaret, etiket ya da atama satır üzerinde ve ilgili sayaçta hemen görünür.
- **Yaygın hata:** Görsel işaretin anlamını açıklamamak veya değişikliği kaydettiğine dair geri bildirim vermemek.
- **Emsal:** Linear, Gmail

#### ME-009 — Etiketle (Tag)

- **Tanım:** Kayda bir veya daha çok sınıflandırma etiketi ekler.
- **Öğe tipi:** satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** İşaret, etiket ya da atama satır üzerinde ve ilgili sayaçta hemen görünür.
- **Yaygın hata:** Görsel işaretin anlamını açıklamamak veya değişikliği kaydettiğine dair geri bildirim vermemek.
- **Emsal:** Linear, Gmail

#### ME-010 — Not ekle (Add note)

- **Tanım:** Kayda bağlamsal kısa bir not iliştirir.
- **Öğe tipi:** satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** İşaret, etiket ya da atama satır üzerinde ve ilgili sayaçta hemen görünür.
- **Yaygın hata:** Görsel işaretin anlamını açıklamamak veya değişikliği kaydettiğine dair geri bildirim vermemek.
- **Emsal:** Linear, Gmail

#### ME-011 — Sahip ata (Assign owner)

- **Tanım:** Kaydın sorumlusunu seçim yoluyla değiştirir.
- **Öğe tipi:** satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** İşaret, etiket ya da atama satır üzerinde ve ilgili sayaçta hemen görünür.
- **Yaygın hata:** Görsel işaretin anlamını açıklamamak veya değişikliği kaydettiğine dair geri bildirim vermemek.
- **Emsal:** Linear, Gmail

#### ME-012 — Okundu/okunmadı (Mark read/unread)

- **Tanım:** Kaydın kişisel okunma durumunu iki yönlü değiştirir.
- **Öğe tipi:** satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** İşaret, etiket ya da atama satır üzerinde ve ilgili sayaçta hemen görünür.
- **Yaygın hata:** Görsel işaretin anlamını açıklamamak veya değişikliği kaydettiğine dair geri bildirim vermemek.
- **Emsal:** Linear, Gmail

### KAT-01-T03 — Bağlamsal eylem keşfi

#### ME-013 — Hover eylemleri (Hover actions)

- **Tanım:** Sık satır eylemlerini fareyle üzerine gelindiğinde ve klavye odağında görünür kılar.
- **Öğe tipi:** satır, tablo
- **Tetik:** hover / tık / klavye
- **Beklenen geri bildirim:** Satıra ait eylemler odak veya hover sırasında görünür; menü odağı yönetilir.
- **Yaygın hata:** Eylemleri yalnız hover ile erişilebilir kılmak veya menü kapanınca odağı kaybetmek.
- **Emsal:** Gmail, GitHub

#### ME-014 — Taşma menüsü (Overflow menu)

- **Tanım:** İkincil satır eylemlerini üç nokta altında toplar.
- **Öğe tipi:** satır, tablo
- **Tetik:** hover / tık / klavye
- **Beklenen geri bildirim:** Satıra ait eylemler odak veya hover sırasında görünür; menü odağı yönetilir.
- **Yaygın hata:** Eylemleri yalnız hover ile erişilebilir kılmak veya menü kapanınca odağı kaybetmek.
- **Emsal:** Gmail, GitHub

#### ME-015 — Sağ tık menüsü (Context menu)

- **Tanım:** İşaretçi konumunda kayda özgü bağlamsal eylemleri açar.
- **Öğe tipi:** satır, tablo
- **Tetik:** hover / tık / klavye
- **Beklenen geri bildirim:** Satıra ait eylemler odak veya hover sırasında görünür; menü odağı yönetilir.
- **Yaygın hata:** Eylemleri yalnız hover ile erişilebilir kılmak veya menü kapanınca odağı kaybetmek.
- **Emsal:** Gmail, GitHub

#### ME-016 — Kayıt bağlantısını kopyala (Copy record link)

- **Tanım:** Kaydın kalıcı derin bağlantısını panoya kopyalar.
- **Öğe tipi:** satır, tablo
- **Tetik:** hover / tık / klavye
- **Beklenen geri bildirim:** Satıra ait eylemler odak veya hover sırasında görünür; menü odağı yönetilir.
- **Yaygın hata:** Eylemleri yalnız hover ile erişilebilir kılmak veya menü kapanınca odağı kaybetmek.
- **Emsal:** Gmail, GitHub

#### ME-017 — Satırı genişlet (Expand row)

- **Tanım:** Ek bilgiyi satırın altında bağlamı bozmadan açar.
- **Öğe tipi:** satır, tablo
- **Tetik:** hover / tık / klavye
- **Beklenen geri bildirim:** Satıra ait eylemler odak veya hover sırasında görünür; menü odağı yönetilir.
- **Yaygın hata:** Eylemleri yalnız hover ile erişilebilir kılmak veya menü kapanınca odağı kaybetmek.
- **Emsal:** Gmail, GitHub

## KAT-02 — Alan düzeyi

Tek bir alanın görüntülenmesi, düzenlenmesi, doğrulanması ve kökeninin anlaşılması.

### KAT-02-T01 — Alan düzenleme ve geri dönüş

#### ME-018 — Satır içi düzenleme (Inline edit)

- **Tanım:** Alanı bulunduğu bağlamdan ayırmadan düzenlenebilir hale getirir.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-019 — Tıkla düzenle (Click to edit)

- **Tanım:** Salt okunur görünen alanı açık bir tıklamayla düzenleme kipine geçirir.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-020 — Klavye ile düzenle (Keyboard edit)

- **Tanım:** Odaktaki alanı fare gerektirmeden düzenlemeye açar.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-021 — Düzenlemeyi onayla (Commit edit)

- **Tanım:** Geçerli alan değişikliğini açıkça kaydeder ve görüntü kipine döner.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-022 — Düzenlemeyi iptal et (Cancel edit)

- **Tanım:** Alanı önceki değerine döndürerek düzenleme kipinden çıkar.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-023 — Alanı temizle (Clear field)

- **Tanım:** Alan değerini tanımlı boş duruma getirir.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-024 — Orijinale döndür (Revert to original)

- **Tanım:** Değiştirilmiş alanı kaynağından gelen önceki değere geri alır.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

#### ME-025 — Alanı kopyala (Copy field)

- **Tanım:** Gösterilen alan değerini türüne uygun panoya kopyalar.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Alan düzenleme çerçevesi, geçerli değer ve kaydet/iptal sonucu açıkça görünür.
- **Yaygın hata:** Görüntü ve düzenleme kiplerini ayırt etmemek ya da Escape ile değişikliği iptal edememek.
- **Emsal:** Airtable, Atlassian

### KAT-02-T02 — Girdi desteği ve doğrulama

#### ME-026 — Anlık doğrulama (Inline validation)

- **Tanım:** Kesinleşen bir kural ihlalini alan bağlamında bildirir.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-027 — Odaktan çıkınca doğrulama (Validate on blur)

- **Tanım:** Yazmayı kesmeden, alan odağı ayrıldığında değeri doğrular.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-028 — Otomatik tamamlama (Autocomplete)

- **Tanım:** Girilen öneke göre seçilebilir değer önerileri sunar.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-029 — Tür önermesi (Typeahead)

- **Tanım:** Liste içinde yazılan karakterlerle eşleşen seçeneğe ilerler.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-030 — Otomatik biçimleme (Auto-format)

- **Tanım:** Kullanıcı girdisini anlamını koruyarak gösterim biçimine dönüştürür.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-031 — Birim maskesi (Unit mask)

- **Tanım:** Sayısal değeri birim işaretiyle birlikte tutarlı biçimde gösterir.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-032 — Para maskesi (Currency mask)

- **Tanım:** Tutarı para birimi ve yerel sayı biçimiyle ayırt edilebilir sunar.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-033 — Tarih seçici (Date picker)

- **Tanım:** Tarih girişini yazma ve takvimden seçme yollarıyla destekler.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

#### ME-034 — Çoklu değer belirteçleri (Tokenized input)

- **Tanım:** Birden çok seçimi ayrı, silinebilir belirteçler halinde gösterir.
- **Öğe tipi:** alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Kabul edilen biçim, öneri ve hata alanın yanında gecikmeden ve metinle belirtilir.
- **Yaygın hata:** Kullanıcı yazarken gereksiz hata üretmek, girdiyi sessizce değiştirmek veya yalnız renkle hata göstermek.
- **Emsal:** Notion, Airtable

### KAT-02-T03 — Köken, boşluk ve salt okunur durum

#### ME-035 — Boş alan göstergesi (Empty field state)

- **Tanım:** Değer bulunmamasını sıfır ya da çizgiyle karıştırmadan açıkça belirtir.
- **Öğe tipi:** alan, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Boşluk, hesaplanmışlık, miras ve kısaltma durumu metinsel bir işaretle anlaşılır.
- **Yaygın hata:** Boş değeri sıfır gibi göstermek veya hesaplanmış alanı düzenlenebilir izlenimiyle sunmak.
- **Emsal:** Stripe, Airtable

#### ME-036 — Orijinali göster (Show original)

- **Tanım:** Dönüştürülmüş değerin kaynak halini isteğe bağlı olarak gösterir.
- **Öğe tipi:** alan, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Boşluk, hesaplanmışlık, miras ve kısaltma durumu metinsel bir işaretle anlaşılır.
- **Yaygın hata:** Boş değeri sıfır gibi göstermek veya hesaplanmış alanı düzenlenebilir izlenimiyle sunmak.
- **Emsal:** Stripe, Airtable

#### ME-037 — Alan geçmişi (Field history)

- **Tanım:** Alan değerinin zaman içindeki değişimlerini ve yapanı gösterir.
- **Öğe tipi:** alan, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Boşluk, hesaplanmışlık, miras ve kısaltma durumu metinsel bir işaretle anlaşılır.
- **Yaygın hata:** Boş değeri sıfır gibi göstermek veya hesaplanmış alanı düzenlenebilir izlenimiyle sunmak.
- **Emsal:** Stripe, Airtable

#### ME-038 — Hesaplanmış alan işareti (Computed field indicator)

- **Tanım:** Değerin kullanıcı girişi değil hesap sonucu olduğunu belirtir.
- **Öğe tipi:** alan, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Boşluk, hesaplanmışlık, miras ve kısaltma durumu metinsel bir işaretle anlaşılır.
- **Yaygın hata:** Boş değeri sıfır gibi göstermek veya hesaplanmış alanı düzenlenebilir izlenimiyle sunmak.
- **Emsal:** Stripe, Airtable

#### ME-039 — Salt okunur işareti (Read-only indicator)

- **Tanım:** Alan değiştirilemiyorsa bunu odaklanabilir bir açıklamayla bildirir.
- **Öğe tipi:** alan, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Boşluk, hesaplanmışlık, miras ve kısaltma durumu metinsel bir işaretle anlaşılır.
- **Yaygın hata:** Boş değeri sıfır gibi göstermek veya hesaplanmış alanı düzenlenebilir izlenimiyle sunmak.
- **Emsal:** Stripe, Airtable

#### ME-040 — Kısaltılmış değeri aç (Reveal truncated value)

- **Tanım:** Kesilmiş alan içeriğinin tamamını hover, odak veya tıklamayla erişilebilir kılar.
- **Öğe tipi:** alan, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Boşluk, hesaplanmışlık, miras ve kısaltma durumu metinsel bir işaretle anlaşılır.
- **Yaygın hata:** Boş değeri sıfır gibi göstermek veya hesaplanmış alanı düzenlenebilir izlenimiyle sunmak.
- **Emsal:** Stripe, Airtable

## KAT-03 — Tablo ve liste

Yoğun veri yüzeylerinde düzenleme, daraltma, karşılaştırma ve gezinme davranışları.

### KAT-03-T01 — Sıralama ve filtreleme

#### ME-041 — Tek sütun sıralama (Single-column sort)

- **Tanım:** Bir sütunu artan, azalan ve sırasız durumlar arasında döndürür.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-042 — Çoklu sıralama (Multi-sort)

- **Tanım:** Birden çok sütuna açık öncelik sırasıyla sıralama uygular.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-043 — Sıralamayı temizle (Clear sort)

- **Tanım:** Etkin sıralamaları başlangıç düzenine geri döndürür.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-044 — VE filtresi (AND filter)

- **Tanım:** Tüm koşulların aynı anda sağlanmasını gerektiren filtre grubu kurar.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-045 — VEYA filtresi (OR filter)

- **Tanım:** Koşullardan en az birini sağlayan kayıtları gösteren filtre grubu kurar.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-046 — Hızlı filtre çipi (Quick filter chip)

- **Tanım:** Sık kullanılan bir filtreyi tek dokunuşla açıp kapatan görünür çip sunar.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-047 — Filtre çipini kaldır (Remove filter chip)

- **Tanım:** Tek bir etkin filtreyi diğerlerini bozmadan kaldırır.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

#### ME-048 — Tüm filtreleri temizle (Clear all filters)

- **Tanım:** Bütün etkin filtreleri tek, geri bildirilen eylemle sıfırlar.
- **Öğe tipi:** tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin sıralama yönü, önceliği ve filtre ifadesi başlıkta ya da çiplerde kalıcı görünür.
- **Yaygın hata:** Etkin kuralı gizlemek, sıfırlama yolunu vermemek veya boş sonucu veri yokmuş gibi göstermek.
- **Emsal:** Airtable, Carbon

### KAT-03-T02 — Gruplama ve özet

#### ME-049 — Tek alanla gruplama (Group by field)

- **Tanım:** Satırları seçilen alanın değerlerine göre görsel kümelere ayırır.
- **Öğe tipi:** tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Grup hiyerarşisi, açılma durumu ve hesaplanan özetin kapsamı görünür kalır.
- **Yaygın hata:** Alt toplamı tüm veri toplamı gibi göstermek veya grup aç/kapa durumunda odağı kaybetmek.
- **Emsal:** Airtable, Shopify

#### ME-050 — İç içe gruplama (Nested grouping)

- **Tanım:** Birden çok grup anahtarını hiyerarşik sırayla uygular.
- **Öğe tipi:** tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Grup hiyerarşisi, açılma durumu ve hesaplanan özetin kapsamı görünür kalır.
- **Yaygın hata:** Alt toplamı tüm veri toplamı gibi göstermek veya grup aç/kapa durumunda odağı kaybetmek.
- **Emsal:** Airtable, Shopify

#### ME-051 — Grubu aç/kapat (Expand/collapse group)

- **Tanım:** Bir grubun satırlarını özet korunarak görünür ya da gizli yapar.
- **Öğe tipi:** tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Grup hiyerarşisi, açılma durumu ve hesaplanan özetin kapsamı görünür kalır.
- **Yaygın hata:** Alt toplamı tüm veri toplamı gibi göstermek veya grup aç/kapa durumunda odağı kaybetmek.
- **Emsal:** Airtable, Shopify

#### ME-052 — Grup alt toplamı (Group subtotal)

- **Tanım:** Yalnız ilgili grubun sayısal özetini grup sınırında gösterir.
- **Öğe tipi:** tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Grup hiyerarşisi, açılma durumu ve hesaplanan özetin kapsamı görünür kalır.
- **Yaygın hata:** Alt toplamı tüm veri toplamı gibi göstermek veya grup aç/kapa durumunda odağı kaybetmek.
- **Emsal:** Airtable, Shopify

#### ME-053 — Özet satırı (Summary row)

- **Tanım:** Görünür veri kapsamının toplam, adet ya da ortalamasını sabit bir satırda sunar.
- **Öğe tipi:** tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Grup hiyerarşisi, açılma durumu ve hesaplanan özetin kapsamı görünür kalır.
- **Yaygın hata:** Alt toplamı tüm veri toplamı gibi göstermek veya grup aç/kapa durumunda odağı kaybetmek.
- **Emsal:** Airtable, Shopify

### KAT-03-T03 — Sütun ve yoğunluk yönetimi

#### ME-054 — Sütun göster/gizle (Show/hide columns)

- **Tanım:** Seçilen sütunların görünürlüğünü veri kaybı olmadan değiştirir.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

#### ME-055 — Sütun yeniden sırala (Reorder columns)

- **Tanım:** Sütunları sürükleme veya erişilebilir alternatifle yeni konuma taşır.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

#### ME-056 — Sütun genişlet/daralt (Resize column)

- **Tanım:** Sütun genişliğini içeriğe ve kullanıcı tercihine göre ayarlar.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

#### ME-057 — Sütunu dondur (Pin column)

- **Tanım:** Referans sütununu yatay kaydırmada görünür tutar.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

#### ME-058 — İçeriğe sığdır (Auto-fit column)

- **Tanım:** Sütun genişliğini görünen içerik için uygun ölçüye otomatik getirir.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

#### ME-059 — Sütun bul (Find column)

- **Tanım:** Geniş tablolarda adla sütun arayıp görünür konuma getirir.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

#### ME-060 — Yoğunluk değiştir (Density control)

- **Tanım:** Satır yüksekliği ve boşluğu bilgi miktarına uygun görünümler arasında değiştirir.
- **Öğe tipi:** tablo
- **Tetik:** sürükle / tık / klavye
- **Beklenen geri bildirim:** Sütun konumu, genişliği, görünürlüğü ve yoğunluk değişimi tablonun yapısında anında görünür.
- **Yaygın hata:** Sütun ayarlarını sessizce kaybetmek, sabit sütunu ayırt etmemek veya yatay konumu sıçratmak.
- **Emsal:** Airtable, AG Grid

### KAT-03-T04 — Büyük veri ve doğrudan işleme

#### ME-061 — Sayfalama (Pagination)

- **Tanım:** Büyük veri kümesini toplam ve mevcut aralık bilgisiyle sayfalara böler.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

#### ME-062 — Sonsuz kaydırma (Infinite scroll)

- **Tanım:** Sonraki kayıt kümesini kaydırma eşiğinde kesintisiz yükler.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

#### ME-063 — Daha fazla yükle (Load more)

- **Tanım:** Kullanıcının açık komutuyla sonraki kayıt grubunu mevcut listenin sonuna ekler.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

#### ME-064 — Yapışkan başlık (Sticky header)

- **Tanım:** Sütun adlarını dikey kaydırma sırasında görünür tutar.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

#### ME-065 — Satır genişletme (Row expansion)

- **Tanım:** İkincil ayrıntıyı satır altında isteğe bağlı açar.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

#### ME-066 — Doldurma tutamacı (Fill handle)

- **Tanım:** Seçili hücre değerini kontrollü sürüklemeyle komşu hücrelere çoğaltır.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

#### ME-067 — Yapıştırarak doldur (Paste to fill)

- **Tanım:** Sekmeli pano verisini seçilen başlangıç hücresinden itibaren tabloya dağıtır.
- **Öğe tipi:** tablo, satır, alan
- **Tetik:** tık / klavye / sürükle / otomatik
- **Beklenen geri bildirim:** Yüklenen kapsam, sabit başlık ve yapılan hücre çoğaltması seçili alanla birlikte görünür.
- **Yaygın hata:** Kullanıcının veri kapsamını anlamadan tümünü seçmesine izin vermek veya kaydırmada başlık bağlamını kaybetmek.
- **Emsal:** Shopify, AG Grid

## KAT-04 — Seçim ve toplu işlem

Birden çok nesneyi güvenle seçme, kapsamı anlama ve ortak eylem uygulama.

### KAT-04-T01 — Seçim kapsamı

#### ME-068 — Tek satır seç (Single select)

- **Tanım:** Bir satırı işlem kapsamına alır.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

#### ME-069 — Aralık seç (Range selection)

- **Tanım:** Başlangıç ve bitiş arasındaki kesintisiz satır aralığını seçer.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

#### ME-070 — Ayrık seçim (Discontiguous selection)

- **Tanım:** Birbirine komşu olmayan satırları mevcut seçime ekler veya çıkarır.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

#### ME-071 — Sayfadakilerin tümünü seç (Select page)

- **Tanım:** Yalnız mevcut sayfada görünen satırların tümünü seçer.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

#### ME-072 — Filtre sonucunun tümünü seç (Select all matching)

- **Tanım:** Mevcut sayfayı aşan bütün filtre sonucunu açık ikinci onayla seçer.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

#### ME-073 — Kısmi seçim göstergesi (Indeterminate selection)

- **Tanım:** Alt küme seçildiğinde üst seçim kutusunu belirsiz durumla gösterir.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

#### ME-074 — Seçim sayacı (Selection count)

- **Tanım:** Toplu işlem kapsamındaki kayıt sayısını sürekli gösterir.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Seçilen satırlar, kısmi seçim ve kapsam sayacı görünür ve ekran okuyucuya duyurulur.
- **Yaygın hata:** Seçimin yalnız renkle belirtilmesi veya sayfa değişince sessizce kaybolması.
- **Emsal:** Linear, Gmail

### KAT-04-T02 — Toplu eylem

#### ME-075 — Toplu eylem çubuğu (Bulk action bar)

- **Tanım:** Seçim oluşunca yalnız ortak ve geçerli eylemleri görünür kılar.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Toplu eylem çubuğu kapsamı ve sonucu adetlerle bildirir; başarısız alt küme ayrılır.
- **Yaygın hata:** Tekil eylemleri toplu kipte etkin bırakmak veya kısmi başarısızlığı tam başarı gibi göstermek.
- **Emsal:** Shopify, ClickUp

#### ME-076 — Toplu düzenleme (Bulk edit)

- **Tanım:** Seçili kayıtların ortak alanlarını tek işlemde değiştirmeyi sağlar.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Toplu eylem çubuğu kapsamı ve sonucu adetlerle bildirir; başarısız alt küme ayrılır.
- **Yaygın hata:** Tekil eylemleri toplu kipte etkin bırakmak veya kısmi başarısızlığı tam başarı gibi göstermek.
- **Emsal:** Shopify, ClickUp

#### ME-077 — Seçimi temizle (Clear selection)

- **Tanım:** Toplu işlem kipinden bütün seçimleri kaldırarak çıkar.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Toplu eylem çubuğu kapsamı ve sonucu adetlerle bildirir; başarısız alt küme ayrılır.
- **Yaygın hata:** Tekil eylemleri toplu kipte etkin bırakmak veya kısmi başarısızlığı tam başarı gibi göstermek.
- **Emsal:** Shopify, ClickUp

#### ME-078 — Seçimi kaydet (Save selection)

- **Tanım:** Tanımlı kayıt kümesini daha sonra yeniden kullanılabilecek adlandırılmış seçim olarak saklar.
- **Öğe tipi:** satır, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Toplu eylem çubuğu kapsamı ve sonucu adetlerle bildirir; başarısız alt küme ayrılır.
- **Yaygın hata:** Tekil eylemleri toplu kipte etkin bırakmak veya kısmi başarısızlığı tam başarı gibi göstermek.
- **Emsal:** Shopify, ClickUp

## KAT-05 — Gezinme ve komut

Yoğun iş akışında fareye bağımlı olmadan konum, komut ve bağlam yönetimi.

### KAT-05-T01 — Komut keşfi

#### ME-079 — Komut paleti (Command palette)

- **Tanım:** Eylem ve hedefleri tek aranabilir yüzeyde toplar.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** klavye / tık
- **Beklenen geri bildirim:** Komut yüzeyi açılır, arama eşleşmesi ve kapsamı görünür; kapanınca odak geri döner.
- **Yaygın hata:** Metin alanında yazarken tek harf kısayolu çalıştırmak veya gizli komutların yardımını sunmamak.
- **Emsal:** Linear, Notion

#### ME-080 — Bağlama göre komut (Contextual command)

- **Tanım:** Palet sonuçlarını mevcut seçim ve yetkiye göre daraltır.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** klavye / tık
- **Beklenen geri bildirim:** Komut yüzeyi açılır, arama eşleşmesi ve kapsamı görünür; kapanınca odak geri döner.
- **Yaygın hata:** Metin alanında yazarken tek harf kısayolu çalıştırmak veya gizli komutların yardımını sunmamak.
- **Emsal:** Linear, Notion

#### ME-081 — Kısayol yardım katmanı (Shortcut help)

- **Tanım:** Kullanılabilir klavye kısayollarını aranabilir bir listede gösterir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** klavye / tık
- **Beklenen geri bildirim:** Komut yüzeyi açılır, arama eşleşmesi ve kapsamı görünür; kapanınca odak geri döner.
- **Yaygın hata:** Metin alanında yazarken tek harf kısayolu çalıştırmak veya gizli komutların yardımını sunmamak.
- **Emsal:** Linear, Notion

#### ME-082 — İkili gezinme dizisi (Chorded navigation)

- **Tanım:** Gezinme ön eki ve hedef harfiyle iki aşamalı, çakışması düşük komut çalıştırır.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** klavye / tık
- **Beklenen geri bildirim:** Komut yüzeyi açılır, arama eşleşmesi ve kapsamı görünür; kapanınca odak geri döner.
- **Yaygın hata:** Metin alanında yazarken tek harf kısayolu çalıştırmak veya gizli komutların yardımını sunmamak.
- **Emsal:** Linear, Notion

#### ME-083 — J/K liste gezintisi (J/K navigation)

- **Tanım:** Liste odağını düzenleme kipinde değilken sonraki veya önceki kayda taşır.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** klavye / tık
- **Beklenen geri bildirim:** Komut yüzeyi açılır, arama eşleşmesi ve kapsamı görünür; kapanınca odak geri döner.
- **Yaygın hata:** Metin alanında yazarken tek harf kısayolu çalıştırmak veya gizli komutların yardımını sunmamak.
- **Emsal:** Linear, Notion

### KAT-05-T02 — Konum ve geçmiş

#### ME-084 — Geri/ileri gezinme (Back/forward navigation)

- **Tanım:** Tarayıcı ve uygulama geçmişiyle önceki ya da sonraki bağlama döner.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Geçerli konum başlık, breadcrumb veya seçili öğeyle görünür; dönüşte önceki odak korunur.
- **Yaygın hata:** Tarayıcı geri tuşunu bozmak, kırıntıda geçerli konumu yanlış göstermek veya odağı sayfa başına atmak.
- **Emsal:** Notion, Stripe

#### ME-085 — Breadcrumb (Breadcrumb)

- **Tanım:** Hiyerarşik konumu üst düzeylere geri bağlantılarla gösterir.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Geçerli konum başlık, breadcrumb veya seçili öğeyle görünür; dönüşte önceki odak korunur.
- **Yaygın hata:** Tarayıcı geri tuşunu bozmak, kırıntıda geçerli konumu yanlış göstermek veya odağı sayfa başına atmak.
- **Emsal:** Notion, Stripe

#### ME-086 — Son bakılanlar (Recently viewed)

- **Tanım:** Yakın zamanda açılan kayıt ve sayfalara kişisel erişim sağlar.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Geçerli konum başlık, breadcrumb veya seçili öğeyle görünür; dönüşte önceki odak korunur.
- **Yaygın hata:** Tarayıcı geri tuşunu bozmak, kırıntıda geçerli konumu yanlış göstermek veya odağı sayfa başına atmak.
- **Emsal:** Notion, Stripe

#### ME-087 — Sekmeli görünüm (Tabbed view)

- **Tanım:** Aynı bağlamdaki kardeş paneller arasında konumu kaybetmeden geçiş sağlar.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Geçerli konum başlık, breadcrumb veya seçili öğeyle görünür; dönüşte önceki odak korunur.
- **Yaygın hata:** Tarayıcı geri tuşunu bozmak, kırıntıda geçerli konumu yanlış göstermek veya odağı sayfa başına atmak.
- **Emsal:** Notion, Stripe

#### ME-088 — Bölünmüş görünüm (Split view)

- **Tanım:** Liste ve ayrıntıyı aynı bağlamda yan yana incelemeye izin verir.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Geçerli konum başlık, breadcrumb veya seçili öğeyle görünür; dönüşte önceki odak korunur.
- **Yaygın hata:** Tarayıcı geri tuşunu bozmak, kırıntıda geçerli konumu yanlış göstermek veya odağı sayfa başına atmak.
- **Emsal:** Notion, Stripe

### KAT-05-T03 — Bağlantılanabilir durum

#### ME-089 — Derin bağlantı (Deep link)

- **Tanım:** Belirli kayıt, bölüm veya görünümü doğrudan açan kalıcı URL üretir.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** URL, başlık ve görünür filtre/seçim aynı durumu tarif eder; yeniden yükleme bağlamı korur.
- **Yaygın hata:** Geçici veya gizli veriyi URL'ye koymak ya da paylaşılan linkte filtre durumunu kaybetmek.
- **Emsal:** Linear, Notion

#### ME-090 — URL'de görünüm durumu (URL state)

- **Tanım:** Paylaşılabilir filtre, sıralama ve sayfa durumunu URL ile eşleştirir.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** URL, başlık ve görünür filtre/seçim aynı durumu tarif eder; yeniden yükleme bağlamı korur.
- **Yaygın hata:** Geçici veya gizli veriyi URL'ye koymak ya da paylaşılan linkte filtre durumunu kaybetmek.
- **Emsal:** Linear, Notion

#### ME-091 — Odak geri yükleme (Focus restoration)

- **Tanım:** Geçici yüzey kapanınca klavye odağını onu açan öğeye döndürür.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** URL, başlık ve görünür filtre/seçim aynı durumu tarif eder; yeniden yükleme bağlamı korur.
- **Yaygın hata:** Geçici veya gizli veriyi URL'ye koymak ya da paylaşılan linkte filtre durumunu kaybetmek.
- **Emsal:** Linear, Notion

#### ME-092 — Katmanlı Escape (Escape hierarchy)

- **Tanım:** En içteki geçici katmandan başlayarak iptal ve kapatma sırasını öngörülebilir uygular.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** URL, başlık ve görünür filtre/seçim aynı durumu tarif eder; yeniden yükleme bağlamı korur.
- **Yaygın hata:** Geçici veya gizli veriyi URL'ye koymak ya da paylaşılan linkte filtre durumunu kaybetmek.
- **Emsal:** Linear, Notion

## KAT-06 — Geri bildirim ve durum

Sistemin ne yaptığını, sonucunu ve verinin güncelliğini görünür kılma.

### KAT-06-T01 — Kısa sonuç bildirimi

#### ME-093 — Başarı bildirimi (Success toast)

- **Tanım:** Tamamlanan eylemi çalışma akışını kesmeden kısa bir mesajla doğrular.
- **Öğe tipi:** sayfa, alan, satır
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Sonuç mesajı eylem, nesne ve gerekiyorsa geri alma seçeneğini açıkça belirtir.
- **Yaygın hata:** Mesajı çok kısa göstermek, yalnız renge dayanmak veya birden çok bildirimi üst üste gizlemek.
- **Emsal:** Material, Atlassian

#### ME-094 — Geri almalı bildirim (Undo toast)

- **Tanım:** Geri alınabilir eylemin sonucunu ve tek adımlı geri alma komutunu birlikte sunar.
- **Öğe tipi:** sayfa, alan, satır
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Sonuç mesajı eylem, nesne ve gerekiyorsa geri alma seçeneğini açıkça belirtir.
- **Yaygın hata:** Mesajı çok kısa göstermek, yalnız renge dayanmak veya birden çok bildirimi üst üste gizlemek.
- **Emsal:** Material, Atlassian

#### ME-095 — Satır içi başarı (Inline success)

- **Tanım:** Alan veya bölüm düzeyindeki başarıyı sonuçla aynı bağlamda gösterir.
- **Öğe tipi:** sayfa, alan, satır
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Sonuç mesajı eylem, nesne ve gerekiyorsa geri alma seçeneğini açıkça belirtir.
- **Yaygın hata:** Mesajı çok kısa göstermek, yalnız renge dayanmak veya birden çok bildirimi üst üste gizlemek.
- **Emsal:** Material, Atlassian

#### ME-096 — Satır içi hata (Inline error)

- **Tanım:** Düzeltilebilir hatayı ilgili alan ya da işlem yanında açıklar.
- **Öğe tipi:** sayfa, alan, satır
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Sonuç mesajı eylem, nesne ve gerekiyorsa geri alma seçeneğini açıkça belirtir.
- **Yaygın hata:** Mesajı çok kısa göstermek, yalnız renge dayanmak veya birden çok bildirimi üst üste gizlemek.
- **Emsal:** Material, Atlassian

#### ME-097 — Kısmi başarı (Partial success)

- **Tanım:** Toplu işlemin başarılı ve başarısız alt kümelerini ayrı sayılarla bildirir.
- **Öğe tipi:** sayfa, alan, satır
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Sonuç mesajı eylem, nesne ve gerekiyorsa geri alma seçeneğini açıkça belirtir.
- **Yaygın hata:** Mesajı çok kısa göstermek, yalnız renge dayanmak veya birden çok bildirimi üst üste gizlemek.
- **Emsal:** Material, Atlassian

### KAT-06-T02 — Bekleme ve ilerleme

#### ME-098 — Meşgul düğme (Busy button)

- **Tanım:** Gönderilen eylemin düğmesinde yinelenen tıklamayı engelleyip sürdüğünü gösterir.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** İşlem sürerken kapsam, ilerleme türü ve iptal olanağı bağlama uygun görünür.
- **Yaygın hata:** Belirsiz işi belirli yüzdeyle göstermek, yüklenen içerikle ilgisiz iskelet kullanmak veya düğmeyi açıklamasız kilitlemek.
- **Emsal:** Material, Carbon

#### ME-099 — Belirli ilerleme (Determinate progress)

- **Tanım:** Toplam iş biliniyorsa tamamlanan oranı gerçek veriden gösterir.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** İşlem sürerken kapsam, ilerleme türü ve iptal olanağı bağlama uygun görünür.
- **Yaygın hata:** Belirsiz işi belirli yüzdeyle göstermek, yüklenen içerikle ilgisiz iskelet kullanmak veya düğmeyi açıklamasız kilitlemek.
- **Emsal:** Material, Carbon

#### ME-100 — Belirsiz ilerleme (Indeterminate progress)

- **Tanım:** Toplam süre bilinmiyorsa işlemin sürdüğünü oran uydurmadan belirtir.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** İşlem sürerken kapsam, ilerleme türü ve iptal olanağı bağlama uygun görünür.
- **Yaygın hata:** Belirsiz işi belirli yüzdeyle göstermek, yüklenen içerikle ilgisiz iskelet kullanmak veya düğmeyi açıklamasız kilitlemek.
- **Emsal:** Material, Carbon

#### ME-101 — İskelet yükleme (Skeleton loading)

- **Tanım:** Beklenen içerik yapısının yer tutucusunu ilk yüklemede gösterir.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** İşlem sürerken kapsam, ilerleme türü ve iptal olanağı bağlama uygun görünür.
- **Yaygın hata:** Belirsiz işi belirli yüzdeyle göstermek, yüklenen içerikle ilgisiz iskelet kullanmak veya düğmeyi açıklamasız kilitlemek.
- **Emsal:** Material, Carbon

#### ME-102 — Yerel yükleme (Inline loading)

- **Tanım:** Yalnız etkilenen bölümün çalıştığını diğer sayfayı kilitlemeden gösterir.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** İşlem sürerken kapsam, ilerleme türü ve iptal olanağı bağlama uygun görünür.
- **Yaygın hata:** Belirsiz işi belirli yüzdeyle göstermek, yüklenen içerikle ilgisiz iskelet kullanmak veya düğmeyi açıklamasız kilitlemek.
- **Emsal:** Material, Carbon

### KAT-06-T03 — Kayıt ve bağlantı durumu

#### ME-103 — İyimser güncelleme (Optimistic update)

- **Tanım:** Başarı olasılığı yüksek eylemi anında gösterip sunucu reddinde geri çevirir.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-104 — İyimser geri sarma (Optimistic rollback)

- **Tanım:** Başarısız iyimser değişikliği önceki değere döndürüp nedeni açıklar.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-105 — Otomatik kaydetme (Autosave)

- **Tanım:** Değişikliği arka planda kaydederken açık kaydetme durumları gösterir.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-106 — Kaydedilmedi uyarısı (Unsaved indicator)

- **Tanım:** Henüz kalıcılaşmamış değişikliği sayfa ve alan bağlamında işaretler.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-107 — Kaydedildi işareti (Saved indicator)

- **Tanım:** Son değişikliğin kalıcı olarak yazıldığını sakin bir durumla doğrular.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-108 — Bağlantı kesildi (Offline state)

- **Tanım:** Ağ bağlantısı yokken hangi eylemlerin güvenli olduğunu açıkça belirtir.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-109 — Yeniden bağlandı (Reconnected state)

- **Tanım:** Bağlantı geri geldiğinde bekleyen değişikliklerin sonucunu bildirir.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

#### ME-110 — Eski veri uyarısı (Stale data warning)

- **Tanım:** Görüntülenen verinin güncel olmayabileceğini ve yenileme yolunu gösterir.
- **Öğe tipi:** sayfa, alan, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Kaydediliyor, kaydedildi, kaydedilemedi, çevrimdışı ve eski veri durumları metinle ayrılır.
- **Yaygın hata:** Kaydetme hatasını başarı işaretiyle örtmek veya çevrimdışı değişikliğin güvenle saklandığını varsaydırmak.
- **Emsal:** Notion, Stripe

### KAT-06-T04 — İçerik ve sonuç durumu

#### ME-111 — İlk kullanım boş durumu (First-use empty state)

- **Tanım:** Henüz veri oluşmadığında amaca ve mümkün sonraki adıma dair bağlam verir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Boşluk nedeni, sonuç adedi ve canlı değişim kaynağı kullanıcıya açıklanır.
- **Yaygın hata:** İlk kullanım boşluğunu sıfır sonuçla karıştırmak veya canlı değişiklikleri sessizce yer değiştirmek.
- **Emsal:** Linear, Carbon

#### ME-112 — Sıfır sonuç durumu (No-results state)

- **Tanım:** Filtre ya da aramanın eşleşme bulmadığını ve temizleme yolunu gösterir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Boşluk nedeni, sonuç adedi ve canlı değişim kaynağı kullanıcıya açıklanır.
- **Yaygın hata:** İlk kullanım boşluğunu sıfır sonuçla karıştırmak veya canlı değişiklikleri sessizce yer değiştirmek.
- **Emsal:** Linear, Carbon

#### ME-113 — Sonuç kartı (Result summary card)

- **Tanım:** Tamamlanan uzun işlemin ana sonucunu ve ayrıntıya geçişi özetler.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Boşluk nedeni, sonuç adedi ve canlı değişim kaynağı kullanıcıya açıklanır.
- **Yaygın hata:** İlk kullanım boşluğunu sıfır sonuçla karıştırmak veya canlı değişiklikleri sessizce yer değiştirmek.
- **Emsal:** Linear, Carbon

#### ME-114 — Canlı sayaç (Live counter)

- **Tanım:** Sayı değişimini kaynağıyla birlikte erişilebilir biçimde günceller.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Boşluk nedeni, sonuç adedi ve canlı değişim kaynağı kullanıcıya açıklanır.
- **Yaygın hata:** İlk kullanım boşluğunu sıfır sonuçla karıştırmak veya canlı değişiklikleri sessizce yer değiştirmek.
- **Emsal:** Linear, Carbon

#### ME-115 — Canlı değişim vurgusu (Live update highlight)

- **Tanım:** Arka planda değişen satır veya alanı kısa, anlamlı bir vurguyla işaretler.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Boşluk nedeni, sonuç adedi ve canlı değişim kaynağı kullanıcıya açıklanır.
- **Yaygın hata:** İlk kullanım boşluğunu sıfır sonuçla karıştırmak veya canlı değişiklikleri sessizce yer değiştirmek.
- **Emsal:** Linear, Carbon

## KAT-07 — Yıkıcı eylem güvenliği

Silme ve geri döndürülemeyen işlemlerde hata önleme, etkiyi anlama ve kurtarma.

### KAT-07-T01 — Geri alınabilir yıkım

#### ME-116 — Geri alınabilir sil (Undoable delete)

- **Tanım:** Silme eylemini önce geri alınabilir bir ara duruma taşır.
- **Öğe tipi:** satır, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kayıt görünümden kalkar; geri alma veya çöp kutusuna gitme yolu ve kapsamı açıkça bildirilir.
- **Yaygın hata:** Kalıcı silmeyi geri alınabilir gibi göstermek ya da geri alma süresini kullanıcıya açıklamamak.
- **Emsal:** Gmail, ClickUp

#### ME-117 — Yumuşak silme (Soft delete)

- **Tanım:** Kaydı kalıcı yok etmeden etkin veri kümesinden çıkarır.
- **Öğe tipi:** satır, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kayıt görünümden kalkar; geri alma veya çöp kutusuna gitme yolu ve kapsamı açıkça bildirilir.
- **Yaygın hata:** Kalıcı silmeyi geri alınabilir gibi göstermek ya da geri alma süresini kullanıcıya açıklamamak.
- **Emsal:** Gmail, ClickUp

#### ME-118 — Çöp kutusu (Trash)

- **Tanım:** Silinen kayıtları belirli yönetişim kuralı içinde geri yüklenebilir alanda tutar.
- **Öğe tipi:** satır, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kayıt görünümden kalkar; geri alma veya çöp kutusuna gitme yolu ve kapsamı açıkça bildirilir.
- **Yaygın hata:** Kalıcı silmeyi geri alınabilir gibi göstermek ya da geri alma süresini kullanıcıya açıklamamak.
- **Emsal:** Gmail, ClickUp

#### ME-119 — Süreli geri alma (Timed undo)

- **Tanım:** Geri alma fırsatının süreli olduğunu görünür ve erişilebilir biçimde bildirir.
- **Öğe tipi:** satır, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kayıt görünümden kalkar; geri alma veya çöp kutusuna gitme yolu ve kapsamı açıkça bildirilir.
- **Yaygın hata:** Kalıcı silmeyi geri alınabilir gibi göstermek ya da geri alma süresini kullanıcıya açıklamamak.
- **Emsal:** Gmail, ClickUp

#### ME-120 — Bekleyen silmeyi iptal (Cancel pending deletion)

- **Tanım:** Kalıcılaştırılmamış silme işini tamamlanmadan durdurur.
- **Öğe tipi:** satır, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kayıt görünümden kalkar; geri alma veya çöp kutusuna gitme yolu ve kapsamı açıkça bildirilir.
- **Yaygın hata:** Kalıcı silmeyi geri alınabilir gibi göstermek ya da geri alma süresini kullanıcıya açıklamamak.
- **Emsal:** Gmail, ClickUp

### KAT-07-T02 — Onay ve etki görünürlüğü

#### ME-121 — Basit onay (Confirmation dialog)

- **Tanım:** Yanlışlık riski bulunan eylem için hedefi adlandıran kısa onay ister.
- **Öğe tipi:** sayfa, belge, satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Onay yüzeyi eylem adı, hedef, etki ve geri döndürülebilirlik bilgisini birlikte gösterir.
- **Yaygın hata:** Her silmede aynı genel onayı kullanmak veya tehlikeli birincil düğmeyi varsayılan odak yapmak.
- **Emsal:** GitHub, Shopify

#### ME-122 — Bağlamsal yüksek risk onayı (High-risk confirmation)

- **Tanım:** Geri döndürülemeyen eylemde sonuçları ve alternatifleri ayrıntılı gösterir.
- **Öğe tipi:** sayfa, belge, satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Onay yüzeyi eylem adı, hedef, etki ve geri döndürülebilirlik bilgisini birlikte gösterir.
- **Yaygın hata:** Her silmede aynı genel onayı kullanmak veya tehlikeli birincil düğmeyi varsayılan odak yapmak.
- **Emsal:** GitHub, Shopify

#### ME-123 — Adını yazarak onay (Type-to-confirm)

- **Tanım:** Çok yüksek etkili işlemde hedef adının bilinçli yazılmasını ister.
- **Öğe tipi:** sayfa, belge, satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Onay yüzeyi eylem adı, hedef, etki ve geri döndürülebilirlik bilgisini birlikte gösterir.
- **Yaygın hata:** Her silmede aynı genel onayı kullanmak veya tehlikeli birincil düğmeyi varsayılan odak yapmak.
- **Emsal:** GitHub, Shopify

#### ME-124 — Bağımlılık etki özeti (Dependency impact preview)

- **Tanım:** Silme veya taşımanın ilişkili kayıtlar üzerindeki etkisini onaydan önce listeler.
- **Öğe tipi:** sayfa, belge, satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Onay yüzeyi eylem adı, hedef, etki ve geri döndürülebilirlik bilgisini birlikte gösterir.
- **Yaygın hata:** Her silmede aynı genel onayı kullanmak veya tehlikeli birincil düğmeyi varsayılan odak yapmak.
- **Emsal:** GitHub, Shopify

#### ME-125 — Kilit ve sahip göstergesi (Lock indicator)

- **Tanım:** Değiştirilemeyen nesnenin kilitli olduğunu ve kilit sahibini açıklar.
- **Öğe tipi:** sayfa, belge, satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Onay yüzeyi eylem adı, hedef, etki ve geri döndürülebilirlik bilgisini birlikte gösterir.
- **Yaygın hata:** Her silmede aynı genel onayı kullanmak veya tehlikeli birincil düğmeyi varsayılan odak yapmak.
- **Emsal:** GitHub, Shopify

#### ME-126 — Yetki engeli açıklaması (Permission guard)

- **Tanım:** Yıkıcı eylem yetkisizse komutu gizlemek yerine uygun yerde nedeni ve başvuru yolunu açıklar.
- **Öğe tipi:** sayfa, belge, satır
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Onay yüzeyi eylem adı, hedef, etki ve geri döndürülebilirlik bilgisini birlikte gösterir.
- **Yaygın hata:** Her silmede aynı genel onayı kullanmak veya tehlikeli birincil düğmeyi varsayılan odak yapmak.
- **Emsal:** GitHub, Shopify

## KAT-08 — Arama

Büyük bilgi alanında sorgu oluşturma, kapsamı daraltma ve sonucu anlama.

### KAT-08-T01 — Sorgu oluşturma

#### ME-127 — Küresel arama (Global search)

- **Tanım:** Yetkili kayıt türleri arasında tek girişten arama yapar.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

#### ME-128 — Bulanık arama (Fuzzy search)

- **Tanım:** Küçük yazım farklılıklarına rağmen olası eşleşmeleri sıralar.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

#### ME-129 — Kesin ifade araması (Exact phrase search)

- **Tanım:** Kullanıcının belirttiği karakter dizisini değişmeden arar.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

#### ME-130 — Sütun içi arama (Column search)

- **Tanım:** Aramayı tek bir sütunun değerleriyle sınırlar.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

#### ME-131 — Türkçe duyarsız eşleşme (Turkish-aware matching)

- **Tanım:** İ/ı/i/I dönüşümlerini açık yerel kuralla ele alarak tutarlı eşleşme sağlar.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

#### ME-132 — Arama içi filtre sözdizimi (Search filter syntax)

- **Tanım:** Alan ve değer belirten yapılandırılmış sorguları düz metin aramayla birleştirir.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

#### ME-133 — Kapsamlı arama (Scoped search)

- **Tanım:** Sorgunun geçerli kayıt türü, görünüm veya bölüm içinde çalışmasını sağlar.
- **Öğe tipi:** sayfa, tablo, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Arama odağı, kapsamı ve işlenen sorgu görünür; eşleşmeler açıkça vurgulanır.
- **Yaygın hata:** Kapsamı belirtmemek, yazılan karakterleri değiştirmek veya düzenleme alanında kısayolu ele geçirmek.
- **Emsal:** Linear, Notion

### KAT-08-T02 — Sorgu hafızası ve sonuç

#### ME-134 — Son aramalar (Recent searches)

- **Tanım:** Kullanıcının yakın sorgularını yeniden çalıştırılabilir ve silinebilir biçimde gösterir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son sorgular ve sonuç durumu silinebilir, seçilebilir ve kapsamıyla birlikte görünür.
- **Yaygın hata:** Hassas sorguları habersiz saklamak veya sıfır sonucu yükleme durumu gibi göstermek.
- **Emsal:** Gmail, Stripe

#### ME-135 — Kaydedilmiş arama (Saved search)

- **Tanım:** Sorgu ve kapsamını adlandırılmış, tekrar kullanılabilir bir nesne olarak saklar.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son sorgular ve sonuç durumu silinebilir, seçilebilir ve kapsamıyla birlikte görünür.
- **Yaygın hata:** Hassas sorguları habersiz saklamak veya sıfır sonucu yükleme durumu gibi göstermek.
- **Emsal:** Gmail, Stripe

#### ME-136 — Sorguyu temizle (Clear search)

- **Tanım:** Arama metnini ve geçici sonuç durumunu tek eylemle sıfırlar.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son sorgular ve sonuç durumu silinebilir, seçilebilir ve kapsamıyla birlikte görünür.
- **Yaygın hata:** Hassas sorguları habersiz saklamak veya sıfır sonucu yükleme durumu gibi göstermek.
- **Emsal:** Gmail, Stripe

#### ME-137 — Eşleşmeyi vurgula (Match highlighting)

- **Tanım:** Sonuç içinde sorguyla eşleşen parçayı metin anlamını bozmadan işaretler.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son sorgular ve sonuç durumu silinebilir, seçilebilir ve kapsamıyla birlikte görünür.
- **Yaygın hata:** Hassas sorguları habersiz saklamak veya sıfır sonucu yükleme durumu gibi göstermek.
- **Emsal:** Gmail, Stripe

#### ME-138 — Arama sonucu yok (Search no results)

- **Tanım:** Eşleşme bulunmadığını, kullanılan kapsamı ve sorguyu düzeltme yolunu belirtir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son sorgular ve sonuç durumu silinebilir, seçilebilir ve kapsamıyla birlikte görünür.
- **Yaygın hata:** Hassas sorguları habersiz saklamak veya sıfır sonucu yükleme durumu gibi göstermek.
- **Emsal:** Gmail, Stripe

## KAT-09 — Görünüm modları

Aynı veri kümesini göreve uygun farklı görsel yapılarda inceleme.

### KAT-09-T01 — Veri görünümü seçimi

#### ME-139 — Tablo görünümü (Table view)

- **Tanım:** Veriyi karşılaştırılabilir sütun ve satırlarda gösterir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-140 — Kart görünümü (Card view)

- **Tanım:** Her kaydı özet alanlardan oluşan ayrı kartta gösterir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-141 — Kanban görünümü (Kanban view)

- **Tanım:** Kayıtları bir alanın değerlerine göre sütunlarda düzenler.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-142 — Takvim görünümü (Calendar view)

- **Tanım:** Tarihli kayıtları zaman hücrelerine yerleştirir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-143 — Galeri görünümü (Gallery view)

- **Tanım:** Görsel ağırlıklı kayıtları önizleme kartlarıyla sunar.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-144 — Zaman çizelgesi (Timeline view)

- **Tanım:** Başlangıç ve bitişi olan kayıtları yatay zaman ekseninde gösterir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-145 — Kompakt liste (Compact list)

- **Tanım:** Az alanla yüksek kayıt yoğunluğu sağlayan metin ağırlıklı görünüm sunar.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-146 — Ana-ayrıntı görünümü (Master-detail view)

- **Tanım:** Liste seçimini aynı sayfadaki ayrıntı paneliyle eşleştirir.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-147 — Görünüm değiştirici (View switcher)

- **Tanım:** Uygun görünüm modları arasında etkin modu açıkça işaretleyerek geçiş yaptırır.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

#### ME-148 — Modlar arası durum koruma (Cross-view state preservation)

- **Tanım:** Görünüm değişirken ortak filtre, seçim ve kapsam bilgisini korur.
- **Öğe tipi:** sayfa, tablo
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Etkin mod, desteklediği işlemler ve korunan filtre/seçim görünür kalır.
- **Yaygın hata:** Mod değişiminde filtreyi, seçimi veya kaydırma bağlamını sessizce sıfırlamak.
- **Emsal:** Airtable, ClickUp

## KAT-10 — Sürükle-bırak

Konum, sıra, dosya ve yapı değişikliklerini doğrudan manipülasyonla yapma.

### KAT-10-T01 — Taşıma ve sıralama

#### ME-149 — Satırları sürükle sırala (Drag to reorder rows)

- **Tanım:** Satırın sırasını tutamaç üzerinden doğrudan değiştirir.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-150 — Kartları sürükle sırala (Drag to reorder cards)

- **Tanım:** Kartın aynı grup içindeki göreli sırasını değiştirir.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-151 — Listeye sürükle taşı (Drag to move between lists)

- **Tanım:** Kaydı açıkça geçerli başka bir liste veya gruba taşır.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-152 — Sütunu sürükle (Drag column)

- **Tanım:** Sütunun tablo içindeki konumunu sürüklemeyle değiştirir.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-153 — Çoklu öğe sürükle (Multi-item drag)

- **Tanım:** Seçilmiş öğe kümesini adet geri bildirimiyle tek hedefe taşır.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-154 — Sürükleme önizlemesi (Drag preview)

- **Tanım:** Taşınan nesneyi ve adetini işaretçiye bağlı temsille gösterir.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-155 — Geçerli bırakma hedefi (Valid drop target)

- **Tanım:** Bırakmayı kabul eden hedefi erişilebilir görsel işaretle belirtir.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-156 — Geçersiz bırakma hedefi (Invalid drop target)

- **Tanım:** Bırakmanın neden kabul edilmediğini işaret ve gerekçeyle bildirir.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-157 — Sürüklerken otomatik kaydır (Drag auto-scroll)

- **Tanım:** İşaretçi kenara yaklaşınca hedef alanı kontrollü biçimde kaydırır.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

#### ME-158 — Sürüklemeyi iptal et (Cancel drag)

- **Tanım:** Sürükleme işlemini değişiklik yapmadan başlangıç durumuna döndürür.
- **Öğe tipi:** satır, tablo, sayfa
- **Tetik:** sürükle / klavye
- **Beklenen geri bildirim:** Sürükleme tutamacı, hayalet önizleme, geçerli hedef ve bırakma sonucu görünür; klavye alternatifi vardır.
- **Yaygın hata:** Tüm satırı kazara sürükleme hedefi yapmak, geçersiz hedefi ayırt etmemek veya Escape ile iptal edememek.
- **Emsal:** Notion, Figma

### KAT-10-T02 — Dosya bırakma

#### ME-159 — Dosya bırakma bölgesi (File drop zone)

- **Tanım:** Dosyayı açık bir hedefe bırakarak ekleme akışını başlatır.
- **Öğe tipi:** alan, sayfa, belge
- **Tetik:** sürükle / tık
- **Beklenen geri bildirim:** Bırakma bölgesi etkinleşir; dosya adı, türü, boyutu, ilerleme ve hata ayrı gösterilir.
- **Yaygın hata:** Tüm sayfayı belirsiz bırakma alanı yapmak veya reddedilen dosyanın nedenini söylememek.
- **Emsal:** Notion, Figma

#### ME-160 — Çoklu dosya bırakma (Multi-file drop)

- **Tanım:** Birden çok dosyayı ayrı durum ve hata satırlarıyla kabul eder.
- **Öğe tipi:** alan, sayfa, belge
- **Tetik:** sürükle / tık
- **Beklenen geri bildirim:** Bırakma bölgesi etkinleşir; dosya adı, türü, boyutu, ilerleme ve hata ayrı gösterilir.
- **Yaygın hata:** Tüm sayfayı belirsiz bırakma alanı yapmak veya reddedilen dosyanın nedenini söylememek.
- **Emsal:** Notion, Figma

#### ME-161 — Bırakmaya klavye alternatifi (Keyboard alternative to drag)

- **Tanım:** Aynı dosya ya da taşıma işini dosya seçici veya komutla yapmayı sağlar.
- **Öğe tipi:** alan, sayfa, belge
- **Tetik:** sürükle / tık
- **Beklenen geri bildirim:** Bırakma bölgesi etkinleşir; dosya adı, türü, boyutu, ilerleme ve hata ayrı gösterilir.
- **Yaygın hata:** Tüm sayfayı belirsiz bırakma alanı yapmak veya reddedilen dosyanın nedenini söylememek.
- **Emsal:** Notion, Figma

## KAT-11 — Çıkış ve paylaşım

Veriyi dosya, yazdırma, bağlantı ve pano yoluyla kontrollü dışarı çıkarma.

### KAT-11-T01 — Dışa aktarma ve yazdırma

#### ME-162 — Seçileni dışa aktar (Export selected)

- **Tanım:** Yalnız açıkça seçilmiş kayıtları belirtilen biçimde dışa aktarır.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kapsam, biçim, alanlar ve hazırlama durumu dışa aktarmadan önce ve sonra görünür.
- **Yaygın hata:** Seçili/filtreli/tümü kapsamını belirtmemek veya uzun dışa aktarımı sessiz bırakmak.
- **Emsal:** Airtable, Shopify

#### ME-163 — Filtreliyi dışa aktar (Export filtered)

- **Tanım:** Etkin filtre sonucu kapsamını dosyaya aktarır.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kapsam, biçim, alanlar ve hazırlama durumu dışa aktarmadan önce ve sonra görünür.
- **Yaygın hata:** Seçili/filtreli/tümü kapsamını belirtmemek veya uzun dışa aktarımı sessiz bırakmak.
- **Emsal:** Airtable, Shopify

#### ME-164 — Tümünü dışa aktar (Export all)

- **Tanım:** Yetkili tam veri kümesini açık kapsam onayıyla dışa aktarır.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kapsam, biçim, alanlar ve hazırlama durumu dışa aktarmadan önce ve sonra görünür.
- **Yaygın hata:** Seçili/filtreli/tümü kapsamını belirtmemek veya uzun dışa aktarımı sessiz bırakmak.
- **Emsal:** Airtable, Shopify

#### ME-165 — Biçim seçici (Export format picker)

- **Tanım:** Desteklenen çıktı biçimlerini ve farklarını seçimden önce gösterir.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kapsam, biçim, alanlar ve hazırlama durumu dışa aktarmadan önce ve sonra görünür.
- **Yaygın hata:** Seçili/filtreli/tümü kapsamını belirtmemek veya uzun dışa aktarımı sessiz bırakmak.
- **Emsal:** Airtable, Shopify

#### ME-166 — Arka planda dışa aktar (Background export)

- **Tanım:** Uzun dışa aktarımı kuyruk işi olarak sürdürüp tamamlanınca bildirir.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kapsam, biçim, alanlar ve hazırlama durumu dışa aktarmadan önce ve sonra görünür.
- **Yaygın hata:** Seçili/filtreli/tümü kapsamını belirtmemek veya uzun dışa aktarımı sessiz bırakmak.
- **Emsal:** Airtable, Shopify

#### ME-167 — Yazdırma önizleme (Print preview)

- **Tanım:** Yazdırılacak sayfa, kırılım ve dahil alanları işlemden önce gösterir.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kapsam, biçim, alanlar ve hazırlama durumu dışa aktarmadan önce ve sonra görünür.
- **Yaygın hata:** Seçili/filtreli/tümü kapsamını belirtmemek veya uzun dışa aktarımı sessiz bırakmak.
- **Emsal:** Airtable, Shopify

### KAT-11-T02 — Bağlantı ve pano paylaşımı

#### ME-168 — Bağlantıyı kopyala (Copy link)

- **Tanım:** Geçerli nesnenin kalıcı bağlantısını panoya kopyalar.
- **Öğe tipi:** sayfa, tablo, satır, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kopyalama başarısı, paylaşılan kapsam ve erişim düzeyi metinle doğrulanır.
- **Yaygın hata:** Gizli veriyi bağlantıya taşımak, izin kapsamını göstermemek veya panoya ne kopyalandığını belirsiz bırakmak.
- **Emsal:** Linear, Notion

#### ME-169 — Görünümü paylaş (Share view)

- **Tanım:** Görünümün filtre ve alan kapsamını izinlerle birlikte paylaşır.
- **Öğe tipi:** sayfa, tablo, satır, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kopyalama başarısı, paylaşılan kapsam ve erişim düzeyi metinle doğrulanır.
- **Yaygın hata:** Gizli veriyi bağlantıya taşımak, izin kapsamını göstermemek veya panoya ne kopyalandığını belirsiz bırakmak.
- **Emsal:** Linear, Notion

#### ME-170 — Paylaşım izin önizlemesi (Share permission preview)

- **Tanım:** Alıcının göreceği kapsamı bağlantı üretilmeden önce özetler.
- **Öğe tipi:** sayfa, tablo, satır, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kopyalama başarısı, paylaşılan kapsam ve erişim düzeyi metinle doğrulanır.
- **Yaygın hata:** Gizli veriyi bağlantıya taşımak, izin kapsamını göstermemek veya panoya ne kopyalandığını belirsiz bırakmak.
- **Emsal:** Linear, Notion

#### ME-171 — Düz metin kopyala (Copy as plain text)

- **Tanım:** Seçili içeriği biçimsiz ve taşınabilir metin olarak panoya yazar.
- **Öğe tipi:** sayfa, tablo, satır, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kopyalama başarısı, paylaşılan kapsam ve erişim düzeyi metinle doğrulanır.
- **Yaygın hata:** Gizli veriyi bağlantıya taşımak, izin kapsamını göstermemek veya panoya ne kopyalandığını belirsiz bırakmak.
- **Emsal:** Linear, Notion

#### ME-172 — Sekmeli veri kopyala (Copy as TSV)

- **Tanım:** Tablo seçimini satır ve sütun yapısını koruyan sekmeli metin olarak kopyalar.
- **Öğe tipi:** sayfa, tablo, satır, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kopyalama başarısı, paylaşılan kapsam ve erişim düzeyi metinle doğrulanır.
- **Yaygın hata:** Gizli veriyi bağlantıya taşımak, izin kapsamını göstermemek veya panoya ne kopyalandığını belirsiz bırakmak.
- **Emsal:** Linear, Notion

#### ME-173 — Kopyalandı geri bildirimi (Copy confirmation)

- **Tanım:** Panoya yazma başarılı olduğunda kopyalanan kapsamı kısa mesajla doğrular.
- **Öğe tipi:** sayfa, tablo, satır, alan, belge
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kopyalama başarısı, paylaşılan kapsam ve erişim düzeyi metinle doğrulanır.
- **Yaygın hata:** Gizli veriyi bağlantıya taşımak, izin kapsamını göstermemek veya panoya ne kopyalandığını belirsiz bırakmak.
- **Emsal:** Linear, Notion

## KAT-12 — Form, modal ve çekmece

Geçici yüzeylerde odak, gönderim, iptal, taslak ve çok adımlı akış yönetimi.

### KAT-12-T01 — Odak ve kapatma sözleşmesi

#### ME-174 — Odak tuzağı (Focus trap)

- **Tanım:** Modal açıkken klavye odağını etkin modal içindeki denetimlerle sınırlar.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Açılan yüzey başlıkla duyurulur, odak içinde yönetilir ve kapanınca tetikleyiciye döner.
- **Yaygın hata:** Odağı arka sayfaya kaçırmak, Escape davranışını belirsizleştirmek veya kapanınca odağı kaybetmek.
- **Emsal:** Radix, WAI-ARIA

#### ME-175 — Anlamlı ilk odak (Initial focus)

- **Tanım:** Yüzey açıldığında odağı güvenli ve göreve uygun ilk hedefe taşır.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Açılan yüzey başlıkla duyurulur, odak içinde yönetilir ve kapanınca tetikleyiciye döner.
- **Yaygın hata:** Odağı arka sayfaya kaçırmak, Escape davranışını belirsizleştirmek veya kapanınca odağı kaybetmek.
- **Emsal:** Radix, WAI-ARIA

#### ME-176 — Odağı tetikleyiciye döndür (Return focus)

- **Tanım:** Yüzey kapanınca odağı onu açan öğeye geri verir.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Açılan yüzey başlıkla duyurulur, odak içinde yönetilir ve kapanınca tetikleyiciye döner.
- **Yaygın hata:** Odağı arka sayfaya kaçırmak, Escape davranışını belirsizleştirmek veya kapanınca odağı kaybetmek.
- **Emsal:** Radix, WAI-ARIA

#### ME-177 — Escape ile kapat (Escape to close)

- **Tanım:** En üst geçici yüzeyi veri kaybı riski yoksa Escape ile kapatır.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Açılan yüzey başlıkla duyurulur, odak içinde yönetilir ve kapanınca tetikleyiciye döner.
- **Yaygın hata:** Odağı arka sayfaya kaçırmak, Escape davranışını belirsizleştirmek veya kapanınca odağı kaybetmek.
- **Emsal:** Radix, WAI-ARIA

#### ME-178 — Dışarı tıklama sözleşmesi (Outside-click dismissal)

- **Tanım:** Modal olmayan yüzeyin dış tıklamada kapanma davranışını tutarlı uygular.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Açılan yüzey başlıkla duyurulur, odak içinde yönetilir ve kapanınca tetikleyiciye döner.
- **Yaygın hata:** Odağı arka sayfaya kaçırmak, Escape davranışını belirsizleştirmek veya kapanınca odağı kaybetmek.
- **Emsal:** Radix, WAI-ARIA

### KAT-12-T02 — Gönderim ve kayıtsız değişiklik

#### ME-179 — Enter ile gönder (Enter to submit)

- **Tanım:** Tek satırlı ve güvenli form bağlamında Enter ile birincil gönderimi çalıştırır.
- **Öğe tipi:** alan, sayfa
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Gönderim, doğrulama ve kayıtsız değişiklik durumu düğme ve sayfa düzeyinde görünür.
- **Yaygın hata:** Çok satırlı alanda Enter'ı yanlışlıkla gönderim yapmak veya çift tıklamayla mükerrer kayıt üretmek.
- **Emsal:** GOV.UK, Radix

#### ME-180 — Çift gönderimi engelle (Prevent duplicate submit)

- **Tanım:** İlk gönderim sürerken aynı işlemin yeniden başlatılmasını önler.
- **Öğe tipi:** alan, sayfa
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Gönderim, doğrulama ve kayıtsız değişiklik durumu düğme ve sayfa düzeyinde görünür.
- **Yaygın hata:** Çok satırlı alanda Enter'ı yanlışlıkla gönderim yapmak veya çift tıklamayla mükerrer kayıt üretmek.
- **Emsal:** GOV.UK, Radix

#### ME-181 — Kayıtsız kapatma uyarısı (Unsaved changes warning)

- **Tanım:** Kapanış veri kaybı doğuracaksa değişiklikleri koruma, atma veya geri dönme seçenekleri sunar.
- **Öğe tipi:** alan, sayfa
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Gönderim, doğrulama ve kayıtsız değişiklik durumu düğme ve sayfa düzeyinde görünür.
- **Yaygın hata:** Çok satırlı alanda Enter'ı yanlışlıkla gönderim yapmak veya çift tıklamayla mükerrer kayıt üretmek.
- **Emsal:** GOV.UK, Radix

#### ME-182 — Taslağı koru (Preserve draft)

- **Tanım:** Tamamlanmamış form verisini açık gizlilik ve süre kuralıyla geçici saklar.
- **Öğe tipi:** alan, sayfa
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Gönderim, doğrulama ve kayıtsız değişiklik durumu düğme ve sayfa düzeyinde görünür.
- **Yaygın hata:** Çok satırlı alanda Enter'ı yanlışlıkla gönderim yapmak veya çift tıklamayla mükerrer kayıt üretmek.
- **Emsal:** GOV.UK, Radix

#### ME-183 — Taslağa devam et (Resume draft)

- **Tanım:** Kullanıcıya kayıtlı taslağı sürdürme veya silme seçimi verir.
- **Öğe tipi:** alan, sayfa
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Gönderim, doğrulama ve kayıtsız değişiklik durumu düğme ve sayfa düzeyinde görünür.
- **Yaygın hata:** Çok satırlı alanda Enter'ı yanlışlıkla gönderim yapmak veya çift tıklamayla mükerrer kayıt üretmek.
- **Emsal:** GOV.UK, Radix

### KAT-12-T03 — Çok adımlı ve koşullu form

#### ME-184 — Adım göstergesi (Step indicator)

- **Tanım:** Çok adımlı formda mevcut, tamamlanan ve kalan adımları gösterir.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Adım konumu, tamamlanma durumu ve ilgili hata özeti sürekli görünür.
- **Yaygın hata:** Adımlar arası veriyi kaybetmek, görünmeyen alan hatasıyla ilerlemeyi engellemek veya koşullu alanı açıklamasız açmak.
- **Emsal:** Atlassian, GOV.UK

#### ME-185 — Adım bazlı doğrulama (Step validation)

- **Tanım:** İleri geçmeden yalnız mevcut adımın gerekli alanlarını doğrular.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Adım konumu, tamamlanma durumu ve ilgili hata özeti sürekli görünür.
- **Yaygın hata:** Adımlar arası veriyi kaybetmek, görünmeyen alan hatasıyla ilerlemeyi engellemek veya koşullu alanı açıklamasız açmak.
- **Emsal:** Atlassian, GOV.UK

#### ME-186 — Hata özeti (Error summary)

- **Tanım:** Gönderim sonrası hataları sayfa başında alanlara bağlantılı biçimde listeler.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Adım konumu, tamamlanma durumu ve ilgili hata özeti sürekli görünür.
- **Yaygın hata:** Adımlar arası veriyi kaybetmek, görünmeyen alan hatasıyla ilerlemeyi engellemek veya koşullu alanı açıklamasız açmak.
- **Emsal:** Atlassian, GOV.UK

#### ME-187 — Koşullu alan açma (Conditional reveal)

- **Tanım:** Bir seçime bağlı alanları ilişkisi anlaşılır biçimde gösterir veya gizler.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Adım konumu, tamamlanma durumu ve ilgili hata özeti sürekli görünür.
- **Yaygın hata:** Adımlar arası veriyi kaybetmek, görünmeyen alan hatasıyla ilerlemeyi engellemek veya koşullu alanı açıklamasız açmak.
- **Emsal:** Atlassian, GOV.UK

#### ME-188 — Gözden geçirme adımı (Review step)

- **Tanım:** Kalıcı gönderimden önce girilen değerleri düzenleme bağlantılarıyla özetler.
- **Öğe tipi:** sayfa, alan
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Adım konumu, tamamlanma durumu ve ilgili hata özeti sürekli görünür.
- **Yaygın hata:** Adımlar arası veriyi kaybetmek, görünmeyen alan hatasıyla ilerlemeyi engellemek veya koşullu alanı açıklamasız açmak.
- **Emsal:** Atlassian, GOV.UK

## KAT-13 — Mikro animasyon ve hareket

Durum değişimini açıklayan, odağı yönlendiren ve azaltılmış hareket tercihine uyan geçişler.

### KAT-13-T01 — Anlam taşıyan geçiş

#### ME-189 — Hover durum geçişi (Hover transition)

- **Tanım:** Etkileşim hedefinin hover durumuna geçtiğini kısa ve dikkat dağıtmayan değişimle gösterir.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

#### ME-190 — Basılma geri bildirimi (Press feedback)

- **Tanım:** Denetimin etkinleştirildiğini anlık görsel durumla doğrular.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

#### ME-191 — Açılma-kapanma geçişi (Expand/collapse transition)

- **Tanım:** İçeriğin hangi başlığa bağlı açılıp kapandığını mekânsal süreklilikle gösterir.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

#### ME-192 — Giriş-çıkış geçişi (Enter/exit transition)

- **Tanım:** Geçici yüzeyin görünme ve kaybolma nedenini konum ve opaklık ilişkisiyle açıklar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

#### ME-193 — Yeniden sıralama hareketi (Reorder animation)

- **Tanım:** Taşınan öğenin eski ve yeni yerini diğer öğeleri kaydırarak anlaşılır kılar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

#### ME-194 — Durum dönüşümü (State morph)

- **Tanım:** Aynı denetimin iki durumu arasındaki ilişkiyi biçim dönüşümüyle gösterir.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

#### ME-195 — Güncelleme vurgusu (Update highlight)

- **Tanım:** Değişen alanı sakin ve geçici bir görsel vurgu ile belirtir.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** hover / tık / otomatik
- **Beklenen geri bildirim:** Hareket hangi nesnenin değiştiğini ve başlangıç-bitiş ilişkisini anlaşılır kılar.
- **Yaygın hata:** Süs için hareket eklemek, eylemi bekletmek veya birden çok odağı aynı anda hareket ettirmek.
- **Emsal:** Material, Atlassian

### KAT-13-T02 — Hareket yönetişimi

#### ME-196 — Anlamsal hareket tokenı (Semantic motion token)

- **Tanım:** Süre, easing ve özelliği ham değer yerine kaynaklı tasarım sistemi niyetinden seçer.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Seçilen hareket, tasarım sistemi anlamlı tokenına bağlıdır ve azaltılmış kipte eşdeğer durum işareti korunur.
- **Yaygın hata:** Kaynağı olmayan süre/easing değeri üretmek veya hareket kapandığında durum bilgisini de kaybetmek.
- **Emsal:** Apple, Atlassian

#### ME-197 — Azaltılmış hareket (Reduced motion)

- **Tanım:** Sistem hareket azaltma tercihini izleyerek büyük ya da sürekli hareketi kaldırır.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Seçilen hareket, tasarım sistemi anlamlı tokenına bağlıdır ve azaltılmış kipte eşdeğer durum işareti korunur.
- **Yaygın hata:** Kaynağı olmayan süre/easing değeri üretmek veya hareket kapandığında durum bilgisini de kaybetmek.
- **Emsal:** Apple, Atlassian

#### ME-198 — Hareketsiz eşdeğer (No-motion equivalent)

- **Tanım:** Animasyon kapalıyken aynı durum değişimini metin, konum veya işaretle anlaşılır tutar.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Seçilen hareket, tasarım sistemi anlamlı tokenına bağlıdır ve azaltılmış kipte eşdeğer durum işareti korunur.
- **Yaygın hata:** Kaynağı olmayan süre/easing değeri üretmek veya hareket kapandığında durum bilgisini de kaybetmek.
- **Emsal:** Apple, Atlassian

#### ME-199 — Engellemeyen hareket (Non-blocking motion)

- **Tanım:** Geçiş sürerken güvenli sonraki etkileşimi gereksiz yere geciktirmez.
- **Öğe tipi:** sayfa, tablo, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Seçilen hareket, tasarım sistemi anlamlı tokenına bağlıdır ve azaltılmış kipte eşdeğer durum işareti korunur.
- **Yaygın hata:** Kaynağı olmayan süre/easing değeri üretmek veya hareket kapandığında durum bilgisini de kaybetmek.
- **Emsal:** Apple, Atlassian

## KAT-14 — Erişilebilirlik mikro davranışları

Klavye, ekran okuyucu, görsel odak ve farklı giriş yöntemlerinde eşdeğer etkileşim.

### KAT-14-T01 — Klavye ve odak

#### ME-200 — Görünür odak halkası (Visible focus ring)

- **Tanım:** Klavye odağındaki denetimi yeterli alan ve kontrastla açıkça işaretler.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** klavye / otomatik
- **Beklenen geri bildirim:** Odak halkası, sıra ve etkin denetim her adımda görünür; kapatma sonrası odak korunur.
- **Yaygın hata:** Odak halkasını kaldırmak, DOM sırası ile görsel sırayı ayırmak veya klavye tuzağı oluşturmak.
- **Emsal:** WAI-ARIA, GitHub

#### ME-201 — Tam klavye eşdeğerliği (Keyboard parity)

- **Tanım:** Fareyle yapılabilen her temel eyleme klavye yolu sağlar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** klavye / otomatik
- **Beklenen geri bildirim:** Odak halkası, sıra ve etkin denetim her adımda görünür; kapatma sonrası odak korunur.
- **Yaygın hata:** Odak halkasını kaldırmak, DOM sırası ile görsel sırayı ayırmak veya klavye tuzağı oluşturmak.
- **Emsal:** WAI-ARIA, GitHub

#### ME-202 — Mantıksal odak sırası (Logical focus order)

- **Tanım:** Odak sırasını görsel ve anlamsal okuma sırasıyla uyumlu tutar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** klavye / otomatik
- **Beklenen geri bildirim:** Odak halkası, sıra ve etkin denetim her adımda görünür; kapatma sonrası odak korunur.
- **Yaygın hata:** Odak halkasını kaldırmak, DOM sırası ile görsel sırayı ayırmak veya klavye tuzağı oluşturmak.
- **Emsal:** WAI-ARIA, GitHub

#### ME-203 — İçeriğe atla (Skip link)

- **Tanım:** Tekrarlanan gezinmeyi atlayıp ana içeriğe doğrudan odak taşır.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** klavye / otomatik
- **Beklenen geri bildirim:** Odak halkası, sıra ve etkin denetim her adımda görünür; kapatma sonrası odak korunur.
- **Yaygın hata:** Odak halkasını kaldırmak, DOM sırası ile görsel sırayı ayırmak veya klavye tuzağı oluşturmak.
- **Emsal:** WAI-ARIA, GitHub

#### ME-204 — Odak örtülmesini önle (Focus not obscured)

- **Tanım:** Odaktaki denetimi yapışkan başlık veya panel arkasında görünmez bırakmaz.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** klavye / otomatik
- **Beklenen geri bildirim:** Odak halkası, sıra ve etkin denetim her adımda görünür; kapatma sonrası odak korunur.
- **Yaygın hata:** Odak halkasını kaldırmak, DOM sırası ile görsel sırayı ayırmak veya klavye tuzağı oluşturmak.
- **Emsal:** WAI-ARIA, GitHub

### KAT-14-T02 — Duyuru ve anlamsal durum

#### ME-205 — Durum mesajı duyurusu (Status announcement)

- **Tanım:** Odağı taşımadan önemli işlem sonucunu ekran okuyucuya bildirir.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** otomatik / klavye
- **Beklenen geri bildirim:** Durum, hata, seçim ve sıralama değişiklikleri görsel metinle birlikte yardımcı teknolojiye duyurulur.
- **Yaygın hata:** Her küçük değişimi agresif duyurmak, yalnız renk kullanmak veya görünür adla erişilebilir adı ayırmak.
- **Emsal:** Figma, WAI-ARIA

#### ME-206 — Hata duyurusu (Error announcement)

- **Tanım:** Doğrulama hatasını alan adı ve düzeltme bağlamıyla duyurur.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** otomatik / klavye
- **Beklenen geri bildirim:** Durum, hata, seçim ve sıralama değişiklikleri görsel metinle birlikte yardımcı teknolojiye duyurulur.
- **Yaygın hata:** Her küçük değişimi agresif duyurmak, yalnız renk kullanmak veya görünür adla erişilebilir adı ayırmak.
- **Emsal:** Figma, WAI-ARIA

#### ME-207 — Seçim sayısı duyurusu (Selection count announcement)

- **Tanım:** Toplu seçim adedi değiştiğinde yeni kapsamı yardımcı teknolojiye bildirir.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** otomatik / klavye
- **Beklenen geri bildirim:** Durum, hata, seçim ve sıralama değişiklikleri görsel metinle birlikte yardımcı teknolojiye duyurulur.
- **Yaygın hata:** Her küçük değişimi agresif duyurmak, yalnız renk kullanmak veya görünür adla erişilebilir adı ayırmak.
- **Emsal:** Figma, WAI-ARIA

#### ME-208 — Sıralama durumu duyurusu (Sort state announcement)

- **Tanım:** Sütun sıralamasının yönünü ve çoklu önceliğini programatik olarak belirtir.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** otomatik / klavye
- **Beklenen geri bildirim:** Durum, hata, seçim ve sıralama değişiklikleri görsel metinle birlikte yardımcı teknolojiye duyurulur.
- **Yaygın hata:** Her küçük değişimi agresif duyurmak, yalnız renk kullanmak veya görünür adla erişilebilir adı ayırmak.
- **Emsal:** Figma, WAI-ARIA

#### ME-209 — Görünür adla eş ad (Label-in-name)

- **Tanım:** Denetimin erişilebilir adını görünür etiketle uyumlu tutar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** otomatik / klavye
- **Beklenen geri bildirim:** Durum, hata, seçim ve sıralama değişiklikleri görsel metinle birlikte yardımcı teknolojiye duyurulur.
- **Yaygın hata:** Her küçük değişimi agresif duyurmak, yalnız renk kullanmak veya görünür adla erişilebilir adı ayırmak.
- **Emsal:** Figma, WAI-ARIA

#### ME-210 — Renge ek durum işareti (Non-color status cue)

- **Tanım:** Durumu renge ek olarak metin, şekil veya simgeyle ifade eder.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** otomatik / klavye
- **Beklenen geri bildirim:** Durum, hata, seçim ve sıralama değişiklikleri görsel metinle birlikte yardımcı teknolojiye duyurulur.
- **Yaygın hata:** Her küçük değişimi agresif duyurmak, yalnız renk kullanmak veya görünür adla erişilebilir adı ayırmak.
- **Emsal:** Figma, WAI-ARIA

### KAT-14-T03 — Alternatif giriş ve algı

#### ME-211 — Hover ve odak eşdeğerliği (Hover/focus parity)

- **Tanım:** Hover ile açılan bilgiyi klavye odağıyla da erişilebilir kılar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** tık / klavye / sürükle
- **Beklenen geri bildirim:** Aynı bilgi ve eylem yakınlaştırma, yüksek kontrast ve sürüklemesiz kullanımda korunur.
- **Yaygın hata:** Sürüklemeyi tek yol yapmak, hover içeriğini klavye odağında göstermemek veya yakınlaştırmada denetimleri kaybetmek.
- **Emsal:** Apple, W3C

#### ME-212 — Sürüklemesiz alternatif (Non-drag alternative)

- **Tanım:** Sürükleme eylemini düğme, menü veya klavye komutuyla da sunar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** tık / klavye / sürükle
- **Beklenen geri bildirim:** Aynı bilgi ve eylem yakınlaştırma, yüksek kontrast ve sürüklemesiz kullanımda korunur.
- **Yaygın hata:** Sürüklemeyi tek yol yapmak, hover içeriğini klavye odağında göstermemek veya yakınlaştırmada denetimleri kaybetmek.
- **Emsal:** Apple, W3C

#### ME-213 — Yakınlaştırmada yeniden akış (Zoom reflow)

- **Tanım:** Yakınlaştırma ve dar görünümde işlevleri yatay kayıp olmadan yeniden düzenler.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** tık / klavye / sürükle
- **Beklenen geri bildirim:** Aynı bilgi ve eylem yakınlaştırma, yüksek kontrast ve sürüklemesiz kullanımda korunur.
- **Yaygın hata:** Sürüklemeyi tek yol yapmak, hover içeriğini klavye odağında göstermemek veya yakınlaştırmada denetimleri kaybetmek.
- **Emsal:** Apple, W3C

#### ME-214 — Kontrastlı durum (High-contrast state)

- **Tanım:** Odak, seçim ve hata göstergelerini yüksek kontrast koşullarında ayırt edilebilir tutar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** tık / klavye / sürükle
- **Beklenen geri bildirim:** Aynı bilgi ve eylem yakınlaştırma, yüksek kontrast ve sürüklemesiz kullanımda korunur.
- **Yaygın hata:** Sürüklemeyi tek yol yapmak, hover içeriğini klavye odağında göstermemek veya yakınlaştırmada denetimleri kaybetmek.
- **Emsal:** Apple, W3C

## KAT-15 — Bildirim ve arka plan işleri

Kullanıcı sayfadan ayrılsa da süren işlerin ve biriken olayların izlenmesi.

### KAT-15-T01 — Bildirim merkezi

#### ME-215 — Bildirim merkezi (Notification center)

- **Tanım:** İlgili olayları kronolojik ve filtrelenebilir bir yüzeyde toplar.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Okunmamış adet, olay kaynağı, zaman ve hedef bağlantısı tutarlı görünür.
- **Yaygın hata:** Bildirim rozetini içerikle tutarsız bırakmak veya bildirimi hedef bağlama taşımamak.
- **Emsal:** Linear, Gmail

#### ME-216 — Okunmamış rozeti (Unread badge)

- **Tanım:** Henüz görülmemiş bildirim sayısını erişilebilir etiketle gösterir.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Okunmamış adet, olay kaynağı, zaman ve hedef bağlantısı tutarlı görünür.
- **Yaygın hata:** Bildirim rozetini içerikle tutarsız bırakmak veya bildirimi hedef bağlama taşımamak.
- **Emsal:** Linear, Gmail

#### ME-217 — Okundu işaretle (Mark notification read)

- **Tanım:** Bildirimin kişisel okunma durumunu değiştirir.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Okunmamış adet, olay kaynağı, zaman ve hedef bağlantısı tutarlı görünür.
- **Yaygın hata:** Bildirim rozetini içerikle tutarsız bırakmak veya bildirimi hedef bağlama taşımamak.
- **Emsal:** Linear, Gmail

#### ME-218 — Bildirim filtresi (Notification filter)

- **Tanım:** Olayları tür, önem veya okunma durumuna göre daraltır.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Okunmamış adet, olay kaynağı, zaman ve hedef bağlantısı tutarlı görünür.
- **Yaygın hata:** Bildirim rozetini içerikle tutarsız bırakmak veya bildirimi hedef bağlama taşımamak.
- **Emsal:** Linear, Gmail

#### ME-219 — Bildirimin hedefine git (Notification deep link)

- **Tanım:** Bildirimi ilgili kayıt ve olay bağlamında açar.
- **Öğe tipi:** sayfa, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Okunmamış adet, olay kaynağı, zaman ve hedef bağlantısı tutarlı görünür.
- **Yaygın hata:** Bildirim rozetini içerikle tutarsız bırakmak veya bildirimi hedef bağlama taşımamak.
- **Emsal:** Linear, Gmail

### KAT-15-T02 — Uzun iş ve kuyruk

#### ME-220 — İşlem kuyruğu göstergesi (Job queue indicator)

- **Tanım:** Bekleyen ve çalışan arka plan işlerinin adedini ve durumunu gösterir.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

#### ME-221 — Uzun iş ilerlemesi (Long-running job progress)

- **Tanım:** Süregelen işin aşama veya gerçek tamamlanma oranını gösterir.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

#### ME-222 — Arka plan işini iptal (Cancel background job)

- **Tanım:** Güvenle durdurulabilen işi açık sonuçla iptal eder.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

#### ME-223 — Başarısız işi yeniden dene (Retry failed job)

- **Tanım:** Başarısız işi önceki girdileri koruyarak yeniden başlatır.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

#### ME-224 — Tamamlanma bildirimi (Completion notification)

- **Tanım:** Kullanıcı başka bağlamdayken biten işi sonuç bağlantısıyla bildirir.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

#### ME-225 — Kısmi hata raporu (Partial failure report)

- **Tanım:** Toplu işte başarısız öğeleri neden ve düzeltme yolu ile ayırır.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

#### ME-226 — Kalıcı iş durumu (Durable job state)

- **Tanım:** Sayfa yenilense veya oturum değişse de izin verilen iş durumunu yeniden gösterir.
- **Öğe tipi:** sayfa, belge, tablo
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** İş adı, kuyruk durumu, gerçek ilerleme, iptal/yeniden deneme ve sonuç erişilebilir kalır.
- **Yaygın hata:** Sayfa kapanınca işi kaybetmek, yüzde uydurmak veya başarısız alt öğeleri gizlemek.
- **Emsal:** Stripe, Shopify

## KAT-16 — Kişiselleştirme ve hafıza

Kullanıcının çalışma tercihlerini açık kapsam ve sıfırlama yolu ile hatırlama.

### KAT-16-T01 — Görünüm tercihleri

#### ME-227 — Sütun genişliğini hatırla (Remember column width)

- **Tanım:** Kullanıcının sütun genişliği ayarını tanımlı kapsamda geri yükler.
- **Öğe tipi:** tablo, sayfa
- **Tetik:** sürükle / tık / otomatik
- **Beklenen geri bildirim:** Hatırlanan tercih yeniden girişte görünür; kişisel ve paylaşılan kapsam ayırt edilir.
- **Yaygın hata:** Kişisel tercihi herkese uygulamak veya sıfırlama yolunu gizlemek.
- **Emsal:** Airtable, Notion

#### ME-228 — Sütun sırasını hatırla (Remember column order)

- **Tanım:** Kişisel sütun düzenini sonraki ziyarette korur.
- **Öğe tipi:** tablo, sayfa
- **Tetik:** sürükle / tık / otomatik
- **Beklenen geri bildirim:** Hatırlanan tercih yeniden girişte görünür; kişisel ve paylaşılan kapsam ayırt edilir.
- **Yaygın hata:** Kişisel tercihi herkese uygulamak veya sıfırlama yolunu gizlemek.
- **Emsal:** Airtable, Notion

#### ME-229 — Gizli sütunları hatırla (Remember hidden columns)

- **Tanım:** Kullanıcının görünürlük seçimini görünüm kapsamında saklar.
- **Öğe tipi:** tablo, sayfa
- **Tetik:** sürükle / tık / otomatik
- **Beklenen geri bildirim:** Hatırlanan tercih yeniden girişte görünür; kişisel ve paylaşılan kapsam ayırt edilir.
- **Yaygın hata:** Kişisel tercihi herkese uygulamak veya sıfırlama yolunu gizlemek.
- **Emsal:** Airtable, Notion

#### ME-230 — Yoğunluğu hatırla (Remember density)

- **Tanım:** Seçilen tablo yoğunluğunu aynı kullanıcı için korur.
- **Öğe tipi:** tablo, sayfa
- **Tetik:** sürükle / tık / otomatik
- **Beklenen geri bildirim:** Hatırlanan tercih yeniden girişte görünür; kişisel ve paylaşılan kapsam ayırt edilir.
- **Yaygın hata:** Kişisel tercihi herkese uygulamak veya sıfırlama yolunu gizlemek.
- **Emsal:** Airtable, Notion

#### ME-231 — Son görünümü hatırla (Remember last view)

- **Tanım:** Son kullanılan görünüm modunu geri dönüşte açar.
- **Öğe tipi:** tablo, sayfa
- **Tetik:** sürükle / tık / otomatik
- **Beklenen geri bildirim:** Hatırlanan tercih yeniden girişte görünür; kişisel ve paylaşılan kapsam ayırt edilir.
- **Yaygın hata:** Kişisel tercihi herkese uygulamak veya sıfırlama yolunu gizlemek.
- **Emsal:** Airtable, Notion

### KAT-16-T02 — Kişisel kısayol ve yakın geçmiş

#### ME-232 — Son kullanılan seçimi hatırla (Remember last choice)

- **Tanım:** Tekrarlanan seçim yüzeyinde kullanıcının son geçerli tercihini önerir.
- **Öğe tipi:** sayfa, tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son kullanılan ve sabitlenen öğeler kişisel olarak işaretlenir; silme ve sıfırlama mümkündür.
- **Yaygın hata:** Hassas geçmişi habersiz saklamak veya kaldırılan kaydı erişilebilir göstermeye devam etmek.
- **Emsal:** Linear, Gmail

#### ME-233 — Sabitlenenleri hatırla (Remember pins)

- **Tanım:** Kişisel sabitlenen kayıt ve görünüm listesini korur.
- **Öğe tipi:** sayfa, tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son kullanılan ve sabitlenen öğeler kişisel olarak işaretlenir; silme ve sıfırlama mümkündür.
- **Yaygın hata:** Hassas geçmişi habersiz saklamak veya kaldırılan kaydı erişilebilir göstermeye devam etmek.
- **Emsal:** Linear, Gmail

#### ME-234 — Yakın geçmiş (Recent items)

- **Tanım:** Yakın açılan öğeleri silinebilir kişisel liste halinde sunar.
- **Öğe tipi:** sayfa, tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son kullanılan ve sabitlenen öğeler kişisel olarak işaretlenir; silme ve sıfırlama mümkündür.
- **Yaygın hata:** Hassas geçmişi habersiz saklamak veya kaldırılan kaydı erişilebilir göstermeye devam etmek.
- **Emsal:** Linear, Gmail

#### ME-235 — Kısayol özelleştirme (Shortcut customization)

- **Tanım:** Desteklenen komutların tuş atamasını çakışma kontrolüyle değiştirmeye izin verir.
- **Öğe tipi:** sayfa, tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son kullanılan ve sabitlenen öğeler kişisel olarak işaretlenir; silme ve sıfırlama mümkündür.
- **Yaygın hata:** Hassas geçmişi habersiz saklamak veya kaldırılan kaydı erişilebilir göstermeye devam etmek.
- **Emsal:** Linear, Gmail

#### ME-236 — Tercihleri sıfırla (Reset preferences)

- **Tanım:** Kişisel görünüm hafızasını açıklanan varsayılanlara döndürür.
- **Öğe tipi:** sayfa, tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son kullanılan ve sabitlenen öğeler kişisel olarak işaretlenir; silme ve sıfırlama mümkündür.
- **Yaygın hata:** Hassas geçmişi habersiz saklamak veya kaldırılan kaydı erişilebilir göstermeye devam etmek.
- **Emsal:** Linear, Gmail

#### ME-237 — Tercih kapsamını göster (Preference scope indicator)

- **Tanım:** Bir ayarın bu cihaz, hesap veya paylaşılan görünüm için geçerli olduğunu belirtir.
- **Öğe tipi:** sayfa, tablo, satır
- **Tetik:** tık / klavye / otomatik
- **Beklenen geri bildirim:** Son kullanılan ve sabitlenen öğeler kişisel olarak işaretlenir; silme ve sıfırlama mümkündür.
- **Yaygın hata:** Hassas geçmişi habersiz saklamak veya kaldırılan kaydı erişilebilir göstermeye devam etmek.
- **Emsal:** Linear, Gmail

## KAT-17 — İşbirliği ve eşzamanlılık

Birden çok kişinin aynı veriyle çalışırken varlık, değişiklik ve çatışmayı anlaması.

### KAT-17-T01 — Canlı varlık ve değişim

#### ME-238 — Eşzamanlı kullanıcı varlığı (Presence indicator)

- **Tanım:** Aynı bağlamda bulunan diğer kullanıcıları güncel varlık işaretiyle gösterir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kim, nerede ve neyi değiştiriyor bilgisi kişisel veriyi aşmadan görünür; hareket yardımcı teknolojiye aşırı duyurulmaz.
- **Yaygın hata:** Varlığı kesin kilit gibi göstermek veya uzak değişiklikte kullanıcının yerel odağını sıçratmak.
- **Emsal:** Figma, Notion

#### ME-239 — Canlı imleç (Live cursor)

- **Tanım:** Diğer kullanıcının işaretçi veya odak konumunu ayırt edilebilir biçimde gösterir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kim, nerede ve neyi değiştiriyor bilgisi kişisel veriyi aşmadan görünür; hareket yardımcı teknolojiye aşırı duyurulmaz.
- **Yaygın hata:** Varlığı kesin kilit gibi göstermek veya uzak değişiklikte kullanıcının yerel odağını sıçratmak.
- **Emsal:** Figma, Notion

#### ME-240 — Canlı alan düzenleme (Live field editing)

- **Tanım:** Bir alanın başka kullanıcı tarafından düzenlendiğini geçici olarak belirtir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kim, nerede ve neyi değiştiriyor bilgisi kişisel veriyi aşmadan görünür; hareket yardımcı teknolojiye aşırı duyurulmaz.
- **Yaygın hata:** Varlığı kesin kilit gibi göstermek veya uzak değişiklikte kullanıcının yerel odağını sıçratmak.
- **Emsal:** Figma, Notion

#### ME-241 — Uzak değişiklik vurgusu (Remote change highlight)

- **Tanım:** Başka kullanıcıdan gelen değişikliği yerel odağı bozmadan işaretler.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kim, nerede ve neyi değiştiriyor bilgisi kişisel veriyi aşmadan görünür; hareket yardımcı teknolojiye aşırı duyurulmaz.
- **Yaygın hata:** Varlığı kesin kilit gibi göstermek veya uzak değişiklikte kullanıcının yerel odağını sıçratmak.
- **Emsal:** Figma, Notion

#### ME-242 — Yazan kişi göstergesi (Typing indicator)

- **Tanım:** Yorum veya ileti alanında karşı tarafın yazma etkinliğini geçici gösterir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kim, nerede ve neyi değiştiriyor bilgisi kişisel veriyi aşmadan görünür; hareket yardımcı teknolojiye aşırı duyurulmaz.
- **Yaygın hata:** Varlığı kesin kilit gibi göstermek veya uzak değişiklikte kullanıcının yerel odağını sıçratmak.
- **Emsal:** Figma, Notion

### KAT-17-T02 — Çatışma ve iz

#### ME-243 — Eşzamanlı değişiklik uyarısı (Concurrent edit warning)

- **Tanım:** Yerel düzenleme sırasında kaynak veri değiştiğinde kullanıcıyı kaydetmeden önce uyarır.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Çatışan sürümler, yapan, zaman ve seçilebilir çözüm yolları karşılaştırmalı sunulur.
- **Yaygın hata:** Son yazanı sessizce kazandırmak veya kullanıcıya anlamadığı ham sürüm bilgisi göstermek.
- **Emsal:** Figma, Airtable

#### ME-244 — Sürüm karşılaştırma (Version comparison)

- **Tanım:** Çatışan eski, yerel ve uzak değerleri anlaşılır biçimde karşılaştırır.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Çatışan sürümler, yapan, zaman ve seçilebilir çözüm yolları karşılaştırmalı sunulur.
- **Yaygın hata:** Son yazanı sessizce kazandırmak veya kullanıcıya anlamadığı ham sürüm bilgisi göstermek.
- **Emsal:** Figma, Airtable

#### ME-245 — Çatışma çözme (Conflict resolution)

- **Tanım:** Kullanıcının hangi değeri koruyacağını veya birleştireceğini açık seçimle belirler.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Çatışan sürümler, yapan, zaman ve seçilebilir çözüm yolları karşılaştırmalı sunulur.
- **Yaygın hata:** Son yazanı sessizce kazandırmak veya kullanıcıya anlamadığı ham sürüm bilgisi göstermek.
- **Emsal:** Figma, Airtable

#### ME-246 — Değişiklik atfı (Change attribution)

- **Tanım:** Değişikliğin kim tarafından ve ne zaman yapıldığını kayıtla ilişkilendirir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Çatışan sürümler, yapan, zaman ve seçilebilir çözüm yolları karşılaştırmalı sunulur.
- **Yaygın hata:** Son yazanı sessizce kazandırmak veya kullanıcıya anlamadığı ham sürüm bilgisi göstermek.
- **Emsal:** Figma, Airtable

#### ME-247 — Etkinlik akışı (Activity feed)

- **Tanım:** Kayıt üzerindeki önemli değişiklikleri kronolojik kanıt akışında gösterir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Çatışan sürümler, yapan, zaman ve seçilebilir çözüm yolları karşılaştırmalı sunulur.
- **Yaygın hata:** Son yazanı sessizce kazandırmak veya kullanıcıya anlamadığı ham sürüm bilgisi göstermek.
- **Emsal:** Figma, Airtable

#### ME-248 — Yinelenen eylem önleme (Duplicate action prevention)

- **Tanım:** Aynı işlemin eşzamanlı iki kez uygulanmasını tespit edip ikinci sonucu açıklar.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Çatışan sürümler, yapan, zaman ve seçilebilir çözüm yolları karşılaştırmalı sunulur.
- **Yaygın hata:** Son yazanı sessizce kazandırmak veya kullanıcıya anlamadığı ham sürüm bilgisi göstermek.
- **Emsal:** Figma, Airtable

## KAT-18 — Keşif, yardım ve işe alıştırma

Özelliği bağlam içinde keşfettirirken sık kullanılan işi engellememek.

### KAT-18-T01 — Bağlamsal öğrenme

#### ME-249 — Bağlamsal araç ipucu (Contextual tooltip)

- **Tanım:** Tanıdık olmayan denetimi hover ve odakta kısa metinle açıklar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-250 — Odak noktası (Coach mark)

- **Tanım:** Yeni ya da önemli bir özelliği tek hedefe bağlı geçici açıklamayla tanıtır.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-251 — Kılavuzlu boş durum (Guided empty state)

- **Tanım:** Boş yüzeyde amacı, örneği ve mümkün ilk adımı anlatır.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-252 — Aşamalı açıklama (Progressive disclosure)

- **Tanım:** İleri seçenekleri ihtiyaç doğana dek gizleyip erişilebilir açma yolu sağlar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-253 — Kısayol öğretimi (Shortcut education)

- **Tanım:** Fareyle yapılan eylemin ardından uygun klavye karşılığını ölçülü biçimde gösterir.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-254 — Yeni özellik rozeti (New feature badge)

- **Tanım:** Yeni işlevi geçici ve kapatılabilir bir etiketle işaretler.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-255 — Bir daha gösterme (Don't show again)

- **Tanım:** Tekrarlayan yardım için kullanıcıya kalıcı kapatma tercihi verir.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

#### ME-256 — Satır içi örnek (Inline example)

- **Tanım:** Beklenen girdi veya sonuç biçimini alanın yakınında gerçekçi örnekle açıklar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / klavye / otomatik
- **Beklenen geri bildirim:** Yardım hedefe bağlı, kapatılabilir ve tekrar erişilebilir olur; tamamlanma durumu saklanır.
- **Yaygın hata:** Her girişte aynı ipucunu zorlamak, hedefi kapatmak veya yalnız hover ile yardım sunmak.
- **Emsal:** Notion, Atlassian

## KAT-19 — İçe aktarma, pano ve veri bütünlüğü

Toplu veriyi yapıştırma veya dosyadan alma sırasında kapsam, eşleme ve hatayı yönetme.

### KAT-19-T01 — Önizleme ve eşleme

#### ME-257 — Tablo verisi yapıştır (Paste tabular data)

- **Tanım:** Satır ve sütun yapısını koruyan pano verisini tabloya alır.
- **Öğe tipi:** tablo, alan, belge, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kaynak sütun, hedef alan, örnek değer ve dönüşüm önizlemesi onaydan önce görünür.
- **Yaygın hata:** Başlıkları sessizce yanlış alana eşlemek veya yerel sayı/tarih biçimini varsaymak.
- **Emsal:** Airtable, ClickUp

#### ME-258 — Yapıştırma önizlemesi (Paste preview)

- **Tanım:** Toplu yapıştırmanın etkileyeceği hücre ve dönüşümleri uygulamadan gösterir.
- **Öğe tipi:** tablo, alan, belge, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kaynak sütun, hedef alan, örnek değer ve dönüşüm önizlemesi onaydan önce görünür.
- **Yaygın hata:** Başlıkları sessizce yanlış alana eşlemek veya yerel sayı/tarih biçimini varsaymak.
- **Emsal:** Airtable, ClickUp

#### ME-259 — Sütun eşleme (Column mapping)

- **Tanım:** Kaynak sütunları hedef alanlarla kullanıcı tarafından doğrulanabilir eşleştirir.
- **Öğe tipi:** tablo, alan, belge, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kaynak sütun, hedef alan, örnek değer ve dönüşüm önizlemesi onaydan önce görünür.
- **Yaygın hata:** Başlıkları sessizce yanlış alana eşlemek veya yerel sayı/tarih biçimini varsaymak.
- **Emsal:** Airtable, ClickUp

#### ME-260 — Tür dönüşümü önizlemesi (Type conversion preview)

- **Tanım:** Metin, sayı, tarih ve seçim dönüşümünü örnek satırlarla gösterir.
- **Öğe tipi:** tablo, alan, belge, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kaynak sütun, hedef alan, örnek değer ve dönüşüm önizlemesi onaydan önce görünür.
- **Yaygın hata:** Başlıkları sessizce yanlış alana eşlemek veya yerel sayı/tarih biçimini varsaymak.
- **Emsal:** Airtable, ClickUp

#### ME-261 — Yerel biçim algılama (Locale format detection)

- **Tanım:** Kaynak sayı ve tarih biçimini algılar, belirsizliği kullanıcıya sorar.
- **Öğe tipi:** tablo, alan, belge, sayfa
- **Tetik:** tık / klavye
- **Beklenen geri bildirim:** Kaynak sütun, hedef alan, örnek değer ve dönüşüm önizlemesi onaydan önce görünür.
- **Yaygın hata:** Başlıkları sessizce yanlış alana eşlemek veya yerel sayı/tarih biçimini varsaymak.
- **Emsal:** Airtable, ClickUp

### KAT-19-T02 — Doğrulama ve sonuç

#### ME-262 — Satır tür doğrulaması (Row validation)

- **Tanım:** Her satırı hedef alan kurallarına göre bağımsız doğrular.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kabul, ret, yinelenen ve düzeltilmesi gereken satırlar ayrı sayılır; indirilebilir hata listesi sağlanır.
- **Yaygın hata:** Hatalı satırları sessizce atlamak, tüm içe aktarmayı nedensiz reddetmek veya geri dönüş yolu vermemek.
- **Emsal:** Shopify, Airtable

#### ME-263 — Kısmi kabul (Partial import)

- **Tanım:** Geçerli satırları alırken reddedilen satırları ayrı ve düzeltilebilir tutar.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kabul, ret, yinelenen ve düzeltilmesi gereken satırlar ayrı sayılır; indirilebilir hata listesi sağlanır.
- **Yaygın hata:** Hatalı satırları sessizce atlamak, tüm içe aktarmayı nedensiz reddetmek veya geri dönüş yolu vermemek.
- **Emsal:** Shopify, Airtable

#### ME-264 — Yinelenen kayıt algılama (Duplicate detection)

- **Tanım:** Olası tekrarları eşleşme nedeni ve çözüm seçenekleriyle gösterir.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kabul, ret, yinelenen ve düzeltilmesi gereken satırlar ayrı sayılır; indirilebilir hata listesi sağlanır.
- **Yaygın hata:** Hatalı satırları sessizce atlamak, tüm içe aktarmayı nedensiz reddetmek veya geri dönüş yolu vermemek.
- **Emsal:** Shopify, Airtable

#### ME-265 — Kuru çalışma özeti (Dry-run summary)

- **Tanım:** Kalıcı yazmadan önce eklenecek, değişecek ve reddedilecek kayıtları sayar.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kabul, ret, yinelenen ve düzeltilmesi gereken satırlar ayrı sayılır; indirilebilir hata listesi sağlanır.
- **Yaygın hata:** Hatalı satırları sessizce atlamak, tüm içe aktarmayı nedensiz reddetmek veya geri dönüş yolu vermemek.
- **Emsal:** Shopify, Airtable

#### ME-266 — İçe aktarma hata raporu (Import error report)

- **Tanım:** Hatalı satırları kaynak satır numarası, alan ve gerekçeyle dışarı verir.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kabul, ret, yinelenen ve düzeltilmesi gereken satırlar ayrı sayılır; indirilebilir hata listesi sağlanır.
- **Yaygın hata:** Hatalı satırları sessizce atlamak, tüm içe aktarmayı nedensiz reddetmek veya geri dönüş yolu vermemek.
- **Emsal:** Shopify, Airtable

#### ME-267 — İçe aktarma ilerlemesi (Import progress)

- **Tanım:** Uzun içe aktarmanın gerçek aşamasını ve sonucunu izlenebilir kılar.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Kabul, ret, yinelenen ve düzeltilmesi gereken satırlar ayrı sayılır; indirilebilir hata listesi sağlanır.
- **Yaygın hata:** Hatalı satırları sessizce atlamak, tüm içe aktarmayı nedensiz reddetmek veya geri dönüş yolu vermemek.
- **Emsal:** Shopify, Airtable

## KAT-20 — Oturum, yetki ve güven

Kullanıcı kimliği, rol, hassas bilgi ve oturum değişimlerinde güven sınırını görünür tutma.

### KAT-20-T01 — Oturum sürekliliği

#### ME-268 — Oturum süresi uyarısı (Session timeout warning)

- **Tanım:** Oturum bitmeden kalan işlem ve güvenli uzatma seçeneğini bildirir.
- **Öğe tipi:** sayfa, alan
- **Tetik:** otomatik / tık / klavye
- **Beklenen geri bildirim:** Süre sonu, yeniden doğrulama ve korunacak taslak açıkça bildirilir; kullanıcı kaldığı bağlama döner.
- **Yaygın hata:** Oturumu uyarısız düşürmek, taslağı silmek veya yeniden girişten sonra yanlış bağlama göndermek.
- **Emsal:** Stripe, Shopify

#### ME-269 — Yeniden doğrulama (Reauthentication)

- **Tanım:** Hassas eylem için bağlam ve taslağı koruyarak kimliği yeniden doğrulatır.
- **Öğe tipi:** sayfa, alan
- **Tetik:** otomatik / tık / klavye
- **Beklenen geri bildirim:** Süre sonu, yeniden doğrulama ve korunacak taslak açıkça bildirilir; kullanıcı kaldığı bağlama döner.
- **Yaygın hata:** Oturumu uyarısız düşürmek, taslağı silmek veya yeniden girişten sonra yanlış bağlama göndermek.
- **Emsal:** Stripe, Shopify

#### ME-270 — Oturum sonrası bağlama dön (Return after sign-in)

- **Tanım:** Başarılı girişten sonra kullanıcıyı izinli önceki iş bağlamına döndürür.
- **Öğe tipi:** sayfa, alan
- **Tetik:** otomatik / tık / klavye
- **Beklenen geri bildirim:** Süre sonu, yeniden doğrulama ve korunacak taslak açıkça bildirilir; kullanıcı kaldığı bağlama döner.
- **Yaygın hata:** Oturumu uyarısız düşürmek, taslağı silmek veya yeniden girişten sonra yanlış bağlama göndermek.
- **Emsal:** Stripe, Shopify

#### ME-271 — Oturum sonu taslak koruması (Draft preservation on timeout)

- **Tanım:** Süre sonu sırasında girilmiş verinin kayıp durumunu açık politikayla yönetir.
- **Öğe tipi:** sayfa, alan
- **Tetik:** otomatik / tık / klavye
- **Beklenen geri bildirim:** Süre sonu, yeniden doğrulama ve korunacak taslak açıkça bildirilir; kullanıcı kaldığı bağlama döner.
- **Yaygın hata:** Oturumu uyarısız düşürmek, taslağı silmek veya yeniden girişten sonra yanlış bağlama göndermek.
- **Emsal:** Stripe, Shopify

### KAT-20-T02 — Yetki ve hassas bilgi

#### ME-272 — Etkin rol göstergesi (Active role indicator)

- **Tanım:** Kullanıcının geçerli yetki bağlamını yanlış hesapla işlem yapmayı önleyecek şekilde gösterir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** Etkin rol, maskeleme, erişim engeli ve dış bağlantı sınırı metinsel olarak açıklanır.
- **Yaygın hata:** Yetkisiz veriyi önce yükleyip sonra gizlemek veya maskeli değeri panoya açık kopyalamak.
- **Emsal:** Stripe, Shopify

#### ME-273 — Yetki reddi açıklaması (Permission denied explanation)

- **Tanım:** Erişilemeyen eylem ya da verinin nedenini güvenli ayrıntı düzeyinde açıklar.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** Etkin rol, maskeleme, erişim engeli ve dış bağlantı sınırı metinsel olarak açıklanır.
- **Yaygın hata:** Yetkisiz veriyi önce yükleyip sonra gizlemek veya maskeli değeri panoya açık kopyalamak.
- **Emsal:** Stripe, Shopify

#### ME-274 — Hassas değeri göster (Reveal sensitive value)

- **Tanım:** Maskelenmiş bilgiyi açık kullanıcı eylemi ve izin kontrolüyle geçici gösterir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** Etkin rol, maskeleme, erişim engeli ve dış bağlantı sınırı metinsel olarak açıklanır.
- **Yaygın hata:** Yetkisiz veriyi önce yükleyip sonra gizlemek veya maskeli değeri panoya açık kopyalamak.
- **Emsal:** Stripe, Shopify

#### ME-275 — Maskeli kopyalama (Masked copy)

- **Tanım:** Hassas alanın pano çıktısını görünür maskeleme ve yetki kuralıyla uyumlu tutar.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** Etkin rol, maskeleme, erişim engeli ve dış bağlantı sınırı metinsel olarak açıklanır.
- **Yaygın hata:** Yetkisiz veriyi önce yükleyip sonra gizlemek veya maskeli değeri panoya açık kopyalamak.
- **Emsal:** Stripe, Shopify

#### ME-276 — Dış bağlantı işareti (External link indicator)

- **Tanım:** Kullanıcının uygulama ve güven alanı dışına çıkacağını bağlantıdan önce belirtir.
- **Öğe tipi:** alan, satır, sayfa, belge
- **Tetik:** tık / otomatik
- **Beklenen geri bildirim:** Etkin rol, maskeleme, erişim engeli ve dış bağlantı sınırı metinsel olarak açıklanır.
- **Yaygın hata:** Yetkisiz veriyi önce yükleyip sonra gizlemek veya maskeli değeri panoya açık kopyalamak.
- **Emsal:** Stripe, Shopify

## KAT-21 — Yerelleştirme ve veri sunumu

Dil, sayı, para, tarih, saat dilimi ve yazı yönündeki farkları anlam kaybetmeden yönetme.

### KAT-21-T01 — Yerel biçim ve zaman

#### ME-277 — Yerel sayı biçimi (Localized number format)

- **Tanım:** Sayıyı seçilen yerelin ayraçlarıyla gösterirken ham değeri korur.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Gösterim biçimi, birim, para birimi ve saat dilimi gerektiğinde değerin yanında görünür.
- **Yaygın hata:** Yerel biçimi veri değerine dönüştürmek, saat dilimini gizlemek veya ondalık ayıracı yanlış yorumlamak.
- **Emsal:** Stripe, Shopify

#### ME-278 — Para birimini açık göster (Explicit currency display)

- **Tanım:** Tutarın para birimini simge belirsizliğini giderecek kod veya adla belirtir.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Gösterim biçimi, birim, para birimi ve saat dilimi gerektiğinde değerin yanında görünür.
- **Yaygın hata:** Yerel biçimi veri değerine dönüştürmek, saat dilimini gizlemek veya ondalık ayıracı yanlış yorumlamak.
- **Emsal:** Stripe, Shopify

#### ME-279 — Yerel tarih biçimi (Localized date format)

- **Tanım:** Tarihi yerel sırada gösterirken makine değerini değişmeden tutar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Gösterim biçimi, birim, para birimi ve saat dilimi gerektiğinde değerin yanında görünür.
- **Yaygın hata:** Yerel biçimi veri değerine dönüştürmek, saat dilimini gizlemek veya ondalık ayıracı yanlış yorumlamak.
- **Emsal:** Stripe, Shopify

#### ME-280 — Saat dilimi etiketi (Time-zone label)

- **Tanım:** Zamana duyarlı değerin hangi saat diliminde gösterildiğini belirtir.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Gösterim biçimi, birim, para birimi ve saat dilimi gerektiğinde değerin yanında görünür.
- **Yaygın hata:** Yerel biçimi veri değerine dönüştürmek, saat dilimini gizlemek veya ondalık ayıracı yanlış yorumlamak.
- **Emsal:** Stripe, Shopify

#### ME-281 — Göreli/mutlak zaman değişimi (Relative/absolute time toggle)

- **Tanım:** Göreli zamanı kesin zaman damgasıyla isteğe bağlı karşılaştırır.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Gösterim biçimi, birim, para birimi ve saat dilimi gerektiğinde değerin yanında görünür.
- **Yaygın hata:** Yerel biçimi veri değerine dönüştürmek, saat dilimini gizlemek veya ondalık ayıracı yanlış yorumlamak.
- **Emsal:** Stripe, Shopify

#### ME-282 — Birim dönüştürme göstergesi (Unit conversion indicator)

- **Tanım:** Dönüştürülmüş ölçünün kaynak birim ve dönüşüm durumunu açıklar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Gösterim biçimi, birim, para birimi ve saat dilimi gerektiğinde değerin yanında görünür.
- **Yaygın hata:** Yerel biçimi veri değerine dönüştürmek, saat dilimini gizlemek veya ondalık ayıracı yanlış yorumlamak.
- **Emsal:** Stripe, Shopify

### KAT-21-T02 — Dil ve metin yönü

#### ME-283 — İçerik dili işareti (Content language marker)

- **Tanım:** Metin parçasının dilini yardımcı teknoloji ve yazım araçları için tanımlar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Dil ve yazı yönü içerik düzeyinde doğru uygulanır; kısaltma ve taşma bilgi kaybetmez.
- **Yaygın hata:** Karışık dilli metni tek yönde zorlamak veya çevrilmeyen sabitleri yanlış dönüştürmek.
- **Emsal:** Notion, Gmail

#### ME-284 — Çift yönlü metin desteği (Bidirectional text support)

- **Tanım:** Sağdan sola ve soldan sağa parçaları aynı alanda okunabilir sırada tutar.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Dil ve yazı yönü içerik düzeyinde doğru uygulanır; kısaltma ve taşma bilgi kaybetmez.
- **Yaygın hata:** Karışık dilli metni tek yönde zorlamak veya çevrilmeyen sabitleri yanlış dönüştürmek.
- **Emsal:** Notion, Gmail

#### ME-285 — Çevrilmeyen sabit koruması (Non-translatable token preservation)

- **Tanım:** Marka, kod ve kimlik gibi sabitleri yerelleştirme sırasında değişmeden korur.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Dil ve yazı yönü içerik düzeyinde doğru uygulanır; kısaltma ve taşma bilgi kaybetmez.
- **Yaygın hata:** Karışık dilli metni tek yönde zorlamak veya çevrilmeyen sabitleri yanlış dönüştürmek.
- **Emsal:** Notion, Gmail

#### ME-286 — Yerel sıralama (Locale-aware sorting)

- **Tanım:** Metin sırasını etkin dilin karşılaştırma kurallarına göre uygular.
- **Öğe tipi:** alan, satır, tablo, sayfa, belge
- **Tetik:** otomatik
- **Beklenen geri bildirim:** Dil ve yazı yönü içerik düzeyinde doğru uygulanır; kısaltma ve taşma bilgi kaybetmez.
- **Yaygın hata:** Karışık dilli metni tek yönde zorlamak veya çevrilmeyen sabitleri yanlış dönüştürmek.
- **Emsal:** Notion, Gmail

## KAT-22 — Uyarlanabilir yüzey ve giriş

Farklı ekran, işaretçi ve dokunma koşullarında aynı görevin erişilebilir kalması.

### KAT-22-T01 — Duyarlı davranış

#### ME-287 — Duyarlı eylem yoğunluğu (Responsive action density)

- **Tanım:** Dar alanda ikincil eylemleri erişilebilir taşma menüsüne aktarır.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Dar görünümde kaybolan öğeler erişilebilir alternatif yüzeye taşınır; temel bağlam korunur.
- **Yaygın hata:** Masaüstü eylemini mobilde sessizce kaldırmak veya tablo sütunlarını anlaşılmaz karta dönüştürmek.
- **Emsal:** Shopify, Gmail

#### ME-288 — Duyarlı tablo önceliği (Responsive table priority)

- **Tanım:** Dar görünümde sütunları açık öncelik ve ayrıntıya erişimle yönetir.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Dar görünümde kaybolan öğeler erişilebilir alternatif yüzeye taşınır; temel bağlam korunur.
- **Yaygın hata:** Masaüstü eylemini mobilde sessizce kaldırmak veya tablo sütunlarını anlaşılmaz karta dönüştürmek.
- **Emsal:** Shopify, Gmail

#### ME-289 — Dokunmada kalıcı satır eylemi (Persistent actions on touch)

- **Tanım:** Hover bulunmayan girişte gerekli satır eylemlerini görünür veya menüden erişilebilir tutar.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Dar görünümde kaybolan öğeler erişilebilir alternatif yüzeye taşınır; temel bağlam korunur.
- **Yaygın hata:** Masaüstü eylemini mobilde sessizce kaldırmak veya tablo sütunlarını anlaşılmaz karta dönüştürmek.
- **Emsal:** Shopify, Gmail

#### ME-290 — Giriş yöntemini değiştirme (Input modality switch)

- **Tanım:** Fare, dokunma ve klavye arasında geçişte odak ve seçim durumunu korur.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Dar görünümde kaybolan öğeler erişilebilir alternatif yüzeye taşınır; temel bağlam korunur.
- **Yaygın hata:** Masaüstü eylemini mobilde sessizce kaldırmak veya tablo sütunlarını anlaşılmaz karta dönüştürmek.
- **Emsal:** Shopify, Gmail

#### ME-291 — Yön değişiminde durum koruma (Orientation state preservation)

- **Tanım:** Görünüm boyutu değiştiğinde açık kayıt, filtre ve taslağı korur.
- **Öğe tipi:** tablo, sayfa, belge
- **Tetik:** otomatik / tık
- **Beklenen geri bildirim:** Dar görünümde kaybolan öğeler erişilebilir alternatif yüzeye taşınır; temel bağlam korunur.
- **Yaygın hata:** Masaüstü eylemini mobilde sessizce kaldırmak veya tablo sütunlarını anlaşılmaz karta dönüştürmek.
- **Emsal:** Shopify, Gmail

### KAT-22-T02 — İşaretçi ve hedef güvenliği

#### ME-292 — İşaretçi iptali (Pointer cancellation)

- **Tanım:** Eylemi basma anında değil güvenli bırakma anında çalıştırıp vazgeçme olanağı verir.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / sürükle
- **Beklenen geri bildirim:** Etkin hedef durumu görünür; yanlış basma, sürükleme ve hover kaybı geri döndürülebilir olur.
- **Yaygın hata:** Küçük hedefleri bitişik yerleştirmek, hover kartını işaretçi geçişinde kapatmak veya pointer-up öncesi eylem çalıştırmak.
- **Emsal:** Apple, W3C

#### ME-293 — Hover içeriğinde kalıcılık (Hover content persistence)

- **Tanım:** Hover ile açılan içeriği işaretçi üzerine taşındığında açık tutar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / sürükle
- **Beklenen geri bildirim:** Etkin hedef durumu görünür; yanlış basma, sürükleme ve hover kaybı geri döndürülebilir olur.
- **Yaygın hata:** Küçük hedefleri bitişik yerleştirmek, hover kartını işaretçi geçişinde kapatmak veya pointer-up öncesi eylem çalıştırmak.
- **Emsal:** Apple, W3C

#### ME-294 — Hover içeriğini kapat (Dismiss hover content)

- **Tanım:** Ek hover içeriğini odağı taşımadan kapatma yolu sağlar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / sürükle
- **Beklenen geri bildirim:** Etkin hedef durumu görünür; yanlış basma, sürükleme ve hover kaybı geri döndürülebilir olur.
- **Yaygın hata:** Küçük hedefleri bitişik yerleştirmek, hover kartını işaretçi geçişinde kapatmak veya pointer-up öncesi eylem çalıştırmak.
- **Emsal:** Apple, W3C

#### ME-295 — Hedef çakışmasını önle (Target separation)

- **Tanım:** Bitişik eylem hedeflerini yanlış etkinleştirmeyi azaltacak biçimde ayırır.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / sürükle
- **Beklenen geri bildirim:** Etkin hedef durumu görünür; yanlış basma, sürükleme ve hover kaybı geri döndürülebilir olur.
- **Yaygın hata:** Küçük hedefleri bitişik yerleştirmek, hover kartını işaretçi geçişinde kapatmak veya pointer-up öncesi eylem çalıştırmak.
- **Emsal:** Apple, W3C

#### ME-296 — Uzun basma alternatifi (Long-press alternative)

- **Tanım:** Bağlam menüsü gibi uzun basma eylemine görünür düğme veya menü yolu da sağlar.
- **Öğe tipi:** alan, satır, tablo, sayfa
- **Tetik:** hover / tık / sürükle
- **Beklenen geri bildirim:** Etkin hedef durumu görünür; yanlış basma, sürükleme ve hover kaybı geri döndürülebilir olur.
- **Yaygın hata:** Küçük hedefleri bitişik yerleştirmek, hover kartını işaretçi geçişinde kapatmak veya pointer-up öncesi eylem çalıştırmak.
- **Emsal:** Apple, W3C
