# TedarikApp Eklenti v2 — E2E Senaryo Kataloğu

**Belge türü:** Claude Code tarafından otomasyon koduna dönüştürülecek test kitabı  
**Kapsam:** 1688 sayfa içi, kullanıcı-tetiklemeli ürün yakalama arayüzü  
**Tarih:** 22 Ağustos 2026  
**Çalışma sınırı:** Bu belge hazırlanırken repo yalnız okundu; koda, dala, PR’a veya canlı 1688 sayfasına yazılmadı/istek atılmadı.

## 1. Kaynak ve otomasyon kararı

Bu katalog aşağıdaki mevcut sözleşmeleri birleştirir:

- `tedarikapp-1688-sayfa-ici-eklenti-ui-uygulama-raporu.md`: 10 görünür durum, geçişler ve 16 kabul kriteri;
- `1688-867966081795-eklenti-veri-raporu.md`: fiyat, SKU, MOQ, sevk, satıcı ve medya doğruluk kuralları;
- Görev #1’deki sekiz sanitize parser fikstürü;
- repodaki `e2e/` düzeni: Playwright `1.56`, Chromium, `tr-TR`, `Europe/Istanbul`, tek worker, gerçek MySQL ve hata hâlinde trace/screenshot;
- `e2e/tests/04-gelen-kutusu.spec.ts`: Bearer ile gerçek `/api/capture` v2 sözleşmesinin panel tarafında uçtan uca sınanması.

Mevcut repo README’si MV3 uzantısının gerçek tarayıcı yükleme yaşam döngüsünü bilinçli olarak otomatik E2E dışında tutmaktadır. Bu nedenle üç test sınıfı kullanılmalıdır:

| Sınıf | Ne test edilir? | 1688 ağına istek |
|---|---|---:|
| **Otomatik — sahte sayfa** | Shadow DOM UI, parser çıktısı, durum/geçiş, ağ stub’ı, erişilebilirlik | Hayır |
| **Otomatik — panel/API** | Gerçek `/api/capture`, idempotency, mükerrer ve panel bağlantıları; CI MySQL üzerinde | Hayır |
| **Manuel — paketlenmiş uzantı** | Host eşleşmesi, gerçek Chrome uzantı yüklemesi, gerçek sayfadaki konum/CSS uyumu | Yalnız Ürün Sahibi’nin kendi tıklamasıyla |

Sahte sayfa otomasyonu gerçek 1688 URL’sine çıkmamalıdır. İstek, Playwright tarafından daha ağ çıkışı olmadan `route.fulfill` benzeri bir mekanizmayla yerel fixture HTML’i olarak karşılanmalı veya tamamen yerel bir harness kullanılmalıdır. Testte özel MTop çağrısı, cookie, imza veya token üretimi yasaktır.

## 2. Durum sözlüğü

| Kod | UI durumu | Zorunlu görünür metin/işaret | Ağ ilkesi |
|---|---|---|---|
| D1 | Kapalı | `TedarikApp’e Ekle` | Ağır parser ve `/api/capture` yok |
| D2 | Okunuyor | `Veriler okunuyor…` | 1688’e yeni istek ve `/api/capture` yok |
| D3 | Başarılı okuma / Önizleme | `Ürün verileri bulundu` | Gönderim onayına kadar `/api/capture` yok |
| D4 | Kısmi okuma | `Bazı bilgiler eksik` | Gönderim onayına kadar `/api/capture` yok |
| D5 | Okuma hatası | `Ürün verileri alınamadı` | TedarikApp gönderim isteği yok |
| D6 | Gönderiliyor | `Gönderiliyor…` | Tek gönderim isteği |
| D7 | Gönderildi | `TedarikApp’e gönderildi` | Başarılı isteğin ardından ek POST yok |
| D8 | Mükerrer | `Ürün zaten listede` | Sessiz ikinci kayıt yok |
| D9 | Yetki hatası | `TedarikApp bağlantısı gerekli` | 401/403 sonrası otomatik sonsuz tekrar yok |
| D10 | Sunucu hatası | `TedarikApp şu anda yanıt vermiyor` | Veri korunur; kullanıcı komutuna kadar tekrar yok |

## 3. Durum/geçiş kapsam tablosu

| Başlangıç | Tetik | Hedef | Kanıtlayan senaryo |
|---|---|---|---|
| Başlangıç | Desteklenen ürün sayfası açılır | D1 Kapalı | E2E-EKL-02 |
| Başlangıç | Desteklenmeyen sayfa açılır | UI yok | E2E-EKL-01 |
| D1 Kapalı | İlk kullanım ve düğme tıklaması | Disclosure | E2E-EKL-24 |
| D1 Kapalı | Onaylı kullanıcı düğmeye basar | D2 Okunuyor | E2E-EKL-03 |
| D2 Okunuyor | Aynı düğmeye tekrar basılır | D2 Okunuyor | E2E-EKL-04 |
| D2 Okunuyor | Tam veri bulunur | D3 Önizleme | E2E-EKL-05 |
| D2 Okunuyor | Eksik alanlarla veri bulunur | D4 Kısmi | E2E-EKL-10 |
| D2 Okunuyor | Parser başarısız olur | D5 Okuma hatası | E2E-EKL-11 |
| D4 Kısmi | Kullanıcı eksikleri görüp devam eder | D3 Önizleme | E2E-EKL-10 |
| D5 Okuma hatası | Tekrar tara | D2 Okunuyor → D3 | E2E-EKL-11 |
| D3 Önizleme | Gönder | D6 Gönderiliyor | E2E-EKL-12 |
| D6 Gönderiliyor | API 201/200 | D7 Gönderildi | E2E-EKL-13 |
| D6 Gönderiliyor | Ağ kopar | D10 Sunucu hatası | E2E-EKL-14 |
| D6 Gönderiliyor | HTTP 502 | D10 Sunucu hatası | E2E-EKL-15 |
| D10 Sunucu hatası | Tekrar gönder | D6 → D7 | E2E-EKL-15 |
| D6 Gönderiliyor | API mükerrer yanıtı | D8 Mükerrer | E2E-EKL-16 |
| D8 Mükerrer | Mevcut ürünü aç | Mevcut kayıt görünümü | E2E-EKL-17 |
| D8 Mükerrer | Başka listeye ekle | D6 → D7 | E2E-EKL-18 |
| D8 Mükerrer | Mevcut kaydı güncelle | D6 → D7 | E2E-EKL-19 |
| D8 Mükerrer | İptal | D3 Önizleme | E2E-EKL-20 |
| D6 Gönderiliyor | API 401/403 | D9 Yetki hatası | E2E-EKL-21 |
| D9 Yetki hatası | Bağlantı yenilenir | D3 Önizleme | E2E-EKL-21 |
| D3/D4 | SPA offer ID değişir | Temiz D1/D2 | E2E-EKL-23 |
| Açık panel | `Esc` veya kapat | D1 Kapalı | E2E-EKL-25 |
| D7 Gönderildi | Ürün/listede aç | Panelde kayıt | E2E-EKL-13 |

