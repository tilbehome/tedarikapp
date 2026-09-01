# P–Z Anayasası

**Kaynak:** Görev #31 §10  
**Statü:** 31-R ile bağlayıcı  
**Kapsam:** P–Z haritasındaki tam fazlar, bağlı bloklar ve tetikli fazlar  
**Tamlık:** 10/10

Bu kurallar faz kapsamı değildir; bütün P–Z emirlerinin uyması gereken çapraz ürün sınırlarıdır. Bir faz emri, ilgili anayasa maddesini hangi kabul kanıtıyla sınayacağını yazmadan tamamlanmış sayılmaz.

## 1. Tek eylem sözleşmesi

**Kural:** Aynı iş eylemi; fare, sağ tık, klavye, komut paleti, toplu işlem, portal, otomasyon veya AI asistanından çağrılsa da tek command/action sözleşmesini, aynı yetkiyi, doğrulamayı ve audit yolunu kullanır.

**Gerekçe:** Aynı eylemin farklı yüzeylerde ayrı iş mantığına dönüşmesi davranış ayrışması, yetki açığı ve tutarsız hata üretir.

**İhlal örneği:** Toplu “firmaya gönder” eylemi eksik şartnameyi engellerken komut paletindeki eşdeğer eylemin aynı kontrolü atlaması.

**Sınandığı fazlar:** P, Q, S, V, W, Y.

## 2. İş olayı ana durum değildir

**Kural:** Değişiklik talebi, istisna vakası, görev, kanıt, mal kabul bulgusu ve otomasyon çalışması ana `status.*` sözlüğüne yeni durum eklemez; kendi olay/alt kayıt yaşam döngüsünde tutulur.

**Gerekçe:** Ana durum makinesi işin genel ilerlemesini, alt kayıtlar ise eşzamanlı olayları temsil eder. İkisini birleştirmek durum patlamasına yol açar.

**İhlal örneği:** “Kritik istisna açık” bilgisini bir vaka rozeti yerine on birinci ana liste durumu yapmak.

**Sınandığı fazlar:** Q, S, U, V, W, Y, Z.

## 3. Ortak snapshot standardı

**Kural:** Şartname, teklif, sipariş, karar ve tekrar sipariş anları aynı kimlik, sürüm, zaman, kaynak ve değişmezlik sözleşmesiyle snapshot üretir veya mevcut snapshot'a referans verir.

**Gerekçe:** Karar ve fark analizi, karşılaştırılan kayıtların hangi anda hangi değerleri taşıdığını güvenilir biçimde yeniden kurabilmelidir.

**İhlal örneği:** R senaryosunun güncel teklifi, X tekrar siparişinin ise üzerine yazılmış eski fiyat alanını kullanması ve iki değerin aynı sürüm sanılması.

**Sınandığı fazlar:** Q, R, S, T, V, X.

## 4. Kanıt kökeni zorunludur

**Kural:** Her görsel, video, belge, ölçüm ve metin kanıtında kaynağı, neyi kanıtladığı, bağlı kayıt/madde, sürüm ve zaman bilgisi bulunur.

**Gerekçe:** Kökeni belirsiz dosya uyuşmazlık çözmez, yanlış ürüne bağlanabilir ve eski kanıtın güncel sanılmasına neden olur.

**İhlal örneği:** Mal kabul fotoğrafının hangi sipariş satırına, hangi kusura ve hangi koliye ait olduğu bilinmeden genel dosya listesine yüklenmesi.

**Sınandığı fazlar:** Q, S, T, U, V, W, X.

## 5. Eksik veri sıfır veya başarı değildir

**Kural:** Eksik, bilinmeyen ya da doğrulanmamış değer; sıfır, ortalama, varsayılan olumlu sonuç veya “uygun” değerine çevrilmez. Sonuçta açıkça eksik/hesaplanamadı olarak kalır.

**Gerekçe:** Eksik veriyi sayısal veya olumlu varsayıma çevirmek ucuzluk, uygunluk, doluluk veya otomasyon güvenliği hakkında yanlış kesinlik üretir.

**İhlal örneği:** Koli ölçüsü olmayan teklifin CBM değerini 0 kabul ederek R senaryosunu en avantajlı seçenek göstermek.

**Sınandığı fazlar:** P, Q, R, T, U, V, X, Y, Z.

## 6. İnsan onayı korunur

**Kural:** Akıllı yapıştırma, belge/metin çıkarma, senaryo, otomasyon ve AI sonucu taslak veya öneridir; kritik kayıt değişikliği ve satın alma kararı açık insan onayı olmadan kesinleşmez.

