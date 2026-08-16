# İŞ EMRİ #2 (v4) — Belge Kapanışı: Kütüphane Listesi, Hızlı Paylaşım, Havuz Senkronu, Numara Düzeltmesi
Faz: Faz 0 kapanışı · Modül: docs · Dal: `is-emri-2-belge-bakim` (PR aç, merge ETME — denetim PM'de)

> Not: v1–v3'teki 5 denetim bulgusu `c1c1baf` (K18 Tanım Tamamlama Paketi) ile kapandı ve PM tarafından içerik olarak kabul edildi — o maddeler bu emirden DÜŞTÜ.

## Hedef
Karar numarası çakışması giderilmiş; onaylı kütüphane listesi (K19) anayasada; hızlı paylaşım kararı (K20) beş belgede; fikir havuzu 6 yeni fikirle senkron; protokolde numara kuralı tanımlı.

## Ön Koşul
- docs/08'deki karar tablosunun SON numarasını kontrol et. Aşağıdaki K19/K20 atamaları "son satır K18 = Tanım Tamamlama Paketi" varsayımıyladır; tablo farklıysa sıradaki iki boş numarayı kullan ve raporda belirt.

## Yapılacaklar
1. **docs/00-calisma-protokolu.md** — "## 2. Çalışma Döngüsü" bölümünün Kurallar listesine madde ekle: "- Karar (K#) numaralarını yalnızca PM atar. Ürün Sahibi'nin PM döngüsü dışındaki doğrudan talimatları geçerlidir; ancak yapılan iş PM'e raporlanır ve karar kaydına PM'in verdiği numarayla işlenir (çakışma önleme)."
2. **CLAUDE.md §2 sonuna** şu alt bölümü ekle (birebir):
   ```
   ### Onaylı Kütüphane ve Araç Listesi (K19 — liste dışı her paket PM onayı ister)
   - Backend (composer): slim/slim ^4 · slim/psr7 · vlucas/phpdotenv · monolog/monolog ·
     phpoffice/phpspreadsheet · mpdf/mpdf · robthree/twofactorauth · bacon/bacon-qr-code
   - Backend geliştirme (require-dev): phpunit/phpunit · phpstan/phpstan (seviye 6+) ·
     friendsofphp/php-cs-fixer (PSR-12)
   - Frontend (npm): react · react-dom · react-router-dom · zustand · axios ·
     tailwindcss · lucide-react — geliştirme: vite · eslint · prettier
   - Chrome eklentisi: SIFIR harici bağımlılık (vanilla JS, Manifest V3)
   - Kurallar: sürümler composer.lock/package-lock ile sabitlenir ve repoya girer;
     her faz sonunda güvenlik denetimi (composer audit / npm audit) raporlanır;
     PHPStan ve CS-Fixer her PR öncesi temiz geçer.
   ```
3. **K20 — Hızlı Paylaşım belge işlemesi:**
   a) `docs/02-moduller-kapsam.md` M7 sonuna: "- **Hızlı paylaşım (K20):** liste detayında hazır butonlar — WhatsApp (wa.me, hazır mesaj: liste adı + paylaşım linki), e-posta (mailto, konu+gövde dolu taslak), linki kopyala, mobilde cihazın paylaşım menüsü (Web Share API). Gönderim kullanıcının kendi uygulamasından yapılır; sunucudan otomatik mail/WhatsApp gönderimi kapsam DIŞIdır (fikir havuzu)."
   b) `docs/03-kullanici-akislari.md` — "Siparişin firmaya iletilmesi" akışındaki "Dosyaları + linki firmaya WeChat/WhatsApp/e-posta ile ilet..." adımını şununla değiştir: "\"Paylaş\" menüsüyle linki firmaya ilet: WhatsApp hazır mesajı, e-posta taslağı veya kopyala; Excel/PDF'i aynı kanaldan eklersin. Firma linkten videoları da izler."
   c) `docs/05-yol-haritasi.md` Faz 2 kabul kriterlerine: "- Paylaş menüsü: WhatsApp hazır mesajı ve e-posta taslağı doğru içerikle (liste adı + link) açılıyor."
   d) `docs/06-test-ve-kabul.md` Faz 2 listesine: "- [ ] Paylaş menüsü: WhatsApp/e-posta/kopyala doğru içerikle çalışır; mobilde cihaz paylaşım menüsü (Web Share API) açılır."
   e) `docs/08` karar tablosuna: "| K20 | 16 Ağu 2026 | Hızlı paylaşım butonları (WhatsApp wa.me + mailto + kopyala + Web Share) Faz 2 kapsamına alındı; sunucudan otomatik gönderim (SMTP / WhatsApp Business API) fikir havuzunda | Sıfır maliyet/API ile iletme akışının doğal parçası; otomatik gönderim solo operasyonda maliyetine değmez |" — ve fikir havuzundaki "WhatsApp'a hazır mesaj şablonu" ifadesini "sunucudan otomatik gönderim (SMTP mail / WhatsApp Business API)" ile değiştir.
4. **docs/08 §3 Fikir Havuzu** — bölümü numaralı listeye çevir (F1'den başlat, mevcut fikirler sırayla) ve şu 6 yeni fikri PM değerlendirmesiyle ekle:
   - PWA: panel ana ekrana kurulabilir uygulama gibi davranır — düşük maliyet, yüksek değer · Faz 4 güçlü aday
   - Mal kabul sayım modu: konteyner gelince telefondan ürünleri tek tek "Geldi" işaretleme + eksik/hasar notu · Faz 4
   - Fiyat değişim uyarısı: aynı ürün tekrar yakalandığında eski/yeni Yuan fiyat karşılaştırması (external_id + fiyat geçmişi) · Faz 4
   - Excel özet sayfası: çıktının 2. sekmesinde kategori bazlı adet/tutar özeti · Faz 4
   - Yedeklerin uzak kopyası: gece yedeğinin Google Drive'a da atılması · Faz 4
   - Tedarikçi kartları: firma bazlı liste geçmişi ve iletişim notları · v1.1 (çoklu firma ihtiyacı doğunca)
5. **Kontrol maddesi:** `c1c1baf` ile gelen `remember_tokens` için 04'teki indeks listesinde `remember_tokens(selector)` var mı? Yoksa ekle.
6. **CHANGELOG.md** — "[Yayınlanmadı] / Değişti" altına: "Onaylı kütüphane listesi (K19), hızlı paylaşım kararı (K20), fikir havuzu senkronu (F-numaralı 12 fikir), karar numarası kuralı (protokol)."

## Kapsam DIŞI
- Uygulama kodu, migration, docs/10 içerik değişikliği YOK.

## Kabul Kriterleri
- [ ] Karar tablosunda numara çakışması yok; K19/K20 (veya raporlanan gerçek numaralar) doğru yerde.
- [ ] CLAUDE.md'de onaylı liste birebir; 00'da numara kuralı var.
- [ ] K20 beş belgeye harfiyen işlendi; havuz F-numaralı ve 12 fikir içeriyor.
- [ ] Tüm dahili linkler çalışıyor (yeniden tarama).
- [ ] Dal `is-emri-2-belge-bakim`, PR açık, merge edilmemiş; commit: `docs(bakim): K19-K20 islemesi, havuz senkronu, numara kurali (IE#2)`.

## Test
- Link taraması + 08 karar tablosunun son hali (numara dizisi) rapora eklenir.

## Teslim
PR aç, ÇIKTI RAPORU şablonuyla raporla. PM GitHub erişimi açılırsa PR üzerinden, açılmazsa rapor üzerinden onay verilecek; merge onaydan sonra.
