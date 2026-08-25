# TedarikApp v1.0 — Manuel Kabul Turu Kontrol Listesi

**Tur hedefi:** Dilim 4 sonunda v1.0 ilan kararı  
**Test verisi:** Görev #5A `demo-urun-seti.json`, 100 kurgusal ürün (`DM-001`–`DM-100`)  
**Tahmini toplam süre:** **85 dakika**  
**Kapsam:** Keşif, Gelen Kutusu, Liste Detay, Çıktılar, Ayarlar ve paketlenmiş Eklenti  
**Sonuç kodları:** `GEÇTİ` / `KALDI` / `NOT: …`

> Bu tur yalnız demo/test ortamında yapılır. Gerçek 1688 ilanı, gerçek firma/kişi, gerçek oturum verisi veya canlı üretim kaydı kullanılmaz.

## 1. Tur öncesi hazırlık

- Görev #5A demo setini temiz test alanına içe aktar; `DM-001`–`DM-100` tam 100 kayıt görünmeli.
- Yeni ve boş bir `KT-v1.0 Kabul Listesi` oluştur.
- Test kullanıcısının TR, EN ve ZH çıktı alabilecek; ayarlar, kur ve sözlük alanlarını yönetebilecek yetkisi olsun.
- Tarayıcı önbelleği dışındaki uygulama verilerini temiz test başlangıç durumuna getir.
- Excel/PDF görüntüleyici ve üç paylaşım dilini kontrol edebilecek paylaşım önizlemesi hazır olsun.
- Paketlenmiş uzantı kontrolleri için `eklenti-e2e-senaryo-katalogu.md` ve sekiz sanitize parser fikstürünün son test sonucu hazır olsun.

## 2. Demo referans matrisi

| Test amacı | Demo ürün(ler) |
|---|---|
| Altı aynı ürün/alternatif satıcı kümesi | DM-001/004/012; DM-016/023/025; DM-034/038/044; DM-048/053/057; DM-049/055/059; DM-060/064/068 |
| Kontrollü eksik fiyat kademesi | DM-007, DM-036, DM-064, DM-078 |
| Kontrollü eksik CBM | DM-015, DM-043, DM-071, DM-085 |
| Kontrollü eksik video bilgisi | DM-022, DM-050 |
| Kontrollü eksik metrik | DM-029, DM-057 |
| Yüksek satış + düşük puan | DM-005, DM-010, DM-020, DM-030, DM-050, DM-060, DM-080, DM-090, DM-100 |
| Yeni + ivmeli | DM-001, DM-011, DM-016, DM-026, DM-041, DM-056, DM-076, DM-086, DM-096 |
| Eski + ölü/düşük hareket | DM-002, DM-007, DM-012, DM-017, DM-027, DM-037, DM-042, DM-047, DM-052, DM-062, DM-067, DM-072, DM-077, DM-082, DM-087, DM-092, DM-097 |
| Özel üretim / yüksek MOQ örnekleri | DM-011, DM-024, DM-037, DM-050, DM-063, DM-076, DM-089, DM-098 |
| Çok varyant uçları | DM-023 (14), DM-050 (20), DM-059 (22), DM-068 (24), DM-077 (28), DM-086 (32) |
| Alan dışı genelleme kontrolü | DM-091–DM-100 |

## 3. Keşif ekranı — 28 dakika

