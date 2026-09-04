# Görev #30 — Firma Portalı HTML Prototipi

## Teslim özeti

Çalışan prototip, Blok C kodlamasından önce Ürün Sahibi incelemesi için hazırlanmıştır. Prototip tek HTML dosyasıdır; CSS, JavaScript, 111 adet `portal.*` metni, 5B `status.*` terimleri ve sahte RFQ verisi dosyanın içine gömülüdür. CDN, font, API, form POST'u, analitik veya otomatik dış istek yoktur.

Önceki #30 teslimi için repo varsayılan dalı ve ilgili V3 hazırlık klasörleri tarandı. Önceki prototip veya `gorev-30` klasörü bulunmadı; bu teslim sıfırdan üretildi.

Dayanak snapshot'ı: `tilbehome/tedarikapp` · `main` · `08c2e3f128bbb996124f2733093dacf7d0124f95`.

## Dosya listesi

| Dosya | İçerik |
|---|---|
| `firma-portali-prototip.html` | 7 ekranın tamamı, TR/EN/ZH dil geçişi, durum akışı, firma cevap formu, kademeli fiyat, kısmi/nihai gönderim ve revizyon görünümü |
| `OKUBENI.md` | Şartname eşlemesi, kaynak kullanımı, bilinçli sapmalar ve PM soruları |

## Çalıştırma

`firma-portali-prototip.html` dosyasını güncel Chrome, Edge veya Firefox ile doğrudan açın. Sunucu ve kurulum gerekmez.

Prototipte şu etkileşimler çalışır:

- TR / EN / 中文 düğmeleri bütün portal etiketlerini anında değiştirir.
- Üst durum şeridi `SENT → VIEWED → PRICING → RESPONDED → APPROVED` adımlarında gezilir; görünen adlar 5B `status.*` terimlerinden gelir.
- Sol ekran gezgini 7 ekranın tamamını doğrudan açar.
- Liste araması ve yanıt durumu süzgeçleri çalışır; liste 25 satır ve `18/25` kısmi tamamlanma örneği taşır.
- Satır formunda `Yanıtlanmadı / Bulundu / Bulunamadı / Alternatif var` seçimi alanları değiştirir.
- Fiyatlı cevaptan `Bulunamadı`ya geçiş, fiyat/MOQ/termin değerlerini sessiz silmez; mevcut kaynak metinlerle ikinci onay ister.
- Zorunlu alan, kademe, koli boyutu ve durum doğrulamaları görünür hata üretir.
- Kademeli fiyat satırı ekleme/silme çalışır; ara miktar için fiyat uydurulmaz.
- Çevrimdışı düğmesi kısmi ve nihai gönderimi kapatır; taslak kayıt durumu simüle edilir.
- Kısmi gönderim `18 gönderilir / 7 taslak kalır` onayı ve başarı durumunu gösterir.
- `RESPONDED` durumuna geçildiğinde tamamlanma `25/25` olur; üç ticari onay işaretlenince nihai gönderim başarı/salt-okunur ekrana geçer.

## Ekran ↔ şartname kapsama tablosu

| # | Şartname ekranı | Prototipteki karşılığı | Kapsam |
|---:|---|---|---|
| 1 | Karşılama ve tur özeti | Alıcı, liste, R1, 25 ürün, DDP/Sakarya, yönerge, gizlilik ve dil yardımı | Tam |
| 2 | Liste görünümü ve ilerleme | 25 mobil uyumlu satır, arama, durum süzgeçleri, `18/25` çubuğu, satır açma, kısmi/nihai gönderim girişleri | Tam; eksik filtre anahtarları için aşağıdaki sapma geçerli |
| 3 | Satır yanıt formu | Salt-okunur talep, dört yanıt durumu, KDV dâhil DDP fiyat, ISO para birimi, 阶梯价, MOQ, termin, koli/CBM/ağırlık, ambalaj, not ve bağlı alternatif | Tam |
| 4 | Kısmi gönderim | 18 hazır + 7 kalan özeti, çevrimiçi kapısı, onay ve başarı | Tam |
| 5 | Nihai gönderim onayı | 25/25 kapısı, üç onay kutusu, çevrimdışı engeli ve tek gönderim eylemi | Tam |
| 6 | Başarı ve salt-okunur teklif | Referans, zaman damgası, salt-okunur ticari alanlar ve revizyon bildirimi | Tam |
| 7 | Revizyon turu açıldı | R1 → R2, değişen MOQ/termin/fiyat satırları, önceki turun salt-okunur korunması | Tam; revizyona özgü onaysız metin anahtarları üretilmedi |

**Sonuç: 7/7 ekran prototipte vardır.**

## Metin ve durum tek kaynağı

- `portal-metinleri.json` içindeki 111 kayıt değiştirilmeden HTML içine gömülmüştür.
- Arayüz metinleri çalışma anında anahtarla çözülür; başlıkların altında kullanılan `portal.*` anahtarı görünür.
- Tur durumu şeridi, `cikti-terimleri.json` içindeki `status.sent`, `status.waiting_supplier`, `status.waiting_price`, `status.waiting_approval` ve `status.approved` kayıtlarını kullanır.
- `SENT`, `VIEWED`, `PRICING`, `RESPONDED`, `APPROVED` yalnız durum makinesinin kanonik kodlarıdır; kullanıcı etiketi değildir.
- Ürün adları, kodlar, miktarlar, para birimleri, tur numaraları, tarihler ve firma girdisi sahte iş verisidir; portal etiketi değildir.

