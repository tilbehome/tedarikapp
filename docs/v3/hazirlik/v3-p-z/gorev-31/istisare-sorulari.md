# Görev #31 — PM ve Ürün Sahibi İstişare Soruları

**Tarih:** 2026-08-29  
**Amaç:** P–Z adaylarını karara dönüştürmeden önce kapsam, sıra ve sınır kararlarını görünür kılmak.  
**Yetki:** PM denetler; Ürün Sahibi karar verir.

## Karar özeti tablosu

| # | Karar konusu | Önerilen varsayılan | Kararı kim verir? | Etkilediği fazlar |
|---:|---|---|---|---|
| 1 | P'nin yayılım biçimi | Ortak altyapı + kritik ekran dalgaları | Ürün Sahibi, PM önerisiyle | P ve bütün sonrası |
| 2 | Q şartname zorunluluk seviyesi | Keşifte hafif, RFQ öncesi hazırlık kapısı | Ürün Sahibi | Q/R/U/V |
| 3 | Ana sıra mı risk-öncelikli sıra mı | Gerçek sipariş sapması yüksekse alternatif sıra | Ürün Sahibi | P–Z |
| 4 | R'de miktar/teklif bölme | İlk sürümde elle senaryo; otomatik optimum yok | Ürün Sahibi | R/D |
| 5 | S ayrı faz mı G genişlemesi mi | Ayrı veri modeli; takvimde G genişlemesi olabilir | PM | S/G/X |
| 6 | T'nin ilk teslim derinliği | T1 ölçü+sürüm+etki, sonra 2B; 3B kanıtlanırsa | Ürün Sahibi | T/D/U |
| 7 | U kalite kanıtını kim girer? | Kullanıcı/firma/üretici/bağımsız kaynak etiketi zorunlu | Ürün Sahibi | U/N |
| 8 | V/W'de yeni ana durum açılacak mı? | Hayır; alt nesne ve mevcut Panorama/Gelen Kutusu | PM, Ürün Sahibi onayıyla | V/W/B/D |
| 9 | Y ve Z V3'te mi? | Y yalnız whitelist; Z ihtiyaç kanıtlanmazsa V4 | Ürün Sahibi | Y/Z |
| 10 | Kalıcı RET'ler gevşetilecek mi? | Hayır; araştırma gevşetme gerektirmedi | Ürün Sahibi | Bütün harita |

## 1. P bütün ekranlara tek seferde mi uygulanmalı, yoksa dalga dalga mı?

**Karar ihtiyacı:** Ürün Sahibi “her ekranda her işlem” olgunluğu istiyor. Bu hedef yataydır; ancak tek kabul turunda bütün ekranlara yayılırsa regresyon alanı çok büyür.

**Seçenekler:**

- **A — Ortak altyapı + üç dalga (önerilen):** Önce selection/action/undo/view/paste altyapısı; sonra Keşif-Listeler, Teklif-Sipariş, Portallar-Ayarlar.
- **B — Yalnız en yoğun 5 ekran:** Daha hızlı çıkar fakat ürün genelinde davranış tutarsız kalır.
- **C — Bütün ekranlar tek faz kapanışı:** En bütüncül sonuç; en yüksek süre ve kabul riski.

**PM'nin netleştirmesi gereken kabul ölçüsü:** Her dalgada hangi ekranlar, hangi 10 ortak eylem ve hangi klavye kısayolları zorunludur?

## 2. Q şartnamesi sürecin hangi noktasında zorunlu olmalı?

**Karar ihtiyacı:** Çok erken zorunluluk keşfi yavaşlatır; çok geç zorunluluk karşılaştırılamaz teklif üretir.

**Seçenekler:**

- **A — Keşifte hafif taslak, firmaya RFQ çıkmadan hazırlık kapısı (önerilen).**
- **B — Ürün listeye girerken tam şartname:** Veri kalitesi yüksek, keşif hızı düşük.
- **C — Yalnız sipariş öncesi:** Hızlı fakat firma teklif turları eksik/çelişkili şartlarla yürüyebilir.

