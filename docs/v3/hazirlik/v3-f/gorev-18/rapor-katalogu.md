# TedarikApp V3-F Rapor Kataloğu

**Sürüm:** `v3-f-report-catalog/1.0.0`  
**Dil:** Türkçe ana dil; kritik terimlerde Çince karşılık parantez içindedir.  
**Kapsam:** Zeka + Zaman + Raporlar hazırlığı; platform bağımsız mantıksal rapor sözleşmesi.  
**Kapsam dışı:** GTİP, gümrük, mevzuat ve vergi/oran tespiti.

## Ortak hesap ve kanıt kuralları

1. Her rapor `generated_at`, `period_start`, `period_end`, `timezone=Europe/Istanbul`, filtreler ve kaynak snapshot/sürüm kimliklerini taşır.
2. Oranlarda payda `0` veya kanıtlanmamışsa sonuç `0` değil **tanımsız / kanıtlanmadı** olur.
3. Para kıyası aynı para birimi ve aynı NET/BRÜT temelinde yapılır; dönüşümde kur snapshot'ı（汇率快照）zorunludur.
4. Kaynak ilan, firma DDP teklifi ve gerçekleşen landed cost ayrı katmanlardır; biri diğerinin üzerine yazılmaz.
5. Ürün, varyant, satış birimi, paket adedi ve dönem karşılaştırılabilir değilse yüzdelik kıyas üretilmez.
6. `status.*` gerektiğinde yalnız 5B `cikti-terimleri.json` içindeki 15 anahtardan seçilir; rapor kataloğu yeni durum üretmez.
7. Programlı üretim immutable rapor snapshot'ı oluşturur; aynı dönem yeniden üretim yeni revision alır.
8. Bütün fiziksel tablo adları uygulama aşamasında doğrulanır; aşağıdaki adlar mantıksal veri kümeleridir.

## İç kaynak dayanakları

- `odeme-senaryolari.json`: kilitli kur / ödeme günü kuru ayrımı.
- `cbm-matematik.json`: etkin adet ve koli katı bağı.
- `landed-cost-kalibrasyon.json`: teklif/gerçekleşen landed cost katmanları.
- `ithalat-avantaji-kalibrasyon.json`: NET karşılaştırma, avantaj ve dönem kıyası.
- `cikti-terimleri.json`: yalnız kabul edilmiş 15 `status.*` anahtarı.

Bu kaynakların fiziksel uygulama tablosuna eşlemesi kanıtlanmadı; katalog bu nedenle mantıksal görünüm adları kullanır.

## RPT-01 — Huni ve dönüşüm raporu

**Amaç:** Yakalanan üründen onaylı sipariş satırına kadar kayıp ve dönüşümü gösterir.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `capture_snapshots` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `products` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `lists/list_items` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `supplier_rounds` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `supplier_responses` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `orders/order_items` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `dönem`, `platform`, `kategori`, `liste`, `firma`.

| Ölçü | Tanım |
|---|---|
| `yakalanan` | Dönemde oluşan benzersiz kanonik ürün yakalama sayısı. |
| `listeye_eklenen` | Bu yakalamalardan en az bir liste satırına dönüşen benzersiz ürün sayısı. |
| `firmaya_gönderilen` | Firmaya gönderilmiş benzersiz liste satırı sayısı. |
| `yanıtlanan` | Geçerli firma yanıtı bulunan benzersiz gönderilmiş satır sayısı. |
| `onaylanan` | Ürün Sahibi tarafından onaylanan benzersiz satır sayısı. |
| `sipariş_edilen` | Onaylı sipariş satırına dönüşen benzersiz satır sayısı. |
| `adım_dönüşüm_yüzdeleri` | Her adım / bir önceki uygun adım × 100; uçtan uca sipariş_edilen / yakalanan × 100. |

**Örnek satır:** 2026-08 · Mutfak · 200 yakalama → 100 liste → 60 gönderim → 45 yanıt → 12 sipariş; uçtan uca %6,00.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Haftalık veya aylık; kapanmış dönem immutable snapshot. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-02 — Kategori dağılımı raporu

**Amaç:** Keşif, liste, teklif ve sipariş portföyünün kategori paylarını karşılaştırır.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `products` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `category_mapping` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `lists/list_items` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `orders/order_items` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `dönem`, `kategori_yolu`, `platform`, `iş_aşaması`.