## 4. Test verisi adlandırması

| Test verisi | Kaynak |
|---|---|
| `urun-degil.html` | Offer ID ve ürün kökü bulunmayan yerel sahte sayfa |
| `tam-urun.html` | `skuselector-5sku.json` + temel ürün alanlarından üretilen sahte sayfa modeli |
| `kismi-urun.html` | Başlık ve URL var; zorunlu fiyat/görsel/SKU alanlarından bazıları kontrollü eksik |
| `parser-hatasi.html` | `window.context` bozuk ve DOM yedeği yetersiz kontrollü sayfa |
| `fiyat-celiskisi.json` | Standart `26.90`, koşullu `24.90` + `新人价` |
| `ozel-moq-varyanti.json` | 2.000 set, özel üretim, stok 0 |
| `skuselector-5sku.json` | Beş SKU, ID/spec/stok/fiyat/ağırlık |
| `sevk-suresi.json` | 1–2 adet 48 saat; 3+ adet 480 saat |
| `satici-sinyalleri.json` | 5 yıl, kalite %100, 48 saat %98, tekrar alış %41 |
| `video-yok.json` | Yetenek bayrağı var, gerçek video yok |
| `sablon-videolu-urun.json` | Pozitif video ID/URL/poster/MIME/süre |
| `kenar-durumlar.json` | Erişim yok ≠ oran 0, aşırı stok, sıfır ölçü, ID string dönüşümü |

## 5. Ayrıntılı senaryolar

### E2E-EKL-01 — Ürün sayfası değilse düğme yok

**Amaç:** Eklenti kontrolünün yalnız desteklenen 1688 ürün detay sayfasında göründüğünü kanıtlamak.

**Ön koşul / hazırlık:** `urun-degil.html`; URL offer yolu değildir veya geçerli offer ID taşımaz. İlk kullanım onayı durumu önemsizdir.

**Adımlar:**

1. Sahte 1688 ana sayfa/kategori sayfasını aç.
2. DOM ve Shadow DOM içinde TedarikApp hostunu ara.
3. Beş saniyelik gözlem aralığında URL’yi değiştirmeden bekle.

**Beklenen sonuç:** `TedarikApp’e Ekle` düğmesi ve panel hostu yoktur. Parser çalışmaz. 1688/MTop isteği, hedef liste isteği ve `/api/capture` POST’u oluşmaz.

**Otomasyon notu:** Playwright ile tamamen otomatik; yerel sahte sayfa yeterlidir. Gerçek 1688 gerekmez.

### E2E-EKL-02 — Kapalı durum ve tembel başlangıç

**Amaç:** Desteklenen ürün sayfasında yalnız küçük düğmenin açıldığını, ağır yakalamanın başlamadığını kanıtlamak.

**Ön koşul / hazırlık:** `tam-urun.html`; prominent disclosure daha önce kabul edilmiş.

**Adımlar:**

1. Desteklenen offer URL’sini aç.
2. `TedarikApp’e Ekle` düğmesini bul.
3. Düğmeye basmadan iki saniye bekle.

**Beklenen sonuç:** Düğme görünür, panel kapalıdır. Ürün parser’ı ve `/api/capture` çalışmaz; özel 1688/MTop çağrısı yoktur. Sadece küçük UI kabuğu yüklenir.

**Otomasyon notu:** Playwright sahte sayfa harness’ında otomatik. Paketlenmiş uzantının gerçek host eşleşmesi ayrıca manuel smoke testidir.

### E2E-EKL-03 — Kapalıdan okunuyor durumuna tek geçiş

**Amaç:** İlk geçerli tıklamanın tam bir adet veri yakalama işlemi başlattığını kanıtlamak.

**Ön koşul / hazırlık:** `tam-urun.html`; parser çağrı sayacı; disclosure kabul edilmiş.

**Adımlar:**

1. Düğmeye bir kez bas.
2. Okuma sonucu kontrollü gecikmedeyken arayüzü gözle.

**Beklenen sonuç:** Metin `Veriler okunuyor…` olur; düğme geçici olarak pasiftir. Parser çağrısı tam `1`dir. `/api/capture` ve yeni 1688/MTop isteği yoktur.

**Otomasyon notu:** Playwright ile otomatik; parser Promise’i test kontrolünde bekletilir.

### E2E-EKL-04 — Okunuyor durumunda çift tıklama koruması

**Amaç:** Hızlı tekrar tıklamalarının ikinci parser işi veya ikinci ağ isteği üretmediğini kanıtlamak.

**Ön koşul / hazırlık:** E2E-EKL-03 ile aynı; parser sonucu 500 ms geciktirilmiş.

**Adımlar:**

1. Düğmeye art arda üç kez bas veya `click` + klavye `Enter` gönder.
2. Okuma tamamlanana kadar bekle.

**Beklenen sonuç:** Tek bir `Okunuyor` geçişi ve tek parser çağrısı vardır. `/api/capture` yoktur. UI kilidi açıldıktan sonra tek önizleme oluşur.

**Otomasyon notu:** Playwright ile otomatik; çağrı sayacı zorunludur.

### E2E-EKL-05 — Tam yakalama ile önizleme

