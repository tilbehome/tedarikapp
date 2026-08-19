# tedarikapp v2 — Yol Haritası (v2-01)

Durum: TASLAK (PM, 19 Ağu 2026) · Yerleşim: `docs/v2/01-yol-haritasi.md`

## 0. Ön şartlar

- v1 kapanışı: Faz 1 ✅ · Faz 2 ✅(kapanmak üzere) · Faz 3 (eklenti) · Faz 4
  (iyileştirme) · Faz 5 (profesyonel arayüz — f40).
- **K32 kapısı (mimari hüküm):** Product ≠ SourceListing kararı Faz 3 capture
  sözleşmesi v2 yazılırken bağlanır. Faz 3'te ucuz önlem: capture çıktısında
  kaynak alanları (platform, external_id, seller_id, url, fiyat anları) ayrı
  blokta tutulur ki v2'de source_listings tablosuna migration mekanik olsun.
- v2 hiçbir aşamada v1'in çalışan akışını (liste → Excel/PDF/paylaşım) bozamaz;
  her v2 fazı kendi migration'ı + geriye dönük uyum testiyle gelir.

## 1. Faz planı

### V2-A — Çekirdek Mimari (temel taşı)
Kapsam:
- `source_listings` tablosu + Product↔Listing ayrımı; mevcut products verisinin
  migration'ı (her ürün = 1 product + 1 listing olarak açılır).
- ResearchProject + Candidate modeli ve panelde "Araştırma" sekmesi (havuz
  görünümü: aday listesi, etiket, kısa liste işareti).
- Capture v2 sözleşmesinin RAW/NORMALIZED/provenance katmanlarıyla genişlemesi
  (alan bazlı kaynak izi: kaynak, orijinal değer, yöntem, zaman, güven).
- Eklenti akışı: "Araştırma havuzuna ekle" hedefi (mevcut liste hedefine ek).
Kabul: eski listeler/exportlar birebir çalışır; 1 ürün 2 kaynak ilanıyla
kartta görünür; RAW veri panelde "orijinal" sekmesinde okunur.

### V2-B — Çok Kaynak + Karşılaştırma
Kapsam:
- Connector mimarisi genişler (P0 1688 referans; P1 Tmall/Taobao, Alibaba.com;
  P2 AliExpress/JD/PDD; P3 DHgate/Made-in-China/Yiwugo — F37 listesi).
- Listing'e `source_type` (wholesale/retail/factory/trading/unknown).
- Karşılaştırma matrisi ekranı (fiyat, MOQ, kademeli fiyat, satıcı yaşı,
  termin, koli, birim-normalize fiyat) + adet bazlı fiyat simülasyonu.
- Birim standardizasyon motoru (set/koli/kg → satılabilir birim).
- Duplicate engine katmanlı (source ID → URL → başlık → öznitelik → görsel hash);
  otomatik merge YOK, "%X aynı olabilir" önerisi.
- Veri tazeliği: "son kontrol X gün" + Yenile + değişiklik farkı ekranı.
Kabul: aynı gerçek ürünün 3 kaynağı tek matriste; 500-adet senaryosu doğru
kademeden hesaplanıyor (MoneyService).

### V2-C — Teklif / Numune / Karar
Kapsam:
- RFQ üretici (ürün seçiminden soru listesi çıkarır) + Quote kayıtları ve
  **teklif versiyon geçmişi** (eski fiyat silinmez).
- Tedarikçi portalı: `/q/<token>` — firma DDP fiyat/MOQ/termin girer (K51
  token modeliyle aynı güvenlik; yazılabilir alanlar dar ve doğrulamalı).
- Numune akışı (istendi→geldi→incelendi→onay/ret) + numune kartı (foto/not/ölçü).
- QC checklist (kategoriye göre şablon, elle düzenlenebilir).
- Tamlık skoru tam sürüm + kategoriye göre akıllı eksik-bilgi soruları.
Kabul: bir araştırma adayı teklif+numune adımlarından geçip tek tıkla sipariş
listesine düşüyor; firma portaldan teklif girince panelde "teklif geldi" görünüyor.

### V2-D — Lojistik Zinciri
Kapsam:
- PurchaseOrder (kesin sipariş) + ödeme kilometre taşları (kapora/bakiye/navlun
  — tutar/tarih/dekont; muhasebe DEĞİL).
- Shipment/konsolidasyon: çok tedarikçi → tek sevkiyat; koli/CBM/kg toplamları;
  B/L, ETD/ETA, takip alanları.
- Mal kabul (F08 genişlemesi): beklenen/gelen/eksik/hasar + foto; Claim kaydı
  (talep/refund/replacement/durum).
- Belgeler merkezi: sipariş başına dosya arşivi (proforma, packing list, dekont…)
  — storage sınırları K33'e uygun (dosyalar public dışı, boyut sınırlı).
- Durum zinciri: K22 makinesi İHTİYAÇ KADAR genişler (önerilen eklemeler:
  quote_pending, sample, production, shipped, customs — UI'da bağlama göre
  filtrelenmiş gösterim; 17 durum tek ekranda asla).
Kabul: bir sevkiyat 2 tedarikçinin ürünlerini birleştiriyor; mal kabulde eksik
girilen ürün claim'e dönüşüyor; tüm belgeler ürün/sipariş kartından açılıyor.

### V2-E — Zeka Katmanı
Kapsam (hepsi "yardımcı" statüsünde, Kaynak→Gerçek→Karar ilkesiyle):
- Çince→Türkçe ürün adı/özellik/SKU çevirisi + teknik özellik normalizasyonu
  (inference etiketi + güven yüzdesi zorunlu).
- Görsel benzerlik: önce image hash + başlık/öznitelik benzerliği; embeddings
  ileri aşama.
- F30-lite GTİP aday önerisi (opsiyonel — Ürün Sahibi kararına bağlı; K36 çerçevesi) + tarife sürümü/geçerlilik/
  doğrulayan kaydı.
- Landed cost lite: DDP fiyat + iç maliyet kalemleri → Türkiye depo birim ₺;
  adet senaryolu.
- Fiyat anomalisi / riskli ilan işaretleri; "Analiz Et" özet ekranı;
  semantic search.
Kabul: AI alanları arayüzde her zaman kaynak alanlarından görsel olarak ayrık;
tek bir AI çıktısı bile kaynak alanının üzerine yazmıyor.

## 2. Sıralama gerekçesi

A olmadan B'nin karşılaştırması, B olmadan C'nin teklif seçimi anlamsız;
D sahadaki gerçek operasyon (para/koli) olduğundan C'den sonra; E her faza
yardımcı ama bağımsız kapatılabilir en son katman. Belgedeki §61 öncelik
listesiyle uyumlu — tek fark: AI (E) bizde en sona alındı (belge de aynı
yönde: "AI intelligence katmanını sonra büyüt").

## 3. Faz başına çalışma düzeni

v1'deki düzen aynen: PM iş emri → Claude Code → PR → PM diff denetimi → merge
onayı → faz sonu tek release + tek kurulum + tek genel tur. Playwright E2E
Faz 3 sonrası geldiği için v2 fazlarının tümü otomatik uçtan uca turla korunur.
