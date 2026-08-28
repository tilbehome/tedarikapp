# TedarikApp V3-M — Biçim Kuralları

**Sürüm:** `v3-m-format-rules/1.0.0`  
**Tarih:** 26 Ağustos 2026  
**Diller:** TR, EN, ZH  
**Kapsam:** Excel, PDF, CSV, paylaşım sayfası ve kanal metinlerinin görünür tarih/saat/para/sayı/ayraç/sıralama/font davranışı.

## 1. Kanıt ve karar sınıfları

| Sınıf | Anlam |
|---|---|
| `BAĞLAYICI` | 5B, KT-032..038, K55 veya görev metninde açıkça istenen kural |
| `V3-M VARSAYILANI` | Kaynakta kesin biçimi bulunmayan fakat vektörleri deterministik yapmak için bu pakette önerilen profil; PM onayı gerekir |
| `5B'YE ADAY` | Görünür metin gerekir fakat 5B anahtarı yoktur; bu paket yeni terim açmaz |
| `KANITLANMADI` | Kaynak iddiası bulunamadı; gerçekmiş gibi uygulanmaz |

K55 özgün kaynak davranışı ve sıfır karışık dil bağlayıcıdır. K57/K61 numara eşlemesi **kanıtlanmadı**. Aşağıdaki tarih deseni, sembol konumu, CSV delimiter ve collation ayrıntıları `V3-M VARSAYILANI`dır; PM bunları değiştirirse DL vektörleri aynı sürümde birlikte güncellenir.

## 2. Locale profilleri

| Alan | TR (`tr-TR`) | EN (`en`) | ZH (`zh-Hans-CN`) | Durum |
|---|---|---|---|---|
| Ondalıklı sayı | `1.234,56` | `1,234.56` | `1,234.56` | TR/EN/ZH biçim şartı bağlayıcı |
| Tam sayı | `1.234` | `1,234` | `1,234` | V3-M varsayılanı |
| Yüzde | `%12,50` | `12.50%` | `12.50%` | V3-M varsayılanı |
| Tarih | `26.08.2026` | `26 Aug 2026` | `2026年8月26日` | V3-M varsayılanı; ZH'de TR ay adı yasak |
| Saat | `14:05` | `14:05` | `14:05` | V3-M varsayılanı; 24 saat |
| Tarih-saat | `26.08.2026 14:05` | `26 Aug 2026 14:05` | `2026年8月26日 14:05` | V3-M varsayılanı |
| TRY | `₺1.234,56` | `TRY 1,234.56` | `TRY 1,234.56` | V3-M varsayılanı; EN'de TR nokta/virgül biçimi yasak |
| USD | `$1.234,56` | `USD 1,234.56` | `US$1,234.56` | V3-M varsayılanı |
| CNY tablo/hücre | `¥1.234,56` | `CNY 1,234.56` | `¥1,234.56` | ZH 元/¥ kuralı bağlayıcı |
| CNY cümle içinde | `1.234,56 CNY` | `CNY 1,234.56` | `1,234.56 元` | ZH 元/¥ kuralı bağlayıcı |
| Eksik alan | `—` | `—` | `—` | Görev #22 bağlayıcı; 5B'ye aday |
| CSV delimiter | `;` | `,` | `,` | V3-M varsayılanı |
| Metin collation | `tr-TR` | `en` | `zh-Hans-CN` | V3-M varsayılanı |

### ZH `元` / `¥` kuralı

1. Tablo, Excel/PDF hücresi veya kompakt KPI: `¥1,234.56`.
2. Doğal dil cümlesi: `1,234.56 元`.
3. Aynı değerde hem `¥` hem `元` yazılmaz.
4. Para birimi CNY değilse `¥/元` kullanılmaz; TRY ve USD kendi biçimindedir.
5. Ham tutar Decimal kalır; sembol/veri metne birleştirilip hesap yapılmaz.

## 3. Tarih ve saat

