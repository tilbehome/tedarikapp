# V3-B Hazırlık

Görev #13 paketi: bildirim, panorama, ayarlar bilgi mimarisi ve ekran durum
metinleri. Uygulaması İŞ EMRİ V3-B (v1.2.0) ile yapıldı.

## Katalog kaynağı taşındı (K99)

İki JSON bu klasörden **`config/` altına TAŞINDI** (kopya değil, taşıma):

| Eski yol | Yeni yol |
|---|---|
| `docs/v3/hazirlik/v3-b/bildirim-olay-katalogu.json` | **`config/bildirim-olay-katalogu.json`** |
| `docs/v3/hazirlik/v3-b/panorama-brifing-katalogu.json` | **`config/panorama-brifing-katalogu.json`** |

Sebep: bu iki dosya ŞARTNAME DEĞİL, **çalışma zamanı verisidir** — uygulama
onları her bildirimde ve her panorama isteğinde okur. `docs/` ise şartname ve
tarihçedir ve **release paketine girmez**; katalog orada kaldığı sürece canlıda
hiçbir bildirim üretilemiyor, panorama ucu patlıyordu (v1.2.0'ın ilk paketinde
tam olarak bu yaşandı ve paket teslim edilmedi).

K99: çalışma zamanı verisi `docs/` altından okunmaz. Uygulama yalnız `config/`,
`app/`, `public/` ve veritabanından beslenir. Katalog içeriğini değiştirmek
isteyen `config/` altındaki dosyayı düzenler; buradaki OKUBENI yalnız işaretçidir.

## Bu klasörde kalanlar

- `ayarlar-bilgi-mimarisi.md` — 16 sekmelik bilgi mimarisi (şartname)
- `ekran-durum-metinleri.json` — ekran durum metinleri kataloğu
- `TESLIM-RAPORU-gorev13.md` — Görev #13 teslim raporu
- `tasarim-referans/` — Panorama arayüz tasarımı (SVG)

## Tasarım referansları

`tasarim-referans/` altında iki dosya var ve ikisi de **ŞARTNAME DEĞİL,
REFERANSTIR**: yerleşim ve dil için bakılır, birebir kopyalanmaz.

| Dosya | Kapsam |
|---|---|
| `tedarikapp-panorama-v3b.svg` | Panorama ekranı düzeni |
| `ayarlar-referans.html` | Ayarlar ekranı: sol dikey gezinme, bölüm başlığı kartı, KPI şeridi, bölüm içi kartlar |

### Ayarlar referansından ÜÇ PM SAPMASI

Referans bir mockup'tır; aşağıdaki üç noktada **uygulanan tasarım referanstan
bilinçli olarak ayrılır**. Sapmalar PM kararıdır ve gerekçeleri kayıtlıdır.

1. **Turuncu vurgu KULLANILMAZ — lacivert/altın token'lar geçerlidir.**
   Referansta vurgu rengi turuncudur. Arayüzün kimliği LACİVERTTİR (amblem
   turuncu olsa da, B13 hibrit entegrasyon kararı); vurgu `--navy`/`--gold`
   token'larıyla verilir. `tokens.css`e YENİ RENK EKLENMEZ — mevcut palet
   dışına çıkmak, koyu temayı da ikinci bir bakımı olan yere taşırdı.

2. **"Depolama" kartı ÖLÇÜLEMİYORSA YOKTUR — uydurma sayı yasak.**
   Referansta disk kullanımı gösteren bir kart var. Paylaşımlı hostingte disk
   kotası uygulamadan güvenilir okunamaz; tahmini bir yüzde basmak, kullanıcıyı
   olmayan bir veriye göre karar vermeye iter. Ölçülemeyen KPI kartı hiç
   render edilmez (Panorama'daki "henüz ölçülmüyor" ayrımının aynı disiplini,
   K67: bilinmeyen ≠ sıfır).

3. **Marka bloğu ve "Yeni ürün ekle" düğmesi referanstan ALINMAZ.**
   Sol üstteki marka bloğu ve birincil eylem düğmesi uygulamanın kendi
   kabuğundadır (`YanMenu`); referanstaki hâlleri o bileşenlerin bugünkü
   tasarımının yerine geçmez. Ayarlar ekranı yalnız KENDİ gövdesini yeniden
   düzenler.
