# 34-R TESLİM RAPORU

## Teslim özeti

- `rol-gorunurluk-matrisi.json` sürümü `34-R-v2` olarak güncellendi.
- `sizinti-test-seti.json` sürümü `34-R-v2` olarak güncellendi; `matris_bagi` değeri `rol-gorunurluk-matrisi.json@34-R-v2` oldu.
- Karar/çıktı kapsamı değişen hücre sayısı **16**; değişmeyen hücre sayısı **662**.
- EN ve ZH dosyalarına dokunulmadı.
- Operatör ödeme tutarı veya sipariş toplam tutarı adıyla ayrı bir matris alanı bulunmadığından rol dipnotuna **“Operatör ödeme tutarı alanları V3-D'de matrise eklenir.”** notu eklendi.

## Değişen hücreler

Karar ve çıktı kapsamı birlikte hücre değeri kabul edilmiştir. Aşağıdaki 16 hücre dışında karar/çıktı hücresi değişmemiştir.

| # | Alan | Rol | Eski | Yeni |
|---:|---|---|---|---|
| 1 | `kaynak.platform` | `ithalatci` | `gizle []` | `goster [html, pdf, excel, csv]` |
| 2 | `kaynak.platform` | `cinli_uretici_ciktisi` | `gizle []` | `goster [pdf, excel, csv, whatsapp-ozet]` |
| 3 | `kaynak.url` | `ithalatci` | `gizle []` | `goster [html, pdf, excel, csv, whatsapp-ozet]` |
| 4 | `kaynak.url` | `cinli_uretici_ciktisi` | `gizle []` | `goster [pdf, excel, csv, whatsapp-ozet]` |
| 5 | `kaynak.ilan_no` | `ithalatci` | `gizle []` | `goster [html, pdf, excel, csv]` |
| 6 | `kaynak.ilan_no` | `cinli_uretici_ciktisi` | `gizle []` | `goster [pdf, excel, csv, whatsapp-ozet]` |
| 7 | `kaynak.tedarikci_kimligi` | `ithalatci` | `gizle []` | `goster [html, pdf, excel, csv]` |
| 8 | `kaynak.tedarikci_linki` | `ithalatci` | `gizle []` | `goster [html, pdf, excel, csv]` |
| 9 | `ic_maliyet.kaynak_birim_fiyat` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 10 | `ic_maliyet.ddp_birim_maliyet` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 11 | `ic_maliyet.ddp_toplam_maliyet` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 12 | `ic_maliyet.kar_tutari` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 13 | `ic_maliyet.kar_marji` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 14 | `ic_maliyet.hedef_satis_fiyati` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 15 | `ic_maliyet.satis_fiyati` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |
| 16 | `ic_maliyet.yurtici_referans_fiyati` | `sahip_operator` | `goster [html, pdf, excel, csv]` | `gizle []` |

Değişen alanların `neden_bu_rol_gormez` açıklamaları rol bazlı kararlara göre yenilendi. `kirmizi_cizgi_alanlari` rol kapsamlı kayıtlara dönüştürüldü: sekiz `ic_maliyet.*` alanı dört dış rol için; beş `kaynak.*` alanı yalnız `musteri` ve `anonim_anahtarsiz_iskelet` için kırmızı çizgidir. `MTR-02` bu rol kapsamını doğrulayacak şekilde güncellendi; operatör kısıtı, `ithalatci_yaniti.*` görünürlüğü ve kaynak rol dağılımı için `MTR-05`–`MTR-07` eklendi.

## SZ seti revizyonu

- Görünür duruma dönen `kaynak.* × ithalatci/cinli_uretici_ciktisi` birleşimlerine ait **33 eski negatif vaka silindi**.
- `sahip_operator × 8 ic_maliyet alanı × 4 çıktı` için **32 negatif vaka eklendi**.
- Operatör tokenıyla iç kopya isteme senaryosu için **1 rol yükseltme vakası eklendi**; beklenen sonuç `403` ve sekiz alanın tüm katmanlarda yokluğudur.
- Net değişim: **33 silinen / 33 eklenen**; toplam **273 vaka** korundu.
- Kimlikler kesintisiz olarak `SZ-001`–`SZ-273` aralığında yeniden verildi.
- Operatör negatif vaka toplamı: **33**.
- Rol kapsamlı zorunlu kırmızı çizgi çaprazı: **210** vaka.
- Kaynak rol/kapsam negatifleri: **67** vaka (`musteri`/anonim 50, ithalatçı WhatsApp kapsamı 4, Çinli rakip tedarikçi 10, Çinli HTML paneli yokluğu 3).

## Programatik doğrulama çıktısı

```text
Matris sürümü                         34-R-v2
SZ sürümü                             34-R-v2
Matris bağı                           rol-gorunurluk-matrisi.json@34-R-v2
Alan / rol / hücre                    113 / 6 / 678
Değişen / değişmeyen hücre            16 / 662
Boş veya geçersiz hücre               0
Kaynak × ithalatçı göster             5/5
Kaynak × Çinli göster/gizle           3/2
Kaynak × müşteri+anonim gizle         10/10
Operatör × ic_maliyet gizle           8/8
Operatör × ithalatci_yaniti göster    13/13
Operatör SZ                           33
Kırmızı çizgi dış rol göster          0
Toplam SZ                             273
Kesintisiz SZ aralığı                 SZ-001..SZ-273
Ödeme/sipariş toplam alanı eşleşmesi  0 (V3-D notu mevcut)
JSON sözdizimi                        4/4 geçerli
```

## Değişmezlik kontrolü

| Dosya | SHA-256 | Sonuç |
|---|---|---|
| `portal-metinleri-musteri-en.json` | `61c31178e704d3b209f3b03e4477421404f552d8474a2fb4b76c17dfcb854b29` | Önceki teslimle aynı |
| `portal-metinleri-musteri-zh.json` | `7049e7889974d7c566cbccc2a1214fbc8f439c1ff60fffc32696b9471c8bbb04` | Önceki teslimle aynı |

