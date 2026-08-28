# TedarikApp Panel — E2E Senaryo Kataloğu

**Belge türü:** Claude Code tarafından Vitest/Playwright otomasyon koduna dönüştürülecek bağlayıcı test kitabı  
**Kapsam:** Dilim 4 Keşif, Gelen Kutusu, Liste Detay, Çıktılar, paylaşım kilit ekranı ve Ayarlar  
**Test verisi:** `demo-urun-seti.json` (`DM-001`–`DM-100`)  
**Tarih:** 23 Ağustos 2026  
**Çalışma sınırı:** Salt üretim; repo yazımı ve canlı istek yapılmadı.

> **CI AĞ KURALI — BAĞLAYICI:** CI'dan gerçek dış istek yasaktır. Test süreci gerçek 1688, çeviri/kur sağlayıcısı, WhatsApp veya başka bir üçüncü taraf alan adına bağlanmaz. A sınıfında bütün istekler yerel sahte/veri katmanında tutulur; B sınıfında yalnız test paneli/API'si ile izole gerçek MySQL kullanılabilir. Dış sağlayıcı yanıtları çıkıştan önce route/adapter düzeyinde stub'lanır; beklenmeyen dış host isteği testi derhâl kırmızıya çevirir.

## 1. Kaynak ve otomasyon kararı

Bu katalog şu bağlayıcı sözleşmeleri otomasyona indirger:

- Görev #3 `eklenti-e2e-senaryo-katalogu.md` içindeki amaç → ön koşul → adımlar → beklenen sonuç → otomasyon notu disiplini;
- Görev #5A `demo-urun-seti.json`: 100 kurgusal kayıt, altı `cluster_key` kümesi, kontrollü eksikler ve TR/EN/ZH alanları;
- Görev #6 `kabul-turu-v1.md`: `KT-001`–`KT-045` manuel kabul turu; bu katalog o turu tekrar etmez;
- Görev #8 `skor-kalibrasyon-seti.json`: bant oracle'ları, sıralama kısıtları, `gizli` davranışı ve “yüksek MOQ/özel üretim skor cezası değildir” kuralı;
- Görev #5B çıktı terimleri ve Dilim 4 ekran sözleşmeleri: seçilen dilde komple içerik, K55 orijinal satır, paylaşım anahtarı ve durum geçişleri.

Üç test sınıfı şöyledir:

| Sınıf | Görev #3 karşılığı | Ne test edilir? | Veri/ağ sınırı |
|---|---|---|---|
| **A — Otomatik / sahte veri** | Otomatik — sahte sayfa | Vitest veya yerel Playwright component harness; render, klavye, hesap, istemci durumu, ağ adapter stub'ı | Gerçek DB ve dış ağ yok |
| **B — Otomatik / panel-API** | Otomatik — panel/API | Playwright + gerçek panel/API + her testte sıfırlanan gerçek MySQL; HTTP, DB ve denetim izi kanıtları | Yalnız yerel test paneli/API; dış ağ yok |
| **C — Manuel / yalnız görsel-dokunsal** | Manuel — paketlenmiş uzantı | Piksel taşması, gerçek yazı tipi/glif, yatay sürükleme ve dokunsal okunabilirlik | Demo ortamı; canlı üçüncü taraf isteği yok |

## 2. Ortak test düzeni ve oracle'lar

1. Her B senaryosu kendi transaction/namespace'inde `DM-001`–`DM-100` setini aynı seed sürümüyle yükler; senaryolar birbirine durum bırakmaz.
2. Test kullanıcısı `USR-E2E-PNL`, varsayılan liste `LST-E2E-001`, Gelen Kutusu oturumu `IBX-E2E-001` ve paylaşım sürümü `SHR-E2E-001` olarak sabitlenir.
3. İstemci saati `2026-08-23T12:00:00+03:00`, dil/yerel ayar gerektiğinde açıkça `tr-TR`, `en-US` veya `zh-CN` olur.
4. Skor oracle'ı uygulamanın ağırlıklarını kopyalamaz. Yalnız kalibrasyon setindeki bant/ikili kısıtları ve `gizli` sözleşmesini sınar. Yüksek MOQ veya özel üretim tek başına eksi puan değildir.
5. Paylaşım anahtarı fixture'ı altı hanelidir. Anahtar hash'i DB'de doğrulanabilir; açık anahtar yalnız oluşturma/yenileme yanıtı ve Paylaş penceresinde görünür, log/assertion çıktısına dökülmez.
6. Dil oracle'ı sistem etiketleri ile çevrilebilir alan değerlerini ayrı allowlist'lerle denetler. `name_zh` ve açıkça “orijinal” işaretli değerler kaynak dil istisnasıdır; marka, renk ve varyantın seçilen dil alanı varken başka dildeki karşılığı istisna sayılmaz.
7. Negatif ikizler ayrı senaryodur: `09↔10`, `25↔26`, `32↔33`, `37↔38`, `42↔43`, `47↔48` ve `50↔51`. Bir senaryonun geçmesi ikizinin sonucunu paylaşmaz.

## 3. Kapsam dizini

| Ekran grubu | Kimlik aralığı | Senaryo |
|---|---|---:|
| Keşif | `E2E-PNL-01`–`E2E-PNL-15` | 15 |
| Gelen Kutusu | `E2E-PNL-16`–`E2E-PNL-24` | 9 |
| Liste Detay | `E2E-PNL-25`–`E2E-PNL-36` | 12 |
| Çıktılar + Kilit Ekranı | `E2E-PNL-37`–`E2E-PNL-46` | 10 |
| Ayarlar | `E2E-PNL-47`–`E2E-PNL-52` | 6 |
| **Toplam** | `E2E-PNL-01`–`E2E-PNL-52` | **52** |

## 4. Keşif — 15 senaryo

### E2E-PNL-01 — Kategori + skor bandı + platform AND birleşimi

**Amaç:** Üç farklı filtre ailesinin gevşek OR yerine aynı sorguda AND olarak uygulanmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** Tam demo seed'i; `DM-003` tam verili, platformu `1688`, kategorisi `Ev Tekstili` ve kalibrasyonda `yuksek`; `DM-007` aynı kategori/platformda fakat `dusuk`; `DM-016` `yuksek` fakat `Mutfak Gereçleri`.

**Adımlar:**

1. Kategori=`Ev Tekstili`, skor bandı=`yuksek`, platform=`1688` filtrelerini sırayla seç.
2. Ağ isteğinin tek kanonik filtre sorgusunu ve dönen satırları kaydet.
3. Sayfayı aynı URL ile yenile.

**Beklenen sonuç:** Görünen her satır üç koşulu birden sağlar; `DM-003` görünür, `DM-007` ve `DM-016` görünmez. Üç aktif çip ve URL parametreleri yenileme sonrasında aynıdır; API'ye gevşek/tekrarlı sorgu gitmez.

**Otomasyon notu:** Playwright + gerçek test API/MySQL; hem DOM kimlikleri hem sorgu parametreleri assert edilir.

### E2E-PNL-02 — TR sorgu ZH yağlık kayıtlarını bulur

**Amaç:** Türkçe aramanın Çince kaynak başlıklı aynı ürün kayıtlarına eriştiğini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `DM-016`, `DM-023`, `DM-025`; üçünün `name_zh` değeri `高硼硅玻璃油壶 550ml`, TR karşılığı “Yüksek borosilikat cam yağlık 550ml” ve kümesi `CK-CAM-YAGLIK-550ML`.

**Adımlar:**

1. Görüntü dilini ZH yap.
2. Arama alanına `yüksek borosilikat cam yağlık 550ml` yazıp gönder.
3. Sonuç kimliklerini ve vurgulanan eşleşme alanını oku.

**Beklenen sonuç:** `DM-016`, `DM-023`, `DM-025` bulunur; kartlarda ZH başlık gösterilir. Türkçe sorgu Çince başlığa yazılmaz, Unicode/`550ml` bozulmaz ve alakasız `DM-034` görünmez.

**Otomasyon notu:** Gerçek MySQL arama indeksiyle; sıralama serbest, sonuç üyeliği bağlayıcıdır.

### E2E-PNL-03 — TR sorgu ZH ayakkabı kutusu kayıtlarını bulur

**Amaç:** Türkçe karakter, ölçü ve eş anlam normalizasyonunun ikinci bağımsız kümede çalıştığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `DM-060`, `DM-064`, `DM-068`; ZH başlık `抽屉式透明鞋盒 33×23×14cm`, TR başlık “Şeffaf çekmeceli ayakkabı kutusu 33×23×14cm”.

**Adımlar:**

1. Görüntü dilini ZH tut.
2. `şeffaf çekmeceli ayakkabı kutusu 33x23x14 cm` sorgusunu çalıştır.
3. Sonuçlarda ölçü ve kimlikleri kontrol et.

**Beklenen sonuç:** Üç küme üyesi bulunur; ASCII `x` ile `×` ve `cm` boşluğu normalize edilir. `DM-064` eksik fiyat kademesi nedeniyle kaybolmaz; başlığı ZH kalır.

**Otomasyon notu:** Playwright + gerçek test arama API'si; sorgu normalizasyonu ve sonuç kümesi assert edilir.

