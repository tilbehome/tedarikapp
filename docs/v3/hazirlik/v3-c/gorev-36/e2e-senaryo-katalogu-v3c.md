# V3-C E2E Senaryo Kataloğu

**Kimlik aralığı:** `E2E-C-001`–`E2E-C-090`  
**Toplam:** **90**  
**Amaç:** V3-C’nin v1.2.2 sertleştirmesiyle birlikte tek paket kapanış sınavını otomasyon ve manuel oracle’lara dönüştürmek.

> **KIRMIZI kuralı:** Dil karışması, yanlış ürüne fiyat yazılması, asıl satırın alternatifle ezilmesi veya firma yüzünde iç/başka firma verisi görülmesi tek olayda kabulü durdurur.

## Otomasyon sınıfları

| Kod | Uygulama |
|:---:|---|
| A | Playwright; tarayıcı, erişilebilirlik, klavye ve istemci davranışı |
| B | PHPUnit HTTP/entegrasyon; API, DB, snapshot, içe aktarma ve audit |
| C | Yalnız elle; telefon/PC görsel-dokunsal ve gerçek görüntüleme |

## Ortak düzen

- Dış servisler testte adapter/route düzeyinde sabitlenir; beklenmeyen dış istek testi kırmızı yapar.
- Tur birimi `liste_id + firma_id + tur_no`; her vaka kendi namespace/transaction’ında başlar.
- Anahtar açık loga, URL’ye, referrer’a, analitiğe veya hata paketine yazılmaz.
- “Gerçek ürün” Ürün Sahibinin kendi çalışma listesindeki kayıttır; katalogda demo veri varsayılmaz.
- Marka, model ve ölçü çevrilmez; kaynak değer açıkça “orijinal” işaretlenir.
- YA metrikleri bütün `YA-001..030` sunucu sonuçları üzerinde birlikte hesaplanır: alan doğruluğu **≥%90**, yanlış ürüne fiyat **%0**.

