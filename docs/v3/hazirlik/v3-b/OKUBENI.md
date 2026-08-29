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