## Görev #28 kabul sınırları

| Kabul sınırı | Prototip davranışı |
|---|---|
| Boş cevap ≠ `Bulunamadı` | `Yanıtlanmadı` ayrı nihai olmayan durumdur; `Bulunamadı` seçimi kısa firma notu olmadan tamamlanamaz. |
| `0`, tire veya “bakılıyor” otomatik olumsuz cevap değildir | Kabul örnekleri satır formunda ayrı durum kartlarıyla görünür; prototip bu örneklerden fiyat veya `Bulunamadı` üretmez. |
| Asıl ürün alternatif tarafından ezilmez | `Alternatif var` akışında asıl ürün `Bulunamadı` olarak korunur; alternatif bağlantısı/açıklaması ve kendi ticari alanları bağlı ayrı cevap yüzünde tutulur. |
| Kısmi dönüş hata değildir | 18 tamamlanan satır gönderilir; 7 satır taslak ve `Yanıtlanmadı` kalır. |
| Para birimi ve KDV varsayılmaz | Fiyatlı cevapta ISO para birimi ve Türkiye KDV dâhil onayı zorunludur. |
| DDP kur riskini çözmez | Firma yüzünde iç kur veya TL kıyas gösterilmez; yalnız ham fiyat/para birimi ile KDV dâhil DDP beyanı vardır. |
| Kademe aralıkları arasında doğrusal fiyat üretilmez | Prototip yalnız girilen min/max/fiyat satırlarını saklar ve sıra/çakışma doğrular; 700 adet için interpolasyon yapmaz. |
| Eski tur yeni turu ezmez | Revizyon ekranı R1'i salt okunur bırakır ve R2 farklarını ayrı gösterir. |

## Bilinçli sapmalar

1. Emirde dayanaklar `v3-c/gorev-15/` ve `gorev-7/portal-metinleri.json` olarak verilmişti. Repo `main` dalında bağlayıcı dosyalar doğrudan `docs/v3/hazirlik/v3-c/` altındadır. Prototip repo içindeki mevcut dosyaları kullandı; hayali klasör üretilmedi.
2. Şartnamenin “yeni öneri” bölümündeki `portal.filter.all`, `portal.filter.unanswered`, `portal.filter.invalid`, `portal.action.clear_local_draft`, `portal.revision.*` ve ek doğrulama anahtarları 111'lik onaylı kaynakta yoktur. Ret sebebi olacak elle çeviri yapılmadı. Liste süzgeçlerinde yalnız mevcut `portal.status.*` etiketleri kullanıldı; “Tümü”ne dönüş seçili duruma ikinci kez basarak yapılır. Revizyon ekranı mevcut `portal.success.revision_notice` metnini yeniden kullanır.
3. `termin_baslangici`, `termin_birimi`, MOQ birimi ve ambalaj gibi enum seçenekleri için onaylı portal metni yoktur. Prototip bu seçeneklerde sözleşmedeki kanonik kodları (`calendar_day`, `order_confirmation` vb.) gösterir; ürün kodu gibi teknik veri kabul edilir. Üretim emrinden önce insan etiketleri için PM anahtarı gerekir.
4. Statik prototip gerçek oturum, 6 haneli anahtar doğrulama, sunucu sürüm kıyası, idempotency kaydı, bildirim, API, POST, kalıcı yerel taslak veya farklı cihaz senkronizasyonu üretmez. Bunların UI kapıları gösterilir ve tarayıcı içi geçici durumla simüle edilir.
5. Şartname erişimi doğru anahtar girildikten sonra başlattığı için 6 haneli anahtar ekranı 7 ekrana eklenmedi.

## PM'e açık sorular

1. `portal-ekran-sartnameleri.md` bölüm 11'de önerilen eksik filtre, revizyon, yerel taslak ve hata anahtarları 7B/portal metin kaynağına eklenecek mi?
2. `termin_baslangici`, `termin_birimi`, miktar birimi ve ambalaj enumlarının TR/EN/ZH kullanıcı etiketleri hangi tek kaynak dosyasında tutulacak?
3. Görev #28 kararı gereği `Alternatif var` arayüzde asıl satırın `Bulunamadı` durumuyla birlikte bağlı ayrı cevap olarak gösterildi. `rfq-alan-sozlesmesi.json` içindeki tek `yanit_durumu=alternative_available` modeli üretimde iki kayıtlı nesneye ayrılacak mı, yoksa alt nesne aynı durum kaydında mı tutulacak?
4. `VIEWED` için 5B'de ayrı kullanıcı etiketi yok; prototip şartname eşlemesine uygun olarak `status.waiting_supplier` kullanıyor. PM ayrı `status.viewed` anahtarı ister mi?

## Kaynaklar

- `docs/v3/hazirlik/v3-c/portal-ekran-sartnameleri.md`
- `docs/v3/hazirlik/v3-c/teklif-turu-durum-makinesi.md`
- `docs/v3/hazirlik/v3-c/rfq-alan-sozlesmesi.json`
- `docs/v3/hazirlik/v3-c/portal-metinleri.json`
- `docs/v3/hazirlik/v3-c/gorev-28/28-v3c-firma-dongusu-saha-gercekleri.md`
- `docs/v3/hazirlik/v3-c/gorev-28/28-ek-donus-formatlari.md`
- `docs/v3/hazirlik/cikti-terimleri.json`
- `docs/v3/tasarim-referans/paylasim-sayfasi.png`
- `docs/v3/tasarim-referans/paylasim-sayfasi-detay.png`
