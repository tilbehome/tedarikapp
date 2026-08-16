# M23 — GTİP & Gümrük Sınıflandırma Motoru
## PM Değerlendirmesi

**Tarih:** 16 Ağustos 2026
**Öneren:** Ürün Sahibi (Bünyamin)
**Değerlendiren:** PM
**Statüs:** Değerlendirme tamamlandı — Ürün Sahibi kararı bekliyor

---

# 0. ÖZET KARAR

**Fikir sağlam, teknik olarak yapılabilir, ve doğru kurgulanmış.** Özellikle üç tasarım tercihin profesyonel seviyede doğru:

1. **"Aday" kelimesini merkeze koyman** — AI çıktısını kesin GTİP saymamak. Bu, modülü sorumluluk açısından savunulabilir kılan tek şey.
2. **Sürüm/versiyon takibi** — `products.gtip` tek alan olmasın demen. Doğru; hatta düşündüğünden de gerekli (bkz. §3.1).
3. **TARA'yı scraping ile bağımlılık haline getirmemen** — araştırma bunu birebir doğruladı: TARA'nın kamuya açık API'si yok **ve CAPTCHA korumalı**. Sezgin doğruydu.

**Ama v1 kapsamına GİRMEMELİ.** Gerekçe §4'te. Kısaca: v1 henüz ortada yok (Faz 1 auth ortasındayız), modül tek başına v1 kadar iş, ve en değerli parçaları (BTB karşılaştırma, vergi analizi) resmi veri erişimi olmadığı için bugün zaten yapılamıyor.

**Şimdi yapılması gereken 3 ucuz hamle var** (§6) — bunlar kaçırılırsa geri dönülemez.

**Ve cevaplaman gereken 1 kritik soru var** (§8): DDP'de ithalatçı kim? Bu cevap modülün değerini 10 kat değiştiriyor.

---

# 1. İDDİA DOĞRULAMA

Yazdığın mevzuat iddialarını resmi kaynaklardan tek tek kontrol ettirdim. **Neredeyse hepsi doğru çıktı** — bu ciddi bir hazırlık.

| # | İddian | Verdikt | Not |
|---|---|---|---|
| 1 | 2026 TGTC, Karar 10781, 30 Aralık 2025 RG | ✅ **DOĞRU** | RG tam künye: **33123 sayılı, 1. Mükerrer** |
| 2 | Bakanlık Excel formatını da yayımlıyor | ✅ **DOĞRU** | Link bulundu: `ggm.ticaret.gov.tr/.../2026 TGTC.zip` |
| 3 | 12 hane yapısı (6 HS / 8 CN / 10 milli / 12 istatistik) | ✅ **DOĞRU** | Bakanlığın kendi resmi sayfasında birebir bu tanım var |
| 4 | Her yıl yayımlanır, 1 Ocak'ta yürürlüğe girer | ✅ **DOĞRU** | 2022, 2025, 2026 örüntüsü teyitli |
| 5 | Temmuz 2026'da ara değişiklik oldu | ✅ **DOĞRU** | **Karar 11507**, 11 Temmuz 2026, RG 33307. Etkilenen fasıllar: 28, 32, 38, 39, 48, 83, 84, 85 |
| 6 | 1-83 mamul maddeye, 84-96 işleve göre | ⚠️ **KISMEN** | Bakanlık bu net sayısal sınırı çizmiyor — bkz. §2.1 |
| 7 | Sınıflandırma kaynakları (İzahname, GYK, AB kararları, HS görüşleri) | ✅ **DOĞRU** | Bakanlığın resmi listesiyle birebir örtüşüyor |
| 8 | BTB 6 yıl geçerli | ✅ **DOĞRU** | Gümrük Kanunu **m.9/4** birincil kaynaktan doğrulandı |
| 9 | Başkasının BTB'sine dayanılamaz | ⚠️ **NÜANSLI** | bkz. §2.2 |
| 10 | TARA'nın belgelenmiş API'si yok | ✅ **DOĞRU** | Üstelik CAPTCHA'lı — scraping teknik olarak da kapalı |
| 11 | Gümrük müşavirliği tekeli "Kanun m.225 civarı" | ❌ **YANLIŞ** | Dayanak **Gümrük Yönetmeliği m.561-578** + Kanun m.5 |

