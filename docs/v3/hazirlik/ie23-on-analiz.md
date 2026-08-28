# İE#23 ÖN ANALİZİ — V3-C paketinin (#15+#16) teknik okuması

> **Amaç:** PM'in İE#23 emrini derlerken kullanacağı teknik iskelet. Bu belge
> KARAR VERMEZ; işin büyüklüğünü, bugünkü şemaya eklemlenme noktalarını ve
> eklemlenemeyen yerleri gösterir.
>
> **Kaynak paket:** `docs/v3/hazirlik/v3-c/`
> — `teklif-turu-durum-makinesi.md` (10 durum, 15 geçiş)
> · `rfq-alan-sozlesmesi.json` (7 RFQ alanı + 19 firma yanıt alanı + 12 çapraz kontrol)
> · `portal-ekran-sartnameleri.md` (7 ekran + ortak mobil kabuk)
> · `portal-metinleri.json` (1017 satır sözlük)
> · `excel-gelgit-spec.md` (5 sayfalı çalışma kitabı)
> · `yapistir-ayristir-altin-seti.json` (30 örnek, kabul kapısı %90 alan doğruluğu / %0 yanlış ürün)
>
> **Koda dokunulmadı.** rc5 aday paketi kilitlidir.

---

## 0. EN ÖNEMLİ BULGU — V3-C bir ekran değil, İKİNCİ BİR UYGULAMA

Bugün paylaşım yüzeyi: **sunucuda üretilen tek sayfalık, salt-okunur HTML**
(`SharePage` + `ShareLockPage` + tek dış betik `/p-share.js`, satır içi script/stil
yok — K51). Yazma yok, oturum yok, taslak yok.

Portal şartnamesi ise şunları istiyor: 7 ekranlı **mobil kabuk**, alan bazlı
**otomatik kayıt**, **çevrimdışı taslak**, **iyimser kilit + çakışma ekranı**
(`round_version`), kısmi gönderim, satır sohbeti, üç dil anahtarlı ~1000 metin.
Bu, mevcut paylaşım sayfasına "birkaç form eklemek" değildir; **dışa açık,
hesapsız, yazan bir istemci uygulamasıdır**.

**Sonuç:** İE#23'ün ilk kararı teknoloji sınırıdır (CLAUDE.md §2 değiştirilemez:
React + Vite). İki seçenek var ve PM seçmelidir:

| Seçenek | Ne demek | Bedel |
|---|---|---|
| **A — ayrı Vite girdisi** (`/portal/`) | Panelden bağımsız ikinci bir React bundle; kendi rotaları, kendi i18n sözlüğü | Bundle ayrımı + dış yüzeyde React yüklemek; CSP ve K34 sınırları yeniden kurulur |
| **B — sunucu HTML + aşamalı geliştirme** | Formlar `<form>` olarak çalışır; otomatik kayıt/çevrimdışı için sınırlı, framework'süz JS | Şartnamedeki çevrimdışı taslak + çakışma ekranı bu yolla pahalıdır; büyük ihtimalle karşılanamaz |

Öneri: **A**, ama dış yüzey olduğu için ayrı güvenlik denetimiyle
(paylaşım sayfası dışa açık tek yüzeydir — XSS, CSP, oran sınırı, K34).

---

## 1. Durum makinesi: 5B'ye eklemlenme

Bugün (`config/durumlar.json`, docs/04 §5B):

- liste: `draft · sent · ordered · completed · cancelled`
- ürün: `to_order · ordered · in_transit · received · cancelled`

V3-C'nin birimi **liste değil**: `liste_id + firma_id + tur_no` üçlüsü
(`supplier_round_id`). Bu kritik ayrımdır ve iyi haberdir:

> **V3-C statüleri listenin durumunu DEĞİŞTİRMEZ; yeni bir varlığın durumudur.**
> `DRAFT · SENT · VIEWED · PRICING · RESPONDED · REVISION_REQUESTED · APPROVED ·
> ABANDONED · EXPIRED · REVOKED` — hepsi `supplier_rounds` satırının alanıdır.

