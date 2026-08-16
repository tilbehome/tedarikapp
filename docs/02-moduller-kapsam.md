# tedarikapp — Modüller ve Kapsam

> Durum: v1.0 — ONAYLANDI (16.08.2026)

## Modül Haritası

```
tedarikapp
├── M1  Giriş & Ayarlar
├── M2  Sipariş Listeleri
├── M3  Ürün Yönetimi
├── M4  Chrome Eklentisi (veri yakalama)
├── M5  Durum Takibi
├── M6  Export (Excel / PDF / CSV)
├── M7  Paylaşım Linki (firma görünümü)
└── M8  Panel Ana Ekranı (özet/istatistik)
```

## M1 — Giriş & Ayarlar

- Tek admin kullanıcı; e-posta + şifre ile giriş, oturum yönetimi.
- Ayarlar: güncel Yuan→TL kuru, Dolar→TL kuru (elle girilir, tarihçesi tutulur — eski listeler girildiği günün kuruyla kalır).
- Kategori yönetimi: kategoriler serbest metin değil, tanımlı liste (tutarlılık ve filtreleme için). Ekle/düzenle/sil.
- Eklenti API anahtarı: eklentinin panele veri gönderirken kullanacağı token burada üretilir/yenilenir.

## M2 — Sipariş Listeleri

Sistemin ana birimi tek tek ürünler değil, **sipariş listesidir**. Bir liste = firmaya iletilen bir sipariş dosyası.

- Liste oluştur: ad (örn. "Eylül 2026 DDP Sipariş"), tedarikçi firma adı, not.
- Liste durumları: `Taslak → İletildi → Sipariş Verildi → Tamamlandı` (+ `İptal`).
- Liste kopyalama (tekrar eden siparişler için önceki listeden başlat).
- **Görünümler:** panel listeleri üç sekmede sunar — **Aktif** (üzerinde çalışılan/süren siparişler), **Pasif** (dondurulan/bekletilen listeler) ve **Arşiv** (bitenler). Tek tıkla "Pasife al / Aktife döndür / Arşivle".
- **Düzenleme:** liste adı, dönem, tedarikçi ve not her zaman değiştirilebilir; ürün ekleme/çıkarma her durumda serbesttir — **export alınmış olması listeyi kilitlemez**, export yalnızca alındığı anın anlık görüntüsüdür.
- **Silme:** liste ve ürün silme çöp kutusuna gider, 30 gün içinde geri alınabilir; 30 gün sonra kalıcı silinir.

## M3 — Ürün Yönetimi

Bir ürün her zaman bir listeye aittir. Alanlar (örnek Excel ile birebir + ek):

| Alan | Kaynak |
|---|---|
| Sıra No | Otomatik |
| Ürün görseli (ana) | Eklenti otomatik / elle URL |
| Ek görseller | Eklenti otomatik (opsiyonel) |
| Video | Eklenti otomatik / elle URL — yalnızca paylaşım linkinde görünür |
| Kategori | Admin seçer (tanımlı liste) |
| Ürün adı | Admin girer (Türkçe); eklentinin yakaladığı orijinal başlık referans olarak saklanır |
| Varyasyon seçimi (SKU) | Eklentide varyasyon matrisi (renk/beden/adet kademesi) yakalanır, seçim popup'ta veya panelde yapılır |
| Satıcı/mağaza (ad + link) | Eklenti otomatik |
| Ürün detay | Admin girer (renk, beden, varyant notu) |
| Ürün linki (1688) | Eklenti otomatik / elle |
| Miktar | Admin girer |
| 1688 fiyatı (Yuan) | Eklenti otomatik / elle |
| 1688 fiyatı (TL) | Sistem hesaplar (Yuan × kur) |
| DDP fiyatı (Dolar) | Admin girer (firmadan gelen fiyat) |
| DDP fiyatı (TL) | Sistem hesaplar (Dolar × kur) |
| Ürün durumu | Bkz. M5 |
| Takip kodu | Admin girer ("Yolda" aşamasında kargo/konteyner takip no) |
| Not | Serbest |

- Ürün ekleme iki yolla: (a) eklentiden düşer, (b) panelde elle form.
- Eklentiden gelen ürün, eklentide hedef liste seçilmişse doğrudan o listeye; seçilmemişse **Gelen Kutusu'na** düşer (karar K10). Gelen Kutusu'ndakiler için admin listeyi seçer, adet/kategori/detayı tamamlar.
- Toplu işlemler: seçili ürünleri başka listeye taşı, sil, durum değiştir.
- Arama + filtre: kategori, durum, liste, ad içinde arama.
- Görseller panele indirilip sunucuda saklanır (1688 linki ölse bile liste bozulmaz).

## M4 — Chrome Eklentisi

