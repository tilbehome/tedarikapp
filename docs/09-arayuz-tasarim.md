# tedarikapp — Arayüz Tasarımı ve Ekran Envanteri

> Durum: v1.0 — ONAYLANDI (16.08.2026)
> Görsel dil (renk, tipografi, bileşen stili) Faz 1'in arayüz iş emrinde bu ilkelere göre netleştirilir.

## 1. Tasarım İlkeleri

- **Mobile-first, uygulama seviyesi.** Panel önce telefonda tasarlanır, masaüstü genişletmedir. Tilbe Home standartı: estetik, zarif, kullanıcı dostu, kolay, erişilebilir.
- **Tek el kullanımı:** ana aksiyonlar (durum ilerletme, ürün ekleme, export) başparmak erişim bölgesinde; kritik aksiyonlar alt çubukta.
- **Minimum tıklama:** en sık işler (durum ilerlet, Gelen Kutusu'ndan listeye ata) en fazla 2 dokunuş.
- **Hız hissi:** sayfa geçişleri anlık (SPA), işlem sonrası iyimser güncelleme + arka planda kaydetme; görseller lazy-load + küçük önizleme (thumbnail).
- **Affedicilik:** yıkıcı işlemler (sil, iptal) onay ister ve çöp kutusuyla geri alınabilir; işlem sonrası "Geri Al" bildirimi.
- **Durum görünürlüğü:** her listede ilerleme çubuğu (kaç ürün hangi durumda), "çıktı güncel değil" rozeti belirgin.
- **Arayüz dili Türkçe;** para biçimleri Türkçe (₺1.234,56 / ¥9,00 / $12,50).
- **Erişilebilirlik temeli:** yeterli kontrast, 44px dokunma hedefleri, form hatalarının metinle bildirilmesi.

## 2. Ekran Envanteri (panel — React)

| # | Ekran | İçerik / ana aksiyonlar |
|---|---|---|
| E1 | Giriş | E-posta+şifre → TOTP kodu; kurtarma kodu girişi; kilit/backoff mesajları |
| E2 | Ana Ekran | Özet kartları (aktif liste, yoldaki ürün, Gelen Kutusu bekleyen), son aktiviteler, hızlı erişim |
| E3 | Listeler | Aktif/Pasif/Arşiv sekmeleri; kart/satır başına ilerleme + son export bilgisi; oluştur, kopyala, pasife al, arşivle |
| E4 | Liste Detayı | Ürün tablosu (görsel, ad, varyasyon, adet, fiyatlar, durum), TOPLAM satırı, arama/filtre, toplu işlem, export butonları, paylaşım linki yönetimi, "çıktı güncel değil" rozeti |
| E5 | Ürün Ekle/Düzenle | Elle giriş formu; eklentiden gelen üründe alanlar dolu gelir; varyasyon seçimi; tekrar-ekleme uyarısı |
| E6 | Gelen Kutusu | Eklentiden düşen ürünler; her kart: listeye ata + adet/kategori tamamla; hatalı yakalamalar ayrı bölümde |
| E7 | Çöp Kutusu | Silinen liste/ürünler, kalan gün, geri al / kalıcı sil |
| E8 | Ayarlar | Kurlar (tarihçeli), kategoriler, eklenti API token'ı, güvenlik (şifre/2FA/kurtarma kodları), yedek durumu |
| E9 | Aktivite | Liste bazlı işlem geçmişi (kim-ne-ne zaman — Faz 4'te genişler) |

## 3. Dışa Açık Sayfa (paylaşım — sunucu render)

| # | Sayfa | İçerik |
|---|---|---|
| P1 | Paylaşım Görünümü | Liste başlığı + dönem; ürün kartları: görsel(ler), gömülü video oynatıcı, ad + orijinal başlık, detay, varyasyon, adet, fiyatlar, 1688 linki; TOPLAM; mobil öncelikli, JS'siz de okunabilir; noindex |

## 4. Eklenti Arayüzü (popup)

| # | Görünüm | İçerik |
|---|---|---|
| X1 | Yakalama önizleme | Ürün başlığı, görsel seçimi, varyasyon/fiyat kademesi seçimi, hedef liste (varsayılan: Gelen Kutusu), "Panele Gönder" |
| X2 | Durum | Bağlantı/token durumu, bekleyen kuyruk, son gönderilenler |

## 5. Ekran Dışı Kurallar

- Boş durumlar (hiç liste yok, Gelen Kutusu boş) yönlendirici mesaj + aksiyon butonu içerir, boş beyaz sayfa bırakılmaz.
- Yükleme durumları iskelet (skeleton) ile gösterilir; hata durumları yeniden dene butonuyla gelir.
- Tablolar telefonda karta dönüşür (yatay kaydırmalı dev tablo dayatılmaz).

## 6. Makine Değeri ↔ Türkçe Etiket Çeviri Tablosu (K22)

Veritabanı ve API **yalnızca** sol sütundaki İngilizce kodları taşır. Türkçe karşılıklar arayüz etiketidir; koda, DB'ye veya JSON'a girmez. Arayüz bu tabloyu tek kaynak olarak kullanır (`frontend/src/i18n/labels.ts`).

**Ürün durumu** (`products.status`)

| Kod | Türkçe etiket |
|---|---|
| `to_order` | Verilecek |
| `ordered` | Verildi |
| `in_transit` | Yolda |
| `received` | Geldi |
| `cancelled` | İptal |

**Liste durumu** (`lists.status`)

| Kod | Türkçe etiket |
|---|---|
| `draft` | Taslak |
| `sent` | İletildi |
| `ordered` | Sipariş Verildi |
| `completed` | Tamamlandı |
| `cancelled` | İptal |

**Liste görünürlüğü** (`lists.visibility`)

| Kod | Türkçe etiket |
|---|---|
| `active` | Aktif |
| `passive` | Pasif |
| `archived` | Arşiv |

**Gelen kutusu durumu** (`inbox_items.status`)

| Kod | Türkçe etiket |
|---|---|
| `pending` | Bekliyor |
| `error` | Hatalı |
| `assigned` | Atandı |

> Not: `ordered` hem ürün hem liste durumunda geçer ama farklı tablolarda ve farklı anlamlarda (ürün: sipariş verildi · liste: sipariş verildi). Karışıklık olmaması için kod içinde durumlar daima kendi enum/sabit setleriyle kullanılır.
