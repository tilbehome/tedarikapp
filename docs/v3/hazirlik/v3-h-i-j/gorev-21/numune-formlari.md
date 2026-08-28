# Numune süreci formları

**Sürüm:** V3-I / 1.0  
**Kapsam:** Ev ve yaşam ürünlerinde numune talebi, değerlendirme, hızlı AQL kabul örneklemesi, onay numunesi ve bedel mahsubu  
**Kullanım notu:** Köşeli parantezler doldurulacak alanlardır. Kritik terimlerin Çince karşılığı parantez içinde verilmiştir.

## 1. Numune talep formu（样品申请表）

### A. Talep ve taraf bilgileri

| Alan | Doldurulacak bilgi |
|---|---|
| Talep no（申请编号） | `[NUM-YYYY-NNNN]` |
| Talep tarihi（申请日期） | `[YYYY-AA-GG]` |
| Talep sahibi | `[Ad / rol]` |
| Tedarikçi firma（供应商） | `[Firma adı]` |
| Yetkili kişi（联系人） | `[Ad soyad]` |
| İletişim kanalı | `[WeChat / e-posta / telefon]` |
| TedarikApp ürün referansı | `[DM-… / ürün kayıt id]` |
| Kaynak ürün bağlantısı | `[Kanonik TedarikApp kayıt bağlantısı]` |

### B. Ürün ve numune ayrıntıları

| Alan | Doldurulacak bilgi |
|---|---|
| Ürün adı TR | `[Ürün adı]` |
| Ürün adı ZH（产品名称） | `[中文产品名]` |
| Varyant（规格 / 款式） | `[Renk, ölçü, model, desen]` |
| Numune adedi（样品数量） | `[n] adet` |
| İstenen özellik / revizyon（要求） | `[Malzeme, ölçü, renk, işlev vb.]` |
| Referans görsel / teknik dosya | `[Dosya bağlantısı veya ek no]` |
| Numune amacı | `[İlk değerlendirme / revizyon / onay numunesi]` |
| İstenen hazır olma tarihi（交样日期） | `[YYYY-AA-GG]` |

### C. Bedel ve mahsup koşulu

| Alan | Doldurulacak bilgi |
|---|---|
| Numune bedeli（样品费） | `[Para birimi] [tutar]` |
| Kargo bedeli（运费） | `[Para birimi] [tutar]` |
| Diğer bedel | `[Kalıp / baskı / hazırlık: tutar]` |
| Ödeme yöntemi ve tarihi（付款方式 / 付款日期） | `[Yöntem] — [YYYY-AA-GG]` |
| Siparişe mahsup edilecek tutar（订单抵扣金额） | `[Para birimi] [tutar]` |
| Mahsup için asgari sipariş（抵扣条件） | `[MOQ / sipariş tutarı / ürün-varyant şartı]` |
| Mahsup son tarihi（抵扣期限） | `[YYYY-AA-GG veya gün sayısı]` |
| Mahsup dışı kalemler | `[Örn. kargo bedeli]` |
| Tedarikçi yazılı teyit bağlantısı | `[Mesaj / proforma / e-posta referansı]` |

### D. Kargo takibi

| Alan | Doldurulacak bilgi |
|---|---|
| Gönderim tarihi（发货日期） | `[YYYY-AA-GG]` |
| Kargo firması（物流公司） | `[Firma]` |
| Takip numarası（运单号） | `[Takip no]` |
| Takip bağlantısı | `[URL]` |
| Çıkış ülkesi / şehri | `[Ülke / şehir]` |
| Beklenen teslim | `[YYYY-AA-GG]` |
| Gerçek teslim | `[YYYY-AA-GG]` |
| Teslim alan | `[Ad / rol]` |
| Paket hasarı notu | `[Yok / açıklama + foto no]` |

### E. Teyit

```text
Tedarikçi teyidi（供应商确认）: [Ad / tarih / mesaj referansı]
TedarikApp kayıt sorumlusu: [Ad / tarih]
Ürün Sahibi onayı: [Ad / tarih / onay-ret]
```

## 2. Dört boyutlu numune değerlendirme formu（样品评估表）