**Sonuç:** 11 iddiadan 8'i tam doğru, 2'si nüanslı, 1'i yanlış (ve o da modülü etkilemiyor). Bu temel üstüne inşa edilebilir.

---

# 2. DÜZELTMELER

## 2.1 "1-83 madde / 84-96 işlev" kuralını motora KODLAMA

Bunu kural motoruna yazacaktın; yazma. Ticaret Bakanlığı'nın resmi ifadesi bu sınırı çizmiyor:

> *"Ticarete konu olan tüm mallar Armonize Sistemin 99 faslında, malın bileşimindeki maddelere göre, kullanım alanına veya kullanım amacına göre, imalat ve işleme derecesine göre gruplandırılırlar."*
> — gumrukrehberi.gov.tr

Yani **dört kriter, tüm fasıllara birden uygulanabilir** şekilde sayılıyor. 83/84 sınırı, özel eğitim kaynaklarında dolaşan bir basitleştirme — genel yönü doğru ama kural motoruna sabit sınır olarak girerse hatalı öneri üretir.

**Doğru kullanım:** bunu kesin kural değil, "ağırlık/öncelik ipucu" olarak tut. Asıl kural GYK'dır (özellikle GYK 1 ve GYK 3/b — "esas karakteri veren madde").

## 2.2 Başkasının BTB'si — söylediğinden biraz daha kullanışlı

Sen "başkasının BTB'sini kendi bağlayıcı kararımızmış gibi kullanamayız" dedin. Doğru. Ama eksik:

14 Seri No.lu Gümrük Genel Tebliği m.15'e göre gümrük idareleri, **geçerli bir BTB'ye konu eşya ile "aynı olduğu tartışmasız" eşya** için de aynı sınıflandırmayı uygulamak zorunda. Yani üçüncü kişiler, birebir aynı eşya söz konusuysa fiilen o sınıflandırmadan yararlanabiliyor.

⚠️ Bu bilgi **yalnızca ikincil kaynaktan** (bir hukuk sitesi + doğrulanamayan Danıştay atfı) geliyor, birincil metinden teyit edilemedi. **Modülde bunu "hukuki dayanak" olarak sunma** — sadece "benzer BTB bulundu, müşavirinize gösterin" seviyesinde tut.

## 2.3 Sorumluluk sorusu cevapsız kaldı

"Bir yazılımın GTİP önerisi sunması gümrük müşavirliği tekeline girer mi?" sorusuna **mevzuatta net hüküm veya içtihat bulunamadı.** Yasak olduğuna dair kanıt yok; serbest olduğuna dair de yok.

Bu iç araç olduğu için (kendi firman, dışarıya satılmıyor) pratik risk düşük. Ama **modül dışarıya açılırsa** bu soru bir gümrük hukukçusuna sorulmalı. Şimdiden dokümana not düşülsün.

## 2.4 Küçük: MariaDB

Sen "TedarikApp MariaDB" yazmışsın. Önceki kararımızda **kör MariaDB pin'i reddedilmişti** — üretim DB türü deploy'da `SELECT VERSION()` ile doğrulanacak. Şema yazarken MySQL/MariaDB'ye özgü sözdiziminden kaçınalım.

---

# 3. ARAŞTIRMADAN ÇIKAN, SENİN BİLMEDİĞİN 4 BULGU

Bunlar tasarımı doğrudan değiştiriyor.

## 3.1 🔴 Sürüm modelin YETERSİZ — yıl değil, KARAR bazlı olmalı

Senin `tariff_versions` tablon yıl seviyesinde (`year: 2026`). Ama **Karar 11507 (11 Temmuz 2026)** yıl ortasında istatistik pozisyonlarını değiştirdi.

Yıl bazlı versiyonlama bunu kaydedemez. Bir ürünün GTİP'i 1 Ocak – 10 Temmuz arası geçerli, 11 Temmuz'dan sonra geçersiz olabilir — ve senin şeman bu ikisini aynı "2026" sürümü sayar.

**Düzeltme:**
```
tariff_versions → tariff_editions
  id, decision_no (10781 / 11507), rg_date, rg_no, effective_from,
  supersedes_edition_id, kind (annual | amendment), source_hash
```
Ve `gtip_codes` satırında `valid_from` / `valid_to` — kod bazında geçerlilik. Böylece "bu ürünün GTİP'i beyan tarihinde geçerli miydi?" sorusu cevaplanabilir.