**Amaç:** Başarılı parser sonucunun gerçek veriyle D3 önizleme durumuna dönüştüğünü kanıtlamak.

**Ön koşul / hazırlık:** `skuselector-5sku.json`, `sevk-suresi.json` ve `satici-sinyalleri.json` birleşik preview modeli.

**Adımlar:**

1. Düğmeye bas.
2. Okuma tamamlanmasını bekle.
3. Ürün özeti, yakalanan veri grupları ve satıcı/sevk kartını incele.

**Beklenen sonuç:** `Ürün verileri bulundu` görünür. Çince başlık, çeviri önerisi etiketi, beş SKU, beyan stokları, `0.66 kg`, iki sevk satırı ve platform kaynaklı satıcı sinyalleri görünür. Mock/sabit metin yoktur. Gönder düğmesine basılmadığı için `/api/capture` yoktur; özel 1688 isteği yoktur.

**Otomasyon notu:** Sahte sayfa + sanitize fixture ile otomatik. Gerçek sayfa gerekmez.

### E2E-EKL-06 — Koşullu fiyat uyarısı

**Amaç:** Standart fiyatın koşullu/yeni müşteri fiyatıyla ezilmediğini kanıtlamak.

**Ön koşul / hazırlık:** `fiyat-celiskisi.json`.

**Adımlar:**

1. Ürünü yakala.
2. Fiyat bölümünü aç.
3. Önizleme payload özetini kontrol et.

**Beklenen sonuç:** Standart fiyat `¥26,90`; ayrı amber uyarıda `¥24,90`, `新人价`/yeni müşteri etiketi ve uygunluğu doğrulama metni görünür. Para değerleri string kalır. Gönderim onayına kadar `/api/capture` yoktur.

**Otomasyon notu:** Playwright + fixture ile otomatik.

### E2E-EKL-07 — Çok varyantlı SKU bütünlüğü

**Amaç:** Seçili varyantla birlikte tüm SKU matrisinin, stok ve kimliklerinin korunmasını kanıtlamak.

**Ön koşul / hazırlık:** `skuselector-5sku.json`; başlangıçta bir SKU seçili.

**Adımlar:**

1. Yakalamayı aç.
2. Başlangıçta seçilen SKU’yu doğrula.
3. Panelden başka bir SKU seç.
4. Önizleme ayrıntısında tüm varyantları aç.

**Beklenen sonuç:** Beş SKU’nun `skuId`, `specId`, fiyat, stok, `0.66 kg` ve görsel bilgileri korunur. Panel seçimi 1688 sayfasındaki sepet/varyant seçimini değiştirmez. `/api/capture` yoktur.

**Otomasyon notu:** Otomatik; sahte sayfa DOM’unda 1688 seçimi için gözlemci/spi kullanılır.

### E2E-EKL-08 — Özel MOQ ve stok yok uyarısı

**Amaç:** Özel üretim varyantının genel MOQ’dan ayrıldığını ve sessizce normal varyant gibi gönderilmediğini kanıtlamak.

**Ön koşul / hazırlık:** `ozel-moq-varyanti.json`.

**Adımlar:**

1. Ürünü yakala.
2. `定制款2000套起` varyantını seç.
3. Varyant uyarı kartını incele.

**Beklenen sonuç:** `Özel üretim`, minimum `2.000 set` ve `Stok yok` uyarıları görünür; genel MOQ 1 adet değeriyle karışmaz. Kullanıcı açıkça devam etmeden gönderim yoktur.

**Otomasyon notu:** Playwright + fixture ile otomatik.

### E2E-EKL-09 — Pozitif ve negatif video kararı

**Amaç:** Gerçek medya kanıtı olduğunda video var, yalnız yetenek bayrağı olduğunda video yok sonucunu kanıtlamak.

**Ön koşul / hazırlık:** İki alt test: `sablon-videolu-urun.json` ve `video-yok.json`.

**Adımlar:**

1. Pozitif fixture ile yakala; medya özetini aç.
2. Paneli sıfırla.
3. Negatif fixture ile yakala; medya özetini aç.

**Beklenen sonuç:** Pozitif durumda bir video, poster ve medya bilgisi görünür; negatif durumda sahte video rozeti yoktur ve gerekirse `Video desteği var; oynatılabilir medya bulunamadı` bilgi uyarısı görünür. Gönderim yoktur.

**Otomasyon notu:** Fixture düzeyinde otomatik. Gerçek videoyu ağdan oynatmak gerekmez.

### E2E-EKL-10 — Kısmi okuma ve kontrollü devam

**Amaç:** Eksik alanların veri kaybı veya sessiz başarı yerine D4 olarak gösterildiğini ve izin verilen eksiklerle devam edilebildiğini kanıtlamak.

**Ön koşul / hazırlık:** `kismi-urun.html`; başlık/offer ID mevcut, bir ana görsel ve bazı satıcı/sevk alanları eksik; zorunlu/opsiyonel alan listesi sabit.

**Adımlar:**

1. Yakalamayı başlat.
2. `Bazı bilgiler eksik` durumunu aç.
3. Eksik alan listesini kontrol et.
4. `Bulunan verilerle devam et` seçeneğine bas.

**Beklenen sonuç:** Eksik alanlar `bulunamadı/kısmen bulundu` olarak listelenir; bulunan veri korunur. Zorunlu alan eksikse gönderim kapalıdır; yalnız opsiyonel alanlar eksikse D3 önizlemeye geçilir. Bu aşamada `/api/capture` yoktur.

**Otomasyon notu:** Otomatik; iki alt test (zorunlu eksik/opsiyonel eksik) önerilir.

### E2E-EKL-11 — Okuma hatası ve tekrar tara

**Amaç:** Parser hatasının paneli kapatmadığını ve tekrar denemenin D5 → D2 → D3 geçişini yaptığını kanıtlamak.

**Ön koşul / hazırlık:** İlk çağrıda hata veren `parser-hatasi.html`; ikinci çağrıda geçerli fixture döndüren kontrollü parser.

**Adımlar:**

