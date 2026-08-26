# GÖREV #18 — V3-F Hazırlık Paketi Teslim Raporu

**Paket:** Zeka + Zaman + Raporlar  
**Teslim tarihi:** 26 Ağustos 2026  
**Çalışma sınırı:** Salt üretim; TedarikApp reposuna yazım ve canlı platform isteği yok.  
**Teslim:** 4 çalışma dosyası + bu rapor

## 1. Dosyalar ve sayımlar

| Dosya | İçerik | Sayım |
|---|---|---|
| `yorum-analizi-altin-seti.json` | Çince yorum analizi | 30 vaka; 10 tuzaklı; 10 etiketli kapalı küme |
| `rapor-katalogu.md` | Sekiz rapor ailesi | 8 aile; 20 RP test vektörü |
| `izleme-esikleri.json` | Ürün, mağaza ve kategori izleme | 14 eşik; 20 İZ test vektörü |
| `sezon-ivme-matematigi.md` | Sezon takvimi, 60 günlük geri sayım ve tarihçe ivmesi | 14 SZ test vektörü; C3 ayrım tablosu |
| `TESLIM-RAPORU.md` | Sayım, doğrulama ve açık kararlar | Bu rapor |

Çalışma dosyaları toplamı: **2850 satır**, yaklaşık **9011 kelime**, **98625 bayt**.

## 2. Kabul kapısı karşılığı

### 18A

- `YRM-001..YRM-030` kesintisiz ve benzersizdir.
- Etiketler yalnız `kalite/ölçü/renk/paket/kargo/koku/dayanıklılık/hizmet/olumlu/belirsiz` kümesindedir.
- İroni, karışık dil, spam/teşvik, satıcı cevabı, ölçü–renk ayrımı, yanlış ürün, platform yer tutucusu, puan–metin çelişkisi ve varyant karışması kapsanmıştır.
- Kabul eşiği etiket doğruluğu en az `%90`; yanlış ürüne atama `%0`dır.
- Yorum analizi yalnız sipariş öncesi tek kalite sinyalidir; numune/AQL süreci eklenmemiştir.

### 18B

- İstenen sekiz rapor ailesinin her birinde amaç, mantıksal kaynaklar, boyut/ölçüler, örnek satır, Excel/PDF kuralı ve zamanlanmış üretim seçeneği vardır.
- `RP-001..RP-020` PM tarafından uygulamadan bağımsız hesaplanabilir.
- Fiziksel tablo adları varmış gibi sunulmamış; eşleme gerçekleşene kadar mantıksal kaynak olarak işaretlenmiştir.

### 18C

- Eşikler platform adı içermez; yetenek ve kanıt kapılarıyla çalışır.
- Mağaza yeni ürünü yalnız `Keşif` adayına yönlendirir; otomatik sipariş oluşturmaz.
- Yüksek MOQ tek başına ceza değildir; yalnız değişim veya kullanıcı tavanı kesişimi bilgi/bulgu üretir.
- Bu paket yeni NTF olayı açmamıştır. Birebir 13B olayı kanıtlanmayan bütün dış bildirim eşlemeleri `PM_KARARI` bırakılmıştır.
- `İZ-001..İZ-020` eşik sınırı, karşılaştırılabilirlik, minimum örnek ve eksik kullanıcı tavanını kapsar.

### 18D

- 60 günlük varsayılan tedarik süresi geri sayımı, kullanıcı/ürün/firma lead time değeriyle ezilebilir.
- Tarihi kayan sezonlar sabit ayla değil olay-relative günle hizalanır.
- Tek nokta, sıfır payda, sayaç gerilemesi, stokta yok günleri ve sezon anchor eksikliği açık davranışa sahiptir.
- C3 skor ivmesi ile V3-F zaman serisi ivmesi ayrı amaç, veri, çıktı ve yazma yetkileriyle tabloda ayrılmıştır.
- `SZ-001..SZ-014` deterministik beklenen sonuç taşır.

