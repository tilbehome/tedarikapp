# Tedarikapp V3 — Yol Haritası ve Geliştirme Anayasası

**Sürüm:** Taslak 1.0 · 19 Ağustos 2026 · Ürün Sahibi onaylı iskelet üzerine
**Kapsam:** Faz 4 (v0.11.0) sonrası tüm geliştirme. Repodaki docs/v2 vizyonunu yutar ve
günceller; çelişki hâlinde bu belge geçerlidir.
**Takvim beklentisi:** 6–12 ay, fazlar sırayla; her faz tek büyük iş emri + tek release.

---

## 1. İş modeli gerçeği (her kararın turnusolu)

- 1688 ve diğer platformlar **vitrindir**; oradaki satıcılardan sipariş verilmez.
- Akış: ürün bul ve incele → liste oluştur → **ithalatçı firmaya ilet** → firmanın Çin
  ekibi toptancıdan bulur, **DDP fiyat döner** → onaylananlara sipariş → teslimat.
- Muhatap tek ithalatçı firmadır. Gelecek yıl firma süreci Ürün Sahibi'ne öğretecek
  (birlikte Çin ziyareti planlı) — sistem, sürecin bir gün devralınabileceğini bilerek
  tasarlanır (özellikle V3-D).
- Gümrük/mevzuat işleri (GTİP, İGV, uygunluk) kalıcı olarak **kapsam dışıdır**;
  sistemdeki tek gümrük teması pasif menşe kaydıdır.

## 2. Temel kararlar (Ürün Sahibi cevaplarından, 19 Ağu 2026)