## 3.2 🔴 Asıl kriz 2027 değil, 2028 — ve HS'nin kendisi değişiyor

Sen yıllık değişiklik analizi tasarladın (2026→2027). Ama araştırma şunu buldu:

**WCO'nun bir sonraki HS revizyonu HS2027 değil, HS2028.** COVID nedeniyle 7. inceleme döngüsü 5 yıldan 6 yıla uzatıldı; yürürlük **1 Ocak 2028**.

Bu ikisi tamamen farklı olay:

| | Yıllık Türk cetveli | HS büyük revizyonu |
|---|---|---|
| Sıklık | Her yıl | 5-6 yılda bir |
| Etkilenen haneler | 9-12 (milli/istatistik) | **1-6 (uluslararası HS)** |
| Etki büyüklüğü | Küçük, lokal | Kökten — pozisyonlar bölünür/birleşir |
| Sıradaki | Ocak 2027 (küçük) | **Ocak 2028 (büyük)** |

**Yani 2027 cetveli muhtemelen sadece milli hanelerde oynayacak, HS 6 hane sabit kalacak. Asıl stres testi Ocak 2028'de — bugünden ~17 ay sonra.**

Tasarım sonucu: değişiklik analizi motorunun **iki farklı modu** olmalı. Milli değişiklik = satır eşleştirme yeter. HS revizyonu = **korelasyon tablosu** gerekir (eski kod → yeni kod eşlemesi), çünkü basit diff "kod kayboldu" der ama nereye gittiğini söyleyemez.

⚠️ **Açık risk:** AB, CN yılları arası korelasyon tablosu yayımlıyor. Türkiye'nin 9-12 haneleri için böyle bir tablo yayımlanıp yayımlanmadığı **araştırılmadı**. Yayımlanmıyorsa 2028 geçişinde manuel eşleme gerekecek. Bu, modülün en riskli tek noktası.

## 3.3 🔴 BTB veri tabanı toplu indirilemiyor — "BTB karşılaştırma" özelliği v1'de YAPILAMAZ

Senin en heyecanlı fikrin bu ("altın değerinde veri"). Haklısın ama:

- Kamuya açık BTB sorgu sistemi **var**: `uygulama.gtb.gov.tr/BTBBasvuru/Btbler`
- Ama **toplu indirme / CSV / API yok** — sadece JS ile render edilen arama arayüzü
- AB'nin EBTI'si de aynı: **toplu erişim/API yok**
- Tek toplu kaynak: `tarifemevzuati.com` — **özel bir site**, kendi ifadesiyle "hukuki olarak bağlayıcı değildir"

**Sonuç:** "Benzer BTB kararlarını otomatik ara" özelliği, resmi ve sürdürülebilir bir veri kaynağına dayandırılamıyor. Özel bir siteyi kazımak da hem hukuken hem sürdürülebilirlik açısından zayıf temel.

**v1 için gerçekçi olan:** BTB'yi *manuel referans alanı* olarak tut (`btb_reference` — sen zaten koymuşsun ✅). Müşavirin bulduğu BTB numarasını sisteme girer. Otomatik eşleştirme sonraki sürüm.

## 3.4 ✅ Telif sorunu senin lehine çözülüyor — ama tek şartla

- **Resmî Gazete metinleri:** FSEK m.31 gereği serbest. *"Resmen yayımlanan veya ilân olunan kanun, tüzük, yönetmelik, tebliğ, genelge ve kazai kararların çoğaltılması, yayılması, işlenmesi veya herhangi bir suretle bunlardan faydalanma serbesttir."* ✅
- **Genel Yorum Kuralları:** Resmî Gazete metni → serbest ✅
- **Armonize Sistem İzahnamesi:** WCO'nun orijinali **telifli/ücretli** (WCO Trade Tools). **AMA** Türkiye onu "Gümrük Genel Tebliği (Gümrük Tarife Cetveli İzahnamesi)" olarak Resmî Gazete'de yayımlıyor → **Türkçe Tebliğ versiyonu kullanılabilir** ✅
- ⚠️ **Tuzak:** `gumrukrehberi.gov.tr` kendi kullanım koşullarında içeriğinin kopyalanmasını Bakanlık izni olmadan **yasaklıyor**.

**Kural:** Veriyi her zaman **Resmî Gazete / TGTC Excel'inden** al, Bakanlık portallarının açıklama metinlerinden değil.

---

