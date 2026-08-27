# GÖREV #22 — V3-M Çıktı Dil Kapsama Denetim Seti Teslim Raporu

**Teslim tarihi:** 26 Ağustos 2026  
**Çalışma sınırı:** Salt üretim; TedarikApp reposuna yazım ve canlı platform isteği yok.  
**Teslim:** 3 çalışma dosyası + bu rapor

## 1. Dosyalar ve sayımlar

| Dosya | İçerik | Sayım |
|---|---|---:|
| `dil-kapsama-matrisi.md` | Çıktı × dil matrisi ve tam 5B envanteri | 5 çıktı × 3 dil = 15 kombinasyon; 185 5B satırı; 14 aday |
| `dil-denetim-vektorleri.json` | Pozitif/negatif locale denetimi | 70 vektör (`DL-001..DL-070`) |
| `bicim-kurallari.md` | Tarih/saat/para/sayı/CSV/sıralama/font | 3 locale profili; Noto Sans SC kapsam ve fallback kapısı |
| `TESLIM-RAPORU.md` | Kaynak, doğrulama, açık karar ve hash | Bu rapor |

Çalışma dosyaları toplamı: **2705 satır**, yaklaşık **11227 kelime**, **105015 bayt**.

## 2. Kaynak doğrulaması

- 5B `cikti-terimleri.json`: **185/185** benzersiz anahtar, **15/15** `status.*`.
- Grup dağılımı: `col.*=59`, `doc.*=18`, `filter.*=25`, `footnote.*=15`, `kpi.*=18`, `msg.*=12`, `status.*=15`, `total.*=23`.
- KT-032..034: TR/EN/ZH Excel'de başlık, sütun, durum, finans özeti, uyarı ve dipnotların tek dilde olması doğrulandı.
- KT-035/K55: `DM-016` özgün değerinin `高硼硅玻璃油壶 550ml` olarak korunması doğrulandı.
- KT-036..037: PDF ve paylaşım önizlemelerinde tek dil, doğru ZH glifi ve doğru liste/revision bağı doğrulandı.
- KT-038: eksik metrikte boş/“mevcut değil”; `0` veya sahte skor üretmeme kuralı kullanıldı.
- 7B `portal-metinleri.json`: kademeli fiyat başlangıç/bitiş/birim fiyat alanlarının TR/EN/ZH semantiği doğrulandı; bunlar **5B'ye eklenmiş sayılmadı**.

## 3. Vektör dağılımı

| Kategori | Sayı |
|---|---:|
| `all_accepted_statuses` | 15 |
| `baseline_output_locale` | 15 |
| `channel_placeholder_positive` | 3 |
| `csv_en_positive` | 1 |
| `csv_tr_positive` | 1 |
| `csv_zh_positive` | 1 |
| `currency_language_positive` | 1 |
| `currency_language_trap` | 1 |
| `date_language_positive` | 1 |
| `date_language_trap` | 1 |
| `date_sort_positive` | 1 |
| `empty_value_positive` | 3 |
| `k55_original_mutation_trap` | 1 |
| `k55_original_positive` | 3 |
| `mixed_language_trap` | 6 |
| `null_leak_trap` | 3 |
| `numeric_sort_positive` | 1 |
| `original_allowlist_abuse_trap` | 1 |
| `tier_header_flattening_trap` | 3 |
| `tier_header_positive` | 3 |
| `unknown_status_trap` | 1 |
| `zh_font_fallback_positive` | 1 |
| `zh_font_no_fallback_trap` | 1 |
| `zh_font_subset_positive` | 1 |
| `zh_text_collation_positive` | 1 |
| **Toplam** | **70** |

Zorunlu tuzaklar kapsandı: karışık dil sızıntısı, ZH'de TR ay adı, EN'de TR/₺ sayı biçimi, `null` sızıntısı ve PDF'te üç satırlı kademeli başlığın tek satıra düşmesi. Ek olarak K55 mutasyonu, orijinal allowlist suistimali, bilinmeyen durum, CSV kaçışı ve ZH font kapsamı sınandı.

## 4. Doğrulama beyanı

- JSON parse: **başarılı** (`jq empty` çıkış kodu `0`).
- 5B TR/EN/ZH yer tutucu kümeleri eşit: **185/185**.
- `DL-001..DL-070` kesintisiz ve benzersiz: **başarılı**.
- Her vektörde çıktı türü, dil, liste durumu, boş alan senaryosu ve fixture: **70/70**.
- Bütün vektörlerin `list_status_key` değeri kabul edilmiş 15 anahtardan: **başarılı**.
- 5 çıktı × 3 dil temel kombinasyonu: **15/15**.
- Yeni durum anahtarı: **0**; kasıtlı `status.completed` yalnız negatif fixture ve `STATUS_KEY_NOT_IN_5B` beklenen hatasıdır.
- Yeni kesin 5B terimi: **0**; 14 boşluk açıkça `5B'ye aday`dır.
- Repo yazımı: **yok**.

## 5. Açık kararlar

1. **K57/K61:** Erişilen kabul kaynaklarında numara–kural eşlemesi bulunamadı; **kanıtlanmadı**. Görev metnindeki “—” ve üç satırlı başlık kuralları doğrudan kabul girdisi olarak uygulandı.
2. **14 aday metin:** 5B'ye eklenmeli veya mevcut 5B anahtarına bağlanmalıdır; bu paket ekleme yapmaz.
3. **Locale varsayılanları:** Tarih/saat deseni, para sembol konumu, CSV delimiter/BOM ve collation PM onayı ister; test vektörleri mevcut öneriyi deterministikleştirir.
4. **ZH font fallback:** Paketli tam font ailesi/sürümü ve lisans dağıtımı uygulama öncesi sabitlenmelidir.
5. **Durum sırası:** 5B'nin dosya sırasının iş akışı önceliği olduğu kanıtlanmadı; ayrı PM kararı gerekir.

## 6. Dosya bütünlükleri

| Dosya | SHA-256 |
|---|---|
| `dil-kapsama-matrisi.md` | `1a4c7b568514c17f5671f84846e54b0b8c2aa1812a0e6f5e468b0acd63cd0a7d` |
| `dil-denetim-vektorleri.json` | `4937aa3356ab998102941bade71d0a08d9256e66a30b7eae1b01304fcd95d937` |
| `bicim-kurallari.md` | `8ce9cfe43144755cc8d508c9d08b8cf96217ec2d886e6124e1336c7161e547b2` |