**Ürün Sahibi kararı:** “Hazır” sayılmak için ev/mutfak/banyo ürünlerinde ortak zorunlu minimum alanlar hangileridir?

## 3. Ana sıra mı, operasyonel risk öncelikli alternatif sıra mı seçilmeli?

**Ana sıra:** `P → Q → R → S → T → U → V → W → X → Y → Z`

**Alternatif sıra:** `P → Q → V → W → U → T → X → R → S → Y → Z`

**Karar ölçütü:** Son 6–12 aylık gerçek işte en büyük kayıp hangisinden geldi?

- Yanlış/eksik ürün şartı ve teklif kararı ağır basıyorsa **ana sıra**.
- Geç teyit, WhatsApp değişikliği, gecikme, kısmi sevkiyat veya kalite sapması ağır basıyorsa **alternatif sıra**.

**Ürün Sahibi kararı:** İlk üç P–Z fazının sonunda hangi somut günlük sorunun ortadan kalkmış olması bekleniyor?

## 4. R senaryo motoru miktarı farklı firma/tekliflere bölmeli mi?

**Karar ihtiyacı:** SAP Ariba tipi split award güçlüdür fakat tek ithalatçıda nadir kullanılıyorsa R'yi gereksiz büyütebilir.

**Seçenekler:**

- **A — Elle bölme + etki hesabı (önerilen):** Kullanıcı miktarı dağıtır; sistem DDP, CBM, MOQ ve termin sonucunu gösterir.
- **B — Yalnız tek teklif seçimi:** R, gelişmiş karşılaştırma olarak M'den S boya küçülebilir.
- **C — Otomatik optimizasyon:** V3 için önerilmez; yanlış kesinlik ve yüksek test maliyeti.

**Ürün Sahibi kararı:** Aynı ürün/sepet için gerçekten birden çok ithalatçı firmadan kısmi alım yapılıyor mu, yoksa karşılaştırma yalnız kazananı seçmek için mi?

## 5. S ayrı faz mı olmalı, G'nin genişlemesi mi?

**Değişmeyen ilke:** Teknik audit (“kim ne alanı değiştirdi?”) ile iş kararı (“neden bu teklif seçildi?”) ayrı veri türüdür.

**Seçenekler:**

- **A — S ayrı M faz:** Snapshot, varsayım, kanıt türü, bayatlık ve karar zinciri birlikte tamamlanır.
- **B — G genişlemesi (önerilen takvim alternatifi):** Aynı veri modeli korunur; ayrı harf süresi açılmaz.
- **C — Yalnız serbest not:** Önerilmez; aranabilir/karşılaştırılabilir karar hafızası oluşmaz.

**PM kararı:** S'nin veri modeli bağımsız kalırken geliştirme takviminde G bakım paketi olarak yürütülmesi daha mı gerçekçi?

## 6. T ilk sürümde 3B konteyner optimizasyonu içermeli mi?

**Seçenekler:**

- **A — Kademeli (önerilen):** T1 ambalaj seviyeleri+sürüm+CBM etkisi; T2 doğrulanabilir 2B/katman planı; T3 gerekirse 3B.
- **B — Baştan 3B optimizasyon:** Görsel değer yüksek; algoritma, ağırlık ve gerçek yükleme kısıtı riski de yüksek.
- **C — Yalnız CBM:** D tekrarına düşer; yeni faz gerekçesi zayıflar.

**Ürün Sahibi kararı:** Gerçek sevkiyatlarda konteyner doluluk/yerleşim problemi yeterince sık ve pahalı mı; yoksa asıl sorun koli ölçüsünün teklif–numune–mal kabul arasında değişmesi mi?

## 7. U'da kalite kontrolünü kim yapacak ve kanıtın güven seviyesi nasıl gösterilecek?

**Seçenekler:**