### 2.1 Üst bilgi

| Alan | Değer |
|---|---|
| Değerlendirme no | `[DEG-YYYY-NNNN]` |
| Numune talep no | `[NUM-YYYY-NNNN]` |
| Ürün / varyant | `[Ürün] — [varyant]` |
| Numune seri/etiket no | `[Numune no]` |
| Teslim tarihi | `[YYYY-AA-GG]` |
| Değerlendirme tarihi | `[YYYY-AA-GG]` |
| Değerlendirenler | `[Ad / rol]` |
| Referans spesifikasyon | `[Dosya ve sürüm]` |

### 2.2 Puanlama ölçeği

| Puan | Anlam |
|---:|---|
| 1 | Kabul edilemez; temel beklenti karşılanmıyor |
| 2 | Zayıf; önemli düzeltme gerekiyor |
| 3 | Sınırda; koşullu ve doğrulanabilir düzeltme gerekiyor |
| 4 | İyi; küçük iyileştirme dışında uygun |
| 5 | Referansla tam uyumlu / beklentiyi karşılıyor |

### 2.3 Değerlendirme matrisi

Her satırda puan, açıklayıcı not ve en az bir foto yuvası birlikte doldurulur.

| Boyut | Kontrol ipuçları | Puan (1–5) | Not | Foto yuvası |
|---|---|---:|---|---|
| Görsel uyum（外观一致性） | Renk, ölçü, desen, yüzey, referans görselle fark | `[ ]` | `[Somut fark / ölçüm]` | `[FOTO-GOR-01…]` |
| Malzeme–işçilik（材料与工艺） | Malzeme hissi/uyumu, dikiş, birleşim, çapak, leke, koku | `[ ]` | `[Somut bulgu]` | `[FOTO-ISC-01…]` |
| Fonksiyon（功能） | Kullanım senaryosu, mekanizma, ölçü uyumu, dayanım ön kontrolü | `[ ]` | `[Test adımı ve sonuç]` | `[FOTO-FON-01…]` |
| Ambalaj（包装） | İç/dış ambalaj, koruma, etiket, koli içi hareket, ezilme riski | `[ ]` | `[Somut bulgu]` | `[FOTO-AMB-01…]` |

### 2.4 Sonuç ve karar

```text
Toplam puan（总分）: [  ] / 20
Kritik kusur（严重缺陷）var mı?: [Hayır / Evet — açıklama]
İstenen düzeltmeler（整改要求）: [Madde madde]
Karar（结论）:
[ ] Onaya aday
[ ] Düzeltme sonrası yeniden numune
[ ] Koşullu kabul — koşul: [...] 
[ ] Ret

Karar sahibi: [Ad / rol]
Karar tarihi: [YYYY-AA-GG]
```

Pratik karar önerisi: 17–20 “onaya aday”, 13–16 “koşullu/düzeltme”, 12 ve altı “ret” başlangıç eşiği olarak kullanılabilir. Herhangi bir boyutun 3’ün altında olması veya kritik kusur bulunması toplam puandan bağımsız inceleme gerektirir. Bu eşikleri Ürün Sahibi ürün riskine göre onaylar.

## 3. AQL hızlı referansı（AQL 抽样检验速查）

### 3.1 Kullanım çerçevesi

- Bu tablo ev/yaşam ürünleri için **normal, tekli örnekleme** ve **Genel Muayene Seviyesi II（一般检验水平 II）** kabulüyle hazırlanmış pratik bir hızlı referanstır.
- `AQL 2.5` önemli kusur（主要缺陷）, `AQL 4.0` küçük kusur（次要缺陷）için başlangıç profili olarak kullanılır.
- Kritik kusur（严重缺陷）için iç kural `Ac 0 / Re 1`’dir: bir kritik kusur partiyi durdurur ve Ürün Sahibi incelemesine taşır.
- `Ac` kabul sayısı（接收数）, `Re` ret sayısıdır（拒收数）. Örneğin `Ac 3 / Re 4`, üç kusurlu birime kadar kabul; dörtte ret demektir.
- Örnekler parti boyunca rastgele ve farklı kolilerden seçilir; kolay erişilen tek koliden alınmaz.

