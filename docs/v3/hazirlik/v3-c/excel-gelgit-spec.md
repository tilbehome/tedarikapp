# TedarikApp V3-C Excel Gel-Git Şablon Spesifikasyonu

**Amaç:** Portala girmek istemeyen firmanın aynı RFQ sözleşmesini Excel'de doldurup geri göndermesi  
**Biçim:** `.xlsx`, makrosuz, mobilde okunabilir fakat masaüstü Excel/LibreOffice ile doldurulması önerilir  
**Güvenlik:** Dosya linki veya 6 haneli portal anahtarı içermez; Excel koruması güvenlik sınırı değil, yanlış düzenleme önlemidir.

## 1. Çalışma kitabı yapısı

| Sayfa | Görünürlük | Amaç |
|---|---|---|
| `START` | Görünür, ilk sayfa | TR/EN/ZH kısa kullanım, DDP Türkiye KDV dahil açıklaması, geçerlilik ve termin başlangıç tanımı |
| `QUOTATION` | Görünür | Ürün/RFQ satırları ve firma yanıt ana alanları |
| `PRICE_TIERS` | Görünür | Bir satıra ait sınırsız görsel karmaşa yaratmadan en çok 20 kademeli fiyat satırı |
| `VALIDATION` | Çok gizli (`veryHidden`) | Durum, para birimi, termin başlangıcı/birimi ve birim kodu doğrulama listeleri |
| `MANIFEST` | Çok gizli (`veryHidden`) | Şema sürümü, tur kimliği, satır sayısı ve satır imzaları; kullanıcı alanı değildir |

Dosyada VBA/makro, dış çalışma kitabı bağlantısı, uzak veri sorgusu, Power Query veya çalıştırılabilir nesne bulunmaz.

## 2. Dil ve görünüm

- Bütün görünür başlıklar tek hücrede üç satırdır: `TR / EN / 中文`. Örnek: `Yanıt durumu\nResponse status\n回复状态`.
- `START` sayfasında üç ayrı dil bölümü vardır; kaynak/ticari terimlerde ZH metin belge dilidir.
- DDP başlığı açıkça “Türkiye KDV dahil” der: `KDV dâhil DDP birim fiyat / DDP unit price including Turkish VAT / 含土耳其增值税的DDP单价`.
- Donmuş üst satır, filtre, zebra satır, yüksek kontrastlı zorunlu alan rengi ve yazdırmada yatay sayfa kullanılır.
- Kilitli kaynak sütunları lacivert-açık gri; açık firma alanları beyaz; hatalı hücreler kırmızı kenarlık; uyarılar sarı bilgi notudur.
- Renk tek başına anlam taşımaz; hücre notu ve `VALID/ERROR/WARNING` metni de bulunur.

## 3. `START` sayfası

Üst bilgi:

- Liste adı, firma adı, tur numarası, oluşturulma tarihi ve teklif son tarihi.
- “Bağlantı/anahtar bu dosyada bulunmaz; portal anahtarı ayrı kanaldadır.” uyarısı.
- Incoterm + yer: örneğin `DDP — Sakarya, Türkiye; Türkiye KDV dahil`.
- Termin başlangıcı: örneğin “yazılı sipariş onayı ve kararlaştırılan ön koşulların tamamlanması”.
- Teklif geçerliliği: `{gun}` gün; iç karşılaştırma için kullanılan kur snapshot'ının firma ham fiyatını değiştirmediği açıklaması.
- Dört adım: satırı bul → durum seç → zorunlu alanları doldur → dosyayı kaydedip geri gönder.
- Firma başka firmanın fiyatını veya karşılaştırma sonucunu göremez.

## 4. `QUOTATION` sütunları

### 4.1 Kilitli kimlik ve RFQ sütunları