### E2E-PNL-04 — Skor kısıtı DM-003 > DM-007

**Amaç:** Güçlü satış + yüksek puan + güçlü satıcı karnesinin eski/düşük hareketli ve fiyat kademesi eksik üründen yukarıda kalmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** Kalibrasyon çifti `ustte=DM-003`, `altta=DM-007`; ikisi de `Ev Tekstili` ve `1688`.

**Adımlar:**

1. İki ürünü kimlik filtresiyle görünür kümeye al.
2. Skoru azalan sırayı seç.
3. API ve DOM sıralarını oku.

**Beklenen sonuç:** `DM-003` hem API sırası hem görünür sırada `DM-007`'nin üstündedir; skorlar eşit değildir. `DM-007`nin fiyat kademesi eksikliği gizlenmez.

**Otomasyon notu:** Ağırlık değerine değil kalibrasyon ikili kısıtına assert edilir.

### E2E-PNL-05 — Skor kısıtı DM-038 > DM-034

**Amaç:** Aynı ürün kümesinde güçlü satış + yüksek puanın yalnız yüksek puanlı fakat düşük hacimli üyeyi geçmesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `CK-KEHRIBAR-KADEH-350ML`; kalibrasyon çifti `DM-038 > DM-034`; üçüncü üye `DM-044` karşılaştırma bağlamı olarak yüklü.

**Adımlar:**

1. Küme filtresini aç.
2. Skoru azalan sırayı seç.
3. `DM-038` ve `DM-034` sıra indekslerini karşılaştır.

**Beklenen sonuç:** `DM-038` üsttedir ve eşitlik yoktur. Küme üyeliği skor yerine geçmez; `DM-044`ün konumu bu ikili oracle'ı değiştirmez.

**Otomasyon notu:** Gerçek skor çıktısı ve kalibrasyon constraint'i birlikte doğrulanır.

### E2E-PNL-06 — Skor kısıtı DM-023 > DM-025

**Amaç:** Aynı yağlık kümesinde yüksek satış hacminin düşük puanı örtüp yanlış lider üretmesini engellemek.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `CK-CAM-YAGLIK-550ML`; kalibrasyon çifti `DM-023 > DM-025`. `DM-023` 4,94 puan/14 varyant, `DM-025` 3,63 puan/2 varyant ve daha yüksek satışlıdır.

**Adımlar:**

1. Küme kartını skor azalan sıralamayla aç.
2. `DM-023` ve `DM-025` konumlarını oku.
3. Sıralamayı yeniden yükleyip kararlılığı kontrol et.

**Beklenen sonuç:** Her iki yüklemede `DM-023`, `DM-025`in üstündedir; yüksek satış tek başına düşük puan riskini bastırmaz. Varyant sayısı kısıtın gerekçesi olarak sunulmaz.

**Otomasyon notu:** API sırası ve UI sırası aynı ikili kısıtı sağlamalıdır.

### E2E-PNL-07 — 同款 yağlık kümesi açılımı

**Amaç:** 同款 kartının yalnız doğru üç üyeyi ve karşılaştırma sinyallerini göstermesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `DM-016`, `DM-023`, `DM-025`; ortak `cluster_key=CK-CAM-YAGLIK-550ML`.

**Adımlar:**

1. Yağlık 同款 kartını genişlet.
2. Üye kimlikleri, fiyat, satıcı, puan ve varyant sayısı sütunlarını oku.
3. Kartı kapatıp yeniden aç.

**Beklenen sonuç:** Tam üç ve yalnız bu kimlikler görünür; değerler kaynak kayıtlarla eşleşir. Aç/kapa yeni API kaydı veya yinelenen DOM satırı üretmez.

**Otomasyon notu:** Playwright + gerçek DB; DOM'da kimlik tekilliği zorunludur.

### E2E-PNL-08 — 同款 ayakkabı kutusu kümesi ve eksik veri işareti

**Amaç:** İkinci kümede üyelik ile üye-özel eksik veri bilgisinin karışmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `DM-060`, `DM-064`, `DM-068`; ortak `CK-CEKMECELI-AYAKKABI-33X23`; yalnız `DM-064`te `price_tiers` eksik.

**Adımlar:**

1. Ayakkabı kutusu 同款 kartını aç.
2. Üç üyenin uyarı/puan alanlarını karşılaştır.
3. `DM-064` uyarısını aç.

**Beklenen sonuç:** Tam üç doğru üye vardır; yalnız `DM-064` “fiyat kademesi eksik” uyarısı taşır. `DM-068`in 24 varyantı korunur; eksik alan sıfır fiyat olarak gösterilmez.

**Otomasyon notu:** Gerçek DB ve görünür satır verileri birlikte assert edilir.

### E2E-PNL-09 — Karşılaştırma matrisi 2–6 ürün

**Amaç:** Matrisin asgari iki üründen azami altı ürüne kadar sütunları kayıpsız ve seçme sırasıyla kurmasını kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Keşif / Karşılaştırma

**Ön koşul / hazırlık:** Sahte store'da sırasıyla `DM-016`, `DM-023`, `DM-025`, `DM-034`, `DM-038`, `DM-044`.

**Adımlar:**

1. İlk iki ürünü karşılaştırmaya ekle ve matrisi aç.
2. Kalan dört ürünü birer birer ekle.
3. Her eklemede sütun başlıkları ile fiyat/puan/MOQ/satıcı/eksik alan satırlarını oku.

**Beklenen sonuç:** İki üründe matris açılır; altıya kadar her seçim tam bir sütun ekler, öncekileri değiştirmez ve kimlik tekilliğini korur. Yüksek MOQ yalnız değer/bilgi olarak görünür, kalite/skor eksi işareti almaz.

**Otomasyon notu:** Vitest component veya yerel Playwright harness; ağ çağrısı yoktur.

### E2E-PNL-10 — Yedinci karşılaştırma ürünü reddedilir

**Amaç:** Altı ürün sınırının sessiz taşma veya ilk ürünü düşürme yerine açık ve geri alınabilir biçimde uygulanmasını kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Keşif / Karşılaştırma

**Ön koşul / hazırlık:** Sahte store'da seçili `DM-016`, `DM-023`, `DM-025`, `DM-034`, `DM-038`, `DM-044`; yedinci aday `DM-048`. Test seed'i bağımsız kurulur.

**Adımlar:**

1. `DM-048` için karşılaştırmaya ekle eylemini tetikle.
2. Uyarıyı, seçili ürün sayısını ve matris sütunlarını oku.

**Beklenen sonuç:** “En fazla 6 ürün” uyarısı görünür; seçili sayı ve sütunlar altıda kalır. İlk altı üründen hiçbiri sessizce çıkarılmaz, store/API mutasyonu oluşmaz.

**Otomasyon notu:** E2E-PNL-09'un negatif ikizidir; ayrı component testi olarak kodlanır.

### E2E-PNL-11 — Altı sütunlu matrisin görsel ve dokunsal okunabilirliği

**Amaç:** Otomatik DOM doğruluğunun yakalayamadığı yatay sürükleme, sabit başlık ve hücre taşması sorunlarını gerçek render'da elemek.

**Sınıf:** C — Manuel / yalnız görsel-dokunsal  
**Ekran:** Keşif / Karşılaştırma

**Ön koşul / hazırlık:** Seçili `DM-016`, `DM-023`, `DM-025`, `DM-034`, `DM-038`, `DM-044`; desteklenen masaüstü ve dar dizüstü genişliği; yerel demo build.

**Adımlar:**

1. Matrisi iki viewport'ta aç.
2. Yatay kaydır/sürükle; sabit ürün ve satır başlıklarını izle.
3. Uzun ZH/TR adları, MOQ birimi ve uyarı rozetlerini görsel kontrol et.

**Beklenen sonuç:** Sütunlar üst üste binmez, başlık bağlamı kaybolmaz, dokunma/trackpad kaydırması takılmaz ve odak görünür kalır. İçerik kırpılması ticari değeri belirsizleştirmez.

**Otomasyon notu:** Manuel-yalnız görsel/dokunsal kayıt; işlevsel 2–6 sınırı E2E-PNL-09/10'da otomatik kanıtlanır.

### E2E-PNL-12 — Kaydedilmiş görünüm ve URL durumunun çift yönlü eşliği

**Amaç:** Kaydedilmiş görünümün filtre/sıra/kolon durumunu kanonik URL ile aynı sözleşmede yeniden kurmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `USR-E2E-PNL`; filtreler kategori=`Banyo`, skor=`yuksek`, platform=`1688`; arama `sabunluk`; sıralama skor azalan; örnek kayıtlar `DM-048`, `DM-053`, `DM-057`.

**Adımlar:**

1. Durumu kurup “Görünümü kaydet” ile `Banyo güçlüler` adını ver.
2. Filtreleri değiştir ve kayıtlı görünümü yeniden aç.
3. Üretilen URL'yi yeni temiz sekmede aç.

**Beklenen sonuç:** Kayıt tek kullanıcıya ait tek DB satırıdır; görünüm ve URL aynı filtre/arama/sıra/kolon durumunu kurar. `DM-057` skor `gizli` olduğu için sonuçtan dışlanır; geri/ileri tarayıcı geçişi durumu bozmadan çalışır.

