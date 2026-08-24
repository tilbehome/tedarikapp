# Görev #13 — Teslim Raporu

**Paket:** V3-B Hazırlık Paketi  
**Üretim tarihi:** 23 Ağustos 2026  
**Kapsam:** 3 JSON + 1 Markdown ana teslim ve bu rapor

## 1. Teslim dosyaları ve sayımlar

| Parça | Dosya | İçerik | Sayım |
|---|---|---|---:|
| 13A | `panorama-brifing-katalogu.json` | Koşul → brifing şablonu | 18 |
| 13A | `panorama-brifing-katalogu.json` | Aksiyon kartı tipi | 10 |
| 13A | `panorama-brifing-katalogu.json` | Ayarlanabilir anomali kuralı | 8 |
| 13A | `panorama-brifing-katalogu.json` | Boş gün cümlesi | 4 |
| 13B | `bildirim-olay-katalogu.json` | Bildirim olayı | 39 |
| 13C | `ayarlar-bilgi-mimarisi.md` | Ayar sekmesi | 16 |
| 13C | `ayarlar-bilgi-mimarisi.md` | Tanımlı ayar | 126 |
| 13C | `ayarlar-bilgi-mimarisi.md` | Açık mevcut-ayar taşıma satırı | 7 |
| 13D | `ekran-durum-metinleri.json` | Ekran | 8 |
| 13D | `ekran-durum-metinleri.json` | BOŞ/YÜKLENİYOR/HATA kaydı | 24 |

## 2. Bildirim olaylarının grup dağılımı

| Grup | Olay sayısı |
|---|---:|
| Kuyruk | 9 |
| Liste | 12 |
| Paylaşım | 7 |
| Sistem | 5 |
| Çeviri | 6 |
| **Toplam** | **39** |

Yüksek frekanslı yakalama, çevrimdışı kuyruk, retry, mükerrer bastırma, listeye ürün ekleme/çıkarma, firma satır yanıtı, başarısız paylaşım erişimi ve çeviri sonuçlarında birleştirme kuralı zorunlu tutuldu. `birlestirme.izinli=true` olan bütün olaylarda pozitif dakika penceresi, grup anahtarı ve `{n}` içeren toplu gövde bulunduğu otomatik kontrol edildi. Dead-letter, kur kilidi, durum değişimi, paylaşım anahtarı yenileme ve güvenlik sınırı gibi kritik/denetlenebilir işlemler tekil bırakıldı.

## 3. 5B/7B çakışmazlık kanıtı

Doğrulanan kaynaklar:

- Görev #5B `cikti-terimleri.json`: **185** anahtar; bunun **15** adedi bağlayıcı `status.*` durum anahtarıdır.
- Görev #7B `portal-metinleri.json`: **111** benzersiz `portal.*` anahtarıdır.
- 5B ↔ 7B mevcut kaynak çakışması: **0**.

Yeni kimlikler:

- 13A: `BRF-*`, `ANM-*`, `EMPTY-*` ve `action.*`
- 13B: `NTF-*`
- 13D: yalnız `ui.durum.*`

13D’deki 24 `ui.durum.*` anahtarı ile 5B+7B toplam 296 anahtarın küme kesişimi otomatik karşılaştırıldı: **0 çakışma**. 13A/13B kimlikleri de aynı birleşik kaynak kümesiyle karşılaştırıldı: **0 çakışma**.

Liste durumlarında yeni ad üretilmedi. Koşul, hedef ve metinlerde kullanılan `status.waiting_price`, `status.waiting_approval`, `status.ready`, `status.missing_data`, `status.sent`, `status.waiting_supplier` ve `status.expired` anahtarlarının tamamı 5B kaynağında bulundu. Ayarlar ekranında durum sözlüğü salt okunur ve kaynağı `cikti-terimleri.json:status.*` olarak tanımlandı.

## 4. Koşulların veri modelinden türetilebilirliği

13A koşulları yeni iş verisi istemez; mevcut kayıtların agregasyonudur:

