# İŞ EMRİ #21 — DURUM TABLOSU (canlı belge)

> Bu belge İE#21'in nerede olduğunu madde madde gösterir. Nihai rapor bunun
> üzerine yazılır. **Kapatılan madde buraya kanıtıyla (test/dosya) yazılır;**
> kanıtsız "bitti" kaydı bu tabloya girmez.

**Dal:** `v3-faz1` · **Hedef sürüm:** v1.0.0 · **Başlangıç:** 23 Ağustos 2026

---

## EKSİK GİRDİLER — ÇÖZÜLDÜ (23 Ağu 2026, Ürün Sahibi teslim etti)

Aşağıdaki tablo tarihsel kayıttır; **dosyaların hepsi artık `docs/v3/hazirlik/`
altındadır** ve bağımlı maddeler yürütülmüştür. `demo-urun-seti.json` (Görev #5A)
da eklendi ve C3 sınavı onunla koştu.

## (tarihsel) EKSİK GİRDİLER

İş emri şu kaynaklara atıf yapıyor; hiçbiri `docs/v3/hazirlik/` altında (klasör de
yok). Bunlara bağlı maddeler **beklemede** ve aşağıda öyle işaretli:

| Beklenen dosya | Hangi madde bekliyor | Yerine ne yapıldı |
|---|---|---|
| `store-yayin-paketi.md` | A9 (manifest + store metinleri) | Marka kitindeki `docs/marka/chrome-web-store/STORE-LISTING-COPY.md` bulundu; A9'un metin ihtiyacının çoğunu karşılıyor |
| `store-politika-teyidi.md` (9 bağlayıcı madde) | A6, A9 | Emrin kendi metnindeki dört madde (kategori "Workflow & Planning", website content + authentication information beyanları, unlisted varsayımı) kullanılacak; kalan 5 madde bilinmiyor |
| Eklenti E2E kataloğu (29 senaryo) | A7 | — |
| Panel E2E kataloğu (52 senaryo) | B15 | — |
| `kategori-agaci.json` (Görev #8B) | B10 seed + Gelen Kutusu kategori tahmini | İçe aktarım UCU yazıldı ve üç biçimi de kabul ediyor; dosya gelince tek çağrıyla yüklenir |
| 8A kalibrasyon seti | C3 (skor kalibrasyon sınavı) | — |
| `kabul-turu-v1.md` (85 dk) | C5 | — |

**Ayrıca bir kapsam bulgusu:** `docs/v3/tasarim-referans/paylasim-sayfasi*.png`
karelerindeki arayüz repoda **hiç yok** (sekmeli detay paneli, "Talep/Seçim"
bloğu, üç sütunlu bilgi ızgarası, kategori/kaynak/durum sütunları). Yani B8
"4 düzeltme + 3 ince ayar" gibi görünse de aslında paylaşım sayfasının
**yeniden düzenlenmesini** istiyor. Emirde sayılan 7 maddeyi uyguluyoruz;
tam mockup uyarlaması ayrı bir dilim olarak raporlanacak.

---

## BÖLÜM A — DİLİM 3: EKLENTİ v2 + STORE

| # | Konu | Durum |
|---|---|---|
| A1 | Sayfa içi yakalama (inline düğme + pill, durum şeridi, varyant bölümü) | ☐ |
| A2 | Üç dünya mimarisi + 10 durumlu makine | ☐ |
| A3 | 16+ alan eksiksiz | ☐ |
| A4 | Kalıcı kuyruk + MV3 başlangıç toparlama + adaptör kimliği | ☐ |
| A5 | Mükerrer 4 seçenek + 502 idempotens + tazele + çoklu yakalama | ☐ |
| A6 | Seçici sürümleme (gömülü paket, fikstür ön-kapısı) | ☐ (politika teyidi bekliyor) |
| A7 | Fikstürler + 29 senaryo E2E + canary | ◐ kapsam defteri ✅ · senaryolar ☐ |
| A8 | Prominent disclosure + manifest Seçenek A | ☐ |
| A9 | Store yayın paketi | ✅ `bin/store-paketi.php` + dist/store |

## BÖLÜM B — DİLİM 4: PANEL EKRANLARI

| # | Konu | Durum | Kanıt |
|---|---|---|---|
| B1 | Keşif havuzu | ✅ | API+ekran · `KesifHavuzuTest` (17) · E2E-PNL-01/02/03/07/08/09/10/13/15 · migration 0030 · sistem listesi korumaları `SistemListesiKorumasiTest` (8) |
| B2 | Liste detay komuta merkezi | ◐ | aşama çubuğu (5B) ✅ · özet şerit ✅ · uyarı çipleri→süzgeç ✅ · satır içi miktar ✅ · HAZIR kapısı satırda ✅ — `AsamaCubugu.test.tsx` (8) · `UyariCipleri.test.tsx` (5) · `MiktarHucresi.test.tsx` (9) · `eksikler.test.ts` (5) · toplu eylem çubuğu ZATEN VAR · sütun/yoğunluk/gruplama denetimleri ✅ (`TabloDenetimleri.test.tsx` 7 · `UrunTablosu.test.tsx` 10 · `tabloTercihi.test.ts` 9) |
| B3 | Ürün çekmecesi | ✅ | `GET /products/{id}/cekmece` (tek istek) + sağ çekmece: galeri · ürün bilgileri · ürün sağlığı (C8) · varyasyonlar · fiyat kademeleri · tedarik puanı · yorum özeti · kaynak/satıcı · not — `UrunCekmecesiTest` (7) · `UrunCekmecesi.test.tsx` (11). Yurt içi kıyas: veri kaynağı yok, açıkça `null` (V3-C) |
| B4 | Gelen kutusu — deste modu | ◐ | deste modu ✅ (`DesteModuTest` 9 + `DesteModu.test.tsx` 8) · entity çözümü ✅ · E2E-PNL-16/17/18/19 · kural motoru + zenginleştirme paneli ☐ |
| B5 | Kur: güncel kuru getir + taslak tazeleme | ✅ | `KurTazelemeTest` (8) · `KurKaynagiTest` (8) · K76 |
| B6 | Paylaş penceresi | ✅ | `PaylasPenceresi` (bağlantı+bitiş tarihi · erişim anahtarı bloğu · üç dilli kanal metni) · yeni uç `GET /lists/{id}/share-text` (şablon sunucudan, `{link}` panelde dolar — K51) · `PaylasPenceresi.test.tsx` (11) · `KilitEkraniTest` metin bölümü (4) |
| B7 | Kilit ekranı | ✅ | referans düzeni + üç dil + kalan hane sayacı · `KilitEkraniTest` (9). İKİ BİLİNÇLİ SAPMA: anahtar geri sayımı YOK (anahtarın süresi yok — K62; yerine bağlantı bitişi), "yeni anahtar iste" düğme DEĞİL bilgi satırı (kanal yok) |
| B8 | Paylaşım sayfası düzeltmeleri | ◐ | B8-1 kur ibaresi ZATEN VAR · B8-2 tek kaynak ✅ (`DurumSozluguTest`) · B8-4 firma görünümü ✅ (`PaylasimFirmaGorunumuTest`, 4) · B8-3 + 3 ince ayar ☐ |
| B9 | Çeviri tam kapsam | ✅ | `CeviriKapsamiTest` (16) · K77 |
| B10 | Kategori içe aktarma + seed + tahmin | ✅ | uç + `KategoriIceAktarimTest` (12); **seed dosyası bekliyor** |
| B11 | Kuyruk sertleştirme | ✅ | `KuyrukSertlestirmeTest` (20) · K79 |
| B12 | Sürümlü çeviri belleği | ✅ | `CeviriKapsamiTest` sürüm bölümü · migration 0027 · K78 |
| B13 | Marka hibrit entegrasyonu | ◐ | kit `docs/marka/`'ya taşındı · favicon seti · panel amblemi · durum haritası eşitlendi (`DurumSozluguTest`, 9) · belge antet/amblem/filigran ✅ (`BelgeMarkasi` + `public/marka/belge/` + `config/belge-tema.json`; PDF: iç kopyada "İÇ KOPYA" metin filigranı, firma kopyasında marka filigranı; Excel: marka amblemi) · `BelgeMarkasiTest` (7). SAPMA: belge PALETİ rev7 şablonundan (lacivert/altın) — marka kitinin krem/turuncu belge teması onaylı şablonu bozacağı için yalnız GÖRSEL varlıklar ve sayı biçimleri kitten alındı |
| B14 | Sihirbazda 2FA | ✅ | `SetupOnarimUclariTest` (14, 3'ü 2FA) |
| B15 | Panel E2E (52 senaryo) | ◐ | kapsam defteri: **37/81 kapsandı**, 2 çelişki (PNL-37/38), 42 bekliyor. Bu turda eklenen: PNL-22/23/24/25/26/29/31/35/36/39/41/42/43/44/46 |

## BÖLÜM C — KAPSAM SINIRI + KAPANIŞ

| # | Konu | Durum |
|---|---|---|
| C1 | V3-C/D/E öğeleri yapılmaz (menüde gizli) | ☐ doğrulanacak |
| C2 | Tasarım referansı adlandırma + OKUBENI | ✅ commit `dac469f` |
| C3 | Kabul sınavları | ◐ skor kalibrasyonu ✅ (%82/0/%100) · E2E defteri ✅ (10/81) · kabul turu ☐ |
| C4 | Release + güncelleme runbook notu | ◐ v1.0.0-rc1 |
| C5 | Nihai rapor | ☐ |

---

## Bu turda kapanan işlerin özeti

**Tamamlanan:** B5, B9, B11, B12, B14, C2 · **kısmi:** B8, B10, B13
**Yeni test:** 77 (8+8+16+20+12+9+4) · **yeni karar:** K76–K80
**Yeni migration:** 0027 (çeviri belleği sürümü), 0028 (kuyruk sertleştirme)

---

## İŞ EMRİ #22'YE DEVREDİLEN NOTLAR (PM, 24 Ağu 2026)

- **Test süresi:** `tests/Http` yerel koşumda 26 dakikaya çıktı (396 test, tek
  süreç, SQLite bellek şeması). Çözüm CI'da paralelleştirme veya grup ayrımıdır
  (örn. `capture`, `setup`, `paylasim`, `liste` süitleri ayrı job). **rc2 SONRASI**
  ele alınacak — v1.0 kapanışını geciktirmemek PM kararıdır.

---

## İE#21 EK-5 (24 Ağu 2026) — K81 tamamlama

| # | İş | Durum | Kanıt |
|---|---|---|---|
| 1 | SharePage yerelleştirme (K81 istisna dışı tek dil) | ✅ | `ShareTexts` sayfa sözlüğü (51 anahtar × 3 dil) · `ProductFacts` etiketleri üç dilli · `testK81_ISTISNA_DISINDA_TEK_DIL` + `testK81_SECILEN_DIL_SAYFAYA_ISLENIR` |
| 2 | PdfRenderer dil desteği (pdf-rev4) | ✅ | `options.lang` okunuyor; başlık TEK satır seçilen dilde; KPI/TOPLAM/şartlar/alt bilgi aynı dilde · `testPDF_BASLIK_SECILEN_DILDE_TEK_SATIR` · panel export'u da `lang` taşıyor |
| 3 | PHPUnit sayım mutabakatı | ✅ | 1045 = Http 435 + Services 388 + Core 103 + Setup 47 + Auth 43 + Production 13 + Integration 9 + Support 6 + Models 1 |

**Kapsam defteri:** PNL-37/38/40 `kapsandi` (40/81). K81 istisnaları testlerle korunuyor:
başlık bloğu üç dilli (`testK81_BASLIK_BLOGU_HER_DILDE_UC_DILLI`), K55 orijinal satır
(`testE2E_PNL_39_ORIJINALSATIRUCDILDEAYNEN`).

- **E2E kalanları (PM kararı, 25 Ağu 2026 — hiçbiri v1.0 blokajı DEĞİL):**
  - `E2E-PNL-20` (kural rozeti + geri alma) ve `E2E-PNL-50/51` (sözlük CSV içe
    aktarımı) **özellikleriyle birlikte V3-B'ye** devredildi. Kural motoru zaten
    "B4 hariç" kabul edilmişti; CSV içe aktarma ucu hiç yazılmadı. Test, özellik
    yazılmadan kodlanamaz.
  - `E2E-PNL-11/12/14/45` kabul turuna MANUEL madde olarak eklendi:
    `docs/v3/hazirlik/kabul-turu-v1.md` → **KT-EK-1..4**. Playwright altyapısı
    (gerçek tarayıcı + MySQL turu) İE#22 kapsamındadır.
  - Dördü de kapsam defterinde **bekliyor** kalır; "kapsandı" işaretlenmez —
    otomatik kanıtı olmayan senaryo yeşil sayılmaz.

---

## D5 SAHA BULGUSU (25 Ağu 2026) — sayfa içi panel bağlantıyı görmüyordu

**Belirti (canlı, Çince `detail.1688.com`, panel rc'ye bağlı):** toolbar popup
"bağlı ✓" derken sayfa içi panelde Hedef listesi BOŞ, "Yakala ve Gönder" pasif.

**Kök neden — üç ayrı kusur üst üste:**

1. `bridge.content.ts` → `arkaPlan()` **`chrome.runtime.lastError` okumuyordu**.
   MV3 service worker uykudan kalkarken ilk mesaj düşer; callback yanıtsız
   çağrılır ve eski kod bunu `BILINMEYEN_HATA` sanıyordu. Popup bu kontrolü
   baştan beri yapıyordu — iki yüzey arasındaki farkın asıl kaynağı budur.
2. `listeler()` ve `duranlar()` hatayı **yutup boş dizi** dönüyordu: "bağlantı
   yok" ile "liste yok" ayırt edilemiyor, kullanıcıya sebep söylenmiyordu.
3. `Akis.ac()` listeleri **açılışta bir kez** (`listeler.length === 0`) çekiyordu;
   yeniden deneme yok, bağlantı kavramı yok, ayar sonradan girilse haber alan yok.

**Düzeltme (rc4):**

| Değişiklik | Dosya |
|---|---|
| Tek kaynak: durum sınıflandırma + yeniden deneme (`AYAR_EKSIK`/`YETKI` denenmez) | `extension/core/baglanti.ts` (yeni) |
| `lastError` okunur; liste hatası fırlatılır; `storage.onChanged` ile otomatik tazeleme; SPA aralık sızıntısı giderildi | `extension/entrypoints/bridge.content.ts` |
| Bağlantı durumu görünüme girdi, `baglantiyiTazele()`, önce-çiz açılış, bağlanınca son liste seçimi geri gelir | `extension/ui/v2/akis.ts` |
| Bağlantı şeridi + "Yeniden dene"; boş seçici "Bağlantı bekleniyor…" der; `gonderDugmesiKapali()` saf kural | `extension/ui/v2/panel.ts`, `stil.ts`, `cekmece.ts` |

**Ek olarak giderilen:** her SPA yönlendirmesinde `kur()` yeni bir 1 sn'lik
`setInterval` açıyordu (sayaçlar üst üste birikiyordu); artık eski sayaç ve
storage dinleyicisi sökülüyor.

**Test:** `extension/tests/baglantiSenkronu.test.ts` — 11 test (sınıflandırma,
geçici hatada yeniden deneme, kalıcı hatada denememe, açılışta otomatik dolan
hedef listesi + son seçim, token sonradan girilince tazeleme, bağlantısızken de
önizleme üretilmesi, gönder düğmesi kilidi). DOM'suz koşar: jsdom bağımlılığı
eklenmedi (K19).

**Sayfa varyantı notu:** global (TR arayüzlü) 1688 görünümünde ayrıştırıcı hiç
uyanmıyor; Çince detay görünümü v1.0 için yeterlidir. Ayrıntı ve devir:
`docs/v3/hazirlik/v3-e/platform-veri-kanali-raporu.md` §9 → V3-E/K.

---

## D6 SAHA BULGUSU (25 Ağu 2026) — LLM turu makine çevirisinin üstüne yazmıyordu

**Belirti (altın set koşumu):** TR 4/4 dolu ama sağlayıcı `mymemory` ve kalite
düşük ("无脚踏 → Bisiklet Yok"; doğrusu *pedalsız*, "乐扣杯 → Le toka fincan");
EN 2/4 ve `llm:deepseek` kalitesinde. K56'nın "TR+EN tek LLM isteğinde birlikte"
ilkesi fiilen bozuluyordu.

**Kök neden — iki mekanizma birden:**

1. **Adaylık ölçütü yanlıştı.** `ProductRepository::cevrilmemisler()` "önbellekte
   o başlık için HERHANGİ bir satır var mı" diye soruyordu. Yakalamada makine
   katmanı TR'yi doldurduğu için ürün "çevrilmiş" sayılıyor, LLM turu ona HİÇ
   uğramıyordu.
2. **Yazma tek yönlüydü.** `TranslationCacheRepository::store()` yalnız INSERT
   eder; aynı anahtarda satır varsa sessizce geçer. LLM tura girse bile makine
   satırı yerinde kalırdı. Üstüne, LLM yalnız **sürümlü** anahtara yazıyordu;
   kullanıcının ve `bin/ceviri-sinavi.php`nin okuduğu satır ise **sürümsüz**
   anahtardaki makine satırıydı.

**Düzeltme:**

| Değişiklik | Dosya |
|---|---|
| Adaylık: hedef dillerden HERHANGİ BİRİ için `llm:*` ya da `elle` satırı yoksa ürün tura girer | `app/Models/ProductRepository.php` |
| `tazele()` — makine satırının ÜZERİNE yazar; `llm:*` ve `elle` satırlarına dokunmaz. `tazeleTumAnahtarlar()` sürümlü + sürümsüz anahtarı birlikte tazeler | `app/Models/TranslationCacheRepository.php` |
| LLM yazımı `tazeleTumAnahtarlar()` üzerinden | `app/Services/Translation/LlmTranslator.php` |
| Toplu tur hedef dilleri ayarlardan okur | `app/Controllers/TranslationController.php` |

**K54 sınırı korunuyor:** `elle` (onaylı düzeltme) sağlayıcılı satır hiçbir
otomatik tur tarafından ezilmez; `TranslationCacheRepository::ELLE_SAGLAYICI`
sabiti hem yazan hem koruyan tarafça kullanılır. Makine çevirisi artık ne ise
odur: **LLM gelene kadarki geçici doldurma**.

**Test:** `tests/Services/CeviriLlmTazelemeTest.php` — 13 test (tazeleme, onaylı
satırın korunması, çift anahtar yazımı, adaylık ölçütünün altı hâli).

---

## D7 SAHA BULGUSU (25 Ağu 2026) — kuyruk cron'da hiç işlemiyordu

**Belirti (MegaTR, ea-php83 CLI):** `bin/kuyruk.php` →
`Call to undefined function App\Services\Kuyruk\getmypid()`. Paylaşımlı hosting
`disable_functions` ile `getmypid`'i CLI'da kapatmış; kuyruk her turda ölümcül
hatayla düşüyordu — yani "kuyruk var" demek "kuyruk çalışıyor" demek değilmiş.
D6'nın mekanik kökü de büyük ölçüde budur: LLM turu kuyrukta koşar.

**Düzeltme:** `JobRunner::surecKimligi()` — süreç fonksiyonlarına GÜVENMEZ.
`getmypid` varsa PID kullanılır (log okunur kalsın), yoksa `posix_getpid`
denenir, o da yoksa `bin2hex(random_bytes(8))` ile benzersiz kimlik üretilir.
`gethostname` de aynı korumadan geçer. Kimlikten beklenen tek şey kira
sahipliğinde benzersizliktir; gerçek PID şart değildir. Kuyruk hattında başka
süreç/sistem fonksiyonu çağrısı KALMADI (tarandı: `getmypid`, `posix_*`,
`pcntl_*`, `gethostname`, `set_time_limit`, `shell_exec`).

**Test:** `tests/Services/SurecKimligiTest.php` — 5 test. `disable_functions`
ad-alanı düzeyinde taklit edilir (`App\Services\Kuyruk\function_exists`), yani
fonksiyon-yok senaryosu gerçek sunucuya gitmeden koşar. Asıl kabul: ölümcül hata
YOK ve 50 kimlik çakışmıyor.

---

## D8 SAHA BULGUSU (25 Ağu 2026) — sihirbaz eski sürüm damgasını SAĞLIKLI sayıyor

**Belirti (canlı):** dosyalar `1.0.0-rc5`, veritabanındaki kurulu sürüm kaydı
`0.12.1-beta`; sihirbaz durumu **SAĞLIKLI** raporluyor ve damga eşitleme adımı
sunmuyor.

**Kök neden (koddan doğrulandı):** `SetupSituation::kararVer()` içinde
`SURUM_UYUSMAZLIGI` yalnız **bekleyen migration > 0** iken üretilir. Bekleyen 0 +
damga eski → `SAGLIKLI`. Üstelik SAĞLIKLI açıklaması `$surum['kurulu']` değerini,
yani eski damgayı "kurulu sürüm" diye basıyor.

**Etki:** veri ve şema etkilenmez — damga bir ayar kaydıdır
(`settings['system.app_version']`). Etkilenen şey teşhisin doğruluğudur.

**Şimdiki çözüm (rc5 kilidi sürüyor, koda dokunulmadı):** terfi prosedürüne
**§5 — DB sürüm damgası** bölümü eklendi (`docs/v3/hazirlik/rc5-terfi-proseduru.md`).
Terfi sonrası adım 7 olarak zorunlu: sahiplik doğrulaması → `POST /api/setup/update`
(yıkıcı değil; bekleyen yoksa yalnız damgayı tazeler) → `/setup`te dosya = kurulu
doğrulaması. Son çare olarak tek satırlık SQL yolu da yazıldı, tercih edilmez.

**Kalıcı çözüm:** İE#22, blok **H** (kozmetik) — **PM kararı 25 Ağu: seçenek B
onaylı.** Sekiz durumlu teşhis sözleşmesi korunur; SAĞLIKLI eylemlerine yalnız
`surum['ayni'] === false` iken görünen `damgayi_esitle` eklenir ve SAĞLIKLI metni
fark varsa iki değeri birden basar ("dosya X · kurulu Y"). Terfi prosedürü
§5 + adım 7 yolu (sihirbaz üzerinden; SQL son çare) v1.0.0 terfisinde koşulacak.

---

## D9 SAHA BULGUSU (25 Ağu 2026) — panel "5 bekliyor", cron "kuyruk boş"

**İlk belirti (20:06):** Ayarlar > Kuyruk durumu "Bekleyen 5 · Çalışan 0 · Ölü 0 ·
en eski bekleyen 8 dk · kuyruk sağlıklı" derken cron günlüğü her turda
"KUYRUK TURU: 0 iş · kuyruk boş". **Güncelleme (20:15):** işler aslında
alınıyormuş — 5 işten 2'si tamamlanmış (ürün 1 ve 4, TR `llm:deepseek`, kalite
doğru). Yani blokaj değil, **AKIŞ HIZI** sorunu; ilk gözlem iki turun arasına
denk gelmiş.

### Yine de düzeltilen kusur: iki yüzey aynı soruyu farklı soruyordu

Sayaç ile işçi AYNI tabloya **ayrı koşullarla** bakıyordu:

| Yüzey | Sorgu |
|---|---|
| Sayaç (`saglik`) | `durum = 'bekliyor'` |
| İşçi (`sahiplen`) | `durum = 'bekliyor' AND calisacak_at <= now` |

Tek fark zaman koşuludur. Bu fark yüzünden "5 bekliyor" ile "kuyruk boş"
cümleleri **aynı anda doğru** olabiliyor ve kimse çelişkiyi göremiyordu — D5'te
popup ile sayfa içi panel arasında yaşananın kuyruk hâli. Bu turda kapatıldı:

| Değişiklik | Dosya |
|---|---|
| Alınabilirlik koşulu TEK YERDE (`JobQueue::ALINABILIR`); sahiplenme ve sayım aynı metni kullanır | `app/Services/Kuyruk/JobQueue.php` |
| `saglik()` artık `alinabilir`, `ileri_tarihli`, `en_yakin_calisacak_dakika` da döner | aynı dosya |
| İşçi "kuyruk boş" derken YALAN SÖYLEMEZ: bekleyen varsa sebebi ve **kendi saatini** yazar | `app/Services/Kuyruk/JobRunner.php` |
| `GET /api/system/queue` üç yeni sayıyı taşır | `app/Controllers/SystemController.php` |
| `bin/kuyruk.php --durum` ayrışma varsa UYARI satırı basar (SSH gerekmeden teşhis) | `bin/kuyruk.php` |

**Test:** `tests/Services/KuyrukGorunurlukTest.php` (6) — sözleşme: *panel sayacının
bekleyen dediği her iş, işçinin bir sonraki turda claim ettiği kümededir* (küme
karşılaştırması, sayı değil) · `tests/Http/TopluCeviriKuyrugaTest.php` (2) —
düğme → kuyruk → **gerçek `JobRunner`** uçtan uca; ağa çıkılmaz.
`AuthTestCase` şemasına `jobs` tabloları eklendi (gerçek migration'lardan).

### AKIŞ HIZI ANALİZİ (asıl soru)

Ölçülen değerler (koddan):

| Ayar | Değer | Nerede |
|---|---|---|
| Cron aralığı | **5 dk** (`*/5`) | runbook |
| Tur süre bütçesi | **50 sn** (`--sure` varsayılanı) | `bin/kuyruk.php` |
| Tur iş sınırı | 25 | `JobRunner::$isSiniri` |
| LLM istek zaman aşımı | **45 sn** | `TRANSLATE_LLM_TIMEOUT` |

Bütçe **iş almadan önce** denetlenir: bir tur, 50 saniye dolana kadar yeni iş
alır; almış olduğu işi yarıda kesmez. Bir çeviri işi (ad + kategori + öznitelik
değerleri tek istekte) sahada 20–45 sn sürüyor. Sonuç: **tur başına 1–2 iş**.
Beş iş = 3–5 cron turu = **15–25 dakika**. Gözlem (18 dakikada 2 iş) bu aralığın
alt ucudur; yani sistem tasarlandığı gibi çalışıyor, yalnız yavaş.

**Öneri — ayar, kod değil (İE#22):**

```cron
*/5 * * * * /usr/local/bin/php /home/<kullanici>/tedarikapp/bin/kuyruk.php --sure=240 >> ~/kuyruk-gunluk.txt 2>&1
```

`--sure=240` ile bir tur ~4 dakika iş alır (en kötü hâlde 240 + son işin süresi
≈ 285 sn < 300 sn cron aralığı; üst üste binme olmaz, binse bile kira token'ı
korur). Beş iş **tek turda** biter. Alternatif: cron'u dakikada bire çekip
`--sure=50` bırakmak — aynı verim, daha çok süreç başlatma maliyeti.

**Kod değişikliği ŞART DEĞİL** (PM kararı, 25 Ağu). İE#22'de değerlendirilecek
iki iyileştirme: (a) `--sure` varsayılanını cron aralığından türetmek,
(b) çeviri işini ürün başına değil **parti** hâlinde kuyruğa almak (tek LLM
isteğinde 5 ürün → beş kat hız, K56'nın "tek istek" ilkesine de uygun).
