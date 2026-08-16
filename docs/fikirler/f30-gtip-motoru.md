# F30 — GTİP, Gümrük ve İthalat Sınıflandırma Motoru

## 0. Kapsam Notu — K36 (16 Ağu 2026, PM · KESİNLEŞTİ)

**Açık soru yanıtlandı:** DDP'de ithalatçı **biz değiliz**. Tedarikçi/konsolidatör ithal ediyor, bize yurt içi teslim yapılıyor (**Senaryo B**). Dolayısıyla GTİP beyan sorumluluğu ve Gümrük Kanunu m.234 ceza riski üzerimizde doğmuyor — tam sınıflandırma motorunun ana gerekçesi düşüyor.

**K36 hükmü:**

- **Birincil hedef `F30-lite`'tır:** yakalanan üründe anahtar kelime + kategori eşleşmesiyle riskli grup tespiti → *"Bu ürün TAREKS / CE / gözetim / ürün güvenliği denetimi kapsamında olabilir. Sipariş öncesi müşavirine danış."* Çıktı bir **uyarıdır, GTİP kodu değildir**; yanlış olduğunda mali/hukuki zarar doğurmaz. Kaynak: Ürün Güvenliği ve Denetimi Tebliğleri'ndeki GTİP listeleri (Resmî Gazete → FSEK m.31, serbest kullanım). Tahmini maliyet: tam motorun ~%10'u.
- **Aşağıdaki tam motor taslağı KORUNUR** — silinmez, küçültülmez. v2 planlaması açıldığında varsayılan kapsam F30-lite olur; **tam motor şu tetikleyicilerden birine bağlıdır:** ithalat modeli **FOB/EXW**'ye geçer · **kendi adımıza beyanname** vermeye başlarız · konsolidatörden bağımsızlaşma / **doğrudan ithalat** gündeme gelir.
- F30, Ürün Sahibi'nin "önemli" bayrağıyla **v2 öncelikli aday olmayı sürdürür**; K36 fikri geri plana atmaz, kapsamını gerçek ihtiyaca kalibre eder.
- Rezidüel risk sıfır değil: ithalatçı olmasak da TAREKS/CE kapsamındaki ürün gümrükte takılırsa **bekleyen mal ve bağlanan para bizim**. F30-lite tam olarak bu riski hedefler.

> ⚠️ Doğrulanmış kaynaklar ve v1-dışı hükmü için bkz. [gtip-m23-degerlendirme.md](../arastirma/gtip-m23-degerlendirme.md).
> Bu belgedeki bazı tasarım kararları (yıl bazlı sürümleme, otomatik BTB eşleştirme) orada düzeltilmiştir — ikisini birlikte okuyun.
>
> Durum: FİKİR TASLAĞI — DETAYLI PLAN (havuz kaydı F30) · Hedef: **v2 öncelikli modül** (Ürün Sahibi "önemli" bayrağı)
> Şu an kapsam DIŞI (K17) — bu belge, günü geldiğinde iş emirlerine bölünecek hazır yol haritasıdır.
> Kaynak: Ürün Sahibi'nin paylaştığı iki dış analiz (16.08.2026), PM birleştirmesi ve şerhleriyle.

## 1. Temel İlke (taviz yok)
**Sistem asla "kesin GTİP" üretmez.** Akış: 1688 teknik verisi → normalize ürün teknik profili → resmî tarife veritabanı → kural + benzerlik eşleştirme → **GTİP ADAYLARI** (gerekçe + güven seviyesi) → eksik bilgi soruları → **insan / gümrük müşaviri doğrulaması** → gerekiyorsa BTB referansı. Hukuki sorumluluk her zaman insanda kalır; `verified` durumu yalnız insan onayıyla atanır.

## 2. GTİP Yapısı (modellenecek hiyerarşi)
2 hane fasıl → 4 pozisyon → **6 HS (uluslararası ortak)** → 8 AB/CN → 10 millî açılım → **12 tam GTİP**. Çin tarafında bulunan HS-6 güçlü aday sinyalidir; Çin'in 8/10 haneli kodu asla Türkiye 12 hanesi yerine kullanılmaz.

## 3. Resmî Tarife Verisi
Kaynak: Ticaret Bakanlığı'nın her yıl Resmî Gazete kararıyla yayımladığı Türk Gümrük Tarife Cetveli (resmî Excel). Yıl içi ara değişiklikler olur → sürümleme şart.
```
tariff_versions   id, year, decision_no, source_ref, effective_from, effective_to,
                  source_hash, imported_at
tariff_codes      id, tariff_version_id, gtip_12, hs_6, cn_8, national_10,
                  description_tr, unit, parent_code, chapter, heading, active,
                  effective_from, effective_to
```
Ağaç gezinme + arama (kod ve tanımda). Import aracı: resmî Excel → doğrulama → içeri alma; kaynak dosya hash'iyle kayıt.

