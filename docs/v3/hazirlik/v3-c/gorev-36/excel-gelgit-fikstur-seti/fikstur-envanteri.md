# Excel Gel-Git Fikstür Envanteri

**Fikstür sayısı:** **12**  
**Şema:** excel-gelgit-v1  
**Ortak test satırları:** RFQ-36-001..003; test entegrasyonu manifestteki sentetik imzaları aynı seed ile doğrular.

> Fikstürler makrosuzdur; portal linki veya 6 haneli anahtar içermez. Negatif dosyalar da fiziksel olarak açılabilir çalışma kitaplarıdır; beklenen red uygulama içe aktarıcısındadır.

| Kimlik | Dosya | Amaç | Manifest turu | Beklenen sonuç |
|---|---|---|---|---|
| FX-01 | 01-temiz.xlsx | temiz | SR-36-R1 | Tam ve imzaları doğru çalışma kitabı içe alınır; önizlemede uygulanabilir satırlar oluşur, tur `PRICING` kalır. |
| FX-02 | 02-kismi.xlsx | kısmi | SR-36-R1 | Dolu satırlar önizlenir; boş satır “değişiklik yok”, `not_found` değildir. |
| FX-03 | 03-bozuk-satir-imzasi.xlsx | bozuk imza | SR-36-R1 | Bozuk imzalı satır uygulanamaz; diğer satır güvenli önizlenebilir. |
| FX-04 | 04-formul-enjeksiyonu.xlsx | formül enjeksiyonu | SR-36-R1 | Ham formül hücresi çalıştırılmadan güvenlik hatası; kaçışlı metin metin olarak korunur. |
| FX-05 | 05-yanlis-tur.xlsx | yanlış tur | SR-36-R2 | Çalışma kitabının tamamı reddedilir; aktif tura hiçbir yazma olmaz. |
| FX-06 | 06-eksik-zorunlu.xlsx | eksik zorunlu | SR-36-R1 | Eksik para/MOQ/termin alanlı satır hatalı; geçerli satırlar yalnız önizlenir. |
| FX-07 | 07-para-birimi-belirsiz.xlsx | para belirsiz | SR-36-R1 | Fiyat kesinleştirilmez; satır `BELİRSİZ`, kullanıcı kararı olmadan seçilemez. |
| FX-08 | 08-kademe-cakismasi.xlsx | kademe çakışması | SR-36-R1 | Yalnız çakışan kademeler bloklanır; ana fiyat geçerliyse ayrı önizlenebilir. |
| FX-09 | 09-cince-baslik.xlsx | Çince başlık | SR-36-R1 | Başlık varyantı güvenli biçimde eşlenir veya açık şema hatası verir; sıra/adla yanlış ürüne yazma olmaz. |
| FX-10 | 10-bom-kodlama.xlsx | BOM/kodlama | SR-36-R1 | Baştaki BOM normalize edilir; ZH/TR karakterler bozulmaz; kimlik/imza eşlemesi korunur. |
| FX-11 | 11-yabanci-mukerrer.xlsx | yabancı+mükerrer | SR-36-R1 | `YABANCI` ve iki `MÜKERRER` satır bloklanır; “sonuncuyu al” yoktur. |
| FX-12 | 12-kilitli-tur.xlsx | kilitli tur | SR-36-R1-CLOSED | Gönderilmiş turda uygulama yok; “yeni revizyon turu aç” seçeneği, özgün dosya değişmeden sunulur. |

## Ortak sayfa sözleşmesi

| Sayfa | Görünürlük | İçerik |
|---|---|---|
| START | Görünür | TR/EN/ZH kullanım, gerçek olmayan test liste/firma kimliği, DDP Türkiye KDV dahil açıklaması |
| QUOTATION | Görünür | A–AF kanonik RFQ ve cevap sütunları |
| PRICE_TIERS | Görünür | A–H kanonik kademe sütunları |
| VALIDATION | Çok gizli | Durum/para/termin/birim doğrulama listeleri |
| MANIFEST | Çok gizli | schema_version, tur/snapshot, satır sayısı ve imzalar |

## Çalıştırma sırası

1. Güvenlik ve şema kontrolü.
2. Tur/snapshot/satır/imza eşleştirmesi.
3. Alan doğrulama ve fark önizlemesi.
4. Yalnız hatasız/açık eşleşenlerin seçimi.
5. Tek idempotency anahtarıyla uygulama; orijinal dosya değişmeden sonuç dosyası.
