# TedarikApp Rol Bazlı Dış Liste ve Teklif Portalı

## Claude Code uygulama raporu

**Belge amacı:** TedarikApp içindeki aynı ürün/listenin; müşteri, ithalatçı firma ve Çinli üretici/tedarikçi için farklı veri görünürlüğü, farklı işlem yetkisi ve farklı ticari amaçla sunulmasını tarif eder.

**Bu raporun yanında verilen çalışan örnek:** `tedarikapp-rol-portallari-demo.html`

---

## 1. Karar özeti

Mevcut tek liste yaklaşımı üç ayrı dış kullanıcı türü için doğru değildir. Dış kullanıcıların aynı sütunları görmesi hem gereksiz bilgi kalabalığı oluşturur hem de ticari sırların istemeden paylaşılmasına yol açar.

Uygulanacak temel karar:

> Tek ürün ve liste veri çekirdeği korunacak; fakat müşteri, ithalatçı ve Çinli firma için üç ayrı portal görünümü ve üç ayrı yanıt modeli üretilecektir.

Bu üç yüz birbirinin yalnızca CSS ile değiştirilmiş kopyası değildir. Her biri farklı iş tamamlar:

1. **Müşteri portalı:** Ürün ve satış teklifini inceler, not bırakır, ilgi/onay bildirir.
2. **İthalatçı portalı:** İthalat için gerekli ürün verisini görür, eksik bilgiyi bildirir, DDP ve KDV dâhil fiyatlandırma yapar.
3. **Çinli firma RFQ portalı:** Ürünü bulur veya alternatif sunar; EXW/FOB, MOQ, kademeli fiyat, termin, paket ve belge bilgisi girer.

TedarikApp iç paneli bunlardan ayrıdır. İç panel; kaynak fiyatı, Türkiye pazar fiyatı, skorlar, kârlılık ve firmalar arası karşılaştırma gibi bütün özel verileri göstermeye devam eder.

---

## 2. Tasarım ilkesi: uygulama paneli değil, etkileşimli ticari belge

Dışarıya açılan sayfa klasik yönetim paneli veya dashboard gibi görünmemelidir. Sol menü, karmaşık filtreler, görünüm ayarları, sütun yöneticisi ve iç operasyon KPI'ları dış kullanıcıya gösterilmemelidir.

Dış portal şu dengeyi kurmalıdır:

- Görünüş olarak kurumsal RFQ/teklif belgesi
- Çalışma biçimi olarak kayıt yapan web formu
- PDF çıktısında resmî belge düzeni
- Mobilde satır yerine açılır ürün kartı
- Masaüstünde okunabilir liste + satıra bağlı yanıt alanı

Ortak üst alan:

- TedarikApp/Ürün Sahibi logosu
- Belge türü ve belge numarası
- Muhatap firma
- Teklif/yanıt son tarihi
- Güncelleme tarihi
- Belge durumu
- Dil seçimi
- PDF/Excel indirme (yetkiye göre)
- Kaydetme durumu

Gerçek paylaşılan sayfada “Müşteri / İthalatçı / Çinli Firma” rol seçici bulunmaz. Rol, bağlantı oluşturulurken sunucuda belirlenir. Örnek HTML'deki rol seçici yalnızca üç tasarımı tek dosyada göstermek içindir.

---

## 3. Tek kaynak, üç görünüm

Ürün bilgileri üç ayrı tabloda kopyalanmamalıdır. Ortak ürün anlık görüntüsü oluşturulmalı, rol bazlı yanıtlar ayrı kaydedilmelidir.

Önerilen ana varlıklar:

### 3.1 `lists`

- `id`
- `owner_id`
- `title`
- `document_no`
- `status`
- `default_locale`
- `currency_policy`
- `created_at`
- `updated_at`

### 3.2 `list_items`

- `id`
- `list_id`
- `product_snapshot_id`
- `requested_quantity`
- `requested_variants_json`
- `buyer_note`
- `sort_order`
- `status`

### 3.3 `product_snapshots`

Kaynak ilan sonradan değişse bile gönderilen belgenin içeriği değişmemelidir.