## 4. Ürün Teknik Profili (motorun girdisi)
```
product_technical_profiles  product_id, primary_material, secondary_materials,
    material_percentages, primary_function, intended_use, electrical (bool),
    voltage, power_w, capacity, dimensions, weight, textile_composition,
    woven_or_knitted, set_or_single, translated_attributes (JSON),
    source_confidence, built_from_capture_id
```
Kaynak: parser'ın yakaladığı `featureAttributes`/CPV alanları (malzeme, güç, kapasite, işlev...), orijinal Çince başlık, SKU farkları. **Ham Çince değerler asla kaybolmaz**; çeviri ayrı alandır. Profil GTİP dışında tedarikçi karşılaştırma ve AI için de yeniden kullanılır.

## 5. Sınıflandırma Kaydı
```
product_tariff_classifications  id, product_id, sku_id (null), tariff_code_id,
    status, confidence, classification_reason, source_type (ai|manual|musavir|btb),
    classifier_version, verified_by, verified_at, effective_from, effective_to,
    btb_reference, notes
```
Durum enum'u (K22 standardı): `suggested → needs_information → reviewed → verified → btb_confirmed` (+ `obsolete`). Ürün GTİP'i tek alan olarak TUTULMAZ — her kayıt tarife sürümüne, tarihe, kaynağa ve gerekçeye bağlıdır. **SKU override:** varsayılan miras; malzemesi/işlevi gerçekten farklı varyantta SKU seviyesinde ayrı kayıt.

## 6. Motor (hibrit — yalnız AI DEĞİL)
Sıra: resmî tarife hiyerarşisi + tanımları → Genel Yorum Kuralları → erişilebilirse açıklama notları/sınıflandırma kararları → benzer BTB örnekleri → deterministik öznitelik kuralları (malzeme/işlev/elektrik) → AI semantik aday sıralama (yalnız karar destek). Eksik kritik bilgi varsa tahmin yerine **kullanıcıya soru**: "Paslanmaz çelik mi plastik mi?", "Elektrikli mi?", "Set mi tek ürün mü?".

## 7. Sonuç Ekranı (taslak)
Önerilen GTİP + **sınıflandırma güven seviyesi** (düşük/orta/yüksek — "AI güveni" denmez, hukuki kesinlik imasından kaçınılır) + gerekçe maddeleri + alternatif adaylar + eksik bilgi listesi + aksiyonlar: Doğrula / Başka GTİP seç / Müşavire gönder / BTB araştır / Teknik bilgi iste.

## 8. BTB (Bağlayıcı Tarife Bilgisi)
BTB kayıtları bağlanabilir: referans no, tarih, geçerlilik, kod, eşya tanımı, gerekçe. Benzer BTB **güçlü sinyal** olarak gösterilir; başkasının BTB'si bizim eşyamız için bağlayıcı KABUL EDİLMEZ. Yüksek adetli/riskli üründe sistem "yüksek mali risk — BTB önerilir" uyarısı verebilir.

## 9. GTİP Teknik Tanım Dosyası (otomatik çıktı)
Üründen tek tıkla üretilir: ticari ad, orijinal Çince başlık, Türkçe teknik tanım, kullanım amacı, malzeme+oranlar, çalışma prensibi, elektrik/güç, ölçü/ağırlık/kapasite, model/SKU, fotoğraflar, satıcı bilgisi, 1688 linki, önerilen + alternatif GTİP'ler, gerekçe, eksikler. Müşavire ve BTB başvuru hazırlığına gider.

## 10. Yıllık Güncelleme Motoru
Yeni cetvel yüklenince otomatik karşılaştırma: değişmedi / tanım değişti / kaldırıldı / bölündü. Ürünler otomatik taşınmaz — **inceleme kuyruğuna** düşer ("37 ürünün tanımı değişti, 8'inin kodu kalktı").

## 11. Menşe Ayrımı
`country_of_origin` ≠ `country_of_dispatch` — ayrı alanlar. Çin'den gönderim ≠ Çin menşei. Vergi/önlem analizi (ileri katman) ikisini birlikte kullanır.

## 12. Vergi/Mevzuat Katmanı (GTİP'ten AYRI, daha sonra)
GTİP+menşe+tarih → gümrük vergisi, İGV, KDV, ÖTV, gözetim, anti-damping, TAREKS, ürün güvenliği/izinler. Resmî ve sürdürülebilir veri kaynağı doğrulanmadan kurulmaz; **TARA'ya scraping bağımlılığı YAPILMAZ.**

## 13. Sınırlar ve Şerhler (PM)
- Capture akışını asla bloklamaz — sınıflandırma arka plan işidir.
- Yıllık cetvel + ara değişiklik takibi kalıcı bakım yüküdür; üstlenildiği bilinerek açılır.
- Faz sırası önerisi: v2 açılışında (a) tarife import + arama/ağaç + ürüne elle GTİP bağlama, (b) teknik profil + aday motoru, (c) BTB + teknik dosya + yıllık diff. AI sıralaması en sonda.
- Bugünkü mimari hazırlığı tamam: parser spec/malzeme alanlarını, orijinal başlığı, SKU matrisini ve `(platform, external_id)` kimliğini zaten topluyor.
