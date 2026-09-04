# TASLAK — PM onayı yok — V3-C kapanış iş emri

> Bu metin PM’in nihai emri değildir. Köşeli karar boşlukları ve **PM KARARI GEREKLİ** satırları PM tarafından kapatılmadan kodlamaya başlanmaz. Nihai emir tek parça yürütülür; ara onay beklenmez.

## BAĞLAM

V3-C firma döngüsünü tek kapanışta birleştir: dal zinciri ve sertleştirme, tur/RFQ omurgası, firma portalı, yapıştır-ayrıştır, Excel gel-git, Listeler merkezi, tekrar sipariş/şablonlar, firma çıktısı, kabul kapıları, PR ve doğrulanmış sürüm paketi. Kaynaklar çeliştiğinde sessiz seçim yapma; bu taslaktaki karar kapılarını PM’in nihai metnindeki açık hükümlerle değiştir.

**Kaynak:** `docs/v3/V3-YOL-HARITASI.md` · §7.4, §7.6, §14 FAZ 3; `docs/v3/hazirlik/v3-c/OKUBENI.md`; GÖREV #37 · BAĞLAM.

### PM karar kapısı — nihai emre girmeden doldur

1. RFQ kanonu ve v1’in kaderi: **[PM KARARI GEREKLİ — `acik-sorular.md` §1]**
2. Alternatifin Excel temsili: **[PM KARARI GEREKLİ — §2]**
3. Geçiş adjacency’si ve tur olaylarının kalıcılığı: **[PM KARARI GEREKLİ — §3–4]**
4. Bildirim olay kümesi: **[PM KARARI GEREKLİ — §5]**
5. `recipient_type` / #34-R rol eşlemesi ve 34-R’nin statüsü: **[PM KARARI GEREKLİ — §6–7]**
6. K105 dış portal istisnaları ve bileşen bekçisi: **[PM KARARI GEREKLİ — §8–9]**
7. Listeler stepper sayısı ve hedef/geçmiş fiyat sınırı: **[PM KARARI GEREKLİ — §10–11]**
8. Dar/tam FAZ 3 kapsamı ve paket sürümü: **[PM KARARI GEREKLİ — §12–13]**
9. #36/KT-C/fikstürler, K107 ve sertleştirme ref’i: **[PM KARARI GEREKLİ — §14–16]**
10. PR hedefi ve eksik gün emri/dış denetim kaydı: **[PM KARARI GEREKLİ — §17–18]**

## GÖREVLER

### 0. Merge zinciri ve kaynakların kanonikleştirilmesi

