# F38 — Panel Tema Yenilemesi: TilbeCore Tasarım Diline Geçiş (fikir taslağı)

> Durum: HAVUZ (kod yok). Hedef Faz 4+ — işlevsel fazlar tamamlandıktan sonra
> kendi iş emriyle açılır.

## İlke
tedarikapp paneli, **TilbeCore Kurban Takip** uygulamasının
(github.com/tilbehome/kurban2026) görsel kimliğini benimser. Amaç iki üründe
**ortak TilbeCore görsel kimliği**: kullanıcı hangi TilbeCore ürününü açarsa
açsın aynı tasarım dilini görür.

Birebir ekran kopyası **DEĞİLDİR** — taşınan şey tasarım DİLİDİR. tedarikapp'ın
ekran envanteri ve bilgi mimarisi docs/09'da tanımlandığı gibi SABİT kalır;
yalnız görünüm katmanı yenilenir.

## Yöntem (iş emri açıldığında)
1. kurban2026 deposundan **tasarım token seti** çıkarılır:
   - renk paleti (birincil/ikincil/durum renkleri, yüzeyler)
   - tipografi (aile, ölçek, ağırlıklar)
   - spacing / radius / gölge ölçekleri
   - bileşen stilleri: buton, kart, tablo, form elemanları
   - kenar çubuğu + başlık (header) düzeni
2. Token seti tedarikapp'ın **Tailwind yapılandırmasına** uygulanır
   (tema değişkenleri tek noktadan; bileşenlere dağınık renk kodu yazılmaz).
3. Ekran ekran gözden geçirme: docs/09 envanterindeki her ekran yeni token
   setiyle doğrulanır (işlev ve yerleşim değişmez).

## Sınırlar
- KOD bu maddede yazılmaz; yalnız havuz kaydı + bu taslak.
- Onaylı kütüphane listesi (K19) değişmez — tema mevcut Tailwind ile yapılır,
  yeni UI kütüphanesi eklenmez.
- Ekran envanteri/akışlar (docs/09, docs/03) bu iş kapsamında DEĞİŞMEZ.
