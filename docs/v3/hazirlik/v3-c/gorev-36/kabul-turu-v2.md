# V3-C Birleşik Kabul Turu v2

**Kimlik aralığı:** `KT-C-001`–`KT-C-036`  
**Tek tur süresi:** **87 dakika**  
**Test sahibi:** Ürün Sahibi  
**Veri:** Yalnız Ürün Sahibinin seçtiği gerçek ürünler; demo veri kullanılmaz.

> Bir KIRMIZI kuralı görülürse ilgili madde ve bütün paket KALDI olarak işaretlenir: karışık dil, yanlış ürüne fiyat, alternatifin asıl satırı ezmesi, firma yüzünde iç/başka firma verisi.

## Tur öncesi

- V3-C + v1.2.2 tek paketi ve 12 Excel fikstürü hazırdır.
- Gerçek listede en az 5 ürün; en az bir found, bir not_found, bir MOQ/kademe ve bir medya kuyruğu örneği bulunur.
- PC ve telefon, PDF görüntüleyici ve Excel/LibreOffice hazırdır.
- v1.2.1’de zaten kabul edilmiş keşif, gelen kutusu, temel çıktı stili, genel ayarlar ve eklenti maddeleri **tekrar edilmez**. Bu tur yalnız aşağıdaki kapanış sınırıdır.

## Tek kabul akışı

