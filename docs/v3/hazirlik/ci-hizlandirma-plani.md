# CI HIZLANDIRMA PLANI + PLAYWRIGHT ALTYAPI ÖNERİSİ

> **Durum:** PLAN — **K19 onayı ALINDI (PM, 25 Ağu 2026)**, uygulama İE#22'dedir.
> rc5 kilidi sürerken CI'a ve bağımlılıklara dokunulmaz.
> Ölçümler 25 Ağu 2026 tarihli rc5 ağacında yerel makinede alınmıştır.
>
> **Onayın şartları (PM):**
> 1. CI 5'li matris — **bekçi test ZORUNLU** (§1.4). Dosya sayısı ↔ suite
>    listeleri tutmuyorsa CI kırmızı. Matris bekçi testsiz kabul edilmez:
>    "yeşil CI, koşmayan test" riskinin panzehiri budur.
> 2. Playwright — yalnız **devDependency**, **chromium tek tarayıcı**, senaryo
>    sırası **2 → 3 → 4 → 1** (görsel regresyon en sona).

---

# BÖLÜM 1 — `tests/Http` 26 dakika sorunu

## 1.1 Ölçülen durum

| Süit | Test | Süre (yerel, tek süreç) |
|---|---|---|
| `tests/Http` | **445** | **26 dk 24 sn** |
| `tests/Services` | 406 | 32 sn |
| Core+Setup+Auth+Models+Integration+Support+Production | 222 | 3 dk 33 sn |
| **Toplam** | **1073** | ~30 dk 30 sn |

Bugün CI'da bunların hepsi **tek `quality` job'unda `composer test` ile** koşuyor
(`.github/workflows/ci.yml`), yani PR başına ~30 dakika tek çekirdekte akıyor.

**Neden Http bu kadar yavaş:** her testte tam uygulama kabı kuruluyor ve
SQLite bellek şeması sıfırdan yaratılıyor (`AuthTestCase`). Test başına ~3,5 sn.
Bu bir "yavaş test" sorunu değil, **kurulum maliyetinin 445 kez ödenmesi**dir.