**Gerekçe:** Kaynak metinler eksik, çelişkili veya bağlam dışı olabilir. Otomatik kesinleştirme pahalı ve geri döndürülmesi zor iş hatası üretir.

**İhlal örneği:** WhatsApp'tan çıkarılan yeni koli ölçüsünün önizleme olmadan sipariş revizyonuna yazılması ve maliyetin sessizce değişmesi.

**Sınandığı fazlar:** P, Q, R, S, T, U, V, W, X, Y, Z.

## 7. PWA/offline sınırı ve açık çakışma çözümü

**Kural:** Bağlantı kesintisine dayanıklı giriş sunulan yerde yerel taslak, eşitleme durumu, tekrar deneme ve aynı kaydın iki tarafta değişmesi halinde açık çakışma çözümü bulunur. Büyük medya ve gelişmiş 3B plan için offline taahhüdü verilmez.

**Gerekçe:** Sessiz son-yazan-kazan yaklaşımı saha kanıtını veya merkezdeki güncel veriyi kaybettirebilir.

**İhlal örneği:** Telefonda çevrimdışı sayılan 120 adedin bağlantı gelince merkezdeki 96 adedi uyarısız ezmesi.

**Sınandığı fazlar:** P, Q, U, V, W.

## 8. Dil tek kaynaktan üretilir

**Kural:** Yeni sistem etiketi, durum dışı rozet, alan adı, seçim değeri ve çıktı metni M/K56'nın TR/EN/ZH tek kaynak hattına eklenmeden UI veya çıktıya çıkmaz.

**Gerekçe:** Dağınık sabit metinler karışık dil, eksik çeviri ve aynı kavram için farklı terim üretir.

**İhlal örneği:** Z ile eklenen seçim değerinin ekranda Türkçe, Excel'de İngilizce anahtar ve firma paylaşımında çevrilmemiş görünmesi.

**Sınandığı fazlar:** Q, S, T, U, V, W, X, Y, Z.

## 9. Gözlenebilir sonuç zorunludur

**Kural:** Toplu işlem, kural, eşitleme, veri eşleme ve uzun süren hesap; başarı, kısmi hata, atlandı ve yeniden deneme sonuçlarını ölçülebilir ve kayıt bazında açıklanabilir verir.

**Gerekçe:** “Tamamlandı” mesajı hangi kayıtların değişmediğini saklar ve operatörün sessiz veri kaybını fark etmesini engeller.

**İhlal örneği:** 200 satırlık Excel gel-git işleminde 17 özel alan eşleşmediği hâlde yalnız yeşil başarı mesajı gösterilmesi.

**Sınandığı fazlar:** P, R, S, T, V, W, Y, Z.

## 10. Kademeli ve geri çevrilebilir açılış

**Kural:** Yüksek yayılım veya otomasyon riski taşıyan özellikler küçük kapsam, feature flag, gerçek veri kopyası içermeyen test fikstürü, kabul kanıtı ve geri alma yolu ile açılır.

**Gerekçe:** Yatay etkileşim, yük planı ve kural davranışındaki hata çok sayıda kayıt veya ekranı aynı anda etkileyebilir.

**İhlal örneği:** Yeni toplu düzenleme davranışının bütün ekranlarda tek seferde ve geri alma yolu olmadan etkinleştirilmesi.

**Sınandığı fazlar:** P, T, S, V, W, Y, Z.

## Anayasa kabul özeti

| Madde | Kural | Zorunlu kanıt |
|---:|---|---|
| 1 | Tek eylem sözleşmesi | Yüzeyler arası aynı yetki/doğrulama/audit testi |
| 2 | İş olayı ≠ ana durum | Ana durum sözlüğü farkının sıfır olması |
| 3 | Ortak snapshot | Kimlik/sürüm/zaman/kaynak tutarlılık testi |
| 4 | Kanıt kökeni | Eksiksiz köken ve bağ kaydı |
| 5 | Eksik veri semantiği | Bilinmeyenin sıfıra/başarıya dönmediği test |
| 6 | İnsan onayı | Taslak→onay geçiş kanıtı |
| 7 | Offline sınırı | Kesinti, eşitleme ve çakışma testi |
| 8 | Dil tek kaynağı | TR/EN/ZH çıktı kapsama testi |
| 9 | Gözlenebilirlik | Başarı/kısmi hata/atlandı dökümü |
| 10 | Kademeli açılış | Flag, fikstür, kabul ve geri alma kanıtı |