## Teklif turu — 23 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-001 | — → `DRAFT` | Temiz liste, firma ve tur alanı. | Ürün Sahibi firma için tur oluşturur. | `supplier_round_id`, `created_at`, `drafted_at` oluşur; dış erişim kapalı, RFQ düzenlenebilir. | teklif-turu-durum-makinesi geçiş 1 | B |
| E2E-C-002 | `DRAFT` → `SENT` | `DRAFT`; geçerli RFQ ve gönderim onayı. | “Firmaya gönder” onaylanır; kilitli görüntüler okunur. | `rfq_snapshot_id` ve `rate_snapshot_id` bir kez oluşur; `sent_at`, `rfq_locked_at`, `rate_locked_at`, `share_created_at` yazılır. | geçiş 2; `NTF-LIST-SENT`, `NTF-SHARE-CREATED` | B |
| E2E-C-003 | `SENT` → `VIEWED` | `SENT`; geçerli link ve 6 haneli anahtar. | Firma içeriği ilk kez açar, sonra tekrar açar. | İlk açış `first_viewed_at` ve `status.viewed`; tekrar yalnız `last_viewed_at` üretir. | geçiş 3; `NTF-QUOTE-ROUND-VIEWED`; 5B `status.viewed` | B |
| E2E-C-004 | `SENT/VIEWED` → `PRICING` | Firma oturumu açık; geçerli bir yanıt alanı. | İlk geçerli değişiklik yapılır ve otomatik kayıt tamamlanır. | `pricing_started_at` bir kez, `last_draft_saved_at` her başarılı kayıtta güncellenir; RFQ/kur kilidi aynı kalır. | geçiş 4; `portal.system.autosaving`, `portal.system.saved_at` | B |
| E2E-C-005 | `PRICING` → `PRICING` | En az bir tamamlanmış ve bir eksik satır. | Tamamlananlar kısmi gönderilir. | Tur `PRICING` kalır; gönderilen sürümler auditte kilitlenir; `partial_submission_count` artar. | geçiş 5; kısmi gönderim sözleşmesi | B |
| E2E-C-006 | `SENT/VIEWED/PRICING` → `RESPONDED` | Tüm satırlar geçerli; üç nihai onay işaretli. | Nihai gönderim iki kez aynı `idempotency_key` ile tetiklenir. | Tek yanıt snapshotı oluşur; `responded_at`/`response_locked_at` yazılır; tur salt okunur, kur snapshotı değişmez. | geçiş 6; nihai gönderim kapısı | B |
| E2E-C-007 | `RESPONDED` → `APPROVED` | Nihai yanıtlanmış tur. | Ürün Sahibi teklifi onaylar. | `approved_at` ve ayrı karar audit kaydı oluşur; firma snapshotı değişmez. | geçiş 7; `status.approved` | B |
| E2E-C-008 | `RESPONDED` → `REVISION_REQUESTED` | Yanıtlanmış R1; gerekçe ve satır seçimi. | Ürün Sahibi revizyon ister. | R1 salt okunur kalır; `revision_requested_at` yazılır; yeni tur işlemi başlar. | geçiş 8; `NTF-LIST-REVISION-CREATED` | B |
| E2E-C-009 | `REVISION_REQUESTED` → R2 `DRAFT` | Revizyon talebi; açık `rate_policy`. | Sistem yeni turu atomik açar. | R2 farklı `supplier_round_id`, `parent_round_id=R1`; `next_round_created_at` oluşur; R1 değişmez. | geçiş 9; `rate_policy=inherit\|refresh` | B |
| E2E-C-010 | R2+ `DRAFT` → `SENT` | Fark görünümü onaylanmış R2. | Yeni tur firmaya gönderilir. | R2 RFQ ve seçilmiş kur snapshotı kilitlenir; R1 bayt/sürüm olarak değişmez. | geçiş 10; `NTF-QUOTE-ROUND-SENT` | B |
| E2E-C-011 | R2+ dış akış | R2 `SENT`; yeni tur yetkisi. | R2 sırasıyla görüntülenir, fiyatlanır ve nihai gönderilir. | R2 `VIEWED → PRICING → RESPONDED`; tüm olay payloadlarında `tur_no=2`; R1 zamanları değişmez. | geçiş 11; tur bazlı bağımsız kilit | B |
| E2E-C-012 | `DRAFT` → `ABANDONED` | Gönderilmemiş taslak. | Ürün Sahibi vazgeçer. | `abandoned_at`/`decision_at` oluşur; dış erişim açılmaz; bağlayıcı kur görüntüsü yoksa iptal edilir. | geçiş 12; `status.cancelled` | B |
| E2E-C-013 | Aktif/yanıtlı → `ABANDONED` | `SENT`, `VIEWED`, `PRICING` veya `RESPONDED` tur. | Gerekçe ile ticari değerlendirmeden vazgeçilir. | Mevcut veri ve snapshot korunur; yeni yazma kapanır; `abandoned_at` oluşur. | geçiş 13 | B |
| E2E-C-014 | Aktif/yanıtlı → `EXPIRED` | Geçerlilik anı dolan tur. | Sistem süreyi geçirir veya Ürün Sahibi teyit eder. | Tur salt okunur; snapshot korunur; yalnız revizyon/yenileme eylemi sunulur; `expired_at` oluşur. | geçiş 14; `status.expired` | B |
| E2E-C-015 | Aktif dış erişim → `REVOKED` | Açık paylaşım ve firma oturumu. | Ürün Sahibi erişimi iptal eder. | Tur verisi korunur; oturum/yeni yazma 401/403 alır; `revoked_at` ve `access_revoked_at` oluşur. | geçiş 15; `NTF-SHARE-REVOKED` | B |
| E2E-C-016 | Geçiş koruması | `DRAFT` ve eksik zorunlu satırlar. | İstemci doğrudan `RESPONDED` endpointine ve atlanmış durum sürümüne istek yollar. | 409/422; durum `DRAFT` kalır, snapshot/bildirim oluşmaz. | durum makinesi geçiş tablosu; izinli geçiş dışında yazma yok | B |
| E2E-C-017 | Terminal durumlar | `APPROVED`, `ABANDONED`, `EXPIRED`, `REVOKED` örnekleri. | Her örnekte taslak alanı değiştir ve `PRICING`e geçmeyi dene. | Yazma reddedilir; yalnız şartnamede belirtilen revizyon/yenileme yüzeyi varsa görünür; tarihçe değişmez. | durum makinesi §§4,7 | B |
| E2E-C-018 | Çoklu firma | Aynı liste; Firma A/B için ayrı R1. | A turunda fiyat kaydet; B turunu ve cache/API yanıtını aç. | Durum, oturum, `supplier_round_id` ve yanıtlar bağımsızdır; B yüzünde A verisi yoktur. | durum makinesi §1 ve §6 | B |
| E2E-C-019 | Tur ve kur kilidi | `SENT` R1. | Kaynak ürün, varyant, miktar ve panel kuru değiştirilir; R1 tekrar okunur. | R1 `rfq_snapshot_id`/`rate_snapshot_id` ve yeniden üretilen kıyas değişmez; ham firma fiyatı kurla ezilmez. | durum makinesi geçiş 2 ve §5 | B |
| E2E-C-020 | Revizyon kuru: inherit | R1 yanıtlı; R2 açma. | `rate_policy=inherit` seçilip R2 oluşturulur. | R2 eski `rate_snapshot_id` ile kıyaslanır; seçim auditte; R1 aynı kalır. | durum makinesi §5.4 | B |
| E2E-C-021 | Revizyon kuru: refresh | R1 yanıtlı; güncel farklı kur mevcut. | `rate_policy=refresh` seçilip R2 oluşturulur. | R2 yeni snapshot alır; fiyat farkı ve kur etkisi ayrıdır; R1 yeniden hesaplanmaz. | durum makinesi §5.4 | B |
| E2E-C-022 | “Tur turu ezmez” | R1 cevapları, R2 taslağı ve çelişen yeni kaynak. | R2’ye yeni cevap uygulanır; R1 ve mesaj/belge kanıtı tekrar okunur. | Yeni kayıt eski turun alan/zaman/kaynağını güncellemez; `copied_from_round_id` yalnız başlangıç izidir. | #28 kabul sınırı 10; kırılma 12; durum makinesi revizyon koruması | B |
| E2E-C-023 | 700 adet kuralı | 500–999 aralıklı kademe ve ayrıca yalnız 500/1000/2000 nokta fiyatlı iki teklif. | Her iki teklifte 700 adet kıyası istenir. | Açık aralıkta 500 kademesi kullanılır; yalnız nokta fiyatlarında hesap/interpolasyon yok, yeniden fiyat istenir. | #28 kabul sınırı 7; doğrudan karar “700 adet” | B |

