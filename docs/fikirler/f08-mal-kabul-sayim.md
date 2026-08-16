# F8 — Mal Kabul Sayım Modu

> Durum: FİKİR TASLAĞI (havuz kaydı F8) · Hedef: Faz 4 / v1.1 · Şu an kapsam DIŞI (K17)

## Amaç
Konteyner/kargo geldiği gün depoda telefonla liste üzerinden hızlı sayım: beklenen ↔ gelen karşılaştırması, eksik/fazla/hasar kaydı ve kanıt fotoğrafı — teslim alma gününün kaosunu tek ekrana indirmek.

## Akış
1. Panel → ilgili liste → **"Mal Kabul Modu"** (tam ekran, tek el kullanım, büyük dokunma hedefleri).
2. Ürünler görselli kart olarak sırayla gelir; her kartta: beklenen adet, "GELDİ (tam)" büyük butonu, "Eksik/Fazla" (adet gir), "Hasarlı" (adet + fotoğraf + not), "Yanlış varyant" işareti.
3. İşaretlenen ürün otomatik `received` durumuna geçer (durum makinesi kuralları geçerli); kısmi teslimde ürün "kısmi geldi" rozetiyle listede kalır.
4. Bitişte **Mal Kabul Raporu** üretilir: tam/eksik/fazla/hasarlı dökümü — firmaya iletilebilir (paylaşım/PDF).

## Veri modeli (taslak)
```
goods_receipts        id, list_id, started_at, completed_at, note
goods_receipt_items   id, receipt_id, product_id, expected_qty, received_qty,
                      damaged_qty, wrong_variant (bool), photo_path, note
```
`product_status_history` ile entegre; rapor ekranı E9 aktiviteyle beslenir.

## İleri seviye (opsiyonel, aynı fazda değil)
Koli/QR eşleştirme: export'ta her ürüne kısa kod/QR basılır, firmadan kolilere yapıştırması istenir; depoda QR okut → kart doğrudan açılır. (F29 CBM/koli verisiyle birleşir.)

## Kabul ölçütleri (yazılacak iş emrine devir)
- 50 ürünlük listenin sayımı telefonda 10 dakikanın altında tamamlanabiliyor.
- Kısmi teslim ikinci sayımda kaldığı yerden sürüyor.
- Hasar fotoğrafı rapora gömülüyor.
