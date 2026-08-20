# tedarikapp V3 — Yol Haritası v2 (Genişletilmiş)

> v1'in üzerine: ana menü bilgi mimarisi, modül kataloğu, teknik altyapı ve
> yaşam kalitesi geliştirmeleri eklendi. v1'deki iş modeli, kabul kriterleri
> ve kalıcı RET'ler aynen geçerlidir.

---

## 1. ANA SOL MENÜ — BİLGİ MİMARİSİ (hedef hâli)

```
◆ Panorama            (dashboard)
▸ Keşif               (ürün istihbaratı — V3'ün kalbi)
▸ Gelen Kutusu        (mevcut, v2)
▸ Listeler            (mevcut + revizyon tarihçesi)
▸ Numuneler           (YENİ)
▸ Siparişler          (derinleşiyor)
▸ Lojistik & Maliyet  (YENİ)
▸ Kârlılık            (YENİ)
▸ Katalog Hazırlık    (YENİ, opsiyonel faz)
▸ Firma Portalı       (yönetim tarafı)
▸ Raporlar            (YENİ)
▸ Arşiv / Çöp         (mevcut, K13)
⚙ Ayarlar             (genişliyor)
```

---

## 2. MODÜL KATALOĞU

### 2.1 Panorama (V3-B)
Günün fotoğrafı: bekleyen işler (firma cevabı bekleyen ürün sayısı, numune
bekleyenler, yoldaki siparişler), hafta özeti (yakalanan ürün, gönderilen
liste), kur kartı (güncel + kilitli listelerdeki kurlarla sapma), skor
dağılımı mini grafiği, son aktivite akışı. Her kart tıklanınca ilgili modüle
filtreli gider.

### 2.2 Keşif — Ürün İstihbaratı (V3-A, kalp)
- **Havuz görünümü:** yakalanan tüm ürünler (listeden bağımsız) tablo+kart;
  Tedarikapp Skoru, satış 30g/toplam, değerlendirme, yayın tarihi, satıcı
  karnesi, video var/yok, veri tamlığı yüzdesi sütunları.
- **Filtre/sıralama:** kategori, skor aralığı, fiyat aralığı, platform,
  yakalama tarihi, "listeye eklenmemişler". Kaydedilmiş görünümler
  ("Mutfak · skor>70" gibi) tek tıkla.
- **Karşılaştırma matrisi:** 2–6 ürün yan yana; fiyat kademeleri, MOQ,
  özellik farkları, skor bileşen dökümü.
- **Koleksiyonlar/etiketler:** listeye girmeden önce serbest gruplama
  ("Kış 2027 adayları").
- **Zaman boyutu (V3-F):** yeniden yakalama kuyruğu → fiyat/satış geçmişi
  grafiği, ivme göstergesi (▲ satış hızlanıyor), "ilan kapandı" tespiti.
- **Benzerini bul:** ürün görselinden platform içi görsel arama linki üretme
  (dış istek yok, kullanıcı tıklar).

### 2.3 Listeler (mevcut → V3-C'de derinleşir)
- Revizyon tarihçesi (Rev A/B/C diff görünümü: ne eklendi/çıktı/fiyat değişti).
- Teklif turu tarihçesi (firma her cevap verdiğinde tur kaydı).
- Liste şablonları ("standart aylık liste" ön ayarı: firma, dönem, kur kaynağı).
- Toplu işlemler: havuzdan çoklu seçim → listeye ekle; listeler arası taşı.

### 2.4 Numuneler (YENİ — V3-D ile birlikte)
Sipariş öncesi kritik adım bugün "Not" alanında yaşıyor; modülleşir:
- Numune isteği kaydı (ürün + firma + tarih + kargo takip no).
- Değerlendirme formu: foto yükleme, puan (kalite/ambalaj/uyum), karar
  (Onay / Ret / Şartlı onay + not).
- Karar ürüne işlenir → listede "numune onaylı ✓" rozeti; skor bileşenine
  küçük katkı (ayarlanabilir).