| Sütun | Makine adı | Koruma | Kural |
|---:|---|---|---|
| A | `rfq_satir_id` | Kilitli + gizli değil | Birincil eşleştirme kimliği; firma silmemeli/değiştirmemeli. |
| B | `supplier_round_id` | Kilitli + gizli | Dosyanın ait olduğu firma turu; başka turla birleştirilemez. |
| C | `satir_imzasi` | Kilitli + gizli | Kimlik ve RFQ snapshot bütünlüğü; gizli sır/token değildir. |
| D | `urun_kodu` | Kilitli | Firma iletişiminde kısa referans. |
| E | `urun_adi` | Kilitli | Seçili dosya dilinde görünür; kaynak ad hücre notunda korunur. |
| F | `kaynak_urun_url` | Kilitli | Yalnız `https`; tıklanabilir güvenli bağlantı. |
| G | `talep_edilen_varyant` | Kilitli | Renk/ölçü/model. |
| H | `talep_miktari` | Kilitli | Pozitif sayı. |
| I | `talep_birimi` | Kilitli | adet/set/paket/koli vb. |
| J | `alici_satir_notu` | Kilitli | Ticari talep veya teknik not. |

Kimlik sütunu satır kaydırılsa bile eşleştirme sağlar; satır sıra numarası eşleştirme anahtarı değildir.

### 4.2 Açık firma yanıt sütunları

| Sütun | Makine adı | Excel veri doğrulaması | Zorunluluk |
|---:|---|---|---|
| K | `yanit_durumu` | Liste: `found/not_found/alternative_available` ve üç dilli görünen değer | Her yanıtlanan satırda |
| L | `ddp_birim_fiyat_kdv_dahil` | Ondalık `>0`, en çok 6 basamak | Bulundu/alternatif ise |
| M | `para_birimi` | Liste: USD/CNY/TRY/EUR | Fiyat varsa |
| N | `ddp_turkiye_kdv_dahil_onayi` | Liste: YES/NO | Fiyat varsa YES |
| O | `moq_deger` | Ondalık `>=1` | Bulundu/alternatif ise |
| P | `moq_birim` | İzinli birim listesi | MOQ varsa |
| Q | `termin_baslangici` | Sipariş onayı/kapora/numune/görsel onayı/özel | Bulundu/alternatif ise |
| R | `termin_baslangici_aciklamasi` | En çok 300 karakter | Başlangıç “özel” ise |
| S | `termin_suresi` | Tam sayı 1–365 | Bulundu/alternatif ise |
| T | `termin_birimi` | Takvim günü/iş günü/hafta | Süre varsa |
| U | `koli_ici_adet` | Tam sayı 1–1.000.000 | Uyarılı isteğe bağlı |
| V | `koli_uzunluk_cm` | Ondalık `>0`, `<=1000` | Bir ölçü varsa üçü birlikte |
| W | `koli_genislik_cm` | Ondalık `>0`, `<=1000` | Bir ölçü varsa üçü birlikte |
| X | `koli_yukseklik_cm` | Ondalık `>0`, `<=1000` | Bir ölçü varsa üçü birlikte |
| Y | `koli_cbm` | Ondalık `>0`, `<=100` | İsteğe bağlı |
| Z | `koli_brut_kg` | Ondalık `>0`, `<=10000` | Net varsa zorunlu |
| AA | `koli_net_kg` | Ondalık `>0`, `<=10000` | İsteğe bağlı |
| AB | `ambalaj` | En çok 1000 karakter | İsteğe bağlı/özel talepte zorunlu |
| AC | `firma_notu` | En çok 5000 karakter | Bulunamadı ise en az 3 karakter |
| AD | `alternatif_urun_baglantisi` | `https` URL | Alternatifte bağlantı veya açıklama |
| AE | `alternatif_aciklamasi` | 3–3000 karakter | Alternatifte bağlantı veya açıklama |
| AF | `satir_dogrulama` | Kilitli formül/uygulama çıktısı | `OK`, `UYARI`, `HATA` |