- **A — Çok kaynaklı kayıt (önerilen):** Kullanıcı, ithalatçı firma, üretici ve bağımsız kontrol ayrı kaynak etiketiyle aynı checklist yapısını kullanır.
- **B — Yalnız kullanıcı/ithalatçı firma:** Daha basit; üretim öncesi görünürlük sınırlı.
- **C — Haricî denetim hizmeti entegrasyonu:** Resmî API/haricî hizmet genişlemesine dönüşebilir; V3 ve RET sınırı için önerilmez.

**Ürün Sahibi kararı:** “Geçti” kararını kim verebilir; üretici öz kontrolü yalnız kanıt mı, yoksa karar mı sayılır?

## 8. V değişiklikleri ve W istisnaları mevcut 10 ana durumu değiştirecek mi?

**Önerilen karar:** **Hayır.** V'de `change_request/order_revision`, W'de `exception_case` ayrı alt nesne olur. Ana liste durumu 5B tek kaynaktan kalır.

**Netleştirilmesi gerekenler:**

- Ana satırda hangi rozetler gösterilecek?
- Bir sipariş aynı anda ana durumda ilerlerken açık değişiklik/istisna taşıyabilir mi?
- Açık kritik istisna hangi ana durum geçişlerini engeller?
- Firma portalında satır düzeyi kabul/karşı öneri zorunlu mu, yoksa yalnız iç kullanıcı mı change order oluşturur?

**PM kararı:** Ana durum makinesi bozulmadan alt nesnelerin geçiş kapıları nasıl tanımlanacak?

## 9. Y ve Z'nin V3 sınırı ne olmalı?

**Y için önerilen ilk sınır:** Yalnız whitelist iç eylemleri — görev/taslak oluştur, alan öner, kuyruğa ekle, uygulama içi işaretle. Serbest kod, webhook, e-posta/push, platform API'si ve otomatik sipariş yok.

**Z için seçenekler:**

- **A — V3 Z fazı:** P–Y sırasında en az üç gerçek kategori alanı ihtiyacı ve tekrar eden kod değişikliği kanıtlandıysa.
- **B — V4'e ertele (önerilen koşullu karar):** İhtiyaç henüz varsayımsa; V3 çekirdek şeması sabit tutulur.

**Ürün Sahibi kararı:** Bugün çekirdek modelde bulunmayan ve en az üç farklı ekranda gerçekten gereken özel alan/formül örnekleri nelerdir?

## 10. Kalıcı RET'lerden herhangi biri gevşetilmeli mi?

**Araştırma sonucu:** Gevşetme gerektiren bir kanıt bulunmadı. Olgun ürünlerdeki birçok yetenek TedarikApp'in özgün amacını genişletmek yerine ERP/marketplace/supplier-risk ürününe dönüştürür.

**Önerilen karar:** Aşağıdakiler aynen kalıcı RET olarak kalsın:

- GTİP/gümrük compliance,
- muhasebe/cari/stok/depo,
- pazaryeri satış API'leri,
- çok kiracılık/SaaS satışı,
- supplier due diligence,
- e-posta/push,
- resmî platform API'leri.

**Ürün Sahibi kararı:** Herhangi bir RET gevşetilecekse “emsalde var” gerekçesi yeterli sayılmasın; hangi doğrulanmış iş kaybını çözdüğü, neden mevcut harfle çözülemediği ve yeni kalıcı sınırı ayrı karar kaydıyla yazılsın. Böyle bir somut gerekçe yoksa **RET'ler değişmesin**.

## Karar toplantısı için önerilen çıktı

Toplantı sonunda yalnız şu dört kayıt yeterlidir:

1. Seçilen sıra ve ilk üç faz.
2. P'nin ekran dalgaları ile Q'nun hazırlık kapısı.
3. S/T/Y/Z için küçültme, birleştirme veya V4 erteleme kararı.
4. RET'lerin aynen korunduğuna ya da istisnai değişikliğin gerekçesine dair açık karar.

Bu belgede varsayılanlar öneridir; onay sayılmaz.
