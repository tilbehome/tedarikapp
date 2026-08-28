# TedarikApp V3-C Teklif Turu Durum Makinesi

**Belge türü:** İE#23 için bağlayıcı hazırlık hammaddesi  
**Kapsam:** Bir liste ile bir firma arasındaki hesapsız, link + 6 haneli anahtarla erişilen teklif turu  
**Durum sözlüğü:** Liste ve ürün durum adlarında tek kaynak `cikti-terimleri.json:status.*` dosyasıdır.

## 1. Modelin birimi

Durum makinesinin birimi yalnız `liste_id` değildir; **`liste_id + firma_id + tur_no`** üçlüsüdür.

```text
liste
 ├─ firma A → tur 1 → tur 2 → ...
 ├─ firma B → tur 1 → tur 2 → ...
 └─ firma C → tur 1 → tur 2 → ...
```

Her tur aşağıdaki değişmez kimlikleri taşır:

- `supplier_round_id`: Turun benzersiz kimliği.
- `liste_id`, `firma_id`, `tur_no`: İlişki ve sıra.
- `parent_round_id`: Revizyonun önceki turu; ilk turda `null`.
- `rfq_snapshot_id`: O turda firmaya gösterilen ürün, varyant, miktar ve dipnotların değişmez görüntüsü.
- `rate_snapshot_id`: Gönderim anındaki iç karşılaştırma kuru görüntüsü.
- `share_id`: Firma erişim kaydı; anahtarın kendisi iş kayıtlarında veya loglarda açık tutulmaz.
- `state`, `state_changed_at`, `state_changed_by_type`, `state_reason`.

Firma hesabı yoktur. Dış aktör, başarılı link + 6 haneli anahtar doğrulamasından sonra yalnız ilgili `supplier_round_id` üzerinde kısa ömürlü oturum alır.

## 2. Durumlar

Tur numarası durum adına gömülmez. Örneğin `tur_no=2, state=SENT` arayüzde “R2 gönderildi” olarak gösterilir; böylece R3, R4 ve devamı için yeni enum gerekmez.

| Kod | Arayüz anlamı | Düzenlenebilir yüz | Bağlayıcı `status.*` karşılığı |
|---|---|---|---|
| `DRAFT` | Taslak tur | Yalnız Ürün Sahibi RFQ'yu düzenler | `status.preparing` |
| `SENT` | Firmaya gönderildi | RFQ kilitli; firma yanıt taslağı henüz başlamamış | `status.sent` + bekleme bağlamında `status.waiting_supplier` |
| `VIEWED` | Firma görüntüledi | RFQ kilitli; firma yanıt alanları açılabilir | `status.waiting_supplier` |
| `PRICING` | Fiyatlanıyor / kısmi | Firma yalnız kendi yanıt taslağını düzenler | `status.waiting_price` |
| `RESPONDED` | Nihai yanıtlandı | Turun RFQ ve yanıtı salt okunur | `status.waiting_approval` |
| `REVISION_REQUESTED` | Revizyon istendi | Eski tur salt okunur; yeni taslak tur hazırlanır | `status.preparing` (yeni tur) |
| `APPROVED` | Teklif onaylandı | Tur salt okunur | `status.approved` |
| `ABANDONED` | Vazgeçildi | Tur salt okunur | `status.cancelled` |
| `EXPIRED` | Geçerlilik doldu | Tur salt okunur; revizyon açılabilir | `status.expired` |
| `REVOKED` | Erişim idari olarak iptal | Tur ve paylaşım salt okunur/erişilemez | `status.cancelled` |

`portal.status.found`, `portal.status.not_found` ve `portal.status.alternative` satır yanıtıdır; tur durumu değildir. Bunların alan karşılığı sırasıyla `status.found`, `status.not_found`, `status.alternative_available` anahtarlarıdır.

## 3. Ana geçiş sözleşmesi

