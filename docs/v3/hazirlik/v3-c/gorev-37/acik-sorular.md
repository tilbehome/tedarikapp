# V3-C açık sorular

**Statü:** TASLAK — PM onayı yok  
Bu dosyadaki her madde **PM KARARI GEREKLİ** durumundadır. Seçenekler öneridir; hiçbiri seçilmiş sayılmaz.

## 1. Kanonik RFQ dosyası ve v1’in kaderi

**Kaynak:** `docs/v3/hazirlik/v3-c/rfq-alan-sozlesmesi.json` · v1; `gorev-30-ek/rfq-alan-sozlesmesi-v2.json` · v2; GÖREV #37 · alternatif termin üçlüsü v2.1 kararı.

- **A — v2.1 kanon, v1 arşiv:** Üretim ve testler tek v2.1 dosyasını okur; v1 tarihçe için `archive/` altına taşınır. Geçmiş iz korunur, yanlışlıkla çalışma zamanı kaynağı olma riski azalır.
- **B — v2.1 kanon, v1 silinir:** En temiz tek kaynak elde edilir; v1 geçmişi yalnız Git tarihçesinde kalır.
- **C — İki dosya sürümlü desteklenir:** Eski içe aktarımlar yaşayabilir; fakat dönüştürücü, sürüm seçimi ve çift test yükü doğar.

**PM KARARI GEREKLİ:** A/B/C ve seçilen dosyanın tam yolu.

## 2. Alternatif nesnesinin Excel gösterimi

**Kaynak:** `excel-gelgit-spec.md` · §4.2, §7; `rfq-alan-sozlesmesi-v2.json` · `alternatif_cevap_modeli`; GÖREV #37 · v2.1 termin üçlüsü.

- **A — Ayrı `ALTERNATIVES` sayfası:** Nesne ayrılığı ve birden çok alternatif doğal temsil edilir; şablona altıncı sayfa eklenir.
- **B — QUOTATION altında bağlı tekrar satırları:** Kullanıcı tek yüzeyde çalışır; satır türü/imza/eşleme karmaşıklığı artar.
- **C — Satır başına tek alternatif, mevcut sütunlar:** Mevcut şablon daha az değişir; v2.1’in birden çok ayrı nesne genişlemesini sınırlar.

**PM KARARI GEREKLİ:** A/B/C; termin üçlüsü seçilen temsilde zorunlu bağlanmalıdır.

## 3. Durum makinesinde “15 geçiş” neyi sayıyor?

**Kaynak:** `teklif-turu-durum-makinesi.md` · §3; `app/Services/Tur/TurDurumMakinesi.php` · `GECISLER`.

- **A — 15 numaralı iş olayı kanon:** Çoklu “önce” durumları tek iş olayı sayılır; ayrıca tam adjacency tablosu yayımlanır. Kod yalnız bu tablodaki kenarları açar.
- **B — Her doğrudan kenar ayrı geçiş:** Belge tüm kenarları ayrı numaralandırır; kabul sayısı 15 olmaktan çıkar.
- **C — Kodun bugünkü ek kenarları kapanır:** `REVISION_REQUESTED/EXPIRED` kaynaklı belgesiz kenarlar reddedilir; sayı belgedeki sözleşmeye yaklaşır.

**PM KARARI GEREKLİ:** Sayım ve kanonik adjacency.

## 4. Tur olayları kolon mu, olay günlüğü mü?

**Kaynak:** `teklif-turu-durum-makinesi.md` · §3 zaman damgaları; `migrations/0036_firmalar_ve_turlar.php` · `supplierRounds`.

- **A — Eksik zaman damgalarını kolon olarak ekle:** Okuma basittir; yeni olaylarda şema genişlemeye devam eder.
- **B — Append-only `supplier_round_events`:** Actor, reason, önce/sonra, zaman ve idempotency tek yapıda izlenir; özet kolonların nasıl güncelleneceği tanımlanmalıdır.
- **C — Hibrit:** Sık sorgulanan anlar kolon, tüm ayrıntı olay günlüğü; iki yüzeyin atomik tutarlılık testi gerekir.

