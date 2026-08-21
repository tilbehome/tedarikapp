# tedarikapp V3 — Kapsamlı Yol Haritası

**Sürüm:** 2.0 (21 Ağustos 2026) · **Durum:** kanon — çelişkide bu belge kazanır
**Yer:** `docs/v3/V3-YOL-HARITASI.md` · Önceki v2 vizyonu `docs/archive/v2/` altındadır.

Bu belge, tedarikapp'in V3 dönemini uçtan uca tanımlar: iş modeli, ilkeler,
bilgi mimarisi, tasarım sistemi, veri modeli, ekran şartnameleri, teknik
altyapı, riskler, fazlar ve kabul kriterleri. İş emirleri bu belgeden türetilir.

---

## 0. NASIL OKUNUR

- **Bölüm 1-6:** değişmeyen çerçeve (iş modeli, ilkeler, mimari, veri, skor).
- **Bölüm 7:** ekran ekran şartnameler — iş emirlerinin ana kaynağı.
- **Bölüm 8-13:** eklenti, ortak davranışlar, altyapı, güvenlik, riskler.
- **Bölüm 14:** 5 büyük faz, her biri tek iş emri; kabul kriterleriyle.
- **Bölüm 15-17:** maliyet, terminoloji, yürütme disiplini, havuz.

---

## 1. İŞ MODELİ ve KAPSAM

### 1.1 Gerçek akış
1. Kaynak platformlar (1688, Alibaba, AliExpress, Taobao, Temu, Amazon …)
   **yalnızca vitrindir** — buradan sipariş verilmez.
2. Ürünler yakalanır → havuza girer → değerlendirilir → **liste** olur.
3. Liste Türkiye'deki **ithalatçı firmaya** gider.
4. Firmanın Çin ekibi toptancıdan bulur, **DDP (KDV dahil)** fiyat döner.
5. Onaylanan kalemler **sipariş** olur; ödeme, üretim, sevkiyat, mal kabul.
6. Gerçekleşen maliyet (landed cost) çıkar; yurtiçi alternatifle karşılaştırılır.

### 1.2 Odak
**Ürün araştırma + tedarik döngüsü.** Bu ürünün sınırı budur.