| Ölçü | Tanım |
|---|---|
| `benzersiz_ürün` | Seçili iş aşamasındaki tekilleştirilmiş ürün sayısı. |
| `satır_sayısı` | Seçili iş aşamasındaki satır sayısı; benzersiz ürün sayısıyla karıştırılmaz. |
| `adet` | Kanıtlı satış birimine göre etkin adet toplamı. |
| `tutar` | Aynı para birimi ve NET/BRÜT temelinde karşılaştırılabilir tutar toplamı. |
| `kategori_payı` | Kategori değeri / aynı ölçünün tüm kategori toplamı × 100; toplam 0 ise tanımsız. |

**Örnek satır:** Mutfak Gereçleri · 40/100 benzersiz aday · %40,00 pay.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Aylık; kategori eşleme sürümü rapora gömülür. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-03 — Firma performansı raporu

**Amaç:** Firma cevap süresi, bulunma oranı ve revizyon yükünü kör kıyas sınırları içinde Ürün Sahibine gösterir.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `supplier_rounds` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `supplier_responses` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `list_revisions` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `suppliers` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `firma`, `dönem`, `kategori`, `tur_no`.

| Ölçü | Tanım |
|---|---|
| `medyan_cevap_saati` | Gönderim ile ilk geçerli yanıt arasındaki saatlerin medyanı. |
| `bulundu_oranı` | Nihai sonucu bulundu olan satır / yanıtlanabilir nihai satır × 100. |
| `bulunamadı_oranı` | Nihai sonucu bulunamadı olan satır / yanıtlanabilir nihai satır × 100. |
| `alternatif_oranı` | Nihai sonucu alternatif olan satır / yanıtlanabilir nihai satır × 100. |
| `revizyon_oranı` | Revizyon gerektiren tamamlanmış tur / tamamlanmış tur × 100. |
| `nihai_yanıt_oranı` | Nihai yanıtı olan satır / firmaya gönderilen satır × 100. |

**Örnek satır:** Firma A · 10 satırın 7'si bulundu · bulunma %70,00 · 2 revizyon/5 tur = %40,00.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Aylık; dış firma çıktısına başka firma kıyası girmez. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-04 — İthalat avantajı özeti

**Amaç:** Yurtiçi net birim fiyat ile ithalat net birim maliyetini aynı ürün/varyant/birim temelinde karşılaştırır.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `domestic_price_book` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `landed_cost_runs` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `fx_snapshots` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `import_advantage_decisions` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `order_quantities` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `ürün`, `varyant`, `dönem`, `senaryo`, `karar`.

| Ölçü | Tanım |
|---|---|
| `yurtiçi_net_birim` | Aynı ürün/varyant/birim için yurtiçi NET birim fiyat. |
| `ithalat_net_birim` | Aynı ürün/varyant/birim için seçili landed-cost snapshot'ındaki NET birim maliyet. |
| `avantaj_tl_birim` | yurtiçi_net_birim − ithalat_net_birim. |
| `avantaj_yüzde` | avantaj_tl_birim / yurtiçi_net_birim × 100; yurtiçi değer 0 ise tanımsız. |
| `etkin_adet` | MOQ, satış birimi ve koli katı uygulandıktan sonra fiili hesap adedi. |
| `toplam_avantaj` | avantaj_tl_birim × etkin_adet. |
| `başabaş_adedi` | Ek sabit ithalat maliyeti / pozitif avantaj_tl_birim; yukarı yuvarlanır. |

**Örnek satır:** Yurtiçi 150 TL net, ithalat 100 TL net, 100 adet → 50 TL/adet, %33,33 ve 5.000 TL toplam avantaj.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Karar anında ve aylık özet; 17D snapshotları değiştirilmez. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-05 — Kur etkisi raporu

**Amaç:** Kilitli kur（锁定汇率）ile ödeme günü kuru arasındaki farkın TL karşılığını gösterir; kilitli snapshotı değiştirmez.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `fx_snapshots` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `payment_plan_lines` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `payments` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `orders` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `sipariş`, `ödeme_satırı`, `para_birimi`, `vade_olayı`, `kur_politikası`.

| Ölçü | Tanım |
|---|---|
| `yabancı_tutar` | İlgili ödeme satırının kaynak para birimindeki tutarı. |
| `kilitli_kur` | Sipariş/ödeme planında immutable olarak kilitlenen kur snapshot değeri. |
| `ödeme_kuru` | Gerçek ödeme gününe ve kabul edilmiş kur politikasına ait snapshot değeri. |
| `kilitli_tl` | yabancı_tutar × kilitli_kur. |
| `ödeme_tl` | yabancı_tutar × ödeme_kuru. |
| `kur_farkı_tl` | ödeme_tl − kilitli_tl; pozitif değer TL maliyet artışıdır. |
| `kur_farkı_yüzde` | kur_farkı_tl / kilitli_tl × 100; kilitli_tl 0 ise tanımsız. |