| Kimlik | Önem | Yapılacak eylem | Beklenen sonuç | Demo ürün | Süre | Sonuç |
|---|---|---|---|---|---:|---|
| KT-001 | KRİTİK | Keşif'i aç; kimlik aralığını ve toplamı kontrol et. | Tekilleştirilmiş `DM-001`–`DM-100` kayıtları ve toplam 100 görünür; yinelenen/kayıp kimlik yoktur. | DM-001, DM-100 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-002 | KRİTİK | Kategori filtresinde `Ev Tekstili`ni seç, sonra temizle. | Yalnız ilgili kategori görünür; temizlemede 100 ürün geri gelir ve aktif filtre çipi kalkar. | DM-001, DM-015 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-003 | MAJOR | `Alan Dışı > Genelleme Testi` filtresini uygula. | Tam 10 alan dışı kayıt görünür; DM-090 görünmez, DM-091–DM-100 görünür. | DM-090, DM-091, DM-100 | 1 dk | GEÇTİ / KALDI / NOT |
| KT-004 | KRİTİK | `Videolu` filtresini aç; sonra `varyantsız` filtreyle ayrı ayrı sınama yap. | Videolu filtre 15, varyantsız filtre 30 ürünü verir; filtreler ürün ayrıntısıyla tutarlıdır. | DM-008; DM-003 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-005 | KRİTİK | `Özel üretim/yüksek MOQ` filtresini uygula. | Sekiz kontrollü ürün görünür; normal ürün karışmaz. MOQ birimi ve değer kaybolmaz. | DM-011, DM-024, DM-050, DM-098 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-006 | KRİTİK | `Eksik bilgi` filtresini uygula; eksik türlerini aç. | 12 ürün görünür; fiyat kademesi, CBM, video ve metrik eksikleri doğru alt türde sayılır. | DM-007, DM-015, DM-022, DM-029 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-007 | KRİTİK | Skoru azalan sıraya getir; yüksek satış+düşük puan profilini başka profil ile karşılaştır. | Görsel sıralama gerçek skor değerine uyar; salt satış adedi DM-005'i yanlışlıkla “en iyi” yapmaz; eşitlik kuralı kararlıdır. | DM-005, DM-003 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-008 | KRİTİK | `Yeni/ivmeli` profil filtresi veya ilgili skor görünümünü aç. | Yeni tarih ve satış ivmesi birlikte değerlendirilir; yeni ürün, toplam satış düşük diye kaybolmaz. | DM-001, DM-011, DM-016 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-009 | KRİTİK | `Eski/ölü` profilini aç ve yüksek satışlı profille karşılaştır. | Düşük güncel hareket/tazelik görünür; eski ve düşük hareketli ürünler güçlü/ivmeli etiketi almaz. | DM-002, DM-027, DM-072 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-010 | MAJOR | Düşük hacim + yüksek puan ile oturmuş güçlü profili karşılaştır. | Puan, yorum/satış hacmi ve tazelik ayrı sinyaller olarak görünür; profil etiketleri birbirine dönüşmez. | DM-009, DM-013 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-011 | KRİTİK | Altı küme kartını tek tek aç; her kartın üyelerini say. | Altı kartın her birinde tam üç doğru üye vardır; fiyat, satıcı, karne ve metrik farkları karşılaştırılabilir. | Altı kümenin 18 üyesi | 3 dk | GEÇTİ / KALDI / NOT |
| KT-012 | MAJOR | Küme kartında “en uygun”/önerilen tedarikçi işaretinin açıklamasını aç. | Öneri gerekçesi görünür ve verisi eksik üyeyi kesin kazanan yapmaz; DM-057 metrik eksikliği açıkça hesaba katılır. | DM-048, DM-053, DM-057 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-013 | KRİTİK | TR sorgusu `yüksek borosilikat cam yağlık 550ml`, ardından EN sorgusu `high borosilicate glass oil dispenser 550ml` ile ara. | İki sorgu da aynı yağlık ürünlerini bulur; sorgu dili sonuç içeriğini bozmaz. | DM-016, DM-023, DM-025, DM-033 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-014 | KRİTİK | ZH/orijinal sorgu `高硼硅玻璃油壶 550ml` ve TR sorgu `şeffaf çekmeceli ayakkabı kutusu` ile ara. | İlk sorgu yağlıkları, ikinci sorgu ayakkabı kutusu kümesini bulur; Unicode, Türkçe karakter ve ölçü işaretleri korunur. | DM-016/023/025/033; DM-060/064/068 | 2 dk | GEÇTİ / KALDI / NOT |

## 4. Gelen Kutusu — 13 dakika

