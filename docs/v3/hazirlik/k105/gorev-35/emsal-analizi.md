# Emsal Analizi — sekiz yoğun iş uygulaması

Bu analiz, ürünlerin resmi yardım ve tasarım belgelerinde gözlemlenebilen ayırt edici mikro etkileşimleri özetler. “Uyarlama” notları ekran tasarımı kararı değil, K105 matrisi değerlendirilirken korunması gereken davranış niteliğidir.

## 1. Linear

1. J/K ile liste odağı, X ile seçim ve Shift ile aralık seçimi fareye bağımlılığı azaltır.
2. Cmd/Ctrl+K seçili kayıt bağlamındaki komutları aynı palete taşır.
3. G ile başlayan ikili gezinme dizileri tek harf çakışmalarını azaltır.
4. Sağ tık ve taşma menüsü satır bağlamını korur.
5. Liste/board geçişi aynı iş kümesini farklı yoğunlukta gösterir.
6. Favoriler kişisel hızlı erişim ve sıralama hafızası sağlar.
7. Arama, sayfa içi bulma ile çalışma alanı aramasını farklı kısayol ve kapsamlarla ayırır.

**tedarikapp’e uyarlama notu:** Kısayol aileleri, seçimin görünür kapsamı ve aynı komutun palet/menü yollarında eş anlamlı olması alınabilir; hedef ekran ve G→harf eşlemesi PM kararı olarak kalmalıdır. Kaynaklar: [Select issues](https://linear.app/docs/select-issues), [Search](https://linear.app/docs/search), [Board layout](https://linear.app/docs/board-layout), [Favorites](https://linear.app/docs/favorites).

## 2. Notion

1. Cmd/Ctrl+K veya P araması sayfa ve komut keşfini merkezileştirir.
2. Slash komutları yazma bağlamında yapı ve eylem keşfi sağlar.
3. İç içe kenar çubuğu sürükle-sırala, aç/kapat ve favorileri bir araya getirir.
4. Markdown benzeri yazım kısayolları biçimlendirmeyi akış içinde yapar.
5. Geri/ileri gezinme uygulama içi sayfa geçmişini korur.
6. Sayfa bağlantıları ve blok bağlamı derin bağlantı davranışını destekler.

**tedarikapp’e uyarlama notu:** Bağlama duyarlı komut keşfi ve kişisel gezinme hafızası örnek alınabilir; belge düzenleyiciye özgü blok özellikleri katalogda emsal olarak kalmalı, özellik kararına dönüşmemelidir. Kaynaklar: [Keyboard shortcuts](https://www.notion.com/help/keyboard-shortcuts), [Sidebar](https://www.notion.com/help/navigate-with-the-sidebar), [Slash commands](https://www.notion.com/help/guides/using-slash-commands).

## 3. Airtable

1. Hücre odağı ile Enter/Escape düzenleme sözleşmesi tabloyu elektronik tablo hızına yaklaştırır.
2. Shift aralık ve Cmd/Ctrl ayrık seçim büyük veri kümelerinde hassas kapsam kurar.
3. Hücreler arasında kenara atlama ve genişletilmiş kayıt, yoğun tablo ile ayrıntıyı bağlar.
4. Kopyala/yapıştır davranışı satır-sütun yapısını korur.
5. Görünüm, alan ve sütun düzenleme tercihleri tablo bağlamını kişiselleştirir.
6. Geçersiz yapılandırılmış giriş, uygulanmadan önce satır içi hata verir.
7. Genişletilmiş kayıttan Escape ile tablo bağlamına dönüş sağlanır.

**tedarikapp’e uyarlama notu:** Hücre düzenleme kipinin görünür olması, çoklu seçim ve pano kapsamı K105 için güçlü emsaldir; elektronik tablo serbestliğinin iş kurallarını aşmasına izin verilmemelidir. Kaynaklar: [Keyboard shortcuts](https://support.airtable.com/articles/7980233311-airtable-keyboard-shortcuts), [Miscellaneous extensions](https://support.airtable.com/articles/1258690122-miscellaneous-airtable-extensions).

## 4. Stripe Dashboard

1. `?` ile kısayol listesinin açılması keşfedilebilirliği güçlendirir.
2. Arama ve hızlı gezinme, yüksek hacimli finansal kayıtlar arasında bağlam geçişini hızlandırır.
3. Kaynak türleri ve durumlar metinsel işaretlerle ayrılır.
4. Ayrıntı sayfaları kimlik, olay ve ilişkili kayıt bağlantılarını birlikte sunar.
5. Hassas iş akışlarında açık durum ve sonuç geri bildirimi güven sağlar.

**tedarikapp’e uyarlama notu:** Finansal veri yüzeylerindeki açık kapsam, durum ve hassas değer davranışları örnek alınabilir; Stripe’a özgü işlem modeli yeni özellik olarak taşınmamalıdır. Kaynak: [Dashboard basics](https://docs.stripe.com/dashboard/basics).

## 5. Figma

1. Canlı imleç ve varlık göstergeleri çok kullanıcılı bağlamı görünür kılar.
2. Cursor chat, işaretçi konumuna bağlı geçici iletişim sağlar.
3. Çoklu seçim ve sürükle-bırak doğrudan manipülasyonu ölçekler.
4. Tidy-up benzeri düzenleme eylemleri seçili kümenin yapısını görünür biçimde değiştirir.
5. Hızlı eylemler klavyeyle araç ve komut keşfi sağlar.
6. Ekran okuyucu duyuruları seçim ve nesne değişimlerini yardımcı teknolojiye taşır.

**tedarikapp’e uyarlama notu:** Varlık, uzak değişiklik vurgusu, sürükleme önizlemesi ve klavye eşdeğerliği alınabilir; sonsuz tuval veya tasarım aracı özelliği önerilmez. Kaynaklar: [Cursor chat](https://help.figma.com/hc/en-us/articles/4403130802199-Use-cursor-chat-in-Figma-Design), [FigJam guide](https://help.figma.com/hc/en-us/articles/1500004362321-Guide-to-FigJam), [Screen reader](https://help.figma.com/hc/en-us/articles/14477051168791-Use-FigJam-with-a-screen-reader).

## 6. Gmail

1. J/K gezinme, X seçim ve G ile başlayan diziler yüksek hacimli liste işlemeyi hızlandırır.
2. Toplu araç çubuğu seçim oluştuğunda bağlama göre değişir.
3. Z geri alma, arşivleme ve taşıma gibi eylemlerde hata kurtarma sağlar.
4. Sağ tık menüsü iletiye özgü ikincil eylemleri açar.
5. Okundu/okunmadı durumu hem satır görseli hem komutla yönetilir.
6. Smart Compose önerisi açık kullanıcı kabulüyle tamamlanır.
7. Seçim ve sayfa kapsamı ayrı davranışlarla ele alınır.

**tedarikapp’e uyarlama notu:** Liste klavye sözleşmesi, toplu araç çubuğu ve geri alma özellikle güçlü emsallerdir; posta kavramları kayıt yönetimine doğrudan kopyalanmamalıdır. Kaynaklar: [Keyboard shortcuts](https://support.google.com/mail/answer/6594?hl=en-GB), [Toolbar actions](https://support.google.com/mail/answer/2473038?hl=en-GB), [Smart Compose](https://support.google.com/mail/answer/9116836?hl=en-GB).

## 7. Shopify Admin

1. Toplu düzenleyici seçili ürünleri elektronik tablo benzeri yüzeyde değiştirir.
2. Sütun ekleme/çıkarma toplu işlem alanını kullanıcıya görünür kılar.
3. Aralık ve ayrık hücre seçimi büyük veri üzerinde kontrollü kapsam sağlar.
4. Doldurma tutamacı aynı değeri komşu hücrelere çoğaltır.
5. Hata göstergeleri sorunlu hücreleri bağlamında işaretler.
6. `?` yardım yüzeyi kısayolları keşfedilebilir kılar.
7. Polaris index table seçim, sıralama, filtreleme ve sayfalamayı ortak tablo sözleşmesinde toplar.

**tedarikapp’e uyarlama notu:** Toplu düzenleme kapsamının sütun ve seçimle açıkça kurulması örnek alınabilir; ürün kataloğuna özgü alanlar yeni kapsam olarak değerlendirilmemelidir. Kaynaklar: [Bulk editing](https://help.shopify.com/en/manual/shopify-admin/productivity-tools/bulk-editing), [Keyboard shortcuts](https://help.shopify.com/en/manual/shopify-admin/productivity-tools/keyboard-shortcuts), [Polaris index table](https://polaris.shopify.com/components/tables/index-table).

## 8. monday.com / ClickUp

1. monday.com hızlı arama ve çoklu sıralama yoğun pano/listelerde görünümü daraltır.
2. monday.com Shift aralık seçimi ve toplu işlem davranışı tekrarlı işi azaltır.
3. ClickUp Cmd/Ctrl+K komut merkezini arama ve eylem için ortaklaştırır.
4. ClickUp J/K ile bildirim gezintisi ve Escape ile kapanış sözleşmesi sunar.
5. ClickUp görünüm kısayolları liste, board ve takvim arasında doğrudan geçiş sağlar.
6. ClickUp Bulk Action Toolbar yalnız seçimden sonra geçerli ortak eylemleri gösterir.
7. Toplu taşıma, atama ve etiketleme ortak seçim kapsamını kullanır.
8. Her iki ürün de sık eylemleri klavye ve görünür araç çubuğu yollarıyla sunar.

**tedarikapp’e uyarlama notu:** Seçimden türeyen eylem çubuğu, çoklu sıralama ve komut merkezi yakınsaması alınabilir; pano hiyerarşileri ya da görev özellikleri tedarikapp’e özel karar gibi yazılmamalıdır. Kaynaklar: [monday.com shortcuts](https://support.monday.com/hc/en-us/articles/115005339905-monday-com-Shortcuts), [ClickUp shortcuts](https://help.clickup.com/hc/en-us/articles/6309030550167-Use-keyboard-shortcuts), [ClickUp bulk toolbar](https://help.clickup.com/hc/en-us/articles/6309768265495-Manage-tasks-with-the-Bulk-Action-Toolbar).