| No | Görev | Kaynak |
|---:|---|---|
| 0.1 | Başlamadan `main`, `v3-faz1`, `is-emri-v3c` ve PM’in belirlediği sertleştirme ref’inin tam SHA’larını; çalışma ağacının durumunu ve merge-base’leri rapora kaydet. Eksik `sertlestirme-v1-2-2` ref’i için tahmin yürütme. | GÖREV #37 · GİRDİLER ve Blok 0; `hazirlik-envanteri.md` · dal kayıtları |
| 0.2 | `main`i `v3-faz1`e, ardından oluşan `v3-faz1`i `is-emri-v3c`ye birleştir. Her birleşmeden sonra çakışma listesini ve çözüm dayanağını kaydet; korumalı dala doğrudan push yapma. | GÖREV #37 · GÖREVLER/3 Blok 0; `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · 1 Eyl 2026 süreç notu; `docs/v3/V3-YOL-HARITASI.md` · §17 |
| 0.3 | D8’i `0036_paylasim_anahtari_sifreli_alan.php` olarak koru; V3-C `0036_firmalar_ve_turlar.php`, `0037_paylasim_tablosu.php`, `0038_paylasim_gocu.php`, `0039_belgeler_ve_sablonlar.php` dosyalarını sırasıyla `0037`, `0038`, `0039`, `0040` numaralarına taşı. Dosya içi numara atıflarını, K103’ü, testleri ve release beklentilerini aynı commit’te düzelt. | GÖREV #37 · GÖREVLER/1 ve Blok 0; `origin/v3-faz1:migrations/0036_paylasim_anahtari_sifreli_alan.php` · D8; `docs/08-risk-ve-karar-kaydi.md` · K103; V3-C migration başlıkları |
| 0.4 | D8 şifreleme politikasını yeni `shares` yolunda da koru: şifreli zarfı alacak alan MySQL’de 96 karakter olsun; repository düz anahtar saklamasın; eski `lists` kaydı geriye uyumlu tembel göç sınırında kalsın. | `origin/v3-faz1:migrations/0036_paylasim_anahtari_sifreli_alan.php` · D8; `origin/v3-faz1:app/Services/Share/ShareKeyService.php`; V3-C `migrations/0037_paylasim_tablosu.php` · `key_plain`; `app/Models/ShareRepository.php` · `anahtariYaz` |
| 0.5 | #30 ve #30-EK dosyalarını `docs/v3/hazirlik/v3-c/gorev-30/` ve `gorev-30-ek/` altında kanonikleştir; 149 portal anahtarını ve `status.viewed` ekini tek repo kaynaklarına birleştir. #30-R prototip dosyalarını değiştirme. | `gorev-30/OKUBENI.md` · “İşlevsel değişmezlik”; `gorev-30-ek/teslim-raporu.md` · §1, §6; `prototip-baglama-haritasi.md` · tüm bölümler |
| 0.6 | RFQ sözleşmesini PM’in seçtiği v2.1 yolunda kanonikleştir: ambalaj serbest metin; alternatif ayrı nesne; alternatifte `termin_baslangici`, `termin_suresi`, `termin_birimi`; bayat öneri bölümü yok. V1 için PM’in sil/arşiv/sürüm desteği kararını aynen uygula. | GÖREV #37 · GÖREVLER/1; `gorev-30-ek/rfq-alan-sozlesmesi-v2.json` · `alternatif_cevap_modeli`, `yeni_anahtar_onerileri`; `gorev-30-ek/prototip-baglama-haritasi.md` · “Enum anahtarları” |
| 0.7 | #34-R-v2 dosyalarını yalnız PM’in bağlayıcılık kararına göre kanonik konuma al; eski OKUBENİ’deki “bütün dış rollerde kaynak gizli” cümlesini R revizyonuyla tutarlı hâle getir. | `gorev-34/rol-gorunurluk-matrisi.json` · `durum`, MTR-05…07; `gorev-34/34-R-TESLIM-RAPORU.md` · “Değişen hücreler”; `gorev-34/OKUBENI.md` · “Kırmızı çizgi yorumu” |
| 0.8 | #36/KT-C/gerçek Excel fikstürleri ve K107 kaydı sağlanmışsa kanonik dosyaları ekle; sağlanmamışsa içerik üretme ve PM’in erteleme/bloklama hükmünü rapora geçir. | GÖREV #37 · GİRDİLER “#36 (gelirse)”, K107; `hazirlik-envanteri.md` · eksik kaynaklar |
| 0.9 | Merge ve doküman kanonikleştirmesinden sonra tam test tabanını çalıştır; Blok B’ye kırmızı tabanla geçme. | `docs/v3/V3-YOL-HARITASI.md` · §17 “Testler”; `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · K100–K102, K105–K106 |

### B. Tur, RFQ, paylaşım ve Teklifler omurgası

| No | Görev | Kaynak |
|---:|---|---|
| B.1 | `liste_id + firma_id + tur_no` biriminde RFQ snapshot ve firma/tur izolasyonunu kalıcılaştır. Q şartname alanlarını yalnız PM’in sağladığı/kanonikleştirdiği kayıtlı Q sözleşmesinden ekle; dosya yoksa alan uydurma. | `teklif-turu-durum-makinesi.md` · §1; `migrations/0036_firmalar_ve_turlar.php` · `rfqSnapshots`, `supplierRounds`; GÖREV #37 · “RFQ snapshot + Q şartname alanları” |
| B.2 | RFQ yanıt tablolarını kanonik v2.1’e eşitle: asıl durum `unanswered/found/not_found`; alternatif ayrı ve asıl `not_found` cevabına bağlı; ambalaj serbest; alternatif termin üçlüsü mevcut. | `gorev-30-ek/rfq-alan-sozlesmesi-v2.json` · v2 diff’i ve `alternatif_cevap_modeli`; GÖREV #37 · üç PM kararı; `gorev-28/28-v3c-firma-dongusu-saha-gercekleri.md` · §7.2, §9/8 |
| B.3 | 10 durumlu tur makinesini PM’in onayladığı adjacency ile sunucuda zorla; 15 numaralı iş olayıyla doğrudan kenar sayısını karıştırma. Actor, reason, eski/yeni durum ve zaman kalıcı olsun. | `teklif-turu-durum-makinesi.md` · §2–3; `app/Services/Tur/TurDurumMakinesi.php` · `DURUMLAR`, `GECISLER`; `acik-sorular.md` · §3–4 |
| B.4 | Geçişi yalnız doğrulayan saf sınıfla yetinme: optimistic version, transaction, idempotency, timestamp ve audit yazımını tek uygulama servisinde yap; revizyon eski turu değiştirmeden yeni turu atomik açsın. | `teklif-turu-durum-makinesi.md` · §3, §4 “Revizyon”, §7; `migrations/0036_firmalar_ve_turlar.php` · `supplierRounds`; `app/Services/Tur/TurDurumMakinesi.php` |
| B.5 | Nihai gönderim kapısını v2.1 nesne modeline göre sekiz koşulla sunucuda uygula; aynı idempotency anahtarı ikinci response snapshot üretmesin. | `teklif-turu-durum-makinesi.md` · §4 “Nihai gönderim kapısı”; `portal-ekran-sartnameleri.md` · §7 “Kapı”; `app/Services/Tur/NihaiGonderimKapisi.php` |
| B.6 | Kısmi gönderimi `PRICING`te tut; tamamlanan satır sürümünü ve ham kaynağı audit et, boşları kapatma, her otomatik kayıtta bildirim üretme. | `teklif-turu-durum-makinesi.md` · §3/5, §4 “Kısmi gönderim”; `gorev-28/28-v3c-firma-dongusu-saha-gercekleri.md` · §8–9 |
| B.7 | K104 kur dörtlüsünü tur açılışında kopyala; `rate_snapshot_id`yi yalnız provenance say; `inherit/refresh` seçimini audit et; belge hattını `lists.yuan_rate`ten ayırma. | `docs/08-risk-ve-karar-kaydi.md` · K104; `teklif-turu-durum-makinesi.md` · §5; `migrations/0036_firmalar_ve_turlar.php` · `supplierRounds` |
| B.8 | `shares` bütün okuma/yazmanın tek uygulama yolu olsun; eski `lists` kolonlarını silmeden idempotent kopyala ve uygulama başvurularını sıfırla. Yeniden numaralamadan sonra göç atfı `0039_paylasim_gocu.php` olsun. | `docs/08-risk-ve-karar-kaydi.md` · K103; `app/Models/ShareRepository.php` · sınıf açıklaması; `migrations/0038_paylasim_gocu.php` · başlık |
| B.9 | `shares.recipient_type` ve #34-R serializer rolünü PM’in seçtiği modelle sunucuda çöz; rol istemciden alınmasın. Bugün olmayan üretici panelini şema beklentisinden türetme. | V3-C `migrations/0037_paylasim_tablosu.php` · `recipient_type`; `gorev-34/rol-gorunurluk-matrisi.json` · `roller`, `ilke`, MTR-07; `gorev-34/OKUBENI.md` · “Esaslar” |
| B.10 | `share_dispatch_log` üzerinden hangi linkin kime, ne zaman, hangi kanal/dilde gittiğini kaydet; anahtar mesajını linkten ayrı kanal/mesaj kuralında tut. | `migrations/0037_paylasim_tablosu.php` · `dispatchLog`; `app/Models/ShareRepository.php` · `gonderimKaydet`; `firma-mesaj-kaliplari.md` · §1–3, “Gönderim öncesi hızlı kontrol” |
| B.11 | Panel sol menüsünde `Teklifler → Açık turlar / Geçmiş turlar`ı ve PM’in açıkladığı stepper 1–4 sözleşmesini bağla; liste/tur durumlarını birbirine karıştırma. | `docs/v3/V3-YOL-HARITASI.md` · §3, §7.6; GÖREV #37 · GİRDİLER “Teklifler menüsü”, “stepper 1-4”; `acik-sorular.md` · §10 |
| B.12 | Bildirim yayınını K102’ye göre bağla; transaction içindeki hata geçişi geri alsın, dışındaki hata kritik kayda/sayaca dönüşsün. K82’ye aykırı anahtar süresi olayı üretme. | `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · K102; `docs/08-risk-ve-karar-kaydi.md` · K82; `teklif-turu-durum-makinesi.md` · §7–8 |