- Başlık ve orijinal başlık
- Görseller
- Kaynak bağlantısı
- Kaynak platformu
- Malzeme, ölçü ve menşe
- Varyasyonlar
- Paket bilgileri
- Kaynaktan yakalanan fiyat/rozet/satış sinyalleri
- Yakalama tarihi
- Veri sürümü

Kaynak fiyat ve pazar sinyalleri snapshot içinde tutulabilir; fakat dış görünürlük politikası bunların gösterilmesini engeller.

### 3.4 `list_shares`

- `id`
- `list_id`
- `recipient_type`: `customer | importer | supplier`
- `recipient_name`
- `recipient_company_id` (varsa)
- `locale`: `tr | en | zh`
- `token_hash`
- `access_code_hash`
- `expires_at`
- `max_attempts`
- `revoked_at`
- `permissions_json`
- `last_opened_at`

### 3.5 `customer_offer_responses`

- `share_id`
- `list_item_id`
- `interest_status`: `interested | undecided | not_interested`
- `requested_quantity`
- `customer_note`
- `approved_at`

### 3.6 `importer_quote_responses`

- `share_id`
- `list_item_id`
- `status`: `priced | missing_info | alternative | cannot_quote`
- `ddp_unit_price`
- `ddp_total_price`
- `currency`
- `vat_included`
- `exchange_rate`
- `exchange_rate_date`
- `delivery_days`
- `quote_valid_until`
- `included_costs_json`
- `excluded_costs`
- `gtip_suggestion`
- `compliance_note`
- `attachments_json`
- `company_note`

### 3.7 `supplier_rfq_responses`

- `share_id`
- `list_item_id`
- `match_status`: `found | not_found | alternative`
- `exw_unit_price`
- `fob_unit_price`
- `currency`
- `moq`
- `tier_prices_json`
- `production_days`
- `sample_price`
- `units_per_carton`
- `carton_dimensions`
- `gross_weight`
- `net_weight`
- `total_cbm`
- `logo_customization`
- `packaging_customization`
- `certificates_json`
- `alternative_product_url`
- `supplier_note`

### 3.8 `quote_rounds` ve `audit_events`

Her teklif turu sürümlenmelidir. Firma önceki cevabı değiştirdiğinde sessizce üzerine yazılmamalıdır.

- Teklif turu numarası
- Kur kilidi
- Gönderim tarihi
- Gönderen firma ve kullanıcı/oturum
- Değişen alanlar
- IP/cihaz özeti (KVKK ve güvenlik politikasına uygun)
- Taslak, gönderildi ve salt okunur durumları

---

## 4. Rol bazlı görünürlük matrisi

| Alan | İç panel | Müşteri | İthalatçı | Çinli firma |
|---|---:|---:|---:|---:|
| Ürün görseli ve adı | Evet | Evet | Evet | Evet |
| İstenen varyasyon ve miktar | Evet | Evet | Evet | Evet |
| Teknik özellikler | Evet | Gerektiği kadar | Evet | Evet |
| Kaynak ürün bağlantısı | Evet | Hayır | Evet | Evet |
| Kaynak platform adı | Evet | Hayır | İsteğe bağlı | İsteğe bağlı |
| 1688/platform görünen fiyatı | Evet | Hayır | Hayır | Hayır |
| Türkiye pazar/vitrin fiyatı | Evet | Teklif satış fiyatı olarak farklı alan | Hayır | Hayır |
| İç hedef maliyet | Evet | Hayır | Hayır | Hayır |
| Kârlılık ve marj | Evet | Hayır | Hayır | Hayır |
| Skorlar ve karar kartı | Evet | Hayır | Hayır | Hayır |
| Başka firmaların teklifleri | Yetkiye bağlı | Hayır | Hayır | Hayır |
| EXW/FOB yanıt alanları | Karşılaştırmada | Hayır | Görüntüleme politikaya bağlı | Düzenler |
| DDP + KDV yanıt alanları | Karşılaştırmada | Hayır | Düzenler | Normalde hayır |
| Müşteriye satış fiyatı | Evet | Evet | Hayır | Hayır |
| Firma notu | Evet | Kendi notu | Kendi notu | Kendi notu |

