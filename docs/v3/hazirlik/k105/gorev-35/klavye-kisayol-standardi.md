# Klavye Kısayol Standardı — K105 karar girdisi

## Amaç ve sınır

Bu belge sektör yakınsamalarını, Türkçe klavye risklerini ve çakışmasız bir atama çerçevesini verir. Belirli bir tedarikapp ekranına kısayol atamaz; nihai komut–ekran eşlemesi PM kapsamındadır. Kısayollar yalnız görüntüleme kipinde çalışır; metin, sayı, tarih ve zengin metin düzenleme kipleri önceliklidir.

## Sektör yakınsamaları

| Yakınsama | Yaygın anlam | Kanıt örnekleri | K105 yorumu |
|---|---|---|---|
| J / K | Liste odağını ileri / geri taşıma | Linear, Gmail, ClickUp | Düzenleme alanı dışındayken ve görünür odakla kullanılmalı. |
| Cmd/Ctrl+K | Komut paleti veya birleşik arama | Linear, Notion, ClickUp | Tarayıcı/işletim sistemi farkı açıkça yazılmalı; metin seçiliyken bağlantı ekleme gibi yerel bağlamlar öncelikli olabilir. |
| G→X | İkili gezinme dizisi | Linear, Gmail | G bir gezinme ön eki; ikinci tuş hedefin anımsatıcı harfi. Hedef listesi PM tarafından belirlenir. |
| ? | Kısayol yardımını açma | Stripe, Shopify | Türkçe yerleşimde üretilen karaktere göre algılanmalı ve paletten alternatif erişim bulunmalı. |
| Esc | En iç katmanı kapatma / düzenlemeyi iptal | Airtable, ClickUp, Radix, WAI-ARIA | Katman sırası: öneri → menü → popover → modal/drawer → seçim; kayıtsız değişiklik varsa uyarı araya girer. |
| Enter | Açma, düzenleme onayı veya güvenli form gönderimi | Airtable, WAI-ARIA | Anlam odağa ve kipe bağlıdır; çok satırlı metinde satır sonu önceliklidir. |
| Cmd/Ctrl+S | Açık kaydetme | Yerleşik masaüstü/web beklentisi | Otomatik kayıtta bile tarayıcının “sayfayı kaydet” davranışı engelleniyorsa görünür bir kayıt sonucu verilmelidir. |
| Cmd/Ctrl+Z | Son geri alınabilir eylem | Gmail, Linear, AG Grid | Geri alma yığını kapsamı görünür olmalı; metin düzenleyicinin yerel yığını önceliklidir. |
| Cmd/Ctrl+Shift+Z | Yinele | Notion, AG Grid ve masaüstü yakınsaması | Yalnız geri alınan, hâlâ geçerli eylem için etkin olmalı. |
| / | Arama ya da komut başlangıcı | Linear, ClickUp/Notion yazma bağlamı | Metin alanında karakter girişi; görüntüleme kipinde bağlamsal arama olabilir. |