**Otomasyon notu:** Playwright + gerçek DB; kaydedilmiş görünüm JSON'u ile URL canonicalization karşılaştırılır.

### E2E-PNL-13 — Boş sonuç durumu ve filtreyi temizleme

**Amaç:** Sonuçsuz sorgunun hata veya boş beyaz alan yerine erişilebilir bir toparlanma yolu vermesini kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Keşif

**Ön koşul / hazırlık:** Sahte store'da `DM-001`–`DM-003`; sorgu `bulunmayacak-dmx-999`, aktif kategori çipi `Ev Tekstili`.

**Adımlar:**

1. Sonuç vermeyen sorguyu çalıştır.
2. Boş durum başlığı, açıklama ve “Filtreleri temizle” eylemini oku.
3. Temizleme eylemini tetikle.

**Beklenen sonuç:** Sıfır sonuç ve etkin koşullar açıklanır; tablo başlığı/hayalet satır gösterilmez. Temizlemeden sonra üç fixture kaydı geri gelir, arama ve filtre URL'den kalkar.

**Otomasyon notu:** Vitest/Playwright component; erişilebilir ad ve odak dönüşü assert edilir.

### E2E-PNL-14 — Sanal kaydırmada 100 ürünün tekilliği

**Amaç:** Sanal liste/sayfalamanın sınır geçişlerinde kayıt atlamadığını veya yinelemediğini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** Tam `DM-001`–`DM-100`; kimliğe göre artan kararlı ikincil sıra; ağ sayacı.

**Adımlar:**

1. Listenin başından sonuna kontrollü kaydır.
2. Her sayfa/cursor yanıtındaki ve render edilen `data-demo-id` değerlerini topla.
3. Son kayıttan yukarı dönüp ilk kaydı yeniden görünür yap.

**Beklenen sonuç:** Birleşik kimlik kümesi tam `DM-001`–`DM-100` ve 100 adettir; API cursor'ları yinelenmez, boş ara sayfa yoktur. DOM pencereleme eski düğümleri kaldırabilir fakat aynı anda yinelenen kimlik üretmez; `DM-001` ve `DM-100` erişilebilir.

**Otomasyon notu:** Gerçek API/MySQL ve Playwright scroll; yalnız DOM düğüm sayısı değil toplanan kimlik kümesi oracle'dır.

### E2E-PNL-15 — Eksik metrikte skor GİZLİ

**Amaç:** Temel skor girdisi eksik ürüne sayı, bant, yıldız veya sıralama değeri uydurulmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Keşif

**Ön koşul / hazırlık:** `DM-029` ve `DM-057` için `metrics={}` ve `missing=["metrics"]`; kalibrasyon bandı `gizli`. `DM-053` tam verili kontrol kaydıdır.

**Adımlar:**

1. Üç kimliği birlikte aç.
2. Kart, tablo ve 同款 açılımındaki skor alanlarını oku.
3. Skor azalan sırayı ve skor bandı filtresini uygula.

**Beklenen sonuç:** `DM-029` ve `DM-057` yalnız “Skor için veri yetersiz” durumunu gösterir; DOM/API'de sayısal skor veya renkli bant yoktur ve skor bandı sonuçlarına katılmaz. `DM-053` normal puanlanır. Gizli ürünün sıralamaya sokulmuş sentinel değeri bulunmaz.

**Otomasyon notu:** Kalibrasyon sözleşmesindeki `%100 skor göstermeme` oracle'ı; API JSON'u ve görünür UI birlikte sınanır.

## 5. Gelen Kutusu — 9 senaryo

### E2E-PNL-16 — J/K ile deste odağı, mutasyonsuz gezinme

**Amaç:** Deste modunda `J`/`K` tuşlarının yalnız kart odağını değiştirdiğini, ürün durumunu kendiliğinden değiştirmediğini kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Gelen Kutusu / Deste modu

**Ön koşul / hazırlık:** Sıralı sahte deste `DM-001`, `DM-002`, `DM-003`; ilk kart odakta; eylem store'u ve çağrı sayaçları sıfır.

**Adımlar:**

1. `J` gönder ve odaklanan kartı oku.
2. Bir `J`, ardından iki `K` gönder.
3. Seçim, durum ve eylem çağrı sayaçlarını kontrol et.

**Beklenen sonuç:** Odak sınırlar içinde kararlı ilerler/geri döner; sayaç ve kart sırası değişmez. Havuz/liste/çöp mutasyonu ve API çağrısı yoktur; odak halkası görünür ve erişilebilir kart adı güncellenir.

**Otomasyon notu:** Component harness; fiziksel tuş olayları kullanılır, doğrudan store metodu çağrılmaz.

### E2E-PNL-17 — Space ile seçim aç/kapa

**Amaç:** `Space` tuşunun odaktaki kart seçimini tek kez değiştirdiğini ve sayfayı kaydırmadığını kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Gelen Kutusu / Deste modu

**Ön koşul / hazırlık:** `DM-001` odakta, seçili ürün yok; tarayıcı scroll konumu sabit.

**Adımlar:**

1. `Space` gönder.
2. Seçim rozeti ve toplu işlem sayacını oku.
3. Yeniden `Space` gönder.

**Beklenen sonuç:** İlk basışta yalnız `DM-001` seçilir ve sayaç `1` olur; ikinci basışta seçim kalkar ve sayaç `0` olur. Sayfa kaymaz, kart eylemi veya API isteği oluşmaz.

**Otomasyon notu:** Yerel Playwright component; `preventDefault`, aria-selected ve store sayacı assert edilir.

### E2E-PNL-18 — Ok tuşlarıyla Çöpe/Havuza/Listeye üçlüsü

**Amaç:** Deste klavye eşlemesinin üç hedefi doğru API komutuna ve tek DB geçişine dönüştürdüğünü kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Gelen Kutusu / Deste modu

**Ön koşul / hazırlık:** `IBX-E2E-001` içinde `DM-001`, `DM-002`, `DM-003`; test keymap sözleşmesi ekranda görünür: sol ok=`Çöpe`, aşağı ok=`Havuza`, sağ ok=`Listeye`; hedef `LST-E2E-001`.

**Adımlar:**

1. `DM-001`de sol ok, `DM-002`de aşağı ok, `DM-003`te sağ ok gönder.
2. Her geçişten sonra kart sayacı ve sıradaki kartı oku.
3. API isteklerini ve üç ürünün DB durumunu sorgula.

**Beklenen sonuç:** Her tuş tam bir doğru komut üretir; `DM-001` çöp, `DM-002` havuz, `DM-003` listede ve her biri kaynak desteden tek kez düşmüştür. `DM-003` listede bir satırdır; çapraz hedef veya ek istek yoktur.

**Otomasyon notu:** Playwright + gerçek API/MySQL; görünür sonuç, HTTP çağrı sayısı ve DB satırı birlikte kanıttır.

### E2E-PNL-19 — Son deste eylemini geri alma

**Amaç:** Geri almanın ürünü tam önceki konum/durumuna döndürdüğünü ve hedefte artık bırakmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Gelen Kutusu / Deste modu

**Ön koşul / hazırlık:** `DM-003` test kurulumu içinde bir kez “Listeye” geçirilmiş; önceki deste sıra indeksi ve `IBX-E2E-001` oturum kimliği kayıtlı. Başka senaryonun bıraktığı durum kullanılmaz.

**Adımlar:**

1. Görünür geri al eylemini bir kez tetikle.
2. Deste sırasını, liste satırını ve sayaçları kontrol et.
3. Geri al düğmesine ikinci kez basmayı dene.

**Beklenen sonuç:** `DM-003` aynı oturumdaki önceki sıra indeksine döner; `LST-E2E-001` satırı kalkar ve sayaçlar eski hâline gelir. İkinci tetik etkisiz/kapalıdır; negatif sayaç veya yinelenen ürün oluşmaz.

**Otomasyon notu:** Gerçek transaction ve audit olayı assert edilir; test E2E-PNL-18'e durum bağımlı çalıştırılmaz.

### E2E-PNL-20 — Kural rozeti ve kural eylemini geri alma

**Amaç:** Otomatik/yarı otomatik kural kaynağının görünür kaldığını ve geri almayla birlikte temizlendiğini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Gelen Kutusu

**Ön koşul / hazırlık:** `DM-002`; test kuralı `Ev Tekstili → Havuza`, sürüm `rule-v1`; ürün başlangıçta destede ve rozetsiz.

**Adımlar:**

1. Kuralı `DM-002` üzerinde çalıştır.
2. Rozetin adını, sürümünü ve sonucu aç.
3. Rozet menüsünden “Geri al”ı tetikle.

**Beklenen sonuç:** İlk işlemde ürün havuza geçer ve kural rozeti/audit kaynağı görünür. Geri almada ürün desteye döner, aktif rozet kalkar fakat audit geçmişi “geri alındı” olarak korunur; ikinci kayıt doğmaz.

**Otomasyon notu:** API/DB audit satırı ile UI rozeti birlikte doğrulanır.

### E2E-PNL-21 — Oturum grubunda toplu işlem atomikliği