**Güvenlik kuralı:** Görünürlük “izin verilen alanlar listesi” ile kurulmalıdır. Yasaklı alanları sonradan silmeye çalışan blacklist yaklaşımı kullanılmamalıdır.

---

## 5. Müşteri portalı

### 5.1 Amaç

Müşteri tedarik sürecini değil, kendisine sunulan ürünü ve ticari teklifi değerlendirmelidir.

### 5.2 Gösterilecek alanlar

- Ürün görseli
- Ürün adı ve işletmenin stok kodu
- Ticari ürün açıklaması
- Sunulan renk/varyasyonlar
- Satış birim fiyatı
- Satır toplamı
- KDV durumu
- Müşteri için minimum sipariş/paket miktarı
- Tahmini teslim süresi
- Teklif geçerlilik tarihi
- Garanti/belge bilgisi (ürüne göre)
- Ürün durumu

### 5.3 Müşterinin yapabilecekleri

- İlgileniyorum / kararsızım / istemiyorum seçimi
- Talep miktarı bildirme
- Ürün bazlı not yazma
- Tüm listeyi onaylama
- Sorusu olduğunu bildirme
- PDF teklif indirme

### 5.4 Kesinlikle gösterilmeyecekler

- Kaynak site ve kaynak bağlantısı
- 1688/Alibaba fiyatı
- Üretici/tedarikçi adı
- EXW/FOB/DDP maliyet dökümü
- Kârlılık, komisyon, landed cost
- İç skor ve pazar analizleri
- Başka müşteri ve firma bilgileri

---

## 6. İthalatçı firma portalı

### 6.1 Amaç

İthalatçı firma, verilen ürün/miktar/teslim noktası üzerinden Türkiye teslim DDP fiyatı oluşturur. Ürün sahibinin kaynak fiyatını veya pazar kârlılığını görmesine gerek yoktur.

### 6.2 Gösterilecek alanlar

- Ürün görseli ve adı
- Kaynak ürün bağlantısı
- İstenen varyasyon ve miktar
- Menşe
- Malzeme ve ürünün teknik özeti
- Koli içi, koli ölçüsü, brüt/net ağırlık, CBM
- Mevcut veya eksik GTİP
- Mevcut sertifika/test belgeleri
- Türkiye teslimat noktası
- İstenen teslim şekli: DDP ve KDV dâhil

### 6.3 İthalatçının düzenleyeceği alanlar

- Durum: fiyatlandırıldı / bilgi eksik / alternatif / fiyatlandırılamıyor
- DDP birim fiyat
- DDP toplam fiyat
- Para birimi
- KDV dâhil/hariç (varsayılan ve zorunlu politika: dâhil)
- Kullanılan döviz kuru ve tarih
- Teslim süresi
- Teklif geçerlilik tarihi
- Kapsanan giderler
- Hariç tutulan giderler
- GTİP önerisi
- Mevzuat/uygunluk notu
- Belge veya hesap dökümü yükleme
- Firma açıklaması

### 6.4 DDP doğrulama kuralları

- `ddp_total_price = ddp_unit_price × requested_quantity`
- Yuvarlama politikası para birimine göre merkezi tanımlanmalı.
- KDV durumu boş bırakılamaz.
- Teslim noktası teklif turu boyunca kilitlenmeli.
- Kur alanı doldurulduysa kur tarihi zorunlu olmalı.
- “DDP ve KDV dâhil” şartı karşılanmıyorsa teklif gönderilememeli veya açık uyarıyla istisna kaydı oluşturulmalı.
- Hariç tutulan gider varsa özet ekranında kırmızı/amber uyarı gösterilmeli.

---

## 7. Çinli üretici/tedarikçi RFQ portalı

### 7.1 Amaç

Çinli firma, referans ürünü bulur; aynı ürün veya alternatif için üretim ve tedarik cevabı verir. Türkiye satış fiyatı veya şirketin hedef maliyeti paylaşılmaz.

### 7.2 Arayüz dili

- Varsayılan: Basitleştirilmiş Çince (`zh-CN`)
- Bir görünümde yalnız seçilen dil bulunmalı.
- Sistem etiketleri alt alta TR/EN/ZH yazılmamalı.
- Para, sayı ve tarih biçimi seçilen locale göre üretilmeli.
- Ürün adı gerekirse Çince çeviri + açılabilir “orijinal başlık” olarak gösterilebilir.