Yani **5B durum makinesi bozulmadan** yanına ikinci bir makine konur. Bu, İE#22
ön analizindeki "iki ayrı statü dünyası" sorununun da çözümüdür: panorama
brifingleri (BRF-001..008) liste statüsüne değil, **tur statüsüne** bakmalıdır.

### Eşleme (spec §2'den, bugünkü modele çevrilmiş)

| Tur durumu | Liste tarafında karşılığı | Bugün var mı |
|---|---|---|
| `DRAFT` | liste `draft` kalır | ✅ |
| `SENT` | liste `sent` olur (ilk turda) | ✅ |
| `VIEWED`, `PRICING`, `RESPONDED` | liste `sent` kalır — bunlar **tur** bilgisidir | ✅ (liste tarafı değişmez) |
| `APPROVED` | liste `ordered`a geçebilir (Ürün Sahibi kararı) | ✅ |
| `ABANDONED`, `REVOKED` | liste değişmez; tur kapanır | ✅ |
| `EXPIRED` | liste değişmez; tur salt okunur | ✅ |

**Migration stratejisi (K23 ileri yönlü):**

1. `cikti-terimleri.json`daki `status.*` sözlüğü **belge/portal dili** olarak
   kalır; DB'ye **girmez** (K22: DB yalnız İngilizce makine kodu taşır — tur
   durumları zaten İngilizce enum).
2. Mevcut listelerin dönüştürülmesi **gerekmez**: yeni tablolar boş başlar,
   eski listeler turu olmayan listelerdir. Geri dönüşsüz veri dönüşümü YOK →
   göç riski düşük.
3. Tek gerçek dokunuş: `lists` üzerindeki paylaşım kolonları (§3).

---

## 2. Şema etkisi — yeni tablolar taslağı

RFQ sözleşmesi 7 satır alanı + 19 firma yanıt alanı + kademe alt şeması
tanımlıyor. Bugünkü şemada karşılığı **yok** (products/lists ürün kaydıdır,
teklif kaydı değildir).

