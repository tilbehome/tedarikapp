# F34 — Çok Dilli Yapı (i18n)

> Durum: FİKİR TASLAĞI (havuz kaydı F34) · Hedef: Faz 4 (paylaşım sayfası önce) · Şu an kapsam DIŞI (K17)

## Amaç
Panel Türkçe kalır; **paylaşım sayfası** Çinli firmanın dilinde de okunabilir olur (中文/EN/TR seçici). Firmayla iletişim kalitesini doğrudan artırır.

## Temel
K22 zaten altyapıyı kurdu: DB/API'de yalnız İngilizce makine enum'ları var, Türkçe sadece arayüz katmanında. Yani çeviri = yeni dil dosyası eklemek; veri modeline dokunulmaz.

## Kapsam (öncelik sırasıyla)
1. **Paylaşım sayfası (P1):** sabit etiketlerin (adet, fiyat, toplam, durum adları) zh-CN + en çevirisi; dil seçici; `lang` parametresiyle link paylaşımı (`/p/TOKEN?lang=zh`). Ürünün orijinal Çince başlığı zaten saklandığından zh görünümde **orijinal başlık asıl gösterilir** — çeviriye gerek kalmaz, firma kendi dilindeki gerçek ürün adını görür.
2. **Panel:** dil dosyaları (`locales/tr.json` varsayılan); ileride en eklenebilir. Zorunlu değil.
3. **Export:** Excel başlıkları için dil seçeneği (firma zh isterse) — ayrı karar, şimdilik hayır.

## Teknik not
Sunucu render'lı paylaşım sayfasında basit PHP dizi tabanlı sözlük yeter; panelde React tarafında hafif bir t() yardımcıyla. Ağır i18n kütüphanesi GEREKMEZ (K13 ruhu).

## Kabul ölçütleri (devir)
- Aynı link `?lang=zh` ile tüm etiketleri Çince gösteriyor; sayılar/para biçimleri bozulmuyor (¥/₺/$ biçimleri dil-bağımsız doğru).
- zh görünümde ürün adı = orijinal başlık; TR görünümde = senin girdiğin ad (orijinal altta küçük).