1. Yakalamayı başlat.
2. `Ürün verileri alınamadı` durumunu doğrula.
3. `Tekrar tara` düğmesine bas.

**Beklenen sonuç:** İlk hatada gönderim isteği yoktur; teknik stack trace kullanıcıya gösterilmez. Tekrar tıklaması bir yeni parser çağrısı başlatır, yeni 1688/MTop isteği oluşturmaz ve başarılı önizlemeye geçer.

**Otomasyon notu:** Playwright ile otomatik.

### E2E-EKL-12 — Önizlemeden gönderime geçiş ve kilit

**Amaç:** Kullanıcı onayının tek gönderim başlattığını ve işlem sürerken kontrollerin kilitlendiğini kanıtlamak.

**Ön koşul / hazırlık:** Tam önizleme, seçili hedef liste; `/api/capture` yanıtı geciktirilmiş.

**Adımlar:**

1. `Ürünü TedarikApp’e Gönder` düğmesine bas.
2. Yanıt gelmeden düğmeye tekrar bas ve `Enter` gönder.
3. Gönderim sırasında form alanlarını değiştirmeyi dene.

**Beklenen sonuç:** D6 `Gönderiliyor…` görünür; tek POST oluşur. Ana düğme ve kaydı değiştiren kontroller pasiftir. Aynı `capture_id` için ikinci eşzamanlı istek yoktur.

**Otomasyon notu:** Playwright ağ stub’ı ile otomatik; ayrıca gerçek `/api/capture` sözleşme testiyle desteklenir.

### E2E-EKL-13 — Başarılı gönderim ve panel bağlantısı

**Amaç:** Başarılı API yanıtından sonra D7 durumunun ve doğru ürün/liste bağlantısının gösterildiğini kanıtlamak.

**Ön koşul / hazırlık:** Gerçek test API’si veya 201 yanıtı; yanıt içinde kayıt ve liste bağlantısı.

**Adımlar:**

1. Tam önizlemeyi gönder.
2. Başarı durumunu bekle.
3. `Panelde ürünü aç` bağlantısına bas.

**Beklenen sonuç:** `TedarikApp’e gönderildi` görünür; yeni POST oluşmaz. Bağlantı yanıtın gerçek kayıt/listesine gider ve ürün panelde aynı offer ID ile görünür.

**Otomasyon notu:** UI kısmı stub ile; kayıt doğrulaması mevcut CI MySQL ve `/api/capture` üzerinden otomatik yapılabilir.

### E2E-EKL-14 — Ağ kopması ve veri koruma

**Amaç:** İstek seviyesindeki bağlantı hatasında önizlemenin kaybolmadığını kanıtlamak.

**Ön koşul / hazırlık:** Tam önizleme; `/api/capture` bağlantısı ilk denemede abort edilir.

**Adımlar:**

1. Gönder düğmesine bas.
2. Ağ isteğini bağlantı hatasıyla düşür.
3. Sunucu hatası kartını ve önizleme alanlarını kontrol et.

**Beklenen sonuç:** D10 görünür; ürün, varyant, hedef liste ve uyarılar bellekte aynen korunur. Otomatik tekrar veya ikinci POST yoktur. HTML hata sayfası gösterilmez.

**Otomasyon notu:** Playwright `route.abort` eşdeğeriyle otomatik.

### E2E-EKL-15 — HTTP 502, tekrar gönder ve idempotency

**Amaç:** 502 sonrasında aynı mantıksal yakalamanın tekrar gönderilebildiğini fakat çift kayıt üretmediğini kanıtlamak.

**Ön koşul / hazırlık:** İlk gönderimde 502; ikinci gönderimde başarı; iki isteğin gövdesini kaydeden API stub’ı. Ayrı alt testte sunucu ilk kaydı oluşturur fakat istemci yanıtı alamaz.

**Adımlar:**

1. Gönder ve 502 yanıtını üret.
2. D10’da `Tekrar gönder` düğmesine bas.
3. İkinci yanıtı başarılı yap.
4. Her iki isteğin `capture_id`/idempotency değerini ve panel kayıt sayısını karşılaştır.

**Beklenen sonuç:** İlk hatada veri korunur. Tekrar yalnız kullanıcı eylemiyle başlar. Her iki denemede aynı idempotency kimliği kullanılır; panelde tek kayıt oluşur. Başarı sonunda D7 görünür.

**Otomasyon notu:** UI stub’ı + gerçek API idempotency entegrasyon testi önerilir; gerçek 1688 gerekmez.

### E2E-EKL-16 — Mükerrer durumu ve dört seçeneğin görünmesi

**Amaç:** API mükerrer yanıtının sessiz ikinci kayıt yerine D8 ve dört açık seçenek oluşturduğunu kanıtlamak.

**Ön koşul / hazırlık:** Aynı platform + offer ID kayıtlı; API yapılandırılmış mükerrer yanıtı ve mevcut kayıt kimliği döndürür.

**Adımlar:**

1. Aynı ürünü aynı listeye göndermeyi dene.
2. Mükerrer panelini incele.

**Beklenen sonuç:** `Ürün zaten listede` görünür. `Mevcut ürünü aç`, `Başka listeye ekle`, `Mevcut kaydı güncelle`, `İptal` seçeneklerinin dördü de vardır. İkinci ürün kaydı oluşmaz.

**Otomasyon notu:** Playwright + API fixture; panel kayıt sayısı gerçek test DB’de doğrulanabilir.

### E2E-EKL-17 — Mükerrerde mevcut ürünü aç

**Amaç:** İlk mükerrer seçeneğinin doğru mevcut kayda yönlendirdiğini kanıtlamak.

**Ön koşul / hazırlık:** E2E-EKL-16 durumu.

**Adımlar:**

1. `Mevcut ürünü aç` seçeneğine bas.
2. Açılan panel URL’si ve offer ID’yi kontrol et.

**Beklenen sonuç:** Mevcut kayıt açılır; yeni `/api/capture` veya güncelleme POST’u oluşmaz.

**Otomasyon notu:** Otomatik; yeni sekme veya aynı sekme ürün sözleşmesine göre beklenir.

### E2E-EKL-18 — Mükerreri başka listeye ekle

