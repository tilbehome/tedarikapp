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

### 2b. Gerçeklenen Ekran ↔ Rota (Faz 1D · İE#8)

Panel `public/panel/` altından sunulur ve `/panel` ön ekiyle çalışır (React Router `basename`). Slim'deki `/panel[/{path:.*}]` catch-all'ı istemci tarafı rotaları `index.html`'e verir; sayfa yenilendiğinde 404 alınmaz.

| # | Ekran | Rota | Dosya | Durum |
|---|---|---|---|---|
| E1 | Giriş | `/panel/giris` | `screens/LoginScreen.tsx` | ✅ |
| E2 | Ana Ekran | `/panel/` | `screens/HomeScreen.tsx` | ✅ |
| E3 | Listeler | `/panel/listeler` | `screens/ListsScreen.tsx` | ✅ |
| E4 | Liste Detayı | `/panel/listeler/:id` | `screens/ListDetailScreen.tsx` | ✅ (export/paylaşım butonları "Faz 2" rozetiyle pasif) |
| E5 | Ürün Ekle/Düzenle | `/panel/listeler/:id/urun/yeni` · `/panel/listeler/:id/urun/:productId` | `screens/ProductFormScreen.tsx` | ✅ |
| E6 | Gelen Kutusu | — | — | ⏳ Faz 3 (menüde "yakında" olarak durur) |
| E7 | Çöp Kutusu | `/panel/cop-kutusu` | `screens/TrashScreen.tsx` | ✅ |
| E8 | Ayarlar | `/panel/ayarlar` · `/panel/ayarlar/kategoriler` | `screens/SettingsScreen.tsx` · `screens/CategoriesScreen.tsx` | ✅ (token üretimi/2FA yenileme Faz 3) |
| E9 | Aktivite | `/panel/aktivite` | `screens/ActivityScreen.tsx` | ✅ |

**Kategoriler** docs/09'da E8'in içeriğidir; ekranda Ayarlar'ın alt sayfası olarak açılır (`/panel/ayarlar/kategoriler`).

**Para kuralı (K14/K29):** panelde para aritmetiği YOKTUR. Tutarlar API'den `string` gelir, `lib/format.ts` yalnızca karakter düzeyinde biçimlendirir (binlik ayracı + virgül), TOPLAM satırı backend'in `totals` alanından okunur.

**Durum geçişleri:** ürün ve liste durum menüleri `GET /api/system/state-machine` haritasından kurulur; arayüz kendi kopyasını tutmaz. Kural yine de backend'de zorlanır — arayüz katmanı yalnızca geçersiz seçeneği sunmama işini yapar.

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

Veritabanı ve API **yalnızca** sol sütundaki İngilizce kodları taşır. Türkçe karşılıklar arayüz etiketidir; koda, DB'ye veya JSON'a girmez. Arayüz bu tabloyu tek kaynak olarak kullanır: **`frontend/src/locales/tr.ts`** (İE#8 §4 bu yolu şart koştu; bu belgedeki eski `i18n/labels.ts` yolu geçersizdir). Aktivite kaydı kodlarının Türkçe karşılıkları `frontend/src/lib/activityLabels.ts` dosyasındadır.

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