**PM KARARI GEREKLİ:** A/B/C.

## 5. Yeni teklif bildirim kodları

**Kaynak:** `teklif-turu-durum-makinesi.md` · §8; `docs/08-risk-ve-karar-kaydi.md` · K82, K102.

- **A — Önerilen altı `NTF-QUOTE-*` olayını kataloğa al:** Tur yaşam döngüsü ayrı izlenir; katalog/çeviri/audit/merge politikası genişler.
- **B — Yalnız mevcut liste ve share olaylarını kullan:** Katalog küçük kalır; tur düzeyindeki ilk görüntüleme/fiyatlamaya başlama ayrımı kaybolur.
- **C — Asgari üç olay:** gönderim, yanıt, revizyon ayrı; taslak/görüntüleme/fiyatlama yalnız audit. Daha az bildirim gürültüsü, sınırlı görünürlük.

**PM KARARI GEREKLİ:** Kod listesi ve K82’ye uygun teklif-geçerliliği olay adı. Anahtar süresi olayı açılamaz.

## 6. `shares.recipient_type` ile #34-R rol kimlikleri

**Kaynak:** `migrations/0037_paylasim_tablosu.php` · `recipient_type`; `ShareRepository.php` · `ALICI_ITHALATCI`; `gorev-34/rol-gorunurluk-matrisi.json` · `roller`.

- **A — Bugün yalnız `importer`, açık eşleme katmanı:** Migration küçük kalır; serializer `importer → ithalatci` eşlemesini sunucuda tek yerde yapar.
- **B — Saklanan değerler #34-R rol kimlikleri olur:** Şema ve matris aynı dili kullanır; mevcut `importer` kayıtlarının göçü gerekir.
- **C — `recipient_type` ve `serializer_role` ayrılır:** Gelecekte çıktı/portal farkını taşır; ek kolon ve tutarlılık kuralı doğar.

**PM KARARI GEREKLİ:** A/B/C ve izinli değerlerin kanonik kaynağı.

## 7. #34-R-v2’nin bağlayıcılık statüsü

**Kaynak:** `gorev-34/rol-gorunurluk-matrisi.json` · `durum:oneri`; `gorev-34/OKUBENI.md` · yönetişim notu; GÖREV #37 · sızıntı kabul seti.

- **A — 34-R-v2 bu emirde kanonlaştırılır:** 678 hücre ve 273 SZ vakası V3-C kabul kapısı olur.
- **B — Yalnız V3-C ithalatçı altkümesi kanonlaştırılır:** Dar kapanış yapılır; diğer roller V3-N’ye kalır.
- **C — Paket bilgilendirici kalır:** Sızıntı kabulü başka onaylı matrise bağlanmalıdır; bugün o kaynak yoktur ve kapanış bloklanır.

**PM KARARI GEREKLİ:** A/B/C ve Ürün Sahibi kırmızı çizgi onayının kaydı.

## 8. K105’in firma portalı istisna matrisi

**Kaynak:** `docs/v3/k105-mikro-etkilesim-standardi.md` · §1–3; GÖREV #37 · firma görünümünde Paylaş yok; `portal-ekran-sartnameleri.md` · salt-okunur RFQ sınırları.

- **A — Dış portal için gerekçeli `yok` hücreleri:** Paylaş, çoğalt, taşı, sil, arşivle gibi yetkisiz eylemler istisna listesine yazılır; kalan K105 davranışları zorunlu kalır.
- **B — K105 yalnız iç panel ekranlarına uygulanır:** Portal üretim kapsamı basitleşir; “V3-C’de doğan her yeni ekran” kararı değiştirilmiş olur.
- **C — Dış portal için ayrı K105 profil eki:** En açık sözleşme olur; yeni bakım yüzeyi doğar.

**PM KARARI GEREKLİ:** A/B/C. Firma görünümünde Paylaş düğmesi hiçbir seçenekte açılamaz.

## 9. K105 bileşen bekçisi