| # | Önce → sonra | Tetikleyen taraf ve eylem | Tur kilidi etkisi | Kur kilidi etkisi | Bildirim bağı | Zaman damgası |
|---:|---|---|---|---|---|---|
| 1 | — → `DRAFT` | Ürün Sahibi bir firma için teklif turu oluşturur | Yeni RFQ düzenlenebilir; hiçbir dış erişim yok | Kur yalnız önizleme olabilir, henüz bağlayıcı snapshot yok | `NTF-LIST-CREATED` yalnız liste de yeni oluşturulmuşsa; tur için **YENİ OLAY: `NTF-QUOTE-ROUND-DRAFTED`** | `created_at`, `drafted_at` |
| 2 | `DRAFT` → `SENT` | Ürün Sahibi “Firmaya gönder” eylemini onaylar | RFQ snapshot alınır ve bu tur için sonsuza kadar kilitlenir | `rate_snapshot_id` oluşturulur; ham firma fiyatı değil yalnız iç kıyas dönüşümü bu snapshot'ı kullanır | `NTF-LIST-SENT`, `NTF-SHARE-CREATED`; liste zaten daha önce gönderildiyse yalnız **YENİ OLAY: `NTF-QUOTE-ROUND-SENT`** | `sent_at`, `rfq_locked_at`, `rate_locked_at`, `share_created_at` |
| 3 | `SENT` → `VIEWED` | Firma doğru anahtarla tur içeriğini ilk kez açar | RFQ kilidi değişmez | Kur snapshot değişmez | **YENİ OLAY: `NTF-QUOTE-ROUND-VIEWED`** | `first_viewed_at`; sonraki açılışlar yalnız `last_viewed_at` günceller |
| 4 | `SENT` veya `VIEWED` → `PRICING` | Firma ilk geçerli alan değişikliğini otomatik kaydeder | RFQ kilitli; firma yanıt taslağı düzenlenebilir | Kur snapshot değişmez; ekranda iç kur kıyası firmaya gösterilmez | **YENİ OLAY: `NTF-QUOTE-PRICING-STARTED`**; her otomatik kayıtta bildirim üretilmez | `pricing_started_at`, `last_draft_saved_at` |
| 5 | `PRICING` → `PRICING` | Firma tamamlanmış satırları kısmi gönderir | Gönderilen satır sürümleri o kısmi teslim için kilitlenir; tamamlanmayan satırlar taslak kalır | Kur snapshot değişmez | `NTF-SUPPLIER-RESPONSE-RECEIVED` birleştirilmiş kısmi yanıt olarak kullanılabilir; gürültü için firma+tur bazında katlanır | `last_partial_submitted_at`, `partial_submission_count` |
| 6 | `SENT`/`VIEWED`/`PRICING` → `RESPONDED` | Firma bütün zorunlu satırları tamamlar, geçerlilik ve DDP KDV onay kutularını işaretleyip nihai gönderir | Yanıt snapshot alınır; tüm tur salt okunur olur | Aynı kur snapshot korunur; nihai gönderim kuru yeniden yazmaz | `NTF-SUPPLIER-RESPONSE-RECEIVED`, `NTF-LIST-STATUS-CHANGED` | `responded_at`, `response_locked_at` |
| 7 | `RESPONDED` → `APPROVED` | Ürün Sahibi firma teklifini onaylar | Tur salt okunur; onay ayrı audit kaydıdır | Karar anı karşılaştırma kuru ayrıca kaydedilebilir fakat tur snapshot'ı değişmez | `NTF-LIST-STATUS-CHANGED` | `approved_at`, `decision_at` |
| 8 | `RESPONDED` → `REVISION_REQUESTED` | Ürün Sahibi gerekçe ve değişecek satırları seçerek revizyon ister | Yanıtlanan tur değişmez; yeni `tur_no+1` taslağı klonlanır | Yeni tur için eski kur snapshot'ı açıkça korunabilir veya yeni snapshot seçilebilir; seçim audit'e yazılır | `NTF-LIST-REVISION-CREATED`; firma turu için **YENİ OLAY: `NTF-QUOTE-REVISION-REQUESTED`** | Eski turda `revision_requested_at`; yeni turda `created_at` |
| 9 | `REVISION_REQUESTED` → yeni turun `DRAFT` | Sistem atomik olarak yeni turu açar | Eski RFQ/yanıt salt okunur; yeni tur önceki değerleri taslak başlangıç olarak taşıyabilir | `rate_policy=inherit|refresh` kararı zorunlu; seçilen snapshot yeni tura bağlanır | `NTF-LIST-REVISION-CREATED` | `next_round_created_at` |
| 10 | R2+ `DRAFT` → `SENT` | Ürün Sahibi yeni tur farklarını kontrol edip gönderir | Yeni RFQ snapshot kilitlenir; R1 değişmez | Yeni turun seçili kur snapshot'ı kilitlenir | **YENİ OLAY: `NTF-QUOTE-ROUND-SENT`** | `sent_at`, `rfq_locked_at`, `rate_locked_at` |
| 11 | R2+ `SENT` → `VIEWED` → `PRICING` → `RESPONDED` | İlk turdaki aynı firma eylemleri | Kilitler tur bazında bağımsızdır | Kur snapshot tur bazında bağımsızdır | İlk turla aynı olaylar, payload içinde `tur_no` | Aynı alanlar, yeni `supplier_round_id` üzerinde |
| 12 | `DRAFT` → `ABANDONED` | Ürün Sahibi göndermeden vazgeçer | Taslak kapanır, dış erişim hiç açılmaz | Bağlayıcı kur snapshot yoksa iptal edilir | **YENİ OLAY: `NTF-QUOTE-ROUND-ABANDONED`** | `abandoned_at`, `decision_at` |
| 13 | `SENT`/`VIEWED`/`PRICING`/`RESPONDED` → `ABANDONED` | Ürün Sahibi gerekçe girerek ticari değerlendirmeden vazgeçer | Mevcut tur ve yanıt korunur, yeni yazma kapatılır | Snapshot korunur | `NTF-LIST-STATUS-CHANGED` | `abandoned_at`, `decision_at` |
| 14 | `SENT`/`VIEWED`/`PRICING`/`RESPONDED` → `EXPIRED` | Sistem geçerlilik anını geçirir veya Ürün Sahibi teyit eder | Tur salt okunur; yalnız revizyon/yenileme eylemi sunulur | Snapshot korunur | `NTF-LIST-EXPIRED`, paylaşım da dolduysa `NTF-SHARE-EXPIRED` | `expired_at` |
| 15 | Aktif dış erişim → `REVOKED` | Ürün Sahibi paylaşımı iptal eder veya güvenlik olayı nedeniyle sistem kapatır | Tur verisi korunur; firma oturumu ve yeni yazma reddedilir | Snapshot korunur | `NTF-SHARE-REVOKED`; güvenlik olayına göre `NTF-SHARE-RATE-LIMITED` | `revoked_at`, `access_revoked_at` |