## Portal — 6 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-024 | Erişim kapısı | `SENT` tur ve ayrı kanaldaki doğru 6 haneli anahtar. | Link açılır; anahtar girilir. | K62 kapısı başarılı olur, kısa ömürlü oturum yalnız ilgili `supplier_round_id` için açılır; anahtar URL/logda yoktur. | portal şartnamesi erişim; durum makinesi §1 | B |
| E2E-C-025 | Sabit 404 | Var olmayan link, yanlış anahtar ve başka turun anahtarı. | Üç deneme ayrı ayrı yapılır. | Üçünde aynı sabit 404 yüzü/metni; tur/firma varlığı ve iç kimlik sızmaz; durum değişmez. | Görev #36 K62/sabit 404 kararı; portal §12 | B |
| E2E-C-026 | Hız sınırı | Aynı kaynak IP/oturum; yanlış anahtarlar. | Sınır aşılana kadar tekrarlı deneme yapılır. | Sabit hata korunur; yeni denemeler sınırlanır, `NTF-SHARE-RATE-LIMITED`; tur durumu değişmez. | durum makinesi §7; portal §12 | B |
| E2E-C-027 | Ekran 1 — Karşılama | Doğrulanmış firma oturumu. | Telefon ve PC’de özet/dil/gizlilik/kuralları aç. | Alıcı, liste, tur, son tarih ve ilerleme doğru; metinler seçili dilde; başka firma/iç maliyet yok. | portal şartnamesi §3; `portal.welcome.*`, `portal.instruction.*` | C |
| E2E-C-028 | Ekran 2 — Liste | 25 gerçek satır; tamam/eksik/hatalı örnekler. | Ara; `portal.filter.all`, `portal.filter.unanswered`, `portal.filter.invalid` filtrelerini ve ürün kartını kullan. | Sayaç/filtre/arama doğru; `portal.progress.*`; tek kart hatası listeyi kapatmaz. | portal şartnamesi §4; #30-EK bağlama haritası | A |
| E2E-C-029 | Ekran 3 — Bulundu | Yanıtlanmamış satır. | `found` seç; fiyat, ISO para birimi, KDV, MOQ, termin ve ambalaj girip kaydet. | RFQ alanları salt okunur; geçerli cevap kaydolur; ambalaj serbest metindir. | RFQ v2 firma yanıt alanları; PM eki | A |

## Portal doğrulama — 9 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-030 | Yanıt durumu | Boş `yanit_durumu`. | Satırı tamamlamayı dene. | `portal.validation.status_required` görünür; değer silinmez, odak alana gider. | portal metin tek kaynağı | A |
| E2E-C-031 | Fiyat + para birimi | `found`; fiyat ve para birimi ayrı ayrı boş. | Her eksikle kaydet/gönder dene. | Sırasıyla `portal.validation.found_price_required` ve `portal.validation.currency_required`; kesin fiyat yazılmaz. | RFQ v2; portal metin tek kaynağı | A |
| E2E-C-032 | MOQ + termin | `found`; MOQ, başlangıç/süre/birim eksikleri. | Alanları sırayla boş bırakıp tamamla. | `portal.validation.found_moq_required` ve `portal.validation.found_lead_time_required`; kısmi alan nihai sayılmaz. | RFQ v2 | A |
| E2E-C-033 | DDP KDV onayı | Fiyatlı satır; onay null/false. | Satırı/teklifi göndermeyi dene. | `portal.validation.ddp_vat_confirmation_required`; satır tamamlanmış sayılmaz. | RFQ v2 çapraz kontrol | A |
| E2E-C-034 | Pozitif sayı | Fiyat/MOQ/ölçü alanlarında 0 ve negatif değer. | Her alanı kaydetmeyi dene. | `portal.validation.positive_number`; ham giriş görünür kalır, sunucuya kesin değer yazılmaz. | RFQ v2 doğrulama | A |
| E2E-C-035 | Eksik kademe | Min miktarı dolu, fiyatı boş kademe. | Yeni kademe ekle, sonra satırı tamamla. | `portal.validation.tier_incomplete`; satır tamamlandı sayılmaz. | RFQ v2 kademeli fiyat alt şeması | A |
| E2E-C-036 | Kademe sırası | 1000, 100, 500 min miktarlı kademeler. | Kaydet ve önizle. | `portal.validation.tier_order`; sıralama önerilebilir fakat sessiz anlam değişmez. | RFQ v2; Excel spec §5 | A |
| E2E-C-037 | Kademe çakışması | 100–500 ve 400–999 aralıkları. | Kaydet/gönder dene. | `portal.validation.tier_overlap`; ilgili kademe bloklanır. | RFQ v2; #28 kabul sınırı 7 | A |
| E2E-C-038 | Koli ölçüleri | Yalnız L veya L+W girilmiş satır. | Kaydet/gönder dene. | `portal.validation.carton_dimensions_together`; üç boyut birlikte istenir. | RFQ v2 | A |

