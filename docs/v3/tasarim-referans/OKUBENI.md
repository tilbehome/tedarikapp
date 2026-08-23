# V3 Tasarım Referansları — ne olduğu ve nasıl kullanılacağı

Bu klasördeki görseller **onaylı tasarım referanslarıdır**: uygulanacak ekranın
nasıl görüneceğini ve hangi bilgiyi hangi hiyerarşide taşıyacağını gösterirler.

**Bunlar ekran görüntüsü değil, ŞARTNAMEDİR.** Bir ekranı yazarken önce buradaki
karşılığına bakılır; görselle koddaki davranış çelişirse görsel kazanır ve çelişki
ÇIKTI RAPORU'nda bildirilir (CLAUDE.md §1). Piksel kopyası beklenmez — bilgi
mimarisi, sütun düzeni, durum dili ve etkileşim sırası bağlayıcıdır.

| Dosya | Ekran | Bağlı iş emri maddesi |
|---|---|---|
| `panaroma.png` | Ana ekran / Panorama | V3 Dilim 4 |
| `listeler.png` | Listeler | V3 Dilim 4 |
| `liste-ici.png` | Liste detay — komuta merkezi | İE#21 B2 |
| `urun-duzenleme-alani.png` | Ürün çekmecesi (düzenleme) | İE#21 B3 |
| `gelen-kutusu.png` | Gelen Kutusu — dolu hâl | İE#21 B4 |
| `gelen-kutusu-bos-hali.png` | Gelen Kutusu — boş hâl | İE#21 B4 |
| `erisim-anahtar-ekrani.png` | Paylaşım kilit ekranı | İE#21 B7 |
| `paylasim-sayfasi.png` | Paylaşım sayfası — detay paneli KAPALI | İE#21 B8 |
| `paylasim-sayfasi-detay.png` | Paylaşım sayfası — detay paneli AÇIK | İE#21 B8 |

## ⚠ PAYLAŞIM SAYFASI GÖRSELLERİ v1.0'DA KISMİ UYGULANIR (PM kararı, 23 Ağu 2026)

`paylasim-sayfasi.png` ve `paylasim-sayfasi-detay.png` karelerindeki arayüz —
sekmeli detay paneli (Ürün bilgileri / Varyasyonlar / Medya / Kaynak), üç sütunlu
bilgi ızgarası, "Talep notu" kutusu — **v1.0'da uygulanmaz.**

**v1.0 kapsamı:** İE#21 B8'de SAYILI yedi madde (4 düzeltme + 3 ince ayar) +
bugün canlıda çalışan detay açılımının KORUNMASI. Mevcut "Ürün bilgileri +
varyasyon açılımı" yeterli tabandır.

**Tam uyarlama V3-C'ye ertelendi.** Gerekçe: firma yüzü V3-C Firma Döngüsü'nde
tedarikçi portalıyla birlikte zaten baştan ele alınacak; aynı yüzeyi iki kez
inşa etmek hem israf hem de iki farklı arayüz mirası bırakır.

**Kabul turunda** paylaşım sayfası mevcut + düzeltmeli hâliyle sınanır;
bu iki kareyle **birebirlik aranmaz**.

## Son iki dosya hakkında (İE#21 C2)

`paylasim-sayfasi.png` ve `paylasim-sayfasi-detay.png` bu klasöre UUID adlarıyla
(`c120e0c1-…`, `60d3be2b-…`) düşmüştü. Adlandırıldılar: bir şartname dosyasının
adı ne olduğunu söylemelidir — UUID, dosyayı açmadan hiçbir şey anlatmaz ve
belgeler ona referans veremez.

İkisi de **canlı sistemin gerçek çıktısıdır** (TDK-2026-0013, 22.08.2026). Bu
yüzden ayrıca bir KUSUR KAYDIDIR: İE#21 B8'de düzeltilecek dört sapma bu iki
karede görülebilir —

1. Antette **kilitli kur ibaresi yok** (₺ karşılıkları var ama hangi kurla
   hesaplandığı yazmıyor).
2. Durum etiketleri ("Bekleme listesinde", "Fiyat bekleniyor") tek kaynaktan
   değil; 5B durum haritasıyla eşitlenecek.
3. Seçilen varyant, "Varyasyonlar ve talep" bloğunun içinde eriyor; kendi
   alanına çıkacak.
4. Başlıkta **"Firma kopyası" yazarken "Paylaş" düğmesi duruyor** — firma
   görünümünde bu düğme olmamalı.

Detay karesinde ayrıca B8'in üç ince ayarı görülür: blok başlığı "Varyasyonlar ve
talep" yerine "Talep/Seçim" olacak, "Eksik bilgileri göster (8)" yerine "Sizden
beklenen bilgiler (8)" yazacak, talep notu üç dilde görünecek.
