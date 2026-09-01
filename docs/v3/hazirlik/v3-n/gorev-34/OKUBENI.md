# Görev #34 — OKUBENİ

Bu klasör V3-N için öneri statüsündeki whitelist görünürlük matrisini, sızıntı kabul kümesini ve 27B müşteri portalı metinlerinin EN/ZH karşılıklarını içerir. PM matrisi denetler; Ürün Sahibi kırmızı çizgileri onaylar.

## Dosyalar

| Dosya | İşlev |
|---|---|
| `rol-gorunurluk-matrisi.json` | 113 alan × 6 rol için whitelist ve çıktı kapsamı |
| `sizinti-test-seti.json` | 273 negatif vaka; kırmızı çizgi çaprazları ve ek güvenlik vakaları |
| `portal-metinleri-musteri-en.json` | 27B’nin 477 anahtarlık ticari İngilizce karşılığı |
| `portal-metinleri-musteri-zh.json` | Aynı 477 anahtarın sadeleştirilmiş Çince karşılığı |
| `34-TESLIM-RAPORU.md` | Sayımlar ve doğrulama sonuçları |

## Esaslar

- Bir alan rol hücresinde açıkça `goster` veya `maskele(<kural>)` değilse serializer’a girmez; blacklist yasaktır.
- Rol istemciden değil, sunucuda token’dan çözülür.
- `gizle`; alan adının, değerinin, `null`/`—` yer tutucusunun, gizli sütununun ve metadata’sının dahi bulunmamasıdır.
- Çinli üretici K15 gereği panel değildir; yalnız rol-süzgeçli çıktı setidir.
- Durum etiketleri elle yazılmaz; 5B `status.*` terim anahtarından çözülür.
- Tüm SZ vakaları yeşil olmadan V3-N kapanmaz.

## Kırmızı çizgi yorumu

- İç `ic_maliyet.ddp_*` alanları bütün dış rollerde gizlidir. İthalatçının kendi `ithalatci_yaniti.ddp_*` alanları ayrı rol yanıtıdır.
- İç satış/hedef fiyatları dış rollerde gizlidir. `musteri_teklifi.birim_satis_fiyati` müşteriye hazırlanmış ayrı teklif alanıdır.
- Eski mimaride dış role açılabilen kaynak linkleri, Görev #34’ün daha yeni kırmızı çizgi kararı nedeniyle bütün dış rollerde kapatılmıştır.

## Kaynak ↔ dosya izlenebilirliği

| Kaynak | Hedef |
|---|---|
| Portal mimari raporu + demo | Rol/alan/çıktı envanteri → matris |
| Görev 25 A/B/C/E | Müşteri, ithalatçı, üretici çıktı sınırları ve terimler → matris/çeviri |
| Görev 26 §9.2 | HTML, API, hydration, cache ve dışa aktarım görünmezliği → SZ |
| Görev 27A/27B/27C | Hukuki sınırlar, 477 TR anahtar, KT-N kabul koşulları → çeviri/SZ |
| V3-C `rfq-alan-sozlesmesi.json` | RFQ alan adları → matris |
| 5B `cikti-terimleri.json` | `status.*` tek kaynak → matris |
| Görev #34 K12/K14/K15 | Tahsilat yok, müşteri izolasyonu, üretici paneli yok → tüm teslim |

## Bilinçli sapmalar

- Eski üretici portalı önerisi K15 nedeniyle uygulanmadı; yalnız çıktı rolü bırakıldı.
- Eski kaynak-link görünürlüğü yeni kırmızı çizgi kararı nedeniyle kapatıldı.
- Kod, şema veya migration önerisi eklenmedi.

## PM’e soru

1. K1–K19’un kanonik PM karar defteri repoda bulunamadı. Teslim görev metnindeki K12/K14/K15 ile mevcut bağlayıcı belgeleri uyguladı; erişilemeyen kararlar uydurulmadı.
2. `sahip_operator` kişisel veri sınırı nihai PM denetiminde teyit edilmelidir.
3. K15 nedeniyle Çinli üretici çıktısında HTML kapalıdır; etkileşimsiz HTML belge de çıktı sayılacaksa PM açıkça işaretlemelidir.
