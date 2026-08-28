# Görev #28 Ek — Dönüş Formatı Ayrıştırma Numuneleri

**Gözlem tarihi:** 28 Ağustos 2026  
**Amaç:** V3-C yapıştır-ayrıştır ve Excel gel-git testlerine saha biçimi sağlamak  
**Önemli şerh:** Aşağıdaki metinler gerçek müşteri yazışmasının kopyası değildir. Türkiye’deki kamuya açık talep/iletişim desenleri, OAİB proforma alanları ve Oracle/Coupa çevrimdışı tablo davranışlarından türetilmiş, ticari veri içermeyen sentetik numunelerdir. Format sınıfları gerçektir; fiyat ve firma adları kurgusaldır.

Kaynak dayanağı: [Hesnaf Global](https://hesnafglobal.com/teklif-talep-formu/), [FimexAsia](https://cindengetir.com/), [Shanghai Trimpex](https://www.cindenyedekparca.com/news/cinden-%C3%BCr%C3%BCn-tedariki%C4%9F-baslang%C4%B1c-rehberi), [OAİB proforma açıklaması](https://oaib.org.tr/bilgi-ve-operasyon-merkezi/ihracat-belgeleri/dis-ticarette-kullanilan-faturalar), [Oracle spreadsheet response](https://docs.oracle.com/en/cloud/saas/procurement/26c/oaprc/response-to-negotiations.html), [Coupa Sourcing FAQ](https://compass.coupa.com/en-us/products/product-documentation/supplier-resources/for-suppliers/coupa-supplier-portal/set-up-the-csp/sourcing/sourcing-faq).

## 1. WhatsApp — numaralı kısa satırlar

```text
Liste 2026-018 için dönüş:
1) TH-001 cam yağlık — DDP KDV dahil 186,50 TL/ad — MOQ 600 — 35 gün
2) TH-002 çelik termos — 500 ad 4,20 USD / 1000 ad 3,85 USD — DDP henüz yok
3) TH-003 organizer — bulunamadı
4) TH-004 masa lambası — aynısı yok, benzeri var: https://example.invalid/alt-44
   700 adet DDP+KDV 248 TL, termin 40-45 gün
Kur: USD/TRY 47,80. TL fiyat 31.08.2026 17:00'a kadar sabit.
Teklif geçerliliği: 05.09.2026.
```

### Beklenen anlam

| Girdi | Anlam |
|---|---|
| TH-001 | Fiyatlandı; DDP+KDV açık. |
| TH-002 | Kademeli ürün fiyatı var; DDP sonucu henüz yok. Nihai DDP sayılmaz. |
| TH-003 | Açık `Bulunamadı`. |
| TH-004 | Asıl satır bulunamadı; alternatif ayrı cevap olarak asıl satıra bağlanır. |
| Kur/geçerlilik | İki ayrı süre; tek tarihe birleştirilmez. |

## 2. WhatsApp — serbest metin ve kısmi dönüş

```text
Abi ilk baktıklarımız bunlar
0458 kulaklık 1000 tane 315 tl her şey dahil kdv dahil
0312 termos fiyat bekliyorum
0187 valiz yok aynı kalite bulamadık ama foto atacağım
0099 led 2.36$ ürün fiyatı moq 500, kargo gümrük daha çalışılmadı
diğerlerine yarın devam
```

### Ayrıştırma tuzakları

- `her şey dahil` ifadesinde teslim noktası yazmıyor; DDP var sayılmamalıdır.
- `fiyat bekliyorum` ve `diğerlerine devam` cevapsız/beklemededir; `Bulunamadı` değildir.
- `foto atacağım` henüz alternatif ürün cevabı değildir.
- `2.36$ ürün fiyatı` DDP+KDV fiyatı değildir.
- Mesaj yalnız bazı satırları kapsar; tur kısmi kalır.

## 3. WhatsApp — kademeler yalnız tam miktar gibi yazılmış

```text
Ürün 12:
500 adet: $5.10
1000 adet: $4.70
2000 adet: $4.25
MOQ 500
```

### Beklenen davranış

700 adet için otomatik interpolasyon yapılmaz. Metin `500–999` veya `500+` demediği için 700 fiyatının 5,10 USD olduğu kesinleştirilmez; yeni turda sorulur.

## 4. WhatsApp — aralık açık kademeli fiyat

```text
Ürün 12 / EXW:
500-999 pcs 5.10 USD
1000-1999 pcs 4.70 USD
2000+ pcs 4.25 USD
MOQ: 500 pcs
Lead time: 28 days after deposit
```

### Beklenen davranış

700 adet, `500–999` aralığındaki 5,10 USD fiyatına eşleşir. Bu EXW fiyatıdır; DDP+KDV alanına yazılmaz.

## 5. WhatsApp — ondalık ve para işareti varyasyonları

```text
A-01 4,25 USD
A-02 USD 3.90
A-03 $5.10
A-04 6.20$
A-05 215,00 TL KDV dahil
A-06 RMB 18.50 EXW
```

### Beklenen davranış

- Virgül/nokta ondalık ayraçları bağlama göre normalize edilir.
- Para işaretinin konumu anlamı değiştirmez.
- USD, TL ve RMB tek para birimine sessizce çevrilmez.
- EXW fiyatı DDP fiyatına dönüştürülmez.

## 6. WhatsApp — boş, sıfır, tire ve durum ayrımı

```text
Kod       Fiyat      Not
TH-101    -          bakılıyor
TH-102    0          numune ücretsiz, kargo hariç
TH-103               fabrika cevap vermedi
TH-104    yok        ürün bulunamadı
TH-105    N/A        bu model üretilmiyor
```

### Beklenen anlam

| Kod | Sonuç |
|---|---|
| TH-101 | Cevap bekleniyor; fiyat yok. |
| TH-102 | Sıfır fiyat geçerli olabilir; notla birlikte değerlendirilir. |
| TH-103 | Cevapsız; `Bulunamadı` değil. |
| TH-104 | Açık `Bulunamadı`. |
| TH-105 | Açık olumsuz cevap; `Bulunamadı` karşılığı olabilir. |

## 7. WhatsApp — alternatif asıl satıra bağlı

```text
LST-2026-0187 valiz seti: exact item not available.
Alternative 1: Model V-88, ABS, 3 pcs set
Photo: [görsel]
DDP VAT included: TRY 1,480 / set
MOQ: 120 sets
Lead time: 42 days
Difference: wheels are single, requested item has double wheels.
```

### Beklenen davranış

- LST-2026-0187 asıl satırı değiştirilmez.
- Asıl cevap `Bulunamadı` olarak korunur.
- V-88 kendi fiyat/MOQ/termin ve fark açıklamasıyla bağlı alternatif cevaptır.

## 8. Serbest Excel — sütun sırası ve başlık eş anlamları

```text
Supplier Code | Product / Ref | Qty | Unit DDP | VAT | Min.Order | Delivery | Remark
CN-7781       | TH-001        | 600 | 186.50   | incl| 600       | 35 days  | white box
CN-9012       | TH-002        |1000 |          |     | 500       |          | price pending
              | TH-003        | 500 |          |     |           |          | not found
CN-8120       | TH-004-ALT1   | 700 | 248.00   | incl| 500       | 45 days  | alternative
```

### Başlık eşlemeleri

| Gelen başlık | V3-C anlamı |
|---|---|
| Unit DDP / Kapı teslim / DDP Unit | DDP birim fiyat adayı; KDV ayrıca doğrulanır. |
| VAT / KDV | Dahil-hariç bilgisi. |
| Min.Order / MOQ / 起订量 | Minimum sipariş adedi. |
| Delivery / Lead time / 交期 | Termin. |
| Remark / Note / 备注 | Açıklama. |

## 9. Excel — dikey kademeler

```text
Row ID | Product | From Qty | To Qty | Unit Price | Currency | Basis
12     | TH-002  | 500      | 999    | 5.10       | USD      | EXW
12     | TH-002  | 1000     | 1999   | 4.70       | USD      | EXW
12     | TH-002  | 2000     |        | 4.25       | USD      | EXW
```

### Beklenen davranış

Üç satır tek ürünün üç fiyat kademesidir; üç ayrı ürün değildir. Boş `To Qty`, son kademede üst sınır bulunmadığını ifade eder.

## 10. Excel — yatay kademeler

```text
ID     | Product       | MOQ | 500 pcs | 1000 pcs | 2000 pcs | Lead time
TH-002 | Steel Thermos | 500 | 5.10 USD| 4.70 USD | 4.25 USD | 30-35 days
```

### Beklenen davranış

Sütun başlıklarındaki miktarlar kademe eşikleridir. Aralık anlamı belgede açıklanmıyorsa ara miktar fiyatı kesinleştirilmez.

## 11. Excel — kısmi Tur 1

```text
List ID: V3C-2026-0042
Round: 1

Line | Product | Status        | DDP+VAT | MOQ | Lead | Note
1    | TH-001  | Found         | 186.50  | 600 | 35d  |
2    | TH-002  | Pending       |         | 500 |      | factory checking
3    | TH-003  | Not found     |         |     |      |
4    | TH-004  | Alternative   | 248.00  | 500 | 45d  | see ALT-4A
```

### Beklenen davranış

- Tur 1 kısmi cevap olarak kilitlenir.
- Satır 2 cevapsız/beklemede; satır 3 `Bulunamadı`.
- `ALT-4A` satır 4’ün yerine yazılmaz; ona bağlı alternatif olarak alınır.

## 12. Excel — yanlış tur dosyası

```text
List ID: V3C-2026-0042
Round: 1
Exported at: 2026-08-20 09:30

[Firma bu eski dosyayı Tur 2 başladıktan sonra değiştirip geri gönderir.]
```

### Beklenen davranış

Dosya Tur 2’ye sessizce yazılmaz. İçindeki `Round: 1` ve dışa aktarım bilgisi korunur; kullanıcıya ait olduğu tur bağlamında doğrulatılır.

## 13. PDF/proforma — biçimli dönüş iskeleti

```text
PROFORMA INVOICE
PI No: PI-2026-088
Buyer: [YER TUTUCU]
Delivery term: DDP Sakarya Warehouse, Incoterms 2020
Currency: TRY
VAT: Included
Exchange-rate reference: USD/TRY 47.80
Rate locked until: 31.08.2026 17:00
Offer valid until: 05.09.2026

Item Ref | Description | Qty | Unit DDP+VAT | Total
TH-001   | Glass oil dispenser | 600 | 186.50 TRY | 111,900 TRY

MOQ: 600 pcs
Lead time: 35 days after order confirmation
```

### Beklenen davranış

- DDP varış noktası, Incoterms sürümü, KDV, kur ve iki ayrı süre korunur.
- PDF ham kaynak belgedir; satır cevabı bu belgeyle ilişkilidir.
- PDF’deki veri, aynı turun daha eski WhatsApp fiyatını görünmez biçimde silmez.

## 14. PDF/proforma — kapsam belirsiz dipnot

```text
Price: 210 TRY / pc
Term: Door delivery
Validity: 5 days
Note: Taxes and local charges may apply.
```

### Beklenen davranış

Bu fiyat `DDP + KDV dahil` olarak kabul edilemez. `Door delivery` tek başına ithalat vergisi/KDV kapsamını kanıtlamaz; dipnot ek masraf ihtimalini açık bırakır.

## 15. Tur 2 — yalnız eksik ve itirazlı satırlar

```text
Liste: V3C-2026-0042
Tur: 2
Referans: Tur 1

Eksik: 02, 05, 07, 11, 14, 18, 22
İtiraz: 03 (MOQ), 09 (termin), 16 (DDP fiyat)

02: 700 adet için DDP+KDV 232 TL, MOQ 700, 38 gün
03: MOQ 1000'den 600'e indi, birim fiyat 195 TL
09: termin 45 günden 36 güne revize
16: önceki fiyat geçersiz; yeni kur 48,10, DDP+KDV 274 TL
05/07/11/14/18/22: çalışma devam ediyor
```

### Beklenen davranış

- Tur 1’in diğer 15 satırı Tur 2’ye yeniden cevaplanmış sayılmaz.
- Tur 2 değerleri Tur 1’i silmez; karşılaştırmada değişiklik olarak görünür.
- `çalışma devam ediyor` satırları `Bulunamadı` değildir.

## 16. Minimum ayrıştırma kabul listesi

- [ ] Satır kimliği/kodu bulundu mu?
- [ ] Asıl ürün ile alternatif ayrıldı mı?
- [ ] Boş, beklemede ve `Bulunamadı` ayrıldı mı?
- [ ] Fiyatın türü EXW/FOB/DDP olarak belirlendi mi?
- [ ] KDV dahil/hariç açık mı?
- [ ] Para birimi açık mı?
- [ ] Kademe eşik/aralık anlamı açık mı?
- [ ] MOQ ve termin doğru ürüne bağlandı mı?
- [ ] Kur kaynağı/değeri ve kilit süresi ayrıldı mı?
- [ ] Teklif geçerliliği kur kilidinden ayrı tutuldu mu?
- [ ] Liste ve tur kimliği doğrulandı mı?
- [ ] Ham kaynak metin/dosya korunuyor mu?