**Amaç:** Aynı kaynak ürünün kullanıcı onayıyla farklı hedef listeye eklenebilmesini kanıtlamak.

**Ön koşul / hazırlık:** E2E-EKL-16; ikinci açık liste mevcut.

**Adımlar:**

1. `Başka listeye ekle` seçeneğine bas.
2. İkinci listeyi seç.
3. İşlemi onayla.

**Beklenen sonuç:** Açık seçime bağlı tek istek oluşur; ilk listedeki kayıt değişmez, ikinci listede tek kayıt görünür. Aynı işlem kimliği değil, açıkça yeni listeleme eylemini tanımlayan sunucu sözleşmesi kullanılır.

**Otomasyon notu:** Gerçek test DB ve API ile otomatik önerilir.

### E2E-EKL-19 — Mükerrerde mevcut kaydı güncelle

**Amaç:** Güncelleme seçeneğinin yeni ürün oluşturmak yerine belirlenen kaydı güncellediğini kanıtlamak.

**Ön koşul / hazırlık:** E2E-EKL-16; fixture fiyatı veya seçili varyantı mevcut kayıttan farklı.

**Adımlar:**

1. `Mevcut kaydı güncelle` seçeneğine bas.
2. Değişiklik özetini incele ve onayla.
3. Panelde mevcut kaydı aç.

**Beklenen sonuç:** Tek güncelleme isteği oluşur; kayıt kimliği değişmez, ürün sayısı artmaz, değişen alanlar güncellenir. Koşullu fiyat normal fiyatı ezmez.

**Otomasyon notu:** Gerçek test API/DB ile otomatik; UI stub’ıyla görünür metin ayrıca test edilir.

### E2E-EKL-20 — Mükerrer işlemini iptal

**Amaç:** İptalin hiçbir veri değişikliği yapmadan önizlemeye döndürdüğünü kanıtlamak.

**Ön koşul / hazırlık:** E2E-EKL-16.

**Adımlar:**

1. `İptal` seçeneğine bas.
2. Önizleme ve kayıt sayısını kontrol et.

**Beklenen sonuç:** D3 önizleme korunur; POST/PATCH/PUT oluşmaz ve panelde kayıt sayısı değişmez.

**Otomasyon notu:** Playwright ile otomatik.

### E2E-EKL-21 — Geçersiz token ve bağlantıyı yenileme

**Amaç:** 401/403 yanıtının D9 yetki durumunu göstermesini ve ürün önizlemesini kaybetmemesini kanıtlamak.

**Ön koşul / hazırlık:** Geçersiz/süresi dolmuş TedarikApp test tokenı; tam önizleme.

**Adımlar:**

1. Gönder düğmesine bas ve 401/403 döndür.
2. `Ayarlara git` eylemini doğrula.
3. Test bağlantısını yenile ve panele dön.

**Beklenen sonuç:** `TedarikApp bağlantısı gerekli` görünür; token değeri DOM’da veya hata metninde görünmez. Otomatik retry döngüsü yoktur. Bağlantı düzeldikten sonra aynı önizleme D3’te kalır; gönderim yalnız yeniden kullanıcı komutuyla olur.

**Otomasyon notu:** UI/API stub ile otomatik; gerçek Chrome ayarlar sayfası yönlendirmesi paketlenmiş uzantıda manuel doğrulanır.

### E2E-EKL-22 — Hedef liste yükleme ve son seçimi hatırlama

**Amaç:** Açık listelerin seçilebildiğini ve son tercihin yalnız işlevsel ayar olarak hatırlandığını kanıtlamak.

**Ön koşul / hazırlık:** İki açık, bir kapalı liste; storage temiz başlangıç.

**Adımlar:**

1. Önizlemeyi aç ve liste seçeneklerini bekle.
2. İkinci açık listeyi seç.
3. Paneli kapatıp aynı offer’da tekrar aç.
4. Liste API’sini bir alt testte hataya düşür.

**Beklenen sonuç:** Yalnız uygun listeler görünür; son açık liste tekrar seçilidir. Liste GET isteği olabilir fakat `/api/capture` yoktur. Liste API hatasında yakalanan ürün verisi korunur ve `Tekrar dene` görünür.

**Otomasyon notu:** Playwright + storage izolasyonu ve API stub ile otomatik.

### E2E-EKL-23 — SPA offer değişiminde eski önizlemeyi temizleme

**Amaç:** URL’de offer ID değiştiğinde önceki ürünün yeni ürüne yanlışlıkla gönderilmesini engellemek.

**Ön koşul / hazırlık:** Offer A tam önizlemede; History API ile Offer B’ye soft navigation; iki farklı fixture başlığı/ID’si.

**Adımlar:**

1. Offer A’yı yakala ve önizle.
2. Sayfada reload olmadan URL/ürün kökünü Offer B’ye geçir.
3. Paneli ve düğmeyi gözle.
4. Offer B için yeniden yakala.

**Beklenen sonuç:** A’nın geçici önizlemesi, seçili SKU’su ve `capture_id`si temizlenir. A için gönderim mümkün değildir. B yakalanana kadar eski başlık görünmez. Otomatik `/api/capture` ve tüm sayfayı sürekli tarayan ağ/DOM döngüsü yoktur.

**Otomasyon notu:** Sahte SPA harness’ında otomatik; gerçek 1688 soft-navigation davranışı haftalık manuel canary’de gözlenir.

### E2E-EKL-24 — İlk kullanım prominent disclosure

**Amaç:** İlk veri işleme öncesinde kullanıcının hangi verinin nereye gideceğini görüp açık onay verdiğini kanıtlamak.

**Ön koşul / hazırlık:** Temiz extension storage; disclosure kabul kaydı yok.

**Adımlar:**

1. Desteklenen ürün sayfasında TedarikApp düğmesine bas.
2. Açıklama metninde ürün bilgisi + kaynak URL’si + kendi TedarikApp hesabı hedefini kontrol et.
3. `Vazgeç`e bas; yeniden aç.
4. `Kabul et ve devam et`e bas.
5. Paneli kapatıp tekrar aç.

