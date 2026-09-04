# TedarikApp V4 Adayları

**Karar:** Ürün Sahibi — “şimdilik ertelendi”  
**Kaynak:** Görev #31-R, bölüm 3  
**Sınır:** Bu kayıtlar V3 P–Z fazlarına ve `faz-adaylari.json` dosyasına girmez.

## 1. GTİP / gümrük sınıflandırma

**Ne:** F30 tam sınıflandırma motoru ile K36 “lite” gümrükte takılma riski uyarısını aynı V4 değerlendirmesinde ele almak.

**Neden V3 dışında:** DDP modelinde beyannameyi ve resmî sınıflandırma sorumluluğunu ithalatçı/konsolidatör taşır. V3'te kalıcı RET sürer; ürün bilgi sistemi resmî sınıflandırma veya mevzuat kararı veren bir motora dönüşmez.

**Hangi kanıt V4'e taşır:** FOB/EXW modeline fiilen geçilmesi veya işletmenin kendi adına beyanname vermeye başlaması.

**Referans dosya:** `docs/v3/hazirlik/arsiv/tedarikapp-gtip-tareks-entegrasyon-raporu-2026-08-22.md` — içerik değiştirilmez.

## 2. TAREKS/ÜGD uyum kapısı ve başvuru hazırlığı

**Ne:** Ürün için uyum kapısı, gerekli belge/kanıt kontrolü ve başvuru hazırlık paketi.

**Neden V3 dışında:** DDP modelinde ithalatçı konsolidatördür; resmî başvuru ve uygunluk akışı Tilbe Home'un yürüttüğü iş değildir. V3'te kalıcı RET sürer.

**Hangi kanıt V4'e taşır:** FOB/EXW modeline geçiş veya işletmenin kendi adına beyanname vermesi; 1. adayla aynı tetik.

**Referans dosya:** `docs/v3/hazirlik/arsiv/tedarikapp-gtip-tareks-entegrasyon-raporu-2026-08-22.md` — içerik değiştirilmez.

## 3. Navlungo / yurtiçi kargo API

**Ne:** Landed cost iç nakliye fiyatını servis üzerinden almak ve ileride müşteri sevkiyatı operasyonunu taşıyıcı/entegratör üzerinden yürütmek; Navlungo, Shipink ve Kargonomi adaylarını karşılaştırmak.

**Neden V3 dışında:** V3-D'de iç nakliye tutarı elle girilir. Mevcut hacim dış servis entegrasyonu, hata/yeniden deneme ve taşıyıcı sözleşmesi yükünü doğrulamamıştır.

**Hangi kanıt V4'e taşır:** Aylık en az **N = ____** yurtiçi gönderi; eşik PM tarafından doldurulur.

**Referans dosya:** `docs/v3/hazirlik/v3-d/gorev-29/` — Görev #29 fizibilite raporu.

## 4. Deterministik formül ve rollup alanları

**Ne:** Özel alanlardan güvenli türetilmiş değer üretme, bağımlılık grafiği, döngü tespiti, yeniden hesaplama ve çıktı tutarlılığı.

**Neden V3 dışında:** 31-R ile Z yalnız bilgi taşıyan özel alan katmanı olarak sınırlandı. Özel alanlar ana durum, kur/fiyat hesabı ve skoru etkileyemez; formül motoru bu sınırı belirsizleştirir.

**Hangi kanıt V4'e taşır:** Aynı türetilmiş değerin en az üç kayıt türünde düzenli olarak elle hesaplandığının; mevcut rapor/çıktı hesaplarıyla çözülemediğinin ve finansal karar kaynağı olmayacağının kanıtlanması.

**Referans dosya:** `docs/v3/hazirlik/v3-p-z/gorev-31/v3-p-z-arastirma.md` — eski Z kapsamı ve §4 madde 38.

## 5. #31 “yeni faz değil” bulgularının V4 triyajı

Bu maddeler V3 P–Z'ye yeni faz olarak eklenmez. Aşağıdaki tablo, 31-R kararının “V3'e girmeyen diğer maddeler de bu dosyada kalsın” gereğini karşılar; “mevcut harfte” sonucu verilen satırlar V4 adayı değil, mevcut fazın kabul kapsamıdır.