| Konu | Karar |
|---|---|
| Kiracılık | Tek kiracılı — ürünleştirme yok, kendi operasyonu |
| Kullanıcı | Tek kullanıcı; ileride ithalatçı arkadaşa ayrı hesap (acil değil → V3-G) |
| Ölçek | Ayda birkaç liste → iş akışı oturunca haftalık liste |
| Firma | Tek firma; paralel firma yarıştırma yok |
| Metrik ağırlıkları | PM kalibre eder, kullanıcı ayarlayabilir |
| Eşik/eleme | Sert eşik yok → **Tedarikapp Skoru** (puanlama) |
| Platform hedefi | Alibaba.com, AliExpress, Taobao, Temu, diğer Çin siteleri + Amazon.com |
| Portal dili | Türkçe arayüz; firmaya giden içerikte İngilizce+Çince alt başlıklar |
| Pazarlık | Var → revizyon turları birinci sınıf kavram |
| Sipariş takibi | Derin: ödeme (kapora/bakiye), parti/konteyner, mal kabul sayım+hasar |
| Bildirim | Yalnız panel içi (WhatsApp API'ye girilmez) |
| Mobil | Yoğun mobil kullanım, PC birincil → responsive + PWA |
| Altyapı | VDS'ye geçilecek (yeri esnek, ihtiyaç tetikler) |

## 3. Tedarikapp Skoru (0–100)

Her yakalanan ilana, karar desteği için tek bakışta okunan skor:

| Bileşen | Ağırlık (v1) | Kaynak |
|---|---|---|
| Satış hacmi | %35 | 30 günlük dağıtım adedi, toplam satış, SKU satışları |
| Değerlendirme kalitesi | %25 | Puan × yorum sayısı, olumlu oran |
| Satıcı karnesi | %20 | Mağaza yılı, tekrar alım oranı, zamanında kargo, mağaza puanı |
| Tazelik | %10 | Yayın tarihi (yeni ve satıyor = güçlü sinyal) |
| Veri tamlığı | %10 | Görsel sayısı, video varlığı, özellik zenginliği |

Kurallar: ağırlıklar ayarlar ekranından değiştirilebilir; skor bileşenleri ürün
kartında şeffaf dökümle gösterilir; kaynak sayfada bulunmayan metrik **uydurulmaz**,
skor eksik veriyle orantılı normalize edilir ve "eksik veri" rozeti taşır
(Kaynak→Gerçek→Karar ilkesi).

## 4. Fazlar

### V3-A · Temel + Ürün İstihbaratı (kalp)
**Amaç:** Karar hammaddesinin tamamını yapılandırılmış olarak toplamak.
- A1. Ürün ≠ İlan mimarisi: `products` (senin ürünün) ↔ `source_listings`
  (platformdaki ilan); mevcut veriler migration ile taşınır, RAW korunur.
- A2. Yakalama zenginleştirme (1688): yayın tarihi, satış metrikleri (30 gün /
  toplam / SKU bazında satış+stok), değerlendirme (puan, yorum sayısı, olumlu oran),
  satıcı karnesi (yıl, tekrar alım, zamanında kargo, mağaza puanı), tüm görseller,
  video kimliği+poster, özellik tablosu, min. sipariş+birim.
- A3. Tedarikapp Skoru v1 (yukarıdaki tablo) + ürün kartında skor rozeti ve dökümü.
- A4. Panelde metrik filtre/sıralama: skor, satış, puan, tarih, video var/yok vb.
- A5. İlan anlık görüntüsü temeli: her yakalama tarihli snapshot olarak saklanır
  (V3-F ivme analizinin zemini; ucuz kurulur, sonra işlenir).
**Kabul:** Gerçek bir 1688 ürününde tüm metrikler panelde görünür ve doğru; skor
hesabı şeffaf; eski ürünler kırılmadan yeni mimaride yaşar.

### V3-B · Profesyonel Arayüz (eski Faz 5'i yutar)
**Amaç:** "Amatör görünüm ve zayıf işleyiş" şikâyetinin kökten çözümü.
- B1. Dashboard (PM maketle gelir, onayla kesinleşir): bekleyen firma cevapları,
  aktif listeler ve durum dağılımı, dönem sipariş özeti (adet/¥/₺), son
  yakalananlar, skor dağılımı, sistem sağlığı.
- B2. Liste oluşturma "seçim ekranı": filtrele → işaretle → listeye bas akışı.
- B3. Karşılaştırma matrisi: seçilen ilanlar yan yana (fiyat kademeleri, satış,
  karne, tarih, skor) — "firmaya hangisini sorayım" ekranı.
- B4. Komut paleti (Ctrl+K), evrensel arama.
- B5. Koyu tema; tipografi/aralık/renk sisteminin Şablon v2 diliyle hizalanması.
- B6. Mobil cila + PWA (kurulabilir panel, telefonda tam işlev).
**Kabul:** Ürün Sahibi maketleri onaylar; günlük akış (yakala→incele→listele)
yalnız yeni ekranlarla, eski ekrana dönmeden yürür.

### V3-C · Firma Teklif Döngüsü
**Amaç:** WhatsApp'ta dağılan fiyat trafiğinin tek, kayıtlı hatta inmesi.
- C1. Firma Çalışma Alanı: yazma yetkili özel link + opsiyonel erişim anahtarı
  (K51 modelinin genişletilmesi; iptal anında öldürür, her yazma denetim kayıtlı).
- C2. Firma alanları: ürün başına DDP fiyat, durum (Bulundu / Bulundu ama… /
  Olmadı / Alternatif var), not, alternatif önerisi. Başka hiçbir veriye erişemez.
- C3. Teklif turu: firma turu "tamamladım" ile kapatır; Ürün Sahibi ürün başına
  Onayla / Reddet / **Tekrar sor** (revizyon turu) — tur tarihçesi saklanır,
  "bu ürüne geçen ay ne fiyat verilmişti" tek tıkla.
- C4. Onaylananlar otomatik sipariş durumuna ilerler; reddedilenler arşive.
- C5. Panel içi bildirim merkezi (çan): firma girişi, tur kapanışı, sistem olayları.
- C6. Çift dilli içerik: portal satırlarında ve Excel/PDF çıktısında İngilizce+Çince
  alt başlıklar (Çin ekibine iletilebilir tek belge).
**Kabul:** Gerçek firma kullanıcısı bir turu uçtan uca portal üzerinden tamamlar;
revizyon turu canlıda çalışır; tüm girişler denetim kaydında.

### V3-D · Sipariş Derinliği
**Amaç:** Onaydan teslimata kadar iz.
- D1. Sipariş kaydı (onaylı ürünlerden otomatik doğar): firma, dönem, kalemler.
- D2. Ödeme takibi: kapora/bakiye planı, ödeme kayıtları, kalan bakiye görünümü.
- D3. Sevkiyat: parti/konteyner bilgisi, termin, durum güncellemeleri (firma
  portalından da işlenebilir).
- D4. Mal kabul: sayım (beklenen/gelen), hasar-eksik kaydı, fotoğraflı tutanak.
- D5. Belge arşivi: sipariş başına dosya ekleri (fatura, çeki listesi vb.).
**Kabul:** Bir gerçek sipariş dönemi uçtan uca sistemde yürür; ödeme ve mal kabul
kayıtları raporlanabilir.

### V3-E · Çok Platform
**Amaç:** Tek tık yakalamanın tüm vitrinlere yayılması.
- E1. Bağlayıcı mimarisi: platform = seçici seti + parser modülü + platform rozeti
  (1688 deneyiminden şablonlaşır; seçiciler veridir ilkesi korunur).
- E2. Sıra: Alibaba.com → AliExpress → Taobao/Tmall → Temu → Amazon.com →
  diğerleri (DHgate, Made-in-China, Yiwugo…). Her platform kendi küçük emri;
  metrik eşlemesi platform yeteneğine göre (her sitede her metrik yok — dürüst
  eşleme tablosu belgeye işlenir).
- E3. Eklenti çok-platform: adres tanıma, platforma göre parser seçimi, rozet.
**Kabul:** Her yeni platformda gerçek ürünle canlı yakalama kanıtı + metrik
eşleme tablosu.

### V3-F · Zeka ve Zaman
**Amaç:** Birikmiş veriden görü üretmek.
- F1. Tam çeviri hattı: başlık+özellik çevirisinin kaliteli sağlayıcıyla
  güçlenmesi (K54 öneri ilkesi korunur), toplu çeviri.
- F2. İvme/tarihçe: aynı ilanın yeniden yakalanması → satış/fiyat değişim grafiği,
  "yükselen ürün" işareti (V3-A5 snapshotlarından beslenir).
- F3. Görselden benzer ürün: arşiv içinde benzerlik (hash-önce, ucuz yöntem);
  "bu ürünü daha önce yakalamış mıyım" cevabı.
- F4. Trend keşif: 1688 sıcak satış sinyalleri, kategori izleme (havuzdaki F39).
**Kabul:** İvme grafiği gerçek tarihçeyle çalışır; benzerlik eşleşmesi örnek
kümede doğrulanır.

### V3-G · Platform Olgunluğu
**Amaç:** Büyüyen sistemin sağlam zemini.
- G1. VDS'ye taşınma: ayrı "kuruluş + taşıma" emri (yeri esnek — performans veya
  özellik ihtiyacı tetiklerse öne çekilir; adaylar önceden belirlenmişti).