**Kaynak:** `docs/v3/k105-mikro-etkilesim-standardi.md` · §3; `origin/v3-faz1:tests/Support/` envanteri.

- **A — Belgede adı verilen `K105BilesenBekcisiTest.php` eklenir:** Sözleşme aynen uygulanır.
- **B — Var olan `K105DefterBekcisiTest` genişletilir:** Test sayısı azalır; belge ve sınıf adı buna göre güncellenir.
- **C — Davranış ESLint/başka statik kuralla zorlanır:** Dil-katmanı daha uygun olabilir; PHP bekçisi atfı kaldırılır ve eşdeğer kapsama kanıtı gerekir.

**PM KARARI GEREKLİ:** A/B/C.

## 10. Listeler stepper’ın kanonik aşama sayısı

**Kaynak:** GÖREV #37 · “stepper 1-4”; `docs/v3/tasarim-referans/listeler.png`; `docs/v3/V3-YOL-HARITASI.md` · §7.4–7.5.

- **A — Dört aşama:** GÖREV #37 esas alınır; referans görseldeki etiketler ve yaşam döngüsü eşlemesi güncellenir.
- **B — Beş aşama:** Referans görsel esas alınır; “1-4” ifadesi görev sıra numarası olarak düzeltilir.
- **C — Liste ve teklif stepper’ı ayrılır:** Liste yaşam döngüsü beş, teklif turu ayrı durum makinesi olur; ekranda iki kavramın karışmaması için ayrı bileşen gerekir.

**PM KARARI GEREKLİ:** A/B/C ve kanonik etiketler.

## 11. Hedef/geçmiş fiyatın firma portalındaki kapsamı

**Kaynak:** `V3-YOL-HARITASI.md` · §7.6; `portal-ekran-sartnameleri.md` · §1; #34-R kırmızı çizgileri.

- **A — Tamamen kapalı:** İç fiyat sinyali dışarı çıkmaz; portal yalnız firma teklifini taşır.
- **B — Değer göstermeyen nitel sinyal:** “hedefe yakın/uzak” gibi bir istem verilir; yine de tersine mühendislik ve pazarlık riski değerlendirilir.
- **C — Açık hedef değer:** Yol haritasındaki opsiyon uygulanır; mevcut gizlilik ilkesi ve rol matrisi açık kararla değiştirilmek zorundadır.

**PM KARARI GEREKLİ:** A/B/C. Varsayılanla karar verilmez.

## 12. Yol haritası FAZ 3 kapsamı mı, dar kapanış mı?

**Kaynak:** `V3-YOL-HARITASI.md` · §14 FAZ 3; GÖREV #37 · Blok B–F kapsam özeti; bağımsız gün emri dosyası eksik.

- **A — Dar kapanış:** Tur/portal/ayrıştırma/Excel/Listeler/şablon/F42 teslim edilir; bildirim merkezi, Belgeler, Takvim+ICS, analitik vb. açık erteleme listesine yazılır.
- **B — Tam FAZ 3:** Yol haritasındaki bütün modüller aynı emre girer; mevcut kaynak/kabul kataloğu yetersiz olduğundan yeni şartname gerekir.
- **C — Dar kod + şema temelleri:** UI yalnız dar kapsam, diğer modüller için yalnız açıkça kayıtlı DDL temeli; “tamamlandı” denmez.

**PM KARARI GEREKLİ:** A/B/C ve kapsam dışı kalemlerin adı.

## 13. Sürüm numarası

**Kaynak:** GÖREV #37 · `v1.3.0`; `V3-YOL-HARITASI.md` · §14 FAZ 3 → 1.2.

- **A — v1.3.0:** Kapanış kaydındaki hedef izlenir; yol haritası ve sürüm sıralaması güncellenir.
- **B — v1.2.x:** Yol haritası korunur; GÖREV #37 paket hedefi değiştirilir.
- **C — Ön sürüm etiketi:** Kapsam/kabul boşlukları nedeniyle önce RC üretilir; nihai paket ancak eksikler kapandıktan sonra çıkar.

**PM KARARI GEREKLİ:** Tam sürüm dizesi ve RC kullanımı.

