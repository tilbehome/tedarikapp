# tedarikapp v1.0 — Sürüm Notları (TASLAK)

> Bu metin **kullanıcı içindir**: teknik terim değil, işin dili kullanılır.
> v1.0 adı kabul turu geçilmeden kullanılmaz; aday paket `v1.0.0-rc5`.
> Teknik döküm: `CHANGELOG.md` · kapsam durumu: `docs/is-emirleri/IE21-DURUM.md`.

## Kısaca

tedarikapp artık uçtan uca çalışıyor: tedarik sitesindeki ürünü tek tıkla
yakalıyor, panelde listeye alıyor, fiyatı sabit kurla hesaplıyor ve firmaya
gidecek Excel/PDF belgesini üretiyor. Bu sürümle birlikte **panel bir kayıt
defteri olmaktan çıkıp bir komuta merkezi** oldu; eklenti ise artık sayfanın
içinde çalışıyor — açılır pencereye geçmeye gerek yok.

---

## Panelde neler var

### Liste ekranı artık işin durumunu tek bakışta söylüyor
- **Aşama çubuğu:** listenin nerede olduğu (Taslak → İletildi → Sipariş Verildi →
  Tamamlandı) ekranın üstünde durur; hangi adımın neden kapalı olduğu yazar.
- **Özet şeridi:** ürün sayısı, toplam tutar, eksik alanı olan ürün sayısı.
- **Uyarı çipleri:** "fiyat yok", "görsel yok", "adet girilmemiş" gibi eksikler
  ürünün üstünde görünür — belge üretmeden önce ne eksik, tahmin etmezsiniz.
- **Toplu eylemler:** seçtiğiniz ürünleri tek hamlede taşıyın, durum değiştirin,
  silin. Seçim süzgeç değişince kaybolmaz.
- **Ürün çekmecesi:** ürüne tıklayınca sayfa değişmez; sağdan çekmece açılır,
  düzenlersiniz, kapatırsınız.

### Keşif havuzu
Hangi listeye gireceği henüz belli olmayan ürünler için ayrı bir havuz var.
Havuz **normal listelerin arasında görünmez**, sayımlara karışmaz; silinemez,
iletilemez, paylaşılamaz — yanlışlıkla firmaya gitmesi mümkün değildir.

### Gelen Kutusu — deste modu
Eklentiden gelen yakalamaları kart kart, hızlı biçimde işleyin: listeye at,
düzelt, at. Aynı ürün ikinci kez geldiyse dört seçenek sunulur (yeni kayıt ·
mevcut kaydı güncelle · adet ekle · atla).

### Paylaşım
Listeyi bir bağlantı ile paylaşırsınız; karşı taraf uygulamaya girmez.
- Bağlantı **erişim anahtarıyla** korunur; anahtarın süresi dolmaz.
- Kilit ekranı seçilen dilde açılır ve **kendini tazeler** (sayfa bayatlamaz).
- Anahtar unutulduysa "Yeni anahtar iste" WhatsApp üzerinden size ulaşır —
  anahtar mesajın içine ASLA yazılmaz.
- Arka arkaya hatalı denemede uyarı çıkar; kalan hak sayısı gösterilmez.

### Belgeler
Excel ve PDF çıktıları artık kurumsal antetli: logo, filigran, dil seçimi.
PDF başlığı seçtiğiniz dilde tek satırdır; tablo başlıkları üç dilli kalır
(Türkçe · İngilizce · Çince) ki karşı taraf hangi sütunun ne olduğunu görsün.
Ürünün orijinal Çince adı belgede korunur — karşı taraf kendi kaydını bulur.

### Çeviri
Ürün adları, kategoriler ve öznitelik değerleri Türkçe (ve seçtiğiniz diğer
dillerde) görünür. Çeviri **öneridir**: onaylamadığınız hiçbir metin ürün
kaydına kendiliğinden yazılmaz, onayladığınız metin de hiçbir otomatik tur
tarafından değiştirilmez.

### Kur
Kur listeye kilitlenir: liste iletildikten sonra kur değişse bile o listenin
fiyatları oynamaz. "Güncel kuru getir" düğmesi TCMB bülteninden okur ve forma
yazar — kaydetme kararı sizindir.

### Ayarlar ve kurulum
Kurulum sihirbazı artık bir teşhis merkezi: bir şey ters giderse durumu adıyla
söyler ("ayar dosyası bozuk", "migration yarım", ...) ve onarımı aynı ekranda
sunar. Teşhis çıktısı tek tuşla kopyalanır.

---

## Eklentide neler var

- **Sayfa içi panel:** ürün sayfasında, sayfanın kendi arayüzünün içinde açılır.
  Ne yakalandığını **göndermeden önce** görürsünüz: 16'dan fazla alan, doluluk
  oranı, seçili varyant, eksik alanlar sarı işaretli.
- **Hiçbir adım sessiz değil:** okunuyor · önizleme hazır · kısmi okundu ·
  gönderiliyor · gönderildi · mükerrer · yetki hatası — hepsi ekranda yazar.
- **Bağlantı durumu görünür:** panele ulaşılamıyorsa sebebi yazar ve "Yeniden
  dene" düğmesi çıkar; bağlantı kurulunca hedef listesi kendiliğinden dolar.
- **Çevrimdışı kuyruk:** bağlantı yokken yakalama kaybolmaz, cihazda bekler ve
  bağlanınca gönderilir. Gönderilemeyen kayıt sessizce ölmez: rozet çıkar ve
  üç seçenek sunulur (yeniden dene · düzelt · vazgeç).
- **Son liste hatırlanır:** aynı listeye otuz ürün eklerken listeyi otuz kez
  seçmezsiniz.
- **Ne topladığımız açıkça yazıyor:** ilk kullanımda izin ekranı çıkar; toplanan
  veri kalemleri Chrome Web Store beyanıyla birebir aynıdır.

---

## Bu sürümde OLMAYANLAR (bilinçli kapsam kararı)

- Kural motoru rozetleri ve geri alma · sözlük CSV içe aktarma → sonraki sürüm.
- Teklif toplama portalı, RFQ akışı → V3-C.
- Panorama brifing ekranı, bildirim merkezi, PWA → İE#22.
- Eklenti şimdilik yalnız **1688 Çince ürün detay görünümünde** çalışır; global
  (Türkçe arayüzlü) görünüm desteklenmez.

---

## Kurulum ve yükseltme

`docs/07-deploy-runbook.md` ve temiz kurulum eki geçerlidir. Yükseltmede
veritabanı göçleri ileri yönlüdür; geri alma yoktur, yedek alınmadan
yükseltilmez.
