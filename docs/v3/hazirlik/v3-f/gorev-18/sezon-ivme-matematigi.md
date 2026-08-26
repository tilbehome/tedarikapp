# TedarikApp V3-F Sezon ve İvme Matematiği

**Sürüm:** `v3-f-season-momentum/1.0.0`  
**Amaç:** Geçmiş siparişlerden sezon hazırlık zamanı üretmek ve tekrarlanan fiyat/satış snapshot'larından operasyonel ivmeyi hesaplamak.  
**Kapsam dışı:** GTİP, gümrük, mevzuat, vergi/oran tespiti ve otomatik sipariş kararı.

**Kaynak dayanakları:** `skor-kalibrasyon-seti.json` ve C3 bağlamı (C3 ayrımı); `demo-urun-seti.json` (ürün/kategori örnek bağı); sipariş geçmişi alanları için 16B/16C/16D bağlamı. Kesin fiziksel tablo adları ve kesin C3 katsayıları kanıtlanmadı; bu belge bunları uydurmaz.

## 1. Veri sözleşmesi

Her hesap aşağıdaki kanıtları ister:

- `entity_id`, `platform`, `category_id`, `captured_at`, `source_snapshot_id`;
- fiyat için `amount`, `currency`, `price_basis`, `unit`, `pack_qty`, `variant_id`;
- satış için metriğin türü: `period_sales` veya `cumulative_sales`;
- sipariş sezonu için `ordered_at`, `category_id`, `effective_qty` ve isteğe bağlı `season_event_id`;
- tedarik süresi（采购提前期）için kullanıcı/ürün/firma kaynaklı `lead_time_days`; yoksa bu hazırlık paketinin iş varsayılanı **60 gün** açıkça etiketlenir.

Eksik alan `0` yapılmaz. Platform adaptörü desteklemediği sinyali üretmez. Aynı ürün/varyant/para/birim/paket kapısı geçmeden iki fiyat karşılaştırılmaz.

## 2. Sezon takvimi ve 60 günlük geri sayım

### 2.1 Sabit takvim ayı

Geçen yıl aynı ay sipariş edilen kategori miktarı:

\[
Q_{y-1,m,c} = \sum effective\_qty
\]

Yalnız `Q > 0` ve kayıtlar doğrulanmışsa şu tür cümle üretilebilir:

> Geçen yıl bu ay **{kategori}** kategorisinde **{Q} adet** sipariş verdin.

Sipariş hazırlık tarihi:

\[
planned\_order\_date = season\_anchor\_date - lead\_time\_days
\]

Kalan gün:

\[
days\_remaining = planned\_order\_date - today
\]

- `days_remaining > 0`: geri sayım gösterilir.
- `days_remaining = 0`: bugün karar günü.
- `days_remaining < 0`: gecikme mutlak günle gösterilir; sipariş otomatik verilmez.

### 2.2 Tarihi kayan sezon（移动节日）

Ramazan gibi tarihi kayan dönemler sabit ay numarasıyla kıyaslanmaz. Her yıl için kanıtlı `season_anchor_date` girilir ve kayıtlar olay gününe göre hizalanır:

\[
relative\_day = order\_date - season\_anchor\_date
\]

Geçen yıl `relative_day=-60` civarında alınan kategori, bu yılın yeni anchor tarihinden aynı göreli günle planlanır. Anchor tarihi yoksa **kanıtlanmadı**; tarih uydurulmaz.

## 3. Fiyat tarihçesi

Karşılaştırılabilir iki fiyat snapshot'ı için:

\[
price\_change = new - old
\]

\[
price\_change\_pct = \frac{new-old}{old} \times 100
\]

`old <= 0` ise yüzde tanımsızdır. Kademe, varyant, para birimi, satış birimi veya paket miktarı değiştiyse önce normalize edilmeden kıyas yapılmaz.

## 4. Satış tarihçesi ivmesi

### 4.1 Kümülatif sayaçtan hız

İki kümülatif snapshot için günlük satış hızı（销售速度）:

\[
v = \frac{sales_2-sales_1}{days(t_2-t_1)}
\]

Sayaç gerilerse sonuç negatif talep değildir; `COUNTER_RESET_OR_DATA_ERROR` olur.

### 4.2 İki pencere arasında operasyonel ivme

Önceki ve yakın pencere hızları:

\[
v_p = \frac{S_1-S_0}{d_1}, \qquad v_r = \frac{S_2-S_1}{d_2}
\]

\[
momentum\_pct = \frac{v_r-v_p}{|v_p|} \times 100
\]

- `v_p > 0`: yüzde hesaplanır.
- `v_p = 0` ve `v_r > 0`: yüzde sonsuz yazılmaz; `NEW_ACTIVITY` gösterilir.
- Tek veri noktası veya iki pencere yoksa ivme **gizli / kanıtlanmadı** kalır.
- Stokta yok（缺货）olduğu kanıtlanan günler talep yokluğu sayılmaz; hız penceresinden çıkarılır ve kapsama not edilir.