## 3. Doğrulama beyanı

- JSON parse: **başarılı** (`jq empty` iki JSON dosyasında da çıkış kodu `0`).
- Demo ürün bağı: **30/30 geçerli DM referansı**.
- Yorum kanıt parçaları ham ZH metinde mevcut: **başarılı**.
- Kapalı etiket kümesi dışına çıkış: **0**.
- Yanlış `status.*` anahtarı: **0**; doğrulanan 5B kümesi **15** anahtar.
- Yeni NTF olay kodu: **0**.
- GTİP/gümrük/mevzuat/vergi hesap kuralı: **0**.
- Repo yazımı: **yok**.

## 4. Kaynaklar

| Kaynak | Kullanım | Kanıt durumu |
|---|---|---|
| `cikti-terimleri.json` v1.0 | 185 terim ve 15 `status.*` tek kaynağı | Doğrulandı |
| `demo-urun-seti.json` şema v2 | DM-001..DM-100 ürün bağları | Doğrulandı |
| `bildirim-olay-katalogu.json` | Olay/birleştirme sözleşmesi | İç kaynakta 39 olay gözlendi; görev şartnamesi 37 diyor, kanonik sayı PM kararı |
| `odeme-senaryolari.json` | Kilitli kur / ödeme günü kur politikası | Doğrulanan kaynak içeriği |
| `cbm-matematik.json` | Etkin adet ve koli katı bağı | Doğrulanan kaynak içeriği |
| `landed-cost-kalibrasyon.json` | Kaynak/teklif/gerçekleşen maliyet ayrımı | Doğrulanan kaynak içeriği |
| `ithalat-avantaji-kalibrasyon.json` | NET kıyas, avantaj ve dönem raporu matematiği | Doğrulanan kaynak içeriği |
| `skor-kalibrasyon-seti.json` ve C3 bağlamı | Skor ivmesi ayrımı; yüksek MOQ ceza değildir | Kesin C3 katsayıları bu pakette yeniden tanımlanmadı |

## 5. Açık kararlar

1. **13B olay sayısı:** Görev şartnamesi `37`, erişilen kabul kaynağı `39` olay göstermektedir. Katalog değiştirilmedi; kanonik sürüm/sayı PM tarafından seçilmelidir.
2. **V3-F bildirim olayları:** Ürün fiyat/stok/MOQ, mağaza yeni ürün ve kategori hareketi için birebir mevcut olay kanıtlanmadı. Mevcut olayı yanlış amaçla kullanmak yerine eşlemeler `PM_KARARI` kaldı.
3. **Fiziksel rapor tabloları:** Katalog mantıksal kaynak adları kullanır; uygulama sırasında gerçek şemaya eşleme ve indeks kararı gerekir.
4. **C3 kesin formülü:** V3-F matematiği C3'ten ayrılmıştır; C3'ün kesin katsayı/formül sürümü kendi kabul edilmiş kaynağından bağlanmalıdır.
5. **60 gün varsayılanı:** Şartnamedeki iş varsayılanıdır; ürün/firma bazlı kanıtlı lead time varsa onu ezmelidir.

## 6. Dosya bütünlükleri

| Dosya | SHA-256 |
|---|---|
| `yorum-analizi-altin-seti.json` | `5c1f2c79b92bb42e0d22b6420081d546797f3bee7809325adecebfda62f2d4b4` |
| `rapor-katalogu.md` | `a62e3ac2d6edfe441580fb4178dff4ab42b98464345da786ae1ba08eac38b84c` |
| `izleme-esikleri.json` | `5f5401c6207d83110efa4b7697fe4aaa05a52145692a5a582ca76924eb79b62d` |
| `sezon-ivme-matematigi.md` | `1b425f5afc247d0c9625dc982001f1029bbf7dfd49169e0873a2857e8350ac04` |
