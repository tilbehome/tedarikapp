# F9 — Fiyat Değişim Uyarısı

> Durum: FİKİR TASLAĞI (havuz kaydı F9) · Hedef: Faz 4 · Şu an kapsam DIŞI (K17)

## Amaç
Aynı 1688 ürünü (`platform + external_id`, varsa SKU bazında) tekrar yakalandığında eski↔yeni Yuan fiyatını karşılaştırıp farkı göstermek — tekrar siparişte pazarlık kozu ve maliyet erken uyarısı.

## Akış
1. Eklenti capture'ı geldiğinde backend `(platform, external_id, sku_id)` için son kayıtlı fiyata bakar.
2. Fark varsa Gelen Kutusu kartında rozet: **"Fiyat değişti: ¥9,00 → ¥9,80 (+%8,9) · son yakalama 14 Mar 2026"**.
3. Ürün detayında mini fiyat geçmişi listesi (tarih + fiyat + değişim %).
4. (Ops.) Eşik uyarısı: %X üzeri artışta panel özet ekranına düşer.

## Veri modeli (taslak)
```
price_history   id, platform, external_id, sku_id (null), price_yuan DECIMAL(12,4),
                captured_at, capture_id
```
Yazma: her başarılı capture'da 1 satır (fiyat değişmese de — zaman serisi bütünlüğü). Para kuralları K24'e tabi.

## Neden en ucuz aday
Şemadaki mevcut alanlar + tek tablo + tek karşılaştırma sorgusu; arayüzde bir rozet. Eklenti tarafında sıfır ek iş (veri zaten geliyor).

## Kabul ölçütleri (devir)
- Aynı ürün ikinci yakalamada doğru farkı gösteriyor; SKU bazlı fark SKU kartında görünüyor.
- Fiyat geçmişi export'a SIZMAZ (iç bilgi).