### 7.3 Gösterilecek alanlar

- Referans görsel/video
- Referans ürün bağlantısı
- Teknik özellikler
- İstenen renk/varyasyon/adet
- Kalite ve malzeme beklentisi
- Logo/ambalaj talebi
- Teslim hedefi
- Ürün sahibi notu

### 7.4 Firmanın düzenleyeceği alanlar

- Bulundu / bulunamadı / alternatif var
- EXW fiyat
- FOB fiyat
- Para birimi
- MOQ
- 100/240/500 gibi kademeli fiyat satırları
- Üretim süresi
- Numune fiyatı
- Koli içi adet
- Koli ölçüsü
- Brüt ve net ağırlık
- Toplam CBM
- Logo ve ambalaj özelleştirme
- Sertifika/test belgesi yükleme
- Alternatif ürün bağlantısı ve görseli
- Firma açıklaması

### 7.5 Gösterilmeyecekler

- Türkiye satış/vitrin fiyatı
- Trendyol/Hepsiburada satış fiyatları
- Hedef maliyet
- Marj ve kârlılık
- DDP maliyet hesabı
- Diğer Çinli firmaların yanıtları
- İç karar notları ve skorlar

---

## 8. Erişim ve yetkilendirme

Firma hesabı zorunlu değildir. Önerilen akış:

1. Ürün sahibi listede “Dış paylaşım oluştur” seçer.
2. Muhatap türünü seçer: müşteri / ithalatçı / Çinli firma.
3. Dil, son tarih ve düzenleme izinlerini belirler.
4. Sistem tahmin edilemez süreli bağlantı ve ayrı 6 haneli kod üretir.
5. Muhatap bağlantıyı açar, kodu girer.
6. Sunucu token üzerinden rolü belirler.
7. Kullanıcıya yalnız rolünün serializer/view-model çıktısı gönderilir.

### Güvenlik gereksinimleri

- Rol query parametresinden (`?role=importer`) alınmamalı.
- Tarayıcıya gizli alan gönderilip CSS ile saklanmamalı.
- Token ve 6 haneli kod veritabanında hash olarak tutulmalı.
- Kod denemeleri sınırlanmalı ve gecikmeli kilit uygulanmalı.
- Paylaşım iptal edilebilmeli ve süreli olmalı.
- Liste dışındaki ID'lere erişim engellenmeli.
- Firma yalnız kendi yanıtlarını görmeli.
- Gönderilmiş teklif varsayılan olarak salt okunur olmalı; yeniden açma iç panelden yapılmalı.
- Dosya yüklemelerinde MIME, boyut, zararlı içerik ve sahiplik doğrulaması yapılmalı.
- Her kritik işlem audit kaydına yazılmalı.

---

## 9. Durum makineleri

### 9.1 Paylaşım belgesi

`draft → shared → opened → partially_completed → submitted → locked`

Ek durumlar:

- `expired`
- `revoked`
- `revision_requested`
- `reopened`

### 9.2 Çinli firma ürün yanıtı

`unanswered → found | not_found | alternative`

- `found`: fiyat, MOQ ve termin zorunlu
- `not_found`: açıklama önerilir, fiyat alanları kapatılır
- `alternative`: alternatif bağlantısı veya görseli zorunlu

### 9.3 İthalatçı ürün yanıtı

`unanswered → priced | missing_info | alternative | cannot_quote`

- `priced`: DDP birim fiyat, para birimi, KDV, geçerlilik ve termin zorunlu
- `missing_info`: eksik alan seçimi/açıklaması zorunlu
- `alternative`: açıklama ve fiyatlandırma varsayımları zorunlu
- `cannot_quote`: gerekçe zorunlu

### 9.4 Müşteri ürün yanıtı

`unreviewed → interested | undecided | not_interested`

Liste onayı, bütün satırların “interested” olmasını gerektirmek zorunda değildir. Müşteri seçtiği ürünleri onaylayabilir; onay özeti hangi ürünlerin kapsamda olduğunu açıkça göstermelidir.