### C. Firma portalı

| No | Görev | Kaynak |
|---:|---|---|
| C.1 | Yedi portal ekranını #30-R prototipinin akış, yerleşim, lacivert/altın görsel dili ve #15 şartnamesiyle üretim uygulamasına bağla; prototip HTML’ini uygulama kodu olarak kopyalama ve kaynak dosyayı değiştirme. | `gorev-30/OKUBENI.md` · “Görsel revizyon”, “Ekran ↔ şartname kapsama tablosu”, “İşlevsel değişmezlik”; `portal-ekran-sartnameleri.md` · §3–9 |
| C.2 | Portalın bütün metinlerini 149 anahtarlık tek kaynaktan çöz; durumları 186 terimli 5B’den al; VIEWED için `status.viewed` kullan. Teknik kodları kullanıcı etiketi diye gösterme. | `gorev-30-ek/teslim-raporu.md` · §1–4; `prototip-baglama-haritasi.md` · “Bölüm 11”, “Enum anahtarları”, “status.viewed”; `cikti-terimleri.json` · `status.*` |
| C.3 | Seçili dilde TR/EN/ZH arayüzü eksiksiz göster; bir hücrede dilleri karıştırma; yalnız etiketli kaynak/orijinal değer istisnasına izin ver. PM’in aktör terminolojisi kararını bütün metinlere tek seferde uygula. | `portal-ekran-sartnameleri.md` · §1–2, §12; `docs/v3/V3-YOL-HARITASI.md` · §2.5–2.6, §16; `acik-sorular.md` · aktör çelişkisi |
| C.4 | Satır formunu v2.1’e bağla: asıl yanıt değişmeden kalsın, alternatif ayrı nesne oluşturulsun; serbest ambalaj ve alternatif termin üçlüsü uçtan uca kaydedilsin. | `gorev-30-ek/prototip-baglama-haritasi.md` · “Alternatif modelinin prototipten üretime bağlanması”; GÖREV #37 · üç PM kararı; `gorev-28/...saha-gercekleri.md` · §7.2 |
| C.5 | Otomatik kayıt, çevrimdışı kuyruk, `round_version` çakışma yüzü, kısmi ve nihai gönderim kapılarını gerçek sunucu davranışıyla bağla; prototip simülasyonunu başarı kanıtı sayma. | `portal-ekran-sartnameleri.md` · §3–9 kayıt/çevrimdışı/hata bölümleri; `gorev-30/OKUBENI.md` · “Bilinçli sapmalar”/4; `teklif-turu-durum-makinesi.md` · §7 |
| C.6 | K51/K62 güvenlik kapılarını uygula: token hash, anahtar doğrulanmadan veri yok, sabit 404, oran sınırı, token+anahtar hash’ine bağlı 12 saatlik imzalı çerez, iptal/yenilemede eski oturumun ölmesi. | `docs/08-risk-ve-karar-kaydi.md` · K51, K62; `portal-ekran-sartnameleri.md` · §12 |
| C.7 | Firma görünümüne `Paylaş` düğmesi, route’u veya erişilebilir gizli eylemi koyma. K105’te PM’in onayladığı gerekçeli `yok`/profil kaydını işle. | GÖREV #37 · GÖREVLER/1; `gorev-30/firma-portali-prototip.html` · yedi ekran; `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §1, §2.4, §5 |
| C.8 | Portal dış serializer ve cache’i #34-R’nin PM onaylı rol kapsamına bağla; başka firma/tur, iç maliyet, hedef satış, kâr, iç kur veya saklı metadata hiçbir katmanda bulunmasın. | `gorev-34/rol-gorunurluk-matrisi.json` · `ilke`, `kirmizi_cizgi_alanlari`; `gorev-34/sizinti-test-seti.json` · negatif beklenti; `teklif-turu-durum-makinesi.md` · §5–6 |
| C.9 | K105’in ilgili zorunlu hücrelerini ortak bileşenlerle karşıla; dış portal yetkisine uymayan eylemleri yalnız PM’in onayladığı istisna kaydıyla `yok` say. | `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §1–5; `acik-sorular.md` · §8–9 |

### D. Yapıştır-ayrıştır

| No | Görev | Kaynak |
|---:|---|---|
| D.1 | Yapıştır-ayrıştırı sunucuda uygula; tarayıcı sonucu güvenilir sayma. Ham metni kanıt olarak sakla ve ayrıştırılmış önizlemeyi kullanıcı onayı olmadan tur yanıtına uygulama. | `gorev-28/28-v3c-firma-dongusu-saha-gercekleri.md` · §8–9; `portal-ekran-sartnameleri.md` · §1 “kanıt/salt-okunur” ilkeleri; GÖREV #37 · GİRDİLER |
| D.2 | Satır eşlemede kimlik → ürün kodu/link → kontrollü benzerlik sırasını kullan; belirsizliği otomatik doğru sayma. | `gorev-28/...saha-gercekleri.md` · §8 “Satır sırası değişiyor”, §9/2; `gorev-28/28-ek-donus-formatlari.md` · §1–16 |
| D.3 | Boş, sıfır, tire, “bakılıyor”, açık olumsuz, belirsiz DDP/KDV/kur/para birimi ve kademeleri #28’in 4 yasak varsayımı, 10 sınırı ve 14 telafisine göre ayır. | `gorev-28/...saha-gercekleri.md` · §8–11; `karar-defteri-v3c.md` · 10–37 |
| D.4 | Asıl/alternatif ayrımını v2.1 nesnesine yaz; alternatif aslı ezmesin; kısmi dönüş eksik satırları kapatmasın; eski tur verisini yeni tur cevabı yapma. | `gorev-28/...saha-gercekleri.md` · §6.3, §7.2, §9/8–10; `gorev-30-ek/rfq-alan-sozlesmesi-v2.json` · `alternatif_cevap_modeli` |
| D.5 | YA-001…YA-030 vakalarının tamamını sunucu ayrıştırıcı testine bağla ve CI’da zorunlu kapı yap; fixture’ı teste göre değiştirme. | `docs/v3/hazirlik/v3-c/yapistir-ayristir-altin-seti.json` · YA-001…YA-030; GÖREV #37 · kabul kapısı |
| D.6 | K105’in alan, tablo, sayfa ve yıkıcı eylem hücrelerini ayrıştırma önizlemesi/uygulama ekranında karşıla; eylemler sessiz çalışmasın. | `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §2.2, §2.3, §2.5, §2.6 |