Excel veri doğrulaması yalnız ilk kullanıcı yardımıdır. İçe aktarıcı aynı kuralları sunucuda yeniden uygular; kopyala-yapıştırla doğrulamanın aşılması kabul sayılmaz.

## 5. `PRICE_TIERS` sayfası

| Sütun | Makine adı | Koruma/kural |
|---:|---|---|
| A | `rfq_satir_id` | Açılır listeden yalnız `QUOTATION` içindeki kimlik; satır eşleştirme için zorunlu |
| B | `supplier_round_id` | Kilitli/gizli; ana sayfadaki turla aynı |
| C | `min_adet` | `>=1`; aynı ürün içinde kesin artan sıra |
| D | `max_adet` | Son kademede boş olabilir; varsa `>=min` |
| E | `birim_fiyat` | `>0`; en çok 6 ondalık |
| F | `para_birimi` | Ana satırın para birimiyle aynı |
| G | `aciklama` | İsteğe bağlı, en çok 500 karakter |
| H | `kademe_dogrulama` | Kilitli; sıra/çakışma/para birimi sonucu |

Ürün başına en çok 20 kademe kabul edilir. Satırlar Excel'de dağınık sırada olabilir; içe aktarım önizlemesi `min_adet` sırasına dizilmiş öneriyi gösterir. Sistem sessizce sıra veya sınır anlamı değiştirmez. Çakışan aralıklar bloklanır. Miktar yükselirken fiyat yükselmesi ticari olarak mümkün kabul edilir; hata değil teyit uyarısıdır.

## 6. Koruma ve dosya bütünlüğü

1. `START`, kimlik sütunları, kaynak alanlar, formüller, `MANIFEST` ve `VALIDATION` kilitlidir.
2. Yalnız K–AE firma hücreleri ve `PRICE_TIERS` giriş hücreleri açıktır.
3. Sayfa koruma parolası güvenlik sırrı değildir; yalnız kazara değişikliği önler.
4. Satır ekleme/silme varsayılan kapalıdır. Firma teklif dışı alternatif satır eklemek yerine mevcut satırdaki alternatif alanlarını kullanır.
5. `MANIFEST` içinde `schema_version`, `exported_at`, `supplier_round_id`, `rfq_snapshot_id`, `row_count` ve her `rfq_satir_id` için imza bulunur.
6. İmza, sunucudaki gizli doğrulama ile kontrol edilir; dosyaya gizli anahtar yazılmaz.
7. Aynı dosyayı ikinci kez yükleme aynı `import_fingerprint` ile mükerrer önizleme uyarısı üretir; otomatik tekrar uygulamaz.
8. Formül enjeksiyonuna karşı dış metinlerde ilk görünür karakter `=`, `+`, `-` veya `@` ise metin olarak kaçışlanır; geri içe aktarımda formül çalıştırılmaz.
9. Harici bağlantılar, OLE nesneleri, makrolar ve parola ile şifrelenmiş dosyalar içe alınmaz.

## 7. İçe aktarım eşleştirme kuralları

Eşleştirme sırası şöyledir:

1. Çalışma kitabı `schema_version` destekleniyor mu?
2. `supplier_round_id` açık ve yazılabilir aktif turla birebir aynı mı?
3. `rfq_snapshot_id` turdaki kilitli RFQ ile aynı mı?
4. Her satırın `rfq_satir_id` değeri bu turda var mı?
5. `satir_imzasi` kaynak kimlik/miktar/varyant snapshot'ıyla eşleşiyor mu?
6. Cevap alanları `rfq-alan-sozlesmesi.json` doğrulamasından geçiyor mu?

Ürün adı, Excel satır numarası, URL veya sıra **eşleştirme anahtarı değildir**. Bunlar yalnız insan önizlemesi içindir.

## 8. Bozuk ve eksik satır davranışı