**Amaç:** Aynı yakalama oturumundaki bir grubun topluca listeye alınırken eksik/yinelenen üye bırakmamasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Gelen Kutusu

**Ön koşul / hazırlık:** Aynı oturum grubunda yağlık kümesi `DM-016`, `DM-023`, `DM-025`; hedef `LST-E2E-001`; liste başlangıçta boş.

**Adımlar:**

1. Oturum grubunu seç.
2. Toplu “Listeye” eylemini bir kez tetikle.
3. Grup, liste ve audit kayıtlarını sorgula.

**Beklenen sonuç:** Üç ürün tek transaction sonucu desteden çıkar ve listede üç tekil satır olur; grup sayacı sıfırlanır. Eylem sonucu `3 başarılı / 0 başarısız`dır; kısmi görünür ara durum veya tekil istek fırtınası yoktur.

**Otomasyon notu:** Playwright + gerçek MySQL; ürün sayısı, unique anahtar ve transaction sonucu assert edilir.

### E2E-PNL-22 — Eksik ürün uyarı rozetleri

**Amaç:** Kontrollü eksik alanların sıfır/“yok” değere çevrilmeden doğru ürün ve alan adıyla gösterilmesini kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Gelen Kutusu

**Ön koşul / hazırlık:** `DM-007` `price_tiers`, `DM-015` `package.cbm`, `DM-029` `metrics` eksik; `DM-001` tam kontrol kaydı.

**Adımlar:**

1. Dört kartı sırayla render et.
2. Uyarı rozetini ve açılır açıklamasını oku.
3. Alan değerlerinin sunumunu kontrol et.

**Beklenen sonuç:** İlk üç kart sırasıyla “Fiyat kademesi eksik”, “CBM eksik”, “Skor metrikleri eksik” uyarısı taşır; `DM-001` taşımaz. Eksik alan `0`, `0,00` veya “video yok” gibi türetilmiş değere dönüşmez.

**Otomasyon notu:** Vitest parametrik component testi; DM fixture'ları değiştirilmeden kullanılır.

### E2E-PNL-23 — Filtre veya sayfa değişiminde seçim sıfırlama

**Amaç:** Görünmeyen eski seçimlerin yeni bağlamda toplu işleme sızmasını engellemek.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Gelen Kutusu

**Ön koşul / hazırlık:** İki sayfalı sahte sonuç; ilk sayfada `DM-001`, `DM-002`, ikinci sayfada `DM-003`, `DM-004`; kategori filtresi kullanılabilir.

**Adımlar:**

1. İlk sayfada `DM-001` ve `DM-002`yi seç.
2. İkinci sayfaya geç; geri dön.
3. Yeniden seçip kategori filtresini değiştir.

**Beklenen sonuç:** Her sayfa/filtre bağlam değişiminde seçim kümesi ve toplu sayaç sıfırlanır; gizli ürün kimliği store'da kalmaz. Odak yeni sonucun ilk kartına taşınır; API mutasyonu yoktur.

**Otomasyon notu:** Component/router harness; store snapshot'ları ve erişilebilir odak assert edilir.

### E2E-PNL-24 — Çift tık mükerrer işlem üretmez

**Amaç:** Hızlı çift tıklama veya click+Enter yarışının listeye iki satır/iki audit olayı yazmasını engellemek.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Gelen Kutusu

**Ön koşul / hazırlık:** Destede `DM-001`; hedef liste boş; yanıt kontrollü 300 ms gecikmeli; idempotency anahtarı gözlenebilir.

**Adımlar:**

1. “Listeye” düğmesine çift tıkla ve yanıt gelmeden `Enter` gönder.
2. İstekleri, toast'ları ve DB satırlarını say.
3. Sayfayı yenileyip iki ekranı tekrar sorgula.

**Beklenen sonuç:** Tek mantıksal komut/idempotency anahtarı vardır; listede `DM-001` tek satır, audit'te tek başarılı geçiş bulunur. Deste bir azalır; ikinci bildirim veya geçici yinelenen kart oluşmaz.

**Otomasyon notu:** Playwright + gerçek API/MySQL; istemci kilidi ile sunucu idempotency'si ayrı assert edilir.

## 6. Liste Detay — 12 senaryo

### E2E-PNL-25 — Satır içi düzenleme ve Enter akışı

**Amaç:** Geçerli satır içi değerin Enter ile kaydedilip odağın bir sonraki düzenlenebilir hücreye geçmesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** `LST-E2E-001` içinde `DM-001`, `DM-011`, `DM-016`; liste İletildi değildir; `DM-001` adet alanı düzenlenebilir ve başlangıç değeri `3`.

**Adımlar:**

1. `DM-001` adet hücresini açıp `12` yaz.
2. `Enter` gönder.
3. Odak, API yanıtı, DB değeri ve canlı toplamı kontrol et.

**Beklenen sonuç:** Değer bir kez `12` olarak kaydedilir; odak aynı satırdaki sözleşmede tanımlı sonraki hücreye geçer. Satır kaydedildi durumunu gösterir, toplam tek kez güncellenir ve yenilemede `12` korunur.

**Otomasyon notu:** Playwright + gerçek API/MySQL; klavye, PATCH sayısı ve kalıcı değer birlikte assert edilir.

### E2E-PNL-26 — Geçersiz satır içi değer kaydedilmez

**Amaç:** Geçersiz miktarın Enter ile sessizce sıfıra/önceki değere çevrilmeden reddedilmesini kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** `DM-001`; başlangıç adet `3`; adapter spy; değer `0` ve ardından `abc` parametrik olarak uygulanır.

**Adımlar:**

1. Hücreye geçersiz değeri yazıp `Enter` gönder.
2. Hata, odak ve store değerini oku.
3. `Esc` ile düzenlemeyi kapat.

**Beklenen sonuç:** Alan-özel hata görünür, odak hücrede kalır ve kayıt adapter'ı çağrılmaz. `Esc` sonrası önceki `3` değeri görünür; toplam değişmez.

**Otomasyon notu:** E2E-PNL-25'in negatif ikizi; Vitest/Playwright component testi.

### E2E-PNL-27 — Eksik CBM ürünü HAZIR olamaz

**Amaç:** HAZIR kapısının zorunlu lojistik eksikliği olan ürünü durum değiştirmeden engellemesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** Listede `DM-015`; `missing=["package.cbm"]`; ürün HAZIR öncesi durumda.

**Adımlar:**

1. `DM-015` için HAZIR eylemini tetikle.
2. UI uyarısını ve HTTP yanıtını oku.
3. Ürün durumu/audit kaydını DB'den kontrol et.

**Beklenen sonuç:** Kapı “CBM eksik” alanını bildirir; HTTP doğrulama yanıtı başarı değildir ve ürün HAZIR olmaz. Önceki durum değişmez, başarılı geçiş audit'i ve çıktı/paylaşım yan etkisi oluşmaz.

**Otomasyon notu:** Sunucu kuralı asıl oracle'dır; düğme kapalı olsa bile doğrudan API denemesi de reddedilmelidir.

### E2E-PNL-28 — Boş liste tamamlanamaz

**Amaç:** İstemci ve API'nin sıfır ürünlü listeyi son aşamaya geçirmemesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** Yeni boş `LST-E2E-EMPTY`; DM verisi kullanılmaz, referans `DM-001` listeye eklenmemiştir; çıktı ve paylaşım sürümü yok.

**Adımlar:**

1. “Tamamla” eylemini UI'dan dene.
2. Aynı geçişi test istemcisiyle API'ye gönder.
3. Liste, çıktı ve paylaşım tablolarını sorgula.

**Beklenen sonuç:** Anlaşılır “Listeye en az bir ürün ekleyin” engeli vardır; API de geçişi reddeder. Liste tamamlanmış olmaz; Excel/PDF/paylaşım sürümü veya audit başarı kaydı üretilmez.

**Otomasyon notu:** Playwright + doğrudan yerel API doğrulaması + gerçek DB.

### E2E-PNL-29 — Uyarı çipi ilgili eksik alana filtreler

**Amaç:** Özet uyarı çipinin doğru alt eksiklik filtresini kurup yalnız eşleşen satırları göstermesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** Listede `DM-001` tam, `DM-015` ve `DM-085` `package.cbm` eksik, `DM-007` `price_tiers` eksik.

**Adımlar:**

1. “Eksik CBM · 2” çipine tıkla.
2. Görünen satırlar ve URL durumunu oku.
3. Çipi temizle.

**Beklenen sonuç:** Yalnız `DM-015` ve `DM-085` görünür; `DM-001` ve farklı eksikliğe sahip `DM-007` görünmez. URL alt alanı açıkça taşır; temizlemede dört satır geri gelir.

**Otomasyon notu:** Gerçek API/MySQL; sayaç ile sonuç kümesi aynı DB predicate'ından doğrulanır.

### E2E-PNL-30 — İletildi sonrası kur kilidi

**Amaç:** İletilmiş listenin bağlı kur sürümünün UI veya API ile yerinde değiştirilememesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** `LST-E2E-001` durumu `İLETİLDİ`; ürünler `DM-001`, `DM-011`; onaylı kur sürümü `FX-LOCK-1` ve oran `4.50`.