### E. Excel gel-git

| No | Görev | Kaynak |
|---:|---|---|
| E.1 | START, QUOTATION, PRICE_TIERS, VALIDATION ve MANIFEST yapısını; kilitli kimlik alanlarını; çok dilli görünümü; fiyat/MOQ/termin/koli/ambalaj alanlarını kanonik v2.1’e göre üret. | `excel-gelgit-spec.md` · §1–5; `gorev-30-ek/rfq-alan-sozlesmesi-v2.json` · alan modelleri; GÖREV #37 · v2.1 termin kararı |
| E.2 | Alternatif nesnesini PM’in seçtiği Excel temsiline bağla; asıl satırı değiştirme ve alternatif termin üçlüsünü kaybetme. | `excel-gelgit-spec.md` · §4.2, §7; `acik-sorular.md` · §2; `gorev-28/...saha-gercekleri.md` · §7.2 |
| E.3 | İçe aktarmayı `supplier_round_id + rfq_snapshot_id + rfq_line_id + satır imzası` üzerinden eşle; ad, sıra veya çıplak URL ile sessiz eşleştirme yapma. Yanlış tur dosyasını reddet. | `excel-gelgit-spec.md` · §7–8; `gorev-28/28-ek-donus-formatlari.md` · §11–12 |
| E.4 | Uygulama öncesi fark/uyarı/hata önizlemesi göster; boş hücreyle veri silme; geçersiz veya yabancı satırı uygulama. Kaynak ve zamanı audit et. | `excel-gelgit-spec.md` · §8–10; `gorev-28/...saha-gercekleri.md` · §8 “Mesaj ile belge çelişiyor”, §9/1 |
| E.5 | Makro, dış bağlantı, OLE, parola ve formül enjeksiyonu kapılarını uygula; yüklenen dosyayı güvenli sınırda işle. | `excel-gelgit-spec.md` · §6; `docs/v3/V3-YOL-HARITASI.md` · §11 güvenlik |
| E.6 | #36 sağlanmışsa kayıtlı 10–15 gerçek XLSX fikstürünü test matrisiyle çalıştır; paket yoksa dosya/vaka uydurma ve PM’in kararını uygula. | GÖREV #37 · GİRDİLER ve kabul sınavları; `hazirlik-envanteri.md` · #36 fikstür boşluğu |
| E.7 | Şablon ve içe aktarma hata/önizleme yüzlerinde üç dili eksiksiz ve karıştırmadan göster; durum metnini 5B’den çöz. | `excel-gelgit-spec.md` · §2; `portal-ekran-sartnameleri.md` · §1–2; `cikti-terimleri.json` · `status.*` |
| E.8 | Excel ekranının K105 tablo/alan/sayfa hücrelerini ve ortak bileşenlerini kapsa; yeni P-borcu açma. | `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §1–4 |

### F. Listeler merkezi, tekrar sipariş/şablonlar ve firma çıktısı

| No | Görev | Kaynak |
|---:|---|---|
| F.1 | Listeler merkezini `docs/v3/tasarim-referans/listeler.png` kompozisyonuna birebir yaklaştır: KPI/uyarı şeridi, aktif-pasif-arşiv, durum çipleri, arama/filtre/sıra/grup/sütun kontrolleri, liste tablosu, sağlık paneli ve sayfalama. Davranışı yol haritası ve K105 ile tamamla. | `docs/v3/tasarim-referans/listeler.png`; `docs/v3/tasarim-referans/OKUBENI.md` · dosya tablosu; `docs/v3/V3-YOL-HARITASI.md` · §7.4; K105 · §2.3 |
| F.2 | PM’in stepper kararını görsel, durum sözlüğü ve gerçek liste/tur verisiyle tekleştir; sabit örnek sayı/metin üretime girmesin. | GÖREV #37 · “stepper 1-4”; `docs/v3/tasarim-referans/listeler.png`; `docs/v3/V3-YOL-HARITASI.md` · §7.4–7.5; `acik-sorular.md` · §10 |
| F.3 | Geçmiş listeden tekrar sipariş akışını yeni liste/tur olarak aç; eski tur/yanıt/snapshot’ı değiştirme veya yeni yanıt diye kopyalama. | `docs/v3/V3-YOL-HARITASI.md` · §7.4 “Hızlı ekleme”; `gorev-28/...saha-gercekleri.md` · §9/10 |
| F.4 | Liste şablonlarını `list_templates` şemasına bağla; ürün kimliklerini referansla, ürün satırlarını şablon içinde donmuş kopya yapma; kullanım sayısı/zamanını kaydet. | V3-C `migrations/0039_belgeler_ve_sablonlar.php` · `listTemplates` |
| F.5 | F42 firma Excel/PDF/CSV indirmesini sayfa üretilirken APP_KEY ile imzalanan 15 dakikalık linkle ver; token başına saatte 20 sınırı, Retry-After, kırpılmış IP audit’i ve sabit 404 uygula. | `docs/08-risk-ve-karar-kaydi.md` · K58; `docs/v3/V3-YOL-HARITASI.md` · §7.6 “Firma için çıktı” |
| F.6 | Dışa aktarmada kopya türünü istekten okuma: yalnız firma kopyası; iç maliyet/hedef satış/kâr alanı yok; `exports` satırı/revizyon harfi açma; K62 çerezi olmadan export-link/export/QR çalışmasın. | `docs/08-risk-ve-karar-kaydi.md` · K58, K62; `gorev-34/sizinti-test-seti.json` · dış çıktı vakaları |
| F.7 | Listeler merkezi ve tekrar sipariş/şablon yüzlerini K105’in satır, tablo, liste/link, sayfa ve yıkıcı eylem sözleşmeleriyle ortak bileşenlerden kur. | `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §2–3 |