## Portal — 1 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-039 | Alternatif ayrı nesne | Asıl satır yanıtı `not_found`. | Bağlı alternatifte ad, https kaynak, fiyat kademesi, MOQ, termin üçlüsü, serbest metin ambalaj ve not gir. | Asıl `not_found` kalır; ayrı nesne `asil_rfq_satir_id` ile bağlıdır; rozet ilişkiden türetilir, `alternative_available` alan değildir. | RFQ v2 + PM eki; #28 kabul sınırı 8; `portal.status.alternative` | B |

## Portal doğrulama — 8 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-040 | Alternatif ayrıntısı | `not_found`; boş/eksik alternatif nesnesi. | Alternatifi bağlamayı dene. | `portal.validation.alternative_details_required`; ad/kaynak/fiyat kademesi/MOQ/termin tamamlanmadan rozet yoktur. | RFQ v2 + PM eki | A |
| E2E-C-041 | Bulunamadı notu | `not_found`; kısa not boş. | Satırı tamamla/gönder. | `portal.validation.not_found_note_required`; asıl fiyat/MOQ boş kalır. | RFQ v2 | A |
| E2E-C-042 | Kalan satırlar | En az bir `unanswered` satır. | Nihai gönderimi aç. | `portal.validation.remaining_rows` ve `portal.submit.blocked_incomplete`; nihai snapshot yoktur. | portal şartnamesi §§4,7 | A |
| E2E-C-043 | Talep MOQ altında | Talep 700, MOQ 1000. | Yanıtı kaydet ve önizle. | `portal.validation.quantity_below_moq` sarı/teyit uyarısıdır; gerçek MOQ korunur, hata/ceza değildir. | RFQ v2; #28 700 kararı | A |
| E2E-C-044 | Termin üst sınırı | `termin_suresi=366`. | Kaydet/gönder. | `portal.validation.lead_time_max`; alan hatası, sessiz düzeltme yok. | #30-EK anahtarı; RFQ v2 | A |
| E2E-C-045 | Brüt/net | Brüt 9 kg, net 10,2 kg. | Kaydet/gönder. | `portal.validation.gross_below_net`; değerler korunur, satır geçmez. | #30-EK anahtarı; RFQ v2 | A |
| E2E-C-046 | CBM farkı | L×W×H ile beyan CBM farkı %5’ten yüksek. | Kaydet ve önerilen CBM’yi incele. | `portal.validation.cbm_mismatch`; hesap beyanın üstüne yazılmaz, açık teyit gerekir. | #30-EK anahtarı; RFQ v2 | A |
| E2E-C-047 | URL güvenliği | `http`, `javascript:`, `data:` ve bozuk https örnekleri. | Kaynak/alternatif bağlantısını kaydet. | `portal.validation.url_invalid`; yalnız güvenli https kabul edilir, bağlantı çalıştırılmaz. | #30-EK anahtarı; portal §12 | A |

## Portal — 11 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-048 | Taslak ve otomatik kayıt | Düzenlenebilir satır; ağ açık. | Alanı değiştir; debounce süresini ve başarılı/başarısız kayıt durumunu izle. | 600–1000 ms sonra `portal.system.autosaving` → `portal.system.saved_at`; hatada `portal.system.save_failed`, yalnız ilgili alan geri alınabilir. | portal şartnamesi ortak kabuk ve §5 | B |
| E2E-C-049 | Çevrimdışı yerel taslak | Ağ kesik; cihazda değişiklik. | Değiştir, kapat/aç, sonra `portal.action.clear_local_draft` kullan. | `portal.system.offline_queued` ve `portal.system.local_draft_restored`; token/başka firma verisi saklanmaz; temizleme geri bildirimlidir. | portal şartnamesi §§1,4; #30-EK | A |
| E2E-C-050 | Çevrimdışı gönderim | Kuyrukta yerel değişiklik, ağ kesik. | Kısmi ve nihai gönderime bas. | İkisi de çalışmaz; `portal.system.offline_submit_blocked`; “gönderilmiş” yerel kaydı oluşmaz. | portal şartnamesi §§4,6,7 | A |
| E2E-C-051 | Çakışma — sunucu | Aynı tur iki cihazda, eski `round_version`. | İki cihazda değiştir; `portal.conflict.compare` ardından `portal.conflict.keep_server` seç. | Son yazan sessiz kazanmaz; sunucu sürümü korunur, cihaz değeri izlenebilir biçimde reddedilir. | durum makinesi §7; #30-EK çatışma anahtarları | B |
| E2E-C-052 | Çakışma — cihaz | Aynı çakışma düzeni. | `portal.conflict.keep_device` seç. | Cihaz değeri mevcut snapshotı ezmez; yeni taslak sürümü olarak alınır; audit/versiyon artışı vardır. | #30-EK çatışma anahtarları | B |
| E2E-C-053 | Ekran 4 — Kısmi gönderim | 18 geçerli, 7 eksik gerçek satır. | Özeti onayla; bir satırda sunucu çakışması da dene. | 18/25 tek idempotent snapshot; durum `PRICING`; çakışmada 17 sessiz uygulanmaz ve önizleme yeniden açılır. | portal şartnamesi §6; `portal.partial.*` | B |
| E2E-C-054 | Ekran 5 — Nihai gönderim | 25/25 geçerli, bekleyen yerel kayıt yok. | Üç onayı sırayla dene; hepsini işaretleyip çift tıkla. | Her eksik onayda kapı kapalı; tamamında tek `RESPONDED` snapshotı; `portal.submit.*` seçili dilde. | portal şartnamesi §7; durum geçiş 6 | B |
| E2E-C-055 | Ekran 6 — Başarı/salt okunur | `RESPONDED` tur. | Başarı referansını/tarihini kontrol et; sayfayı kapatıp yeniden aç; alanı değiştirmeyi dene. | `portal.success.*`, tur no/son tarih görünür; aynı snapshot salt okunur; boş form gösterilmez. | portal şartnamesi §8; `portal.readonly.valid_until` | A |
| E2E-C-056 | Ekran 7 — Revizyon | R1 yanıtlı; R2 açılmış. | R1/R2 arasında geç; farkları ve kopya rozetini kontrol et; eski anahtarla R2 açmayı dene. | `portal.revision.title`, `portal.revision.changed_rows`, `portal.revision.copied_from_previous`; R1 salt okunur; eski anahtar varsayılan olarak R2’yi açmaz. | portal şartnamesi §9; durum makinesi revizyon koruması | B |
| E2E-C-057 | Oturum ve iptal | Süresi dolmuş oturum; ayrıca iptal edilen tur. | Her bağlamda içerik/yazma isteği yap. | `portal.system.session_expired` veya `portal.system.round_revoked`; sabit güvenli yüz, yerel taslak temizliği, veri sızıntısı yok. | #30-EK sistem anahtarları; geçiş 15 | B |
| E2E-C-058 | Erişilebilirlik/kaçış | Hatalı alan, serbest metinde HTML; kaynak linki. | Gönder; hata özetinden alana git; linki aç. | Odak ilk hataya gider, girdi silinmez; HTML metin olarak kaçar; link `noopener,noreferrer`; dil seçici `aria-current`. | portal şartnamesi §12 | A |

