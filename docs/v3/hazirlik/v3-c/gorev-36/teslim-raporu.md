# GÖREV #36 Teslim Raporu

## Sayılar

| Ölçü | Dosyadaki sayı |
|---|---:|
| E2E senaryosu | 90 |
| E2E kimlik aralığı | E2E-C-001..E2E-C-090 |
| Kabul turu maddesi | 36 |
| Kabul turu süresi | 87 dk |
| XLSX fikstürü | 12 |
| Altın set vakası bağlı | 30 |
| portal.validation.* anahtarı bağlı | 19 |
| #28 yasak varsayım bağlı | 4/4 |
| #28 kabul sınırı bağlı | 10/10 |
| Durum geçişi bağlı | 15/15 |

## Otomasyon sınıfı dağılımı

| Sınıf | Sayı |
|:---:|---:|
| A — Playwright | 29 |
| B — PHPUnit HTTP/entegrasyon | 59 |
| C — yalnız elle | 2 |
| **Toplam** | **90** |

## 15 geçiş kapsaması

| Bağlayıcı geçiş | Senaryo |
|---|---|
| Geçiş 1: — → `DRAFT` | E2E-C-001 |
| Geçiş 2: `DRAFT` → `SENT` | E2E-C-002 |
| Geçiş 3: `SENT` → `VIEWED` | E2E-C-003 |
| Geçiş 4: `SENT/VIEWED` → `PRICING` | E2E-C-004 |
| Geçiş 5: `PRICING` → `PRICING` | E2E-C-005 |
| Geçiş 6: `SENT/VIEWED/PRICING` → `RESPONDED` | E2E-C-006 |
| Geçiş 7: `RESPONDED` → `APPROVED` | E2E-C-007 |
| Geçiş 8: `RESPONDED` → `REVISION_REQUESTED` | E2E-C-008 |
| Geçiş 9: `REVISION_REQUESTED` → R2 `DRAFT` | E2E-C-009 |
| Geçiş 10: R2+ `DRAFT` → `SENT` | E2E-C-010 |
| Geçiş 11: R2+ dış akış | E2E-C-011 |
| Geçiş 12: `DRAFT` → `ABANDONED` | E2E-C-012 |
| Geçiş 13: Aktif/yanıtlı → `ABANDONED` | E2E-C-013 |
| Geçiş 14: Aktif/yanıtlı → `EXPIRED` | E2E-C-014 |
| Geçiş 15: Aktif dış erişim → `REVOKED` | E2E-C-015 |

## Portal ve v1.2.2 kapsaması

| Bağlayıcı yüz/madde | Senaryo | Kabul maddesi |
|---|---|---|
| Portal ekran 1 — Karşılama ve tur özeti | E2E-C-027 | KT-C-017..018 |
| Portal ekran 2 — Liste görünümü ve ilerleme | E2E-C-028 | KT-C-018 |
| Portal ekran 3 — Satır yanıt formu | E2E-C-029..047 | KT-C-019..021 |
| Portal ekran 4 — Kısmi gönderim | E2E-C-053 | KT-C-025 |
| Portal ekran 5 — Nihai gönderim onayı | E2E-C-054 | KT-C-026 |
| Portal ekran 6 — Başarı ve salt okunur teklif | E2E-C-055 | KT-C-026 |
| Portal ekran 7 — Revizyon turu | E2E-C-056 | KT-C-024, KT-C-026 |
| Yedek parça + SHA + manifest + Doğrula | E2E-C-089 | KT-C-006 |
| APP_KEY emaneti ve şifreyi yeniden sorma | E2E-C-089 | KT-C-007 |
| KISMİ set rozeti | E2E-C-089 | KT-C-008 |
| Ana görsel kuyruğu + proxy + yerel/uzak rozeti | E2E-C-090 | KT-C-009 |
| Sözlüksüz çevrilmiş ürün kartı | E2E-C-090 | KT-C-010 |

## #28 — 4 yasak varsayım

Bu rapordaki YV-* işaretleri yalnız izlenebilirlik etiketidir; kaynak metindeki dört cümleyi değiştirmez.

| Etiket | Yasak varsayım | Senaryo |
|---|---|---|
| YV-01 | Her firma portal kullanır | E2E-C-062, E2E-C-072 |
| YV-02 | Boş satır `Bulunamadı`dır | E2E-C-068, E2E-C-073 |
| YV-03 | DDP kur riskini çözer | E2E-C-019, E2E-C-021 |
| YV-04 | Kademeler arasında doğrusal fiyat hesaplanır | E2E-C-023, E2E-C-069 |

## #28 — 10 kabul sınırı

| Etiket | Bağlayıcı sınır | Senaryo |
|---|---|---|
| KS-01 | Ham kanıt ayrıştırılmış sonuçtan bağımsız kalır | E2E-C-062, E2E-C-071 |
| KS-02 | Satır kimliği ad benzerliğinden önce gelir | E2E-C-064, E2E-C-081 |
| KS-03 | Boş cevap değildir; açık olumsuz ayrıdır | E2E-C-068, E2E-C-073 |
| KS-04 | 0 fiyat tek başına `not_found` değildir | E2E-C-068 |
| KS-05 | Belirsiz para kesin fiyat olmaz | E2E-C-065, E2E-C-078 |
| KS-06 | DDP/KDV/teslim noktası ayrı doğrulanır | E2E-C-033, E2E-C-065 |
| KS-07 | Belirsiz kademede ara miktar hesaplanmaz | E2E-C-023, E2E-C-069 |
| KS-08 | Alternatif ayrı nesne; asıl değişmez | E2E-C-039, E2E-C-067 |
| KS-09 | Kısmi cevap eksik satırları kapatmaz | E2E-C-053, E2E-C-073 |
| KS-10 | Eski tur yeni turun cevabı olmaz | E2E-C-022, E2E-C-076 |

