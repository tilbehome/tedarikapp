# V3 Arayüz Prototipi — Kullanım ve Bağlayıcılık

**Dosyalar**
- `tedarikapp-v3-prototip.html` — çalışan prototip (tek dosya, bağımlılıksız) — ONAYLI
- `tasarim-tokenlari.css` — koddan **doğrudan kullanılacak** token dosyası
- `OKUBENI.md` — bu dosya

Prototip; uygulama kabuğunu (sol menü + üst çubuk + komut paleti), **Keşif
havuzunu** ve **Gelen Kutusu deste modunu** gerçek davranışlarıyla gösterir.
Faz 1'in görsel ve davranışsal şartnamesidir.

**Tasarım felsefesi (bağlayıcı):** ekran SAKİN açılır, derinlik TALEP ÜZERİNE
gelir. Varsayılan görünüm az sütunlu ve az gürültülüdür; filtreler, gelişmiş
ayarlar ve ayrıntı ekranı yer kaplamaz — istendiğinde açılır.

---

## 1. BAĞLAYICI OLANLAR (birebir uygulanır)

### 1.1 Tasarım tokenları
`tasarim-tokenlari.css` projeye alınır ve tek kaynak olur. Bileşenlerde sabit
renk/ölçü/gölge yazılmaz. Koyu tema yalnız token değişimiyle çalışır; ikinci
bir CSS seti yazılmaz. Altın renk YALNIZ vurgu içindir (aktif menü şeridi,
skor çubuğu) — geniş yüzeylerde kullanılmaz.

### 1.2 Kabuk
- Grid: `sidebar | içerik`; `Ctrl+B` ile 60 px ikon şeridine iner.
- Sol menü: marka bloğu (logo + ad + sürüm rozeti) → "Yeni" düğmesi →
  gruplu gezinme (ÇALIŞMA / TEDARİK / ANALİZ / KAYITLAR / SİSTEM) → hesap satırı.
- Aktif öğe: yumuşak mavi dolgu + sol kenarda ince altın şerit.
- Rozetler sağda; sıfırsa görünmez.
- Üst çubuk: kırıntı yolu (Bölüm › Ekran) + **komut kutusu** + bildirim.
- **Komut paleti `Ctrl+K`** zorunludur: ekranlar arası zıplama ve eylemler;
  ok tuşları + Enter ile kullanılır. Uygulama hissinin merkezi budur.

### 1.3 Keşif havuzu
- **Kayıtlı görünümler sekme olarak** en üstte (Tümü · Yüksek skor · Avantajlı ·
  Eksik verili · + Görünüm). En sık kullanılan kesitler tek tık uzaklıkta.
- **Filtreler bir düğmenin arkasında** (popover). Seçilen filtreler tablo
  üstünde çıkarılabilir çip olarak görünür; düğmede sayaç rozeti olur.
- **Varsayılan sütunlar (7):** Ürün (görsel + ad + orijinal/kategori) ·
  Platform · Tedarik Puanı (sayı + çubuk) · Birim fiyat · Avantaj (± %) ·
  Durum (nokta + metin) · satır eylemleri. Diğer alanlar çekmecededir.
- Satır yüksekliği ferah (~62 px); ayraçlar ince; gürültü yok.
- **Seçim kutusu ve satır eylemleri hover'da belirir** — tablo boşken temiz.
- Satır eylemleri: listeye ekle · izle · ürüne git.
- Seçim yapılınca **alttan toplu işlem çubuğu** yükselir (canlı sayı ile).
- Satıra tıklamak **sağ çekmeceyi** açar: puan dökümü, ürün bilgileri,
  **"Eksik bilgileri göster (N)"** katlaması, ithalat avantajı kartı, yorum
  özeti, galeri, alt eylem çubuğu.
- Sütun başlığına tıklayınca sıralama; Liste/Izgara görünüm anahtarı.

### 1.4 Gelen Kutusu — deste modu
- Tek ürün büyük kart; dört karar: **← çöp · → havuz · ↑ listeye · Space atla**.
- Klavye zorunlu; aynı dört eylem düğme olarak da bulunur.
- Kural uygulanan üründe kural rozeti.
- Kutu bitince "kutu boşaldı" ekranı + dönüşüm istatistiği.
- Üstte bekleyen sayısı ve yakalama oturumu bilgisi.

### 1.5 Ortak davranışlar
- İşlem sonrası **"geri al" bildirimi** (toast).
- Boş durumlar tasarlanmış olur.
- Sayılarda tabular rakam; para birimi karışmaz.
- Türkçe karakter duyarsız + çift dilli arama ("termos" ve "保温" aynı kaydı bulur).
- `/` arama odağı, `Esc` kapatma, `Ctrl+B` menü, `Ctrl+K` komut.

---

## 2. ÖRNEK / TEMSİLİ OLANLAR (kopyalanmaz)

- **Veriler** uydurmadır; gerçek veri şemadan gelir.
- **Görseller** renkli yer tutucudur; gerçekte ürün görselleri (yerel arşiv +
  türev boyutlar) kullanılır.
- **Skor** prototipte sabit değerdir; gerçek formül yol haritasındaki
  normalizasyon kurallarına göre hesaplanır.
- **Diğer menü ekranları** bilinçli olarak boştur; sonraki fazlarda tasarlanır.
- **Font** prototipte CDN'den gelir; üretimde Inter + Noto Sans SC **self-host**
  edilir (K45 — dış istek yasak).
- **Tek dosya** olması mimari örneği değildir; üretimde bileşenler ayrılır,
  satır içi script kullanılmaz (K45 CSP).

---

## 3. DEĞİŞİKLİK YÖNETİMİ

Bir davranış değişirse önce bu dosya güncellenir, sonra kod. Prototip ile kod
çelişirse **yol haritası ve bu dosya kazanır**; prototip örnektir, delil değil.
