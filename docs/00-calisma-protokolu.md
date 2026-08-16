# tedarikapp — Çalışma Protokolü

> Durum: v1.0 — yürürlükte
> Bu belge, projede kimin ne yaptığını ve işlerin nasıl aktığını tanımlar. Tüm taraflar için bağlayıcıdır.

## 1. Roller

| Rol | Kim | Sorumluluk |
|---|---|---|
| **Ürün Sahibi** | Bünyamin | Kararları verir, iş emirlerini iletir, teslimatları kabul eder, gerçek kullanımda test eder |
| **PM (Proje Yöneticisi)** | Claude (sohbet) | İş emirlerini yazar, çıktı raporlarını değerlendirir, belge setini yönetir |
| **Geliştirici** | Claude Code | İş emirlerini uygular, kodu yazar/test eder, ÇIKTI RAPORU üretir |

Bilgi akışı: **PM → Bünyamin → Claude Code → Bünyamin → PM**. Claude Code ile PM doğrudan konuşmaz; köprü her zaman Bünyamin'dir.

## 2. İş Emri Düzeni

- Her iş, numaralı bir **İş Emri** ile gelir (`İŞ EMRİ #N`). İş emri; hedef, yapılacaklar, kapsam dışı, kabul kriterleri, test ve teslim bölümlerini içerir.
- Her iş emri `docs/is-emirleri/is-emri-NN.md` olarak repoya kaydedilir.
- Claude Code, iş emrinin **kapsamı dışına çıkmaz**. Kapsam dışı bir ihtiyaç görürse işi durdurmaz; rapora "PM kararı gerekli" notu düşer.
- Ön koşul sağlanmamışsa iş başlamaz; eksik, Bünyamin'e bildirilir.

## 3. Dal ve Commit Standardı

- Varsayılan dal: `main`. Faz 1'den itibaren her iş emri kendi dalında çalışılır: `is-emri-NN-kisa-ad` → iş bitince `main`'e merge.
- Commit formatı (Conventional Commits): `<tip>(<alan>): <açıklama>` — tipler: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`.
- Repoya **asla sır girmez**: şifre, API anahtarı, veritabanı bilgisi yalnızca `.env` dosyasında durur; `.env` gitignore'dadır, şablonu `.env.example`'dır.

## 4. ÇIKTI RAPORU Şablonu

Her iş emri tamamlandığında Claude Code aşağıdaki şablonla rapor üretir. Rapor, Bünyamin aracılığıyla PM'e iletilir.

```markdown
# ÇIKTI RAPORU — İş Emri #NN
Tarih: GG.AA.YYYY · Dal: <dal> · Commit: <hash + mesaj>

## 1. Yapılanlar
İş emrindeki maddelere birebir karşılık gelecek şekilde, madde madde ne yapıldığı.

## 2. Kabul Kriterleri Durumu
- [x] Karşılanan kriter — kanıt/açıklama
- [ ] Karşılanamayan kriter — nedeni

## 3. Test Çıktıları
Çalıştırılan testler ve sonuçları (komut çıktısı, ekran görüntüsü referansı).

## 4. Sapmalar ve Notlar
İş emrinden sapılan noktalar, gerekçeleri; PM kararı bekleyen hususlar.

## 5. Sonraki Adım Önerisi
Geliştiricinin gözünden sıradaki mantıklı adım (bağlayıcı değil, öneri).
```

## 5. Belge Yönetimi

- Belgeler **yaşayan belgelerdir**: her fazda alınan kararlar ilgili MD dosyasına işlenir, karar tarihiyle not düşülür.
- Belge setinin haritası kökteki [README.md](../README.md) dosyasındadır.
- Belgelerde içerik değişikliği yalnızca PM onayıyla yapılır; Claude Code yazım hatası dahi görse rapora not düşer, kendisi düzeltmez (iş emri açıkça yetki vermedikçe).