# 4. NEDEN v1'E GİRMEMELİ

Fikir iyi diye kapsama girmesi gerekmiyor. Dört gerekçe:

**1. v1 henüz yok.** Faz 0 kapandı, Faz 1 çekirdek kapandı, şu an İE#4 REV2 (auth) sahada. Liste yönetimi, export, paylaşım — Faz 2 — henüz yazılmadı. Ürünün ana işi (1688'den yakala → liste yap → Çin'e gönder → takip et) çalışır hale gelmeden ikinci bir ürün başlatmak, ilkini geciktirir.

**2. Modül tek başına v1 kadar iş.** Tarife import + karar-bazlı sürüm yönetimi + ağaç arama + öneri motoru + doğrulama iş akışı + değişiklik analizi + korelasyon eşleme. Bu bir modül değil, ikinci bir ürün.

**3. En değerli parçaları bugün yapılamıyor.** BTB otomatik eşleştirme (§3.3), vergi/önlem analizi (TARA API yok), TAREKS eşleştirme (toplu veri yok). Geriye kalan "tarife cetvelini DB'ye koy + ara" kısmı, tek başına Excel'de arama yapmaktan çok da değerli değil.

**4. Tanım fazı kapalıydı.** Karar gereği yeni fikirler yalnızca fikir havuzuna giriyor — ve HS/GTİP zaten havuzda F-numaralı olarak duruyor. Bu değerlendirme, o tek satırlık havuz maddesini gerçek bir modül tanımına yükseltmeye yarayan malzeme.

---

# 5. ALTERNATİF: "M23-lite" — düşündüğünden daha değerli olabilir

Bir gözlem: **senin gerçek ihtiyacın GTİP değil olabilir.**

GTİP bir *anahtar*. Onunla açtığın kapılar şunlar: ne kadar vergi ödeyeceğim, bu ürün gümrükte takılır mı, izin/belge gerekiyor mu. Asıl değer bunlarda.

Ve DDP alıyorsan (bkz. §8) vergi hesabı zaten tedarikçide — geriye **"bu ürün gümrükte takılır mı?"** kalıyor.

**M23-lite:** Tam sınıflandırma motoru yerine, **riskli kategori uyarısı**:

> Yakalanan ürün → anahtar kelime + kategori eşleşmesi → "⚠️ Bu ürün TAREKS/CE/gözetim kapsamında olabilir. Sipariş öncesi müşavirine sor."

Elektrikli ürünler, oyuncak, gıda temaslı plastik, tekstil, kozmetik, pil içerenler — riskli GTİP grupları belli ve Ürün Güvenliği Tebliğlerinde GTİP listeleri halinde yayımlanıyor.

**Maliyeti tam motorun ~%10'u, değerinin ~%70'i.** Ve yanlış olduğunda zararı yok — çünkü çıktısı bir uyarı, bir kod değil.

Bunu tam motora giden yolun ilk adımı olarak da kurabiliriz.

---

# 6. ŞİMDİ YAPILMASI GEREKEN 3 UCUZ HAMLE

Modül ertelense bile bunlar **şimdi** yapılmalı — çünkü kaçırılırsa geri dönülemez.

## 6.1 🔴 Parser ham özellik JSON'unu SAKLASIN

1688 parser araştırmasından çıkan kritik bağlantı: GTİP için gereken veriler (malzeme, lif bileşimi, işlev, teknik parametre) tam olarak `featureAttributes` alanında — **ve o alanın JSON yolu sayfa versiyonuna göre değişiyor, bazen hiç yok.**

Eğer parser bugün sadece "başlık + fiyat + resim" saklarsa, 6 ay sonra M23'ü yaptığımızda **geçmiş ürünlerin teknik verisi kayıp** olur. 1688 sayfası silinmiş olabilir, fiyat değişmiş olabilir.

**Karar önerisi:** `products.raw_attributes` (JSON) — parser'ın bulduğu tüm özellik/spec verisini ham haliyle sakla. Bugün kullanmasan bile. Maliyeti bir kolon.

## 6.2 🔴 `country_of_origin` ≠ `country_of_dispatch`

Sen bunu zaten yazmışsın ve **kesinlikle haklısın**. "Çin'den gönderildi" ≠ "Çin menşeli". Menşe, antidamping ve ticaret politikası önlemlerinde tarife sınıflandırması kadar belirleyici.