| Kimlik | Önem | Yapılacak eylem | Beklenen sonuç | Demo ürün | Süre | Sonuç |
|---|---|---|---|---|---:|---|
| KT-015 | KRİTİK | Gelen Kutusu'nu deste modunda aç; en üst kartı seçmeden ekranı kontrol et. | Deste sayacı, mevcut kart, sonraki kart ve kullanılabilir eylemler doğru görünür; hiçbir ürün kendiliğinden kabul/ret olmaz. | DM-001, DM-002 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-016 | KRİTİK | DM-001'i kabul et/aktif listeye ekle. | DM-001 desteden çıkar, sayaç bir azalır, bir sonraki kart gelir ve listeye tek kayıt eklenir. | DM-001 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-017 | KRİTİK | DM-002 üzerinde bir kural eylemi uygula; görünen kural rozetini kontrol et. | Uygulanan kuralın adı/sonucu ürün üzerinde rozet olarak görünür; eylemin kaynağı gizlenmez. | DM-002 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-018 | KRİTİK | Hemen `Geri al` yap. | DM-002 önceki konum ve durumuyla desteye döner; sayaç ve kural rozeti eski hâline gelir; çift kayıt oluşmaz. | DM-002 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-019 | KRİTİK | Eksik fiyat kademeli ürünü aç. | `price_tiers` eksikliği alan adı/anlaşılır mesajla görünür; sıfır fiyat gibi sunulmaz. | DM-007 | 1 dk | GEÇTİ / KALDI / NOT |
| KT-020 | KRİTİK | Eksik CBM ve eksik metrik ürünlerini art arda aç. | DM-015'te CBM; DM-029'da satış/puan metrikleri eksik gösterilir. Hesaplanan değer uydurulmaz ve kritik aksiyon uyarılır/kilitlenir. | DM-015, DM-029 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-021 | MAJOR | Eksik video işaretli özel üretim ürünü aç; deste eylemini tamamlayıp geri al. | `media.has_video` bilinmiyor olarak gösterilir, “video yok”a çevrilmez; özel üretim/MOQ bilgisi korunur ve geri alma çalışır. | DM-050 | 2 dk | GEÇTİ / KALDI / NOT |

## 5. Liste detay — 20 dakika

| Kimlik | Önem | Yapılacak eylem | Beklenen sonuç | Demo ürün | Süre | Sonuç |
|---|---|---|---|---|---:|---|
| KT-022 | KRİTİK | DM-001, DM-011 ve DM-015'i kabul listesine ekle; Liste Detay'ı aç. | Üç ürün tekil görünür; özet sayıları, aşama çubuğu ve ürün durumları kayıtlarla uyumludur. | DM-001, DM-011, DM-015 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-023 | KRİTİK | Aşama çubuğunda mevcut ve sonraki aşamaları incele; ürün durumunu ilerlet. | Yalnız izin verilen sonraki aşama aktif olur; tamamlanmamış koşullar atlanamaz; geri/ileri durumları tutarlı kaydedilir. | DM-001 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-024 | KRİTİK | Kur alanında kilitli durumdayken değeri doğrudan değiştirmeyi dene. | Kilitli kur düzenlenemez; miktar/tutarlar sessizce yeniden hesaplanmaz. | DM-001, DM-011 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-025 | KRİTİK | Ayarlar akışıyla kuru getir, kullanıcı onayından önce Liste Detay'a dön. | Yeni kur “bekleyen/onaylanmamış” kalır; liste maliyetlerine uygulanmaz. | DM-001, DM-011 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-026 | KRİTİK | Kuru onayla ve Liste Detay'ı yenile. | Onaylı kur, kaynak/tarih bilgisiyle kilitlenir; ilgili tutarlar bir kez ve doğru kurla hesaplanır. | DM-001, DM-011 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-027 | KRİTİK | `Eksik CBM` uyarı çipine tıkla. | Liste yalnız ilgili eksikliğe sahip ürüne filtrelenir; DM-015 görünür, DM-001/011 gizlenir; çip temizlenince tümü döner. | DM-015 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-028 | KRİTİK | DM-015'i HAZIR yapmayı dene. | Zorunlu CBM eksikliği açıkça listelenir; ürün HAZIR durumuna geçmez ve durum kaydı değişmez. | DM-015 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-029 | KRİTİK | Eksik fiyat kademeli DM-007'yi listeye ekleyip HAZIR yapmayı dene. | Fiyat kademesi eksikliği sıfır/değer varmış gibi geçmez; HAZIR kapısı eksik alanı bildirerek engeller. | DM-007 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-030 | KRİTİK | Tüm zorunlu alanları dolu DM-001'i HAZIR yap. | Kapı kontrolleri geçer, durum HAZIR olur, aşama/özet sayıları güncellenir ve denetim izi oluşur. | DM-001 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-031 | KRİTİK | Yeni boş liste oluşturup `Tamamla`/son aşamaya geçir eylemini dene. | Boş liste tamamlanamaz; anlaşılır engel görünür; tamamlandı kaydı, belge veya paylaşım oluşmaz. | Referans: DM-001 eklenmeden | 2 dk | GEÇTİ / KALDI / NOT |