| Ne | Neden V3 P–Z dışında | Hangi kanıt V4'e taşır | Referans dosya |
|---|---|---|---|
| Görselle aynı ürünü arama, fiyat geçmişi, görsel indirme | E/K ve F/L'nin mevcut keşif/izleme kapsamıdır | Mevcut harflerin kabulü sonrasında çözülemeyen, ölçülmüş ayrı iş kaybı | `docs/v3/hazirlik/v3-p-z/gorev-31/v3-p-z-arastirma.md` §5 |
| Amazon anahtar kelime, yorum ve talep sinyali analizi | F zekâ ve L trend keşfinin çekirdeğidir | F/L kapsamının gerçek kullanım verisiyle yetersiz kaldığının kanıtı | Aynı dosya §5 |
| Temel RFQ, teklif turu ve kör kıyas | V3-C'de zaten vardır | C kabulünden sonra ayrı veri/iş akışı gerektiren ölçülmüş kayıp | Aynı dosya §5 |
| PO, ödeme planı, CBM, landed cost ve mal kabul temeli | V3-D'de zaten vardır | D kabulünden sonra P–Z derinlikleriyle çözülemeyen ayrı operasyon kaybı | Aynı dosya §5 |
| Numune ve AQL planı | V3-I'da zaten vardır | I kabulünden sonra mevcut numune sürecinin çözemediği yeni model kanıtı | Aynı dosya §5 |
| SKU ve etiket üretimi | V3-J'de zaten vardır | J kabulünden sonra çözülemeyen ayrı üretim/etiket iş akışı kanıtı | Aynı dosya §5 |
| Çok dilli çıktı | V3-M'de zaten vardır | M/K56 hattının kabul sonrasında karşılayamadığı yeni dil/çıktı gereği | Aynı dosya §5 |
| Rol, portal ve haricî taraf | V3-N/O ve G'de zaten vardır | Mevcut rol/portal sınırının kabul sonrasında ölçülmüş bir işi engellemesi | Aynı dosya §5 |
| Genel dashboard ve rapor | V3-B/F'de zaten vardır | B/F kabulünden sonra ayrı karar nesnesi gerektiren doğrulanmış analiz ihtiyacı | Aynı dosya §5 |
| Süreç KPI/bottleneck görünümü | V3-F rapor genişlemesidir | F'nin mevcut rapor yapısıyla üretilemeyen, düzenli kullanılan karar metriği | Aynı dosya §4 madde 39 |
| Belge/tekliften taslak veri çıkarma | P/C/K içinde insan onaylı taslak olarak ele alınır | Kaynak çeşitliliği ve hacmin mevcut taslak hattını aşması | Aynı dosya §4 madde 40 |

## 6. Kalıcı RET listesi — #31 ile birebir

Bu satırlar ertelenmiş V4 özelliği değildir. Kalıcı RET kararı değişmemiştir; aşağıdaki on satır #31'deki listeyi birebir korur.

| RET çakışması | Emsallerde görülen desen | Neden aday değil |
|---|---|---|
| **GTİP/gümrük compliance** | Jungle Scout HS/import kayıtları; QIMA compliance; ERP gümrük modülleri | Kalıcı RET; TedarikApp kalite kontrolü mevzuat uygunluk motoruna dönüşmez |
| **Muhasebe/cari/fatura** | Procurify three-way match; Çin ERP'lerde 对账/开票/付款 | Kalıcı RET; D yalnız operasyonel ödeme kaydı/snapshot tutar |
| **Stok/depo/replenishment** | Amazon Restock, Zentail inventory, 领星/店小秘/马帮 WMS | Kalıcı RET; X tekrar sipariş hafızası stok önerisi üretmez |
| **Pazaryeri satış API'leri** | Zentail ve cross-border ERP listing/order sync | Kalıcı RET; pazar siteleri yalnız keşif/talep sinyalidir |
| **Supplier due diligence/risk** | Jungle Scout import records, QIMA supplier score, SRM onboarding/risk | Kalıcı RET; U yalnız sipariş/ürün kalite kanıtını tutar |
| **Çok kiracılık/SaaS satışı** | Kurumsal procurement tenant/organizasyon yapıları | Tek kullanıcı ve belirli dış portallar yeterlidir |
| **E-posta/push bildirim** | Anvyl, Coupa, SourceDay e-posta teyitleri | Kalıcı RET; W/Y yalnız uygulama içi Gelen Kutusu/Panorama kullanır |
| **Resmî platform API'si** | ERP–1688 doğrudan sipariş/ödeme; marketplace entegrasyonları | Kalıcı RET ve “sitelerden alım yok” ilkesi |
| **Alibaba escrow/Trade Assurance ödeme akışı** | Platform içi ödeme ve koruma | TedarikApp gerçek işlemi ithalatçı firma üzerinden yürütür; yalnız şart/kanıt deseni alınır |
| **Kurumsal çok kademeli onay** | Coupa/SAP/Procurify approval routing | G/N rol sınırını büyütür; tek iç operatöre değer düşük |

**V4'e taşıyıcı kanıt:** Bu dokuz genel RET satırı için olağan kullanım metriği yoktur; yalnız PM + Ürün Sahibi'nin ayrı ve bağlayıcı ürün sınırı kararı yeniden araştırma açabilir. İlk satırın somut taşıyıcı kanıtı bu dosyanın 1. bölümünde ayrıca tanımlıdır.

**Referans dosya:** `docs/v3/hazirlik/v3-p-z/gorev-31/v3-p-z-arastirma.md` §6.

## V4'e taşıma kapısı

Bir madde yalnız emsal üründe bulunması nedeniyle V4'e alınmaz. Taşıma için tetik kanıtı, ölçülebilir iş kaybı, mevcut V3 harfiyle neden çözülemediği, kalıcı ürün sınırı ve insan onayı/gerçeklik kaynağı etkisi birlikte karar kaydına yazılır.