**Örnek satır:** 1.000 USD · kilitli 30,00 · ödeme 33,00 → 30.000 TL / 33.000 TL; +3.000 TL, %10,00 olumsuz fark.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Ödeme işlendiğinde ve aylık; kur kaynağı/sürümü zorunlu. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-06 — Sevkiyat ve ETA sapması raporu

**Amaç:** Planlanan ve gerçekleşen kilometre taşlarını karşılaştırarak gecikmeyi gün cinsinden açıklar.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `shipments` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `shipment_milestones` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `orders` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `supplier_rounds` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `sipariş`, `sevkiyat`, `firma`, `taşıma_türü`, `kilometre_taşı`.

| Ölçü | Tanım |
|---|---|
| `planlanan_tarih` | Kilometre taşının kanıtlı plan/ETA tarihi. |
| `gerçekleşen_tarih` | Kilometre taşının kanıtlı gerçekleşme tarihi. |
| `sapma_gün` | gerçekleşen_tarih − planlanan_tarih; pozitif gecikme, negatif erken. |
| `geciken_sevkiyat` | sapma_gün > 0 olan benzersiz sevkiyat sayısı. |
| `zamanında_oranı` | sapma_gün <= 0 olan sevkiyat / iki tarihi de bulunan sevkiyat × 100. |

**Örnek satır:** Planlanan 1 Eylül, gerçekleşen 6 Eylül → +5 gün gecikme.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Günlük operasyon özeti ve sevkiyat kapanış snapshotı. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-07 — Maliyet sapması raporu

**Amaç:** Kaynak ilan, firma DDP teklifi ve gerçekleşen landed cost katmanlarını birbirine yazmadan karşılaştırır.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `source_offer_snapshots` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `supplier_offer_snapshots` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `landed_cost_runs` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `landed_cost_allocations` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |
| `receipts` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `ürün`, `varyant`, `sipariş`, `masraf_türü`, `maliyet_aşaması`.

| Ölçü | Tanım |
|---|---|
| `kaynak_birim` | Kaynak ilanın normalize edilmiş birim fiyat snapshot'ı. |
| `teklif_ddp_net_birim` | Firma DDP teklifinin NET birim maliyeti. |
| `gerçekleşen_net_birim` | Kapanmış landed-cost çalışmasının gerçekleşen NET birim maliyeti. |
| `gerçek_teklif_farkı` | gerçekleşen − teklif; yüzde için teklif paydadır. |
| `gerçek_kaynak_farkı` | gerçekleşen − kaynak; yüzde için kaynak paydadır. |
| `dağıtım_temeli` | Masrafın satıra adet/ağırlık/hacim/değer temellerinden hangisiyle dağıtıldığının sürümlü kaydı. |

**Örnek satır:** Kaynak 50 TL, teklif 70 TL, gerçekleşen 85 TL → teklife göre +15 TL / %21,43; kaynağa göre +35 TL / %70,00.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Mal kabul/masraf kapanışında ve aylık; LC çalışma sürümü zorunlu. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RPT-08 — Dönem kıyası raporu

**Amaç:** Seçilen iki eş dönem arasında hacim, süre, maliyet ve dönüşüm farkını ortak formülle gösterir.

### Veri kaynakları

| Mantıksal tablo/görünüm | Kullanım kuralı |
|---|---|
| `RPT-01..RPT-07 dönem snapshotları` | Mantıksal kaynak; fiziksel tablo/görünüm adı uygulama sırasında kanıtlanıp eşlenir. |

> Tablo adları fiziksel şema iddiası değildir. Kabul edilen iç sözleşmelerdeki alanların raporlama görünümüne eşlenmesini tarif eder; eşleme kanıtlanana kadar **kanıtlanmadı** yazılır.

### Boyutlar ve ölçüler

Boyutlar: `cari_dönem`, `önceki_dönem`, `kategori`, `platform`, `firma`.

| Ölçü | Tanım |
|---|---|
| `cari_değer` | Seçili ölçünün cari dönem değeri. |
| `önceki_değer` | Aynı filtre ve ölçünün eş önceki dönem değeri. |
| `mutlak_fark` | cari_değer − önceki_değer. |
| `yüzde_fark` | mutlak_fark / önceki_değer × 100; önceki değer 0 ise tanımsız. |
| `karşılaştırılabilirlik` | Dönem uzunluğu, filtre, birim, para ve veri kapsamı kapılarının toplu evet/hayır sonucu. |

