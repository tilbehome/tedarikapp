# F37 — Kaynak Site Modülleri: Platform Kayıt Sistemi (fikir taslağı)

> Durum: HAVUZ (kod yok). Altyapı Faz 3 eklenti mimarisiyle birlikte tasarlanır
> (parser arayüzü TEK, 1688 ilk uygulama); ek platform modülleri Faz 4+ / talebe bağlı.

## İlke
Her kaynak site bağımsız bir parser/yakalama MODÜLÜDÜR (K15 ilkesinin somutlaşması:
yeni site = çekirdeğe dokunmadan yeni modül dosyası). Panel Ayarlar ekranında modül
listesi ve her modül için **aktif/pasif** anahtarı bulunur; **pasif platformdan gelen
yakalama isteği reddedilir**. Tekrar kontrolü mevcut `platform + external_id` (K25)
üzerinden platformlar arası aynen çalışır.

## Aday platform envanteri

**Çin tedarik tarafı:**
- 1688 (v1 — tek aktif modül)
- Taobao · Tmall · Alibaba.com · AliExpress · Pinduoduo · JD.com · DHgate · Made-in-China · Yiwugo

**Global / satış tarafı** (kapsam ihtiyaç doğunca netleşir: fiyat kıyas/referans):
- Amazon · eBay · Walmart Marketplace · Temu · Shein

## Tasarım notları (Faz 3'e girdi)
- Parser arayüzü tek: `parse(sayfa) → docs/04 §2c yakalama şeması` (schema_version/parser_version zorunlu).
- Modül kaydı: eklenti tarafında manifest + panel tarafında `settings` kaydı (aktif/pasif).
- Pasif modül: eklenti UI'da gri; `/api/capture` sunucu tarafında da reddeder (yalnız arayüz değil).