### 1.3 Kalıcı RET'ler (kapsam dışı)
- Muhasebe, cari hesap, stok/depo takibi (TilbeOS'un alanı)
- Pazaryeri komisyon hesabı (TilbeSync'te kalır)
- Pazaryeri API entegrasyonları (katalog çıkışı yalnız kendi WooCommerce'ine)
- RFQ/tedarikçi pazarlık modülleri (Çinli satıcıyla doğrudan pazarlık yok)
- Gümrük mevzuatı / GTİP / compliance takibi (yalnız durum etiketi düzeyinde)
- E-posta/push bildirim (yalnız panel içi bildirim)
- Çok kiracılı SaaS ürünleştirme (tek kiracı)
- Watermark / telif müdahalesi
- Resmî platform API'leri, otomatik/toplu kazıma (yakalama insan tetiklemeli)

---

## 2. ÜRÜN İLKELERİ (bağlayıcı)

### 2.1 "Web sitesi değil, webde çalışan uygulama"
App-shell mimarisi: sabit sol menü + üst çubuk, içerik alanı kısmi güncellenir
(tam sayfa yenileme ve beyaz flaş yok). Kalıcı düzen (menü durumu, kaydırma,
filtre hatırlanır), modal ve yan çekmeceler, satır içi düzenleme, iyimser
güncelleme + geri alma, klavye kısayolları ve `Ctrl+K`, sürükle-bırak, çoklu
seçim + toplu eylem çubuğu, otomatik kayıt, PWA olarak kurulabilirlik.
**Hız hedefi: her etkileşimde 150 ms içinde geri bildirim.**

### 2.2 Platform bağımsızlık (zorunlu)
Hiçbir ekran/alan/kural tek platforma göre yazılmaz.
- Her platform kendi ayrıştırıcısıyla **ortak şemaya** yazar.
- Platform bilgisi **kayıttan** gelir (ad, rozet, URL kalıbı, para birimi, dil);
  kodda platform adı gömülü olmaz.
- Skor **platform + kategori içinde** normalize edilir.
- Para birimi ilan bazında; kur dönüşümü tek merkezden.
- Güven sinyalleri (1688 rozetleri vb.) genel "platform güven sinyalleri"
  yapısında tutulur.

### 2.3 KDV politikası (K57)
**Sistemde her şey KDV hariç (net) tutulur.** İthalatta ödenen KDV indirilecek
KDV'dir, gerçek maliyet nettir. Firma DDP'yi KDV dahil verdiği için sistem
otomatik ayrıştırır (varsayılan oran ayarlardan, ürün/kategori bazında
geçersiz kılınabilir). Belgelerde "KDV DAHİL" ibaresi ve dahil tutar aynen
basılır; iç kopyada hem net hem brüt görünür.

### 2.4 Kur politikası
TCMB resmî kurundan **otomatik** çekilir (günlük yayın saati), yedek kaynak
tanımlanır. Liste firmaya iletildiğinde **kur kilitlenir** (K48). Kilitli kur
ile güncel kur arasındaki sapma eşiği aşarsa uyarı çıkar. Elle geçersiz kılma
mümkündür ama **gerekçe zorunludur** ve kayda geçer.

### 2.5 Çeviri mimarisi (K56) — üç katman
1. **Yerel sözlük** (belirlenimci): malzeme, renk, menşe, birim gibi kapalı
   kümeler. Anında, bedava, her seferinde aynı sonuç → kurumsal tutarlılık.
   Çok dilli kurulu (ZH→TR, EN→TR); dil metinden otomatik saptanır.
2. **LLM (ana motor):** ürünün **tamamı tek istekte** gider (başlık + kategori +
   özellikler + varyasyonlar), JSON girdi/JSON çıktı, sözlük prompt'a gömülü.
   Talimat: pazarlama sıfatı ekleme, ölçü/marka/sayı değiştirme, bilmediğini
   uydurma. Sağlayıcı önerisi: Qwen Flash / DeepSeek Flash sınıfı (Çince
   kavrayışı + düşük maliyet); çok sağlayıcılı, anahtar Ayarlar'da.
   Sonuç `translation_cache`'e yazılır — tekrar maliyeti sıfır.
3. **Yedek:** DeepL Free / MyMemory. Yedekten gelen öneri **"makine çevirisi"**
   etiketiyle işaretlenir.

**Değişmez kurallar:** K54 — daima öneri, asla otomatik yazma. Orijinal metin
hiçbir koşulda kaybolmaz. Marka, model kodu, ölçü/birim, ilan no ve orijinal
başlık **asla çevrilmez**. Kullanıcının düzeltmesi sözlüğe aday olarak düşer.

### 2.6 Çok dillilik (arayüz ve belgeler)
Panel TR birincil, tüm metinler dil dosyasından. **Paylaşım sayfası ve Firma
Portalı tam çok dilli (TR / 中文 / EN)**: dil değiştirilince sayfanın tamamı
o dile döner — arayüz, sütun başlıkları, durum adları, şartlar, ürün adları ve
özellik değerleri. **Çince seçilince ürün adı çeviri değil, orijinal başlıktır**
(firmanın kaynak sitede göreceği metnin aynısı → yanlış ürün riski sıfırlanır).
Dil linkte taşınır (`?lang=zh`), tarayıcıda hatırlanır; belge çıktıları da
seçilen dilde üretilebilir. Para/sayı/tarih biçimi dile göre yerelleşir.

---

## 3. BİLGİ MİMARİSİ — SOL MENÜ

```
◆ MARKA BLOĞU  (logo · tedarikapp · TİLBE HOME · sürüm rozeti · « daralt)
+ Hızlı işlem   (Yeni liste · Ürün yakala · Yeni sipariş · Yurtiçi fiyat gir)
★ Sabitlenenler (sık kullanılan 2-3 kayıt)

ÇALIŞMA
  Panorama
  Keşif          → Havuz · Koleksiyonlar · Karşılaştırma · İzleme listesi
  Gelen Kutusu
  Listeler       → Taslaklar · Fiyat bekleyen · Değerlendirmede · Onaylı
TEDARİK
  Teklifler      → Açık turlar · Geçmiş turlar
  Siparişler     → Açık · Ödemeler · Tamamlananlar
  Sevkiyat       → Partiler · Takvim görünümü · Mal kabul
ANALİZ
  İthalat Avantajı → Karşılaştırma · Yurtiçi fiyat defteri
  İzleme         → İzlenen ürünler · Mağazalar · Uyarılar
  Raporlar
KAYITLAR
  Takvim
  Belgeler
  Firmalar & Kişiler
SİSTEM
  Ayarlar
  Arşiv          → Kapanan listeler · Çöp kutusu · Aktivite günlüğü

⌄ Son bakılanlar (son 3 kayıt)
👤 Hesap (avatar · rol · tema · dil · çıkış) · bağlantı durumu
```

**Marka bloğu:** logo işareti + "tedarikapp" kelime markası + ince "TİLBE HOME"
üst etiketi + sürüm/ortam rozeti (TEST ortamında turuncu). Tıklanınca:
çalışma alanı · sürüm notları · sistem durumu · yardım · klavye kısayolları.

**Davranışlar:** bölüm başlıkları açılır-kapanır ve durumu hatırlanır · alt
maddeler yalnız aktif ana maddede görünür · aktif öğede 3 px altın şerit ·
bekleyen iş rozetleri (sıfırsa görünmez, acilde turuncu nokta) · `Ctrl+B`
daralt (64 px ikon şeridi, ipucu balonlu) · `Ctrl+K` hızlı komut · menüdeki
listeye sürükle-bırak ile ürün ekleme · klavyeyle tam gezinme.

**Üst çubuk:** global arama · bildirim zili · hesap menüsü.
**Mobil:** alttan tam ekran menü + alt sekme çubuğu (Panorama · Keşif ·
Listeler · Daha fazla).

---

## 4. TASARIM SİSTEMİ

- **Renk:** lacivert `#0F2557` marka, altın `#D4A017` yalnız vurgu/aktif;
  50-900 nötr gri ölçeği; sınırlı anlam renkleri (başarı/uyarı/hata/bilgi);
  renk körlüğü için renk + ikon + metin birlikte.
- **Koyu tema baştan** (Açık/Koyu/Sistem) — token bazlı, CSS iki kez yazılmaz.
- **Tipografi:** Inter (self-host) + CJK için Noto Sans SC alt kümesi;
  ölçek 12/13/14/16/20/24/30; sayılarda tabular rakam; başlıkta sıkı aralık.
- **Aralık:** 4 px ritim (4/8/12/16/24/32/48); köşe 8/12/16; gölge 3 seviye.
- **Bileşen kitaplığı** (tek yerde tanımlı): düğme (birincil/ikincil/ghost/
  tehlikeli + yükleniyor), giriş alanları, seçici, **tablo** (yoğunluk, sabit
  başlık, sıralama, satır içi düzenleme), kart, çekmece, modal, sekme, rozet,
  chip, ipucu, boş durum, iskelet, toast, onay, sayfalama, adım göstergesi.
- **Tablo kalitesi ürünün yüzüdür:** hizalı sayı sütunları, sabit başlık,
  sütun genişliği hatırlanır, satır hover, çoklu seçim.
- **İkon:** lucide tek çizgi 18 px, aktifte dolgulu.
- **Hareket:** 120-200 ms, yalnız anlam taşıyanlarda.
- **Erişilebilirlik:** AA kontrast, odak halkası, klavyeyle tam kullanım.
- Tokenlar tek dosyada; panel + paylaşım sayfası + belgeler aynı dilden beslenir.

---

## 5. VERİ MODELİ — ÜRÜN ≠ İLAN

### 5.1 Kavramlar
- **Ürün:** zihindeki nesne ("Çift cidarlı termos 500 ml"). Ad (TR + orijinal),
  kategori, kanonik özellikler, hedef satış fiyatı, SKU/etiket, notlar,
  yaşam döngüsü işareti ("artık almıyoruz").
- **İlan:** bir platformdaki belirli satıcının sayfası. Platform, ilan no,
  satıcı/mağaza, fiyat kademeleri, MOQ, para birimi, görseller, video,
  ham öznitelikler, koli ölçüsü/CBM/ağırlık, yakalama zamanı.
- **Anlık görüntü (snapshot):** bir ilanın belirli andaki ölçümleri (fiyat,
  satış, değerlendirme, satıcı karnesi) — tarihçe ve ivme buradan.
- **Liste kalemi:** listeye eklenen Ürün + miktar, durum, not, firma cevabı.
  Ürüne bağlanır; hangi ilandan geldiği "kaynak ilan" olarak saklanır.
- **Medya:** ilana bağlı; yerel arşiv yolu + türev boyutlar.

### 5.2 Eşleştirme
Aday önerisi: aynı platform + aynı ilan no (kesin) · görsel parmak izi (güçlü) ·
başlık benzerliği + kategori + yakın fiyat (zayıf). **Otomatik birleştirme
yoktur** — sistem sorar, karar kullanıcınındır; birleştirme geri alınabilir.

### 5.3 Göç planı
1. Yeni tablolar eklenir, eski `products` korunur.
2. **Göçten önce veri temizliği** (çöp kayıtlar, kırık görseller, yinelenenler).
3. Her mevcut ürün → 1 Ürün + 1 İlan olarak kopyalanır.
4. Listeler yeni kimliklere bağlanır; **eski export snapshot'ları dokunulmaz**
   (K50 — geçmiş belgeler aynen üretilebilir kalır).
5. Doğrulama betiği: liste/ürün sayıları ve toplam tutarlar birebir eşleşmeli;
   eşleşmezse göç geri alınır. Tam yedek zorunlu.

### 5.4 Saklama
Ham veri silinmez/üzerine yazılmaz. Snapshot'lar özetlenir (90 günden eski
günlükler haftalığa, 1 yıldan eskiler aylığa). **Uyku politikası:** 3 aydır
dokunulmamış ve hiçbir listeye girmemiş ürünler "uykuda" olur — silinmez,
varsayılan görünümden çıkar, arama yine bulur, tek tıkla geri gelir.

---

## 6. TEDARİKAPP SKORU

**Bileşenler (varsayılan):** satış %35 · değerlendirme %25 · satıcı karnesi %20 ·
tazelik + ivme %10 · veri tamlığı %10. Ayarlardan kaydırıcıyla değiştirilir,
100'e normalize edilir.

**Kurallar:**
- Her bileşen 0-100'e çekilir; mutlak eşik yerine **kategori + platform içi
  yüzdelik** (farklı kategorilerin satış ölçekleri kıyaslanamaz).
- Kategoride 20'den az ürün varsa sabit ölçek kullanılır.
- **Eksik veri ne cezalandırılır ne ödüllendirilir:** hesaplanamayan bileşen
  hesap dışı kalır, "veri tamlığı" düşer → eksik veriden yüksek skor çıkmaz.
- İvme: son iki snapshot arası satış artışı; snapshot yoksa hesap dışı.
- Skor **yorumla** gösterilir ("87/100 · Güçlü aday") + bileşen dökümü.
- **"Neden bu skor"** açılır kutusu: bileşen, ham değer, normalize değer,
  ağırlık, katkı. Skor formülü sürümlenir (skor_v1, skor_v2).

---

## 7. EKRAN ŞARTNAMELERİ

### 7.1 Panorama (ana ekran)
**Kabul kriteri:** bakan biri 5 saniyede şu üçüne cevap alır — bugün benden ne
bekleniyor, hangi liste nerede takıldı, para nerede duruyor.

- **Aksiyon kuyruğu (en üstte):** sayaç değil bekleyen iş — "3 liste fiyat
  bekliyor, en eskisi 6 gün" · "firma 12 ürünü cevapladı, onayın bekliyor" ·
  "2 teklifin geçerliliği 2 günde doluyor" · "1 parti bu hafta limanda" ·
  "mal kabul bekliyor" · "izlenen 4 üründe fiyat düştü". **Boş kart basılmaz**;
  hepsi boşsa "Her şey güncel ✓". Kart tıklanınca hedef ekran filtreli açılır.
- **Günlük brifing:** insan diliyle tek paragraf özet.
- **Kişiselleştirilebilir kartlar** (sürükle-sırala, gizle, boyutlandır):
  aktif listeler + ilerleme · sevkiyat takvimi · ödeme takvimi · kur kartı
  (TCMB + kilitli kur sapması) · konteyner doluluk · yeni yakalananlar şeridi ·
  yüksek skorlu adaylar · izleme uyarıları · ithalat avantajı özeti · haftalık
  huni · firma performansı · son aktiviteler · kendi yapılacaklar notun.
- **Sistem sağlık şeridi:** son yedek · cron · çeviri kotası · yakalama sağlığı ·
  disk. Normalde sessiz gri; sorun varsa renklenir.
- **Anomali uyarıları** (her biri eylem düğmeli): kur eskimesi, firmanın linki
  açıp cevap vermemesi, konteyner taşması, yurtiçinin ucuzlaması.
- Dönem seçici + geçen döneme göre değişim · hızlı işlem · başlangıç kontrol
  listesi (yeni kurulumda) · mobilde kritik 4 kart.

### 7.2 Keşif (ürün istihbaratı — V3'ün kalbi)
**Sekmeler:** Havuz · Koleksiyonlar · Karşılaştırma · İzleme · Uykudakiler.

- **Arama:** çift dilli (TR yazınca ZH kaydı bulur), Türkçe karakter duyarsız,
  gelişmiş sözdizimi (`kategori:mutfak skor:>70`), ilan no/SKU/satıcı da aranır.
- **Filtreler:** platform · kategori · skor · fiyat bandı (₺ normalize) · MOQ ·
  satış · değerlendirme · yayın tarihi · video · veri tamlığı · listeye girmiş
  mi · izleniyor mu · etiket · yurtiçi fiyat var mı · ithalat avantajı %.
- **Hazır modlar:** Yeni + Yükselen · Kanıtlanmış Çok Satan · Mavi Okyanus ·
  Ucuz + Yüksek Puan · Yurtiçine göre avantajlı.
- **Kaydedilmiş görünümler** (adlandır, varsayılan yap).
- **Görünümler:** tablo · kart ızgarası · galeri (hızlı eleme) + yoğunluk.
- **Sütunlar:** görsel (▶ video) · ad (TR + orijinal) · platform rozeti ·
  kategori · skor (döküm) · birim fiyat + kademeli min · MOQ · satış · puan ·
  yayın · satıcı karnesi · veri tamlığı · yurtiçi fiyat/avantaj · son
  güncelleme · etiketler · durum.
- **Skor kaydırıcısı:** ağırlıkları ekranda değiştir → liste anında yeniden
  sıralanır.
- **Aynı ürün kümeleme:** farklı satıcı/platformdaki aynı ürün tek kartta
  ("5 kaynak · en ucuz ¥12 · en iyi karne X").
- **Karşılaştırma matrisi:** 2-6 ürün yan yana, farklı hücreler vurgulu,
  satır başına "en iyi" işareti, doğrudan listeye ekleme.
- **Toplu işlem:** listeye ekle (miktarlı) · koleksiyona · etiketle · izlemeye
  al · uykuya al · birleştir · dışa aktar · sil.
- **Ürün çekmecesi:** galeri/video · fiyat kademeleri · özellikler · varyasyonlar ·
  **yorum özeti** · fiyat/satış tarihçesi + ivme · satıcı karnesi · yurtiçi
  kıyas · notlar. Ok tuşlarıyla sonraki ürün.
- **Veri kalitesi:** eksik alan rozeti + "yeniden yakala" · yinelenen tespiti ·
  **yakalama sağlığı göstergesi**.
- **Hızlı ekleme:** link yapıştır (tek/çoklu) · Gelen Kutusu'ndan · elle · CSV.
- **Performans:** 10.000+ üründe sanal kaydırma, filtre < 2 sn.

### 7.3 Gelen Kutusu (hızlı eleme / triage)
- **Eleme modları:** tablo · kart ızgarası · **deste modu** (tek ürün büyük
  görselle; `→` havuza, `←` çöpe, `↑` listeye, `Space` atla — 40 ürün 2 dakikada).
- **Klavye:** `J/K` gez, `1-9` numaralı listeye at, `X` sil, `E` etiketle, `Z` geri al.
- **Otomatik zenginleştirme:** yakalama biter bitmez çeviri önerisi, kategori
  tahmini, skor, görsel arşivleme, yinelenen kontrolü — kart hazır gelir.
- **Yinelenen tespiti:** "zaten havuzda var" / "başka satıcıdaki hali listende".
- **Kural motoru:** "kategori mutfak VE skor>70 → Aday koleksiyonuna" ·
  "fiyat < ¥5 → çöp" · "video varsa etiketle". Kural rozeti, geri alınabilir.
- **Yakalama oturumları:** "Bugün 14:30 · 8 ürün · X mağazası" — oturum topluca
  işlenir.
- **Anında düzenleme** (miktar, not, etiket, kategori, çeviriyi kabul).
- **Kutu sağlığı:** bekleyen sayısı + en eskisi, sıfır kutu hedefi, haftalık
  dönüşüm oranı, 30 gün karar verilmemişlere uyku önerisi.
- **Giriş kanalları:** eklenti · link yapıştırma · CSV · yakalarken not/etiket.
- **Sorma:** tek ürünlük mini paylaşım linkiyle fikir alma.

### 7.4 Listeler ve Liste Detay (komuta merkezi)
**Üst blok (sabit, kaydırınca küçülür):** liste adı (satır içi düzenlenebilir) ·
durum rozeti · belge kodu + Rev · kilitli kur · firma · **durum stepper**
(tıklanabilir, adım tarihleri ipucunda) · eylemler (Paylaş · Excel · PDF ·
Yazdır · Firmaya hatırlat · ⋯).

**Özet şeridi (canlı, filtreye duyarlı):** ürün · toplam miktar · **fiyatlanma
oranı** (18/25) · mal bedeli · DDP toplam · **CBM + konteyner doluluğu** ·
iç kopyada kâr/marj. Kart tıklanınca filtre uygular.

**Araç çubuğu:** arama · filtreler · sıralama · **gruplama** (grup toplamlarıyla) ·
sütun seçici · yoğunluk · görünüm · kaydedilmiş görünümler.

**Tablo:** sabit başlık + sabit ürün sütunu · **satır içi düzenleme** (miktar,
not, hedef satış, durum; Enter'la alta geç) · kademeli fiyatta miktar değişince
birim fiyat otomatik · sürükle-sırala (belgeye yansır) · satır sonu hızlı
eylemler · **uyarı ikonları** (MOQ altı, fiyat gelmemiş, görsel eksik, ürün iki
kez, yurtiçi daha ucuz) · firma cevap sütunları (DDP, durum, not, alternatif).

**Toplu işlem çubuğu:** miktar (sabit veya ×çarpan) · durum · kategori ·
taşı/kopyala · çıkar · seçilenleri dışa aktar + canlı toplam.

**Sağ çekmece:** ürün detayı (galeri, kademeler, özellikler, varyasyonlar,
yorum özeti, skor dökümü, yurtiçi kıyas, tarihçe, notlar).

**Sekmeler:** Ürünler · **Teklif turları** (iki tur yan yana) · Belgeler ·
Zaman çizelgesi · **Revizyonlar** (Rev A→B farkı).

**Hızlı ekleme:** Gelen Kutusu'ndan · link yapıştır · elle · geçmiş listeden
kopyala (**tekrar sipariş**) · çoklu link yapıştırma.

**Akıllı uyarı bandı:** kur eskimesi · fiyatı gelmeyenler · konteyner taşması ·
teklif geçerliliği. Tıklanınca ilgili satırları filtreler.

**Klavye:** `/` arama · `F` filtre · `E` düzenle · `Space` seç · `Shift+↓` çoklu ·
`Ctrl+S` · `D` detay · `?` kısayollar.

### 7.5 Liste yaşam döngüsü (durum zinciri)
1. **Hazırlanıyor** — taslak, serbest düzenleme.
2. **Fiyat bekleniyor** — firmaya iletildi; **kur kilitlenir**, teklif
   geçerlilik süresi başlar, portal yazılabilir olur.
3. **Değerlendirmede** — fiyatlar döndü; ürün bazında Onayla/Reddet/Tekrar sor.
4. **Sipariş verildi** — onaylı kalemler siparişe döner.
5. **Üretim / tedarikte** — kilometre taşı kanıtları akar.
6. **Çin limanında** · 7. **Gemide (yolda)** · 8. **Türkiye limanında**
   (yalnız durum etiketi; mevzuat takibi kapsam dışı).
9. **Teslim edildi** — mal kabul açılır.
10. **Kapandı** — salt okunur, raporlara veri.
- **İptal edildi** — her durumdan geçilebilen kesici (gerekçe notuyla).

Kurallar: tek yönlü ilerleme, geri alma yalnız bir adım + gerekçe (kayıtlı) ·
çok partili listede **en gerideki parti** gösterilir ("2/3 parti Türkiye'de") ·
her geçiş tarih damgalı · çıktıların üst bandında güncel durum rozeti.

### 7.6 Teklifler ve Firma Portalı (V3'ün ikinci kalbi)

**Bizim taraf (Teklifler):** açık turlar (ana kolon: **açıldı mı / kaç gündür
bekliyor**), geçmiş turlar, tur karşılaştırma, onay/ret/tekrar sor, karşı
teklif, hatırlatma.

**Firma tarafı (Portal):**
- **Erişim:** tokenli yazma yetkili link + opsiyonel erişim anahtarı, süre
  sınırı, tek tıkla iptal, ilk girişte "adınız", denetim kaydı, hız sınırı,
  sabit 404 (K51).
- **Üst blok:** antet, liste adı, belge kodu + Rev, kilitli kur, **geçerlilik
  sayacı**, dil anahtarı, ilerleme çubuğu, yönerge bandı.
- **Firma yalnız şunları yazar:** DDP birim fiyat (para birimi seçilebilir),
  **kademeli fiyat** (adet eşikleri), MOQ, termin (gün + başlangıç noktası),
  durum (Bulundu / Bulundu ama farklı / Bulunamadı / Alternatif var), not,
  alternatif (link + foto), **koli ölçüsü/CBM/ağırlık**, stok ve süre, parti
  önerisi, ödeme şartı önerisi. Gerisi salt okunur.
- **Bizim veriler asla görünmez:** hedef satış, kâr, yurtiçi fiyat, skor, notlar.
- **Yazma deneyimi:** alan bazlı otomatik kayıt + "kaydedildi ✓", satır içi
  düzenleme, toplu işaretleme, doğrulama, **çevrimdışı dayanıklılık**, mobil
  kart düzeni + sayısal klavye.
- **Excel gel-git:** şablon indir → doldur → yükle → sistem eşleştirir, farkları
  onaylatır. **Yapıştır-ayrıştır:** WhatsApp metnini satırlara dağıtır.
- **Kısmi gönderim** ("bulduklarımı şimdi gönder").
- **Satır sohbeti + otomatik çeviri:** firma Çince yazar, biz Türkçe okuruz.
- **Akıl kontrolü:** absürt/hatalı fiyatta uyarı (hane ve para birimi hatası
  portalda yakalanır).
- **Hedef fiyat sinyali** ve **geçmiş fiyat gösterimi**: liste bazında
  açılıp kapatılabilir (varsayılan kapalı).
- **Tur mantığı:** "Teklifi gönder" → tur kapanır, portal salt okunur; yeni
  turda firma **önceki tur değerlerini** görerek revize eder.
- **Gönderim onayı:** "bu fiyatlar N gün geçerlidir" + kim/ne zaman → belge değeri.
- **Değişiklik rozeti** ("3 yeni ürün eklendi") · **tur kilidi** · **otomatik
  hatırlatma** (geçerlilik dolmadan 2 gün kala + hazır WhatsApp mesajı).
- **Firma için çıktı:** kendi doldurduğu listeyi Excel/PDF indirebilir (süreli
  imzalı link, oturum gerekmez); yazdırma da çok dilli.
- **Güvenlik:** opsiyonel tek kullanımlık giriş kodu, yükleme denetimi
  (tür/boyut/EXIF), kim yazdı kaydı, eşzamanlı düzenlemede kilit göstergesi.
- **Bize dönen sinyaller:** açılma zamanı/sayısı, doldurulan satırlar, bekleme
  süresi, geçerlilik durumu → Panorama ve Teklifler.
- **İleride:** kör çoklu firma (aynı liste iki ithalatçıya, cevaplar yan yana).

### 7.7 Siparişler / Sevkiyat
- **Sipariş oluşturma:** onaylı kalemlerden; **liste birden çok siparişe
  bölünebilir**; kalanlar listede bekler.
- **Ödeme planı:** esnek şablonlar (peşin · kapora + bakiye · teslimatta ·
  kısmi). Her satırda tutar, vade, durum, **dekont eki**, ödeme günü kuru.
  Vadesi yaklaşanlar Panorama ve Takvim'e düşer.
- **Üretim kilometre taşları:** malzeme → üretim → kalite → yükleme; tarih +
  firma kanıtı (foto/belge).
- **Parti / konteyner:** kalemleri partilere böl; parti başına **CBM + brüt
  ağırlık**, konteyner doluluk yüzdesi (20'/40'/40HC/LCL), "yer kaldı, şunları
  ekleyebilirsin" önerisi; konşimento, ETD/ETA, gemi, gümrük tarihi.
- **Mal kabul:** beklenen vs gelen sayım (telefondan), eksik/hasar + foto,
  **otomatik rücu raporu** (çok dilli PDF).
- **Masraf dağıtımı (landed cost):** navlun, gümrük, iç nakliye, banka, sigorta
  kalemleri + **dağıtım anahtarı seçimi (değer / ağırlık / CBM)** → ürün başına
  gerçek birim maliyet ve plana göre sapma; kur farkı ayrı satırda.
- **Kapanış:** planlanan vs gerçekleşen özeti + ders notu.

### 7.8 İthalat Avantajı
- **Yurtiçi Fiyat Defteri:** ürüne **çoklu tedarikçi fiyatı** (tedarikçi, fiyat,
  KDV durumu, MOQ, teslim süresi, tarih, kaynak/foto). Eski fiyatlar tarihçede
  kalır → yurtiçi fiyat trendi.
- **Karşılaştırma kartı:** yurtiçi net vs ithalat net (landed cost); fark ₺ ve %;
  yanında **toplam yük** (bekleme süresi, MOQ, bağlanan nakit, kur riski).
- **Başabaş adedi:** kademeli fiyatla "kaç adetten sonra ithalat mantıklı" —
  iki eğrinin kesişimi grafiği.
- **Senaryo denemesi:** kur/navlun/adet değiştirerek sonucu görme.
- **Uyarı:** yurtiçi fiyat ithalat maliyetinin altına inerse.
- **Karar günlüğü:** verilen kararın gerekçesi kayıtlı kalır.

### 7.9 Takvim ve Belgeler
**Takvim:** kaynaklar (teklif geçerliliği, ödeme vadesi, ETD/ETA, gümrük,
mal kabul, kendi hatırlatmaların) · ay/hafta/ajanda · renk kodu · kayda gitme ·
**ICS akışı** (telefon takvimine tek yönlü abonelik; Google'a yazma yok) ·
hatırlatma eşikleri.

**Belgeler:** tür etiketleri (proforma, fatura, konşimento, dekont, çeki listesi,
mal kabul tutanağı, sertifika) · listeye/siparişe/partiye bağlanır ·
**sürümleme** (proforma v2) · geçerlilik tarihi + dolunca uyarı · tam metin
arama · **WhatsApp ekran görüntüsünü sürükle-bırak** · toplu indirme (zip) ·
**sunucu birincil** (yedeğe dahil), büyük dosyalar için dış bağlantı alanı ·
yükleme denetimi (tür/boyut/EXIF).

### 7.10 Raporlar
Huni (yakalanan → listelenen → fiyatlanan → sipariş → gelen, dönüşüm
oranlarıyla) · kategori dağılımı · **firma performansı** · ithalat avantajı
özeti · **kur etkisi** (kilitli vs ödeme günü) · sevkiyat performansı (ETA
sapması) · **maliyet sapması** (plan vs gerçek landed cost) · dönem
karşılaştırması. Her rapor **detaya inilebilir**, Excel/PDF çıktısı,
kaydedilmiş rapor tanımı, "her ay başında üret" zamanlaması.

### 7.11 Koleksiyonlar · İzleme · Firmalar · Arşiv · Bildirimler
- **Koleksiyonlar:** proje bazlı gruplar ("Kış 2027 adayları"), kapak, not,
  sürükle-sırala, koleksiyondan tek tıkla liste, salt okunur paylaşım linki.
- **İzleme:** izlenen ürün / mağaza / kategori sekmeleri; son değişim (fiyat
  ▼%12, ilan kapandı, stok bitti), satır bazında eşik, uyarı geçmişi; mağaza
  izlemede "yeni ürün çıktı → Gelen Kutusu'na düştü".
- **Firmalar & Kişiler:** ithalatçı kartı (iletişim, portal ayarları, performans,
  geçmiş listeler), yurtiçi tedarikçiler (fiyat defteriyle bağlantılı), Çin
  tarafı kaynak kartı (platform güven sinyalleri), notlar.
- **Arşiv:** kapanan listeler + çöp kutusu + **aktivite/denetim günlüğü**
  (filtrelenebilir, dışa aktarılabilir); çöpte 30 gün sonra kalıcı silme uyarısı.
- **Bildirim merkezi:** zil + okunmamış sayacı, tür ikonları, gruplama
  ("3 üründe fiyat düştü"), okundu işaretleme, arşiv, kayda gitme.
  **Olay kataloğu:** firma cevap verdi · firma X gündür açmadı · teklif
  geçerliliği doluyor · parti ETA yaklaştı · mal kabul bekliyor · fiyat
  düştü/yükseldi · ilan kapandı · stok tükendi · yedek gecikti · çeviri kotası ·
  yakalama sağlığı bozuldu. **Tek kanal: panel içi.**

### 7.12 Ayarlar (16 sekme + meta katman)
**Meta:** ayar içi arama · her ayarda açıklama + canlı önizleme · **değişiklik
geçmişi + geri al** · **yapılandırmayı JSON dışa/içe aktarma** · test düğmeleri
(kur, çeviri, yedek hedefi, platform seçicisi) · riskli ayarda şifre tekrarı ·
bölüm bazlı varsayılana dön · basit/gelişmiş mod · eksik yapılandırma rozetleri.

**Sekmeler:** 1) Genel · 2) Şirket & Belge (antet, logo, belge kodu şeması,
çok dilli şartlar metni, KDV oranı, yazdırma profilleri) · 3) Kur (TCMB, saat,
elle geçersiz kılma + gerekçe, kilit politikası, sapma eşiği) · 4) Skor
(ağırlıklar + canlı önizleme, normalizasyon, eşik etiketleri) · 5) Çeviri &
Terminoloji (sağlayıcı sırası, anahtarlar, kota, sözlük yönetimi, onaylanan
çeviriler, asla çevrilmeyecekler) · 6) **Platformlar** (kayıt, seçici setleri +
sürüm + sağlık, yeni platform ekleme, test URL'i) · 7) Kurallar (kural motoru,
kuru test) · 8) Listeler & Şablonlar (durum zinciri özelleştirme) · 9) Firma &
Portal (geçerlilik, erişim anahtarı, hedef/geçmiş fiyat gösterimi, portal dili) ·
10) **Lojistik & Maliyet** (konteyner tipleri, dağıtım anahtarı, masraf
kalemleri, ödeme şablonları) · 11) Bildirimler (eşikler, sessiz saatler) ·
12) Güvenlik (2FA, oturumlar/cihazlar, deneme sınırı, API token + son kullanım,
erişim kayıtları) · 13) Yedek & Kurtarma (program, uzak hedef + test, **geri
yükleme sihirbazı**, prova kaydı) · 14) Veri (dışa/içe aktarma, temizlik
araçları, uyku politikası, **demo modu**) · 15) Kullanıcılar & Roller (izin
matrisi) · 16) Sistem (sürüm, migration, **log görüntüleyici**, cron, disk,
**tek tık tanılama**, bakım modu, sürüm notları).

