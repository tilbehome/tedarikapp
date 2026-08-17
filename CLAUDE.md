# CLAUDE.md — tedarikapp Geliştirme Anayasası

Bu dosya Claude Code için bağlayıcıdır. Her oturumda geçerlidir, iş emirleri bu kuralların ÜZERİNE gelir, yerine geçmez.

## 1. Proje ve Kapsam Disiplini
- Bu uygulama 1688.com tedarik listesi yönetim sistemidir: panel + Chrome eklentisi + Excel/PDF/paylaşım çıktıları. Tek gerçek kaynak `docs/` klasörüdür.
- SADECE aktif iş emrinin kapsamındaki işi yap. İş emrinde olmayan özellik, refactor, "iyileştirme" EKLEME — fikrin varsa ÇIKTI RAPORU'na öneri olarak yaz.
- Belgelerle çelişki görürsen: belge kazanır; çelişkiyi raporla, kafana göre çözme.

## 2. Teknoloji Sınırları (değiştirilemez — karar K5/K13)
- Backend: PHP + Slim + MySQL (PDO). Frontend: React + Vite. Eklenti: vanilla JS, Manifest V3. Sürümlerin TEK gerçek kaynağı: `docs/TECH-BASELINE.md` (İE#9 §F14) — belgelere sürüm numarası yazma, oraya referans ver.
- YASAK: Python/FastAPI, PostgreSQL, Redis, Docker, Node backend, framework değişikliği.
- Yeni composer/npm paketi eklemeden önce ÇIKTI RAPORU'nda gerekçesiyle bildir; büyük bağımlılıklar PM onayı ister.
- Yeni bağımlılık/özellik eklerken `docs/SUNUCU-PROFILI.md` ile uyumu doğrula; uyumsuzlukta uygulamadan önce PM'e bildir (K41).
- Sunucu kısıtları (docs/04 bölüm 7): dış istek SADECE cURL (`file_get_contents` ile URL açmak YASAK), `exec/system/proc_open` YASAK, `mail()` YASAK, yazma sadece `storage/` altına, vendor lokalde kurulur.

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

## 3. Para ve Kur Kuralları (taviz yok)
- Para değerleri ASLA float ile tutulmaz/hesaplanmaz. DB: `DECIMAL(12,2)` (kurlar `DECIMAL(12,4)`). PHP: bcmath ile hesap, string taşıma. JS: görüntüleme dışında aritmetik yapma; yapılacaksa tam sayı kuruş üzerinden.
- Kur, listeye kilitlenir (docs/08 K4). TL değerleri DB'ye yazılmaz, her zaman `orijinal fiyat × listenin kilitli kuru` olarak hesaplanır.
- Kur ve para fonksiyonları TEST-FIRST yazılır: önce PHPUnit testi, sonra fonksiyon.

## 4. Durum Makinesi (docs/04'teki şemaya harfiyen uy)
- Ürün: `Verilecek → Verildi → Yolda → Geldi`; her durumdan `İptal`e geçilebilir (Geldi hariç); düzeltme için yalnızca BİR adım geri alınabilir. Atlama yok (Verilecek'ten Yolda'ya geçilemez).
- Liste: `Taslak → İletildi → Sipariş Verildi → Tamamlandı` (+İptal). `Tamamlandı` ancak tüm ürünler Geldi veya İptal ise mümkündür.
- Bu kurallar backend'de zorlanır (API geçersiz geçişi reddeder), sadece arayüzde değil.

## 5. Güvenlik
- SQL: yalnızca prepared statements/parametreli sorgu. String birleştirmeyle sorgu YAZMA.
- Çıktıda XSS koruması: kullanıcı/eklenti kaynaklı her veri escape edilir (özellikle paylaşım sayfası — dışa açık tek yüzey).
- Sırlar yalnızca `.env`'de; koda, loga, repoya sır yazılmaz.
- Eklenti isteği Bearer token doğrulaması olmadan işlenmez; paylaşım token'ları kriptografik rastgele üretilir.

## 6. Kod Kalitesi
- Placeholder/geciştirme YASAK: "// devamı burada", "TODO: implement", boş catch bloğu yazılmaz. Her fonksiyon eksiksiz ve hata yönetimli teslim edilir.
- Hatalar `storage/logs/` altına loglanır (tarih, bağlam); kullanıcıya teknik hata dökülmez.
- Standart: PSR-12 (PHP), ESLint (JS). Arayüz dili Türkçe, kod/değişken adları İngilizce.
- Veri sözleşmeleri sabittir: eklenti→API JSON şeması docs/04'te, panel API sözleşmesi (zarf, hata kodları, uç gövdeleri) docs/10'da tanımlıdır; alan adı uydurma/değiştirme yapılmaz, şema değişikliği PM kararıdır.

## 7. Teslim
- Her iş emri: kendi dalı (`is-emri-N-slug`), standart commit (`tip(kapsam): açıklama`), PR, ve docs/00'daki şablonla ÇIKTI RAPORU.
- İş emrinin kabul kriterleri madde madde ✓/✗ raporlanmadan iş bitmiş sayılmaz.