## 4. Geçiş korumaları

### Nihai gönderim kapısı

`RESPONDED` durumuna geçiş yalnız şu koşulların tamamında atomik yapılır:

1. Tur `SENT`, `VIEWED` veya `PRICING` durumundadır ve erişim iptal edilmemiştir.
2. Her RFQ satırında nihai bir firma yanıt durumu vardır.
3. `found` satırlarında fiyat, para birimi, DDP Türkiye KDV dahil onayı, MOQ, termin başlangıcı, süre ve birim geçerlidir.
4. `not_found` satırlarında kısa açıklama vardır.
5. `alternative_available` satırlarında alternatif bağlantı veya açıklama ve fiyat/MOQ/termin vardır.
6. Kademeler sıralı, pozitif ve çakışmasızdır.
7. Fiyat geçerlilik onayı ile DDP Türkiye KDV dahil onayı işaretlidir.
8. İstemci `round_version` değeri sunucu sürümüyle aynıdır; değilse çakışma ekranı açılır.

İki kez tıklama aynı `idempotency_key` ile tek yanıt snapshot'ı üretir.

### Kısmi gönderim

- Kısmi gönderim turu `RESPONDED` yapmaz; durum `PRICING` kalır.
- Yalnız doğrulamadan geçen tamamlanmış satır sürümleri gönderilir.
- Gönderilmiş kısmi satır, firma düzenlemeye devam ederse yeni sürüm alır; önceki teslim audit'te korunur.
- Ürün Sahibi “18/25 tamamlandı” gibi ilerlemeyi görür; tamamlanmamış satır içeriği başka firmaya veya dış kanala açılmaz.
- Her otomatik kayıt veya her satır için ayrı panel bildirimi üretilmez; olaylar firma+tur bazında katlanır.

### Revizyon

- Revizyon hiçbir zaman yanıtlanmış turun satırlarını yerinde değiştirmez.
- Yeni tur, önceki RFQ ve firma yanıtından seçilen başlangıç değerlerini kopyalayabilir; her kopya `copied_from_round_id` taşır.
- Ürün Sahibi değişen RFQ alanlarını gönderim öncesi fark görünümünde görür.
- Firma yeni turda önceki fiyatı, yeni fiyatı ve alıcının revizyon notunu yalnız kendi turu için görebilir.
- Eski 6 haneli anahtarın yeni turu açıp açmayacağı varsayılan olarak **hayırdır**; yeni tur için yeni anahtar ayrı kanaldan gönderilir. Ürün Sahibi açıkça “aynı güvenli paylaşımı sürdür” seçerse bile oturum ve tur yetkisi yeniden üretilir, anahtar loglanmaz.

## 5. Kur kilidi sözleşmesi

1. Firma, teklif ettiği ham fiyatı ve para birimini görür/yazar; iç karşılaştırma kuru firmaya gösterilmez.
2. `rate_snapshot_id` yalnız Ürün Sahibi tarafındaki karşılaştırma, rapor ve karar görüntüsünü yeniden üretmek içindir.
3. Kur güncellenince eski tur fiyatı veya çevrilmiş karar görüntüsü sessizce değişmez.
4. Revizyonda `inherit` seçilirse kör kıyas aynı kur tabanında yapılır; `refresh` seçilirse yeni kur snapshot'ı alınır ve turlar arası farkın kur etkisi ayrı gösterilir.
5. Kilitli kur sapması panelde `NTF-LIST-RATE-DRIFT` üretebilir; bu firma teklifini geçersiz kılmaz.
6. Kur kilidi ile teklif geçerlilik süresi ayrı kavramlardır: süre dolması `EXPIRED`, kur sapması ise uyarıdır.