---

## 10. API ve uygulama katmanı önerisi

Örnek uçlar:

```text
POST   /api/lists/{listId}/shares
POST   /api/share/access
GET    /api/share/{token}/document
PATCH  /api/share/{token}/items/{itemId}/draft
POST   /api/share/{token}/items/{itemId}/attachments
POST   /api/share/{token}/submit
GET    /api/share/{token}/export/pdf
GET    /api/share/{token}/export/xlsx
POST   /api/lists/{listId}/shares/{shareId}/reopen
POST   /api/lists/{listId}/shares/{shareId}/revoke
```

`GET /document` rol bazlı view-model döndürmelidir:

```json
{
  "document": {
    "number": "DDP-2026-013",
    "recipient_type": "importer",
    "locale": "tr",
    "status": "partially_completed",
    "deadline": "2026-09-04T18:00:00+03:00"
  },
  "permissions": {
    "can_edit": true,
    "can_upload": true,
    "can_submit": true,
    "can_view_source_link": true
  },
  "items": []
}
```

API hiçbir zaman rolün görmemesi gereken iç alanları response içine koymamalıdır.

---

## 11. UI bileşenleri

Ortak bileşenler:

- `ExternalDocumentShell`
- `DocumentIdentityHeader`
- `RecipientAccessBanner`
- `LocaleSwitcher`
- `DeadlineStatus`
- `ProgressSummary`
- `ProductRequestList`
- `ProductRequestRow`
- `ProductDetailDrawer`
- `AutosaveIndicator`
- `AttachmentUploader`
- `SubmitConfirmation`
- `ReadOnlySubmissionSummary`

Rol bileşenleri:

- `CustomerOfferView`
- `CustomerItemResponse`
- `ImporterDdpQuoteView`
- `ImporterQuoteForm`
- `SupplierRfqView`
- `SupplierRfqResponseForm`

Rol bileşenleri ortak tabloya rastgele sütun eklemek yerine kendi görev düzenini oluşturmalıdır. İthalatçı ve Çinli firma için masaüstünde önerilen düzen: solda ürün talebi, sağda seçili ürünün yanıt formu. Müşteri için önerilen düzen: belge tipi ürün listesi ve satıra bağlı açılır detay.

---

## 12. Görsel sistem

- Zemin: çok açık soğuk gri
- Belge yüzeyi: beyaz
- Ana marka: koyu lacivert
- Etkileşim: mavi
- Vurgu: sınırlı turuncu
- Başarı: yumuşak yeşil
- Bekleme/eksik: yumuşak amber
- Hata: yumuşak kırmızı
- Dış panel radius: 12–16 px
- Tablo içindeki alanlarda gereksiz kart/radius kullanılmamalı
- Sütun ayraçları ince fakat görünür olmalı
- Alan yoğunluğu 8 px ritim sistemiyle kurulmalı
- Gölge yalnız dış belge yüzeyinde kullanılmalı
- Butonlar eylem hiyerarşisine göre birincil/ikincil olmalı

### Kaçınılacak tasarımlar

- Üç dilin aynı anda alt alta yazılması
- Devasa kurumsal banner
- Her alanın renkli rozet olması
- Uygulama paneline benzeyen sol menü
- Dış kullanıcıya sütun yöneticisi ve görünüm ayarı
- Çok küçük yazı ve birbirine yapışık 14+ sütun
- Kaynak fiyatının dış çıktıda görünmesi
- Formun ürün satırından kopuk başka bir ekrana taşınması

---

## 13. PDF, Excel ve HTML çıktıları

### HTML paylaşım sayfası

- Etkileşimli ana kanal
- Otomatik taslak kayıt
- Alan doğrulama
- Dosya yükleme
- Kısmi tamamlama ve gönderme

### PDF

- Gönderilmiş yanıtın değiştirilemez anlık görüntüsü
- Belge numarası, tur, tarih, firma ve kapsam
- Her sayfada belge numarası ve sayfa numarası
- Dil tamamen seçilen locale göre
- Form alanı değil, gönderilmiş değer görünümü

### Excel