İki kolon. Şimdi ekle. Sonradan eklemek, geçmiş kayıtları geriye dönük doldurmak demek — ki doldurulamaz.

## 6.3 🟡 Orijinal Çince başlık

Önceki kararda zaten kabul edilmişti (referans olarak saklanacak). Sadece teyit: GTİP sınıflandırması için orijinal Çince metin, Türkçe çevirisinden **daha değerli** — çünkü çeviri malzeme bilgisini kaybediyor (örn. 304不锈钢 → "paslanmaz çelik", 304 kalitesi kayboluyor).

---

# 7. ŞEMA ELEŞTİRİSİ

Tasarladığın tablolar genel olarak sağlam. Değişiklik önerilerim:

| Tablo/Alan | Durum | Öneri |
|---|---|---|
| `tariff_versions.year` | 🔴 Yetersiz | Karar bazlı `tariff_editions` (§3.1) |
| `gtip_codes.hs_6 / cn_8 / national_10` | ✅ İyi | `code_12`'nin prefix'leri — indeksli generated column olarak tut, ayrı yazma |
| `gtip_codes` | 🟡 Eksik | `valid_from` / `valid_to` ekle (yıl ortası değişiklikler için) |
| `gtip_codes.unit` | ✅ İyi | "Ölçü birimi" istatistik beyanı için gerekli, doğru düşünmüşsün |
| `gtip_codes` | 🟡 Eksik | `supplementary_unit`, `section` (bölüm), `chapter_note_ref` |
| `product_tariff_classifications.status` | 🟡 Karışık | `btb` bir *durum* değil *kaynak*. `status: suggested/reviewed/verified` + `source_type: ai/manual/broker/btb` olarak ayır (K22 makine-enum standardına da uyar) |
| `product_tariff_classifications` | ✅ İyi | `classifier_version`, `classification_reason`, `confidence` — hepsi doğru. Denetlenebilirlik için şart |
| — | 🔴 Eksik | `code_correlations` tablosu: `from_edition, from_code, to_edition, to_code, relation (1:1 / split / merge / removed)` — 2028 için hayati (§3.2) |
| SKU bazlı GTİP | ✅ Doğru tespit | Ama v1'de yapma. `sku_id` nullable koy, override mantığını sonraya bırak |

---

# 8. CEVAPLAMAN GEREKEN SORU

Bu modülün değeri tek bir cevaba bağlı:

### DDP alımlarında **ithalatçı kim?**

**Senaryo A — Sen ithalatçısın (kendi adına beyanname):**
GTİP senin sorumluluğunda. Yanlış beyan → Gümrük Kanunu m.234: vergi farkı %5'i aşarsa **farkın 3 katı** ceza. (Tespitten önce kendin bildirirsen bunun %10'u.) → **M23 kritik, hatta zorunlu.**

**Senaryo B — Tedarikçi/konsolidatör ithalatçı, sana yurt içi teslim yapıyor:**
GTİP riski sende değil. → **M23'ün değeri düşük.** Sadece maliyet öngörüsü ve "gümrükte takılır mı" için işe yarar → **M23-lite yeter.**

**Senaryo C — Karışık / ileride FOB'a geçme ihtimali var:**
→ **M23-lite şimdi, tam motor FOB'a geçerken.**

Bunu ben bilemem. Sen biliyorsun.

---

# 9. PM ÖNERİSİ (özet)

| Konu | Öneri |
|---|---|
| Fikir | **Kabul — değerli ve doğru kurgulanmış** |
| v1 kapsamı | **Girmez** |
| Yerleşim | Fikir havuzundaki HS/GTİP maddesi, bu değerlendirmeyle birlikte **tam modül tanımına** yükseltilsin; v1 canlıya çıktıktan sonra kendi mini tanım fazıyla açılsın |
| Şimdi yapılacak | §6'daki 3 ucuz hamle — uygun bir iş emrine iliştirilsin |
| Ara adım | **M23-lite** (riskli kategori uyarısı) ciddi şekilde değerlendirilsin |
| Açık teknik risk | Türkiye için kod korelasyon tablosu yayımlanıyor mu? (2028 HS geçişi için hayati) — ayrıca araştırılmalı |
| Açık hukuki risk | Yazılımın GTİP önerisi sunması — modül dışarı açılırsa gümrük hukukçusuna sorulmalı |
| Ön şart | §8'deki DDP/ithalatçı sorusunun cevabı |