## #28 — 14 kırılma + telafi

| Etiket | Kırılma | Senaryo |
|---|---|---|
| KT-01 | Portal kullanılmıyor | E2E-C-062, E2E-C-072 |
| KT-02 | Satır sırası değişiyor | E2E-C-064, E2E-C-081 |
| KT-03 | Cevap kısmi | E2E-C-053, E2E-C-073 |
| KT-04 | Boş/sıfır/tire karışıyor | E2E-C-068, E2E-C-073 |
| KT-05 | KDV belirsiz | E2E-C-033, E2E-C-065 |
| KT-06 | DDP kapsamı belirsiz | E2E-C-033, E2E-C-065 |
| KT-07 | Kur şartı belirsiz | E2E-C-019, E2E-C-021 |
| KT-08 | Kur kilidi tekliften önce bitiyor | E2E-C-019 |
| KT-09 | Kademe aralığı belirsiz | E2E-C-023 |
| KT-10 | Eski Excel yeni tura dönüyor | E2E-C-076 |
| KT-11 | Alternatif aslı eziyor | E2E-C-039 |
| KT-12 | Mesaj ve belge çelişiyor | E2E-C-022 |
| KT-13 | Birden çok para birimi | E2E-C-078 |
| KT-14 | “Bakılıyor” nihai sayılıyor | E2E-C-071 |

## Portal doğrulama anahtarları

| Anahtar | Senaryo |
|---|---|
| portal.validation.status_required | E2E-C-030 |
| portal.validation.found_price_required | E2E-C-031 |
| portal.validation.currency_required | E2E-C-031 |
| portal.validation.found_moq_required | E2E-C-032 |
| portal.validation.found_lead_time_required | E2E-C-032 |
| portal.validation.ddp_vat_confirmation_required | E2E-C-033 |
| portal.validation.positive_number | E2E-C-034 |
| portal.validation.tier_incomplete | E2E-C-035 |
| portal.validation.tier_order | E2E-C-036 |
| portal.validation.tier_overlap | E2E-C-037 |
| portal.validation.carton_dimensions_together | E2E-C-038 |
| portal.validation.alternative_details_required | E2E-C-040 |
| portal.validation.not_found_note_required | E2E-C-041 |
| portal.validation.remaining_rows | E2E-C-042 |
| portal.validation.quantity_below_moq | E2E-C-043 |
| portal.validation.lead_time_max | E2E-C-044 |
| portal.validation.gross_below_net | E2E-C-045 |
| portal.validation.cbm_mismatch | E2E-C-046 |
| portal.validation.url_invalid | E2E-C-047 |

## Excel fikstür sonucu

- 12 dosyanın her birinde envanterdeki aynı dosya adı vardır.
- Her dosya START, QUOTATION, PRICE_TIERS, VALIDATION, MANIFEST sayfalarını taşır.
- Makro, harici çalışma kitabı bağlantısı, uzak veri sorgusu, portal linki ve 6 haneli anahtar yoktur; yalnız şartnamedeki güvenli kaynak ürün URL alanı vardır.
- Negatif fikstür “bozuk dosya” değildir; Excel/LibreOffice’te açılır ve uygulama katmanında beklenen güvenli red/uyarıyı üretir.
- 12/12 XLSX yeniden içe aktarılmış, her birinde 5/5 sayfa ve kritik QUOTATION/MANIFEST aralıkları okunmuştur; formül hata taraması 0'dır.
- 60/60 sayfa render edilip temas sayfalarıyla görsel kontrolden geçirilmiştir.
- 12/12 dosyada VALIDATION ve MANIFEST sayfaları `veryHidden` durumundadır; toplam 24 görünürlük bayrağı doğrulanmıştır.

## Kontrollü uyumsuzluklar / açık sorular

1. Altın sette YA-004 ve YA-017, ayrıştırıcı ara sonucu olarak alternative_available taşır; Excel spesifikasyonu da aynı seçeneği listeler. Kanonik RFQ v2’de bu kalıcı alan değildir. Katalog iki aşamalı oracle belirler: sınıflayıcı sinyali okunabilir, fakat kalıcılık kesin olarak asıl not_found + asil_rfq_satir_id ile bağlı ayrı alternatif nesnesidir. Altın set ve Excel spesifikasyonu sonraki doküman revizyonunda bu ayrımı açıkça yazmalı mı?
2. PM eki alternatif nesnesinde termin_baslangici, termin_suresi, termin_birimi zorunlu ve ambalaj serbest metindir; kanonik v2 dosyasında bu ek alanlar henüz görünmüyorsa uygulama şeması kodlamadan önce hangi sürüm numarasıyla güncellenecek? Senaryolar PM ekini üstün oracle kabul eder.

K105 matrisi görev girdisinde açıkça verilen geri alınabilir sil, kopyala, `⋯` menüsü, Ctrl+K ve mouse/keyboard/context/command palette sınırını bağlayıcı alır.

## Kabul kapısı kanıtları

- E2E kimlikleri 90/90 ve kabul turu kimlikleri 36/36 programatik olarak kesintisiz doğrulanmıştır.
- Rapor sayıları dosya içeriklerinden yeniden sayılmış; Excel envanteri 12/12 dosyayla birebir eşleşmiştir.
- Kabul turu toplamı 87 dakikadır ve 90 dakikayı aşmaz.
- Teslim kökünde kullanıcıya görünen yasak marka ifadesi taranır; sonuç sıfır olmalıdır.
- XLSX dosyaları tekrar içe aktarılıp sayfa adları ve kritik aralıklar denetlenir; tüm sayfalar görsel olarak render edilir.
