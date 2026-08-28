# SKU ve etiket şablonları

**Sürüm:** V3-J / 1.0  
**Kapsam:** TedarikApp iç SKU yapısı, ürün etiketi, koli etiketi ve pasif ürünlerin etiket/arşiv davranışı  
**Kaynak bağı:** Kategori kodları 8B `kategori-agaci.json`; örnek ürünler `demo-urun-seti.json` DM-001 ve DM-003

> **Önemli sınır:** Bu dosyadaki alanlar ticari mevzuat yorumu veya bir ürünün zorunlu etiket mevzuatı listesi değildir. Operasyonel/pratik ihtiyaç şablonudur. Ürün türüne, satış kanalına ve yürürlükteki kurallara göre mevzuat teyidi Ürün Sahibi’ndedir.

## 1. SKU tasarım ilkeleri

1. SKU, TedarikApp’ın kendi kararlı kimliğidir; tedarikçi SKU’su, pazar yeri ilan id’si, EAN/GTIN veya barkodun kendisi değildir.
2. Yalnız ASCII büyük harf (`A–Z`), rakam (`0–9`) ve tire (`-`) kullanılır. Türkçe karakter, boşluk, eğik çizgi ve nokta kullanılmaz.
3. Global sıra numarası tekrar kullanılamaz. Silinen/arşivlenen ürünün sırası yeni ürüne verilmez.
4. Ürün SKU’su atandıktan sonra değişmez. Kategori sonradan değişse bile ilk SKU korunur; güncel kategori ayrı alanda tutulur.
5. Varyant SKU’su ana SKU’dan türetilir ve mağazada benzersizdir.
6. Tedarikçinin kendi kodu `supplier_sku` gibi ayrı alanda tutulur; etiket ana kimliği yapılmaz.
7. Bir varyantın ticari adı değişse bile varyant eki yeniden kullanılmaz; eşleme geçmişi korunur.
8. Basılmadan önce benzersizlik, karakter seti, üst SKU ilişkisi ve arşiv durumu doğrulanır.
9. Pratik üst sınır olarak 32 karakter hedeflenir; bağlı sistemin daha dar sınırı varsa o sınır uygulanır.
10. Barkod numarası atanmışsa SKU ile bağlantılanır; SKU’ya bakarak sahte EAN/GTIN üretilmez.

## 2. Kategori kısa kodu sözlüğü

Alternatif A için 8B kodundan türetilen kısa kodlar ayrı ve sürümlü bir sözlükte tutulur. Başlangıç önerileri:

| 8B kategori kodu | SKU kısa kodu | Açıklama |
|---|---|---|
| `ev_tekstili` | `EVT` | Ev tekstili üst kategorisi |
| `ev_tekstili.havlu` | `EVT-HVL` | Havlu |
| `ev_tekstili.yatak_tekstili` | `EVT-YTK` | Yatak tekstili |
| `ev_tekstili.perde` | `EVT-PRD` | Perde |
| `ev_tekstili.koltuk_kilifi` | `EVT-KLT` | Koltuk kılıfı |
| `ev_tekstili.kirlent` | `EVT-KRL` | Kırlent |
| `ev_tekstili.masa_tekstili` | `EVT-MSA` | Masa tekstili |
| `mutfak` | `MTF` | Mutfak |
| `banyo` | `BNY` | Banyo |
| `zuccaciye` | `ZCC` | Züccaciye |
| `saklama_duzenleme` | `SKD` | Saklama ve düzenleme |
| `dekorasyon` | `DKR` | Dekorasyon |
| `kucuk_ev_aleti_aksesuari` | `KEA` | Küçük ev aleti aksesuarı |
| `temizlik_bakim` | `TMB` | Temizlik ve bakım |
| `sofra_sunum` | `SFS` | Sofra ve sunum |
| `diger` | `DGR` | Diğer; ürün sonradan sınıflandırılmalıdır |

Sözlükte aynı kısa kod iki kategoriye atanamaz. Yeni 8B kategorisi geldiğinde kod Ürün Sahibi tarafından onaylanmadan SKU üretilmez.

