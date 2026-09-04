# K105 — MİKRO ETKİLEŞİM STANDARDI

**Karar:** 31 Ağustos 2026 · PM + Ürün Sahibi
**Kapsam:** V3-C ve sonrasında doğan HER yeni ekran
**Bağlayıcılık:** matristeki `zorunlu` hücreler karşılanmadan ekran defterde KIRMIZI kalır

---

## 0. Bu belge neden var

Panelde aynı işi yapmanın ekrandan ekrana farklı yolları oluşuyordu: bir yerde
satır çift tıklamayla açılıyor, başka yerde sağdaki düğmeyle; bir yerde silme
onay soruyor, başka yerde geri alınabiliyor. Kullanıcı her ekranda **yeniden
öğrenmek** zorunda kalıyor ve öğrendiği şey bir sonraki ekranda yanlış çıkıyor.

Tutarsızlığın bedeli görünmez olduğu için de birikiyor: hiçbiri tek başına hata
değil, toplamı "bu program karışık" hissi. Bu belge o toplamı bir **sözleşmeye**
çevirir.

**Bu belge yeni özellik tanımlamaz.** Zaten yapılan işlerin *nasıl* yapılacağını
sabitler.

---

## 1. Hücre değerleri

| Değer | Anlamı |
|---|---|
| **zorunlu** | Ekran bunu karşılamadan "bitti" sayılmaz. Kabul turunda aranır. |
| **P-borcu** | Mevcut ekranda eksik; V3-P (Operatör Hızı & Sayfa Olgunluğu) fazında kapanacak. Yeni ekranda P-borcu AÇILAMAZ. |
| **yok (gerekçe)** | O öğe tipinde anlamsız. Gerekçe yazılmadan "yok" yazılamaz. |

**ÜS kararı:** hepsi **zorunlu** varsayılandır. İstisna yalnız gerekçeyle ve
PM onayıyla açılır; açılan her istisna bu belgenin sonundaki listeye düşer.

> **Mevcut V3-A/B ekranları ELDEN GEÇİRİLMEZ.** Eksikleri matrise `P-borcu`
> olarak işlenir. Sebep: çalışan ekranları toplu revizyona sokmak, kazanılan
> davranışı yeniden riske atar; borç görünür kaldığı sürece kaybolmaz.

---

## 2. MATRİS

### 2.1 Satır / kart

| Mikro eylem | Değer | Not |
|---|---|---|
| Aç (detay) | zorunlu | Tek tıklama satırı SEÇER, çift tıklama ya da `Enter` AÇAR. |
| Satır içi veya çekmece düzenleme | zorunlu | İkisinden biri; ekran hangisini seçtiyse o ekranda tutarlı olmalı. |
| Kopyala / çoğalt | zorunlu | Kopya "(kopya)" ekiyle ve DÜZENLENEBİLİR durumda doğar. |
| Taşı | zorunlu | Sürükle-bırak varsa klavye eşdeğeri de olmalı (erişilebilirlik). |
| Geri alınabilir sil | zorunlu | **5 sn toast + 30 gün çöp.** Onay kutusu YOK — geri alma onaydan iyidir. |
| Sabitle | zorunlu | Sabitlenen satır sıralamadan bağımsız üstte kalır. |
| Not / etiket | zorunlu | Satır düzeyinde; liste düzeyindekiyle karıştırılmaz. |
| `⋯` menü + sağ tık | zorunlu | İKİSİ AYNI menüyü açar. Sağ tık tarayıcı menüsünü bastırır. |
| Çoklu seçim | zorunlu | `Space` seçer, `Shift+tık` aralık seçer; seçim varken alt çubuk çıkar. |

### 2.2 Alan

| Mikro eylem | Değer | Not |
|---|---|---|
| Tıkla-düzenle | zorunlu | Ayrı "düzenle" moduna girmeden. |
| Kopyala düğmesi | zorunlu | Hover'da belirir; kopyalayınca kısa onay. |
| Temizle | zorunlu | Alanı boşaltır; `undo` ile geri gelir. |
| Orijinali göster | zorunlu | Çevrilmiş/normalize edilmiş alanlarda ham değer görülebilmeli (K56 hattı). |
| Değişiklik geçmişi + geri al | zorunlu | Kim, ne zaman, neyi değiştirdi. |
| Boş alan **"—" değil EYLEM** | zorunlu | Boşluk bir çıkmaz sokak değil, "+ ekle" davetidir. |

### 2.3 Tablo

