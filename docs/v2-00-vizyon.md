# tedarikapp v2 — Vizyon (v2-00)

Durum: TASLAK (PM hazırladı, 19 Ağu 2026) · Onay: Ürün Sahibi
Yerleşim hedefi: repo `docs/v2/00-vizyon.md`

## 1. Tek cümlelik tanım

> Çin'deki herhangi bir ürün kaynağından ürünü saniyeler içinde tedarikapp'e al;
> ham veriyi koru, Türkçeleştir ve normalize et; aynı ürünün alternatif
> tedarikçilerini bul ve karşılaştır; araştırmadan kısa listeye, tekliften
> sipariş listesine, sevkiyattan mal kabule kadar tüm süreci tek zincirde yönet.

v1 (Faz 1–5) bu zincirin "sipariş listesi + iletim + panel" halkasını kurar.
v2, zincirin önünü (araştırma/karşılaştırma) ve arkasını (teklif/lojistik/mal
kabul) ekler.

## 2. v1 → v2 sınırı

| | v1 (Faz 1–5) | v2 |
|---|---|---|
| Kaynak | 1688 (+ Tmall/Taobao modülleri) | Çok kaynak (P0–P3 stratejisi) |
| Akış | ürün → liste → Excel/PDF/paylaşım | araştırma → karşılaştırma → liste → sipariş → lojistik → mal kabul |
| Veri modeli | products (platform+external_id) | Product ≠ SourceListing + ResearchProject/Candidate |
| Zeka | yok (kural tabanlı) | AI normalizasyon/çeviri/benzerlik (yardımcı, asla kaynak verisi yerine geçmez) |

## 3. Değişmez ilkeler (v1'den miras)

- **K34 güvenlik çizgisi:** her kapıya kendi anahtarı; anahtarlar DB'de düz durmaz.
- **K13/K33 gerçekçilik:** paylaşımlı hosting sınırları; Redis/Kafka/K8s YOK;
  MariaDB job queue + cron yeter.
- **K22 sadelik:** durum makinesi ihtiyaç doğdukça büyür; 17 durumlu zincir tek
  seferde eklenmez.
- **K24/K29 para disiplini:** tüm hesap MoneyService, bcmath, string taşıma.
- **Kaynak → Gerçek → Karar (v2'nin anayasa maddesi):** her bilgi üç katman —
  RAW (orijinal, dokunulmaz) → NORMALIZED (çevrilmiş/standart) → KARAR (insan
  onaylı). AI çıktısı daima "inference" etiketiyle ve güven yüzdesiyle durur;
  kaynak verisi gibi gösterilemez.

## 4. Kapsam dışı (RET — gerekçeli)

| Konu | Gerekçe | Yeniden açılma tetikleyicisi |
|---|---|---|
| Resmî platform API'leri (1688/Taobao açık platform) | Çin işletme kaydı + onaylı uygulama gerektirir; erişimimiz yok. Tarayıcı-içi yakalama (kullanıcının kendi oturumu) kalıcı strateji | Aracı firma üzerinden API erişimi doğarsa |
| Tam ithalat uygunluk motoru (İGV/TAREKS/anti-damping) | K36: DDP'de beyan sorumluluğu bizde değil; F30-lite ("gümrükte takılır mı" uyarısı) yeter | FOB/EXW'ye geçiş · kendi adına beyan · doğrudan ithalat |
| Supplier due diligence (banka hesabı takibi, audit, lisans doğrulama) | Tek tedarik kanalı konsolidatör firma; çok-tedarikçili doğrudan ithalatçı işi | Doğrudan fabrika ilişkisi kurulursa |
| Sertifika "geçerli/geçersiz" hükmü veren AI | Sorumluluk insanda/müşavirde kalır; sistem yalnız "uyuşmazlık var, incele" işaretler | — (kalıcı ilke) |

## 5. Başarı ölçütleri

1. Bir ürünü herhangi bir desteklenen sitede görüp araştırma havuzuna almak ≤ 5 sn.
2. Aynı gerçek ürünün 2+ kaynak ilanı tek kartta karşılaştırılabiliyor.
3. Sipariş listesi, araştırma havuzundan tek tıkla türetilebiliyor.
4. Bir yıl önceki ürün "tekrar sipariş" ekranından güncel fiyat farkıyla açılıyor.
5. RAW veri hiçbir işlem sonrasında kaybolmuyor (her capture geri okunabilir).