## 6. Çıktılar — 14 dakika

| Kimlik | Önem | Yapılacak eylem | Beklenen sonuç | Demo ürün | Süre | Sonuç |
|---|---|---|---|---|---:|---|
| KT-032 | KRİTİK | DM-001, DM-011, DM-016, DM-023, DM-029 ve DM-060 ile TR Excel üret. | Dosya açılır; 6 ürün tekil satırdır; başlık, sütun, durum, finans özeti, uyarı ve dipnotların tamamı TR'dir; ZH yalnız açıkça “orijinal” alanlarda kalır. | Belirtilen 6 ürün | 2 dk | GEÇTİ / KALDI / NOT |
| KT-033 | KRİTİK | Aynı listeyi EN Excel üret. | Veri ve formüller TR çıktıyla eşdeğerdir; arayüz/başlık/durum/dipnotların tamamı EN'dir; tek bir TR sistem etiketi kalmaz. | Aynı 6 ürün | 2 dk | GEÇTİ / KALDI / NOT |
| KT-034 | KRİTİK | Aynı listeyi ZH Excel üret. | Başlık, sütun, durum, finans özeti, uyarı ve dipnotların tamamı ZH'dir; TR/EN sistem etiketi kalmaz; kod ve sayılar bozulmaz. | Aynı 6 ürün | 2 dk | GEÇTİ / KALDI / NOT |
| KT-035 | KRİTİK | Referans çıktı şablonundaki `K55 orijinal satır` kontrolünü üç Excel'de yap. | K55 olarak tanımlanan orijinal ürün satırı aynen korunur; kaynak Çince değer, ölçü/model ve ürün kimliği çevrilerek veya biçimlenerek bozulmaz. `[DOĞRULA: uygulamadaki K55 referansının kesin hücre/satır tanımı]` | DM-016 (`高硼硅玻璃油壶 550ml`) | 2 dk | GEÇTİ / KALDI / NOT |
| KT-036 | KRİTİK | TR, EN ve ZH PDF'leri sırayla üret; sayfaları görsel kontrol et. | Üç PDF açılır; seçilen dilde komple içerik vardır; karışık sistem dili, kesilen sütun, taşan başlık, bozuk Çince glif veya eksik ürün yoktur. | DM-001, DM-011, DM-029 | 3 dk | GEÇTİ / KALDI / NOT |
| KT-037 | KRİTİK | TR, EN ve ZH paylaşım önizlemelerini sırayla oluştur. | Her önizlemenin konu/başlık, mesaj, özet, ürün alanları, durum ve dipnotları seçilen tek dildedir; paylaşım bağlantısı doğru liste/sürümü açar. | DM-016, DM-023, DM-025 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-038 | KRİTİK | Eksik metrikli ürünü Excel/PDF/paylaşımda kontrol et. | Eksik metrik boş/“mevcut değil” olarak seçilen dilde açıklanır; `0`, sahte skor veya hesaplanmış satış değeri üretilmez. | DM-029 | 1 dk | GEÇTİ / KALDI / NOT |

## 7. Ayarlar — 8 dakika

| Kimlik | Önem | Yapılacak eylem | Beklenen sonuç | Demo ürün | Süre | Sonuç |
|---|---|---|---|---|---:|---|
| KT-039 | KRİTİK | Çeviri bağlantı testini geçerli yapılandırmayla çalıştır. | Başarı, sağlayıcı/model ve test zamanı anlaşılır görünür; test ürün verisini kaydetmez veya listeye eklemez. | Metin örneği: DM-016 başlığı | 2 dk | GEÇTİ / KALDI / NOT |
| KT-040 | KRİTİK | Bağlantı testini geçersiz/eksik yapılandırmayla çalıştır. | Güvenli ve anlaşılır hata görünür; gizli anahtar/token ekrana veya loga dökülmez; önceki geçerli ayar sessizce silinmez. | Metin örneği: DM-016 başlığı | 1 dk | GEÇTİ / KALDI / NOT |
| KT-041 | KRİTİK | `Kur getir` eylemini çalıştır; gelen kuru onaylamadan çık. | Kur, kaynak ve zamanla önizlenir; kullanıcı onayı olmadan aktif kur olmaz ve liste hesabı değişmez. | DM-001, DM-011 | 2 dk | GEÇTİ / KALDI / NOT |
| KT-042 | KRİTİK | Bekleyen kuru onayla; kilitli kuru tekrar değiştirmeyi dene. | Onayla birlikte tek aktif sürüm oluşur; kilitli değer doğrudan düzenlenemez; yeni değişiklik yeni getir/onay döngüsü ister. | DM-001, DM-011 | 1 dk | GEÇTİ / KALDI / NOT |
| KT-043 | KRİTİK | Sözlük CSV dışa aktar; bir geçerli satırı düzenleyip içe aktar, ardından hatalı CSV dene. | UTF-8/ZH karakterleri ve başlık şeması korunur; geçerli değişiklik önizleme/onayla uygulanır; hatalı dosya satır/sütun hatasıyla reddedilir ve mevcut sözlük bozulmaz. | Terim örneği: DM-016 başlığındaki `高硼硅玻璃` | 2 dk | GEÇTİ / KALDI / NOT |