## 6. Çoklu firma ve kör kıyas

Aynı liste üç firmaya gönderildiğinde tek RFQ içeriğinden üç bağımsız ilişki oluşturulur:

| Alan | Firma A | Firma B | Firma C |
|---|---|---|---|
| `supplier_round_id` | Ayrı | Ayrı | Ayrı |
| Link / 6 haneli anahtar | Ayrı ve farklı kanallar | Ayrı ve farklı kanallar | Ayrı ve farklı kanallar |
| Tur durumu ve zamanları | Bağımsız | Bağımsız | Bağımsız |
| Kısmi/nihai yanıt | Yalnız A'ya ait | Yalnız B'ye ait | Yalnız C'ye ait |
| Revizyon sayısı | Bağımsız | Bağımsız | Bağımsız |
| Firma ekranında diğer teklifler | **Asla gösterilmez** | **Asla gösterilmez** | **Asla gösterilmez** |

Kör kıyas kuralları:

- Firma, başka firmanın adını, durumunu, fiyatını, MOQ'sunu, terminini, sıralamasını veya “en iyi fiyat” işaretini asla görmez.
- URL, HTML, Excel ve API yanıtlarında başka `firma_id` veya `supplier_round_id` bulunmaz.
- Ürün Sahibi karşılaştırma ekranında bütün firmaları görebilir; dışa firma gönderilen hiçbir belgede bu karşılaştırma yer almaz.
- Bir firmadan alınan alternatif ürün diğer firmanın RFQ'suna ancak Ürün Sahibi yeni ve açık bir tur oluşturursa girer; kaynak firma ve fiyat bilgisi aktarılmaz.
- Cache anahtarı `supplier_round_id + authenticated_share_session` içerir; liste bazlı ortak dış cache kullanılmaz.

## 7. Yarış koşulları ve hata davranışı

- Aynı tur iki telefonda açıksa otomatik kayıt `round_version` ile iyimser kilit kullanır; son yazan sessizce kazanmaz.
- Anahtar yenilenince eski anahtar ve mevcut dış oturumlar derhal 401/403 alır; `NTF-SHARE-KEY-RENEWED` üretilir.
- Hız sınırı veya yanlış anahtar tur durumunu değiştirmez; yalnız erişim güvenliği olayıdır.
- Nihai gönderim yanıtı istemciye ulaşmadan bağlantı koparsa aynı idempotency anahtarıyla sorgulanır; ikinci snapshot üretilmez.
- Bildirim teslim edilemese de ticari durum geçişi geri alınmaz; outbox yeniden dener.

## 8. 13B bildirim kapsamı ve yeni olay önerileri

Mevcut bağlar: `NTF-LIST-SENT`, `NTF-SHARE-CREATED`, `NTF-SUPPLIER-RESPONSE-RECEIVED`, `NTF-LIST-REVISION-CREATED`, `NTF-LIST-STATUS-CHANGED`, `NTF-LIST-EXPIRED`, `NTF-SHARE-KEY-RENEWED`, `NTF-SHARE-REVOKED`, `NTF-SHARE-EXPIRED`, `NTF-SHARE-RATE-LIMITED`, `NTF-LIST-RATE-DRIFT`.

13B'de birebir karşılığı bulunmayan, İE#23 sırasında PM onayıyla eklenmesi önerilen olaylar:

| Yeni olay | Önem | Birleştirme | Gerekçe |
|---|---|---|---|
| `NTF-QUOTE-ROUND-DRAFTED` | bilgi | Firma+liste bazında 10 dk | Firma için tur açıldığını liste olayından ayırır. |
| `NTF-QUOTE-ROUND-SENT` | bilgi | Hayır | R2+ gönderimini liste ilk gönderiminden ayırır. |
| `NTF-QUOTE-ROUND-VIEWED` | bilgi | Aynı firma+tur tek kez | İlk gerçek görüntülemeyi kaydeder; her açılış bildirim olmaz. |
| `NTF-QUOTE-PRICING-STARTED` | bilgi | Aynı firma+tur tek kez | Firmanın yanıt vermeye başladığını belirtir. |
| `NTF-QUOTE-REVISION-REQUESTED` | bilgi | Hayır | Revizyon talebinin dış döngü olayını açıklar. |
| `NTF-QUOTE-ROUND-ABANDONED` | bilgi | Hayır | Gönderilmemiş veya bağımsız firma turundan vazgeçmeyi ayırır. |

Bu öneriler `bildirim-olay-katalogu.json` dosyasına bu görev kapsamında yazılmaz; yalnız İE#23 için açık eksik listesine alınır.
