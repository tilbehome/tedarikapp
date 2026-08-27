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

## 6. KABUL SONUCU — v1.0 KAPANIŞ (27 Ağustos 2026)

**Ürün Sahibi kararı: v1.0 İLAN EDİLDİ.**

Kabul, KT listesinin madde madde işaretlenmesiyle değil, **gerçek ürünlerle
gerçek bir liste ve gerçek bir paylaşım bağlantısı** üretilerek verildi (Ürün
Sahibi kararı): 1688'den yakalanan ürünler listeye düştü, TL karşılıkları
listenin kilitli kuruyla hesaplandı, çıktı ve paylaşım sayfası firmaya
gönderilebilir hâlde üretildi.

| Kalem | Sonuç |
|---|---|
| Kabul yöntemi | Saha koşumu — gerçek ürün, gerçek liste, gerçek paylaşım bağlantısı |
| rc8 canlıda | 27 Ağustos 2026 |
| Sürüm adı | `1.0.0` (tek kaynak `App\Core\AppVersion`) |
| Eklenti | `2.0.2` |
| Kalan bulgular | İE#22 defterine devredildi (aşağıda) |

### 27 Ağustos saha turu — kapanan bulgular

| # | Bulgu | Durum |
|---|---|---|
| A1 | Sayfa içi çekmece kaydırılamıyor, "Yakala ve Gönder"e ulaşılamıyor | **kapandı** — çekmece 100vh, gövde kendi içinde kaydırılıyor (E2E-EKL-32) |
| A2 | Eşleşme yokken de "TR önerisi" rozeti konuyor | **kapandı** — rozet yalnız gerçek öneride (E2E-EKL-33) |
| A3 | Varyant çiplerinde HTML varlıkları literal görünüyor | **kapandı** — G9'un eklenti ikizi `core/metin.ts` (E2E-EKL-33 + birim) |
| A4 | Native `title` balonu ekran dışına taşıyor | **kapandı** — balon kaldırıldı, kopyala düğmesi kondu (E2E-EKL-33) |
| A5 | Inline düğme mağaza adının üstüne biniyor | **kapandı** — düğme satın alma bloğunun üstünde, tam genişlik (E2E-EKL-34) |
| A7 | 4 sn zaman aşımı yavaş gönderimi "hata" gösteriyor | **kapandı** — 30 sn + aynı kimlikle idempotent kontrol (birim: `gonderim.test.ts`) |
| A8 | ZH görünümde ne düğme ne pill çıkıyor | **kapandı** — montaj nöbeti (MutationObserver) + metin tabanlı hedef (E2E-EKL-34) |

### İE#22 defterine devredilenler

- ~~A5/A8 fikstürleri sentetiktir.~~ **KAPANDI (27 Ağu 2026):** Ürün Sahibi'nin
  gerçek sayfa dökümleri (`e2e/fixtures/1688/detay-zh.html`,
  `detay-tr-alitrading.html`) repoya alındı ve EKL-34 onlara bağlandı. Gerçek
  DOM iki şeyi düzeltti: sipariş modülü iki skinde de `[data-spm="submitOrder"]`
  düğümüdür ve dökümler OTURUMSUZ alındığı için sipariş düğmelerinin yerinde
  giriş çağrısı vardır — yalnız 立即订购/加入进货单 metinlerine bakan liste bu
  sayfalarda hiçbir şey bulamazdı. Kalan sınır: `_files` klasörleri repoya
  girmediği için dış CSS yüklenmiyor; ağaç gerçek, DÜZEN gerçek sayfanın
  pikseli değil.
- MultiPHP 8.3 kurulum adımı (K84-EK) · medya yedeği (F-03) · MariaDB işinin
  koşula bağlanması (K5) · "PHP 8.1 uyum" işinin ruleset'ten düşürülmesi —
  ayrıntı `docs/v3/hazirlik/ie22-on-analiz.md`.

