# İE#22 ÖN ANALİZİ — Görev #13 paketinin teknik okuması

> **Amaç:** PM'in İE#22 emrini derlerken kullanacağı teknik iskelet. Bu belge
> KARAR VERMEZ; ne kadar iş olduğunu, neyin mevcut veri modeline bağlandığını ve
> neyin bağlanamadığını gösterir.
>
> **Kaynak paket (Görev #13):** `docs/v3/hazirlik/v3-b/`
> — `panorama-brifing-katalogu.json` (18 brifing şablonu, 9 veri alanı ailesi)
> · `bildirim-olay-katalogu.json` (39 olay, 5 grup)
> · `ayarlar-bilgi-mimarisi.md` (16 sekme)
> · `ekran-durum-metinleri.json` (24 kayıt × BOŞ/YÜKLENİYOR/HATA).
>
> **Koda dokunulmadı.** rc5 aday paketi kilitlidir.

---

## 0. EN ÖNEMLİ BULGU — statü sözlüğü iki ayrı dünya

Brifing kataloğunun koşulları `status.waiting_price`, `status.waiting_approval`,
`status.ready`, `status.waiting_supplier`, `status.expired`, `status.archived`
gibi değerlere dayanıyor. Bunlar `docs/v3/hazirlik/cikti-terimleri.json`daki
**V3 (teklif/RFQ) statü sözlüğüdür** ve bugünkü sistemde YOKTUR:

| Kaynak | Statü kümesi |
|---|---|
| Bugün çalışan sistem (`config/durumlar.json`, docs/04 §5B) | liste: `draft · sent · ordered · completed · cancelled` · ürün: `to_order · ordered · in_transit · received · cancelled` |
| Brifing kataloğunun beklediği (V3 sözlüğü) | `preparing · waiting_price · found · not_found · alternative_available · waiting_approval · approved · rejected · ready · missing_data · sent · archived · cancelled · waiting_supplier · expired` |

**Sonuç:** 18 brifing şablonunun **8'i** (BRF-001..008) bugün var olmayan bir
durum makinesine bağlıdır. Bunlar ancak V3-C (teklif/RFQ akışı, firma portalı)
geldiğinde anlam kazanır. İE#22 bu şablonları "yazıp boş dönmek" yerine
**kapsam dışı bırakmalı ya da V3-C ile birlikte planlanmalıdır**; aksi hâlde
panorama ekranı, koşulu hiç sağlanmayan sekiz şablon taşır.

Bu, K22 ile de tutarlı bir uyarıdır: DB ve API yalnız İngilizce makine kodu
taşır ve durum makinesi docs/04 §5B'dedir; ikinci bir statü kümesini panorama
uğruna icat etmek, tek gerçek kaynağı ikiye böler — PM kararı gerektirir.

---

## 1. Brifing koşulları → veri modeli haritası

Bugünkü şemayla **doğrudan bağlanabilen** şablonlar:

| Şablon | Koşul | Bağlanacağı kaynak | Durum |
|---|---|---|---|
| BRF-006 | `eksik_urun_sayisi > 0` | `products` + `hazir_eksikleri` mantığı (B2'de var: `frontend/src/lib/eksikler.ts` sunucu alanını okur) | ✅ hazır — sorgu servise taşınmalı |
| BRF-009 | `gelen_kutusu_bekleyen > 0` | `inbox_items WHERE status='pending' AND assigned_product_id IS NULL` | ✅ hazır |
| BRF-010 | `kuyruk.retry_wait > 0` | `jobs.status` (0024 + 0028) | ✅ hazır |
| BRF-011 | `kuyruk.dead > 0` | `jobs.status='dead'` | ✅ hazır (panelde ölü mektup ekranı da var) |
| BRF-012 | `kuyruk.ready>0 ve en_eski_hazir_dakika>=15` | `jobs.status='ready'` + `MIN(available_at)` | ✅ hazır |
| BRF-013 | `kur.aktif_yas_saat >= 24` | `settings` kur değeri + güncelleme zamanı | ⚠️ **kısmi** — kurun "aktif sürüm zamanı" tutulmuyor; kur snapshot sürümlemesi gerekiyor (Ayarlar IA §7 de bunu istiyor) |
| BRF-017 | `ceviri.bekleyen > 0` | `jobs WHERE tur='ceviri' AND status IN(ready,retry_wait)` | ✅ hazır |
| BRF-018 | `ceviri.basarisiz_son_24s > 0` | `jobs` başarısız + `attempts` | ✅ hazır |

**Veri modeli EKSİĞİ olan şablonlar:**

| Şablon | Eksik olan | Gereken iş |
|---|---|---|
| BRF-001..004, BRF-007, BRF-008 | V3 statü kümesi + `status_changed_at` (durumdaki bekleme yaşı) | Durum makinesi genişletmesi (V3-C) + statü değişim zaman damgası kolonu |
| BRF-005 | `teklifler.valid_until` (fiyat geçerliliği) | Teklif varlığı yok — V3-C |
| BRF-014, BRF-015 | Yakalama başarım telemetrisi (`son_24s_toplam`, `basarim_yuzde`, `son_basarili_saat`) | Yakalama denemeleri (başarısız olanlar dahil) kaydedilmiyor; bugün yalnız BAŞARILI yakalama `inbox_items`a düşüyor. **Yeni tablo/olay günlüğü gerekir.** |
| BRF-016 | `ceviri.kota_kalan_yuzde` | Sağlayıcı kota bilgisi hiç okunmuyor; DeepSeek/MyMemory kota uçları araştırılmalı — sağlayıcı vermiyorsa alan `—` kalır (K67) |
| BRF-013 (tam hâli) | `kilitli_liste_sayisi`, `kilitli_sapma_yuzde` | `lists.yuan_rate` var; sapma hesabı için "güncel onaylı kur" sürümü gerekiyor (yukarıdaki kur snapshot işiyle aynı) |

**Önerilen İE#22 dilimlemesi (brifing):** önce ✅ satırlarıyla panorama iskeleti
(8 şablon), sonra kur snapshot'ı (BRF-013/016 ailesi), yakalama telemetrisi ayrı
dilim; V3 statülü sekiz şablon **V3-C'ye bağlı** olarak işaretlensin.

---

## 2. Bildirim olayları → tetik noktaları

39 olay, 5 grup: kuyruk 9 · liste 12 · paylaşım 7 · çeviri 6 · sistem 5.
Önem dağılımı: bilgi 20 · uyarı 14 · kritik 5.

| Grup | Bugün tetik noktası VAR MI | Nereye bağlanır |
|---|---|---|
| **kuyruk** (9) | ✅ 7'si hazır | `JobQueue::sahiplen/basarisiz/oldur/dirilt` + `JobRunner::kos()`; `NTF-OFFLINE-QUEUED` eklenti tarafında `extension/core/kuyruk.ts`; `NTF-DUPLICATE-SUPPRESSED` `CaptureApplier` idempotency dalı |
| **liste** (12) | ⚠️ 5'i hazır | `StateMachine` + `ListController` (`NTF-LIST-CREATED/PRODUCTS-ADDED/REMOVED/STATUS-CHANGED/SENT`). `READY-BLOCKED`, `RATE-DRIFT`, `REVISION-CREATED`, `SUPPLIER-RESPONSE-RECEIVED`, `EXPIRED`, `ARCHIVED` → V3 statü/teklif/revision kavramları gerektirir |
| **paylaşım** (7) | ⚠️ 4'ü hazır | `ShareController::create()`, anahtar yenileme, `ShareLockPage` hatalı deneme + hız sınırı. `EXPIRY-NEAR`/`EXPIRED` **K62 ile çelişir** — erişim anahtarının süresi yoktur; bu iki olay ya kapsam dışıdır ya da K62 değişir → **PM kararı** |
| **çeviri** (6) | ⚠️ 3'ü hazır | `LlmTranslator` (başarı/başarısız), `Glossary` içe aktarma. Kota olayları §1'deki kota eksiğine bağlı; `QUALITY-BLOCKED` "Görev #4A kalite kapısı" henüz yok |
| **sistem** (5) | ⚠️ 3'ü hazır | `SettingsController` (`SETTINGS-CHANGED`), token doğrulama middleware (`TOKEN-INVALID`), kur onayı (`FX-UPDATED` → kur snapshot işine bağlı). `CAPTURE-HEALTH-LOW`/`NO-ACTIVITY` yakalama telemetrisine bağlı |

**Teknik gereksinimler (bildirim altyapısı):**

1. **Tablo:** `notifications` (id, kod, onem, grup, baslik, govde, eylem_linki,
   grup_anahtari, birlestirme_sayisi, okundu_at, created_at) + indeks
   (kullanıcı, okundu, created_at).
2. **Birleştirme sözleşmesi:** aynı `olay_kodu` + `grup_anahtari`,
   `pencere_dakika` içinde tek satırda sayılır (`{n}` gövdesi). Bu, yazma
   anında UPSERT mantığı ister — sonradan toplama değil.
3. **Yayıcı:** tek bir `BildirimYayinci` servisi; controller/servisler doğrudan
   tabloya yazmaz (kod sözlüğü tek yerde kalsın, K67 "alan yoksa —" korunsun).
4. **Okuma ucu:** `GET /api/notifications` (sayfalı, okunmamış sayısı) +
   `POST /api/notifications/read`. docs/10 sözleşmesine eklenmeli.
5. **Sessizlik kuralı:** `bilgi` önemli 20 olay her biri bildirim üretirse
   merkez kullanılmaz hâle gelir. Öneri: `bilgi` olayları varsayılan olarak
   yalnız panorama akışında görünsün, bildirim merkezine düşmesin — **PM kararı**.

---

## 3. Ayarlar: 16 sekmeye taşıma planı

Bugün panelde ayar ekranı **4 bileşen**: `BelgeAntedi.tsx`, `CeviriAyarlari.tsx`,
`KuyrukDurumu.tsx`, `PaylasimIletisimi.tsx` (+ `SettingsScreen` kabuğu).
IA belgesi 16 sekme ve 8 satırlık bir taşıma haritası veriyor.

| Mevcut | Hedef sekme | İş büyüklüğü |
|---|---|---|
| Kur değeri + getir/onayla | 7 — Kur & Para Birimleri | **orta**: sürümlü kur snapshot'ı yeni veri modeli ister (BRF-013/016 ile aynı iş) |
| Çeviri sağlayıcı/model/anahtar | 8 — Çeviri Sağlayıcısı | küçük: mevcut ekran taşınır, kota bloğu eklenir |
| Hedef diller | 9 — Diller & Sözlük | küçük (D4a düzeltmesi burada yaşar) |
| Sözlük CSV | 9 — Diller & Sözlük | **orta**: içe aktarma ucu HİÇ yazılmadı (PNL-50/51 devri) |
| Antet/logo/firma | 10 — Çıktılar & Antet | küçük: `BelgeAntedi` taşınır |
| Eklenti/panel tokenı | 14 — Güvenlik & API | orta: maskeli gösterim var; **rotasyon kaydı** yeni |
| WhatsApp numarası | 11 — Paylaşım & WhatsApp | küçük (B7'de yazıldı) |
| Kuyruk ekranı | 15 — Kuyruk & Zamanlayıcı | küçük: `KuyrukDurumu` taşınır |

**Boş sekmeler uyarısı:** 16 sekmenin 8'i bugün içeriksizdir (Panorama,
Yakalama & Eklenti, Gelen Kutusu & Kurallar, Keşif & Skor, Listeler & İş Akışı,
Firma Portalı, Bildirimler, Veri & Bakım). Boş sekme, kullanıcıya "eksik ürün"
hissi verir. Öneri: İE#22'de **yalnız içeriği olan sekmeler** görünsün; kalanlar
özellik geldikçe açılsın — **PM kararı**.

**Ortak meta katman (IA §4)** ayrı bir işdir ve hepsinden önce gelmelidir:
ayar arama (ad + açıklama + eş anlamlı), değişiklik izi, kaydedilmemiş değişiklik
uyarısı, başarısız kaydın eski değeri silmemesi. Bunlar sekme sayısından bağımsız
altyapıdır.

**Ekran durum metinleri (24 kayıt):** BOŞ/YÜKLENİYOR/HATA üçlüsü tek sözlükten
okunmalı (`config/durumlar.json` deseni). Bugün bu metinler bileşenlerin içinde
dağınık; taşıma sırasında tek kaynağa çekilmesi ucuzdur, sonradan pahalıdır.

---

## 4. PWA gereksinim listesi

| Bileşen | Gereken | Not |
|---|---|---|
| `manifest.webmanifest` | ad, kısa ad, `start_url=/panel/`, `scope=/panel/`, `display=standalone`, tema/arka plan rengi (marka kiti), ikon seti | `public/panel/site.webmanifest` **zaten var** — PWA alanlarıyla genişletilir |
| İkonlar | 192/512 (maskable dahil) | `android-chrome-192/512.png` mevcut; maskable varyant eklenmeli |
| Service worker | **kapsamı `/panel/` ile sınırlı** kayıt; kabuk + statik varlık önbelleği; API isteklerine dokunmaz (K61: belge hattı ağa çıkmaz, ama panel API'si her zaman canlı okunmalı) | |
| Çevrimdışı davranış | Yalnız "bağlantı yok" ekranı + son okunan liste görünümü. **Çevrimdışı YAZMA YOK** — kuyruk eklentide, panelde değil | |
| Güncelleme akışı | Yeni sürüm algılanınca "yenile" şeridi; sessiz güncelleme yok | |

**Eklenti SW'siyle karışma riski YOKTUR ve bunun sebebi yazılmalıdır:** eklentinin
service worker'ı `chrome-extension://` kaynağında çalışır, panelinki
`https://<alan>/panel/` kaynağında. Farklı origin, farklı kayıt defteri; tek
ortak nokta isimlendirmedir. Yine de kayıt kapsamı `/panel/` ile sınırlanmalı ki
paylaşım sayfası (`/s/...`) ve `/setup` SW'nin kapsamına GİRMESİN — paylaşım
sayfası dışa açık tek yüzeydir ve önbelleklenmiş bir sürümü asla sunulmamalıdır.

---

## 5. Faz 1'den devirler (İE#22'nin taşıması gerekenler)

| # | Devir | Kaynak | Öneri |
|---|---|---|---|
| 1 | `E2E-PNL-20` (kural rozeti + geri alma), `E2E-PNL-50/51` (sözlük CSV) | İE#21 PM kararı | Özellikle birlikte V3-B; defterde `bekliyor` kalır |
| 2 | `E2E-PNL-11/12/14/45` → Playwright altyapısı | İE#21 KT-EK-1..4 | Gerçek tarayıcı + MySQL turu; kabul turundaki manuel maddeler otomatikleşir |
| 3 | `tests/Http` 26 dakika | Ölçüldü (445 test, tek süreç) | CI'da grup ayrımı: `capture · setup · paylasim · liste` ayrı job; hedef < 8 dk |
| 4 | `LlmIstemci` `final` — mock'lanamıyor | D6 turunda görüldü | Arayüz arkasına al (`LlmIstemciArayuzu`); D6'nın uçtan uca testi ancak böyle yazılır |
| 5 | Global (TR arayüzlü) 1688 sayfa varyantı | D5 ikincil gözlemi | Veri kanalı araştırılmadı; V3-E fikstür turunda tespit, V3-K'de adaptör sürümleme |
| 6 | Kur snapshot sürümlemesi | Bu belgede üç ayrı yerden çıktı (BRF-013/016, Ayarlar §7, NTF-FX-UPDATED) | **Tek iş olarak planlanmalı** — üç özelliğin ortak önkoşulu |
| 7 | Yakalama telemetrisi | BRF-014/015 + NTF-CAPTURE-HEALTH-LOW/NO-ACTIVITY | Başarısız yakalama denemeleri bugün hiç kaydedilmiyor; yeni tablo |

---

## 6. Önerilen İE#22 iskeleti (PM derlerken)

| Blok | İçerik | Önkoşul |
|---|---|---|
| **A** | Bildirim altyapısı: tablo + `BildirimYayinci` + birleştirme + API ucu + merkez ekranı; **bugün tetiği olan 22 olay** | yok |
| **B** | Panorama iskeleti: 8 bağlanabilir brifing şablonu + boş gün varyantları + aksiyon kartları | yok |
| **C** | Kur snapshot sürümlemesi (BRF-013/016 + Ayarlar §7 + NTF-FX-UPDATED) | yok |
| **D** | Ayarlar meta katmanı (arama, iz, kaydedilmemiş uyarısı) + içeriği olan 8 sekmeye taşıma | yok |
| **E** | PWA (manifest + `/panel/` kapsamlı SW + güncelleme şeridi) | yok |
| **F** | Test altyapısı: Playwright kurulumu (KT-EK-1..4'ün otomatikleşmesi) + `tests/Http` CI grup ayrımı + `LlmIstemci` arayüzü | yok |
| **G** _(PM kararına bağlı)_ | Yakalama telemetrisi (BRF-014/015 + iki NTF) | yeni tablo |
| **— (kapsam dışı önerisi)** | V3 statülü 8 brifing şablonu, 7 liste/paylaşım olayı, sözlük CSV, firma portalı sekmesi | V3-C |

**PM'in karar vermesi gereken dört madde:**
1. V3 statü sözlüğü panoramaya girecek mi, yoksa V3-C'ye mi bağlanacak? (§0)
2. `NTF-SHARE-EXPIRY-NEAR/EXPIRED` K62 ile çelişiyor — olaylar mı düşer, K62 mi değişir? (§2)
3. `bilgi` önemli 20 olay bildirim merkezine düşsün mü, yoksa yalnız panoramada mı görünsün? (§2)
4. İçeriği olmayan 8 ayar sekmesi baştan görünsün mü, yoksa özellik geldikçe mi açılsın? (§3)