### 2.5 Siparişler (V3-D — derinleşme)
- Sipariş = onaylı listeden doğar; kalemler ürün bazında.
- **Ödeme planı:** kapora/bakiye kalemleri, tarih, ödendi işareti, kur o günkü.
- **Parti/konteyner:** kalemleri partilere böl; parti başına ETA, konşimento
  no, durum (üretimde / yüklendi / yolda / gümrükte / depoda).
- **Mal kabul:** parti geldiğinde sayım ekranı (beklenen vs gelen), hasar/
  eksik kaydı foto ile → otomatik "rücu raporu" (firmaya gönderilecek
  eksik-hasar dökümü, PDF).
- Sipariş kapanışı: gerçekleşen toplamlar plana karşı özet.

### 2.6 Lojistik & Maliyet (YENİ — V3-D/E arası)
- Sevkiyat takibi tek ekranda (tüm partiler, ETA sıralı, gecikme uyarısı
  panel içi).
- **Gerçekleşen birim maliyet (landed cost):** DDP $ + iç nakliye + fire/hasar
  payı → gerçek ₺ maliyet; plandaki DDP ile sapma yüzdesi.
- Kur farkı etkisi raporu (kilitli kur vs ödeme günü kuru).

### 2.7 Kârlılık (YENİ — V3-E civarı)
- Ürün başına: hedef satış ₺ (F5'te geldi) + gerçekleşen maliyet → birim ve
  toplam kâr; pazaryeri komisyon şablonlarıyla **net kâr simülasyonu**
  (Trendyol/HB/N11 oranları — TilbeSync'teki mantığın içeri alınması veya
  TilbeSync ile veri köprüsü; KARAR-1 aşağıda).
- "Bu ürün taşır mı" kartı: skor + kâr marjı + MOQ birlikte tek bakış.

### 2.8 Katalog Hazırlık (YENİ — opsiyonel V3-H)
İthal edilen ürünü satışa hazırlama köprüsü:
- Türkçeleştirme çalışma alanı (K54 çeviri altyapısı üstüne): başlık/açıklama
  önerisi — üretim [[tilbecore-ai-assistant]] / ChatGPT köprüsü hattıyla
  (KARAR-2), daima öneri, asla otomatik yayın.
- Görsel seti: kaynaktan indirilen görselleri boyutlandırma/sıralama, ana
  görsel seçimi (telif sorumluluğu kullanıcıda; watermark müdahalesi YOK).
- Çıkış: WooCommerce taslak ürünü oluştur (kendi siten) — pazaryerleri
  kapsam dışı (kalıcı RET listesine ek: pazaryeri API'ları bu üründe yok;
  o iş TilbeOS/başka araçların alanı).

### 2.9 Firma Portalı (V3-C — v1'deki gibi + eklemeler)
- Yönetim tarafında: bekleyen cevaplar sayacı, firma performans kartı
  (ortalama cevap süresi, bulunan oranı, fiyat revizyon sayısı).
- Portal çift dil TR/EN-ZH; denetim kaydı; Onayla/Reddet/Tekrar sor turları.

### 2.10 Raporlar (YENİ — V3-F)
Dönem raporu (ay/çeyrek): yakalanan → listelenen → sipariş edilen → gelen
huni; kategori dağılımı; skor ortalaması trendi; firma performansı; kur
etkisi. Hepsi Excel/PDF export'lu (mevcut renderer altyapısıyla).

### 2.11 Ayarlar (genişleme)
Belge Antedi (F'de geldi) · kur kaynağı ve yuvarlama · skor ağırlıkları
(kaydırıcılarla) · çeviri sağlayıcı/anahtar · yeniden yakalama sıklığı ·
liste şablon varsayılanları · yedek indir/geri yükle (UI) · oturum/2FA
yönetimi (EK-B üstüne) · tema (açık/koyu; KARAR-3).

---

## 3. TEKNİK ALTYAPI GELİŞTİRMELERİ (fazlara dağılır)

| Başlık | İçerik | Faz |
|---|---|---|
| Arka plan iş kuyruğu | Tek cron altında görev tablosu (yeniden yakalama, rapor üretimi, görsel indirme) — paylaşımlı hosting dostu, kilit mekanizmalı | V3-A |
| Görsel boru hattı | Kaynak görselleri yerel arşive alma + boyut türevleri + tembel yükleme; kırık görsel tespiti | V3-A |
| Tam metin arama | MariaDB FULLTEXT (ürün adı TR/ZH + özellikler) — panelde tek arama kutusu her modülü tarar | V3-B |
| Kaydedilmiş görünümler | Filtre setlerini adlandırıp saklama (kullanıcı başına) | V3-B |
| Toplu işlem çerçevesi | Her tabloda çoklu seçim + eylem çubuğu (Gelen Kutusu v2 kalıbı genelleşir) | V3-B |
| PWA | Manifest + service worker: telefona "uygulama gibi" kurulum, temel çevrimdışı görüntüleme (mobil yoğun kullanım için; bildirim push YOK — panel içi ilke korunur) | V3-B |
| Panel içi bildirim merkezi | Zil + okunmamış sayacı: firma cevabı, ilan kapandı, fiyat değişti, parti ETA yaklaştı | V3-C |
| Denetim kaydı genişletme | Kim/ne/ne zaman — firma portalı yazmaları dahil tek zaman çizelgesi | V3-C |
| Fiyat/satış geçmişi şeması | snapshot tabloları + grafik uçları (Chart.js panelde mevcut kalıp) | V3-F |
| API anahtarları | Kendi salt-okunur API'n (ileride TilbeOS/TilbeSync köprüsü için) | V3-G |
| VDS taşınma paketi | Ortam değişkenli yapılandırma, sıfır-dokunuş kurulum betiği, yedek-taşı-doğrula runbook | V3-G |
| Klavye kısayolları + hızlı komut (⌘K) | Modüller arası zıplama, "listeye ekle" gibi eylemler | V3-B/F |

---

## 4. FAZLARA GÜNCEL DAĞILIM (v1 fazları korunur, içerik zenginleşir)

- **V3-A Temel + İstihbarat:** Ürün≠İlan, zenginleştirilmiş yakalama, Skor v1,
  Keşif modülü (havuz+filtre+karşılaştırma), iş kuyruğu, görsel boru hattı.
- **V3-B Profesyonel Arayüz:** yeni sol menü mimarisi, Panorama, tam metin
  arama, kaydedilmiş görünümler, toplu işlem çerçevesi, PWA, ⌘K, tema.
- **V3-C Firma Döngüsü:** portal + bildirim merkezi + revizyon/tur tarihçesi
  + firma performans kartı + çift dil.
- **V3-D Sipariş Derinliği:** Siparişler (ödeme/parti/mal kabul/rücu) +
  Numuneler modülü + Lojistik & Maliyet temel.
- **V3-E Çok Platform:** Alibaba → AliExpress → Taobao → Temu → Amazon;
  Kârlılık modülü (komisyon simülasyonu) bu fazda.
- **V3-F Zeka + Zaman:** yeniden yakalama, fiyat/satış grafikleri, ivme,
  Raporlar modülü.
- **V3-G Platform Olgunluğu:** VDS, API anahtarları, ikinci hesap, yedek UI.
- **V3-H Katalog Hazırlık (OPSİYONEL):** Türkçeleştirme çalışma alanı +
  görsel seti + Woo taslak çıkışı. G'den sonra veya E ile paralel —
  Ürün Sahibi kararına bağlı.

---

## 5. KARAR NOKTALARI (Ürün Sahibi'nden yanıt bekler)

1. **KARAR-1 Kârlılık/komisyon:** TilbeSync'in komisyon mantığı tedarikapp
   içine mi kopyalanır, yoksa iki uygulama arasında veri köprüsü mü kurulur?
   (PM önerisi: içeri kopyala — tek kiracı, bağımlılık azalır.)
2. **KARAR-2 Katalog AI:** başlık/açıklama üretimi TilbeCore AI Assistant
   altyapısıyla mı (çok sağlayıcılı), yoksa şimdilik sadece K54 çeviri
   önerisiyle mi sınırlı kalsın? (PM önerisi: V3-H'ye kadar K54 yeter.)
3. **KARAR-3 Koyu tema:** istenirse V3-B'de tasarım sistemine baştan girer
   (sonradan eklemek pahalı). Evet/Hayır.
4. **KARAR-4 V3-H Katalog fazı:** kapsama girsin mi, girerse E ile paralel mi
   G sonrası mı?

## 6. KALICI RET'LER (v1 + bu turda netleşenler)
RFQ/tedarikçi pazarlık modülleri · GTİP · e-posta/push bildirim · çok
kiracılık/ürünleştirme · pazaryeri API entegrasyonları (Katalog çıkışı yalnız
kendi WooCommerce'ine) · watermark/telif müdahalesi.

---

## 7. SEKTÖR ARAŞTIRMASI EKLERİ (19 Ağu araştırma raporundan — kaynaklı)

Çinli satıcıların/araçların (SellerSprite, 店雷达, 1688 karne sistemi) ve profesyonel
sourcing firmalarının pratiklerinden tedarikapp'e aktarılan maddeler. Her madde
gittiği faza işaretli; ayrı faz açılmaz.

### 7.1 Keşif / Skor (V3-A ve V3-F)
- **7.1.1 Preset seçim modları:** hazır filtre şablonları — "Yeni + Yükselen"
  (yayın son 30-90 gün + satış artışı), "Kanıtlanmış Çok Satan", "Mavi Okyanus"
  (düşük rekabet). Tek tık uygulanır, kullanıcı kendi görünümünü kaydeder. [V3-A]
- **7.1.2 İvme sinyali:** yeniden yakalamalar arası satış artış oranından
  büyüme yüzdesi; ürün kartında yeşil/kırmızı ok + %. Skora "ivme" alt bileşeni
  olarak girer (tazelik bileşeni ikiye bölünür: tazelik + ivme). [V3-F]
- **7.1.3 Sezonluk "geçen yıl aynı dönem":** tarihçe biriktikçe ürün/kategori
  için geçen yılın aynı ayına kıyas görünümü. [V3-F]
- **7.1.4 Satıcı karnesi zenginleştirme:** yakalamada 1688 satıcı sinyalleri
  alınır — rozet katmanı (诚信通 yılı / 实力商家 / 超级工厂), 48 saat kargo,
  tekrar alım oranı (varsa), yanıt süresi; skorun "satıcı karnesi %20"
  bileşeni 1688'in beş boyutlu ağırlık şemasından esinlenerek doldurulur
  (kalite %30 · termin %25 · özelleştirme %20 · iletişim %15 · satış sonrası %10
  — kendi verimizle kalibre edilir, birebir kopyalanmaz). [V3-A]
- **7.1.5 Görselle ters arama (以图搜款):** eklentide "bu görselle benzer ara"
  — Douyin/Instagram/Pinterest'te görülen ürünün görselini platform içi görsel
  aramaya yönlendirme (dış istek yok, kullanıcı tıklar). [V3-E/F, orta-yüksek]

### 7.2 Listeler / Karşılaştırma (V3-A/B)
- **7.2.1 Matrise karne kolonları:** karşılaştırma matrisine rozet katmanı,
  satıcı yılı, 48s kargo, yanıt süresi kolonları. [V3-A]
- **7.2.2 Aynı/benzer ürün kümeleme:** aynı ürünü farklı satıcılardan otomatik
  gruplama, en iyi fiyat/karne kombinasyonu vurgusu. [V3-F, yüksek]

### 7.3 Firma Portalı / Teklif Döngüsü (V3-C — en yüksek getiri)
- **7.3.1 "7 satır + 3 dipnot" RFQ standardı:** listeye giden belgede zorunlu
  alanlar — ürün adı+kod, spesifikasyon/ambalaj, adet+birim, Incoterm+YER
  (DDP Sakarya gibi), birim fiyat+para birimi+kapsamı, termin+BAŞLANGIÇ
  NOKTASI (kapora günü), ödeme şekli+TEKLİF GEÇERLİLİK SÜRESİ; dipnotlar —
  test/sertifika sorumluluğu, DDP'de vergi/gümrükleme kapsamı, numune ücreti
  ve mahsup kuralı. Geçerlilik süresi kur kilidiyle bağlanır. [V3-C]
- **7.3.2 Firma cevap formunda zorunlu alan seti:** fiyat + MOQ + termin +
  koli bilgisi — "her tedarikçi aynı kalemleri doğrular" ilkesi; eksik alanla
  "tamamlandı" denemez. [V3-C]
- **7.3.3 Kademeli miktar teklifi (阶梯价):** ürün başına {adet eşiği → DDP
  birim fiyat} kademeleri (örn. 500/1000/2000); firma her kademeye ayrı fiyat
  girer, panel kademe-duyarlı toplam/marj hesaplar. Termin kademe başına
  DEĞİL sipariş başına alınır. [V3-C + Kârlılık bağı V3-E]
- **7.3.4 Teklif durum takibi + link analitiği:** gönderildi → GÖRÜLDÜ (link
  açılma zamanı/sayısı) → fiyatlandı → onaylandı/reddedildi; "firma X gündür
  açmadı" panel içi hatırlatma. K51 üstüne, ucuz ve yüksek değerli. [V3-C]
- **7.3.5 Sürüm kontrollü teklif:** zaten F7'de gelen Rev A/B/C'ye ek olarak
  revizyonlar arası FARK görünümü (hangi ürün/fiyat değişti). [V3-C]

### 7.4 Numuneler (V3-D)
- **7.4.1 Değerlendirme formu 4 boyut:** ölçü/malzeme · güvenlik/fonksiyon ·
  dayanıklılık · ambalaj/etiket — her biri puanlı + foto. [V3-D]
- **7.4.2 Kusur sınıflaması:** kritik / major / minor (AQL Level II
  esinli; sayısal eşik girilebilir). [V3-D]
- **7.4.3 "Altın onay numunesi" (确认样) kaydı:** onaylanan numune referans
  işaretlenir; mal kabulde seri üretim buna karşı denetlenir. [V3-D]
- **7.4.4 Numune ücreti mahsup takibi:** ücret + siparişten mahsup edildi mi
  alanı. [V3-D]

### 7.5 Siparişler (V3-D)
- **7.5.1 Üretim kilometre taşı zaman çizelgesi:** numune → malzeme → üretim →
  kalite → sevk; her aşama tarih + foto/kanıt (1688 万采万链 pratiği). [V3-D]
- **7.5.2 Kanıta bağlı ödeme:** kapora/bakiye kalemi kapatılırken dekont/foto
  ekleme opsiyonu — "emin konuşuyor diye ödeme yapılmaz" ilkesi. [V3-D]

### 7.6 Raporlar / İzleme (V3-F)
- **7.6.1 Fiyat/satış tarihçesi grafiği:** yeniden yakalamalardan ürün kartında
  çizgi grafik (Keepa Trends kalıbı). [V3-F]
- **7.6.2 Uyarılar:** fiyat düşüşü · ilan kapandı · stok değişimi · satıcı
  karnesi düşüşü → panel içi bildirim merkezine. [V3-F]
- **7.6.3 Firma performansında çeyreklik gözden geçirme + trend:** tek skor
  değil eğilim izlenir; kötüleşmede "iyileştirme notu" alanı. [V3-C/F]

### 7.7 Uygulama dalgaları (araştırma raporundaki öncelik tablosuna göre)
- **Dalga 1 (düşük emek/yüksek getiri):** 7.3.1 · 7.3.4 · 7.1.1 · 7.2.1 · 7.4.4
- **Dalga 2:** 7.1.4 · 7.1.2 · 7.4.1-7.4.3 · 7.3.3 · 7.6.1
- **Dalga 3 (emek yoğun/farklılaştırıcı):** 7.6.2 · 7.1.5 · 7.3.5 · 7.5.1 · 7.1.3 · 7.2.2

### 7.8 Güvenilirlik notu
1688 kaynaklı yüzdeler (tekrar alım %37, "48s kargo ürünlerinin %78'i MOQ≥500",
"%83 numune mahsubu") tek kaynaklıdır — yön gösterici kabul edilir, ürün içine
sabit metrik olarak gömülmez. Amazon araçlarının eşikleri (satış>500, yorum<50
vb.) mantık olarak alınır, 1688/Türkiye toptan modeline kendi verimizle yeniden
kalibre edilir.

---

## 8. LİSTE YAŞAM DÖNGÜSÜ DURUMLARI (Ürün Sahibi talebi, 19 Ağu)

Liste başlığı seviyesinde durum zinciri (ürün bazlı Sipariş Verildi/Bekleme/İptal
rozetleri ayrıca yaşamaya devam eder; bu zincir listenin BÜTÜNÜNÜN nerede
olduğunu söyler). "17+ durum" RET'ine sadık kalınarak 10 durum + 1 kesici:

1. **Hazırlanıyor** — taslak; ürün ekleme/çıkarma serbest, kur güncel kuru izler.
2. **Fiyat bekleniyor** — firmaya iletildi; kur bu anda kilitlenir (K48),
   teklif geçerlilik süresi işlemeye başlar (7.3.1). Firma çalışma alanı bu
   durumda yazılabilir olur.
3. **Değerlendirmede** — firma DDP fiyatları döndü; ürün bazında
   Onayla/Reddet/Tekrar sor turu (V3-C). (Kısmî dönüşte liste burada kalır,
   ilerleme çubuğu "18/25 fiyatlandı" gösterir.)
4. **Sipariş verildi** — onaylı ürünler sipariş oldu; kapora kaydı açılabilir.
5. **Üretim / tedarikte** — firmanın Çin ekibi topluyor/ürettiriyor
   (kilometre taşı kanıtları 7.5.1 burada akar).
6. **Çin limanında** — konsolide edildi, yüklemeye hazır / yüklendi.
7. **Gemide (yolda)** — konşimento + ETA alanları etkin; ETA yaklaşınca
   panel içi bildirim.
8. **Türkiye limanında** — vardı; gümrük/çekim süreci (yalnız durum etiketi —
   mevzuat takibi kapsam dışı, RET korunur).
9. **Teslim edildi** — depoya girdi; mal kabul sayımı (V3-D) bu durumda açılır.
10. **Kapandı** — mal kabul tamam, hasar/rücu süreci sonuçlandı; liste
    arşiv-benzeri salt okunur olur (raporlara veri kaynağı).
- **İptal edildi** — her durumdan geçilebilen kesici durum (gerekçe notuyla).

Kurallar:
- Durumlar tek yönlü ilerler; geri alma yalnız bir adım ve gerekçe notuyla
  (denetim kayıtlı).
- 5-8 arası lojistik durumları parti/konteyner bazında da tutulur (V3-D);
  liste birden çok partiye bölündüyse liste durumu EN GERİDEKİ partiyi gösterir,
  yanında "2/3 parti Türkiye'de" özeti.
- Her durum geçişi tarih damgalı → listede yatay zaman çizelgesi (stepper)
  görünümü; Excel/PDF/paylaşım çıktılarının üst bandında güncel durum rozeti.
- Faz ataması: 1-4 V3-C (firma döngüsüyle birlikte), 5-10 V3-D (sipariş
  derinliği/lojistik); mevcut sistemdeki basit liste durumu V3-C'de bu zincire
  migrate edilir.