### 3.2 Pratik tablo

| Parti büyüklüğü（批量） | Kod | Örnek adedi（样本量） | AQL 2.5 Ac/Re | AQL 4.0 Ac/Re |
|---:|:---:|---:|:---:|:---:|
| 2–8 | A | 2 | 0 / 1 | 0 / 1 |
| 9–15 | B | 3 | 0 / 1 | 0 / 1 |
| 16–25 | C | 5 | 0 / 1 | 0 / 1 |
| 26–50 | D | 8 | 0 / 1 | 1 / 2 |
| 51–90 | E | 13 | 1 / 2 | 1 / 2 |
| 91–150 | F | 20 | 1 / 2 | 2 / 3 |
| 151–280 | G | 32 | 2 / 3 | 3 / 4 |
| 281–500 | H | 50 | 3 / 4 | 5 / 6 |
| 501–1.200 | J | 80 | 5 / 6 | 7 / 8 |
| 1.201–3.200 | K | 125 | 7 / 8 | 10 / 11 |
| 3.201–10.000 | L | 200 | 10 / 11 | 14 / 15 |
| 10.001–35.000 | M | 315 | 14 / 15 | 21 / 22 |

Bu, sahada hızlı planlama için sadeleştirilmiş referanstır; resmi standardın lisanslı kabul tablosunun yerine geçmez ve standardın ok/harf geçiş kurallarının tamamını göstermez. Sözleşmesel kabul planında güncel ISO 2859-1, ürün riski ve tarafların yazılı anlaşması esas alınmalıdır.

### 3.3 Uygulama örneği

`600` adetlik bir ev tekstili partisi için Level II kodu `J`, örnek adedi `80` olur:

- Önemli kusur AQL 2.5: `Ac 5 / Re 6`.
- Küçük kusur AQL 4.0: `Ac 7 / Re 8`.
- Kritik kusur iç kuralı: `Ac 0 / Re 1`.

Seksen birim farklı koli ve konumlardan rastgele seçilir. Önemli kusurlu birim sayısı 6’ya ulaşırsa parti bu plana göre reddedilir; yeniden işleme/ayıklama sonrası yeni örnekleme ayrıca kararlaştırılır.

### 3.4 Sınırlar

AQL, “partide yalnız yüzde 2,5/4 kusur vardır” garantisi değildir; kabul örnekleme yöntemidir. Güvenlik, mevzuat, kimyasal içerik, elektriksel test, gıda teması veya çocuk ürünü gibi özel riskleri ikame etmez. Ürün türüne özel test ve belge kararı Ürün Sahibi’ndedir.

## 4. Onay numunesi tutanağı（确认样记录）

### 4.1 Kimlik ve kapsam

| Alan | Değer |
|---|---|
| Tutanak no | `[OS-YYYY-NNNN]` |
| Ürün / varyant（产品 / 规格） | `[Ad / renk / ölçü / model]` |
| TedarikApp ürün referansı | `[Kayıt id]` |
| Tedarikçi（供应商） | `[Firma]` |
| Sipariş / teklif referansı | `[PO / teklif no]` |
| Onay numunesi kodu（确认样编号） | `[Kod]` |
| Numunenin fiziksel saklama yeri | `[Dolap / raf / kutu]` |
| Dijital foto klasörü | `[Kanonik bağlantı]` |
| Onay tarihi（确认日期） | `[YYYY-AA-GG]` |

### 4.2 Onaylanan özellikler

| Özellik | Onaylanan değer | Ölçüm yöntemi / tolerans | Foto / dosya ref |
|---|---|---|---|
| Malzeme（材料） | `[Değer]` | `[Yöntem / tolerans]` | `[Ref]` |
| Renk / desen（颜色 / 图案） | `[Değer]` | `[Referans kartı / dosya]` | `[Ref]` |
| Ölçü / ağırlık（尺寸 / 重量） | `[Değer]` | `[± tolerans]` | `[Ref]` |
| İşçilik（工艺） | `[Dikiş / birleşim / yüzey]` | `[Kabul tanımı]` | `[Ref]` |
| Fonksiyon（功能） | `[Beklenen sonuç]` | `[Test adımı]` | `[Ref]` |
| Ambalaj（包装） | `[İç/dış ambalaj]` | `[Koli / koruma şartı]` | `[Ref]` |
| Etiket（标签） | `[Sürüm / içerik]` | `[Onaylı artwork]` | `[Ref]` |