### G. Main eşitleme ve kapanış adayı

| No | Görev | Kaynak |
|---:|---|---|
| G.1 | Blok B–F tamamlandığında güncel `main`i iş dalına yeniden birleştir; yeni çakışmaları kaynak/karar atfıyla çöz ve başlangıç/sonuç SHA’larını kaydet. Rebase/force-push ile ortak tarihçeyi gizleme. | GÖREV #37 · Blok G “main eşitleme”; `docs/v3/V3-YOL-HARITASI.md` · §17 yürütme disiplini |
| G.2 | Merge sonrası temiz kurulum, mevcut v1.2.1 üstüne yükseltme, SQLite ve MySQL testlerini; frontend ve E2E paketlerini tam çalıştır. | `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · K100; `origin/v3-faz1:bin/release.php` · mevcut kurulum denetimleri; D8 migration açıklaması |
| G.3 | Kanonik kaynaklarla kodun yeniden ayrışmadığını statik bekçilerle doğrula: tek RFQ, tek portal metni, tek 5B, K103 eski kolon başvurusu 0, K105 yeni ekran borcu 0. | `docs/08-risk-ve-karar-kaydi.md` · K99, K103, K105; `gorev-30-ek/teslim-raporu.md` · §4–5 |

### Kabul sınavları

| No | Görev / geçme ölçütü | Kaynak |
|---:|---|---|
| K.1 | #36 paketi sağlanmışsa E2E kataloğundaki bütün senaryoları koda bağla ve KT-C’yi kayıtlı adımlarla çalıştır. Sağlanmamışsa PM’in bloklama/erteleme kararını raporla; hayalî KT sonucu verme. | GÖREV #37 · GİRDİLER ve kabul sınavları; `hazirlik-envanteri.md` · #36 |
| K.2 | YA kapısı: YA-001…YA-030’ın her biri fixture’dan okunarak sunucu ayrıştırıcısında ve CI’da yeşil; eksik/atlanmış vaka 0. | `yapistir-ayristir-altin-seti.json` · YA-001…YA-030; GÖREV #37 · YA kapısı |
| K.3 | #28 kapısı: 4 yasak varsayım, 10 ayrıştırma sınırı ve 14 telafinin her biri en az bir kayıtlı test/KT adımıyla izlenebilir; sessiz varsayım 0. | `gorev-28/...saha-gercekleri.md` · §8–11; `karar-defteri-v3c.md` · 10–37 |
| K.4 | Tur kapısı: 10 durum ve PM onaylı 15 iş olayı/adjacency; geçersiz rol/kenar reddi; revizyon atomik; eski tur değişmez; idempotency tek snapshot. | `teklif-turu-durum-makinesi.md` · §2–4, §7; `TurDurumMakinesiTest.php`; `NihaiGonderimKapisiTest.php` |
| K.5 | Portal kapısı: 7/7 ekran, 149/149 portal anahtarı, 186/186 5B terimi, VIEWED=`status.viewed`, firma görünümünde Paylaş 0. | `gorev-30/OKUBENI.md` · kapsam tablosu; `gorev-30-ek/teslim-raporu.md` · §1; `prototip-baglama-haritasi.md` · VIEWED; GÖREV #37 · Paylaş yasağı |
| K.6 | Sıfır karışık dil: TR, EN ve ZH yüzlerinin her birinde seçilmemiş arayüz dili metni 0; etiketli kaynak/orijinal alan dışındaki karışım 0; yer tutucular üç dilde eş. | `portal-ekran-sartnameleri.md` · §1–2, §12; `gorev-30-ek/teslim-raporu.md` · §4; `docs/v3/V3-YOL-HARITASI.md` · §2.5–2.6 |
| K.7 | Sızıntı kapısı: PM’in kanonlaştırdığı #34-R kapsamındaki bütün SZ vakaları yeşil; kırmızı çizgi dış rol göster 0; başka firma/tur kimliği, alan adı/değeri, yer tutucu, metadata, cache ve gizli sütun sızıntısı 0. | `gorev-34/sizinti-test-seti.json` · `kabul_kapisi`, 273 vaka; `gorev-34/34-R-TESLIM-RAPORU.md` · programatik doğrulama |
| K.8 | Paylaşım güvenliği: K51 sabit 404/token hash/log yasağı; K62 anahtar ve çerez; K82 link süresi ≠ anahtar ≠ teklif geçerliliği; D8 şifreli alan; çapraz firma izolasyonu yeşil. | `docs/08-risk-ve-karar-kaydi.md` · K51, K62, K82; D8 migration; `teklif-turu-durum-makinesi.md` · §6 |
| K.9 | Excel kapısı: PM onaylı alternatif temsili; doğru/yanlış tur; imza/kimlik; boş/bozuk/yabancı satır; makro/dış bağlantı/OLE/parola/formül enjeksiyonu; önizleme ve audit vakaları yeşil. | `excel-gelgit-spec.md` · §6–10; `gorev-28/28-ek-donus-formatlari.md` · §8–16 |
| K.10 | #36 gerçek XLSX fikstürleri geldiyse kayıtlı 10–15 dosyanın tamamı beklenen sonuçla geçer; gelmediyse bu kapı PM hükmü olmadan “geçti” sayılamaz. | GÖREV #37 · GİRDİLER; `hazirlik-envanteri.md` · fikstür boşluğu |
| K.11 | K105 kapısı: yeni V3-C ekranlarının defter hücresi `kapsandi`; P-borcu 0; beş ortak bileşen ve PM onaylı istisnalar bekçiyle doğrulanır. | `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §1–5; `K105DefterBekcisiTest.php`; PM’in §9 kararı |
| K.12 | Migration kapısı: tekil 0036–0040 sırası, temiz kurulum ve v1.2.1 üstüne yükseltme, MySQL alan genişliği, göç idempotency’si ve checksum davranışı yeşil. | D8 `0036` migration; V3-C `0036…0039` migration’ları; `docs/08-risk-ve-karar-kaydi.md` · K100, K103; GÖREV #37 · yeniden numaralama |
| K.13 | Görsel kapı: Listeler merkezi referans kompozisyonu ve portal #30-R akışı görsel regresyonda onaylı tolerans içinde; mobil 360 px’te zorunlu yatay kaydırma yok, dokunma hedefleri en az 44×44. | `docs/v3/tasarim-referans/listeler.png`; `gorev-30/firma-portali-prototip.html`; `portal-ekran-sartnameleri.md` · §2, §12 |

