# tedarikapp v2 — Dış Vizyon Belgesi Madde Eşlemesi (v2-03)

Durum: PM kararı, 19 Ağu 2026 · Kaynak: Ürün Sahibi'nin ilettiği 61 maddelik
dış AI vizyon belgesi · Yerleşim: `docs/v2/03-madde-eslemesi.md`

Efsane: **F5** = v1 Faz 5 (f40) · **V2-A..E** = v2 fazı · **RET** = gerekçeli ret
(bkz. v2-00 §4) · **İLKE** = anayasa maddesi olarak v2-00'a işlendi

| § | Konu | Karar |
|---|---|---|
| 1 | Ana omurga şeması | V2 geneli (v2-00/01'e işlendi) |
| 2 | Araştırma Havuzu (ResearchProject/Candidate) | V2-A |
| 3 | İlan ≠ Ürün | V2-A (K32 kapısında kesinleşir) |
| 4 | Universal Connector | V2-B (Faz 3 mimarisi zaten bu yönde) |
| 5 | 4 veri katmanı (API/Ext/Dosya/Manuel) | Ext+Manuel v1'de; Dosya/Görsel import V2-B havuz; API RET |
| 6 | Side Panel eklenti | Faz 3 emrine değerlendirme notu |
| 7 | Hızlı / Derin yakalama | Faz 3 (hızlı) + V2-A (derin) |
| 8 | RAW/NORMALIZED/DISPLAY | İLKE + V2-A |
| 9 | Alan bazlı kaynak izi | V2-A |
| 10 | Tamlık skoru | F5 (lite) + V2-C (tam) |
| 11 | Kategoriye göre akıllı eksik bilgi | V2-C |
| 12 | AI operasyon motoru tablosu | V2-E |
| 13 | Görsel benzerlik | V2-E (hash+başlık önce, embeddings sonra) |
| 14 | Karşılaştırma matrisi | V2-B |
| 15 | Adet bazlı fiyat simülasyonu | F5 (veri Faz 3'te) + V2-B tam |
| 16 | Birim standardizasyonu | F5 (lite gösterim) + V2-B (motor) |
| 17 | MOQ veri modeli | V2-B |
| 18 | Supplier Intelligence | V2-B/C (kendi geçmişimiz öncelikli) |
| 19 | Supplier due diligence / banka takibi | RET |
| 20 | RFQ üretici | V2-C |
| 21 | Supplier portalı | V2-C (F26'nın gerçeklenmesi) |
| 22 | Teklif versiyonları | V2-C |
| 23 | Numune yönetimi | V2-C |
| 24 | QC checklist | V2-C |
| 25–26 | GTİP çekirdek yardımcı + zengin kayıt | V2-E (F30-lite — opsiyonel, Ürün Sahibi kararına bağlı; K36 çerçevesi) |
| 27 | İthalat uygunluk motoru (İGV/TAREKS) | RET (tetikleyicili) |
| 28 | Compliance File | RET (F30-lite notu yeter) |
| 29 | Sertifika doğrulama | RET ("hüküm veren AI" hali); uyuşmazlık-işaretleyici hali V2-E havuz |
| 30–31 | Landed cost + senaryo | V2-E (lite, DDP'ye göre) |
| 32 | Sipariş listesi = araştırma sonucu | V2-A/C akışı |
| 33 | Liste snapshot/versiyon | v1'de var (K50); v2'de versiyon farkı ekranı V2-C |
| 34 | 17+ durumlu zincir | RET; küçük makineler (v2-02 §5) |
| 35–36 | Konsolidasyon / Shipment | V2-D |
| 37 | Belgeler merkezi | V2-D |
| 38 | Ödeme takibi | V2-D |
| 39–40 | Mal kabul / Claim | V2-D (F08 genişlemesi) |
| 41 | Tekrar sipariş ekranı | V2-B (ListingSnapshot meyvesi) |
| 42 | Universal search | F5 |
| 43 | Semantic search | V2-E |
| 44 | Command palette | F5 (zaten vizyonda) |
| 45 | Global Inbox | Faz 3 Gelen Kutusu + V2-B genişleme |
| 46 | Duplicate engine | V2-B |
| 47 | Source freshness | F5 (rozet) + V2-B (yenile/snapshot) |
| 48 | Değişiklik zekâsı | V2-B (F09 ile birleşir) |
| 49 | Watchlist | V2-B havuz (kullanıcı-kontrollü refresh; anti-bot aşma YOK) |
| 50 | Platform önceliği P0–P3 | V2-B (F37 ile birleştirildi) |
| 51 | source_type (retail/wholesale…) | V2-A şemasına |
| 52 | Medya arşivi + usage_scope | V2-A (kolon eklemesi) |
| 53 | Medya seçici | V2-C |
| 54 | "Analiz Et" ekranı | V2-E |
| 55 | Sipariş kontrolü motoru | F5 (lite) + V2-C |
| 56 | Ana dashboard | F5 |
| 57 | Sistem sağlığı | F5 (lite) + V2-B (connector health) |
| 58 | Teknik mimari (Slim+MariaDB+MV3, Redis'siz) | Onay — mevcut çizgiyle aynı |
| 59 | Veri modeli tablosu | v2-02'ye pragmatik alt küme olarak işlendi |
| 60 | Kaynak→Gerçek→Karar | İLKE (v2-00 §3) |
| 61 | Öncelik sıralaması | v2-01 sıralamasıyla uyumlu (AI en sona) |