Ayrıca ölçülen bir kırılganlık var: **1073 testi tek süreçte koşmak yerelde
"Premature end of PHP process" ile düşüyor** (~%87'de). Bölünmüş koşum bunu da
çözüyor — yani bölme yalnız hız değil, kararlılık işidir.

## 1.2 Öneri: GitHub Actions matrisi (4 parça + hafif süitler)

```yaml
  quality:
    strategy:
      fail-fast: false          # bir parça kırmızıysa diğerleri yine raporlansın
      matrix:
        parca: [http-1, http-2, http-3, http-4, digerleri]
```

Her parça aynı kurulum adımlarını (PHP 8.4 + composer cache) paylaşır, yalnız
son adımı değişir. **PHPStan / CS-Fixer / audit yalnız bir parçada koşar**
(`digerleri`) — beş kez koşmanın anlamı yok.

Parça dağılımı, dosya başına test sayısına göre dengelenmiştir (ölçüm 25 Ağu):

| Parça | Test | Dosya | İçerik (özet) |
|---|---|---|---|
| `http-1` | 113 | 11 | DataLayer · SetupTeshis · Capture · ExportOptions · Share · Export · TransactionIntegrity · KaliteKapisi · Diagnostics · PaylasimFirma · TopluTasima |
| `http-2` | 111 | 11 | Auth · Settings · Inbox · JsonRequest · Health · SistemListesi · SetupUnlock · IlanYazici · MediaArchive · MigrationGuard · ListDuplicate |
| `http-3` | 111 | 11 | KilitEkrani · ErisimAnahtari · SetupOnarim · IsEmri19 · SharePageV4 · PanelSupport · ListImmutability · Translate · YenidenKurulum · MigrateBaseline · SetupHardening |
| `http-4` | 110 | 11 | SetupEndpoints · KesifHavuzu · ShareOnarim · ShareDownload · DesteModu · SharePageLinks · PaylasimDili · UrunCekmecesi · KurOnerisi · LoginVitrin · RevisionSequence |
| `digerleri` | 628 | — | `tests/Services` + Core/Setup/Auth/Models/Integration/Support/Production + PHPStan + CS-Fixer + audit |

**Uygulama biçimi — iki seçenek:**

| Yol | Nasıl | Artı / eksi |
|---|---|---|
| **A. PHPUnit test suite'leri** (önerilen) | `phpunit.xml`e `http-1..4` adlı dört `<testsuite>` eklenir, dosyalar adıyla listelenir | Dağılım **kayıtlıdır**, gözle görülür, yerelde de aynı komutla koşulur. Yeni dosya eklendiğinde bir parçaya yazılmalı — bunu unutmayı engellemek için §1.4'teki bekçi test |
| B. `--filter` / dosya listesi | Job içinde dosya adları sıralanır | YAML'de saklı liste; yerelde tekrar edilemez, gözden kaçar |

## 1.3 Tahmini kazanç

| Ölçü | Bugün | Matris sonrası |
|---|---|---|
| Duvar saati (PR başına) | ~30 dk | **~7–8 dk** (en yavaş parça ~6,5 dk + kurulum ~1 dk) |
| Toplam makine süresi | ~30 dk | ~35 dk (kurulum 5 kez ödenir) |
| Kararlılık | tek süreçte çökme riski | her parça 110 test — ölçülen çökme eşiğinin çok altında |

> Kazanç **duvar saatindedir**, toplam işlemci süresinde değil. Amaç geliştiricinin
> PR'da beklediği süreyi kısaltmaktır; GitHub Actions ücretsiz kotasında 5 paralel
> job sorun değildir (mevcut iş akışında zaten 5 job var).

## 1.4 Riskler ve önlemler

| Risk | Sonuç | Önlem |
|---|---|---|
| **Yeni test dosyası hiçbir parçaya yazılmaz** → sessizce hiç koşmaz | En tehlikelisi bu: yeşil CI, koşmayan test | **Bekçi test:** `tests/Support/SuitKapsamiTest.php` — `tests/Http` altındaki dosya sayısı ile dört suite'te listelenen dosya sayısını karşılaştırır, tutmuyorsa kırmızı. (`E2eKatalogTest` deseninin aynısı: sahte kapsamı test yakalar) |
| Parçalar zamanla dengesizleşir | Bir parça 15 dk'ya çıkar | Çeyrek dönemde bir yeniden dengele; job süreleri Actions özetinde görünür |
| `fail-fast` varsayılanı diğer parçaları iptal eder | Tek hata diğer bilgileri gizler | `fail-fast: false` |
| Parçalar arası paylaşılan durum | Sızıntı/yarış | Yok: her test kendi SQLite bellek şemasını kurar; dosya sistemi `storage/` altında test başına ayrı |
| MySQL/MariaDB job'ları da yavaşlar | — | Bu plan onlara dokunmaz; `--group mysql` zaten küçük |

## 1.5 Bu planın YAPMADIĞI

- Test sayısını azaltmaz, "yavaş testleri atlamaz".
- `AuthTestCase` kurulum maliyetini optimize etmez. **İkinci aşama önerisi**
  (ölçülmeden yapılmasın): şema kurulumu bir kez yapılıp SQLite bellek
  veritabanının yedeği testler arasında geri yüklenebilir. Tahmini kazanç
  büyüktür ama test izolasyonuna dokunur — ayrı iş, ayrı ölçüm.

---

# BÖLÜM 2 — Playwright altyapı önerisi

## 2.1 Neden gerekli

`docs/v3/hazirlik/kabul-turu-v1.md` içindeki **KT-EK-1..4** maddeleri, kapsam
defterindeki dört senaryonun (`E2E-PNL-11/12/14/45`) otomatik karşılığı olmadığı
için **manuel** koşuluyor. Bunlar ekran düzeyi akışlardır: gerçek tarayıcı,
gerçek MySQL, gerçek oturum. Bugünkü araçlar (vitest + jsdom) bileşen düzeyinde
kalıyor; jsdom bir tarayıcı değildir.

Kapsam defteri kuralı da bunu söylüyor: **otomatik kanıtı olmayan senaryo yeşil
sayılmaz** — bu yüzden yedi senaryo `bekliyor` durumunda duruyor.

## 2.2 Paket ve yerleşim

| Karar | Öneri | Gerekçe |
|---|---|---|
| Paket | `@playwright/test` (yalnız **devDependency**) | Kural gereği yeni bağımlılık PM onayı ister (K19). Bu paket **çalışma zamanına girmez**; panel bundle'ına ve eklenti bundle'ına hiçbir şey eklemez |
| Yer | Depo kökünde `e2e/` (ayrı `package.json` **değil**, `frontend/`in devDependency'si olarak) | Panel testleriyle aynı zincirde kalsın; `npm audit --audit-level=high` kapısına tabi olur |
| Yapılandırma | `e2e/playwright.config.ts` — `baseURL` ortam değişkeninden, tek tarayıcı (chromium) ile başla | Üç tarayıcı üç kat süredir; kabul turu senaryoları tarayıcıya duyarlı değil |
| Seçici disiplini | **F43** (karar kaydı): seçiciler yazılmadan önce gerçek bileşen kaynağından doğrulanır; `data-test` öznitelikleri bileşenlere eklenir | İE#13 dersi; metin tabanlı seçici çeviri değişince kırılır |

## 2.3 CI'da nasıl

Ayrı bir job — `quality` matrisine karışmaz:

```yaml
  e2e:
    name: Playwright (panel akışları)
    runs-on: ubuntu-latest
    services:
      mysql: { image: mysql:8.4, ... }        # mevcut mysql-integration job'ının aynısı
    steps:
      - PHP 8.4 + composer install
      - migrate + demo veri tohumu (bin/kategori-yukle.php + demo-urun-seti.json)
      - cd frontend && npm ci && npm run build
      - php -S 127.0.0.1:8080 -t public &     # yerleşik sunucu; exec yasağı ÜRETİM içindir, CI değil
      - npx playwright install --with-deps chromium
      - npx playwright test
      - artifact: trace + screenshot (yalnız başarısızlıkta)
```

Süre tahmini: kurulum ~2 dk + dört senaryo ~1–2 dk = **~4 dk**, `quality`
matrisiyle paralel koştuğu için duvar saatine ekleme yapmaz.

## 2.4 İlk dört senaryo (KT-EK-1..4 karşılığı)

Kapsam defterindeki bekleyen dört senaryo, sırasıyla ilk dört Playwright testi olur:

| # | Senaryo (KT-EK) | Ekran | Otomatikleştirilecek kanıt | Playwright'ta zor olan |
|---|---|---|---|---|
| 1 | `E2E-PNL-11` / KT-EK-1 | Keşif | Altı sütunlu matris 1440 ve 1024 genişlikte **yatay taşma yapmaz**; dokunma hedefleri ≥44 px | Görsel hizalama gözle denetleniyor → `toHaveScreenshot()` ile **görsel regresyon**; taşma `scrollWidth <= clientWidth` ile ölçülür, hedef boyutu `boundingBox()` ile |
| 2 | `E2E-PNL-12` / KT-EK-2 | Keşif | Süzgeç + sıralama → **adres çubuğuna yansır**; adres yeni sekmede aynı ekranı getirir; kayıtlı görünüm aynı sonucu verir | En kolay dördü: `toHaveURL` + iki context. Otomasyona en uygun senaryo, ilk buradan başlanmalı |
| 3 | `E2E-PNL-14` / KT-EK-3 | Liste detay | 100+ ürünlü listede sona kaydır → başa dön: **ürün iki kez görünmez, atlanmaz, seçim kaybolmaz** | Sanal kaydırma davranışı; DOM'daki ürün kimlikleri toplanıp küme karşılaştırması yapılır (ekran görüntüsü değil, **kimlik kümesi**) |
| 4 | `E2E-PNL-45` / KT-EK-4 | Kilit ekranı | Tazeleme sayacı dolunca **TEK** istek gider, yığılma olmaz | Sayaç 600 sn (`ShareLockPage::TAZELEME_SANIYE`) → testte saat ileri sarılır (`page.clock`); ağ istekleri `page.on('request')` ile sayılır |

**Sıra önerisi:** 2 → 3 → 4 → 1. İlk üçü davranış ölçer (deterministik); görsel
regresyon (1) en kırılgan olandır ve altyapı oturduktan sonra eklenmelidir.

Bu dördü geçtiğinde kapsam defteri **74/81 → 78/81** olur; kalan üç senaryo
(`PNL-20`, `PNL-50/51`) özellikleriyle birlikte V3-B'ye devredilmiştir.

## 2.5 Riskler

| Risk | Önlem |
|---|---|
| Flaky test (zamanlama) | Sabit `waitForTimeout` YASAK; yalnız durum bekleyen assertion'lar (`toBeVisible`, `toHaveURL`). Web testing kuralı: deterministik bekleme |
| Demo veri tohumu kayarsa senaryolar kırılır | Tohum tek kaynak: `docs/v3/hazirlik/demo-urun-seti.json`; senaryolar veri sayısına değil, oluşturdukları kayda bakar |
| Yeni ekran gelince seçiciler kırılır | `data-test` öznitelikleri sözleşmedir; bileşen yeniden yazılsa da korunur |
| CI süresi büyür | Ayrı job, paralel koşar; başarısızlıkta trace artifact'ı yüklenir, tekrar koşturmaya gerek kalmaz |

---

## Özet — İE#22'ye giden iki madde

1. **CI matrisi:** `phpunit.xml`e dört `http-*` suite + `.github/workflows/ci.yml`
   matrisi + kapsam bekçisi testi. Kazanç: **~30 dk → ~7–8 dk** duvar saati.
2. **Playwright:** `frontend` devDependency + `e2e/` klasörü + ayrı CI job +
   ilk dört senaryo. Kazanç: KT-EK-1..4 manuel maddeleri otomatikleşir, kapsam
   defteri 78/81'e çıkar.

İkisi de **yeni bağımlılık** ya da **CI yapılandırma değişikliği** içeriyordu;
**K19 onayı 25 Ağu 2026'da alındı** (bekçi test zorunlu şart). Uygulama İE#22'de.
