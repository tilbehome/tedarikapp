# V3-C kapanış hazırlık envanteri

**Statü:** TASLAK — PM onayı yok  
**Kesit:** 2026-09-04 UTC  
**Repo:** `tilbehome/tedarikapp`  
**İncelenen ref’ler:** `origin/main`, `origin/v3-faz1`, `origin/is-emri-v3c` ve uzak dal listesi.  
**Durum dili:** “Repoda” erişilen ref’te gerçek dosya olduğunu; “pakette” önceki teslim ZIP’inden okunduğunu; “eksik” repo ve Library taramasında bulunamadığını söyler.

| No | Yol / kayıt | Durum | Hangi bloğun girdisi | Not |
|---:|---|---|---|---|
| 1 | GÖREV #37 kullanıcı kaydı | Mevcut — konuşma kaydı, repo dosyası değil | 0–G, kabul, PR, paket | Kapanış sırası, üç #30-EK kararı, yeniden numaralama ve teslim kapıları burada kayıtlı |
| 2 | `docs/v3/hazirlik/v3-c/OKUBENI.md` | Repoda — üç incelenen ref’te | 0, D, kabul | #28’i bağlayıcı şartname girdisi yapıyor |
| 3 | `docs/v3/hazirlik/v3-c/portal-ekran-sartnameleri.md` | Repoda — `is-emri-v3c` | B, C, kabul | #15; 7 ekran, dil, mobil, güvenlik, nihai kapı |
| 4 | `docs/v3/hazirlik/v3-c/rfq-alan-sozlesmesi.json` | Repoda — `is-emri-v3c` | B, C, D, E | RFQ v1.0.0; v2/v2.1 ile çelişiyor |
| 5 | `docs/v3/hazirlik/v3-c/teklif-turu-durum-makinesi.md` | Repoda — `is-emri-v3c` | B, C, kabul | 10 durum ve 15 numaralı geçiş sözleşmesi |
| 6 | `docs/v3/hazirlik/v3-c/excel-gelgit-spec.md` | Repoda — `is-emri-v3c` | E, kabul | V1 RFQ bağımlılığı güncellenmeli |
| 7 | `docs/v3/hazirlik/v3-c/yapistir-ayristir-altin-seti.json` | Repoda — `is-emri-v3c` | D, kabul | YA-001…YA-030 mevcut |
| 8 | `docs/v3/hazirlik/v3-c/firma-mesaj-kaliplari.md` | Repoda — `is-emri-v3c` | B, C | Link/anahtar ayrı gönderim ve tur mesajları |
| 9 | `docs/v3/hazirlik/v3-c/portal-metinleri.json` | Repoda — `is-emri-v3c` | C, kabul | 111 anahtar; #30-EK 149 kanonuna göre eski |
| 10 | `docs/v3/hazirlik/v3-c/gorev-28/28-v3c-firma-dongusu-saha-gercekleri.md` | Repoda — `is-emri-v3c` | B, C, D, E, kabul | 4 yasak + 10 sınır + 14 telafi |
| 11 | `docs/v3/hazirlik/v3-c/gorev-28/28-ek-donus-formatlari.md` | Repoda — `is-emri-v3c` | D, E, kabul | 16 ayrıştırma numunesi |
| 12 | `docs/v3/hazirlik/v3-c/gorev-30/firma-portali-prototip.html` | Önceki teslim paketinde; repoda eksik | C, kabul | #30-R onaylı 7 ekran prototipi; kanonik repo konumu bekleniyor |
| 13 | `docs/v3/hazirlik/v3-c/gorev-30/OKUBENI.md` | Önceki teslim paketinde; repoda eksik | 0, C, kabul | Prototip kapsamı, sapmalar, değişmezlik |
| 14 | `docs/v3/hazirlik/v3-c/gorev-30-ek/portal-metinleri.json` | Önceki teslim paketinde; repoda eksik | 0, C, kabul | 149 portal anahtarı |
| 15 | `docs/v3/hazirlik/v3-c/gorev-30-ek/rfq-alan-sozlesmesi-v2.json` | Önceki teslim paketinde; repoda eksik | 0, B, C, D, E | v2.0.0; v2.1 termin alanları ve temizlik henüz yok |
| 16 | `docs/v3/hazirlik/v3-c/gorev-30-ek/prototip-baglama-haritasi.md` | Önceki teslim paketinde; repoda eksik | 0, B, C | 149 anahtar, alternatif ve VIEWED üretim bağı |
| 17 | `docs/v3/hazirlik/v3-c/gorev-30-ek/5b-ek-status-viewed.md` | Önceki teslim paketinde; repoda eksik | 0, B, C, kabul | 5B’ye bir satır; toplamı 186 yapıyor |
| 18 | `docs/v3/hazirlik/v3-c/gorev-30-ek/teslim-raporu.md` | Önceki teslim paketinde; repoda eksik | 0, C, kabul | Sayımlar ve eski ambalaj açık sorusu |
| 19 | `docs/v3/hazirlik/v3-c/gorev-36/` | Eksik | 0, kabul | GÖREV #37 “gelirse” diyor; repo/Library eşleşmesi yok |
| 20 | `docs/v3/hazirlik/v3-c/gorev-36/e2e-katalogu.*` | Eksik | Kabul | Dosya adı kaynakta kesin verilmemiş; içerik uydurulamaz |
| 21 | `docs/v3/hazirlik/v3-c/gorev-36/KT-C*` | Eksik | Kabul | Kayıtlı kabul turu yok |
| 22 | `docs/v3/hazirlik/v3-c/gorev-36/fixtures/*.xlsx` | Eksik | E, kabul | Beklenen 10–15 gerçek fikstür bulunamadı |
| 23 | `docs/v3/hazirlik/cikti-terimleri.json` | Repoda — `is-emri-v3c` | B–F, kabul | 185 terim; `status.viewed` henüz yok |
| 24 | `docs/v3/hazirlik/k105/` | Eksik | 0, B–F, kabul, paket | GÖREV #37’de verilen yol mevcut değil |
| 25 | `docs/v3/k105-mikro-etkilesim-standardi.md` | Yalnız `origin/v3-faz1`de | 0, B–F, kabul, paket | Gerçek K105 kanonik dosyası; iş dalına merge ile gelmeli |
| 26 | `tests/Support/K105DefterBekcisiTest.php` | Yalnız `origin/v3-faz1`de | Kabul, paket | K105 defter alanı/değerleri bekçisi |
| 27 | `tests/Support/K105BilesenBekcisiTest.php` | Eksik | Kabul, paket | K105 belgesi bu dosya adını verir; dalda yok |
| 28 | `docs/v3/hazirlik/e2e-kapsam-defteri.json` | Repoda; K105 sütunlu sürüm `origin/v3-faz1`de | Kabul, paket | Mevcut senaryolar P-borcu; yeni V3-C satırları eklenmemiş |
| 29 | `docs/v3/hazirlik/v3-n/gorev-34/rol-gorunurluk-matrisi.json` | Önceki `GOREV-34-TESLIM.zip` paketinde; repoda eksik | B, C, F, kabul | 34-R-v2; 113 alan × 6 rol; `durum:oneri` |
| 30 | `docs/v3/hazirlik/v3-n/gorev-34/sizinti-test-seti.json` | Önceki pakette; repoda eksik | C, F, kabul | 273 negatif vaka |
| 31 | `docs/v3/hazirlik/v3-n/gorev-34/OKUBENI.md` | Önceki pakette; repoda eksik | 0, B, C, F | Eski kaynak-link cümlesi 34-R ile çelişiyor |
| 32 | `docs/v3/hazirlik/v3-n/gorev-34/34-R-TESLIM-RAPORU.md` | Önceki pakette; repoda eksik | 0, B, C, F, kabul | 16 değişen / 662 değişmeyen hücre kanıtı |
| 33 | `docs/v3/hazirlik/v3-n/gorev-34/34-TESLIM-RAPORU.md` | Önceki pakette; repoda eksik | Çelişki tarihçesi | R öncesi sayımlar |
| 34 | `docs/v3/hazirlik/v3-n/gorev-34/portal-metinleri-musteri-en.json` | Önceki pakette; repoda eksik | V3-N; V3-C’ye doğrudan görev değil | 34-R’de değişmeden korunmuş |
| 35 | `docs/v3/hazirlik/v3-n/gorev-34/portal-metinleri-musteri-zh.json` | Önceki pakette; repoda eksik | V3-N; V3-C’ye doğrudan görev değil | 34-R’de değişmeden korunmuş |
| 36 | `docs/08-risk-ve-karar-kaydi.md` | Repoda — `is-emri-v3c` | 0–G, kabul, paket | K51/K58/K62/K82/K100/K103/K104 var |
| 37 | `docs/08-risk-ve-karar-kaydi.md` | `origin/v3-faz1`de daha yeni sürüm | 0–G, kabul, paket | K105/K106 ve sertleştirme süreç notu ekli |
| 38 | K107 satırı | Eksik | 0, kabul, paket | Üç ref ve önceki paketlerde eşleşme yok; içerik uydurulamaz |
| 39 | `docs/v3/hazirlik/ie22-on-analiz.md` | Repoda | B, C, F, kabul | K82’nin ayrıntılı kaynağı |
| 40 | `docs/v3/V3-YOL-HARITASI.md` | Repoda — `is-emri-v3c` | B–F, kabul, sürüm | §7.4, §7.6, §14, §16, §17 bağlayıcı girdiler |
| 41 | Bağımsız `tedarikapp gün emri` dosyası | Eksik | B, D, E, F, kabul | GÖREV #37 yalnız kapsam özetini aktarıyor |
| 42 | `docs/v3/tasarim-referans/listeler.png` | Repoda — `is-emri-v3c` | F, kabul | Listeler merkezi referansı |
| 43 | `docs/v3/tasarim-referans/OKUBENI.md` | Repoda — `is-emri-v3c` | F | `listeler.png` ekran bağını kaydediyor |
| 44 | Library `TedarikApp Listeler komuta merkezi.png` | Library’de bulundu; repo dosyasıyla aynı SHA-256 | F | Ayrı kanon değil; repo görselini doğruluyor |
| 45 | `migrations/0036_firmalar_ve_turlar.php` | Repoda — yalnız `origin/is-emri-v3c` | 0, B, D, E | V3-C Blok A; 0037’ye taşınacak; RFQ v1 gömülü |
| 46 | `migrations/0037_paylasim_tablosu.php` | Repoda — yalnız `origin/is-emri-v3c` | 0, B, C | 0038’e taşınacak; `recipient_type=importer`, `key_plain` 12 |
| 47 | `migrations/0038_paylasim_gocu.php` | Repoda — yalnız `origin/is-emri-v3c` | 0, B | 0039’a taşınacak; K103 kopya göçü |
| 48 | `migrations/0039_belgeler_ve_sablonlar.php` | Repoda — yalnız `origin/is-emri-v3c` | 0, F | 0040’a taşınacak; belgeler ve liste şablonları |
| 49 | `migrations/0036_paylasim_anahtari_sifreli_alan.php` | Yalnız `origin/v3-faz1`de | 0, B, C, paket | D8; numarası korunacak |
| 50 | `app/Models/ShareRepository.php` | Yalnız `origin/is-emri-v3c`de | 0, B, C, F | K103 yolu; D8 ile düz/şifreli saklama çelişkisi var |
| 51 | `app/Services/Share/ShareKeyService.php` | İki dalda farklı sürümler | 0, B, C | D8 şifreleme ile V3-C `shares` uyarlanmalı |
| 52 | `app/Services/Tur/TurDurumMakinesi.php` | Yalnız `origin/is-emri-v3c`de | B, kabul | 10 durum; belge dışı kenarlar ve eski VIEWED anahtarı var |
| 53 | `app/Services/Tur/NihaiGonderimKapisi.php` | Yalnız `origin/is-emri-v3c`de | B, C, kabul | V1 alternatif durumuna bağlı saf doğrulayıcı |
| 54 | `tests/Services/TurDurumMakinesiTest.php` | Yalnız `origin/is-emri-v3c`de | B, kabul | Durum/rol testleri; 15 iş olayı tam kalıcılık testi değil |
| 55 | `tests/Services/NihaiGonderimKapisiTest.php` | Yalnız `origin/is-emri-v3c`de | B, C, kabul | Sekiz koşulun çoğu saf veriyle sınanıyor; v2.1’e güncellenmeli |
| 56 | `tests/Services/TurRevizyonKilidiTest.php` | Yalnız `origin/is-emri-v3c`de | B, kabul | Şema kilidi/kur kopyası için mevcut kanıt |
| 57 | `tests/Support/PaylasimKolonuBekcisiTest.php` | Yalnız `origin/is-emri-v3c`de | B, kabul | K103 eski kolon başvurusu kapısı |
| 58 | `tests/Http/PaylasimGocuTest.php` | Yalnız `origin/is-emri-v3c`de | B, kabul | K103 göç idempotency/geriye uyum kanıtı |
| 59 | `bin/release.php` | Repoda; K100+D8 bekleme senaryosu `origin/v3-faz1`de | Paket | V3-C 0037–0040 yükseltme senaryosu eklenmeli |
| 60 | `docs/surum-notlari/1.2.1.md` | Yalnız `origin/v3-faz1`de | 0, paket | Merge zinciriyle gelmeli; yeni sürüm notunun tabanı |
| 61 | `sertlestirme-v1-2-2` uzak dalı | Eksik | 0, kabul, paket | Uzak ref listesinde yok |
| 62 | `origin/v3-faz1` içindeki v1.2.1 sertleştirme geçmişi | Repoda | 0, kabul, paket | PR #61/#62, D8, K105, K106 bulunan en yakın kayıt; yetkili ikame olup olmadığı PM kararı |
| 63 | `dis-denetim-3.md` | Eksik | Kabul / dış denetim | GÖREV #37 örneğinde adı geçen dosya bulunmadı |
| 64 | `docs/denetim/dis-denetim-2026-08-26.md` | Repoda | Tarihçe; doğrudan V3-C görevi değil | Eksik `dis-denetim-3.md` yerine kendiliğinden kanon sayılamaz |
| 65 | `docs/denetim/dis-denetim-2026-08-26-triyaj.md` | Repoda | Tarihçe; doğrudan V3-C görevi değil | Aynı sınır |

## Dal fotoğrafı

- `origin/main`: `08c2e3f128bbb996124f2733093dacf7d0124f95`
- `origin/v3-faz1`: `be9f487efa8fe243fc743fccaaf20b979624f378`
- `origin/is-emri-v3c`: `ba59abf3f407310a1feb43eb089e361fa9f24f39`
- `main...v3-faz1`: iki yönde de benzersiz commit var (`9 / 40`).
- `v3-faz1...is-emri-v3c`: iki yönde de benzersiz commit var (`21 / 4`).
- `sertlestirme-v1-2-2`: uzak ref yok.

Bu fotoğraf yalnız inceleme anını kaydeder; nihai iş emri çalıştırılırken ref’ler yeniden okunmalıdır.