**Adımlar:**

1. Kur alanını düzenlemeyi dene.
2. Farklı kur sürümünü doğrudan API ile bağlamayı dene.
3. Tutar, kur kimliği ve revision'ı yeniden oku.

**Beklenen sonuç:** Alan kilitli ve gerekçesi görünür; API değişikliği conflict/validation ile reddeder. `FX-LOCK-1`, hesaplanan tutarlar ve revision değişmez; sessiz yeniden hesaplama yoktur.

**Otomasyon notu:** UI kilidi tek başına yeterli değildir; sunucu immutability ve DB değeri zorunlu kanıttır.

### E2E-PNL-31 — Toplu işlem çubuğu ve canlı toplam

**Amaç:** Seçim ve adet değişimlerinin toplu çubuk sayısı ile finansal toplamı deterministik güncellediğini kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** `DM-001`, `DM-011`, `DM-016`; fiyatlar demo kaydından, adetler `3/1500/18`, sabit test kuru `4.50`; Decimal hesap adapter'ı.

**Adımlar:**

1. `DM-001` ve `DM-016`yı seç.
2. Çubuk sayısı ve seçili ara toplamı oku.
3. `DM-001` adedini `12` yap, sonra seçimi temizle.

**Beklenen sonuç:** Çubuk `2 ürün` ve yalnız seçili satırların yuvarlama sözleşmesine uygun toplamını gösterir; adet değişiminde toplam bir kez güncellenir. Seçim temizlenince çubuk kapanır, genel liste toplamı yeni değeri korur; float sürüklenmesi yoktur.

**Otomasyon notu:** Vitest'te Decimal oracle ve component render birlikte sınanır; yüksek MOQ özel ceza/indirim üretmez.

### E2E-PNL-32 — İzin verilen aşama geçişleri

**Amaç:** Tam verili ürünün yalnız sözleşmede izin verilen bir sonraki aşamalar üzerinden HAZIR ve İLETİLDİ durumlarına ilerlemesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** Tam verili `DM-001`; liste boş değildir; onaylı kur vardır; ürün HAZIR öncesi son izinli aşamadadır.

**Adımlar:**

1. Aşama çubuğundaki etkin sonraki adımla ürünü HAZIR yap.
2. Paylaşım/iletim ön koşulunu fixture ile tamamlayıp İLETİLDİ geçişini yap.
3. Çubuk, özet sayaç ve audit sırasını oku.

**Beklenen sonuç:** İki geçiş sıralı ve birer kez kaydolur; geçmiş aşamalar tamamlanmış, mevcut aşama tek, ileri aşama kurala uygun görünür. Özet sayıları ve audit zamanları aynı sırayı izler; `DM-001` tek satır kalır.

**Otomasyon notu:** Gerçek transition service ve DB audit'i; E2E-PNL-27'nin tam verili pozitif karşılığıdır.

### E2E-PNL-33 — Aşama atlama reddi

**Amaç:** HAZIR kapısını atlayarak doğrudan İLETİLDİ durumuna geçişin UI ve API'de engellenmesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** `DM-001` HAZIR öncesi durumda; iletim için belge/paylaşım sürümü yok.

**Adımlar:**

1. İleri aşama düğmesinin etkinliğini kontrol et.
2. Doğrudan `İLETİLDİ` geçişini test API istemcisiyle gönder.
3. Durum, audit ve çıktı tablolarını sorgula.

**Beklenen sonuç:** UI atlama eylemi sunmaz; API geçersiz geçişi reddeder. Durum/revision değişmez, audit başarı kaydı ve çıktı/paylaşım yan etkisi yoktur.

**Otomasyon notu:** E2E-PNL-32'nin negatif ikizi; sunucu tarafı transition guard zorunludur.

### E2E-PNL-34 — Revizyon numarası tek artar ve önceki sürüm korunur

**Amaç:** İletilmiş liste için revizyon başlatmanın aynı listeyi ezmeden yeni sürüm üretmesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay

**Ön koşul / hazırlık:** `LST-E2E-001` revision `1`, durum `İLETİLDİ`; ürünler `DM-001`, `DM-016`; paylaşım sürümü revision 1'e bağlı.

**Adımlar:**

1. “Revizyon başlat”ı bir kez tetikle.
2. Açılan revizyonda `DM-016` için not alanını değiştir.
3. Eski paylaşım sürümü, yeni revision ve ürün satırlarını sorgula.

**Beklenen sonuç:** Aktif çalışma revision `2` olur; iki hızlı tetik tek revision üretir. Revision 1 salt okunur ve eski paylaşımına bağlı kalır; revision 2 değişikliği yalnız yeni sürümdedir, ürünler yinelenmez.

**Otomasyon notu:** Playwright + gerçek DB version/audit tabloları; unique revision constraint gözlenir.

### E2E-PNL-35 — Paylaş penceresinde link + anahtar birlikte görünür ve kopyalanır

**Amaç:** Paylaş penceresinin oluşturma anında link ile altı haneli anahtarı aynı güvenli görünümde, ayrı kopyalama eylemleriyle sunmasını kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Liste Detay / Paylaş penceresi

**Ön koşul / hazırlık:** `DM-001` ve `DM-016` içeren `LST-E2E-001` HAZIR; sahte oluşturma yanıtı `{link:"https://panel.test/s/SHR-E2E-001", anahtar:"246810"}`; clipboard spy.

**Adımlar:**

1. Paylaş penceresini aç.
2. Linki ve anahtarı görünürlük/etiket açısından kontrol et.
3. Linki kopyala, ardından anahtarı kopyala.

**Beklenen sonuç:** İki değer aynı pencerede görünür; anahtar tam altı hanedir. İlk eylem clipboard'a yalnız linki, ikinci yalnız `246810`u yazar; başarı geri bildirimi hangi değerin kopyalandığını söyler. Anahtar URL query'sine eklenmez.

**Otomasyon notu:** Playwright component + clipboard izni/spi; gerçek paylaşım açılmaz.

### E2E-PNL-36 — Anahtar yenileme ve eski anahtarın 401 olması

**Amaç:** Anahtar yenilemenin aktif paylaşım linkini korurken eski anahtarı atomik olarak geçersiz kılmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Liste Detay / Paylaş penceresi

**Ön koşul / hazırlık:** `DM-016` içeren `SHR-E2E-001`; eski fixture anahtarı `246810`; paylaşım revision 1 ve link aktif.

**Adımlar:**

1. “Anahtarı yenile”yi onayla ve yeni altı haneyi oku.
2. Eski anahtarla kilit açma POST'u gönder.
3. Yeni anahtarla aynı linkte kilidi aç; DB hash/audit'i kontrol et.

**Beklenen sonuç:** Link değişmez, yeni anahtar eskiden farklıdır ve pencerede bir kez görünür. Eski anahtar sabit hata gövdesiyle `401`, yeni anahtar başarı verir; aynı anda iki aktif hash yoktur ve yenileme audit'i tek kayıttır.

**Otomasyon notu:** Gerçek yerel paylaşım API/MySQL; açık anahtar test loguna yazdırılmaz.

## 7. Çıktılar + Kilit Ekranı — 10 senaryo

### E2E-PNL-37 — Paylaşım sayfası TR/EN/ZH komple tek dil

**Amaç:** Paylaşım sayfasında sistem metinleri kadar çevrilebilir alan değerlerinin de seçilen tek dilde olduğunu kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Paylaşım sayfası

**Ön koşul / hazırlık:** Paylaşımda `DM-001`, `DM-016`, `DM-023`; `DM-023` ilk varyantı `奶油白 / 小号` / `Krem beyaz / Küçük` / `Cream white / Small`; kontrollü marka alanı ZH/TR/EN `无品牌` / `Markasız` / `Unbranded`; locale'ler `tr`, `en`, `zh`.

**Adımlar:**

1. Aynı paylaşım revision'ını sırayla TR, EN ve ZH parametresiyle aç.
2. Sayfa başlığı, kolonlar, durumlar, uyarılar, dipnotlar, marka, renk ve varyant değerlerini çıkar.
3. Her locale için izinli terim/alan değeri kümesine karşı tam metin taraması yap.

**Beklenen sonuç:** Her render komple seçilen dildedir; TR'de `Markasız`, `Krem beyaz / Küçük`; EN'de `Unbranded`, `Cream white / Small`; ZH'de `无品牌`, `奶油白 / 小号` görünür. Başka dilin sistem etiketi veya çevrilebilir alan değeri bulunmaz. Yalnız açıkça “orijinal” etiketli kaynak alanı ZH kalabilir; bu istisna etiketli ve sınırlıdır.

**Otomasyon notu:** Üç locale parametrik Playwright testi; `cikti-terimleri.json` anahtarları ve alan-değeri allowlist'i oracle'dır.

### E2E-PNL-38 — Karışık dil sızıntısı KIRMIZI

**Amaç:** Çevrilebilir tek bir alan değerinin bile yanlış dilde kalmasının otomatik red üretmesini kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Paylaşım sayfası / locale denetleyicisi

**Ön koşul / hazırlık:** `DM-023` için bağımsız EN projection; yalnız renk alanı kasıtlı olarak `Krem beyaz`, ardından durum etiketi kasıtlı olarak TR bırakılan iki alt fixture.