Kaynaklar: [Linear](https://linear.app/docs/select-issues), [Notion](https://www.notion.com/help/keyboard-shortcuts), [Gmail](https://support.google.com/mail/answer/6594?hl=en-GB), [ClickUp](https://help.clickup.com/hc/en-us/articles/6309030550167-Use-keyboard-shortcuts), [Shopify](https://help.shopify.com/en/manual/shopify-admin/productivity-tools/keyboard-shortcuts), [Stripe](https://docs.stripe.com/dashboard/basics), [Radix Dialog](https://www.radix-ui.com/primitives/docs/components/dialog), [WAI-ARIA APG](https://www.w3.org/WAI/ARIA/apg/patterns/).

## Kip ve öncelik kuralları

1. **Metin girişi kipi:** Yazdırılabilir karakterler, oklar, Home/End, silme ve platform metin kısayolları alana aittir.
2. **Hücre düzenleme kipi:** Enter kaydeder, Escape iptal eder; çok satırlı alanın kendi satır sonu davranışı korunur.
3. **Liste gezinme kipi:** J/K odağı taşır, X seçimi değiştirir, Shift aralığı genişletebilir.
4. **Geçici yüzey kipi:** Escape yalnız en iç katmanı kapatır ve odağı tetikleyiciye döndürür.
5. **Modal form kipi:** Tek satırlı güvenli form dışında Enter gönderimi varsayılmaz; yıkıcı birincil eylem yalnız kısayolla tetiklenmez.
6. **Tarayıcı çakışması:** Uygulama bir tarayıcı/işletim sistemi kısayolunu ele geçirirse sonuç ve alternatif yardım yüzeyinde açıkça belirtilir.

## Türkçe klavye ve yerel düzen riskleri

| Risk | Neden | Çakışmasız yaklaşım |
|---|---|---|
| İ / I / i / ı | Türkçe büyük-küçük harf eşleme kuralları İngilizceden farklıdır. | Komut kimliğini harften ayır; gösterilen kısayolu üretilen karakter ve fiziksel yerleşim testleriyle doğrula. G→I benzeri atamalarda dört biçimi birlikte test et. |
| AltGr | Türkçe Q/F düzenlerinde bazı noktalama ve semboller AltGr katmanındadır; tarayıcı ve işletim sistemiyle çakışabilir. | AltGr içeren uygulama kısayolu atama; sembol kısayollarına komut paleti alternatifi ver. |
| ? ve / | Noktalama karakterlerinin tuş konumu ve gerekli değiştiricisi yerleşime göre değişir. | Fiziksel tuş konumuna değil üretilen karaktere göre davran; yardımda gerçek yerel tuş gösterimini sun. |
| Tek harf komutları | Kullanıcı metin alanı, arama veya IME içindeyken veri girişini çalabilir. | Yalnız görüntüleme kipinde etkinleştir; düzenleme kipinde bastırma ve görünür kip göstergesi kullan. |
| İşletim sistemi/tarayıcı | Cmd/Ctrl+L, W, T, R, P, F gibi birleşimler yerleşik davranışlara sahiptir. | Yerleşik komutları yeniden atama; gerekiyorsa komut paletinden aynı eyleme erişim sağla. |
| Ekran okuyucu katmanı | Yardımcı teknoloji kendi gezinme tuşlarını tüketebilir. | Her kısayolun odaklanabilir denetim yolu olsun; tek harfi hiçbir zaman tek erişim yolu yapma. |

## Çakışmasız önerilen aile tablosu

| Aile | Önerilen tuş | Soyut eylem | Geçerlilik koşulu | Alternatif |
|---|---|---|---|---|
| Keşif | Cmd/Ctrl+K | Komut paletini aç | Metin düzenleme dışı; bağlantı düzenleme bağlamı yok | Görünür “Komutlar” düğmesi |
| Yardım | ? | Kısayol listesini aç | Yazma kipi dışı | Palet içinde “Klavye kısayolları” |
| Liste odağı | J / K | Sonraki / önceki kayıt | Liste odakta, alan düzenlenmiyor | Ok tuşları ve tıklama |
| Seçim | X veya Space | Odaktaki satırı seç / bırak | Satır odakta; Space sayfayı kaydırmayacak şekilde bileşen bağlamında | Seçim kutusu |
| Aralık | Shift+J / Shift+K veya Shift+tık | Seçim aralığını genişlet | Başlangıç seçimi var | İki uçlu seçim kutusu akışı |
| Gezinme | G→anlamlı harf | Hedef bağlama git | İlk G sonrası kısa, görünür bekleme; metin düzenleme dışı | Komut paleti / gezinme menüsü |
| Aç / onayla | Enter | Odaktaki kaydı aç veya alanı kaydet | Kip görünür ve tek anlamlı | Birincil düğme |
| İptal / kapat | Esc | En iç katmanı kapat veya düzenlemeyi iptal | Kayıtsız değişiklikte uyarı | Görünür kapat/iptal düğmesi |
| Kaydet | Cmd/Ctrl+S | Açık kaydetme niyeti | Taslak ya da açık kayıt sözleşmesi var | Kaydet düğmesi / otomatik kayıt durumu |
| Geri al | Cmd/Ctrl+Z | Son geçerli eylemi geri al | Yerel metin yığını varsa o öncelikli | Undo toast / menü |
| Yinele | Cmd/Ctrl+Shift+Z | Geri alınan eylemi yinele | Yineleme yığını geçerli | Menü |
| Bağlamsal menü | Shift+F10 | Odaktaki nesnenin menüsünü aç | Nesne menüsü var | Taşma düğmesi / sağ tık |

## Kabul testleri

- Aynı kısayol aynı kipte iki komuta atanmaz.
- Yazdırılabilir tek harfler metin, arama, sayı, tarih, seçim listesi ve IME girdisini kesmez.
- Her kısayolun görünür denetim veya komut paleti alternatifi vardır.
- Odak, seçim ve kip değişimi yalnız renkle değil metin/şekil ve yardımcı teknoloji duyurusuyla anlaşılır.
- TR-Q, TR-F, EN-US; Windows, macOS; temel ekran okuyucu kiplerinde çakışma testi yapılır.
- Kısayol yardımında işletim sistemine ve yerel düzene uygun gösterim kullanılır.