**Beklenen sonuç:** Kabul öncesinde parser, liste API’si ve `/api/capture` çalışmaz; 1688 oturum/çerez verisi işlenmez. Vazgeç kapalı duruma döner. Kabul açık kullanıcı eylemidir ve ardından okuma başlar. İkinci kullanımda aynı sürüm/metin için disclosure tekrar gösterilmez; politika sürümü değişirse yeniden gösterilir.

**Otomasyon notu:** Playwright sahte storage/harness ile otomatik; gerçek extension storage davranışı manuel paket smoke testinde doğrulanır.

### E2E-EKL-25 — Klavye, odak ve Esc turu

**Amaç:** Panelin yalnız klavyeyle kullanılabildiğini, odağın yönetildiğini ve Esc geçişinin güvenli olduğunu kanıtlamak.

**Ön koşul / hazırlık:** Tam önizleme; düğme klavye odağında.

**Adımlar:**

1. `Enter` ile paneli aç.
2. Odağın panel başlığına/ilk anlamlı kontrole geçtiğini doğrula.
3. `Tab`/`Shift+Tab` ile tüm kontrolleri sırayla dolaş.
4. `Esc` ile kapat ve odağın TedarikApp düğmesine döndüğünü doğrula.
5. Gönderim sürerken `Esc`e bas.

**Beklenen sonuç:** Dialog adı/rolü vardır; görünür odak kaybolmaz, sayfa arkasına kontrolsüz taşmaz. Normal durumda Esc D1’e döner ve ağ isteği oluşturmaz. Gönderim sırasında güvenli iptal/“işlem sürüyor” davranışı uygulanır; ikinci POST oluşmaz.

**Otomasyon notu:** Playwright klavye kontrolleriyle otomatik; ekran okuyucu adı ve kontrast ayrıca erişilebilirlik aracı/manuel turla doğrulanır.

### E2E-EKL-26 — Yerleşim, çakışma ve Shadow DOM izolasyonu

**Amaç:** Panelin 1688 kontrollerini kapatmadığını ve iki yönlü CSS sızıntısı olmadığını kanıtlamak.

**Ön koşul / hazırlık:** Sahte sayfada agresif global `button/img/input` CSS’i, sağ araç çubuğu ve ana ürün görseli; masaüstü ile dar viewport.

**Adımlar:**

1. Kapalı düğmenin konumunu ölç.
2. Paneli masaüstünde ve yaklaşık 1.000 px dar görünümde aç.
3. Ana görsel, sipariş/sepet ve yardım kontrollerinin görünürlüğünü kontrol et.
4. 1688 sahte sayfa CSS’ini değiştir ve UI görünümünü yeniden ölç.

**Beklenen sonuç:** Düğme yardım araçlarıyla çakışmaz. Panel yaklaşık 430 px/clamp sınırındadır, ana görseli tamamen gizlemez ve sayfa layout’unu itmez. Shadow DOM UI dış CSS’ten etkilenmez; eklenti CSS’i sayfa kontrollerini değiştirmez. Ağ isteği yoktur.

**Otomasyon notu:** Playwright ölçüm + görsel regresyon ile otomatik; gerçek 1688 yerleşimi canary’de manuel.

### E2E-EKL-27 — Token, cookie ve oturum verisi sızıntı testi

**Amaç:** Hassas tarayıcı/oturum verisinin DOM’a, mesaj köprüsüne veya capture payload’ına girmediğini kanıtlamak.

**Ön koşul / hazırlık:** Sahte sayfada tuzak alanlar: cookie benzeri değerler, `_m_h5_tk`, `sign`, alıcı profil nesnesi; background’da sentetik TedarikApp test tokenı.

**Adımlar:**

1. Ürünü yakala ve gönder.
2. Shadow DOM metnini, MAIN→ISOLATED mesajını ve yakalanan POST gövdesini özyinelemeli tara.
3. Hata logu ve screenshot/trace metadatasını kontrol et.

**Beklenen sonuç:** Yasaklı anahtar/değerlerin hiçbiri UI, bridge veya payload’da yoktur. Authorization yalnız background’ın HTTPS isteği header’ında bulunur; DOM/MAIN world/log içinde değildir. Ürün iş verisi ve sorgusuz açık kaynak URL’si korunur.

**Otomasyon notu:** Sahte canary değerlerle otomatik. Gerçek token/cookie test fixture’ına konulmamalıdır.

### E2E-EKL-28 — GTİP/TAREKS kapsam dışı kalır

**Amaç:** Bu küçük eklenti akışına GTİP, TAREKS, gümrük veya kesin ithalat maliyeti alanlarının sızmadığını kanıtlamak.

**Ön koşul / hazırlık:** Tüm UI durumları ve tam capture v2 payload fixture’ı.

**Adımlar:**

1. Kapalı, önizleme, kısmi, hata, mükerrer ve başarı ekranlarının metnini tara.
2. Gönderilen payload anahtarlarını tara.

**Beklenen sonuç:** GTİP, TAREKS, vergi, kesin gümrük maliyeti veya mevzuat önerisi yoktur. Yalnız tedarik ürün verileri vardır. Ek ağ servisi çağrılmaz.

**Otomasyon notu:** Metin/payload yasaklı terim assertion’ıyla otomatik.

### E2E-EKL-29 — Paneli kapatıp aynı önizlemeyi yeniden açma

**Amaç:** Aynı offer değişmediyse kapat/aç işleminin kontrollü kısa süreli önizleme cache’ini kullandığını kanıtlamak.

**Ön koşul / hazırlık:** Tam önizleme; offer ID sabit.

**Adımlar:**

1. Önizlemeyi aç ve paneli kapat.
2. Aynı sayfada tekrar düğmeye bas.
3. Önizleme ve çağrı sayaçlarını kontrol et.

**Beklenen sonuç:** Önizleme aynı sayfa oturumunda korunabilir ve veri değişmediyse ikinci ağır parser çağrısı gerekmez. `/api/capture` oluşmaz. Cache başka offer’a taşınmaz.

**Otomasyon notu:** Playwright ile otomatik; ürün değişimi davranışı E2E-EKL-23’tedir.

