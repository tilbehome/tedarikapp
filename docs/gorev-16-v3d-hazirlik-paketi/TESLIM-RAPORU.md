> İşlev: Görev #16 V3-D hazırlık paketi ile #15 şerh kapanışının teslim envanterini verir.  
> Faz: V3-D uygulama öncesi kabul ve İE#24 hazırlığında kullanılır.  
> Kapsam: iki tam #15 düzeltme dosyası, beş V3-D kalıcı malzeme dosyası ve doğrulama sonuçlarıdır.  
> Ölçüm: satır ve kayıt sayıları UTF-8 teslim dosyalarının son doğrulanan hâlinden alınmıştır.  
> Kapsam dışı: ince ekran şartnamesi, GTİP, mevzuat ve gümrük vergisi/oran hesabıdır.

# Görev #16 — V3-D Hazırlık Paketi Teslim Raporu

## Dosya envanteri

| # | Dosya | Teslim türü | Satır | Kayıt / kapsam |
|---:|---|---|---:|---|
| 1 | `teklif-turu-durum-makinesi.md` | #15B tam düzeltme dosyası | 155 | 15 geçiş; `alternative_available` ve `waiting_supplier` tek-kaynak uyumu doğrulandı |
| 2 | `portal-ekran-sartnameleri.md` | #15A tam düzeltme dosyası | 287 | 111/111 7B bağı korunarak 7B dışı tam 6 bağ `portal_anahtar_onerisi` alanına taşındı |
| 3 | `siparis-durum-makinesi.md` | 16A | 102 | 7 görünür/terminal aşama, 6 ileri geçiş, 9 engel kontrolü |
| 4 | `odeme-senaryolari.json` | 16B | 710 | 4 plan şablonu, 5 kanıt kilometre taşı, 12 ODM senaryosu, 57 beklenen kayıt satırı |
| 5 | `landed-cost-kalibrasyon.json` | 16C | 1.366 | 6 masraf türü, 3 dağıtım anahtarı, 16 LC vakası; 6 tuzaklı vaka |
| 6 | `cbm-matematik.json` | 16D | 482 | 4 taşıma tipi, 14 CBM vektörü; hacim/ağırlık ve yer-kaldı çıktıları |
| 7 | `rucu-pdf-metinleri.json` | 16E | 519 | 59 benzersiz `belge.rucu.*` sabit metni; TR/EN/ZH |

Toplam: **3.621 satır**. Bu toplam raporun kendisini içermez.

## #15 şerh kapanışı

- Kaynak `teklif-turu-durum-makinesi.md` zaten istenen iki 5B karşılığını taşıyordu; teslim kopyası byte düzeyinde aynı bırakıldı. `status.alternative` ve `status.unanswered` bağı bulunmadı.
- `portal-ekran-sartnameleri.md` içinde 7B'de karşılığı olmayan altı doğrudan görünür bağ şunlardır: `portal.field.product_code`, `portal.field.buyer_note`, `portal.filter.all`, `portal.filter.unanswered`, `portal.filter.invalid`, `portal.action.clear_local_draft`.
- Bu altı bağ yeni 7B anahtarı olarak eklenmedi; yalnız `portal_anahtar_onerisi` alanına taşındı. Kalan içerik ve 111/111 manifest değişmedi.
- #15 dosyalarına beş satırlık yeni özet eklenmedi; “başka hiçbir içerik değiştirilmez” kuralı öncelikli tutuldu.

## Doğrulama sonuçları

- Dört JSON dosyası UTF-8 ve geçerli JSON olarak ayrıştırıldı.
- ODM-001..012, LC-001..016 ve CBM-001..014 kimlikleri kesintisizdir; tüm iç kayıt kimlikleri senaryo içinde kesintisizdir.
- Landed-cost vakalarında her hesaplanan masrafın dağıtılan toplamı kaynak masrafı kuruşu kuruşuna tutar; LC-007 beklenen sert ret vakasıdır.
- 59 rücu metni benzersizdir; tümü `belge.rucu.*` ad alanındadır; 5B ve 7B ile çakışma sıfırdır.
- Rücu metinlerindeki TR/EN/ZH yer tutucu kümeleri birebirdir; boş dil alanı yoktur.
- Sipariş durum dosyasında yeni `status.*` anahtarı veya uygulama enum'u üretilmemiştir.
- Gümrük yalnız `CUSTOMS_SERVICE / Gümrük hizmet bedeli` masraf türü olarak yer alır; vergi/oran hesabı yoktur.

## Açık sorular ve kanıt durumu

1. `config/durumlar.json` hazırlık kaynaklarında bulunamadı. Gerçek durum kodu bağları bu dosya sağlanana kadar `null / kanıtlanmadı` tutuldu.
2. 20', 40' ve 40HC için taşıyıcıların yayımladığı teorik hacim/payload değerleri kaynaklıdır; 28/56/68 m³ ve 26.000 kg iç planlama limitleri ihtiyatlı kalibrasyon olup **kanıtlanmadı** işaretlidir. Rota/taşıyıcı teyidi gerekir.
3. DDP'den %20 ayrıştırma yalnız görevde istenen varsayılan kalibrasyondur; ürün bazında açık oran bunu ezer ve bu paket hukuki/vergi tespiti yapmaz.
4. Rücu belgesindeki talep ve hak saklı tutma cümleleri ticari Çince kalitesinde yazıldı; üretim öncesi şirketin tercih ettiği hukuk/ticaret dili uzmanı son kontrolü önerilir.