- Kaynak zaman damgası timezone bilgisiyle saklanır; görünür çıktı seçilen rapor timezone'una dönüştürülür. Bu paketin örnek timezone'u `Europe/Istanbul`dır.
- Tarih sıralaması ISO/epoch ham değerle yapılır; `26.08.2026` gibi render edilmiş dizeyle yapılmaz.
- ZH render'da `Ocak..Aralık` veya `January..December` ay adı bulunması dil sızıntısıdır.
- EN için ay adı İngilizce kısa addır; `26 Aug 2026` ay/gün belirsizliğini önler.
- Saat tüm dillerde `HH:mm`; saniye gerekiyorsa aynı belgede tutarlı `HH:mm:ss` kullanılır ve profil sürümü yazılır.
- Eksik tarih `01.01.1970`, `0000-00-00` veya bugün yapılmaz; `—` gösterilir.

## 4. Sayı, yüzde ve ölçü

- Hesap ham Decimal/integer üzerinde yapılır; biçim yalnız son render katmanındadır.
- Para iki ondalık, oran/yüzde ihtiyaç duyulan hassasiyetle fakat aynı raporda tutarlı gösterilir. Yuvarlama kuralı finans snapshot'ında saklanır; bu belge yeni finans yuvarlama kuralı açmaz.
- Gerçek `0`, `0,00`/`0.00` olarak gösterilir; eksik alan değildir.
- Negatif işaret sayının önündedir: TR `-1.234,56`, EN/ZH `-1,234.56`.
- Ölçü birimi (`kg`, `cm`, `ml`, `CBM`) kaynak sözleşmesine göre korunur; sayı locale'e göre biçimlenir.
- Ürün/model içindeki `550ml` K55 özgün satırında değiştirilmez; bu bir sunum sayısı değil kaynak metindir.

## 5. CSV

1. Kodlama `UTF-8 with BOM`; ZH karakterleri kayıpsızdır.
2. TR delimiter `;`; EN/ZH delimiter `,`.
3. Delimiter, çift tırnak veya satır sonu içeren alan RFC 4180'e göre çift tırnaklanır; iç tırnak iki kez yazılır.
4. EN/ZH `1,234.56` görünür para alanı comma içerdiğinden hücre `"TRY 1,234.56"` biçiminde quote edilir.
5. İlk satır yalnız seçilen dilde 5B `col.*` başlıklarıdır.
6. `null`, `undefined`, `NaN` yazılmaz; eksik veri `—`, gerçek sıfır `0`dır.
7. Formül enjeksiyonuna açık kullanıcı metni (`=`, `+`, `-`, `@` başlangıcı) veri güvenlik katmanında nötrlenir; bu işlem görünen sistem dilini değiştirmez.

## 6. Excel

- Sayı/para/tarih hücreleri mümkün olduğunca gerçek hücre tipidir; görünür number format locale profilini verir.
- Sütun başlığı 5B `col.*`; durum değeri 5B `status.*` olur.
- Worksheet adı veya print title için 5B karşılığı yoksa görünür aday olarak kayıt gerekir; sessiz TR sabiti kullanılamaz.
- K55 özgün satır `DM-016` için tam `高硼硅玻璃油壶 550ml`; çevrilmiş ürün adı ayrı hücrede.
- Formül sonuçları Excel/PDF eşdeğer veri snapshot'ından gelmelidir; locale yalnız biçimi değiştirir.

## 7. PDF ve üç satırlı kademeli başlık

Kademeli fiyat bölümü 7B'de kabul edilmiş üç semantik alanı taşır fakat bu alanlar 5B'de yoktur; bu nedenle metinler `5B'YE ADAY`dır:

| Satır | TR | EN | ZH | Kaynak |
|---:|---|---|---|---|
| 1 | Kademe başlangıç miktarı | Tier minimum quantity | 阶梯起订数量 | 7B `portal.field.tier_min_quantity` |
| 2 | Kademe bitiş miktarı | Tier maximum quantity | 阶梯最高数量 | 7B `portal.field.tier_max_quantity` |
| 3 | Kademe birim fiyatı | Tier unit price | 阶梯单价 | 7B `portal.field.tier_unit_price` |

- PDF'te üç mantıksal satır korunur; slash ile tek satıra birleştirme, bir satırı kesme veya yalnız ilk satırı bırakma kırmızıdır.
- Metin katmanı sırası 1→2→3 olmalı; görselde de üçü okunabilir olmalıdır.
- Satır sarması gerekirse bir mantıksal satır kendi içinde sarılabilir, fakat diğer satırla birleşmez.

## 8. Sıralama