| Tablo | Amaç | Kritik alanlar |
|---|---|---|
| `suppliers` | Firma kaydı (hesap DEĞİL, kayıt) | ad, iletişim kanalı, notlar |
| `supplier_rounds` | Turun kendisi | `list_id`, `supplier_id`, `round_no`, `parent_round_id`, `state`, `state_changed_at/by_type/reason`, `rfq_snapshot_id`, `rate_snapshot_id`, `share_id`, `round_version`, zaman damgaları (spec §3'te 20+ alan adı verilmiş) |
| `rfq_snapshots` + `rfq_snapshot_lines` | Tura kilitlenen değişmez RFQ görüntüsü | `rfq_satir_id` (uuid), ürün kodu, üç dilli ad, kaynak ürün, talep varyantı, miktar+birim, alıcı notu |
| `quote_responses` + `quote_response_lines` | Firma yanıtı (sürümlü) | 19 alan; para `DECIMAL(12,2)` **K14** (fiyat `decimal`, JSON'da string) |
| `quote_price_tiers` | Kademeli fiyat | `min_adet`, `max_adet`, fiyat — sıralı, pozitif, çakışmasız (spec çapraz kontrolleri) |
| `rate_snapshots` | Kur görüntüsü | **İE#22 ön analizindeki kur snapshot işiyle AYNI TABLO** — iki emir aynı şeyi istiyor |
| `shares` | Paylaşım kaydı (bkz. §3) | `list_id`, `supplier_round_id`, token, `key_hash`, `key_plain`, `enabled`, `expires_at`, `revoked_at` |

**Para disiplini uyarısı (K14/K24):** 19 yanıt alanının 6'sı sayısal para/ölçü
(`ddp_birim_fiyat_kdv_dahil`, kademeler, cbm, kg…). Sözleşme JSON'da `decimal`
diyor; DB'de `DECIMAL`, PHP'de **bcmath + string**, JS'te **aritmetik yok**.
Portalda firma fiyat girecek — istemci tarafında toplam/karşılaştırma HESAPLAMAZ,
yalnız gösterir. Bu, İE#23 emrinde açıkça yazılmalı.

**Çapraz akıl kontrolleri (12 adet)** sunucuda zorlanmalıdır — arayüzde değil.
Nihai gönderim kapısının 8 koşulu (spec §4) tek bir atomik işlemdir ve
`idempotency_key` ile çift tıklamaya karşı korunur (bugünkü `CaptureApplier`
idempotency deseni birebir örnek alınabilir).

---

## 3. Portal ekranları ↔ mevcut paylaşım altyapısı

| Bugün | V3-C'nin istediği | Fark |
|---|---|---|
| Anahtar `lists` tablosunda **tek kolon kümesi** (`share_key_hash`, `share_key_plain`, `share_key_enabled`, `share_expires_at`, migration 0021) | Her **(liste × firma × tur)** için ayrı link + ayrı anahtar | **Şema taşıması:** paylaşım `lists` kolonlarından ayrı `shares` tablosuna çıkar. Eski listeler tek satırlık `shares` kaydına göç eder (ileri yönlü, veri kaybı yok) |
| `ShareGate` + imzalı çerez, kapsam = token, ömür 12 saat (K62) | Aynı model, kapsam = `supplier_round_id` | ✅ mevcut desen doğrudan kullanılabilir |
| `ShareLockPage` — 6 hane, sabit hata dili (K51), hız sınırı 5/dk | Aynısı, portal girişinde | ✅ yeniden kullanılır |
| Salt-okunur liste sayfası | 7 ekranlı yazan uygulama | §0'daki mimari karar |
| `ShareDownload` (Excel/PDF/QR, imzalı) | Excel **gel-git**: indir → doldur → **geri yükle** | **Yeni:** yükleme ucu + doğrulama + `MANIFEST` sayfası imza kontrolü |

**Kör kıyas (spec §6) bir güvenlik gereksinimidir, arayüz tercihi değil:** URL,
HTML, Excel ve API yanıtlarında başka `firma_id`/`supplier_round_id` bulunmaz;
cache anahtarı `supplier_round_id + oturum` içerir. Bu, mevcut paylaşım
sayfasının "liste bazlı" varsayımını kıran tek maddedir ve testle korunmalıdır
(paylaşım sayfası dışa açık tek yüzeydir).

### K82 kesişimi — dikkat edilmesi gereken nüans

K82 (25 Ağu 2026) `NTF-SHARE-EXPIRY-NEAR/EXPIRED` olaylarını düşürdü; gerekçe
**anahtarın** süresiz olmasıydı (K62) ve bu doğrudur. Ancak ölçülen durum şudur:
**paylaşımın (linkin) süresi vardır** — `lists.share_expires_at` kolonu mevcuttur
ve `ShareController` + `PublicExportController` bunu zorlar.

V3-C spec §3 geçiş 14, `EXPIRED` durumunda `NTF-SHARE-EXPIRED` üretmeyi
öneriyor — yani düşürülen olayı çağırıyor. İE#23'te bu üç kavram ayrı ayrı
adlandırılmalıdır:

| Kavram | Süresi var mı | Bildirim |
|---|---|---|
| Erişim **anahtarı** (6 hane) | ❌ yok (K62) | yok (K82) |
| Paylaşım **linki** (`share_expires_at`) | ✅ var, bugün zorlanıyor | K82 bunu kapsam dışı bıraktı — İE#23'te yeniden değerlendirilmeli |
| **Teklif geçerliliği** (`valid_until`) | ✅ V3-C ile gelir | K82'nin işaret ettiği **yeni NTF** buraya açılır |

> **PM'e soru (İE#23 kararı):** link süresi dolduğunda bildirim istiyor muyuz?
> K82 "anahtar" gerekçesiyle yazıldı; link süresi ayrı bir olgudur ve verisi
> zaten var.

---

## 4. Yapıştır-ayrıştır: sunucu mu, istemci mi?

Altın set 30 örnek, kabul kapısı: **alan doğruluğu ≥ %90, yanlış ürün eşleşmesi
%0** ("tek olayda dahi otomatik ret"). Dil profilleri ağırlıklı ZH; kirli metin,
emoji, çoklu satır, para birimi belirsizliği, kademe çakışması gibi tuzaklar var.

**Yeri: SUNUCU.** Gerekçeler:

1. **Kabul sınavı testtir.** 30 örnek PHPUnit veri sağlayıcısı olarak koşulur;
   istemcide olursa vitest'e taşınır ama asıl kapı (yanlış eşleşme %0) sunucuda
   zorlanmalıdır — arayüzde zorlanan kural, API'den dolaşılır.
2. **Sözlük ve normalizasyon zaten sunucuda** (`Glossary`, `ValueSet::normalize`,
   çeviri belleği). Aynı normalizasyon iki yerde ayrı yaşarsa ayrışır (D5 dersi).
3. **Para hesabı JS'te yasak** (K14/K24). Ayrıştırıcı fiyat üretir; istemcide
   üretilen fiyat, gösterim değil veridir.
4. Metin kullanıcının panosundan gelir → **girdi doğrulama sınırı sunucudur**.

**İstemcinin işi:** yapıştırma kutusu, önizleme tablosu ve "şu satıra bağla /
belirsiz bırak" onayı. **Otomatik bağlama yoktur** — belirsizlik `—` kalır (K67)
ve kullanıcı onaylar (K54 deseni: makine önerir, insan onaylar).

**Excel gel-git ile ortak nokta:** ikisi de "dışarıdan gelen yapılandırılmamış
veriyi RFQ satırına bağlama" işidir. Aynı doğrulama çekirdeği (12 çapraz kontrol)
her ikisi tarafından kullanılmalıdır; iki ayrı doğrulayıcı yazmak, iki ayrı
davranış demektir.

---

## 5. Teklif turu × kur kilidi × K82 kesişimi

| Kavram | Bugün | V3-C'de |
|---|---|---|
| Kur | `lists.yuan_rate` — listeye kilitli (K4/K48) | `rate_snapshots` tablosu; **tur bazında** kilit (`rate_snapshot_id`) |
| Revizyon | yok | `rate_policy = inherit \| refresh` **zorunlu seçim**, audit'e yazılır |
| Sapma | — | `NTF-LIST-RATE-DRIFT` uyarısı; teklifi geçersiz KILMAZ |
| Firma görünümü | — | **İç karşılaştırma kuru firmaya GÖSTERİLMEZ** (spec §5.1) — kör kıyasın parçası |

**Çakışma riski:** bugünkü kur listeye kilitli; V3-C'de tur'a kilitli. Bir liste
üç firmaya gidince üç ayrı `rate_snapshot_id` olabilir. `lists.yuan_rate` bu
durumda "listenin varsayılan kuru" hâline gelir; **tek gerçek kaynak turdur**.
Bu, K4/K48'in genişletilmesidir ve **PM kararı gerektirir** (karar kaydına yeni
madde olarak girmeli).

---

## 6. BRF-001..008 aktivasyon noktaları (İE#22 ile bağ)

İE#22 ön analizinde "V3-C gelmeden tetiklenmez" denen sekiz brifing, tam olarak
şu geçişlerde canlanır:

| Brifing | Koşulu sağlayan tur durumu | Gereken alan |
|---|---|---|
| BRF-001/002 (fiyat bekleniyor, ≥5 gün / <5 gün) | `PRICING` | `supplier_rounds.pricing_started_at` |
| BRF-003 (onay bekliyor) | `RESPONDED` | `responded_at` |
| BRF-004 (hazır) | `DRAFT` + RFQ tam | RFQ satır doğrulaması |
| BRF-005 (fiyat geçerliliği yaklaşıyor) | `RESPONDED` + `valid_until` | teklif geçerlilik alanı |
| BRF-007 (firma bekliyor ≥3 gün) | `SENT` / `VIEWED` | `sent_at`, `first_viewed_at` |
| BRF-008 (süresi dolmuş) | `EXPIRED` | `expired_at` |

Yani **panorama brifinglerinin veri kaynağı `supplier_rounds` tablosudur** ve
zaman damgaları spec §3'te zaten adlandırılmıştır. İE#22'de panorama iskeleti
kurulurken bu sekiz şablon için **boş yer bırakmak yeterlidir**; sorgu İE#23'te
takılır.

---

## 7. Önerilen İE#23 iskeleti (PM derlerken)

| Blok | İçerik | Önkoşul |
|---|---|---|
| **A** | Şema: `suppliers`, `supplier_rounds`, `rfq_snapshots(+lines)`, `quote_responses(+lines)`, `quote_price_tiers`, `shares` (paylaşımın `lists`ten çıkması) | — |
| **B** | Tur durum makinesi: 10 durum, 15 geçiş, sunucuda zorlanır; nihai gönderim kapısı (8 koşul) atomik + idempotent | A |
| **C** | `rate_policy=inherit\|refresh` (tur bazlı kur seçimi) | A · **Kur snapshot altyapısı İE#22'DE yapılıyor** (PM kararı, 25 Ağu) — İE#23 hazır bulur, yalnız tura bağlar |
| **D** | Portal istemcisi: 7 ekran, üç dil, otomatik kayıt, çevrimdışı taslak, çakışma ekranı | §0 mimari kararı |
| **E** | Kör kıyas güvenlik testleri (URL/HTML/Excel/API'de yabancı `firma_id` yok) | B, D |
| **F** | Excel gel-git: üretim + geri yükleme + `MANIFEST` imzası + aynı doğrulama çekirdeği | A, B |
| **G** | Yapıştır-ayrıştır (sunucu) + 30 örneklik altın set sınavı (%90 / %0 kapısı) | A |
| **H** | 6 yeni NTF kodu (`NTF-QUOTE-*`) + katalog güncellemesi | İE#22-A (bildirim altyapısı) |

**PM kararı VERİLEN maddeler (25 Ağu 2026):**

- ✅ **Kur snapshot'ı İE#22'de yapılır**; İE#23 hazır bulur ve yalnız tura bağlar
  (`rate_policy`). `lists.yuan_rate` → snapshot geçişi K4/K48 genişletmesi olarak
  İE#22'de karar kaydına yazılacak. (§5)
- ✅ **K82 değişmez:** anahtar süresizdir (K62/K82 aynen). Link süresi bildirimi
  sorusu bu emirde açık kalır; paylaşım `shares` tablosuna taşınırken karara
  bağlanacak. **PM ön eğilimi:** `EXPIRED` bildirimi düşük değerdedir — firma
  zaten kilit ekranında görür; `EXPIRY-NEAR` ise "yenile" hatırlatması olarak
  değerlendirilebilir. (§3)

**PM'in karar vermesi gereken üç madde (İE#23 derlemesinde okunacak):**
1. **Portal mimarisi:** ayrı Vite girdisi (`/portal/`) mi, sunucu HTML mi? (§0)
   — PM notu: İE#23 derlemesinde karara bağlanacak, bu analiz masada olacak.
2. Link süresi (`share_expires_at`) bildirimi: `shares` taşıması bağlamında
   `EXPIRY-NEAR` "yenile" hatırlatması olarak açılsın mı? (§3)
3. 6 yeni `NTF-QUOTE-*` olayı katalog sürümüne girsin mi? (§7-H)