**Adımlar:**

1. Bozuk EN projection'ı locale denetleyicisine ver.
2. Hata listesindeki alan yolu, bulunan metin ve beklenen locale'i oku.
3. Doğru `Cream white`/EN durumuyla yeniden çalıştır.

**Beklenen sonuç:** Her bozuk alt fixture sonucu `KIRMIZI`dır ve kesin alan yolunu bildirir; “değer olduğu için çevrilmez” muafiyeti uygulanmaz. Düzeltilmiş projection yeşile döner. Orijinal kaynak alanı allowlist dışında genişletilemez.

**Otomasyon notu:** E2E-PNL-37'nin negatif locale ikizi; Vitest'te deterministik dil-lint testi.

### E2E-PNL-39 — K55 orijinal satır üç dilde aynen korunur

**Amaç:** Çıktı dili değişse de referans şablondaki K55 orijinal satırının kaynak Çince değerini bozmamasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Excel çıktısı

**Ön koşul / hazırlık:** `DM-016`; kaynak `demo_id=DM-016`, `name_zh=高硼硅玻璃油壶 550ml`; aynı liste revision'ından TR/EN/ZH Excel.

**Adımlar:**

1. Üç Excel'i yerel çıktı endpoint'inden üret.
2. Her workbook'ta K55 “orijinal” hücre/satırını biçimlendirilmemiş değer olarak oku.
3. Değeri UTF-8 kaynak ve ürün kimliğiyle karşılaştır.

**Beklenen sonuç:** Üç dosyada K55 kaynak değeri tam `高硼硅玻璃油壶 550ml`dir; `550ml`, karakterler ve `DM-016` bağı değişmez. Çevrilmiş ürün adı kendi seçili-dil alanında bulunur; K55'in yerine yazılmaz.

**Otomasyon notu:** XLSX parser ile hücre değeri assert edilir; ekran görüntüsü veya gözle okuma oracle değildir.

### E2E-PNL-40 — Excel/PDF başlık-durum-dipnot dili

**Amaç:** Üç dildeki Excel ve PDF belgelerinin başlık, durum ve dipnot sözlüklerini eksiksiz ve tek dilde üretmesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Çıktılar

**Ön koşul / hazırlık:** `DM-001`, `DM-011`, `DM-016`, `DM-023`, `DM-029`, `DM-060`; aynı revision; TR/EN/ZH için toplam altı belge; `DM-029` metrik eksik.

**Adımlar:**

1. Üç Excel ve üç PDF'i dış ağ kullanmadan yerel API'den üret.
2. XLSX XML/hücrelerini ve PDF metin katmanını çıkar.
3. Başlık, durum, finans özeti, uyarı ve dipnot anahtarlarını locale sözlüğüyle; ürün kimliklerini kaynak listeyle karşılaştır.

**Beklenen sonuç:** Altı dosya açılabilir ve altı ürünü tekil içerir. Her belgenin sistem başlık/durum/dipnotları yalnız seçilen dildedir; `DM-029` eksik metriği seçilen dilde “mevcut değil” anlamında gösterilir, `0` veya sahte skor üretilmez. Excel/PDF veri kümeleri birbirine eşdeğerdir.

**Otomasyon notu:** Gerçek çıktı servisi + MySQL; XLSX parser ve PDF text extraction CI'da çalışır, dış font/asset isteği bloklanır.

### E2E-PNL-41 — Kilit ekranının dar görünüm ve %200 yakınlaştırma kullanımı

**Amaç:** Otomatik form doğruluğunun yakalayamadığı altı haneli giriş, dokunma hedefi ve hata alanı çakışmalarını gerçek render'da elemek.

**Sınıf:** C — Manuel / yalnız görsel-dokunsal  
**Ekran:** Paylaşım kilit ekranı

**Ön koşul / hazırlık:** `DM-016` içeren `SHR-E2E-001`; 320 CSS px dar viewport ve masaüstünde %200 tarayıcı yakınlaştırma; yalnız yerel demo build.

**Adımlar:**

1. Kilit ekranını iki görünümde aç; altı haneli alana dokun/odaklan.
2. Sanal/fiziksel klavyeyle altı hane gir, düzelt ve gönder.
3. Sabit hata alanı görünürken tekrar dene; sayfayı dikey kaydır.

**Beklenen sonuç:** Giriş, gönder düğmesi, yardım metni ve hata alanı üst üste binmez; yatay kaydırma gerekmez. Dokunma hedefleri yanlış komşu eylemi tetiklemez, odak halkası görünür ve sanal klavye ana eylemi erişilemez bırakmaz.

**Otomasyon notu:** Manuel-yalnız görsel/dokunsal kayıt; doğru/yanlış anahtar işlevi E2E-PNL-42/43'te otomatik sınanır.

### E2E-PNL-42 — Doğru anahtar içerik açar

**Amaç:** Aktif paylaşımın doğru altı haneli anahtarla tek oturum kurup doğru revision'ı göstermesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Paylaşım kilit ekranı

**Ön koşul / hazırlık:** `SHR-E2E-001`, aktif hash'in açık fixture karşılığı `246810`, içerik `DM-016`, revision 1; temiz cookie/session.

**Adımlar:**

1. Linki aç ve `246810` gir.
2. Formu gönder; yönlendirme, cookie/session ve görünür ürün kimliğini oku.
3. İçerik URL'sini bir kez yenile.

**Beklenen sonuç:** Tek başarılı doğrulama sonrası revision 1 açılır ve `DM-016` görünür. Anahtar URL'ye, HTML'e veya sonraki isteklere açık metin olarak eklenmez; güvenli oturumla yenileme içerikte kalır. Başka liste/revision sızmaz.

**Otomasyon notu:** Gerçek yerel kilit API/MySQL; açık anahtar trace ve server log çıktısında maskelenir.

### E2E-PNL-43 — Yanlış anahtar sabit hata ve hız sınırı

**Amaç:** Yanlış anahtarların kayıt varlığını ele vermeyen sabit yanıtla karşılanmasını ve eşik sonrası hız sınırını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Paylaşım kilit ekranı

**Ön koşul / hazırlık:** `DM-016` içeren `SHR-E2E-001`; yanlış anahtar dizisi; test saat ve rate-limit adapter'ı kontrol altında; aynı istemci/IP fixture'ı.

**Adımlar:**

1. Yanlış altı haneli anahtarları politika eşiğine kadar gönder.
2. Her yanıtın durum, gövde boyu/metni ve süresini karşılaştır.
3. Eşiği aş; `Retry-After`/bekleme davranışını ve doğru anahtar denemesini kontrol et.

**Beklenen sonuç:** Eşik öncesi tüm yanlış girişler aynı sabit hata metni ve yetkisiz sonucu verir; liste/anahtar varlığına göre metin değişmez. Eşik sonrası hız sınırı devreye girer, sayaç reset süresi server kaynağından gelir ve doğru anahtar bile pencere dolana kadar bypass etmez. DB'de açık anahtar tutulmaz.

**Otomasyon notu:** E2E-PNL-42'nin negatif ikizi; sahte saat + gerçek yerel rate-limit deposu, gerçek bekleme yapılmaz.

### E2E-PNL-44 — JavaScript'siz kilit formu yolu

**Amaç:** Kilit açmanın JavaScript kapalıyken standart HTML form POST'u ve server yönlendirmesiyle çalışmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Paylaşım kilit ekranı

**Ön koşul / hazırlık:** JavaScript devre dışı browser context; `DM-016` içeren `SHR-E2E-001`; doğru `246810`; CSRF/oturum fixture'ı.

**Adımlar:**

1. Kilit sayfasını JavaScript kapalı aç.
2. Altı haneyi standart form alanına girip submit et.
3. Yönlendirme ve içerik sayfasını kontrol et; yanlış anahtarla ayrı temiz context'te tekrarla.

**Beklenen sonuç:** Doğru giriş server-side POST/redirect ile içeriği açar; yanlış giriş aynı sabit hata sayfasına döner. Form etiketi, alan adı, CSRF ve erişilebilir hata metni JS'ye bağlı değildir; anahtar query string'e girmez.

**Otomasyon notu:** Playwright `javaScriptEnabled:false` + gerçek yerel API; dış ağ yok.

### E2E-PNL-45 — Tazeleme sayacı tek istek üretir

**Amaç:** Paylaşım içeriğinin görünür tazeleme sayacının zamana göre doğru azalmasını ve sıfırda tek koşullu yenileme yapmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Paylaşım sayfası

**Ön koşul / hazırlık:** `DM-016` içeren ve anahtarı açılmış `SHR-E2E-001`; server `refresh_after=30` ve ETag/revision 1 döndürür; sahte saat; ikinci yanıtta revision değişmez.

**Adımlar:**

1. Sayacı `30`dan başlatıp saati 29 saniye ilerlet.
2. Sıfıra ilerlet ve ağ isteklerini say.
3. Sekmeyi gizliyken 90 saniye ilerlet, görünür yap ve yeniden say.