1. Sayı/para/yüzde ham sayısal değerle; tarih ham timestamp ile sıralanır.
2. Görünür biçim (`1.234,56`) string olarak sıralanmaz.
3. Metin sırası locale collator ile: TR `tr-TR`, EN `en`, ZH `zh-Hans-CN`.
4. Collator eşitliğinde `source_id`, ardından satırın kalıcı kimliği bağlayıcı tie-breaker'dır.
5. Eksikler her iki yönde de sonda; `—` yalnız render değeridir.
6. Durumlar alfabetik çevrilmiş metinle sıralanmaz. İş akışının sürümlü durum sırası varsa o kullanılır; yoksa 5B dizi sırasının kanonik iş sırası olduğu **kanıtlanmadı**, PM kararı gerekir.
7. ZH collator yoksa sonuç metadata'sına fallback yazılarak deterministik Unicode code-point sırası uygulanabilir; bu ticari alfabe/pinyin sırası diye sunulmaz.

## 9. Noto Sans SC alt küme kapısı

### 9.1 Gerekli karakter kümesi

Her ZH PDF için gerekli küme render öncesi oluşturulur:

- o belgede kullanılan 5B ZH sistem metinleri;
- onaylı `5B'ye aday` ZH metinleri;
- dinamik ZH ürün/varyant/marka/malzeme/not verileri;
- K55 özgün kaynak metni;
- ASCII rakam/harf, boşluk ve görünür noktalama;
- `¥`, `元`, `₺`, `$`, `%`, `—`, `×`, parantez ve birim karakterleri.

Yalnız gerçekten kullanılan kod noktaları alt kümeye alınır. Sabit “yaygın Çince karakterler” listesi dinamik veri için yeterli kanıt sayılmaz.

### 9.2 cmap doğrulaması

1. Render metni Unicode code point kümesine ayrılır.
2. Noto Sans SC subset fontunun `cmap` tablosu okunur.
3. `required − covered` kümesi boş değilse ilk PDF yayımlanmaz.
4. Ligatür/variation selector ve supplementary-plane karakterler gerçek shaping motoruyla örnek render testinden de geçer.
5. PDF metin çıkarımında kaynak metin geri okunur; yalnız görüntüde kutu görünmemesi yeterli değildir.

### 9.3 Yedek kural

Kapsam dışı karakter bulunduğunda sıra:

1. Güvenilen tam Noto Sans SC kaynağından belgeye özgü subset yeniden üretilir.
2. Yeniden subset mümkün değilse paketli ve sürümü sabit tam `Noto Sans SC` veya onaylı `Noto Sans CJK SC` PDF'e gömülür.
3. İki yol da karakteri kapsamıyorsa çıktı `ZH_GLYPH_UNCOVERED` ile durdurulur.

İşletim sistemi fontuna sessiz düşme, ağdan font çekme ve tofu (`□`) ile devam etme yasaktır. Kullanılan font ailesi, sürüm, subset hash'i ve eksik-karakter sonucu belge üretim kanıtına yazılır.

## 10. Tek dil lint sırası

1. Görünür node/hücre/metin katmanı çıkarılır.
2. Her sistem öğesi 5B anahtarı veya açık aday kimliğiyle eşleştirilir.
3. Anahtarın seçili locale metniyle placeholder yapısı karşılaştırılır.
4. Diğer locale'in benzersiz sistem metni bulunursa hata verilir.
5. Orijinal kaynak allowlist'i alan yolu + rol + ürün kimliğiyle doğrulanır.
6. Tarih/sayı/para biçimleri locale profiline göre kontrol edilir.
7. `null/undefined/NaN`, bilinmeyen durum ve çözülmemiş `{placeholder}` taranır.
8. PDF'te glif kapsamı ve üç mantıksal kademeli satır ayrıca doğrulanır.

## 11. PM açık kararları

1. K57/K61 numaralarının kesin kaynak maddeleri.
2. V3-M varsayılanı tarih/saat deseni ve para sembol konumları.
3. CSV delimiter/BOM politikası.
4. 14 adet `5B'ye aday` görünür metnin kabulü veya mevcut 5B anahtarına bağlanması.
5. ZH fallback için tam font ailesi/sürümü ve lisans paketi.
6. İş akışı durumlarının sıralama önceliği; 5B dizi sırası otomatik kanonik sayılmaz.