Gün başına hız değişimi istenirse pencere orta noktaları kullanılır:

\[
acceleration = \frac{v_r-v_p}{days(mid_r-mid_p)}
\]

Bu değer yalnız eğilim açıklamasıdır; otomatik sipariş veya ürün skoru değildir.

## 5. C3 skor ivmesi ile V3-F tarihçe ivmesinin ayrımı

| Boyut | C3 skor ivmesi | V3-F sezon/tarihçe ivmesi |
|---|---|---|
| Amaç | Ürün adayını platform × kategori içinde sıralayan skor bileşeni | Zaman içindeki gerçek değişimi ve hazırlık zamanını açıklamak |
| Veri şekli | Tek ürün snapshot'ındaki dönem satış, toplam satış ve ilan yaşı gibi C3 girdileri | Aynı varlığa ait zaman damgalı en az üç karşılaştırılabilir snapshot |
| Çıktı | Skor motorunun sürümlü bileşeni/bandı | Hız, yüzde ivme, fiyat değişimi, sezon geri sayımı |
| Karşılaştırma | Platformlar arası yapılmaz; C3 sözleşmesi belirler | Aynı ürün/varyant/birim veya aynı kategori/dönem içinde yapılır |
| Eksik veri | Skor `GİZLİ`; yüksek MOQ tek başına ceza değildir | Sonuç `kanıtlanmadı`; yüzde veya sıfır uydurulmaz |
| Yazma yetkisi | C3 sonucunu yalnız skor motoru yazar | V3-F yalnız tarihçe/rapor bulgusu yazar; C3 alanını değiştirmez |
| Birbirine besleme | Bu paket C3 formülünü veya ağırlığını değiştirmez | V3-F sonucu C3 ivmesi diye etiketlenmez |

Kesin C3 katsayıları bu pakette yeniden tanımlanmaz; C3'ün kendi kabul edilmiş sözleşmesi tek kaynaktır.

## 6. SZ test vektörleri

| ID | Girdi | Formül/işlem | Beklenen |
|---|---|---|---|
| SZ-001 | Anchor `2027-05-01`, lead `60` | anchor − 60 gün | Plan tarihi `2027-03-02` |
| SZ-002 | Anchor `2027-04-10`, lead `60` | anchor − 60 gün | Plan tarihi `2027-02-09` |
| SZ-003 | Bugün `2027-02-20`, plan `2027-03-02` | plan − bugün | `10 gün kaldı` |
| SZ-004 | Bugün `2027-03-05`, plan `2027-03-02` | plan − bugün | `3 gün gecikti`; otomatik sipariş yok |
| SZ-005 | Geçen yıl aynı ay kayıt yok | `Q` hesaplanamaz | Hatırlatma yok; `kanıtlanmadı` |
| SZ-006 | Tek satış snapshot'ı: 100 | İki pencere yok | İvme gizli / `kanıtlanmadı` |
| SZ-007 | Gün 0:100, gün 10:120 | `(120-100)/10` | `v = 2 adet/gün` |
| SZ-008 | 0→10 gün: 100→120; 10→20 gün: 120→160 | `v_p=2`, `v_r=4` | `momentum = %100,00` |
| SZ-009 | Önceki hız 0, yakın hız 3 | yüzde paydası 0 | `NEW_ACTIVITY`; yüzde yok |
| SZ-010 | Gün 10 sayaç 120, gün 20 sayaç 110 | sayaç geriledi | `COUNTER_RESET_OR_DATA_ERROR` |
| SZ-011 | Fiyat 20 CNY → 18 CNY | `(18-20)/20×100` | `-2 CNY`, `-%10,00` |
| SZ-012 | Fiyat 0 → 18 | eski fiyat `<=0` | Yüzde `tanımsız` |
| SZ-013 | Önceki sezon anchor `2026-04-01`, sipariş `2026-01-31` | relative day | `-60`; yeni yıl yeni anchor'a taşınır |
| SZ-014 | Yeni sezon anchor yok | olay hizalama | Tarih `kanıtlanmadı`; sabit ay uydurulmaz |

## 7. Kenar durumları ve kabul kapısı

1. İlk yıl verisi yoksa “geçen yıl bu ay” cümlesi üretilmez.
2. Tek veri noktasıyla ivme hesaplanmaz.
3. Farklı varyant, para, birim veya paket fiyatı sessizce kıyaslanmaz.
4. Sayaç gerilemesi satış düşüşü sayılmaz.
5. Sezon kaymasında kanıtlı yıllık anchor olmadan tarih üretilmez.
6. Stokta yok günleri kanıtlıysa talep sıfırı sayılmaz; kapsam dışı gün sayısı raporda gösterilir.
7. 60 gün bu paketin açık iş varsayılanıdır; kanıtlı ürün/firma lead time değeri varsa o kullanılır.
8. V3-F hiçbir C3 skor alanını veya ağırlığını değiştirmez.
9. SZ-001..SZ-014 sonuçları tam geçmeden uygulama kabul edilmez.
