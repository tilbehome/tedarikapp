# tedarikapp — Test ve Kabul Planı

> Durum: v1.0 — ONAYLANDI (16.08.2026)

## 1. Test Katmanları

| Katman | Ne | Nerede | Kim |
|---|---|---|---|
| Birim testleri | Kur hesabı, fiyat formatlama, token üretimi, 1688 parser | PHPUnit, lokalde her iş emrinde | Claude Code |
| Entegrasyon | API uçları (giriş, CRUD, capture, export) | Lokal test veritabanıyla | Claude Code |
| Elle doğrulama | İş emrinin kabul kriterleri | Lokal + sunucu | Claude Code + Bünyamin |
| Gerçek saha testi | 1688 gerçek sayfalar, gerçek export'un firmaya gitmesi, telefonda kullanım | Üretim ortamı | Bünyamin |

Kural: PHPUnit testleri lokalde koşar (sunucuda composer yok); PR açılmadan tüm testler yeşil olmalı.

Kural (K14): Para, kur ve durum-geçiş fonksiyonları TEST-FIRST yazılır — önce test, sonra kod. Durum makinesinin tüm geçersiz geçişleri (ör. Verilecek→Yolda, Geldi→İptal) birim testlerinde reddedildiği kanıtlanır.

## 1b. Test Kalibrasyonu (K35)

Test yazmanın maliyeti gerçek; ama bazı hataların bedeli geri alınamaz. Bu yüzden her şey
aynı derinlikte test edilmez:

| Seviye | Kapsam | Ne beklenir |
|---|---|---|
| **KRİTİK — tam test** | Para ve yuvarlama (MoneyService) · durum makinesi (tam geçiş matrisi) · kimlik doğrulama ve oturum · kurulum kilidi · medya alma ve SSRF · log redaction · kur kilidi | Sınır değerler, hata yolları, regresyon testleri. Davranış değişirse test kırılmalı |
| **SMOKE** | Diğer CRUD uçları (kategoriler, kur tarihçesi, liste/ürün listeleme) · yapılandırma dosyalarının varlığı | Mutlu yol + belirgin bir hata durumu |
| **Test edilmez** | Sunum biçimleri, log metinleri, arayüz yerleşimi | — |

**Hedef kapsam yüzdesi GÜDÜLMEZ.** Yüzde peşinde koşmak, önemsiz kodu test edip kritik
sınırları atlamaya yol açar. Ölçü şudur: *"bu davranış bozulursa para, veri veya güvenlik
kaybederiz miyiz?"* Cevap evetse kritik listeye girer.

Canlı duman testi (gerçek sunucu/DB üzerinde uçtan uca akış) her iş emrinde koşulur —
birim testlerin göremediği ortam farklarını yakalar (İE#4 ve İE#6'da birer gerçek hata
bu yolla bulundu).

## 2. Faz Kabul Testleri

### Faz 1 — Panel Çekirdeği
- [ ] Kurulum sihirbazı: gereksinim denetimi, .env üretimi, migration, admin + 2FA kurulumu, kurulum sonrası kalıcı kilit — temiz ortamda uçtan uca doğrulanır.
- [ ] Giriş: şifre + TOTP doğru çalışır, yanlış kod reddedilir, kurtarma kodu tek seferlik işler; yanlış şifre 5 denemede kilitlenir; oturum telefonda korunur.
- [ ] CSRF token'sız form isteği reddedilir; güvenlik başlıkları (CSP, HSTS, X-Frame-Options) yanıtta mevcut; `storage/` altına tarayıcıdan erişim 403 döner.
- [ ] Örnek Excel'deki ürün (¥9,00, kur 7,04 → ₺63,36) panele girildiğinde TL kuruşu kuruşuna aynı hesaplanır.
- [ ] Liste oluştur/kopyala/arşivle çalışır; kur listeye kilitlenir (ayar değişince eski liste oynamaz).
- [ ] Aktif/Pasif/Arşiv sekmeleri ve geçiş aksiyonları çalışır.
- [ ] Silinen liste/ürün çöp kutusuna düşer, geri alınabilir; kalıcı silme 30 gün kuralına uyar.
- [ ] Ürün durumu tek tıkla ilerler, tarihçe kaydolur, toplu güncelleme çalışır.
- [ ] API yanıtları docs/10 sözleşmesine uygun: zarf yapısı, hata kodları, sayfalama ve doğrulama (docs/04 §2d) sözleşme testleriyle kanıtlanır.
- [ ] Telefonda (gerçek cihaz) tüm akışlar tamamlanabilir.