**Beklenen sonuç:** Sayaç monoton azalır, negatif olmaz; sıfırda tam bir koşullu istek oluşur ve revision değişmediyse içerik/scroll/odak korunarak sayaç resetlenir. Gizli sekme dönüşünde üç istek patlaması olmaz; server zamanı temel alınarak en fazla bir uzlaştırma isteği gider.

**Otomasyon notu:** Playwright sahte saat + gerçek yerel paylaşım endpoint'i; gerçek 30/90 saniye beklenmez.

### E2E-PNL-46 — WhatsApp köprüsü numara ve metni doğru kodlar

**Amaç:** Paylaşım sayfasındaki WhatsApp eyleminin doğru uluslararası numarayı ve seçili dildeki kısa metni linke kayıpsız kodlamasını kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Paylaşım sayfası

**Ön koşul / hazırlık:** `DM-016` içeren paylaşım; firma telefonu `+90 532 111 22 33`; TR paylaşım adı `Yaz Koleksiyonu`; link `https://panel.test/s/SHR-E2E-001`; beklenen metin link içerir, erişim anahtarı içermez.

**Adımlar:**

1. WhatsApp köprüsü linkini üret.
2. URL'yi parse ederek hedef numara ve `text` parametresini decode et.
3. Tıklama handler'ında dış navigasyonu iptal edip çağrıyı kaydet.

**Beklenen sonuç:** Hedef numara yalnız rakamlarla `905321112233`dür; decoded metin doğru liste adı/linki ve TR şablonunu taşır. Anahtar, çift encode, `+`, boşluk kaybı veya başka numara yoktur. Test WhatsApp alanına gerçekten gitmez.

**Otomasyon notu:** Vitest URL builder + Playwright click interception; dış navigasyon CI ağ kuralıyla kırmızıya bağlanır.

## 8. Ayarlar — 6 senaryo

### E2E-PNL-47 — Çeviri sağlayıcısını kaydetme ve anahtar maskeleme

**Amaç:** Sağlayıcı ayarının kaydedildiğini, gizli anahtarın sonraki render/API yanıtında geri dökülmediğini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Ayarlar / Çeviri

**Ön koşul / hazırlık:** Test sağlayıcısı `adapter-demo`; sahte anahtar `sk-e2e-NOT-REAL-1234`; model seçimi; dış adapter çağrısı kapalı. `DM-016` yalnız sonraki bağlantı testi için örnek metin olarak hazırdır; bu senaryoda ürün verisi gönderilmez.

**Adımlar:**

1. Sağlayıcı, model ve sahte anahtarı girip kaydet.
2. Sayfayı yenile ve ayar GET yanıtını/DOM'u oku.
3. Server log/audit fixture'ında açık değeri ara.

**Beklenen sonuç:** Sağlayıcı/model kalıcıdır; UI yalnız maskeli son parça gösterir, GET yanıtı ve log açık anahtarı içermez. Secret storage'da şifreli/secret-reference biçimi vardır; yeniden kaydetmeden anahtar değişmez.

**Otomasyon notu:** Gerçek ayar API/MySQL/secret adapter; sahte canary değeri sızıntı taramasında kullanılır.

### E2E-PNL-48 — Çeviri bağlantı testi hatası görünür

**Amaç:** Bağlantı testi sağlayıcı hatasını kullanıcıya görünür ve güvenli biçimde verirken mevcut ayarı koruduğunu kanıtlamak.

**Sınıf:** A — Otomatik / sahte veri  
**Ekran:** Ayarlar / Çeviri

**Ön koşul / hazırlık:** Bağımsız görünür ayar projection'ında sağlayıcı `adapter-demo` ve maskeli test anahtarı; adapter stub'ı sırasıyla `401` ve timeout döndürür; örnek metin `DM-016` TR başlığıdır.

**Adımlar:**

1. Bağlantı testini 401 fixture'ıyla çalıştır.
2. Timeout fixture'ıyla yeniden çalıştır.
3. Hata metni, maskeli anahtar ve ayar store'unu kontrol et.

**Beklenen sonuç:** Her hata görünür, sağlayıcı/model ve güvenli eylem önerisi taşır; spinner biter. Açık anahtar/ham provider gövdesi/stack trace gösterilmez; önceki kayıtlı ayar silinmez ve ürün/liste kaydı oluşmaz.

**Otomasyon notu:** E2E-PNL-47'nin negatif ikizi; adapter tamamen yerel stub'dır, gerçek sağlayıcı isteği yasaktır.

### E2E-PNL-49 — Kur getir → onayla → kaydet

**Amaç:** Dış kur yanıtının kullanıcı onayından önce aktif hesaba uygulanmadığını ve onayla tek sürüm oluşturduğunu kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Ayarlar / Kur

**Ön koşul / hazırlık:** Yerel kur adapter'ı `USD/TRY=40.2500`, kaynak `E2E-FX`, zaman `2026-08-23T12:00:00+03:00`; listede `DM-001`, `DM-011`; önceki aktif kur `40.0000`.

**Adımlar:**

1. “Kur getir”i tetikle; önizleme hâlinde Liste Detay toplamını kontrol et.
2. Kaynak/zaman/oranı onayla.
3. Ayar, DB sürümü ve liste toplamını yenile.

**Beklenen sonuç:** Önizlemede `40.2500` görünür fakat aktif kur/toplam değişmez. Onayla birlikte tam bir yeni aktif sürüm oluşur; kaynak/zaman korunur ve toplam yalnız bir kez Decimal hesapla güncellenir. Dış kur servisine ağ çıkışı yoktur.

**Otomasyon notu:** Gerçek API/MySQL + yerel adapter stub; onay öncesi/sonrası DB snapshot'ları karşılaştırılır.

### E2E-PNL-50 — Geçerli sözlük CSV içe aktarımı

**Amaç:** UTF-8 TR/EN/ZH sözlük satırlarının önizleme ve kullanıcı onayıyla atomik uygulanmasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Ayarlar / Sözlük

**Ön koşul / hazırlık:** UTF-8 CSV; yeni ad alanı `e2e.test.borosilikat`; TR/EN/ZH değerleri `Yüksek borosilikat cam` / `High borosilicate glass` / `高硼硅玻璃`; `DM-016` referansı.

**Adımlar:**

1. CSV'yi seç ve önizlemeyi aç.
2. Anahtar ile üç dil hücresini doğrula.
3. İçe aktarımı onayla, sayfayı yenile ve sözlük API'sini sorgula.

**Beklenen sonuç:** Önizleme `1 eklenecek / 0 hatalı`dır; onaydan önce DB değişmez. Onaydan sonra tek sürümde üç dil aynen korunur; BOM/Çince karakter bozulmaz ve audit kaydı dosyanın kendisini/gizli veriyi loglamaz.

**Otomasyon notu:** Playwright file upload + gerçek API/MySQL; geçici dosya yerel fixture'dır.

### E2E-PNL-51 — Hatalı sözlük CSV mevcut veriyi bozmaz

**Amaç:** Eksik sütun, yinelenen anahtar veya bozuk UTF-8 satırının kısmi içe aktarıma yol açmamasını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Ayarlar / Sözlük

**Ön koşul / hazırlık:** `DM-016` referanslı mevcut `e2e.test.borosilikat` sözlük kaydı bağımsız seed edilir; üç ayrı hatalı CSV fixture'ı: `zh` sütunu eksik, yinelenen `anahtar`, bozuk UTF-8.

**Adımlar:**

1. Her fixture'ı ayrı izole testte yükle.
2. Önizleme/hata satırı ve onay düğmesini kontrol et.
3. Sözlük sürümü ve mevcut `e2e.test.borosilikat` kaydını sorgula.

**Beklenen sonuç:** Hata dosya ve satır/sütun düzeyinde anlaşılır biçimde görünür; onay kapalıdır. Hiçbir fixture kısmi satır, yeni sürüm veya değişmiş mevcut değer üretmez; üç dil önceki hâliyle korunur.

**Otomasyon notu:** E2E-PNL-50'nin negatif ikizi; transaction rollback ve DB checksum assert edilir.

### E2E-PNL-52 — Hedef dil listesi değişimi ve bağımlı UI

**Amaç:** Hedef dil seçiminin kaydedilip çıktı/paylaşım seçeneklerine yansımasını, kaynak/orijinal alanı silmemesini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API  
**Ekran:** Ayarlar / Diller

**Ön koşul / hazırlık:** Başlangıç hedefleri `tr,en,zh`; `DM-016` üç dil alanlı; açık bir çıktı veya paylaşım üretimi yok.

**Adımlar:**

1. EN'yi hedef listesinden çıkarıp kaydet.
2. Çıktı ve Paylaş pencerelerini aç; seçenekleri oku.
3. EN'yi yeniden ekle, sıralamayı `zh,tr,en` yap ve sayfayı yenile.

**Beklenen sonuç:** İlk kayıtta yalnız yeni üretim seçeneklerinden EN kalkar; mevcut ürünün `name_en` ve kaynak `name_zh` verisi silinmez. İkinci kayıtta üç hedef seçilen sırayla görünür ve yenilemede kalıcıdır; ayar değişimi kendiliğinden çıktı/paylaşım üretmez.

**Otomasyon notu:** Gerçek ayar API/MySQL ve Playwright; veri kaybı ile UI seçenekleri ayrı assert edilir.