**Örnek satır:** Cari 120, önceki 100 → +20 ve %20,00; önceki 0 ise yüzde fark 'tanımsız'.

**Excel kuralı:** Ham sayılar ayrı hücrelerde sayısal tipte; yüzde yalnız formülden; para birimi, NET/BRÜT temeli, tarih aralığı, saat dilimi ve kaynak snapshot kimliği üstbilgide. Birleştirilmiş hücre yok; filtre satırı açık; kanıtlanmayan değer boş + `kanıtlanmadı` notuyla çıkar.

**PDF kuralı:** Aynı filtre/snapshot; ilk sayfada kapsam ve hesap formülü; her tabloda birim; sayfa numarası ve oluşturulma zamanı; satır kesilmez; başka firmaya ait kör kıyas verisi dış firma PDF'ine girmez.

**Zamanlanmış üretim:** Aylık, çeyreklik veya yıllık; eş dönem uzunluğu zorunlu. Zamanlama raporu üretir; dış alıcıya otomatik gönderim ayrıca açık PM/kullanıcı ayarı olmadan yapılmaz.

## RP test vektörleri

Bu vektörler PM'in uygulamadan bağımsız olarak hesap makinesiyle doğrulayabileceği minimum kabul setidir.

| ID | Rapor | Girdi | Beklenen |
|---|---|---|---|
| RP-001 | RPT-01 | 200 yakalama, 100 liste | listeye_geçiş = 100/200 = %50,00 |
| RP-002 | RPT-01 | 60 gönderim, 45 yanıt | yanıt = 45/60 = %75,00 |
| RP-003 | RPT-01 | 200 yakalama, 12 sipariş | uçtan_uç = 12/200 = %6,00 |
| RP-004 | RPT-02 | kategori 40, toplam 100 | kategori_payı = %40,00 |
| RP-005 | RPT-02 | toplam 0 | kategori_payı = tanımsız; 0 uydurulmaz |
| RP-006 | RPT-03 | cevap saatleri 2,4,10 | medyan_cevap_saati = 4 |
| RP-007 | RPT-03 | 7 bulundu / 10 yanıtlanabilir satır | bulunma_oranı = %70,00 |
| RP-008 | RPT-03 | 2 revizyon / 5 tur | revizyon_oranı = %40,00 |
| RP-009 | RPT-04 | yurtiçi 150, ithalat 100 | avantaj = 50 TL/adet; %33,33 |
| RP-010 | RPT-04 | 50 TL/adet avantaj, etkin adet 100 | toplam_avantaj = 5.000 TL |
| RP-011 | RPT-04 | yurtiçi 0, ithalat 80 | avantaj_yüzde = tanımsız; veri kapısı |
| RP-012 | RPT-05 | 1.000 USD; 30 kilit; 33 ödeme | kur_farkı = +3.000 TL; %10,00 |
| RP-013 | RPT-05 | 1.000 USD; 30 kilit; ödeme kuru yok | kur_farkı = kanıtlanmadı |
| RP-014 | RPT-06 | plan 2026-09-01, gerçek 2026-09-06 | sapma = +5 gün |
| RP-015 | RPT-06 | plan 2026-09-06, gerçek 2026-09-01 | sapma = -5 gün; erken |
| RP-016 | RPT-07 | teklif 70, gerçek 85 | fark = +15 TL; %21,43 |
| RP-017 | RPT-07 | kaynak 50, gerçek 85 | fark = +35 TL; %70,00 |
| RP-018 | RPT-08 | cari 120, önceki 100 | fark = +20; %20,00 |
| RP-019 | RPT-08 | cari 20, önceki 0 | mutlak fark +20; yüzde fark tanımsız |
| RP-020 | RPT-08 | 30 günlük dönem ile 31 günlük dönem | karşılaştırılabilirlik = hayır; normalize edilmeden yüzde üretilmez |

## Rapor üretim kabul kapısı

- RP-001..RP-020 sonuçlarının tamamı aynı yuvarlama ve tanımsızlık davranışıyla geçer.
- Excel ve PDF aynı snapshot, filtre ve sayısal toplamları verir.
- Başka firmaya giden dış çıktıda firma kör kıyas ihlali `0`dır.
- Para alanında para birimi/NET-BRÜT temeli eksik rapor yayınlanmaz; `status.missing_data` ile incelemeye kalır.
- Zamanlanmış rapor dışa kendiliğinden gönderilmez.