| Durum | Sonuç |
|---|---|
| Kimlik bu turda yok | Satır `YABANCI` olarak bloklanır; hiçbir ürüne uygulanmaz. |
| Aynı `rfq_satir_id` ana sayfada iki kez | İki satır da `MÜKERRER` bloklanır; “sonuncuyu al” yapılmaz. |
| Satır imzası bozuk | Kaynak alan değiştirilmiş sayılır; yanıt önizlenebilir fakat uygulanamaz. |
| Satır dosyada yok | Paneldeki mevcut taslak değişmez; silinmiş sayılmaz. |
| Yanıt hücreleri tamamen boş | “Değişiklik yok” sayılır; mevcut değeri sessizce temizlemez. |
| Kısmen doldurulmuş bulundu satırı | Hata listesine gider; diğer geçerli satırlar önizlenebilir. |
| Para birimi olmayan fiyat | Fiyat uygulanmaz; belirsiz alan olarak gösterilir. |
| Geçersiz kademe | Yalnız ilgili kademe/satır bloklanır; ana fiyat ayrı geçerliyse önizlenebilir. |
| Brüt < net veya termin >365 | Alan hatası; sessiz düzeltme yok. |
| Formül/makro/dış bağlantı | Güvenlik raporu; riskli içerik çalıştırılmaz. |
| Başka firma veya tur dosyası | Tüm içe aktarım reddedilir. |
| Gönderilmiş/kilitli tur | Değişiklik uygulanmaz; Ürün Sahibine “yeni revizyon turu aç” seçeneği sunulur. |

Boş hücre ile temizleme yapılmaz. Bir değeri temizlemek gerekiyorsa portalda açık “Alanı temizle” eylemi veya içe aktarım önizlemesinde satır/alan bazlı açık temizleme onayı kullanılır.

## 9. Önizleme ve onay akışı

```text
Dosya seç
  → güvenlik/şema kontrolü
  → tur ve satır eşleştirme
  → alan doğrulama
  → fark önizlemesi
  → Ürün Sahibinin alan/satır seçimi
  → tek kullanımlık idempotency anahtarıyla uygula
  → sonuç + hata dosyası
```

Fark önizlemesi her satırı şu gruplardan birinde gösterir:

- **Uygulanabilir:** Eski → yeni değer, kaynak hücre adresiyle.
- **Uyarılı:** MOQ talep miktarından yüksek, fiyat kademesi artıyor veya CBM farkı gibi teyit gerektirir.
- **Hatalı:** Uygulanamaz; neden ve düzeltme hücresi görünür.
- **Belirsiz:** Para birimi/ürün eşleşmesi veya metin anlamı açık değil; kullanıcı kararı olmadan uygulanmaz.
- **Değişiklik yok:** Sisteme yazma yapılmaz.

Varsayılan seçim yalnız hatasız ve açık eşleşen satırlardır. Uyarılı satırlar elle seçilir; hatalı/belirsiz satırlar seçilemez. “Tümünü uygula” bile kilitli, yabancı veya hatalı satırı geçiremez. Uygulama sonrası tur sürümü artar, audit ve tek birleştirilmiş bildirim üretilir.

## 10. Dışa tekrar aktarma

- Hatalı dosyaya doğrudan yazılmaz; yeni bir `...-IMPORT-RESULT.xlsx` üretilir.
- Sonuç dosyası her satır için durum, hata kodu ve düzeltme açıklaması içerir; orijinal hücre değerleri korunur.
- Firma geri dönüşü nihai yanıt değildir. Ürün Sahibi içe aktarımı onayladıktan sonra firma turu hâlâ `PRICING` durumundadır; nihai gönderim kapısı ayrıca çalışır.
- Ürün Sahibi Excel yanıtını firma adına nihai gönderirse audit kaydında `actor=product_owner`, `source=excel_import` açıkça yazılır; firma eylemi gibi gösterilmez.