## 14. #36, KT-C ve Excel fikstürleri gelmezse

**Kaynak:** GÖREV #37 · “#36 (gelirse)” ve kabul sınavları; repo/Library envanteri · paket yok.

- **A — Kapanış bloklanır:** #36 kataloğu, KT-C ve gerçek fikstürler gelmeden PR/paket yoktur.
- **B — Mevcut kaynaklarla dar kabul:** YA-001…030 ve #15/#28 testleri koşar; #36 açık erteleme ve sürüm riski olarak raporlanır.
- **C — Ayrı hazırlık işi açılır, aynı kapanışa yetişir:** Kaynak üretimi yetkili görevle yapılır; GÖREV #37 taslağı içerik uydurmaz.

**PM KARARI GEREKLİ:** A/B/C. Fikstür/vaka içeriği kaynaksız oluşturulamaz.

## 15. K107 kaydı

**Kaynak:** GÖREV #37 · K107 beklentisi; erişilebilen üç dal ve karar günlükleri · eşleşme yok.

- **A — Kanonik K107 metni/ref’i sağlanır:** Karar defterine gerçek içerik eklenir ve etkilediği bloklar belirlenir.
- **B — K107 bu kapanıştan açıkça çıkarılır:** Eksik karar uygulanmış sayılmaz; raporda kapsam dışı görünür.
- **C — K107 yeni karar olarak PM tarafından yazılır:** Yeni karar numarası/yetkisi/tarihi açıkça kaydedilir; bu taslak onu üretmez.

**PM KARARI GEREKLİ:** A/B/C.

## 16. Sertleştirme kaynağı

**Kaynak:** GÖREV #37 · `sertlestirme-v1-2-2`; uzak dal listesi · eşleşme yok; `origin/v3-faz1` · v1.2.1 sertleştirme ve D8.

- **A — Doğru v1.2.2 ref/SHA sağlanır:** Merge zinciri o kaynağı kullanır; farklar ayrıca taranır.
- **B — `v3-faz1`deki v1.2.1 yetkili kaynak kabul edilir:** D8/K105/K106 mevcut geçmişten alınır; “v1.2.2” atfı düzeltilir.
- **C — Sertleştirme ayrı PR olarak önce tamamlanır:** V3-C kapanışı yeni hedef SHA’dan başlar; takvim uzar ama kaynak belirsizliği kalkar.

**PM KARARI GEREKLİ:** A/B/C.

## 17. Kapanış PR hedefi

**Kaynak:** GÖREV #37 · merge zinciri, PR ve Blok G “main eşitleme”; korumalı dal süreç notu `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · 1 Eyl 2026.

- **A — `is-emri-v3c → main`:** Tek kapanış PR’ı; `v3-faz1` önce kaynak dal olarak tamamen içeri alınır.
- **B — `is-emri-v3c → v3-faz1`, sonra ayrı `v3-faz1 → main`:** Faz dalı tarihçesi korunur; iki PR/kabul turu gerekir.
- **C — Yeni kapanış dalı → main:** Mevcut iş dalı kaynak olarak kalır; merge zinciri yeni dalda belgelenir.

**PM KARARI GEREKLİ:** A/B/C ve korumalı dal kontrolleri.

## 18. Bağımsız gün emri ve dış denetim kaydı

**Kaynak:** GÖREV #37 · “tedarikapp gün emri” ve `dis-denetim-3.md` örneği; repo/Library envanteri · bulunamadı.

- **A — Dosyalar sağlanır:** Çelişki taraması ve görev atıfları gerçek bölümlere güncellenir.
- **B — GÖREV #37 özeti kanonik ikame kabul edilir:** Taslak yalnız bu özette yazan kapsamı kullanır; daha ayrıntılı talep varmış gibi davranmaz.
- **C — Bu kaynaklara bağlı maddeler emrin dışına alınır:** Stepper/fikstür/kapsam ayrıntılarının bir bölümü kapanıştan düşer.

**PM KARARI GEREKLİ:** A/B/C.