## 6. 16 kabul kriteri ↔ senaryo matrisi

| Kriter | Kabul kriteri özeti | Kanıtlayan senaryolar | Kanıt türü |
|---:|---|---|---|
| 1 | Düğme yalnız desteklenen ürün sayfasında | E2E-EKL-01, 02, 23 | Otomatik + manuel host smoke |
| 2 | Düğme sipariş/sepet/yardım kontrollerini kapatmaz | E2E-EKL-26 | Görsel/ölçü + canary |
| 3 | İlk tıklama yalnız bir yakalama başlatır | E2E-EKL-03, 04 | Çağrı sayacı |
| 4 | Panel yaklaşık 430 px, ana görsel tamamen kapanmaz | E2E-EKL-26 | Viewport ölçümü/görsel regresyon |
| 5 | UI CSS izolasyonu iki yönlü çalışır | E2E-EKL-26 | Shadow DOM ve agresif CSS fixture’ı |
| 6 | Panel gerçek parser sonucunu gösterir | E2E-EKL-05, 09, 10 | Sanitize fixture → görünür alan eşlemesi |
| 7 | Standart ve koşullu fiyat ayrıdır | E2E-EKL-06, 19 | Fiyat fixture’ı ve güncelleme |
| 8 | Seçili varyant, SKU matrisi ve stok korunur | E2E-EKL-07, 08 | Beş SKU + özel MOQ fixture’ı |
| 9 | Hedef liste seçilir ve son seçim hatırlanır | E2E-EKL-22 | API + storage izolasyonu |
| 10 | Gönder düğmesi çift kayıt üretmez | E2E-EKL-12, 15, 16 | POST sayısı + idempotency + DB kayıt sayısı |
| 11 | Başarıda ürün/liste bağlantısı görünür | E2E-EKL-13 | UI + gerçek panel kaydı |
| 12 | 502/ağ hatasında veri kaybolmaz | E2E-EKL-14, 15 | Ağ abort/502 sonrası preview karşılaştırması |
| 13 | Token/cookie/MTop/oturum sızmaz | E2E-EKL-27 | Bridge/DOM/payload özyinelemeli tarama |
| 14 | Klavye kullanımı ve Esc kapanışı | E2E-EKL-25 | Klavye/odak assertion’ı |
| 15 | Offer ID değişince eski önizleme temizlenir | E2E-EKL-23 | Sahte SPA geçişi |
| 16 | GTİP/TAREKS/gümrük kapsam dışıdır | E2E-EKL-28 | UI ve payload yasaklı terim taraması |

**Matris sonucu:** 16/16 kriter kanıtlıdır; kanıtsız kriter yoktur.

## 7. 10 durum ↔ senaryo matrisi

| Durum | Senaryo |
|---|---|
| D1 Kapalı | E2E-EKL-02, 25, 29 |
| D2 Okunuyor | E2E-EKL-03, 04, 11 |
| D3 Başarılı okuma/Önizleme | E2E-EKL-05–09, 10, 13, 22, 23 |
| D4 Kısmi okuma | E2E-EKL-10 |
| D5 Okuma hatası | E2E-EKL-11 |
| D6 Gönderiliyor | E2E-EKL-12–16, 21 |
| D7 Gönderildi | E2E-EKL-13, 15, 18, 19 |
| D8 Mükerrer | E2E-EKL-16–20 |
| D9 Yetki hatası | E2E-EKL-21 |
| D10 Sunucu hatası | E2E-EKL-14, 15 |

**Durum sonucu:** 10/10 durum ve tanımlı geçişlerin tamamı kapsanmıştır.

## 7B. rc7 SAHA BULGULARINDAN DOĞAN SENARYOLAR (26 Ağu 2026)

Bu iki senaryo, Ürün Sahibi'nin rc6 ekranında gördüğü iki kusurdan doğdu ve
GERÇEK TARAYICI ister: ikisi de hesaplanmış düzen (computed layout) ölçer,
jsdom bunu üretmez.

### E2E-EKL-30 — Panel içeriği yatay taşmaz

**Amaç:** Uzun ilan/satıcı adresleri ve 30+ karakterlik varyant çipleri panel
genişliğini aşmasın; yatay kaydırma çubuğu çıkmasın.

**Ön koşul / hazırlık:** Tam önizleme; `offerId`, `hotSaleSkuId`, `spm` ekli
gerçek uzunlukta ilan adresi; altı uzun varyant adı; 1440 px ve 360 px görünüm.

**Adımlar:** Panel çizilir ve açılır → gövdenin `scrollWidth`/`clientWidth`
değerleri okunur → gövdedeki HER öğenin sağ kenarı panelin sağ kenarıyla
karşılaştırılır.

**Beklenen:** `scrollWidth ≤ clientWidth`; panel sınırını aşan öğe YOK.
Kırpma (overflow gizleme) tek başına yeterli sayılmaz — öğe gerçekten panelin
içinde kalmalıdır.

**Otomasyon notu:** `e2e/tests/08-eklenti-paneli.spec.ts`. Ölçüm birimi piksel;
1688'e istek çıkmaz, arayüz boş sayfaya monte edilir.

### E2E-EKL-31 — Panel varsayılan KAPALI, düğme her yüklemede basılır

**Amaç:** Sayfa yüklendiğinde panel AÇILMAMALI; görünen tek şey satır içi
"TedarikApp'e Ekle" düğmesi (montaj hedefi yoksa sağ-alt pill) olmalı.

**Ön koşul / hazırlık:** Beş ardışık sayfa yüklemesi; biri montaj hedefi olan,
biri olmayan sayfa.

**Adımlar:** Her yüklemede arayüz kurulur → `acikMi()` ve panelin HESAPLANMIŞ
`display` değeri okunur → düğme kabının varlığı ve montaj türü doğrulanır →
hedefsiz sayfada pill düğmesinin ölçülebilir kutusu ve konumu kontrol edilir.

**Beklenen:** 5/5 kapalı (`display: none`), 5/5 düğme var; hedefsiz sayfada
`PILL` türü ve sağ altta görünür düğme.