### PR

| No | Görev | Kaynak |
|---:|---|---|
| PR.1 | Bütün kabul kapıları yeşil ve çalışma ağacı temiz olmadan PR açma. PM’in seçtiği hedef dala tek kapanış PR’ı aç; doğrudan korumalı dal push’u yapma. | GÖREV #37 · PR; `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · 1 Eyl 2026 süreç notu; `acik-sorular.md` · §17 |
| PR.2 | PR açıklamasına başlangıç/sonuç SHA’ları, merge zinciri, migration eşleme tablosu, karar/çelişki kapanışları, test komutları/sonuçları, #36/K107 statüsü ve kalan açık riskleri yaz. | GÖREV #37 · RAPOR FORMATI ve kabul kapısı; `docs/v3/V3-YOL-HARITASI.md` · §17 |
| PR.3 | Zorunlu kontrolleri bekle; kırmızı kontrolü atlama veya “flake” diye işaretleyip geçme. Düzeltme commit’lerini aynı kabul setiyle yeniden çalıştır. | `docs/v3/V3-YOL-HARITASI.md` · §17 “Testler”; K100; korumalı dal süreç notu |

### Paket

| No | Görev | Kaynak |
|---:|---|---|
| P.1 | Yalnız PM’in onayladığı hedef dal/SHA’dan `[PM’İN SEÇTİĞİ SÜRÜM]` paketini üret; BUILD/manifest/sürüm notu/ZIP adı tek değeri taşısın. | GÖREV #37 · `v1.3.0` hedefi; `docs/v3/V3-YOL-HARITASI.md` · §14; `acik-sorular.md` · §13 |
| P.2 | K100 ile ZIP’i geçici dizine açıp dosyaları gerçekten kullan: kataloglar ve sözlükler yüklenir, sürüm notu madde üretir, sistem durumu sağlıklıdır, mevcut kurulum üstünde doğru migration bekler/uygulanır. | `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · K100; `origin/v3-faz1:bin/release.php` · `paketCalistirmaDenetimi`, `yeniMigrationBekliyorDenetimi` |
| P.3 | K100’ün D8 `0036` bekleme senaryosunu koru; V3-C `0037…0040` migration’larının fazladan bekleyen/atlanmış görünmediğini yeni yükseltme vakasıyla kanıtla. | `origin/v3-faz1:bin/release.php` · `yeniMigrationBekliyorDenetimi`; GÖREV #37 · yeniden numaralama |
| P.4 | K105 bekçilerini paket kaynak ağacında çalıştır: standart belge, defter sütunu/değerleri, yeni V3-C ekranlarında P-borcu 0 ve PM’in seçtiği bileşen kaynak taraması. | `origin/v3-faz1:docs/v3/k105-mikro-etkilesim-standardi.md` · §3–4; `origin/v3-faz1:tests/Support/K105DefterBekcisiTest.php`; `acik-sorular.md` · §9 |
| P.5 | Doğrulama kırmızıysa ZIP’i teslim etme; sil ve tek raporda kırmızı nedeni göster. Canlıya yükleme/kurulum yapma. | `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · K100, K101 |

