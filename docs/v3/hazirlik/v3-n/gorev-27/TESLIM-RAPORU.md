# Görev #27 — V3-N Müşteri Paneli Metin Paketi Teslim Raporu

**Teslim tarihi:** 28 Ağustos 2026  
**Durum:** Tamamlandı

## Teslim edilen dosyalar

1. `27a-hukuki-ve-islemsel-metinler.md`
   - Yer tutuculu Müşteri Portalı Aydınlatma Metni.
   - Ön sipariş niyet beyanının sözleşme olmadığını ve ticari içerik gizliliğini düzenleyen Kullanım Koşulları.
   - Yedi işlemsel e-posta ailesi: doğrulama, şifre sıfırlama, başvuru alındı, ek bilgi, onay, ret/askı ve parola/e-posta değişikliği.
   - Her belge/şablon başında zorunlu hukuk danışmanı şerhi bulunur.

2. `27b-musteri-portal-metinleri-tr.json`
   - Landing, üç adımlı kayıt, giriş/sıfırlama, onay bekleme ve altı müşteri paneli menüsünün TR metin seti.
   - Buton, yardım, hata, boş durum, güvenlik kapısı ve çıktı metinleri dahil **477 yeni anahtar**.
   - Anahtarların tamamı `portal.musteri.*` ad alanındadır.
   - Görev #25E’deki **167 anahtarla birebir karşılaştırma sonucu çakışma: 0**.
   - JSON içi yinelenen anahtar: 0.
   - PM şerhleri uygulandı: “Tahmini toplam”; İlgileniyorum için zorunlu miktar; Kararsızım için isteğe bağlı miktar; İstemiyorum için kapalı miktar alanı.

3. `27c-musteri-paneli-kabul-senaryolari.md`
   - `KT-N-001`–`KT-N-034` arasında **34 kabul senaryosu**.
   - Her senaryo ön koşul, adımlar ve beklenen sonuç biçimindedir.
   - Erişim/API/çıktı kapısı, kayıt-doğrulama-onay, niyet beyanı, Tahmini toplam, miktar matrisi, dekont, tek dil ve enumeration-safe sıfırlama kapsanır.

## Bağlayıcı karar kontrolü

- Görev #26 §2.1, §7, §10 ve §15 esas alındı.
- Aydınlatma Metni ile açık rıza/kabul birbirinden ayrıldı; zorunlu “KVKK’yı kabul ediyorum” kutusu üretilmedi.
- Kullanım Koşulları kabulü ayrı tutuldu.
- E-posta kapsamına pazarlama, kampanya, bülten, yeni teklif veya stok bildirimi eklenmedi.
- Onay öncesi ürün/fiyat/teklif/liste/belge görünürlüğü verilmedi.
- Panelde ödeme veya sanal POS metni üretilmedi; dekont yalnız harici kapora bildirimi olarak tanımlandı.
- Teklif kaynağı değişmez; müşteri niyeti ayrı yanıt katmanıdır.
- Süre ve oran taahhüdü yazılmadı.

## Teknik doğrulama

- JSON sözdizimi: geçerli.
- Yeni JSON anahtar sayısı: 477.
- Yanlış ad alanı: 0.
- Yinelenen anahtar: 0.
- Görev #25E çakışması: 0/167.
- Kabul senaryosu numara aralığı: kesintisiz `KT-N-001`–`KT-N-034`.
- E-posta şablonu ailesi: 7/7.

## Yayın öncesi açık işler

- 27A’daki `[YER TUTUCU]` alanlar gerçek şirket, veri envanteri, hizmet sağlayıcı, aktarım ve saklama bilgileriyle doldurulmalıdır.
- Hukuki metinler hukuk danışmanı tarafından onaylanmadan yayımlanmamalıdır.
- Çince veya İngilizce çeviri bu görevin kapsamına dahil değildir; 27B yalnız TR kaynak settir.