- 1688 ürün sayfasında ikon aktifleşir; tek tıkla sayfadan şunları okur: başlık, fiyat, **tüm SKU/varyasyon matrisi (renk/beden/adet kademeleri, JSON)**, ana görsel, ek görseller, video URL, ürün URL, **1688 ürün ID'si**, **satıcı mağaza adı ve linki**.
- Okunan veri, ayarlardaki API anahtarıyla panelin API'sine gönderilir → Gelen Kutusu'na (veya seçilen listeye) düşer.
- **Tekrar kontrolü:** aynı 1688 ürün ID'si sistemde varsa panel "bu ürün daha önce eklendi" uyarısı gösterir (hangi listede olduğu linkiyle).
- Gönderim öncesi mini önizleme: fiyat kademesi ve ana görsel seçilebilir; istenirse hedef liste de eklentiden seçilir (varsayılan: Gelen Kutusu).
- Panel erişilemezse veri eklenti içinde bekler, bağlantı gelince gönderilir.

## M5 — Durum Takibi

- Ürün bazında durum: `Verilecek → Verildi → Yolda → Geldi` (+ `İptal`).
- Liste görünümünde tek tıkla durum ilerletme; toplu güncelleme.
- Liste bazında ilerleme göstergesi (örn. "24 üründen 18'i geldi").
- Her durum değişikliğinin tarihi tutulur (ne zaman yola çıktı, ne zaman geldi).

## M6 — Export

- **Excel (.xlsx)**: örnek dosya formatına birebir uygun, görseller hücreye gömülü. Video sütunu YOK. En alta **TOPLAM satırı** eklenir: toplam adet + toplam Yuan/TL ve toplam DDP Dolar/TL (K15).
- **PDF**: aynı içeriğin baskıya uygun hali, görselli. Video YOK.
- **CSV**: düz veri (muhasebe/başka sistemlere aktarım için).
- Export listenin tamamı veya filtrelenmiş hali üzerinden alınabilir.
- **Export geçmişi:** her çıktı (tarih, format) kaydedilir; liste ekranında "son çıktı: 16 Ağu, Excel" bilgisi görünür.
- **"Çıktı güncel değil" rozeti:** son export'tan sonra listede değişiklik yapıldıysa panel uyarır — firmaya eski dosyayla gidilmez.

## M7 — Paylaşım Linki

- Liste başına üretilen tokenli, tahmin edilemez URL (örn. `tedarik.example.com/p/AbC123xYz`).
- Firma girişsiz açar: ürünler görselli kart/tablo halinde, **videolar oynatılabilir**, 1688 linkleri tıklanabilir.
- Salt okunur; fiyatlar dahil tüm sütunlar her zaman görünür (karar K11 — fiyat gizleme kapsam dışı).
- Link iptal edilebilir / yenilenebilir.
- Mobil uyumlu (firma telefondan açacaktır).

## M8 — Panel Ana Ekranı

- Özet kartları: aktif liste sayısı, yoldaki ürün sayısı, bekleyen Gelen Kutusu öğeleri.
- Liste ve panel görünümlerinde toplam satırı: toplam adet, toplam Yuan/TL, toplam DDP tutarı.
- Son aktiviteler.
- (İleri faz) Aylık sipariş istatistikleri, kategori dağılımı.

## EK — Excel Çıktı Şablonu (ekran görüntüsünden birebir)

Referans: `uruntedariklistesi.xlsx` (16.08.2026 ekran görüntüsü)

- Başlık satırı (birleştirilmiş hücre): `ÇİNDEN DDP SİPARİŞ VERİLECEK ÜRÜNLER LİSTESİ - {DÖNEM}` — dönem kısmı kırmızı (örn. "EYLÜL 2026"). Panelde liste adı + dönem alanından otomatik üretilir.
- Sütunlar: NO | ÜRÜN GÖRSELİ | KATEGORİ | ÜRÜN ADI | ÜRÜN DETAY | ÜRÜN LİNKİ | MİKTAR | 1688 FİYATI | DDP FİYATI
- `1688 FİYATI` iki alt sütun: **YUAN** (sarı zemin) ve **TL** (yeşil zemin). `DDP FİYATI` iki alt sütun: **DOLAR** (mavi zemin) ve **TL** (yeşil zemin).
- Para biçimleri: `¥9,00`, `₺63,36`, `$0,00` — Türkçe ondalık (virgül).
- Ürün görseli hücre içine gömülü, satır yüksekliği görsele göre ayarlı; ürün linki tıklanabilir köprü.
- DDP sütunları liste ilk iletilirken boş/0 kalır — DDP fiyatını firma teklif ettikten sonra admin panele girer, güncel export'ta dolu çıkar. (Akış 4 ile uyumlu.)