### Faz 2 — Export ve Paylaşım
- [ ] Excel çıktısı referans şablonla (bkz. 02 no'lu belge EK) birebir: başlık, alt sütunlar, zemin renkleri, gömülü görsel, köprü, ₺/¥/$ biçimleri.
- [ ] Excel dosyası hem masaüstü Excel'de hem telefonda (WPS/Excel mobil) bozulmadan açılır.
- [ ] PDF'te Türkçe karakterler ve görseller doğru; A4 baskı düzgün.
- [ ] Paylaşım linki: girişsiz açılır, video oynar (telefonda test), iptal edilen link 404 verir.
- [ ] 50+ ürünlü listede export süresi makul (< 30 sn) ve bellek hatasız.
- [ ] Export alınmış listeye ürün eklenip silinebiliyor; "çıktı güncel değil" rozeti doğru yanıp sönüyor; TOPLAM satırı (adet + Yuan/TL + DDP) kuruşu kuruşuna doğru.
- [ ] Paylaş menüsü: WhatsApp/e-posta/kopyala doğru içerikle çalışır; mobilde cihaz paylaşım menüsü (Web Share API) açılır.
- [ ] **Formül enjeksiyonu (İE#4 REV2):** `=`, `+`, `-`, `@` (ve `\t`, `\r`) ile başlayan `name_original`, `vendor_name`, `detail`, `note` değerleri Excel ve CSV çıktısında formül olarak yorumlanmaz — hücre metin olarak yazılır veya başına tek tırnak eklenir. Test verisi: `=1+1`, `@SUM(A1)`, `-2+3`, `+cmd|' /C calc'!A0`. Çıktı dosyası açıldığında hiçbir hücre hesaplanmış değer göstermemeli.

### Faz 3 — Chrome Eklentisi
- [ ] En az 10 farklı gerçek 1688 ürün sayfasında (farklı satıcı/şablon) başlık, fiyat kademeleri, ana görsel, video doğru yakalanır. Test sayfa listesi bu belgeye eklenecek.
- [ ] Yanlış sayfada (arama sonucu, mağaza sayfası) eklenti nazikçe "ürün sayfası değil" der.
- [ ] Panel kapalıyken yakalanan 5 ürün, bağlantı gelince eksiksiz Gelen Kutusu'na düşer.
- [ ] Geçersiz/eski API token'ı anlaşılır hata verir.
- [ ] Eklentiden hedef liste seçilerek gönderilen ürün, Gelen Kutusu'na uğramadan doğrudan o listeye düşer.
- [ ] Varyasyonlu üründe (renk/beden) seçilen SKU'nun fiyatı doğru alınır; varyasyon bilgisi panelde ve export'ta görünür.
- [ ] Daha önce eklenmiş bir 1688 ürünü tekrar gönderildiğinde tekrar uyarısı çıkar.

### Faz 4 — Cila
- [ ] Bir aylık gerçek kullanım geri bildirim listesi kapanmış.
- [ ] Günlük yedek cron'u çalıştığı ve yedeğin geri yüklenebildiği kanıtlanmış (deneme geri yükleme yapılır).

## 3. Regresyon Kontrol Listesi

Her fazın sonunda önceki fazların kabul testleri hızlıca yeniden koşulur (özellikle export ve kur hesabı — para söz konusu, hata affetmez).