## 3. İki SKU düzeni alternatifi

### Alternatif A — Okunabilir kategori kodlu düzen

```text
Ana ürün: {KATEGORİ-KISA-KODU}-{6 HANELİ GLOBAL SIRA}
Varyant:  {ANA-SKU}-{VARYANT EKİ}
```

Örnekler:

- DM-001 ana: `EVT-HVL-000001`
- DM-001 Krem beyaz: `EVT-HVL-000001-KRM`
- DM-001 Duman mavisi: `EVT-HVL-000001-DMV`
- DM-001 Gri-yeşil: `EVT-HVL-000001-GRY`
- DM-003 basit ürün: `EVT-YTK-000003`

**Artıları**

- Etiket ve depoda ürün ailesi gözle daha hızlı anlaşılır.
- 8B kategori ağacının operasyonel kullanımını görünür kılar.
- İnsan tarafından okunabilen hata kontrolü kolaydır.

**Eksileri**

- Kategori yanlış seçilmişse eski kod SKU’da kalır; SKU’nun değişmezliği nedeniyle görüntü ile güncel kategori ayrışabilir.
- Kısa kod sözlüğünün yönetilmesi ve çakışmaların engellenmesi gerekir.
- Çok uzun kategori kodları için kontrollü kısaltma gerekir.

### Alternatif B — Kategoriden bağımsız opak düzen

```text
Ana ürün: TH-{7 HANELİ GLOBAL SIRA}
Varyant:  {ANA-SKU}-V{2 HANELİ SIRA}
```

Örnekler:

- DM-001 ana: `TH-0000001`
- DM-001 ilk varyant: `TH-0000001-V01`
- DM-001 ikinci varyant: `TH-0000001-V02`
- DM-003 basit ürün: `TH-0000003`

**Artıları**

- Kategori değişikliği SKU anlamını bozmaz.
- Üretim ve doğrulama kuralı daha basittir.
- Yeni kategori eklenmesi SKU sözlüğünü bekletmez.

**Eksileri**

- Etikete bakarak kategori veya varyant anlamı anlaşılmaz.
- Depo ve müşteri hizmetleri ekranında ürün adını ayrıca görmek gerekir.
- Varyant sırası renk/ölçü bilgisini taşımadığı için eşleme tablosu kritik olur.

**Seçim yapılmamıştır.** PM ve Ürün Sahibi iki seçenekten birini onaylar. 21A örnek çıktısı okunabilirliği göstermek için Alternatif A’yı kullanır; bu kullanım nihai karar sayılmaz.

## 4. Varyant eki kuralları

Alternatif A seçilirse varyant eki her eksen için kararlı kodlardan oluşur:

| Varyant örneği | Önerilen ek | Not |
|---|---|---|
| Krem beyaz | `KRM` | Renk sözlüğü kaydı |
| Duman mavisi | `DMV` | Renk sözlüğü kaydı |
| Gri-yeşil | `GRY` | Renk sözlüğü kaydı |
| 150×200 cm | `150X200` | `×` yerine ASCII `X` |
| Büyük / L | `L` | Onaylı beden kodu |
| Renk + ölçü | `KRM-150X200` | Eksen sırası sözlükte sabit |

Aynı TR adın iki farklı kaynak seçeneğini yanlışlıkla birleştirmemek için her ek, kaynak varyant ref’iyle eşlenir. Kod çakışırsa rastgele rakam eklenmez; varyant sözlüğünde yeni kod onaylanır.

## 5. Ürün etiketi şablonu

### 5.1 Baskı yerleşimi

```text
┌──────────────────────────────────────────────┐
│ [MARKA / LOGO — onaylıysa]                  │
│                                              │
│ Ürün: [ÜRÜN ADI TR]                          │
│ Varyant: [RENK / ÖLÇÜ / MODEL]               │
│ SKU: [EVT-HVL-000001-KRM]                    │
│                                              │
│ Barkod: [                    ]                │
│         [EAN/GTIN veya “İç kullanım” türü]   │
│                                              │
│ Made in PRC                                  │
│ İthalatçı: [TİCARİ UNVAN]                    │
│ [İTHALATÇI ADRESİ / ONAYLI İLETİŞİM SATIRI]  │
│                                              │
│ Parti No: [LOT-YYYY-NNN]                     │
│ [Varsa ürün türüne özel onaylı bilgi alanı]  │
└──────────────────────────────────────────────┘
```

