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

## 2. Faz Kabul Testleri

### Faz 1 — Panel Çekirdeği
- [ ] Giriş: doğru şifre girer, yanlış şifre 5 denemede kilitlenir, oturum telefonda korunur.
- [ ] Örnek Excel'deki ürün (¥9,00, kur 7,04 → ₺63,36) panele girildiğinde TL kuruşu kuruşuna aynı hesaplanır.
- [ ] Liste oluştur/kopyala/arşivle çalışır; kur listeye kilitlenir (ayar değişince eski liste oynamaz).
- [ ] Ürün durumu tek tıkla ilerler, tarihçe kaydolur, toplu güncelleme çalışır.
- [ ] Telefonda (gerçek cihaz) tüm akışlar tamamlanabilir.

### Faz 2 — Export ve Paylaşım
- [ ] Excel çıktısı referans şablonla (bkz. 02 no'lu belge EK) birebir: başlık, alt sütunlar, zemin renkleri, gömülü görsel, köprü, ₺/¥/$ biçimleri.
- [ ] Excel dosyası hem masaüstü Excel'de hem telefonda (WPS/Excel mobil) bozulmadan açılır.
- [ ] PDF'te Türkçe karakterler ve görseller doğru; A4 baskı düzgün.
- [ ] Paylaşım linki: girişsiz açılır, video oynar (telefonda test), iptal edilen link 404 verir.
- [ ] 50+ ürünlü listede export süresi makul (< 30 sn) ve bellek hatasız.

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
