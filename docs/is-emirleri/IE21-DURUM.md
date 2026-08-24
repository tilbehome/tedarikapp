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
