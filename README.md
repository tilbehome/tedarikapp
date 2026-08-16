# tedarikapp

1688.com tedarik listesi yönetim sistemi — panel web uygulaması + Chrome eklentisi.

Çin'den DDP sipariş verilecek ürünleri tek panelden yönet: eklentiyle 1688'den tek tıkla ürün yakala, sipariş listeleri oluştur, firmaya görsel gömülü Excel / PDF ve video oynatabilen paylaşım linkiyle ilet, siparişin her ürününü **Verilecek → Verildi → Yolda → Geldi** adımlarıyla takip et.

Üretim adresi: `tedarikapp.tilbehometoptan.com`

## Belgeler

| Belge | İçerik |
|---|---|
| [docs/00-calisma-protokolu.md](docs/00-calisma-protokolu.md) | Roller (PM / Ürün Sahibi / Geliştirici), iş emri döngüsü, GitHub süreci — **projenin anayasası** |
| [docs/01-vizyon-omurga.md](docs/01-vizyon-omurga.md) | Amaç, kullanıcılar, sistem omurgası, ilkeler |
| [docs/02-moduller-kapsam.md](docs/02-moduller-kapsam.md) | M1–M8 modül dökümü + Excel çıktı şablonu |
| [docs/03-kullanici-akislari.md](docs/03-kullanici-akislari.md) | Adım adım kullanım senaryoları |
| [docs/04-teknik-tasarim.md](docs/04-teknik-tasarim.md) | Stack, veritabanı, API, eklenti mimarisi + doğrulanmış sunucu ortamı |
| [docs/05-yol-haritasi.md](docs/05-yol-haritasi.md) | Faz 0–4, teslimatlar, kabul kriterleri |
| [docs/06-test-ve-kabul.md](docs/06-test-ve-kabul.md) | Test katmanları, faz kabul testleri, regresyon listesi |
| [docs/07-deploy-runbook.md](docs/07-deploy-runbook.md) | Kurulum, sürüm çıkarma, geri alma, yedekleme |
| [docs/08-risk-ve-karar-kaydi.md](docs/08-risk-ve-karar-kaydi.md) | Risk kaydı, karar günlüğü (ADR), fikir havuzu |

Repo kökündeki [CLAUDE.md](CLAUDE.md), Claude Code için bağlayıcı geliştirme anayasasıdır (stack sınırları, para/durum kuralları, veri sözleşmeleri).

## Durum

**Faz 0 — tamamlanmak üzere.** Tüm tasarım kararları onaylandı (karar günlüğü K1–K11), belgeler v1.0 KESİN. Aktif iş: İŞ EMRİ #1 (repo iskeleti ve belge setinin kurulması).