### 5.2 Pratik alan listesi

| Alan | Davranış | Kontrol |
|---|---|---|
| Ürün adı TR | TedarikApp onaylı kısa ad | Yazım ve varyant uyumu |
| SKU | Seçilen 21C düzeni | Benzersiz ve değişmez |
| Varyant | Renk/ölçü/model | Fiziksel ürünle eşleşme |
| Barkod alanı | Atanmış EAN/GTIN veya açıkça “İç kullanım” Code 128 | Geçerli numara ve tarama testi |
| Menşe satırı | Kullanıcı isteğine göre `Made in PRC` | Ürünün gerçek menşei ve kullanım uygunluğu Ürün Sahibi teyidi |
| İthalatçı satırı | Onaylı ticari unvan ve gerekli iletişim/adres | Yer tutucu üretime gönderilmez |
| Parti no | Üretim/tedarik lotu | Koli ve kabul kaydıyla aynı |
| Ürüne özel bilgiler | Malzeme, bakım, ölçü, uyarı vb. yalnız onaylı profil varsa | Ürün tipi ve mevzuat teyidi |

Bu liste **zorunlu ticari mevzuat alanları listesi değildir**. Tekstil, elektrikli ürün, gıda temaslı ürün, çocuk ürünü veya başka bir sınıf için ek/başka bilgiler gerekebilir. Mevzuat teyidi ve nihai artwork onayı Ürün Sahibi’ndedir.

### 5.3 Barkod güvenlik notu

- EAN/GTIN tahsis edilmediyse sayı uydurulmaz.
- İç depo için SKU’dan Code 128 üretilebilir; etiketin altında açıkça `İç kullanım barkodu` yazılır.
- Aynı SKU’ya iki aktif barkod bağlanmaz; eski barkod arşivde tutulur.
- Baskı örneği en az iki farklı cihazla okutulmadan seri baskıya geçilmez.

## 6. Koli etiketi şablonu

```text
┌──────────────────────────────────────────────────────┐
│ KOLİ ETİKETİ / CARTON MARK                           │
│                                                      │
│ Ürün: [ÜRÜN ADI TR]                                  │
│ Ana SKU: [ANA SKU]                                   │
│ Varyant SKU: [VARSA]                                 │
│ Varyant: [RENK / ÖLÇÜ / MODEL]                       │
│                                                      │
│ Koli içi adet / QTY（装箱数）: [   ] adet             │
│ Net ağırlık / N.W.（净重）: [   ] kg                  │
│ Brüt ağırlık / G.W.（毛重）: [   ] kg                 │
│ Koli ölçüsü（外箱尺寸）: [U] × [G] × [Y] cm           │
│ CBM（立方米）: [0.000] m³                             │
│                                                      │
│ Parti No / LOT（批次号）: [LOT-YYYY-NNN]              │
│ PO / Sipariş No（订单号）: [PO-YYYY-NNN]              │
│ Koli No（箱号）: [001] / [TOPLAM KOLİ]                │
│                                                      │
│ Barkod / QR: [SKU veya koli kimliği — tür belirtilir]│
│ Made in PRC                                          │
│ İthalatçı: [ONAYLI TİCARİ UNVAN]                     │
│ Elleçleme işaretleri: [Onaylı semboller]             │
└──────────────────────────────────────────────────────┘
```

### 6.1 Hesap ve tutarlılık kontrolleri

- `CBM = (U cm × G cm × Y cm) / 1.000.000` formülüyle hesaplanır; etikette en az üç ondalık gösterilir, ham değer kayıtta saklanır.
- Brüt ağırlık net ağırlıktan küçük olamaz.
- Koli içi adet × koli sayısı, sevk edilen toplam adede eşit olmalıdır; karışık koli varsa içerik listesi ayrıca eklenir.
- Parti no ürün etiketi, koli etiketi, numune/onay kaydı ve kabul raporunda aynı olmalıdır.
- `demo-urun-seti.json` içindeki paket değerleri teklif/ön bilgi olabilir; fiziksel koli ölçümü yapılmadan baskı değeri sayılmaz.
- Yer tutucu kalan hiçbir alan üretime gönderilmez.