## 8. Eklenti — 2 dakika

| Kimlik | Önem | Yapılacak eylem | Beklenen sonuç | Demo ürün / referans | Süre | Sonuç |
|---|---|---|---|---|---:|---|
| KT-044 | KRİTİK | Son otomasyon raporunda `eklenti-e2e-senaryo-katalogu.md` içindeki `E2E-EKL-01`–`E2E-EKL-25` ile sekiz sanitize parser fikstürünün sonuçlarını kontrol et; senaryoları burada tekrar koşma. | 25/25 E2E senaryosu ve 8/8 sanitize fikstürü geçmiştir; gerçek 1688 ağına çıkılmamış, özel MTop/cookie/token üretilmemiştir. Başarısız/atlanmış kritik test yoktur. | E2E kataloğu + 8 fikstür | 1 dk | GEÇTİ / KALDI / NOT |
| KT-045 | KRİTİK | Paketlenmiş v1.0 uzantının manuel smoke kayıtlarını kontrol et: unlisted paket, desteklenen host, ilk kullanım bildirimi, kullanıcı-tetiklemeli önizleme/gönderim ve panelde tek kayıt. | Paket sürümü ilan adayıyla aynıdır; kullanıcı eylemi olmadan okuma/gönderim yoktur; ilk bildirim görünür; başarılı gönderim panelde tek kayıt üretir ve token görünmez. Canlı isteğin kendisi bu danışman turunda yapılmaz; Ürün Sahibi'nin kontrollü kaydı kanıt olarak kullanılır. | Fikstür eşlemesi: tam ürün + DM-016 panel kaydı | 1 dk | GEÇTİ / KALDI / NOT |

## 9. Tur özeti

| Ekran / alan | Senaryo | Süre |
|---|---:|---:|
| Keşif | 14 | 28 dk |
| Gelen Kutusu | 7 | 13 dk |
| Liste Detay | 10 | 20 dk |
| Çıktılar | 7 | 14 dk |
| Ayarlar | 5 | 8 dk |
| Eklenti | 2 | 2 dk |
| **Toplam** | **45** | **85 dk** |

### Sonuç kayıt alanı

- **Tur tarihi:** `[GG.AA.YYYY]`
- **Aday sürüm / commit:** `[SÜRÜM]` / `[COMMIT]`
- **Test eden:** `[AD SOYAD]`
- **GEÇTİ:** `[SAYI]`
- **KALDI:** `[SAYI]`
- **NOT:** `[SAYI]`
- **Kanıt klasörü / bağlantısı:** `[KANIT-KONUMU]`
- **Karar:** `İLAN / İLAN YOK / DÜZELTME SONRASI TEKRAR TUR`

---

## 10. v1.0 İLAN ŞARTI

### Kritik — biri kalırsa ilan yok

Aşağıdaki gruplarda `KRİTİK` işaretli herhangi bir senaryo `KALDI` ise v1.0 ilan edilmez:

