# F40 — Faz 5 Vizyonu: Dashboard, Raporlar, Gelişmiş Menü/Tema (fikir taslağı)

> Durum: HAVUZ (kod yok). Hedef Faz 5 — işlevsel fazlar (Faz 2 export/paylaşım,
> Faz 3 eklenti, Faz 4 cilalar) tamamlandıktan sonra PM önceliklendirmesiyle açılır.

## Kapsam başlıkları (PM'in kademeli listesinden — İE#10 Blok 6)

1. **Dashboard genişletmesi:** Ana Ekran'ın karar destek paneline dönüşmesi — dönem
   bazlı sipariş özeti, bekleyen/yolda tutarları, kur etkisi göstergesi.
2. **Raporlar:** dönem/tedarikçi/kategori kırılımlı harcama raporları; export edilebilir
   (K50 snapshot altyapısı yeniden kullanılır).
3. **Gelişmiş menü:** modül bazlı gezinme, hızlı arama (liste/ürün genelinde),
   klavye kısayolları.
4. **Tema:** F38 TilbeCore tasarım diliyle birleşik ele alınır (tek tema işi).
5. Kalan kademeler PM listesiyle bu dosyaya işlenecek (İE#10 emrinde başlıklar
   "dashboard, raporlar, gelişmiş menü/tema" olarak özetlenmişti; 9 maddelik tam
   liste PM'den geldiğinde satır satır eklenecek — havuz kaydı şimdiden açık).

## Sınırlar
- Bu maddede KOD YAZILMAZ; yalnız havuz satırı (docs/08 F40) + bu taslak.
- K19 onaylı kütüphane listesi değişmez; grafikler önce CSS/SVG ile denenir.

## PM önerileri (İE#11 A2 — Faz 5 emri yazılırken önceliklendirilecek)

- Dashboard widget seti: aylık ¥/₺ hacim, durum halkası, 6 ay trend (grafik
  kütüphanesi adayı: Tremor — K19 onayı Faz 5 emrinde istenir)
- Kur geçmişi grafiği (rate_history hazır veri)
- Kategori ve tekrar-sipariş analizi
- Ctrl+K komut paleti önceliklendirme + universal arama
- Satır içi düzenleme (tabloda ad/adet/fiyat) · sürükle-bırak sıralama + undo
- Koyu tema (prefers-color-scheme hazırlığıyla)
- Paylaşım sayfası vitrini (kapak görünümü)
- PWA: manifest.json + yükleme iskeleti
- Dönem kapanış raporu (K50 snapshot altyapısı yeniden kullanılır)
- Adet-bazlı fiyat simülasyonu + birim fiyat gösterimi (v2-B motorunun lite'ı)
- Tamlık skoru lite · veri tazeliği rozeti · sistem sağlığı kartı