## 7. “Artık almıyoruz” ve “uykuda” davranışı

Bu iki ifade 5B’nin kanonik durum adları değildir; yeni durum olarak eklenmez. Kullanıcı arayüzünde iş niyeti/işaret olarak gösterilir, kayıt aşağıdaki mevcut 5B durumlarıyla tutulur:

| İş işareti | 5B kanonik durum | Ek alan önerisi | Etiket davranışı | Arşiv davranışı |
|---|---|---|---|---|
| Artık almıyoruz | `status.archived` — **Arşivlendi** | `archive_reason: no_longer_sourcing`, `archived_at`, `archived_by`, serbest not | Yeni ürün/koli etiketi üretimi kapatılır; geçmiş PDF/artwork silinmez | SKU, barkod, tedarikçi eşlemeleri, sipariş ve etiket sürümleri salt okunur saklanır; sıra tekrar kullanılmaz |
| Uykuda | `status.expired` — **Güncelliğini yitirdi** | `review_after`, `reactivation_note`, `sleep_reason` | Varsayılan olarak yeni baskı kapalı; yetkili kullanıcı gözden geçirip etkin akışa almadan çıktı verilmez | Kayıt arşive gömülmez; inceleme tarihinde görev açılır, fiyat/tedarikçi/etiket güncelliği yeniden doğrulanır |

### 7.1 Yeniden etkinleştirme

1. Yetkili kullanıcı “uykuda” işaretli kaydı açar.
2. 5B’deki uygun iş akışı durumuna geçirir; durum seçimi mevcut sözlükten yapılır. Örneğin veri eksikse `status.missing_data`, tedarikçi yanıtı bekleniyorsa `status.waiting_supplier`, onay gerekiyorsa `status.waiting_approval` kullanılabilir.
3. Ürün adı, kategori, tedarikçi, barkod, menşe/ithalatçı artwork’ü ve ürün türüne özel bilgiler yeniden doğrulanır.
4. SKU değiştirilmez. Yeni varyant gerekiyorsa yeni varyant SKU’su üretilir.
5. Yeni etiket eski dosyanın üzerine yazılmaz; artwork sürümü artırılır ve eski sürüm “kullanım dışı” olarak saklanır.

“Artık almıyoruz” kaydını geri almak istisnai bir işlemdir: Ürün Sahibi nedenini kaydeder, mevcut tedarik/etiket bilgilerini baştan doğrular ve 5B’den uygun aktif durum anahtarını seçer. Eski SKU tekrar kullanılabilir; başka ürüne tahsis edilemez.

## 8. Baskı öncesi kontrol listesi

- [ ] SKU seçilen alternatifin söz dizimine uygun ve benzersiz.
- [ ] Ürün, varyant, SKU ve barkod birbirine bağlı.
- [ ] Ürün adı TR ve fiziksel ürünle uyumlu.
- [ ] `Made in PRC` satırı gerçek menşe ve kullanım açısından Ürün Sahibi tarafından teyit edilmiş.
- [ ] İthalatçı satırındaki ticari unvan/adres yer tutucu değil, onaylı bilgi.
- [ ] Ürün türüne özel zorunlu/uygunluk alanları Ürün Sahibi tarafından ayrıca teyit edilmiş.
- [ ] Barkod türü yazılmış ve tarama testi geçmiş.
- [ ] Koli içi adet, net/brüt ağırlık, ölçü ve CBM fiziksel veriyle doğrulanmış.
- [ ] Parti ve PO numarası tüm kayıtlarda tutarlı.
- [ ] Kayıt `status.archived` veya `status.expired` ise yetkisiz yeni etiket üretimi engellenmiş.
- [ ] Artwork sürümü, onaylayan kişi ve onay tarihi kaydedilmiş.