## KOŞULLAR

1. Nihai PM emri tek parça yürütülür; yukarıdaki PM kararları iş başlamadan metnin içine işlenir. Ara onay bekleme, fakat kaynak/izin/korumalı dal engelini de aşma.  
   **Kaynak:** GÖREV #37 · BAĞLAM; `acik-sorular.md`.
2. Yalnız kayıtlı kaynaklardan çalış. Bu taslakta adı olmayan yeni ekran, alan, tablo, rol, durum, bildirim, test vakası veya migration üretme.  
   **Kaynak:** GÖREV #37 · KOŞULLAR; `docs/v3/V3-YOL-HARITASI.md` · §17.
3. PM karar boşluğunu kod tercihiyle kapatma. “En mantıklısı” karar değildir.  
   **Kaynak:** GÖREV #37 · BAĞLAM; `celiski-raporu.md`.
4. Prototipler görsel/akış referansıdır; statik demo tekniği üretim güvenliği veya K105 yerine geçmez.  
   **Kaynak:** `gorev-30/OKUBENI.md` · “Bilinçli sapmalar”; K105 · §3.
5. Para hesaplarında float kullanma; para/kur hassasiyeti mevcut K14/K24 hattında kalır.  
   **Kaynak:** V3-C `migrations/0036_firmalar_ve_turlar.php` · `quoteResponses`; `NihaiGonderimKapisi.php` · `pozitifSayi` açıklaması.