### 7.13 İlk kurulum ve yardım
**Sihirbaz (5 adım):** şirket/antet → kur kaynağı → çeviri sağlayıcı → firma
tanımı → eklenti kurulumu. Atlanabilir; Panorama'da kontrol listesi olarak devam.
**Panel içi yardım:** her ekranda "bu ekran ne yapar", ipucu balonları, kısayol
listesi (`?`), sürüm notları, acil durum runbook bağlantısı.

---

## 8. CHROME EKLENTİSİ — YOL HARİTASI

- **Zenginleştirilmiş yakalama:** kategori/kırıntı yolu, satıcı karnesi, satış
  metrikleri, yayın tarihi, fiyat kademeleri, **koli ölçüsü/CBM/ağırlık**.
- **Yakalama sağlık denetimi:** her yakalamada alan doluluk kontrolü; şüpheli
  yakalamada uyarı; panelde sağlık göstergesi.
- **Seçici sürümleme:** seçiciler panelden güncellenebilir (kod dağıtmadan).
- **Side Panel:** sayfayı kapatmadan liste seçme, önceki yakalamaları görme.
- **Toplu yakalama:** arama sonuç sayfasından çoklu seçim.
- **Sayfada bilgi:** "bunu daha önce yakalamıştın" uyarısı + kendi skorun rozeti.
- **Yakalarken not/etiket/miktar girme**, ekran görüntüsü ekleme.
- **Çevrimdışı kuyruk:** panel erişilemezse yakalamalar birikir, bağlanınca gider.
- **Çok platform:** her platform için ayrı ayrıştırıcı, ortak akış.
- **Sürüm uyumu uyarısı** (eklenti ↔ panel API sürümü).
- **Dağıtım:** Web Store Unlisted yayını (kurulum sürtünmesini bitirir).