## Dil bütünlüğü — 3 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-059 | TR: portal + PDF + Excel | Aynı gerçek tur; seçili dil TR; çevrilebilir alanların tr değeri dolu. | Portalı dolaş; PDF ve Excel çıktısını üret; görünür metin/alan değerlerini allowlist ile tara. | Sistem metinleri ve çevrilebilir alanlar yalnız TR; başka dil değeri tek hücrede/etikette görülürse KIRMIZI. Kaynak, marka, model ve ölçü yalnız açık “orijinal” işaretiyle istisnadır. | portal-metinleri.json; 5B+`status.viewed`; Excel spec §2 | A |
| E2E-C-060 | EN: portal + PDF + Excel | Aynı gerçek tur; seçili dil EN; çevrilebilir alanların en değeri dolu. | Portalı dolaş; PDF ve Excel çıktısını üret; görünür metin/alan değerlerini allowlist ile tara. | Sistem metinleri ve çevrilebilir alanlar yalnız EN; başka dil değeri tek hücrede/etikette görülürse KIRMIZI. Kaynak, marka, model ve ölçü yalnız açık “orijinal” işaretiyle istisnadır. | portal-metinleri.json; 5B+`status.viewed`; Excel spec §2 | A |
| E2E-C-061 | ZH: portal + PDF + Excel | Aynı gerçek tur; seçili dil ZH; çevrilebilir alanların zh değeri dolu. | Portalı dolaş; PDF ve Excel çıktısını üret; görünür metin/alan değerlerini allowlist ile tara. | Sistem metinleri ve çevrilebilir alanlar yalnız ZH; başka dil değeri tek hücrede/etikette görülürse KIRMIZI. Kaynak, marka, model ve ölçü yalnız açık “orijinal” işaretiyle istisnadır. | portal-metinleri.json; 5B+`status.viewed`; Excel spec §2 | A |

## Yapıştır–ayrıştır — 10 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-062 | YA-001, YA-002, YA-003 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-001, YA-002, YA-003 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-001/YA-002/YA-003; RFQ v2 | B |
| E2E-C-063 | YA-004, YA-005, YA-006 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-004, YA-005, YA-006 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-004/YA-005/YA-006; RFQ v2 | B |
| E2E-C-064 | YA-007, YA-008, YA-009 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-007, YA-008, YA-009 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-007/YA-008/YA-009; RFQ v2 | B |
| E2E-C-065 | YA-010, YA-011, YA-012 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-010, YA-011, YA-012 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-010/YA-011/YA-012; RFQ v2 | B |
| E2E-C-066 | YA-013, YA-014, YA-015 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-013, YA-014, YA-015 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-013/YA-014/YA-015; RFQ v2 | B |
| E2E-C-067 | YA-016, YA-017, YA-018 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-016, YA-017, YA-018 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-016/YA-017/YA-018; RFQ v2 | B |
| E2E-C-068 | YA-019, YA-020, YA-021 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-019, YA-020, YA-021 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-019/YA-020/YA-021; RFQ v2 | B |
| E2E-C-069 | YA-022, YA-023, YA-024 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-022, YA-023, YA-024 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-022/YA-023/YA-024; RFQ v2 | B |
| E2E-C-070 | YA-025, YA-026, YA-027 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-025, YA-026, YA-027 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-025/YA-026/YA-027; RFQ v2 | B |
| E2E-C-071 | YA-028, YA-029, YA-030 | Kanonik altın set ve izole sunucu ayrıştırıcısı. | YA-028, YA-029, YA-030 ham metinlerini sırayla POST et; eşleşme, null/belirsiz, kaynak izi ve kalıcılığı karşılaştır. | Her vaka altın setteki alan/null oracleına uyar; belirsiz ürün/para otomatik bağlanmaz. Eski `alternative_available` sınıflayıcı sonucu kalıcılıkta asıl `not_found` + bağlı ayrı alternatif nesnesine çevrilir. | yapistir-ayristir-altin-seti.json YA-028/YA-029/YA-030; RFQ v2 | B |