- G2. İkinci kullanıcı hesabı: ayrı e-posta/şifre, basit yetki (tam erişim /
  salt görüntüleme) — çok-kullanıcı matrisine girilmez.
- G3. İzleme ve sağlık: hata bildirimi, yedek doğrulama raporu, disk/kota uyarısı.
- G4. Performans turu: büyüyen veriyle sorgu/indeks bakımı, sayfa hızı.
**Kabul:** VDS'de kesintisiz geçiş (paylaşım linkleri dahil); ikinci hesap canlı.

## 5. Sıralama ve teslim düzeni

- Fazlar **A → B → C → D → E → F → G** sırasıyla; her faz = tek büyük iş emri
  (bloklu), tek release, PM denetimli merge, faz sonu canlı tur — mevcut çalışma
  düzeni aynen sürer.
- VDS (G1) esnektir: C veya D sırasında ihtiyaç doğarsa bağımsız emirle öne alınır.
- Şablon/çıktı sistemi Faz 4'te (v0.11.0) kurulan tasarım dilini kullanır; V3-C
  çift dilli genişletmeyi onun üstüne ekler.
- docs/v2 belgeleri arşive alınır; madde eşlemesi (hangi v2 maddesi hangi V3
  fazına gitti) belge repoya girerken tek tabloyla işlenir.

## 6. Kapsam dışı (kalıcı RET'ler — değişmedi)

Gümrük/mevzuat (GTİP, İGV, TAREKS, sertifika); resmî platform API'leri (Çin işletme
kaydı ister — tarayıcı içi yakalama kalıcı yöntem); çok-müşterili ürünleştirme;
WhatsApp/e-posta bildirim altyapısı (panel içi yeterli); tedarikçiyle doğrudan
pazarlık/RFQ modülleri (iş modelinde muhatap tek firma).