---

## 9. ORTAK DAVRANIŞLAR (her ekranda geçerli ince dokunuşlar)

**Veri girişi:** e-tablo tarzı kopyala-yapıştır (Excel'den tabloya, tablodan
Excel'e) · ok tuşlarıyla hücre gezinme · "aynı değeri tüm seçili satırlara
uygula" · son işlemi tekrarla · yapıştırınca sayı/para biçimini tanıma ·
alan bazlı doğrulama · sürükle-bırak dosya.

**Güvenlik ağı:** her yıkıcı işlemde 5 saniyelik **geri al** · yıkıcı işlemde
**yazarak onay** · her kayıtta değişiklik geçmişi · birleştirmenin geri
alınabilmesi · çöpten geri getirme.

**Arama:** Türkçe karakter duyarsız (i/ı, ş/s, ğ/g) · çift dilli · gelişmiş
sözdizimi · eşleşme vurgusu · arama geçmişi · üst çubukta **global arama**
(ürün/liste/sipariş/belge/firma gruplu).

**Tablo:** sütun dondurma · çoklu sıralama · filtre çipleri + temizle ·
kaydedilmiş görünüm · sanal kaydırma · yoğunluk · sütun genişliği/sırası
hatırlanır.

**Durum ve zaman:** göreli zaman + tam tarih ipucu · binlik ayraç ve tabular
rakam · **para birimi rozet rengi** (¥/$/₺ karışmaz) · "son güncelleyen".

**Ekran durumu URL'de:** filtre/sıralama/sekme linke yazılır → paylaşılabilir,
yer imi yapılabilir; geri tuşu doğru çalışır; anlamlı sayfa başlıkları.

**Eşzamanlılık:** aynı kaydı iki sekmede açma uyarısı · kilit göstergesi ·
çakışmada birleştirme · oturum dolarken uyarı + uzatma (yazılanlar kaybolmaz).

**Hata ve yardım:** insan dilinde hata + hata kodu + kopyala · bölüm bazlı
yeniden dene · `?` kısayol penceresi · bağlamsal yardım · boş durumlarda
yönlendirme.

**Uzun işlem geri bildirimi:** düğme kilidi + fiil ("Yedek alınıyor…") ·
bilgilendirme bandı · gerçek ilerleme ("312 görselden 118'i taşındı") · sonuç
kartı · 60 sn'yi aşarsa "beklenenden uzun sürüyor" + iptal/tekrar dene ·
çift tıklama engeli. **Veri gelmeden yanlış durum yazılmaz** ("—" yalnız
gerçekten boşsa).

**Çevrimdışı/PWA:** bağlantı göstergesi · salt okunur devam · kuyruk gönderimi ·
telefona kurulabilirlik · uygulama rozeti.

**Çıktı:** her ekrandan yazdırma · seçili satırları dışa aktarma · panoya
kopyalama · paylaşılabilir görünüm linki.

---

## 10. TEKNİK ALTYAPI

| Başlık | İçerik | Faz |
|---|---|---|
| Arka plan iş kuyruğu | Tek cron altında görev tablosu (zenginleştirme, yeniden yakalama, rapor, görsel indirme), kilit mekanizmalı | 1 |
| Görsel boru hattı | Yerel arşiv + türev boyutlar + tembel yükleme + kırık görsel tespiti | 1 |
| Tam metin arama | FULLTEXT (TR + ZH + çeviri karşılıkları), Türkçe karakter normalizasyonu | 1 |
| Platform kaydı | Ayrıştırıcı + seçici sürümleme + sağlık | 1 |
| Toplu işlem çerçevesi | Her tabloda çoklu seçim + eylem çubuğu | 2 |
| Kaydedilmiş görünümler | Filtre setlerini adlandırıp saklama | 2 |
| PWA | Manifest + service worker, çevrimdışı görüntüleme | 2 |
| Bildirim merkezi | Panel içi olay akışı | 3 |
| Denetim kaydı | Kim/ne/ne zaman — portal yazmaları dahil | 3 |
| Snapshot şeması | Fiyat/satış tarihçesi + grafik uçları | 5 |
| API anahtarları | Salt okunur kendi API'n | 5 |
| VDS taşınma paketi | Ortam değişkenli yapılandırma + kurulum betiği + runbook | 5 |

**Performans hedefleri:** liste açılışı < 1,5 sn · Keşif filtreleme < 2 sn /
10.000 ürün · paylaşım sayfası ilk boya < 2 sn (mobil) · 100 ürünlük export
< 20 sn.

---

## 11. GÜVENLİK, YEDEK, FELAKET KURTARMA

- **Güvenlik sertleştirme turu:** oturum süresi ve otomatik çıkış, eşzamanlı
  oturum yönetimi, giriş denemesi sınırı, yükleme doğrulaması (MIME/boyut/EXIF),
  paylaşım linki erişim kaydı, bağımlılık denetimi otomasyonu, hassas
  işlemlerde şifre tekrarı.
- **Oturum politikası:** aktif oturum 12 saat, "beni hatırla" 30 gün (cihaz
  bazlı); token yenileme/yedek indirme/yetki değişimi gibi işlemlerde şifre.
- **Yedek:** otomatik program + saklama politikası + **uzak hedef** (S3/FTP) +
  **geri yükleme sihirbazı**. **Denenmemiş yedek yedek değildir:** dönüş provası
  betiği ve kaydı zorunlu ("son prova: … ✓").
- **Cron görünürlüğü:** koşum log'u + panelde "son yedek: X saat önce" +
  gecikmede uyarı.

---

## 12. RİSK KAYDI

| # | Risk | Etki | Azaltma |
|---|---|---|---|
| R1 | Platform sayfa yapısı değişir, yakalama sessizce bozulur | Yüksek | Sağlık denetimi + seçici sürümleme + panelden güncelleme |
| R2 | Platform yakalamayı engeller | Yüksek | İnsan tetiklemeli yakalama; otomatik kazıma yok |
| R3 | Veri göçünde kayıp | Yüksek | Tam yedek + doğrulama betiği + geri alma |
| R4 | Paylaşımlı hosting performansı yetmez | Orta | Dizinler, sayfalama, görsel türevleri; sınırda VDS |
| R5 | Çeviri kalitesi tutmaz | Orta | Üç katman + kullanıcı düzeltmesi sözlüğe döner |
| R6 | Tek kullanıcı/tek geliştirici darboğazı | Orta | Runbook, panel içi yardım, ikinci hesap |
| R7 | Yedek geri yüklenemez | Yüksek | Dönüş provası + uzak hedef |
| R8 | Kapsam şişmesi, faz bitmez | Orta | Faz başına kabul kriteri + "yeni fikir havuza" disiplini |
| R9 | Firma portalı kullanmaz, WhatsApp'ta kalır | Orta | Excel gel-git + yapıştır-ayrıştır + çevrimdışı dayanıklılık |

---

## 13. VERİ KALİTESİ ve TEMİZLİK

Göç öncesi zorunlu temizlik turu: elle girilmiş çöp kayıtlar, kırık görseller,
yinelenen ürünler. Sürekli araçlar: yinelenen tespiti ve birleştirme, eksik
veri raporu, "tamamla" akışı, yakalama sağlığı göstergesi, uyku politikası
(3 ay), arşiv kuralları.

---

## 14. FAZLAR — 5 BÜYÜK BLOK

> Her faz **tek kapsamlı iş emri = tek dal = tek PR = tek release**.
> Faz içinde iki haftada bir ara rapor (çalışır ara teslim).
> Faz ortasında yeni fikir **havuza** yazılır, faza sokulmaz.

### FAZ 1 — VERİ TEMELİ + KEŞİF → **sürüm 1.0**
Veri modeli (Ürün ≠ İlan) + göç ve doğrulama betiği · göç öncesi veri temizliği ·
zenginleştirilmiş yakalama (kategori, satıcı karnesi, metrikler, CBM/ağırlık) ·
yakalama sağlığı + seçici sürümleme · platform kaydı altyapısı · çeviri Katman 2
(LLM, JSON, sözlük gömülü, çok sağlayıcı) + terminoloji ekranı · arka plan iş
kuyruğu · görsel boru hattı · Tedarikapp Skoru v1 · **Keşif havuzu** (filtreler,
hazır modlar, kaydedilmiş görünümler, karşılaştırma matrisi, aynı ürün
kümeleme, koleksiyonlar) · **Gelen Kutusu v3** (deste modu, kural motoru,
oturumlar, otomatik zenginleştirme) · çift dilli + Türkçe karakter duyarsız
arama · performans hedefleri.

**Kabul:** 500+ ürünlük havuzda filtre < 2 sn · yakalanan üründe zorunlu alan
doluluğu ≥ %90 · göç doğrulaması birebir · her üründe skor dökümü görünür ·
yakalama sağlığı göstergesi çalışıyor.

### FAZ 2 — UYGULAMA KABUĞU + ARAYÜZ → **1.1**
Tasarım sistemi (tokenlar, bileşen kitaplığı, **koyu tema**) · app-shell ·
yeni **sol menü** (marka bloğu, gruplar, rozetler, daralt, son bakılanlar,
sabitlenenler) · üst çubuk (global arama, bildirim zili, hesap) · **Panorama** ·
**Liste Detay** (satır içi düzenleme, toplu işlem, sekmeler, çekmece, uyarı
bandı) · **Ayarlar 16 sekme + meta katman** · geri bildirim deseni · ortak
davranışların tamamı · PWA · erişilebilirlik · ilk kurulum sihirbazı + panel
içi yardım.

**Kabul:** Panorama 5 saniye testini geçiyor · mobilde liste açılışı < 2 sn ·
her modülde boş durum ekranı var · koyu tema tam · klavyeyle uçtan uca kullanım.

### FAZ 3 — FİRMA DÖNGÜSÜ → **1.2**
Teklifler modülü · **Firma Portalı** (çok dilli, yazma alanları, kademeli fiyat,
satır sohbeti + otomatik çeviri, otomatik kayıt, çevrimdışı dayanıklılık,
Excel gel-git, yapıştır-ayrıştır, kısmi gönderim, akıl kontrolü, tur mantığı,
gönderim onayı, hatırlatmalar) · bildirim merkezi · Firmalar & Kişiler ·
Belgeler · Takvim + ICS · liste durum zinciri · revizyon farkı · teklif takip
analitiği.

**Kabul:** firma tek ekrandan tüm listeyi fiyatlandırabiliyor · tur tarihçesi ve
karşılaştırma çalışıyor · üç dil tam · çevrimdışı yazım kaybolmuyor.

### FAZ 4 — SİPARİŞ · LOJİSTİK · MALİYET · ANALİZ → **1.3**
Siparişler (ödeme planı, kanıt, kilometre taşları) · Sevkiyat (parti/konteyner,
CBM doluluk, ETA) · Mal kabul + rücu raporu · **masraf dağıtımı ve landed cost** ·
**İthalat Avantajı** + Yurtiçi Fiyat Defteri + başabaş adedi + senaryo ·
Raporlar · Arşiv & Aktivite.

**Kabul:** bir sipariş uçtan uca izlenebiliyor (kapora → parti → mal kabul →
rücu) · gerçek birim maliyet üç dağıtım anahtarıyla hesaplanabiliyor ·
ithalat avantajı kartı ve başabaş grafiği doğru sonuç veriyor.

### FAZ 5 — ÇOK PLATFORM · ZEKA · OLGUNLUK → **1.4 → 2.0**
Yeni platformlar (Alibaba, AliExpress, Taobao, Temu, Amazon) · eklenti ileri
(side panel, toplu yakalama, çevrimdışı kuyruk, sayfada skor) · **İzleme**
(ürün/mağaza/kategori + uyarılar) · fiyat/satış tarihçesi + ivme + sezon
kıyası · **yorum analizi** · kademeli fiyat eşiği · güvenlik sertleştirme +
roller/izin matrisi · yedek uzak hedef + geri yükleme sihirbazı + prova ·
API anahtarları · VDS taşınma paketi + runbook · veri dışa aktarma · demo modu.

**Kabul:** en az 3 platformdan aynı şemaya yakalama · 8 haftalık fiyat/satış
grafiği + en az 4 uyarı türü · VDS'de sıfırdan kurulum runbook'la 1 saatte ·
yedekten dönüş provası geçiyor.

### Opsiyonel — KATALOG HAZIRLIK (Faz 5 sonrası)
İthal edilen ürünü satışa hazırlama: Türkçe başlık/açıklama üretimi (LLM),
görsel seti düzenleme, kendi WooCommerce'ine taslak ürün çıkışı. Telif ve
yayın sorumluluğu kullanıcıdadır. Pazaryeri API'ları kapsam dışıdır.

### Kaba takvim
Faz 1: 5-7 hafta · Faz 2: 4-5 hafta · Faz 3: 4 hafta · Faz 4: 4-5 hafta ·
Faz 5: 4 hafta. Toplam ~6 ay (tek geliştirici hızıyla).

---

## 15. MALİYET (yıllık, kaba)

| Kalem | Tahmin | Not |
|---|---|---|
| Çeviri (LLM) | ~2-10 USD | 10.000 ürün ≈ 2 USD; önbellek tekrarı bedava |
| Yedek çeviri (DeepL Free) | 0 | 500k karakter/ay ücretsiz |
| Paylaşımlı hosting | mevcut | Faz 1-4 bu ortamda çalışacak şekilde tasarlanır |
| VDS (Faz 5) | ~60-150 USD/yıl | 2 vCPU/4 GB sınıfı |
| Uzak yedek depolama | ~10-25 USD/yıl | S3 uyumlu |
| Chrome Web Store | 5 USD tek sefer | Unlisted yayın |

**Kota aşımı davranışı:** sessiz düşme yasak — panelde uyarı + yedek katmana
geçiş + kayıt.

---

## 16. TERMİNOLOJİ (TR · 中文 · EN)

| TR | 中文 | EN | Not |
|---|---|---|---|
| Liste | 采购清单 | Order list | Belge adı: Tedarik Sipariş Listesi |
| Ürün | 产品 | Product | Kanonik nesne |
| İlan | 商品链接 | Listing | Platformdaki sayfa |
| Vitrin fiyatı | 市场价 | Market price | Kaynak sitede görünen fiyat |
| DDP fiyat | 完税价 | DDP price | KDV dahil, firmadan gelir |
| **Firma** | 进口商 | Importer | Türkiye'deki ithalatçı — "tedarikçi" değil |
| Toptancı | 供应商 | Supplier | Çin'deki kaynak — belgeye basılmaz |
| Parti | 批次 | Batch | Sevkiyat birimi |
| Mal kabul | 收货验收 | Goods receipt | Sayım + hasar kaydı |
| Rücu | 索赔 | Claim | Eksik/hasar talebi |
| Kur kilidi | 锁定汇率 | Locked rate | Liste iletildiğinde sabitlenir |
| Revizyon | 版本 | Revision | Rev A/B/C — liste sürümüne bağlı |

Kural: arayüz, belge ve portal bu tabloyu kullanır; yeni terim önce buraya yazılır.

---

## 17. YÜRÜTME DİSİPLİNİ

- **Akış:** PM iş emri (tek kod bloğu) → geliştirme → çıktı raporu → PM diff
  denetimi → onaylı merge → release. Onaysız merge yasak.
- **Prototip önce:** yeni ekranlar kodlanmadan çalışan HTML prototipi üretilir
  ve onaylanır (paylaşım sayfasında işe yarayan yöntem).
- **Sapma disiplini:** emirden sapma raporda ayrı başlıkta gerekçesiyle bildirilir.
- **Testler:** kritik akışlarda E2E zorunlu; seçiciler gerçek arayüz kaynağından
  doğrulanır (F43); kritik öğelere `data-testid`.
- **Sürüm notları:** her sürümde panelde kullanıcı diliyle "Yenilikler".
- **Migration:** idempotent yazılır; öncesinde otomatik yedek.
- **Belge:** bu dosya kanondur; kararlar buraya işlenir, K-numarası PM tarafından
  verilir.

---

## 18. HAVUZ (şimdilik yapılmayacak, kaydı tutulan fikirler)

Numune modülü (numune süreci başlarsa) · kör çoklu firma karşılaştırması ·
sezon takvimi ve geçen yıl kıyası · kategori fiyat dağılımı · görselle ters
arama · ikinci hesap ötesi çok kullanıcı · mobil uygulama (PWA ötesi) ·
konteyner yerleşim optimizasyonu · firma tarafı mobil uygulama.
