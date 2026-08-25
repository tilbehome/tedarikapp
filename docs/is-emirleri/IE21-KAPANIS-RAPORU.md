# İŞ EMRİ #21 — KAPANIŞ RAPORU (TASLAK · kabul sonucu bekliyor)

> Bu rapor **kabul turu sonucu eklenince** tamamlanır. Şu an tamamlanmış olan:
> teslim edilen iş, kanıtlar ve sayılar. Eksik olan: KT-001..045 + KT-EK-1..4
> sonucu ve Ürün Sahibi kararı (§6).
>
> Aday paket: `dist/tedarikapp-v1.0.0-rc5.zip` · sha256
> `92a8276a5e9d48e59df354c2f740c655b383b76656e2aa632c07d8b6c381f260`
> · panel damgası `v3-faz1 @ 5aead15 (temiz)`.

## 1. Emrin kapsamı ve karşılığı

| Bölüm | İstenen | Durum | Kanıt |
|---|---|---|---|
| A1–A5, A8 | Eklenti v2: sayfa içi panel, akış, mükerrer, kuyruk, disclosure | ✅ | `extension/ui/v2/*`, `extension/core/*`, vitest 122 |
| A6 | Seçici sürümleme | ✅ | `extension/core/secici.ts` + `secici.test.ts` |
| A7 | Eklenti E2E senaryoları | ✅ 29/29 | `extension/tests/v2Senaryolari.test.ts`, `frontend/src/eklenti/*.test.ts` |
| A9 | Store yayın paketi | ✅ | `bin/store-paketi.php`, `docs/eklenti-store.md`, manifest politika denetimi |
| B1 | Keşif havuzu (sistem listesi) | ✅ | `app/Services/Inbox/SistemListesi.php` + guard testleri |
| B2 | Liste komuta merkezi (5B stepper dahil) | ✅ | `frontend/src/screens/liste/*` |
| B3 | Ürün çekmecesi | ✅ | `UrunCekmecesi.tsx` + panel testleri |
| B4 | Gelen Kutusu deste modu | ✅ | `InboxController::deck()` + HTML entity düzeltmesi |
| B5–B14 | Kur · paylaşım · kilit ekranı · şablonlar · skor · kategori · çeviri · marka · kuyruk · sihirbaz 2FA | ✅ | `docs/is-emirleri/IE21-DURUM.md` madde tabloları |
| B15 | E2E kapsam defteri + sahte kapsam kırmızısı | ✅ | `e2e-kapsam-defteri.json` + `E2eKatalogTest` |
| C2/C3/C5 | Kalibrasyon, sınav, paketleme | ✅ | skor sınavı %82 bant isabeti · `bin/ceviri-sinavi.php` |
| EK-4/EK-5 | K81, tazeleme sayacı, WhatsApp köprüsü, SharePage/PDF yerelleştirme | ✅ | `ShareTexts` 51 anahtar × 3 dil · `testK81_ISTISNA_DISINDA_TEK_DIL` |
| D1–D7 | Canlı saha bulguları | ✅ | `IE21-DURUM.md` D5/D6/D7 bölümleri |

## 2. Sayılar

| Ölçü | Değer |
|---|---|
| PHPUnit | **1073** (Http 445 · Services 406 · diğer 222) |
| Vitest panel / eklenti | 201 / 122 |
| E2E kapsam defteri | 74/81 kapsandı · 7 bekliyor (devir gerekçeli) |
| PHPStan / CS-Fixer | temiz / 337 dosya temiz |
| tsc / ESLint | temiz / 0 uyarı |
| Yeni karar | K76–K81 |
| Yeni migration | 0027 (çeviri belleği sürümü), 0028 (kuyruk sertleştirme), 0029/0030 (ilan) |

## 3. Kapsam dışı bırakılanlar (PM kararlı)

| Madde | Karar | Devir |
|---|---|---|
| `E2E-PNL-20`, `E2E-PNL-50/51` | Özellikleri v1.0 kapsamında değil; test özelliksiz yazılamaz | V3-B |
| `E2E-PNL-11/12/14/45` | Kabul turuna manuel madde (KT-EK-1..4); Playwright altyapısı yok | İE#22 |
| Global (TR arayüzlü) 1688 sayfa varyantı | v1.0 için Çince detay görünümü yeterli | V3-E/K |
| `tests/Http` süresi (26 dk) | v1.0'ı geciktirmeme kararı | İE#22 (CI paralelleştirme) |
| `LlmIstemci` arayüz arkasına alınması | D6 uçtan uca testini mümkün kılar; şimdi kapsam değil | İE#22 |

## 4. Bu turda öğrenilenler (tekrar etmemesi için yazıldı)

1. **"Alan dolu" ile "alan doğru" aynı şey değil.** D6'da TR alanları doluydu;
   sistem bunu "çevrildi" saydı. Ölçüt, verinin VARLIĞI değil KAYNAĞI olmalıydı.
2. **Paylaşılan hostingde hiçbir PHP fonksiyonu varsayılamaz.** D7'de `getmypid`
   kapalıydı ve kuyruk hiç koşmadı. Süreç/sistem fonksiyonları `function_exists`
   ile korunmadan çağrılmaz.
3. **İki yüzey aynı veriye iki yoldan bakarsa er geç ayrışır.** D5'te popup ile
   sayfa içi panel farklı davrandı; çözüm ortak modüldü, yama değil.
4. **Sessiz hata yutmak, kullanıcıya "bozuk" izlenimi verir.** `catch { return [] }`
   üç ayrı yerde bulguya dönüştü.
5. **Sahte kapsam kendini gizler.** `E2eKatalogTest` üç kez "kapsandı denip
   yazılmamış" senaryo yakaladı; defterin test alanı gerçek dosya adının
   parçası olmak zorunda.

## 5. Teslim edilen paketler

| Paket | SHA256 | Not |
|---|---|---|
| `tedarikapp-v1.0.0-rc5.zip` | `92a8276a…f260` | Kabul turu adayı; terfi prosedürü `docs/v3/hazirlik/rc5-terfi-proseduru.md` |
| `tedarikapp-eklenti-2.0.0-chrome.zip` | `6d90bad0…9546a` | Unlisted yayın; ekran görüntüleri kabul turu sonrası çekilecek |

## 6. KABUL SONUCU — _(kabul turu sonrası doldurulacak)_

| Alan | Değer |
|---|---|
| Tur tarihi | _(doldurulacak)_ |
| KT-001..045 | _(geçen / kalan)_ |
| KT-EK-1..4 | _(geçen / kalan)_ |
| Bulgular | _(varsa madde madde, karar ve düzeltme commit'i ile)_ |
| Ürün Sahibi kararı | _(v1.0 İLAN / rc6 turu)_ |
| Final paket | _(v1.0.0 sha256 + terfi denetimi çıktısı)_ |