6. `K51`, `K58`, `K62`, `K82`, `K100`, `K103`, `K104`, `K105`, `K106` kapılarını zayıflatma. K107 kaydı gelmeden K107 uyumu beyan etme.  
   **Kaynak:** `docs/08-risk-ve-karar-kaydi.md` · ilgili K satırları; `hazirlik-envanteri.md` · K107.
7. Kullanıcı yüzünde karışık dil üretme; firma görünümüne Paylaş koyma.  
   **Kaynak:** `portal-ekran-sartnameleri.md` · §1–2, §12; GÖREV #37 · GÖREVLER/1.

## RAPOR FORMATI

Tek bir Markdown kapanış raporu ver. Şu sırayı kullan:

1. Sonuç: GEÇTİ/KALDI ve tek cümle gerekçe.
2. Başlangıç/son SHA’ları ve gerçek merge zinciri.
3. Değişen dosyalar; özellikle migration eski→yeni adları.
4. Karar kapanışları: her **PM KARARI GEREKLİ** maddesinde nihai PM hükmüne atıf.
5. Blok 0/B/C/D/E/F/G teslim özeti; her satırda kaynak ve test kanıtı.
6. Kabul tablosu: K.1…K.13 için komut, vaka sayısı, sonuç ve kanıt yolu.
7. Sızıntı/dil/YA/Excel/K105/K100 sayımları.
8. PR numarası/URL’si, hedef dal ve zorunlu kontrol sonuçları.
9. Paket adı, SHA-256, dosya sayısı, manifest sonucu ve K100 çalıştırma çıktısı.
10. Sapmalar, eksik kaynaklar, ertelenenler ve kalan riskler; “yok” ise açıkça `0`.

**Kaynak:** GÖREV #37 · RAPOR FORMATI, TESLİM ve KABUL KAPISI; `docs/v3/V3-YOL-HARITASI.md` · §17 “Sapma disiplini”, “Sürüm notları”, “Migration”.

## YAPMA

- PM kararını varsayma; eksik K107/#36/gün emri/sertleştirme ref’i için içerik uydurma.  
  **Kaynak:** GÖREV #37 · KOŞULLAR; `hazirlik-envanteri.md`.
- `main`, `v3-faz1` veya başka korumalı dala doğrudan push yapma; force-push/rewrite ile geçmişi gizleme.  
  **Kaynak:** `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · 1 Eyl 2026 süreç notu.
- D8’i yeniden numaralama, silme veya düz anahtar saklamaya geri dönme.  
  **Kaynak:** D8 migration; K62; GÖREV #37 Blok 0.
- Eski `lists.share_*` kolonlarını bu kapanışta silme veya yeniden uygulama kaynağı yapma.  
  **Kaynak:** K103; `migrations/0038_paylasim_gocu.php` · başlık.
- Asıl RFQ satırını alternatifle ezme; boş/sıfır/bakılıyor değerini `Bulunamadı` yapma; kademe arası fiyat türetme.  
  **Kaynak:** `gorev-28/...saha-gercekleri.md` · §7–11.
- İç fiyat, maliyet, kâr, başka firma/tur veya rol metadata’sını firma çıktısına/sayfasına/cache’e koyma.  
  **Kaynak:** #34-R matris/SZ; `teklif-turu-durum-makinesi.md` · §5–6; K58.
- Onaylı #30-R HTML/OKUBENİ’yi üretim bağını kolaylaştırmak için değiştirme.  
  **Kaynak:** `gorev-30/OKUBENI.md` · “İşlevsel değişmezlik”; `gorev-30-ek/prototip-baglama-haritasi.md` · “Kapsam ve değişmezlik”.
- Test fixture’ını uygulamanın yanlış sonucuna uydurma; kırmızı testi atlama; eksik kabul kaynağına “geçti” yazma.  
  **Kaynak:** `yapistir-ayristir-altin-seti.json`; K100; GÖREV #37 kabul kapısı.
- Doğrulanmamış paketi canlıya taşıma veya kurma.  
  **Kaynak:** `origin/v3-faz1:docs/08-risk-ve-karar-kaydi.md` · K101.

## TEK RAPORLA KAPAT

Bloklar arasında ara rapor veya ara onay isteme. Bütün görevleri, kabul sınavlarını, PR’ı ve paketi yukarıdaki tek kapanış raporunda birleştir. Herhangi bir zorunlu kapı kırmızıysa sonuç **KALDI** olur; eksik işi “kısmen geçti” diye paketleme.

**Kaynak:** GÖREV #37 · BAĞLAM, RAPOR FORMATI ve KABUL KAPISI; K100–K101.