## Excel gel-git — 12 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-072 | FX-01 temiz | İlgili test seed’i ve 01-temiz.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Tam ve imzaları doğru çalışma kitabı içe alınır; önizlemede uygulanabilir satırlar oluşur, tur `PRICING` kalır. | excel-gelgit-spec §§6–10; fikstür envanteri FX-01 | B |
| E2E-C-073 | FX-02 kısmi | İlgili test seed’i ve 02-kismi.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Dolu satırlar önizlenir; boş satır “değişiklik yok”, `not_found` değildir. | excel-gelgit-spec §§6–10; fikstür envanteri FX-02 | B |
| E2E-C-074 | FX-03 bozuk imza | İlgili test seed’i ve 03-bozuk-satir-imzasi.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Bozuk imzalı satır uygulanamaz; diğer satır güvenli önizlenebilir. | excel-gelgit-spec §§6–10; fikstür envanteri FX-03 | B |
| E2E-C-075 | FX-04 formül enjeksiyonu | İlgili test seed’i ve 04-formul-enjeksiyonu.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Ham formül hücresi çalıştırılmadan güvenlik hatası; kaçışlı metin metin olarak korunur. | excel-gelgit-spec §§6–10; fikstür envanteri FX-04 | B |
| E2E-C-076 | FX-05 yanlış tur | İlgili test seed’i ve 05-yanlis-tur.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Çalışma kitabının tamamı reddedilir; aktif tura hiçbir yazma olmaz. | excel-gelgit-spec §§6–10; fikstür envanteri FX-05 | B |
| E2E-C-077 | FX-06 eksik zorunlu | İlgili test seed’i ve 06-eksik-zorunlu.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Eksik para/MOQ/termin alanlı satır hatalı; geçerli satırlar yalnız önizlenir. | excel-gelgit-spec §§6–10; fikstür envanteri FX-06 | B |
| E2E-C-078 | FX-07 para belirsiz | İlgili test seed’i ve 07-para-birimi-belirsiz.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Fiyat kesinleştirilmez; satır `BELİRSİZ`, kullanıcı kararı olmadan seçilemez. | excel-gelgit-spec §§6–10; fikstür envanteri FX-07 | B |
| E2E-C-079 | FX-08 kademe çakışması | İlgili test seed’i ve 08-kademe-cakismasi.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Yalnız çakışan kademeler bloklanır; ana fiyat geçerliyse ayrı önizlenebilir. | excel-gelgit-spec §§6–10; fikstür envanteri FX-08 | B |
| E2E-C-080 | FX-09 Çince başlık | İlgili test seed’i ve 09-cince-baslik.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Başlık varyantı güvenli biçimde eşlenir veya açık şema hatası verir; sıra/adla yanlış ürüne yazma olmaz. | excel-gelgit-spec §§6–10; fikstür envanteri FX-09 | B |
| E2E-C-081 | FX-10 BOM/kodlama | İlgili test seed’i ve 10-bom-kodlama.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Baştaki BOM normalize edilir; ZH/TR karakterler bozulmaz; kimlik/imza eşlemesi korunur. | excel-gelgit-spec §§6–10; fikstür envanteri FX-10 | B |
| E2E-C-082 | FX-11 yabancı+mükerrer | İlgili test seed’i ve 11-yabanci-mukerrer.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | `YABANCI` ve iki `MÜKERRER` satır bloklanır; “sonuncuyu al” yoktur. | excel-gelgit-spec §§6–10; fikstür envanteri FX-11 | B |
| E2E-C-083 | FX-12 kilitli tur | İlgili test seed’i ve 12-kilitli-tur.xlsx. | Dosyayı yükle; güvenlik/şema/tur/imza/alan önizlemesini ve uygulama seçimini çalıştır. | Gönderilmiş turda uygulama yok; “yeni revizyon turu aç” seçeneği, özgün dosya değişmeden sunulur. | excel-gelgit-spec §§6–10; fikstür envanteri FX-12 | B |

## Listeler merkezi — 1 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-084 | Liste/tekrar sipariş/şablon | Gerçek ürünlerden tamamlanmış bir liste ve kayıtlı bir şablon. | Listeler merkezinde listeyi aç; tekrar sipariş başlat; şablon uygula ve kapsamı karşılaştır. | Yeni çalışma kendi kimliğini alır; kaynak liste salt okunur/auditte kalır; yalnız seçilmiş ürün/alanlar taşınır, firma cevabı yeni turun cevabı sayılmaz. | Görev #36 Blok E kapsamı; #28 kabul sınırı 10 | A |