| Mikro eylem | Değer | Not |
|---|---|---|
| Tek + çoklu sıralama | zorunlu | `Shift+tık` ikincil ölçüt ekler. |
| VE/VEYA filtre + hızlı çipler | zorunlu | Çipler en sık üç filtreyi tek tıkla verir. |
| Alana göre gruplama + alt toplam | zorunlu | Para alt toplamları K14 kurallarıyla (bcmath). |
| Kaydedilmiş görünüm | zorunlu | Mevcut `kesif.gorunumler` deseni tek kaynaktır. |
| Sütun göster/gizle/sırala/genişlik/dondur | zorunlu | Tercih kullanıcı başına saklanır. |
| Yoğunluk (rahat/sıkı) | zorunlu | |
| Koşullu biçimlendirme | zorunlu | Kural görünür ve kapatılabilir olmalı — sihirli renk yok. |
| Özet satırı | zorunlu | Filtre uygulanmışsa özet FİLTRELİ kümeyi anlatır ve bunu söyler. |
| Sütun içi arama | zorunlu | |
| Sayfalar arası tümünü seç | zorunlu | "Bu sayfadaki 50" ile "eşleşen 1.284" AYRI ve açıkça yazılı. |
| Dışa aktar: seçili / filtreli / tümü | zorunlu | Üçü ayrı seçenek; hangisinin dışa aktarıldığı dosya adında görünür. |
| Klavye `J/K/Enter/Space/Esc` | zorunlu | `?` tuşu kısayol kartını açar. |
| URL'de durum | zorunlu | Filtre/sıra/sayfa URL'de; bağlantı paylaşılabilir (mevcut Keşif deseni). |

### 2.4 Liste / belge / link

| Mikro eylem | Değer | Not |
|---|---|---|
| Aç | zorunlu | |
| Kopyala | zorunlu | Bağlantı kopyalama kısa onay verir. |
| Paylaş | zorunlu | K62 anahtar kapısı ve K103 paylaşım kaydı hattından geçer. |
| Yazdır | zorunlu | Yazdırma görünümü ekrandan FARKLI olabilir; farkı önizleme gösterir. |
| PDF / Excel | zorunlu | K50: çıktı üretildiği anın kopyasıdır. |
| Revizyon geçmişi | zorunlu | K57 revizyon harfi ile tutarlı. |
| Arşivle / geri getir | zorunlu | Arşiv silme DEĞİLDİR ve ayrı listede görünür. |

### 2.5 Sayfa

| Mikro eylem | Değer | Not |
|---|---|---|
| `Ctrl+S` / `Esc` / `Ctrl+K` | zorunlu | Kaydet / vazgeç / komut paleti. |
| "Kaydedilmedi" uyarısı | zorunlu | Sayfadan ayrılırken; tarayıcı uyarısı TEK BAŞINA yeterli değil. |
| Boş / yükleniyor / hata durumu TASARIMLI | zorunlu | Üçü de "ne oldu + ne yapmalıyım" söyler. Boş spinner yasak. |
| **Hiçbir eylem sessiz çalışmaz** | zorunlu | Her eylem ya sonuç ya hata gösterir. Sessizlik bir durum değildir. |

### 2.6 Yıkıcı eylem

| Mikro eylem | Değer | Not |
|---|---|---|
| Önce geri alınabilir yol | zorunlu | Silme varsayılan olarak çöpe gider. |
| Geri alınamıyorsa onay + NE GİDECEĞİ | zorunlu | "Emin misiniz?" yetmez: kaç kayıt, hangileri, neyin kaybolacağı yazılır. |
| Onay metninde eylem adı | zorunlu | Düğmede "Sil" yazar, "Tamam" değil. |

---

## 3. ORTAK BİLEŞENLER

Ekranlar bu davranışların kendi kopyasını **YAZMAZ**. Kopya, tutarsızlığın
yeniden doğduğu yerdir — matrisi yazmamızın sebebi de buydu.

| Bileşen | Sorumluluk |
|---|---|
| `SatirEylemMenusu` | `⋯` + sağ tık; aynı menü, tek tanım. |
| `AlanEylemleri` | kopyala · temizle · orijinali göster · geçmiş. |
| `SecimCubugu` | çoklu seçim alt çubuğu; sayfalar arası seçim ayrımını o taşır. |
| `GeriAlToast` | 5 sn geri alma (mevcut kalıp korunur). |
| `TabloAyarlari` | sütun düzeni · yoğunluk · kaydedilmiş görünüm. |

**Bekçi:** kaynak taraması, yeni ekranların bu davranışları elle yeniden
kurmasını engeller (`tests/Support/K105BilesenBekcisiTest.php`).

---

## 4. DEFTER BAĞLANTISI

`docs/v3/hazirlik/e2e-kapsam-defteri.json` içine **`k105`** sütunu eklendi.
Bir ekran, kendi öğe tiplerinin `zorunlu` hücrelerini kapsamadan defterde
**KIRMIZI** kalır.

---

## 5. AÇIK İSTİSNALAR

*(Şu an yok. Her istisna PM onayıyla ve gerekçesiyle buraya yazılır.)*