### 4.3 Sapma, mühürleme ve imza

```text
Önceki numuneden farklar（与前样差异）:
1. [...]
2. [...]

İzin verilen sapmalar（允许偏差）: [...]
İzin verilmeyen sapmalar: [...]
Fiziksel numune mühür/etiket no（封样编号）: [...]
Üretim bu onay numunesine göre yapılacaktır（按确认样生产）: [Evet/Hayır]
Değişiklik için yeniden yazılı onay gerekir: [Evet/Hayır]

Tedarikçi yetkilisi: [Ad / unvan / tarih / imza veya mesaj ref]
TedarikApp kayıt sorumlusu: [Ad / tarih]
Ürün Sahibi: [Ad / tarih / onay]
```

Tutanak, onay numunesi fotoğrafları ve ilgili teknik dosyanın sürüm özetiyle birlikte değiştirilemez bir kayıt olarak arşivlenir. Yeni revizyon eski tutanağın üzerine yazılmaz; yeni tutanak numarası alır ve önceki kayda bağlanır.

## 5. Numune bedeli mahsup kaydı örneği（样品费抵扣记录示例）

> Aşağıdaki rakamlar yalnız form kullanımını göstermek için kurgusal örnektir.

| Alan | Örnek kayıt |
|---|---|
| Talep no | `NUM-2026-0042` |
| Tedarikçi | `ÖRNEK Ev Tekstili Ltd.` |
| Ürün / varyant | `Banyo havlusu — krem beyaz` |
| Numune bedeli | `CNY 120` |
| Kargo bedeli | `CNY 180` |
| Ödenen toplam | `CNY 300` |
| Yazılı mahsup şartı | Aynı ürün için en az `300 adet` sipariş, `60 gün` içinde verilirse numune bedeli mahsup edilir |
| Mahsup edilecek | `CNY 120` numune bedeli |
| Mahsup edilmeyecek | `CNY 180` kargo bedeli |
| Tedarikçi teyidi | `WeChat mesaj ref: MSG-2026-042-07` |
| Sipariş no / tarih | `PO-2026-0118 / 2026-09-12` |
| Proforma satırı | `Sample fee deduction（样品费抵扣）: -CNY 120` |
| Kontrol hesabı | `CNY 120 - CNY 120 = CNY 0 kalan mahsup` |
| Durum | `Tam mahsup edildi（已全额抵扣）` |
| Kontrol eden | `[Ad / tarih]` |

Mahsup yalnız tedarikçinin yazılı teyidi ve ilgili sipariş/proforma satırı birlikte görülünce kapatılır. Kısmi mahsup varsa başlangıç, kullanılan ve kalan tutar ayrı alanlarda izlenir; kur dönüşümü gerekiyorsa kullanılan kur ve tarih ayrıca kaydedilir.

## 6. Kaynak notu

- Güncel standart tanımı: [ISO 2859-1:2026 — Sampling procedures for inspection by attributes](https://www.iso.org/standard/85464.html).
- Kamuya açık tarihsel tablo karşılaştırması: [U.S. Defense Logistics Agency ASSIST — MIL-STD-105E, Sampling Procedures and Tables for Inspection by Attributes](https://quicksearch.dla.mil/qaDocDetails.aspx?ident_number=35496). Kaynak iptal edilmiş tarihsel bir standarttır; güncel normatif kaynak olarak değil, örnek büyüklüğü ve Ac/Re değerlerini çapraz kontrol etmek için kullanılmıştır.

Bu kaynaklar formun kalite planlama dayanağıdır; tedarik sözleşmesi, ürün güvenliği testi veya hukuki uygunluk görüşü değildir.
