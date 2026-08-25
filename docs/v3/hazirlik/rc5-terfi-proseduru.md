# rc5 → v1.0.0 TERFİ PROSEDÜRÜ

> Amaç: kabul turu geçildikten sonra **içeriği birebir aynı** olan final paketi
> üretmek. Terfi bir "yeniden derleme" değildir; aynı ağacın yeniden damgalanmasıdır.
> Fark çıkarsa **terfi İPTAL** edilir (aşağıda kural).
>
> Aday: `dist/tedarikapp-v1.0.0-rc5.zip`
> · sha256 `92a8276a5e9d48e59df354c2f740c655b383b76656e2aa632c07d8b6c381f260`
> · panel damgası `v3-faz1 @ 5aead15 (temiz)` · 2197 dosya · 30.84 MB

## 0) Ön koşullar (hepsi doğrulanmadan başlama)

| # | Koşul | Nasıl doğrulanır |
|---|---|---|
| 1 | Kabul turu GEÇTİ | `docs/v3/hazirlik/kabul-turu-v1.md` — KT-001..045 + KT-EK-1..4 ✓ işaretli, PM onayı yazılı |
| 2 | Çalışma ağacı TEMİZ ve uç `5aead15` (ya da PM'in onayladığı yeni uç) | `git status --short` boş · `git rev-parse --short HEAD` |
| 3 | rc5'ten sonra **kod commit'i yok** | `git log --oneline 5aead15..HEAD -- app bin frontend/src extension public config migrations` boş olmalı |
| 4 | Panel damgası aynı commit'te | `cat public/panel/BUILD.json` → `commit` = HEAD kısası, `temiz: true` |

> **3 numaralı koşul kritik:** kabul turu sırasında koda dokunulduysa tur
> geçersizdir; terfi değil, yeni bir aday paket (rc6) üretilir ve tur yeniden
> koşulur. Bu bir prosedür kuralıdır, tartışmaya açık değildir.

## 1) Tek komut (final paket)

```bash
# vendor üretime çekilir (dev bağımlılığı pakete GİRMEZ)
php /c/Users/PC/tools/composer.phar install --no-dev --optimize-autoloader

# PANELİ YENİDEN DERLEME (koşul 4 sağlanıyorsa): damga dosyası `public/panel/BUILD.json`
# üretim ZAMANINI taşır; yeniden derlemek onu değiştirir ve içerik eşitliği
# denetiminde açıklanması gereken bir fark üretir. Damga zaten HEAD'i gösteriyorsa
# DOKUNMA. Yalnız koşul 4 sağlanmıyorsa: cd frontend && npm run build && cd ..

# FİNAL PAKET
php bin/release.php --panel-dal=v3-faz1 --version=v1.0.0

# geliştirme bağımlılıkları geri (PHP 8.4 ikilisi ile — php-cs-fixer 8.4 ister)
"C:/Users/PC/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe" \
  /c/Users/PC/tools/composer.phar install
```

`bin/release.php` çıktısı şu satırları basar ve **rapora aynen yapıştırılır**:
zip yolu · boyut · dosya sayısı · **sha256** · panel damgası (dal @ commit,
temiz mi) · sürüm.

## 2) İÇERİK EŞİTLİĞİ DENETİMİ (terfinin asıl kapısı)

rc5 ile v1.0.0 arasında **yalnız bir dosyanın** farklı olması BEKLENİR:

| Dosya | Durum | Neden |
|---|---|---|
| `app/Core/AppVersion.php` | **beklenen fark** | Sürüm damgası: `'1.0.0-rc5'` → `'1.0.0'`. Repodaki dosya değişmez; damga yalnız zip'e kopyalanan içeriğe uygulanır. |
| `MANIFEST.txt` | fark (denetim dışı) | `# surum:` satırı ve `AppVersion.php` özeti değişir; karşılaştırmanın kendisi bu dosyayı okur. |
| `public/panel/BUILD.json` | **fark ÇIKMAMALI** | Panel yeniden derlenirse üretim zamanı değişir. Bu yüzden koşul 4 sağlanıyorsa panel YENİDEN DERLENMEZ. Fark çıktıysa: `dal`, `commit`, `temiz` alanları aynı mı diye bakılır; yalnız `zaman` farklıysa PM onayıyla kabul edilir, başka alan farklıysa TERFİ İPTAL. |
| `vendor/composer/installed.php` | **fark ÇIKMAMALI** | Composer kök paketin git referansını buraya yazar; aynı commit'te üretildiği için aynı kalmalıdır. Fark çıktıysa terfi başka bir commit'te yapılıyor demektir → TERFİ İPTAL. |

> Bu üç satır tahmin değil ÖLÇÜMDÜR: rc4→rc5 karşılaştırması koşulduğunda tam
> olarak bu dosyalar (artı D6/D7'de değişen altı kaynak dosya) farklı çıktı.

Denetim (her iki zip'in MANIFEST'i karşılaştırılır):

```bash
php -r '
$a = new ZipArchive(); $b = new ZipArchive();
$a->open("dist/tedarikapp-v1.0.0-rc5.zip"); $b->open("dist/tedarikapp-v1.0.0.zip");
$oku = function (ZipArchive $z): array {
    $satirlar = [];
    foreach (explode("\n", (string) $z->getFromName("MANIFEST.txt")) as $s) {
        $s = trim($s);
        if ($s === "" || $s[0] === "#") { continue; }
        [$hash, $yol] = array_pad(preg_split("/\s+/", $s, 2), 2, "");
        $satirlar[$yol] = $hash;
    }
    return $satirlar;
};
$x = $oku($a); $y = $oku($b);
$fark = [];
foreach ($x as $yol => $hash) { if (($y[$yol] ?? null) !== $hash) { $fark[] = $yol; } }
foreach ($y as $yol => $hash) { if (!isset($x[$yol])) { $fark[] = $yol . " (YENI)"; } }
sort($fark);
echo "dosya sayisi: rc5=" . count($x) . " final=" . count($y) . "\n";
echo "farkli dosyalar:\n  " . (count($fark) ? implode("\n  ", $fark) : "(yok)") . "\n";
'
```

**Beklenen çıktı:**

```
dosya sayisi: rc5=2197 final=2197
farkli dosyalar:
  app/Core/AppVersion.php
```

Listede başka bir yol görünüyorsa yukarıdaki tabloya bakılır; tabloda
karşılığı yoksa **terfi iptaldir**.

## 3) TERFİ İPTAL KURALI

Aşağıdakilerden **biri** olursa terfi durdurulur, final zip **silinir** ve PM'e
bildirilir:

- Dosya sayısı eşit değil.
- `app/Core/AppVersion.php` dışında, yukarıdaki tabloda açıklanmayan farklı dosya var.
- `vendor/composer/installed.php` farklı (terfi aynı commit'te yapılmıyor demektir).
- Panel damgası farklı bir commit ya da `temiz: false` diyor.
- `bin/release.php` doğrulaması başarısız (script zaten zip'i kendisi siler).
- Kabul turu sırasında kod commit'i atılmış (koşul 3).

İptal sonrası yol: düzeltme → yeni aday paket (rc6) → **turun etkilenen
maddeleri** yeniden koşulur (PM hangi maddelerin yeniden koşulacağına karar verir).

## 4) Terfi sonrası (aynı oturumda tamamlanır)

1. `CHANGELOG.md` → `## [1.0.0] — TASLAK ...` başlığı gerçek tarihe çevrilir,
   taslak uyarı bloğu silinir, final sha256 yazılır.
2. `docs/v3/hazirlik/surum-notlari-v1.md` başlığındaki "TASLAK" kaldırılır.
3. `docs/is-emirleri/IE21-KAPANIS-RAPORU.md` kabul sonucu tablosuyla tamamlanır.
4. Etiket: `git tag -a v1.0.0 -m "tedarikapp v1.0.0"` + `git push --tags`.
5. Store paketi: eklenti zip'i (`tedarikapp-eklenti-2.0.0-chrome.zip`) ve
   ekran görüntüleri (kabul turu SONRASI panel dolu haldeyken Ürün Sahibi
   çekecek) `docs/eklenti-store.md` yönergesiyle yüklenir.
6. Canlıya kurulum: `docs/07-deploy-runbook.md` + temiz kurulum eki.
7. **DB SÜRÜM DAMGASINI EŞİTLE** — aşağıdaki §5. Atlanırsa sihirbaz
   "dosyalar 1.0.0 · veritabanı 0.12.1-beta" der ve bunu SAĞLIKLI sayar.

## 5) DB SÜRÜM DAMGASI (D8 saha bulgusu, 25 Ağu 2026)

**Bulgu:** canlıda dosyalar `1.0.0-rc5`, veritabanındaki kurulu sürüm kaydı
`0.12.1-beta` kaldı. Sihirbaz bunu **SAĞLIKLI** raporluyor ve eşitleme adımı
sunmuyor.

**Sebep (koddan doğrulandı):** `SetupSituation::kararVer()` içinde
`SURUM_UYUSMAZLIGI` yalnız **bekleyen migration varken** üretilir
(`if ($bekleyen > 0) { if (sürüm farklı) return SURUM_UYUSMAZLIGI; }`).
Bekleyen 0 + damga eski → akış `SAGLIKLI`ye düşer ve SAĞLIKLI metni
`$surum['kurulu']` değerini, yani **eski damgayı** basar. Damgayı tazeleyen
uç (`POST /api/setup/update` → `surumKaydet()`) vardır ama SAĞLIKLI durumunda
kullanıcıya sunulan eylemler yalnız "Panele git" ve "Temiz kurulum"dur.

**Bu, veriyi etkilemez** — damga bir kayıttır, şema değil. Ama teşhis motorunun
tek işi "hangi sürüm kurulu?" sorusuna doğru cevap vermektir; yanlış cevap veren
teşhis, bir sonraki arızada yanıltır.

### Terfi sonrası eşitleme (v1.0.0 kurulduktan sonra, canlıda)

| Adım | Ne yapılır | Doğrulama |
|---|---|---|
| 1 | `/setup` açılır | Teşhis rozetinde sürüm satırı okunur: "dosya X · kurulu Y" |
| 2 | X = Y ise **yapılacak bir şey yok** | — |
| 3 | X ≠ Y ise: sahiplik doğrulaması (`Sahipliği doğrula` → parola + hesapta 2FA varsa kod) ile **yeniden kurulum bileti** alınır | Bilet alındı |
| 4 | `POST /api/setup/update` koşulur ("Güncellemeyi çalıştır" eylemi bu ucu çağırır) | Yanıtta `onceki_surum` ve yeni sürüm görünür |
| 5 | `/setup` yenilenir | Sürüm satırında dosya = kurulu |

> Uç **yıkıcı değildir**: bekleyen migration yoksa `Migrator::run()` hiçbir şey
> yapmaz, yalnız `settings['system.app_version']` tazelenir.

**Son çare (yalnız sihirbaz erişilemiyorsa, PM onayıyla):** damga tek satırlık
bir ayar kaydıdır ve elle yazılabilir —

```sql
UPDATE settings SET value = '1.0.0' WHERE `key` = 'system.app_version';
```

Bu yol **tercih edilmez**: sihirbaz üzerinden gitmek, aynı anda bekleyen
migration olup olmadığını da denetler. SQL yolu bu denetimi atlar.

### İE#22'ye devir (kozmetik, kalıcı çözüm)

**PM KARARI (25 Ağu 2026): SEÇENEK B ONAYLI.** Sekiz durumlu teşhis sözleşmesi
(D2-REV) **bozulmaz**; yeni durum açılmaz. Yapılacak iki şey:

1. `SetupSituation::eylemler()` SAĞLIKLI dalına, **yalnız `surum['ayni'] === false`
   iken görünen** `damgayi_esitle` eylemi eklenir (yıkıcı değil; `POST /api/setup/update`
   ucunu çağırır — uç zaten var).
2. SAĞLIKLI açıklaması fark varsa **iki değeri birden** basar: "dosya X · kurulu Y".
   Bugün yalnız `surum['kurulu']` basılıyor, yani ekranda eski damga "kurulu sürüm"
   diye görünüyor — bulgunun ikinci yarısı budur.

Reddedilen seçenek (kayıt için): `SAGLIKLI` içinde ayrı bir alt hâl açmak — teşhis
sözleşmesini sekiz durumdan fazlasına çıkarırdı.