- Çinli/ithalatçı firma için çevrimdışı gel-git senaryosunda kullanılabilir
- Satır kimlikleri ve şema sürümü gizli/korumalı teknik sütunlarda tutulmalı
- Yeniden içe aktarmada ürün eşleşmesi adla değil değişmez kimlikle yapılmalı
- Bilinmeyen sütun ve formül enjeksiyonuna karşı doğrulama yapılmalı

---

## 14. Claude Code için uygulama sırası

1. Mevcut liste, ürün, paylaşım ve firma tablolarını incele; mevcut yapıyı bozmadan migration planı çıkar.
2. `recipient_type`, locale, permissions, expiry ve response modellerini ekle.
3. Rol bazlı server-side serializer/view-model katmanını yaz.
4. Ortak belge kabuğunu oluştur.
5. Önce Çinli firma RFQ portalını uygula: bu akış veri alanı olarak en kapsamlı dış formdur.
6. İthalatçı DDP portalını uygula.
7. Müşteri teklif portalını uygula.
8. Autosave için optimistic UI + sunucu revision numarası kullan.
9. Gönderim öncesi rol bazlı doğrulama özetini göster.
10. Gönderim sonrası response'u kilitle ve PDF anlık görüntüsü üret.
11. İç panelde üç rolün yanıtlarını ayrı sekmelerde, kör kıyas kuralına uygun göster.
12. TR/EN/ZH sıfır karışık dil testlerini ekle.
13. Yetkisiz alan sızıntısı için API snapshot/contract testleri yaz.
14. Mobil, yazdırma ve uzun ürün adı testlerini tamamla.

---

## 15. Kabul kriterleri

### Fonksiyonel

- Aynı liste için üç farklı recipient type paylaşımı oluşturulabiliyor.
- Her bağlantı yalnız kendi rol verisini görüyor.
- İthalatçı DDP + KDV teklifini kaydedip gönderebiliyor.
- Çinli firma bulundu/bulunamadı/alternatif yanıtı ile EXW/FOB ve paket bilgisi girebiliyor.
- Müşteri ürün bazlı ilgi ve liste onayı verebiliyor.
- Taslak yarıda bırakılıp devam ettirilebiliyor.
- Gönderilen yanıt salt okunur oluyor.
- Ürün sahibi gerektiğinde revizyon isteyebiliyor.

### Güvenlik

- Rol değiştirmek için URL parametresi değiştirilemez.
- Gizli alanlar API response'unda bulunmaz.
- Bir firma başka firmanın cevabını göremez.
- Süresi dolmuş veya iptal edilmiş bağlantı açılamaz.
- Kod deneme limiti vardır.
- Her gönderim audit kaydı üretir.

### Dil

- TR görünümde Çince/İngilizce sistem etiketi yoktur.
- ZH görünümde Türkçe/İngilizce sistem etiketi yoktur.
- EN görünümde Türkçe/Çince sistem etiketi yoktur.
- Ürün orijinal başlığı sistem etiketi sayılmaz; ayrı “orijinal başlık” alanında açıkça işaretlenir.

### Görsel

- Dış görünüm yönetim paneli gibi değildir.
- Belge kimliği ve son tarih ilk ekranda görünür.
- Ürün ve yanıt alanı aynı bağlamda kalır.
- Masaüstünde sütunlar ve form alanları okunur.
- Mobilde ürünler açılır karta dönüşür.
- Yazdırmada demo rol seçici ve etkileşim düğmeleri görünmez.

---

## 16. Son mimari hüküm

TedarikApp'te “tek listeyi herkese gönder” yaklaşımı bırakılmalıdır. Doğru model:

```text
Ortak ürün/listesi verisi
        │
        ├── İç operasyon görünümü (tüm ticari ve analiz verileri)
        ├── Müşteri teklif portalı (satış ve onay)
        ├── İthalatçı DDP portalı (Türkiye teslim maliyet teklifi)
        └── Çinli firma RFQ portalı (ürün bulma ve EXW/FOB üretim teklifi)
```

Bu ayrım yalnız görsel bir tercih değil; veri güvenliği, pazarlık gücü, firma kullanım kolaylığı ve sağlıklı teklif karşılaştırması için mimari zorunluluktur.