## Gönderim — 1 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-085 | Kayıt + F42 | Aynı listenin en az iki firma turu ve hazır çıktı. | Gönderim yap; kaydı aç; süreli indirme linkini süre içinde ve sonra kullan. | Kayıt kim/ne zaman/hangi turu gösterir; F42 linki süre içinde doğru dosyayı verir, süresi sonunda sabit güvenli hata; başka tur verisi yoktur. | Görev #36 gönderim kaydı ve F42 | B |

## K105 — 2 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-086 | Geri alınabilir sil | Her yeni V3-C ekranında silinebilen gerçek bir öğe. | Fare, klavye ve uygun bağlam menüsünden sil; geri al. | Kapsam görünür; işlem geri alınabilir; sayaç/odak/audit tutarlı; kalıcı kritik silmede açık onay vardır. | k105 mikro-etkileşim standardı; Görev #36 K105 matrisi | A |
| E2E-C-087 | Kopyala + ⋯ + Ctrl+K | Her yeni V3-C ekranı ve seçilebilir kayıt. | Kopyala eylemini, `⋯` menüsünü ve Ctrl+K komutunu klavyeyle/fareyle çalıştır. | Aynı eylem modeli üç girişten erişilir; eylem adı/kapsamı/sonucu aynıdır; odak ve bildirim kaybolmaz. | k105: mouse/keyboard/context/command palette | A |

## Sızıntı — 1 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-088 | Firma yüzü ve dış çıktılar | İç maliyet, hedef satış, kâr ve başka firma verisi panel seed’inde mevcut. | Portal HTML/API/cache, PDF ve Excel’i tara; firma görünümünü aç. | Bu alanların hiçbiri yoktur; başka `firma_id`/`supplier_round_id` yoktur; Paylaş düğmesi görünmez. Tek eşleşme KIRMIZI. | portal şartnamesi değişmez ilkeler; durum makinesi §6 | B |

## v1.2.2 — 2 senaryo

| Kimlik | Ekran/akış | Ön koşul | Adımlar | Beklenen sonuç | Oracle | Sınıf |
|---|---|---|---|---|---|:---:|
| E2E-C-089 | Yedek seti + APP_KEY | Kurulum/yükseltme alanı ve yedek seti. | Karttan parça indir; SHA/manifest kontrolü ve “Doğrula”yı çalıştır; KISMİ seti aç; APP_KEY emanetine eriş. | Parça/manifest SHA ile doğrulanır; eksik parçada KISMİ rozeti; APP_KEY gösteriminde şifre yeniden sorulur ve açık anahtar log/çıktıya düşmez. | Görev #36 v1.2.2 sertleştirme kapsamı | B |
| E2E-C-090 | Medya + sözlüksüz çeviri | Ana görseli henüz yerelleşmemiş ürün ve sözlüksüz çevrilmiş gerçek ürün. | Kuyruğu ilerlet; proxy geçici görünümü, yerel/uzak rozetini ve sözlüksüz ürün kartını telefon/PC’de incele. | Ana görsel kuyruk durumu doğru; geçici proxy yerel dosya gibi sunulmaz; rozet kaynağı doğru; sözlüksüz çeviri açık kartla işaretli, kaynak ad korunur. | Görev #36 v1.2.2 sertleştirme kapsamı | C |

## Doğrulama anahtarı kapsaması

| Anahtar | Senaryo |
|---|---|
| portal.validation.status_required | E2E-C-030 |
| portal.validation.found_price_required | E2E-C-031 |
| portal.validation.currency_required | E2E-C-031 |
| portal.validation.found_moq_required | E2E-C-032 |
| portal.validation.found_lead_time_required | E2E-C-032 |
| portal.validation.ddp_vat_confirmation_required | E2E-C-033 |
| portal.validation.positive_number | E2E-C-034 |
| portal.validation.tier_incomplete | E2E-C-035 |
| portal.validation.tier_order | E2E-C-036 |
| portal.validation.tier_overlap | E2E-C-037 |
| portal.validation.carton_dimensions_together | E2E-C-038 |
| portal.validation.alternative_details_required | E2E-C-040 |
| portal.validation.not_found_note_required | E2E-C-041 |
| portal.validation.remaining_rows | E2E-C-042 |
| portal.validation.quantity_below_moq | E2E-C-043 |
| portal.validation.lead_time_max | E2E-C-044 |
| portal.validation.gross_below_net | E2E-C-045 |
| portal.validation.cbm_mismatch | E2E-C-046 |
| portal.validation.url_invalid | E2E-C-047 |

## Portal 7 ekran kapsaması

| Şartname ekranı | Birincil senaryo | Tamamlayıcı senaryo |
|---|---|---|
| 1 — Karşılama ve tur özeti | E2E-C-027 | E2E-C-024, E2E-C-059..061 |
| 2 — Liste görünümü ve ilerleme | E2E-C-028 | E2E-C-048..052 |
| 3 — Satır yanıt formu | E2E-C-029 | E2E-C-030..047 |
| 4 — Kısmi gönderim | E2E-C-053 | E2E-C-005, E2E-C-050 |
| 5 — Nihai gönderim onayı | E2E-C-054 | E2E-C-006, E2E-C-042 |
| 6 — Başarı ve salt okunur teklif | E2E-C-055 | E2E-C-017 |
| 7 — Revizyon turu açıldı | E2E-C-056 | E2E-C-008..011, E2E-C-020..022 |

## K105 yeni ekran matrisi