1. **Veri bütünlüğü ve seçim:** KT-001, KT-002, KT-004–009, KT-011, KT-013–014.
2. **Gelen Kutusu işlem güvenliği:** KT-015–020; kullanıcı eylemi, sayaç, kural rozeti, geri alma, eksik veri ve mükerrer kayıt bütünlüğü.
3. **Liste/aşama kapıları:** KT-022–031; özellikle kilitli kur, getir–onayla, uyarı çipi, eksik ürünün HAZIR olamaması ve boş listenin tamamlanamaması.
4. **Çıktı doğruluğu:** KT-032–038; üç dilde komple içerik, sıfır karışık sistem dili, K55 orijinal satır, eksik verinin uydurulmaması ve dosyaların açılması.
5. **Ayar ve gizli bilgi güvenliği:** KT-039–043; bağlantı testi, kur onayı, sözlük CSV atomikliği ve token/anahtar sızıntısının olmaması.
6. **Eklenti çekirdeği:** KT-044–045; E2E/fikstürlerin tam geçmesi, ilk kullanım bildirimi, yalnız kullanıcı tetiklemesi, tek panel kaydı ve ilan adayı sürüm eşleşmesi.

Kritik bir senaryo `NOT` ile geçilmiş sayılmaz. Açık kanıt yoksa sonuç `KALDI` yazılır. Düzeltmeden sonra yalnız başarısız satır değil, doğrudan ilişkili kritik grup yeniden koşulur.

### Not düşülüp geçilebilir

Yalnız aşağıdaki koşulların tamamını sağlayan küçük kusurlar `NOT` ile kaydedilip ilanı engellemeyebilir:

- Senaryo `MAJOR` işaretlidir veya kritik işlevin sonucunu değiştirmeyen salt görsel/metin kusurudur.
- Veri kaybı, yanlış hesap, yanlış ürün/satıcı seçimi, eksik uyarı, izin aşımı, gizli bilgi sızıntısı, dil karışması, bozuk çıktı, erişilemeyen temel eylem veya yanlış durum geçişi yoktur.
- Kullanıcı görevi güvenli biçimde tamamlayabiliyor; belgelenmiş basit bir geçici yol vardır.
- Notta ekran, adım, beklenen/gerçek sonuç, kanıt ve takip işi kimliği yazılmıştır.
- PM ve Ürün Sahibi notu açıkça kabul etmiştir.

Örnek not-düşülüp-geçilebilir kusurlar: küme kartındaki ikincil hizalama farkı, işlevi etkilemeyen boşluk/ikon sorunu veya anlamı değiştirmeyen küçük metin hatası. TR/EN/ZH karışması, yanlış skor/sıralama, eksik verinin sıfır gösterilmesi ve HAZIR/tamamlama kapısının aşılması hiçbir koşulda bu sınıfa girmez.


## KT-EK — Otomasyona girmeyen dört madde (PM kararı, 25 Ağu 2026)

Bu dördü `e2e-kapsam-defteri.json`da **bekliyor** kalır ve otomatik koşumda
aranmaz: ikisi GÖRSEL/dokunsal yargı, ikisi ekran düzeyinde Playwright turu
ister (altyapı İE#22'de). Kabul turunda ELLE denenir; sonuç bu belgeye yazılır.

| Kod | Karşılığı | Ekran | Nasıl denenir | Geçti sayılır |
|---|---|---|---|---|
| KT-EK-1 | E2E-PNL-11 | Keşif | Altı sütunlu matris 1440 ve 1024 genişlikte açılır; sütun başlıkları, hizalama ve dokunma hedefleri (≥44 px) gözle denetlenir | Yatay taşma yok, başlık-veri hizası bozulmuyor, dokunma hedefleri parmakla ıskalanmıyor |
| KT-EK-2 | E2E-PNL-12 | Keşif | Süzgeç + sıralama seçilir, görünüm kaydedilir; adres çubuğu kopyalanıp yeni sekmede açılır; sonra kayıtlı görünüm yeniden seçilir | Adres durumu ekrana, ekran durumu adrese birebir yansıyor; kayıtlı görünüm aynı sonucu getiriyor |
| KT-EK-3 | E2E-PNL-14 | Liste detay | 100+ ürünlü listede sayfa sonuna kadar kaydırılır, sonra başa dönülür | Hiçbir ürün iki kez görünmüyor, hiçbiri atlanmıyor, seçim kaydırmada kaybolmuyor |
| KT-EK-4 | E2E-PNL-45 | Gelen Kutusu / kilit ekranı | Tazeleme sayacı dolana kadar beklenir; tarayıcı ağ sekmesi izlenir | Sayaç dolduğunda TEK istek/yenileme oluyor, yığılma yok |

Bu dördü kritik gruplara dahildir: `NOT` ile geçilmeleri yalnız "Not düşülüp
geçilebilir" bölümündeki koşulların tamamı sağlanırsa mümkündür.