---

## KAYNAKLAR

**Tarife cetveli:**
- [Ticaret Bakanlığı GGM — 2026 TGTC duyurusu (Karar 10781)](https://ggm.ticaret.gov.tr/duyurular/istatistik-pozisyonlarina-bolunmus-turk-gumruk-tarife-cetveli-karar-sayisi-10781-yayimlanmistir)
- [Resmî Gazete — 30.12.2025 tarihli 33123 (1. Mükerrer) TGTC](https://www.resmigazete.gov.tr/eskiler/2025/12/20251230M1-2.pdf)
- [Ticaret Bakanlığı — 2026 ara değişiklikler (Karar 11507, 11.07.2026)](https://ticaret.gov.tr/haberler/2026-yili-ithalat-rejimine-iliskin-ara-degisiklikler-resmi-gazetede-yayimlandi)
- [Karar 11507 detayı — etkilenen fasıllar](https://abglobal.tr/istatistik-pozisyonlarina-bolunmus-turk-gumruk-tarife-cetvelinde-degisiklik-yapilmasina-iliskin-karar-karar-sayisi-11507)
- [Ticaret Bakanlığı — GTİP 12 hane yapısı (resmi)](https://www.trade.gov.tr/customs-formalities/frequently-asked-questions/tariff)
- [Gümrük Rehberi — tarife sınıflandırması nasıl yapılır](https://gumrukrehberi.gov.tr/sayfa/tarife-s%C4%B1n%C4%B1fland%C4%B1rmas%C4%B1-nas%C4%B1l-yap%C4%B1l%C4%B1r)

**HS revizyonu:**
- [WCO — HS 2028 Edition, 1 Ocak 2028 yürürlük](https://www.wcoomd.org/en/topics/nomenclature/instrument-and-tools/hs-nomenclature-2028-edition/amendments-effective-from-1-january-2028.aspx)

**BTB:**
- [4458 sayılı Gümrük Kanunu (m.9 — BTB 6 yıl)](https://www.mevzuat.gov.tr/MevzuatMetin/1.5.4458.pdf)
- [BTB e-Başvuru modülü duyurusu](https://ticaret.gov.tr/duyurular/baglayici-tarife-bilgisi-basvuru-modulu-devreye-alinmistir)
- [BTB e-Başvuru Yardım Rehberi (form alanları)](https://uygulama.gtb.gov.tr/BTBBasvuru/docs/klvzyy.pdf)
- [Kamuya açık BTB sorgu sistemi](https://uygulama.gtb.gov.tr/BTBBasvuru/Btbler)
- [14 Seri No.lu Gümrük Genel Tebliği (Tarife)](https://orgtr.org/gumruk-genel-tebligi-tarife-seri-no14/)
- [AB EBTI veri tabanı](https://ec.europa.eu/taxation_customs/dds2/ebti/ebti_home.jsp)

**Veri kaynakları:**
- [TARA — Tarife Arama (API yok, CAPTCHA'lı)](https://uygulama.gtb.gov.tr/TARA)
- [AB TARIC — ham veri Excel olarak ücretsiz](https://taxation-customs.ec.europa.eu/customs/common-customs-tariff-cct/tariff-classification-goods/eu-customs-tariff-taric_en)
- [Combined Nomenclature 2026 veri seti](https://data.europa.eu/data/datasets/combined-nomenclature-2026)
- [EUR-Lex / CELLAR SPARQL uç noktası](https://op.europa.eu/en/web/cellar/cellar-data/metadata/knowledge-graph)
- [GitHub — datasets/harmonized-system (6 hane, PDDL)](https://github.com/datasets/harmonized-system)
- [WCO Trade Tools — HS İzahnamesi (ücretli/telifli)](https://www.wcotradetools.org/en)

**Telif:**
- [5846 sayılı FSEK m.31 — mevzuat metinleri serbest](https://msg.org.tr/mevzuat/yasal-mevzuat/5846-fsek)
- [Gümrük Rehberi kullanım koşulları (kopyalama yasağı)](https://gumrukrehberi.gov.tr/sayfa/kullan%C4%B1m-ko%C5%9Fullar%C4%B1)

**Ceza:**
- [Gümrük Kanunu m.234 — vergi farkının 3 katı (ikincil kaynak)](https://www.muhasebetr.com/yazarlarimiz/kerimcoban/059/)