## 10. V3-B: Bildirimler, Panorama, Ayarlar sekmeleri, tema ve PWA — 10 senaryo

### E2E-PNL-53 — Bildirim zili okunmamış rozetini basar

**Amaç:** Bir olay doğduğunda üst çubuktaki zilin rozetlendiğini ve merkezin o satırı gösterdiğini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Kabuk / Üst çubuk

**Ön koşul / hazırlık:** Bildirim tablosu boş; oturum açık.

**Adımlar:**

1. Bir liste oluştur (NTF-LIST-CREATED doğar).
2. Paneli yenile; zilin rozetine bak.
3. Zile bas, merkezdeki satırı oku, "okundu say" düğmesine bas.

**Beklenen sonuç:** Rozet yalnız okunmamış varken basılır; merkez satırı katalogdaki başlık ve gövdeyi gösterir; okundu işaretinden sonra rozet düşer ve sayaç sıfırlanır.

**Otomasyon notu:** Playwright; sayaç ucu ve merkez listesi ayrı assert edilir.

### E2E-PNL-54 — Aynı olay penceresi içinde "×N" olarak birleşir

**Amaç:** Yüksek frekanslı olayın bildirim merkezini boğmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Kabuk / Bildirim merkezi

**Ön koşul / hazırlık:** Eklenti token'ı üretilmiş; bildirim tablosu boş.

**Adımlar:**

1. Aynı platformdan beş yakalama gönder.
2. Bildirim merkezini aç.

**Beklenen sonuç:** Tek satır görünür, "×5" rozeti basılır ve gövde katalogdaki toplu cümledir; beş ayrı satır OLUŞMAZ.

**Otomasyon notu:** Playwright + capture ucu; sayaç `birlesen_sayi` ile karşılaştırılır.

### E2E-PNL-55 — Kritik bildirim anlık kart olarak çıkar, modal DEĞİLDİR

**Amaç:** A5 görünüm deseninin uygulandığını kanıtlamak: köşe kartı sayfayı bloklamaz.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Kabuk

**Ön koşul / hazırlık:** `onem=kritik` bir bildirim üretilmiş (ölü iş).

**Adımlar:**

1. Paneli aç; sağ üst kartı gör.
2. Kartın arkasındaki bir düğmeye tıkla.
3. Kartı X ile kapat, bildirim merkezini aç.

**Beklenen sonuç:** Kart sağ üstte görünür; arkadaki düğme tıklanabilir (odak tuzağı yok); kapatılan kart bildirimi SİLMEZ ve okundu SAYMAZ — merkezde okunmamış durur.

**Otomasyon notu:** Playwright; `data-testid="bildirim-anlik-kart"` ve arka plan tıklaması.

### E2E-PNL-56 — Panorama brifingleri öncelik sırasıyla dizilir

**Amaç:** "Bugün ne var?" bölümünün en acil konuyu başa aldığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Panorama

**Ön koşul / hazırlık:** Bir ölü iş ve bir bekleyen gelen kutusu kaydı.

**Adımlar:**

1. Ana ekranı aç.
2. Brifing satırlarının sırasını oku.

**Beklenen sonuç:** BRF-011 (ölü iş, öncelik 1) BRF-009'un (gelen kutusu, öncelik 3) üstündedir; her cümlede sayı doludur, süslü parantez KALMAZ.

**Otomasyon notu:** Playwright; `data-testid="panorama-brifingler"`.

### E2E-PNL-57 — "Henüz ölçülmüyor" ayrı gösterilir

**Amaç:** Ölçülemeyen brifinglerin "koşul sağlanmadı" ile karıştırılmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Panorama

**Ön koşul / hazırlık:** Temiz sistem.

**Adımlar:**

1. Ana ekranı aç; boş gün cümlesini oku.
2. "N konu henüz ölçülmüyor" düğmesine bas.

**Beklenen sonuç:** Boş gün cümlesi katalogdan gelir; ölçülmeyen liste açılır ve her satır bir GEREKÇE taşır; bu satırlar brifing gibi gösterilmez.

**Otomasyon notu:** Playwright; `data-testid="panorama-olculmeyen"`.

### E2E-PNL-58 — Ayarlar 16 sekme ve URL eşliği

**Amaç:** Sekme kodunun adres çubuğunda taşındığını ve yer imine eklenebildiğini kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Ayarlar

**Ön koşul / hazırlık:** Oturum açık.

**Adımlar:**

1. `/ayarlar` aç; sekme sayısını say.
2. "Kur & Para Birimleri" sekmesine bas; adresi oku.
3. `/ayarlar?sekme=guvenlik` adresini doğrudan aç.
4. Tarayıcının geri düğmesine bas.

**Beklenen sonuç:** 16 sekme görünür; adres `?sekme=kur` olur; doğrudan açılan adres Güvenlik sekmesini getirir; geri düğmesi önceki sekmeye döner.

**Otomasyon notu:** Playwright; `data-testid="ayar-sekmeleri"`.

### E2E-PNL-59 — Boş sekme gizlenmez, gerekçesini söyler

**Amaç:** Bilgi mimarisinin yarısının saklanmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Ayarlar

**Ön koşul / hazırlık:** Oturum açık.

**Adımlar:**

1. "Firma Portalı" sekmesine bas.

**Beklenen sonuç:** Sekme görünür ve açılır; içerik "Bu sekmede henüz ayar yok" der ve ne zaman dolacağını yazar; boş bir beyaz alan GÖSTERİLMEZ.

**Otomasyon notu:** Playwright; `data-testid="ayar-sekme-bos"`.

### E2E-PNL-60 — Üç tema modu ve kalıcılık

**Amaç:** Açık/Koyu/Sistem seçiminin uygulandığını ve yenilemeden sonra korunduğunu kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Ayarlar / Genel

**Ön koşul / hazırlık:** Tarayıcı tercihi açık tema.

**Adımlar:**

1. Ayarlar > Genel'de "Koyu tema"yı seç; kök öğenin `data-theme` değerini oku.
2. Sayfayı yenile.
3. "Sistem teması"nı seç.

**Beklenen sonuç:** Koyu seçimde `data-theme="dark"`; yenilemeden sonra da aynı; Sistem seçiminde işaret KALDIRILIR ve tarayıcı tercihi geçerli olur.

**Otomasyon notu:** Playwright; `colorScheme` emülasyonu ile iki yön.

### E2E-PNL-61 — Service worker kapsamı paylaşım sayfasını içermez

**Amaç:** Oturumsuz paylaşım sayfasının önbelleğe alınmadığını kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Kabuk + Paylaşım

**Ön koşul / hazırlık:** Üretim derlemesi; bir paylaşım linki üretilmiş.

**Adımlar:**

1. Paneli aç, service worker kaydını bekle.
2. Kayıtlı SW'nin kapsamını oku.
3. Paylaşım sayfasını aç ve önbellek anahtarlarını listele.

**Beklenen sonuç:** Kapsam `/panel/` ile biter; paylaşım sayfası isteği SW'ye düşmez ve hiçbir önbellekte kaydı bulunmaz.

**Otomasyon notu:** Playwright; `navigator.serviceWorker.getRegistrations()` ve `caches.keys()`.

### E2E-PNL-62 — Sözlük CSV turu: indir, düzenle, yükle

**Amaç:** CSV döngüsünün çalıştığını ve kullanıcı teriminin korunduğunu kanıtlamak.

**Sınıf:** B — Otomatik / panel-API
**Ekran:** Ayarlar / Diller & Sözlük

**Ön koşul / hazırlık:** Sözlükte elle yazılmış bir terim var.

**Adımlar:**

1. CSV'yi indir; başlık satırını ve mevcut terimi gör.
2. Aynı terime FARKLI karşılık, bir de yeni terim içeren dosya yükle.
3. Sonuç bildirimini oku.

**Beklenen sonuç:** Yeni terim eklenir; mevcut terim DEĞİŞMEZ; bildirim "N eklendi, M korundu" der ve sözlük içe aktarım olayı bildirim merkezine düşer.

**Otomasyon notu:** Playwright dosya yükleme; sunucu tarafı SozlukCsvTest ile eşleşir.

## 9. Uygulama notları

- A senaryoları tekil/parametrik Vitest veya yerel Playwright component testlerine; B senaryoları Playwright testlerine ve gerektiğinde API/DB yardımcı assertion'larına çevrilmelidir.
- Her test adı senaryo kimliğiyle başlamalıdır; örnek: `test('E2E-PNL-15 skor gizli ...')`.
- Testler paralel koşacaksa kullanıcı, liste, paylaşım ve oturum kimlikleri worker ekiyle namespace edilmelidir; iş kuralı oracle'ları değişmez.
- Beklenmeyen üçüncü taraf isteği, console'da açık token/anahtar, kayıp DM kimliği veya senaryo içinde başka senaryoya bağımlı seed kabul edilmez.
- C sınıfı iki kayıt otomasyon kapsamı dışında bırakılabilir; CI sonucu “atlanmış” değil, ayrı manuel kanıt kimliğiyle raporlanır. C senaryosunun işlevsel karşılığı olan A/B testleri yine geçmelidir.