Bu matris yeni ürün eylemi türetmez. “Sil + geri al” yalnız şartnamede silinebilen bir öğe bulunan yüzlerde zorunludur; salt okunur veya silinebilir öğe tanımlanmamış yüzlerde `Uygulanmaz` sonucu ayrıca doğrulanır. Kopyala, `⋯` menüsü ve Ctrl+K aynı mevcut eylem modeline açılır; kapsam ve sonuç giriş yöntemine göre değişemez.

| Yeni ekran/yüz | Kopyala | `⋯` menüsü | Ctrl+K | Sil + geri al | Senaryo |
|---|---|---|---|---|---|
| Teklif turu paneli | Tur/kimlikte | Var olan tur eylemleri | Tur eylemini bulur | Yalnız taslak turda | E2E-C-086, E2E-C-087 |
| Portal 1 — Karşılama | Görünür referansta | Var olan tur eylemleri | Aynı eylemi bulur | Uygulanmaz | E2E-C-027, E2E-C-087 |
| Portal 2 — Liste | Ürün kodu/kaynakta | Satır eylemleri | Satır/arama eylemini bulur | Yerel taslak varsa | E2E-C-028, E2E-C-049, E2E-C-086..087 |
| Portal 3 — Satır formu | Salt okunur kaynakta | Satır/kademe eylemleri | Aynı eylemi bulur | Kademe veya yerel taslakta | E2E-C-029..047, E2E-C-086..087 |
| Portal 4 — Kısmi gönderim | Gönderim özetinde | Hazır satır eylemleri | Aynı eylemi bulur | Uygulanmaz | E2E-C-053, E2E-C-087 |
| Portal 5 — Nihai onay | Gönderim özetinde | Var olan gönderim eylemleri | Aynı eylemi bulur | Uygulanmaz | E2E-C-054, E2E-C-087 |
| Portal 6 — Başarı/salt okunur | Referans ve tarihte | Var olan salt-okunur eylemler | Aynı eylemi bulur | Uygulanmaz | E2E-C-055, E2E-C-087 |
| Portal 7 — Revizyon | Fark ve referansta | Değişen satır eylemleri | Aynı eylemi bulur | Yalnız yeni taslakta | E2E-C-056, E2E-C-086..087 |
| Yapıştır–ayrıştır önizleme | Kaynak parça/sonuçta | Satır kararları | Aynı kararı bulur | Önizleme seçimini geri alır | E2E-C-062..071, E2E-C-086..087 |
| Excel içe aktarma/sonuç | Hücre adresi/hatada | Satır kararları | Aynı kararı bulur | Uygulama öncesi seçimde | E2E-C-072..083, E2E-C-086..087 |
| Listeler merkezi | Liste/ürün referansında | Liste/ürün eylemleri | Aynı eylemi bulur | Liste/ürün kaldırmada | E2E-C-084, E2E-C-086..087 |
| Tekrar sipariş | Kaynak liste referansında | Var olan tekrar eylemleri | Aynı eylemi bulur | Yeni taslak öğesinde | E2E-C-084, E2E-C-086..087 |
| Şablonlar | Şablon/alan değerinde | Şablon eylemleri | Aynı eylemi bulur | Şablon silmede | E2E-C-084, E2E-C-086..087 |
| Gönderim kaydı + F42 | Kayıt/linkte | Kayıt eylemleri | Aynı eylemi bulur | Uygulanmaz | E2E-C-085, E2E-C-087 |
| Yedek seti kartı | SHA/manifestte | Set eylemleri | Aynı eylemi bulur | Uygulanmaz | E2E-C-089, E2E-C-087 |
| Medya kuyruğu | Medya referansında | Kuyruk eylemleri | Aynı eylemi bulur | Kuyruk öğesinde geri alma | E2E-C-090, E2E-C-086..087 |
| Sözlüksüz çevrilmiş ürün kartı | Kaynak/çeviri metninde | Kart eylemleri | Aynı eylemi bulur | Uygulanmaz | E2E-C-090, E2E-C-087 |

## Altın set kapsaması

| Altın set vakası | Senaryo |
|---|---|
| YA-001 | E2E-C-062 |
| YA-002 | E2E-C-062 |
| YA-003 | E2E-C-062 |
| YA-004 | E2E-C-063 |
| YA-005 | E2E-C-063 |
| YA-006 | E2E-C-063 |
| YA-007 | E2E-C-064 |
| YA-008 | E2E-C-064 |
| YA-009 | E2E-C-064 |
| YA-010 | E2E-C-065 |
| YA-011 | E2E-C-065 |
| YA-012 | E2E-C-065 |
| YA-013 | E2E-C-066 |
| YA-014 | E2E-C-066 |
| YA-015 | E2E-C-066 |
| YA-016 | E2E-C-067 |
| YA-017 | E2E-C-067 |
| YA-018 | E2E-C-067 |
| YA-019 | E2E-C-068 |
| YA-020 | E2E-C-068 |
| YA-021 | E2E-C-068 |
| YA-022 | E2E-C-069 |
| YA-023 | E2E-C-069 |
| YA-024 | E2E-C-069 |
| YA-025 | E2E-C-070 |
| YA-026 | E2E-C-070 |
| YA-027 | E2E-C-070 |
| YA-028 | E2E-C-071 |
| YA-029 | E2E-C-071 |
| YA-030 | E2E-C-071 |