**Otomasyon notu:** `e2e/tests/08-eklenti-paneli.spec.ts`. rc6'da hesaplanmış
değer `flex`ti: `hidden` özniteliği yazar `display` kuralı tarafından eziliyordu.

### E2E-EKL-32 — Panel gövdesi kendi içinde kaydırılır (v1.0 A1)

**Amaç:** İçerik viewport'tan uzun olduğunda "Nereye gitsin" ve "Yakala ve
Gönder" ekran dışında kalmasın; çekmece kendi içinde kaysın.

**Ön koşul / hazırlık:** Bol içerikli önizleme (24 varyant, dolu alan listesi);
1366×768 ve 1920×1080 görünüm.

**Adımlar:** Panel çizilir ve açılır → panel yüksekliği viewport ile
karşılaştırılır → gövde kaydırıcısının `scrollHeight`/`clientHeight` okunur →
"Yakala ve Gönder" düğmesinin kutusu ölçülür ve merkezinde `elementFromPoint`
ile gerçekten o düğmenin durduğu doğrulanır → gövde sonuna kaydırılıp üst
başlığın ve alt çubuğun yerinden oynamadığı kontrol edilir.

**Beklenen:** Panel ≤ viewport; gövde taşıyor (`scrollHeight > clientHeight`);
düğme viewport içinde ve TIKLANABİLİR; kaydırmada üst/alt çubuk sabit.

**Otomasyon notu:** `e2e/tests/08-eklenti-paneli.spec.ts`. Görünürlük tek başına
yetmez: başka bir katmanın altında kalan düğme de "görünür"dür.

### E2E-EKL-33 — Panel metin disiplini (v1.0 A2/A3/A4)

**Amaç:** Yanıltıcı rozet, literal HTML varlığı ve ekran dışına taşan ipucu
balonu kalmasın.

**Ön koşul / hazırlık:** (a) öneri = orijinal olan görünüm, (b) öneri ≠ orijinal
olan görünüm, (c) `A&gt;B` ve `&lt;img src=x onerror=…&gt;` varyantları.

**Adımlar:** Üç görünüm çizilir → rozet ve açıklama satırının varlığı okunur →
çip metinleri okunur ve DOM'da `<img>` düğümü OLUŞMADIĞI doğrulanır → panelde
`title` özniteliği taşıyan öğe kalmadığı ve adres satırında kopyala düğmesi
bulunduğu kontrol edilir.

**Beklenen:** Öneri = orijinal ise rozet YOK + "sunucuda üretilir" notu VAR;
`A&gt;B` → `A>B`; enjekte edilen işaretleme METİN olarak basılır; `title` yok,
kopyala düğmesi var.

**Otomasyon notu:** `e2e/tests/08-eklenti-paneli.spec.ts` + birim:
`extension/tests/metin.test.ts` (XSS regresyonu dâhil).

### E2E-EKL-34 — Inline düğme satın alma bloğunun üstünde (v1.0 A5/A8)

**Amaç:** Düğme sağ ürün sütununda, satın alma bloğunun hemen üstünde dursun;
mağaza adı satırını örtmesin. Hedef bulunamazsa pill yedeği görünsün. Sayfa
kendini yeniden çizse (dil geçişi, geç çizim) düğme kaybolmasın.

**Ön koşul / hazırlık:** İki DOM fikstürü — `e2e/fikstur/1688-zh.html` (立即订购 /
加入进货单) ve `e2e/fikstur/alitrading-tr.html` ("Giriş yaparak…" / "Sepete
ekle"). Fikstürler v1.0'da SENTETİKTİR; gerçek dökümle değiştirilmesi İE#22
maddesidir.

**Adımlar:** Her fikstürde 5 ardışık montaj → düğme kutusu satın alma bloğunun
üst kenarıyla ve mağaza satırıyla kesişim açısından karşılaştırılır → sağ sütun
`innerHTML` ile yeniden çizilerek dil geçişi taklit edilir ve nöbetin düğmeyi
geri bastığı beklenir → hedefsiz sayfada pill 5/5 ölçülür.

**Beklenen:** 5/5 `SATIRICI`, düğme bloğun ÜSTÜNDE, hiçbir komşuyla kesişim yok,
genişlik tam; 5/5 dil geçişinde düğme geri gelir; hedefsizde 5/5 görünür pill.

**Otomasyon notu:** `e2e/tests/08-eklenti-paneli.spec.ts`. A8'in kökü iki
parçalıydı: tek seferlik montaj + adres değişmeden yapılan yeniden çizim.

## 8. Claude Code için uygulama notları

1. Yeni senaryolar mevcut `e2e/` süitinin tek worker ve Chromium düzenine uymalıdır.
2. Gerçek panel/API testleri mevcut `girisYap`, `csrfToken`, `listeAc`, `gorunen` yaklaşımını kullanmalıdır; seçici metni yazmadan gerçek UI kaynağı kontrol edilmelidir.
3. Sahte 1688 UI testleri ayrı bir proje/etiket altında tutulmalı; gerçek 1688 alanına hiçbir CI isteği çıkmamalıdır.
4. MV3 uzantısının Chrome’a gerçek yüklenmesi ve gerçek sayfa konumu manuel kabul/canary kapsamındadır; CI başarısı bunu kanıtlamış sayılmamalıdır.
5. `/api/capture` sözleşme testleri gerçek test DB’sinde, sahte 1688 ürün verileriyle çalışmalıdır.
6. Her test bağımsız veri adı/offer ID üretmeli; tek veritabanı nedeniyle dosyalar paralel çalıştırılmamalıdır.
7. Hata testlerinde görünür davranışla birlikte istek adedi, yöntem, durum kodu, `capture_id` ve DB kayıt sayısı doğrulanmalıdır.
8. Trace/screenshot içine gerçek token, cookie veya HAR konulmamalıdır.
9. Para değerleri string, normalize kimlikler string kalmalıdır.
10. Gerçek 1688 sayfası gerektiren bütün kontroller otomatik süitten çıkarılıp `1688-canary-protokolu.md` kapsamına alınmalıdır.
