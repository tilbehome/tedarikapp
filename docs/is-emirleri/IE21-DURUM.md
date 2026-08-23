# İŞ EMRİ #21 — DURUM TABLOSU (canlı belge)

> Bu belge İE#21'in nerede olduğunu madde madde gösterir. Nihai rapor bunun
> üzerine yazılır. **Kapatılan madde buraya kanıtıyla (test/dosya) yazılır;**
> kanıtsız "bitti" kaydı bu tabloya girmez.

**Dal:** `v3-faz1` · **Hedef sürüm:** v1.0.0 · **Başlangıç:** 23 Ağustos 2026

---

## EKSİK GİRDİLER — bu dosyalar repoda YOK

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
| A7 | Fikstürler + 29 senaryo E2E + canary | ☐ (katalog bekliyor) |
| A8 | Prominent disclosure + manifest Seçenek A | ☐ |
| A9 | Store yayın paketi | ☐ (metin kaynağı bulundu) |

## BÖLÜM B — DİLİM 4: PANEL EKRANLARI

| # | Konu | Durum | Kanıt |
|---|---|---|---|
| B1 | Keşif havuzu | ☐ | şartname: `docs/v3/V3-YOL-HARITASI.md` §7.2 |
| B2 | Liste detay komuta merkezi | ☐ | referans: `liste-ici.png` |
| B3 | Ürün çekmecesi | ☐ | referans: `urun-duzenleme-alani.png` |
| B4 | Gelen kutusu cilaları | ☐ | referans: `gelen-kutusu*.png` |
| B5 | Kur: güncel kuru getir + taslak tazeleme | ✅ | `KurTazelemeTest` (8) · `KurKaynagiTest` (8) · K76 |
| B6 | Paylaş penceresi anahtar bloğu | ☐ | |
| B7 | Kilit ekranı | ☐ | referans: `erisim-anahtar-ekrani.png` |
| B8 | Paylaşım sayfası düzeltmeleri | ◐ | B8-1 kur ibaresi ZATEN VAR · B8-2 tek kaynak ✅ (`DurumSozluguTest`) · B8-4 firma görünümü ✅ (`PaylasimFirmaGorunumuTest`, 4) · B8-3 + 3 ince ayar ☐ |
| B9 | Çeviri tam kapsam | ✅ | `CeviriKapsamiTest` (16) · K77 |
| B10 | Kategori içe aktarma ucu | ◐ | uç + `KategoriIceAktarimTest` (12); **seed dosyası bekliyor** |
| B11 | Kuyruk sertleştirme | ✅ | `KuyrukSertlestirmeTest` (20) · K79 |
| B12 | Sürümlü çeviri belleği | ✅ | `CeviriKapsamiTest` sürüm bölümü · migration 0027 · K78 |
| B13 | Marka hibrit entegrasyonu | ◐ | kit `docs/marka/`'ya taşındı · favicon seti · panel amblemi · durum haritası eşitlendi (`DurumSozluguTest`, 9) · **belge antet/filigran şablonları ☐** |
| B14 | Sihirbazda 2FA | ✅ | `SetupOnarimUclariTest` (14, 3'ü 2FA) |
| B15 | Panel E2E (52 senaryo) | ☐ | katalog bekliyor |

## BÖLÜM C — KAPSAM SINIRI + KAPANIŞ

| # | Konu | Durum |
|---|---|---|
| C1 | V3-C/D/E öğeleri yapılmaz (menüde gizli) | ☐ doğrulanacak |
| C2 | Tasarım referansı adlandırma + OKUBENI | ✅ commit `dac469f` |
| C3 | Kabul sınavları | ☐ |
| C4 | v1.0.0 release + güncelleme runbook notu | ☐ |
| C5 | Nihai rapor | ☐ |

---

## Bu turda kapanan işlerin özeti

**Tamamlanan:** B5, B9, B11, B12, B14, C2 · **kısmi:** B8, B10, B13
**Yeni test:** 77 (8+8+16+20+12+9+4) · **yeni karar:** K76–K80
**Yeni migration:** 0027 (çeviri belleği sürümü), 0028 (kuyruk sertleştirme)
