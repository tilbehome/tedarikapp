# İŞ EMRİ #5 — Kurulum Sihirbazı + Bakım ve Public-Uyum Paketi
Faz: Faz 1 · Modül: setup + docs · Dal: `is-emri-5-sihirbaz` (PR aç; merge PM onayıyla sende)

> ÖN ŞART: PR #3 merge edilmiş olacak. Oku: docs/07 §3, docs/10, docs/04, CLAUDE.md.

## Hedef
Temiz bir sunucuya zip at → tarayıcıdan gir → sihirbaz kur → kilitle akışı uçtan uca çalışıyor; küçük çekirdek düzeltmeleri yapılmış; repo public görünürlüğe uygun ve belgeler gerçekle eşit.

## Bölüm A — Belge ve Public-Uyum (K28)
1. `sunucu-rapor.php` repodan sil.
2. `uruntedariklistesi.xlsx` → gerçek ürün/fiyat verisi içermeyen `ornek-tedarik-listesi.xlsx` ile değiştir (aynı format, uydurma tek satır).
3. `docs/04 §7`: sunucuya özel mutlak yolları ve hesap adını genelleştir (kısıtlar/bulgular kalır, `/home/<kullanıcı>/...` biçimine çevir).
4. `docs/04 §6b`'yi GERÇEK yapıya güncelle (app/Auth/, Middleware gerçek adları, setup/, bin/ — repo neyse o).
5. `docs/arastirma/` oluştur: `1688verienvanterionrapor.md` + `1688parserraporu.md` buraya taşınır/commit'lenir. `docs/fikirler/f30-gtip-motoru.md` — metni Ürün Sahibi verecek (gelmediyse rapora "bekliyor" yaz, bloklama).
6. `docs/02` M3 alan tablosuna satır: "Koli içi adet | Admin girer (1688'de yapılandırılmış alan yok — parser raporu)".
7. CHANGELOG güncelle.

## Bölüm B — Çekirdek Küçük Düzeltmeler
8. **415 middleware (docs/10 §1):** gövdeli yazma isteklerinde `Content-Type: application/json` değilse `415 + UNSUPPORTED_MEDIA_TYPE` zarfı. Testli.
9. **Redaction beyaz listesi:** `error_code` ve `request_id` anahtarları redaksiyondan muaf. Testli.

## Bölüm C — Kurulum Sihirbazı (setup/)
10. **Aktiflik kuralı:** `storage/setup.lock` yoksa sihirbaz aktif; varsa TÜM setup uçları 403 + activity_log kaydı. Kilit dosyası kurulum sonunda atomik yazılır.
11. **Adımlar (sıra zorlanır, kendi CSRF token'ı + oturumu vardır):**
    a. Gereksinim denetimi: PHP ≥ 8.4, zorunlu eklentiler (pdo_mysql, curl, gd, mbstring, zip, intl, bcmath, fileinfo, openssl), `public/media/` + `storage/` yazılabilirliği (eksikler İSİM İSİM ve çözüm öneri metniyle listelenir), HTTPS değilse uyarı.
    b. DB formu → bağlantı testi (utf8mb4 zorunlu) → `SELECT VERSION()` sonucu ekranda ve raporda (üretim DB türü kaydı).
    c. `.env` üretimi: APP_KEY (32B hex) + EXTENSION_TOKEN_SALT kriptografik üretilir, DB bilgileri yazılır, dosya izni daraltılır; **DB şifresi hiçbir loga yazılmaz.**
    d. Migration koşumu: Migrator ile, ekranda migration listesi/süreleri.
    e. Admin oluşturma: e-posta + şifre (asgari güç kuralı) → **TOTP QR** (Bacon SVG) → kod doğrulaması → **kurtarma kodları YALNIZCA BİR KEZ gösterilir** ("kaydettim" onayı olmadan ilerlemez).
    f. Bitiş: `setup.lock` yazılır, özet ekranı, panele yönlendirme.
12. **Güncelleme yolu:** `GET /api/system/status` (auth) → sürüm + bekleyen migration sayısı; `POST /api/system/migrate` (auth + CSRF) → bekleyenleri koşar. docs/10'a bölüm olarak eklenir (PM onaylı ek).
13. `bin/migrate.php` aynen kalır (lokal geliştirme yolu).

## Kapsam DIŞI
React panel, listeler/ürünler tabloları, capture, export, eklenti.

## Kabul Kriterleri
- [ ] Temiz **gerçek MySQL** ortamında (F-İE3-1) sihirbaz uçtan uca: kurulum → kilit → tekrar erişim 403. `SELECT VERSION()` çıktısı raporda.
- [ ] Gereksinim ekranı, bilinen "yazılamaz klasör" senaryosunda eksiği isim isim gösteriyor.
- [ ] `.env` doğru üretiliyor; repoda ve loglarda sır yok; kurtarma kodları ikinci kez GÖRÜNTÜLENEMİYOR.
- [ ] 415 ve redaction beyaz listesi testli; CI yeşil; PHPStan lvl6 0; CS-Fixer 0; composer audit temiz.
- [ ] Bölüm A'nın 7 maddesi birebir; `sunucu-rapor.php` ve gerçek xlsx main'de YOK.
- [ ] docs/04 §6b ile repo ağacı birebir aynı.

## Deploy Kapısı (kod değil, kayıt)
Sunucu PHP 8.4'e geçmeden ve hosting yazma-izni ticket'ı çözülmeden ÜRETİME KURULUM YAPILMAZ — kod PHP 8.3+ sözdizimi içeriyor, 8.1'de fatal verir. Rapora sunucu durumunun son hali not edilir.

## Teslim
Dal `is-emri-5-sihirbaz`, PR aç, ÇIKTI RAPORU (şablon: docs/00 §4) + sihirbaz adımlarının ekran görüntüleri/çıktıları.