| Kimlik | Ekran | Ne yapılır | Ne görülmeli | Süre | Geç/kal |
|---|---|---|---|---:|---|
| KT-C-001 | Kurulum sihirbazı — Başlangıç | Tek paketi aç; akıllı kurulumu seç. | Sürüm/kapsam V3-C + v1.2.2 olarak görünür; gerçek veriye dokunmadan ön tarama başlar. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-002 | Kurulum sihirbazı — Sistem taraması | PHP/DB/depolama/kuyruk/cron ön taramasını çalıştır. | Her kontrol açık sonuç ve telafi eylemi verir; ham sır/token görünmez. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-003 | Kurulum sihirbazı — Veritabanı kararı | Mevcut kurulumu bağla/yükseltme kararını incele; kuru çalıştır. | Mevcut veri hata sayılmaz; yedek ve kuru çalışma tamamlanmadan mutasyon yoktur. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-004 | Kurulum sihirbazı — Son doğrulama | Servis, oturum, depolama, kuyruk, cron ve geri alma provasını çalıştır. | Son doğrulama maddeleri tek tek geçer; kesinti/uyarı görünür ve tekrar denenebilir. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-005 | Kurulum sihirbazı — Tamamlandı | Kurulum raporunu indir; kurulum rotasını tekrar açmayı dene. | Rapor açılır; kurulum rotası kilitlidir; panel gerçek veriyi koruyarak açılır. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-006 | v1.2.2 — Yedek seti | Yedek kartından bir parça ve manifest indir; SHA’yı karşılaştır; “Doğrula”ya bas. | Parça/manifest aynı set ve SHA ile doğrulanır; doğrulama sonucu açıktır. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-007 | v1.2.2 — APP_KEY emaneti | Emanet erişimini aç; mevcut oturumla doğrudan görüntülemeyi dene, sonra şifreyi yeniden gir. | Şifre yeniden sorulmadan anahtar gösterilmez; başarıda kapsam açık, log/raporda anahtar yoktur. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-008 | v1.2.2 — KISMİ set | Bir yedek parçası eksik seti aç. | KISMİ rozeti görünür; tam/doğrulanmış gibi işaretlenmez; eksik parça adı bellidir. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-009 | v1.2.2 — Medya | Ana görseli kuyruktaki gerçek ürünü aç; kuyruk tamamlanana dek yenile. | Proxy geçici görünüm açıkça geçicidir; yerel/uzak rozeti gerçek kaynağa göre değişir. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-010 | v1.2.2 — Sözlüksüz ürün | Sözlüksüz çevrilmiş gerçek ürün kartını TR/EN/ZH’de aç. | Kart açıkça işaretlidir; kaynak ad korunur; marka/model/ölçü değişmez. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-011 | Listeler merkezi | En az 5 gerçek üründen yeni liste oluştur. | Ürünler tekil; miktar/varyant/kaynak notu doğru; demo kayıt yoktur. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-012 | Teklif turu — DRAFT | Gerçek listeyi bir firmaya bağla ve R1 oluştur. | R1 `DRAFT`; dış erişim kapalı; RFQ düzenlenebilir. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-013 | Teklif turu — SENT | Gönderimi onayla; RFQ ve kur bilgilerini not al. | R1 `SENT`; RFQ/kur snapshotı kilitli; 6 haneli anahtar ayrı kanaldadır. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-014 | Teklif turu — Kur kilidi | Panelde güncel kuru değiştir ve R1’i tekrar aç. | R1 kıyası/ham teklif sessizce değişmez; iç kur firma yüzünde yoktur. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-015 | Teklif turu — 700 adet | 500/1000/2000 koşullu bir satırda 700 adet karşılığını incele. | Açık aralık varsa 500 eşiği; yalnız nokta fiyatı varsa yeniden fiyat sorulur, interpolasyon yoktur. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-016 | Teklif turu — Kayıt | Gönderim kaydını aç. | Kim, ne zaman, hangi tur alanları doğru ve değişmezdir. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-017 | Portal PC — erişim | PC’de linki aç; yanlış ve sonra doğru 6 haneli anahtar gir. | Yanlışta sabit 404; doğruda yalnız ilgili R1 açılır; açık anahtar URL/logda yoktur. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-018 | Portal telefon — karşılama/liste | Telefon dikey görünümde karşılama ve liste ekranını dolaş. | Özet, ilerleme, arama ve filtreler taşmadan; firma verisi yalnız kendi turuna aittir. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-019 | Portal PC — bulundu | Gerçek satırı `found` yap; fiyat, ISO para, KDV onayı, MOQ, termin ve ambalaj gir. | Alanlar kaydolur; RFQ salt okunur; ambalaj serbest metindir. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-020 | Portal PC — alternatif | Asıl satırı `not_found` bırak; ayrı alternatif nesnesini ad/kaynak/fiyat/MOQ/termin üçlüsü/not ile doldur. | Asıl bulunamadı kalır; alternatif ayrı ve bağlıdır; rozet ilişkiden türetilir. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-021 | Portal — doğrulama | Eksik fiyat, para, MOQ, termin ve üç koli ölçüsüyle tamamlamayı dene. | Mevcut `portal.validation.*` metinleri seçili dilde; değerler silinmez, geçiş bloklanır. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-022 | Portal — otomatik kayıt | Bir alanı değiştir; başarı ve ağ hatasını ayrı dene. | 600–1000 ms otomatik kayıt, kayıt zamanı; hatada yalnız ilgili alan ve tekrar dene yüzü. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-023 | Portal — çevrimdışı | Ağı kes; değiştir, kapat/aç; kısmi gönderimi dene. | Yerel kuyruk geri gelir; gönderim bloklanır; token/başka firma verisi yerelde yoktur. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-024 | Portal — çakışma | Aynı turu PC ve telefonda değiştir; sunucu/cihaz çözümlerini sırayla incele. | Son yazan sessiz kazanmaz; karşılaştırma ve açık çözüm vardır; eski snapshot ezilmez. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-025 | Portal — kısmi gönderim | En az bir tamam ve bir eksik gerçek satırla kısmi gönder. | Yalnız geçerli sürümler gider; tur `PRICING`; eksik satır `not_found` olmaz. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-026 | Portal — nihai/salt okunur | Tüm satırları tamamla; üç onayı işaretle; çift tıkla ve yeniden aç. | Tek `RESPONDED` snapshotı; başarı referansı; tüm alanlar salt okunur. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-027 | Yapıştır–ayrıştır — gerçek metin | Firmanın gerçek mesajından kimlikli ve para birimli birkaç satırı yapıştır. | Önizleme kaynak izini korur; doğru ürün/alanlar eşleşir, kesin olmayan alanlar ayrılır. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-028 | Yapıştır–ayrıştır — belirsiz | Ürün veya para birimi belirsiz gerçek parçayı yapıştır. | Otomatik bağlama/yazma yoktur; belirsiz satır karar bekler. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-029 | Yapıştır–ayrıştır — kabul kapısı | Sunucu `YA-001..030` raporunu aç. | Alan doğruluğu ≥%90 ve yanlış ürüne fiyat %0; aksi KALDI. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-030 | Excel — temiz | `01-temiz.xlsx` yükle ve önizlemeyi aç. | Tur/imza/satırlar eşleşir; uygulanabilir farklar görünür; nihai yanıt sayılmaz. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-031 | Excel — kısmi/eksik | `02-kismi.xlsx` ve `06-eksik-zorunlu.xlsx` sonuçlarını incele. | Boş satır değişiklik yok; eksik alanlı satır hatalı; güvenli satırlar ayrı önizlenir. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-032 | Excel — güvenlik/tur | `03`, `04` ve `05` fikstürlerini sırayla yükle. | Bozuk imza bloklanır; formül çalışmaz; yanlış tur dosyasının tamamı reddedilir. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-033 | Excel — kodlama/kademeler | `08`, `09`, `10` fikstürlerini sırayla yükle. | Kademe çakışması bloklanır; ZH/BOM metni bozulmaz; yanlış ürüne eşleşme yoktur. | 3 dk | GEÇTİ / KALDI / NOT |
| KT-C-034 | Çıktılar — TR | Aynı gerçek turdan TR portal, PDF ve Excel üret/aç. | Çevrilebilir kullanıcı metni yalnız TR; başka dil alan değeri görülürse KALDI. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-035 | Çıktılar — EN | Aynı turdan EN portal, PDF ve Excel üret/aç. | Çevrilebilir kullanıcı metni yalnız EN; marka/model/ölçü kaynak değeri korunur. | 2 dk | GEÇTİ / KALDI / NOT |
| KT-C-036 | Çıktılar — ZH + F42 | ZH portal, PDF ve Excel’i aç; F42 linkini süre içinde ve süre sonunda dene. | ZH eksiksiz; başka dil alan değeri yok; link süre içinde doğru dosya, sonra sabit güvenli hata. | 3 dk | GEÇTİ / KALDI / NOT |

## Süre kanıtı

| Bölüm | Süre |
|---|---:|
| Kurulum sihirbazı | 11 dk |
| v1.2.2 sertleştirme | 13 dk |
| Gerçek liste + tur | 15 dk |
| Portal — PC + telefon | 25 dk |
| Yapıştır–ayrıştır | 6 dk |
| Excel gel-git | 10 dk |
| TR/EN/ZH çıktılar + F42 | 7 dk |
| **Toplam** | **87 dk** |
