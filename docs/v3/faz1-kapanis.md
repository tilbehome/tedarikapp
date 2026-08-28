# FAZ 1 KAPANIŞ ÖZETİ (28 Ağustos 2026)

> Faz 1 KAPANDI. Bu belge dört dilimin dökümü, sürüm zinciri, kalıcı kararlar
> dizini ve V3-B'ye devredilenlerin listesidir. Ayrıntı için her satır kendi
> kaynağına referans verir; burada tekrar edilmez.

## 1. Faz 1 ne teslim etti

| Dilim | Kapsam | Ana kaynak |
|---|---|---|
| **1 — Temel hat** (İE#18/19) | Panel iskeleti, liste/ürün durum makinesi, para disiplini (bcmath · `DECIMAL(12,2)` · kur listeye kilitli), Excel/PDF/paylaşım çıktıları, eklenti v1 | docs/04 §5, docs/08 K4/K14/K24/K48 |
| **2 — Temiz kurulum** (İE#20) | `/setup` sihirbazı sekiz arıza durumunu adıyla söyleyen teşhis motoruna dönüştü; kuyruk sertleştirildi (kira token'ı, kalp atışı, ölü mektup) | docs/07, docs/08 K37/K45 |
| **3 — Panel v3 ekranları** (İE#21 B1–B14) | Keşif havuzu, liste komuta merkezi, ürün çekmecesi, Gelen Kutusu deste modu, Paylaş penceresi, kilit ekranı, belge şablonları, skor v1, kategori ağacı, sürümlü çeviri belleği, marka kiti | docs/is-emirleri/IE21-DURUM.md |
| **4 — Eklenti v2** (İE#21 A1–A8) | Sayfa içi panel (kapalı shadow DOM), on durumlu akış, 16+ alan önizlemesi, mükerrerde dört seçenek, kalıcı kuyruk, prominent disclosure | docs/v3/hazirlik/eklenti-e2e-senaryo-katalogu.md |

## 2. Sürüm zinciri

| Sürüm | Tarih | Neyi kapattı |
|---|---|---|
| v0.9.5 | 19 Ağu | Faz 1 iskeleti — ilk çalışan uçtan uca hat |
| v0.11.1 | 21 Ağu | Canlı kurulum; ilk saha turu |
| v1.0.0-rc1…rc8 | 23–27 Ağu | Sekiz aday tur: D1–D11 saha bulguları + dış denetim P0 paketi (F-01/02/07/08/13/14/16/32) |
| **v1.0.0** | 27 Ağu | İlk tam sürüm. Kabul: gerçek ürünlerle gerçek liste ve gerçek paylaşım bağlantısı |
| **v1.0.1** | 28 Ağu | D12: çeviri cron istemez · kanonik üç dil · eklenti 2.0.3 görünüm-TR |

Eklenti zinciri: 1.x (v1 yakalama) → 2.0.0/2.0.1 (sayfa içi panel) → 2.0.2 (27 Ağu
saha düzeltmeleri) → **2.0.3** (görünüm Türkçeleştirmesi).

## 3. Kalıcı kararlar dizini

Tam metin `docs/08-risk-ve-karar-kaydi.md`; burada Faz 1 boyunca DEĞİŞMEYEN ve
sonraki fazları bağlayan kararlar listelenir.

| Karar | Özü |
|---|---|
| K4 · K48 | Kur listeye KİLİTLENİR; TL değerleri DB'ye yazılmaz, her seferinde kilitli kurla hesaplanır |
| K14 · K24 | Para float ile tutulmaz/hesaplanmaz; bcmath + string taşıma |
| K23 | Migration'lar ileri yönlüdür; veri dönüştürülmez, yalnız eklenir |
| K34 | Panel token'ı content script'e ULAŞMAZ |
| K37 | Kurulum kilidi fail-closed; sihirbaz her arızayı adıyla söyler |
| K51 | Paylaşım kapısında sabit hata mesajı; satır içi script yok |
| K54 | Çeviri DAİMA öneridir; onaylı elle düzeltme hiçbir turla ezilmez |
| K55 → **K88** | Belgede referans satırı: "Çince satır" değil KAYNAK DİLİ satırı |
| K56 | Çeviri katman sırası: sözlük → önbellek → LLM |
| K57 | Revizyon harfi İÇERİKTEN türer; snapshot'a giren her alan revizyon alanıdır |
| K61 | Belge üretimi AĞ BEKLEMEZ; yalnız önbellekteki metni kullanır |
| K62 · K82 | Paylaşım erişim anahtarının süresi YOKTUR |
| K81 | Tasarlanmış çok dillilik: ekranda üç dil, PDF'te tek satır |
| K84 (+EK) | Taban PHP 8.2; uygulaması ÖNCE sunucu işidir (MultiPHP 8.3) |
| K85 | Kodla çelişen yorum/belge AYNI commit'te düzeltilir |
| **K86** | Çeviri cron İSTEMEZ; tetikleyiciler çeşitlenir, cron yalnız fazlalıktır |
| **K87** | Kanonik üç dil TR+EN+ZH, platform bağımsız; kaynak dil çevrilmez |
| **K89** | İş modeli pusulası: siteler ürün bilgisi madenidir, alım yapılmaz, sitedeki fiyat karar verisi değildir |
| **K90** | Eklentide çeviri değil GÖRÜNÜM Türkçeleştirmesi; LLM yok, veri orijinal gider |

## 4. V3-B'ye devredilenler

| # | Madde | Neden Faz 1'de değil |
|---|---|---|
| 1 | Kural rozeti + geri alma (`E2E-PNL-20`) | Özellik yazılmadı; test özellikten önce kodlanamaz |
| 2 | Sözlük CSV içe aktarımı (`E2E-PNL-50/51`) | Aynı gerekçe |
| 3 | Bildirim toast deseni (28 Ağu kararı) | Panel genelinde tek desen ister; V3-B kapsamında tasarlanacak |
| 4 | TCMB otomatik kur (F44) | Onaylı V3 kararı; K4/K48 ile çelişmez ama ayrı iş emri |
| 5 | Firma portalı (V3-C) | Kapsamı Faz 1'in dışında |

## 5. İE#22'ye devredilen teknik borç

`docs/v3/hazirlik/ie22-on-analiz.md` EK-2'de dört madde: MultiPHP 8.3 kurulum
adımı (K84-EK) · medya yedeği (F-03) · MariaDB işinin koşula bağlanması (K5) ·
taban 8.2'ye çıkınca "PHP 8.1 uyum" işinin ruleset'ten düşürülmesi.

## 6. Faz 1'in bıraktığı ders

Sekiz aday turun tamamı **saha koşumunda** bulundu; hiçbiri testlerden çıkmadı.
Tekrar eden kök neden tekti: **aynı gerçeği iki ayrı yoldan okuyan iki yüzey.**
Popup ile sayfa içi panel bağlantıyı ayrı okuyordu (D5); sayaç ile işçi kuyruğu
ayrı okuyordu (D9); sınav ile ekran çeviriyi ayrı okuyordu (D11b); toplu çevir
düğmesi ile kuyruk işçisi birbirinden habersizdi (D12). Her seferinde çözüm aynı
oldu: **tek kaynak.** Faz 2'ye giren kural budur — bir bilgi iki yerde
hesaplanıyorsa, ikisinden biri er geç yanlış olur ve yanlış olan hep kullanıcının
gördüğüdür.