| Koşul ailesi | Mevcut veri | Türetme |
|---|---|---|
| Liste durumu ve bekleme yaşı | Liste `status`, `status_changed_at` | Duruma göre sayım; bugünden en eski durum değişimine gün farkı |
| Fiyat geçerliliği | Teklif/listenin `valid_until` alanı | En yakın bitiş tarihi ve kalan gün |
| Eksik ürün | Liste satırı `missing`/zorunlu alan doğrulaması | Aktif satırlarda zorunlu eksik sayımı |
| Gelen Kutusu | Oturum, aktif kart ve seçilmiş hedef | Hedefi belirlenmemiş aktif kayıt sayısı |
| Kuyruk | `status`, `created_at`, `available_at`, `attempts`, `last_error` | ready/retry_wait/dead sayımı ve en eski hazır iş yaşı |
| Kur | Aktif kur sürümü ve liste kur snapshot'ı | Aktif kur yaşı; kilitli liste sayısı ve yüzde sapma |
| Yakalama sağlığı | Capture audit, parser sonucu/sürümü | Son başarı yaşı, 24 saatlik toplam ve başarı oranı |
| Çeviri | Translation jobs ve sağlayıcı kota snapshot'ı | Bekleyen/başarısız/toplam sayımı, hata oranı ve kalan kota yüzdesi |

Bu nedenle katalog koşulları Panorama için ayrı bir iş kayıt sistemi kurmaz; salt okunur günlük özet sorgusundan üretilebilir.

## 5. Ayarlar mimarisi doğrulaması

- Sekme sayısı: **16/16**, üst sınır aşılmadı.
- Toplam ayar: **126**; her ayarda `Ad`, `Tip`, `Varsayılan`, `Açıklama` mevcut.
- Mevcut kur, çeviri, hedef dil/sözlük, antet, token ve paylaşım numarası için yeni konum açıkça işaretlendi.
- Her sekmeye ortak arama, son değiştiren/zaman, değişiklik geçmişi, kaydedilmemiş değişiklik uyarısı ve kontrollü varsayılana dönüş meta katmanı tanımlandı.
- Secret alanlarda açık eski/yeni değer gösterilmemesi; snapshot kullanan liste/çıktı/paylaşım sürümlerinin ayar değişikliğiyle yerinde değişmemesi bağlandı.

## 6. Ekran × durum doğrulaması

- Ekranlar: Panorama, Gelen Kutusu, Keşif, Listeler, Liste Detay, Ürün çekmecesi, Ayarlar, Arşiv.
- Her ekran için tam olarak birer `BOŞ`, `YÜKLENİYOR`, `HATA` kaydı vardır: **8 × 3 = 24**.
- Bütün boş durumlar bir ilk adım veya yönlendirici eylem sunar.
- Bütün hata durumları verinin kaybolmadığını/korunduğunu insan dilinde anlatır ve eylem metni birebir **“Tekrar dene”** olur.
- Yükleniyor durumlarında sahte ilerleme yüzdesi veya kesin süre vaadi yoktur.

## 7. Otomatik ve elle yapılan son kontroller

| Kontrol | Sonuç |
|---|---|
| Üç JSON dosyası `jq` ile parse | GEÇTİ |
| 13A brifing/aksiyon/anomali/boş durum kimlikleri benzersiz | GEÇTİ |
| 13B 39 olay kodu benzersiz | GEÇTİ |
| 13B önem değerleri yalnız `bilgi/uyari/kritik` | GEÇTİ |
| 13B grupları yalnız `kuyruk/liste/paylasim/sistem/ceviri` | GEÇTİ |
| Gürültülü olaylarda birleştirme alanları ve `{n}` | GEÇTİ |
| Kullanılan bütün `status.*` anahtarları 5B'de mevcut | GEÇTİ |
| 13D anahtarları yalnız `ui.durum.*` | GEÇTİ |
| 13D anahtarları 5B/7B ile çakışmıyor | GEÇTİ — 0 |
| 8 ekranın her birinde üç durum eksiksiz | GEÇTİ |
| 16 ayar sekmesi ve her ayarda dört sözleşme alanı | GEÇTİ |
| “TedarikApp” bitişik yazım taraması | GEÇTİ — aykırı kullanım yok |

## 8. Teslim beyanı

Paket salt üretim kapsamında hazırlanmıştır; repo yazımı ve canlı istek yapılmamıştır. Panorama koşulları mevcut liste, kuyruk, kur, yakalama ve çeviri kayıtlarından türetilebilir; yeni durum sözlüğü oluşturulmamış, Görev #5B tek kaynak olarak korunmuştur.
