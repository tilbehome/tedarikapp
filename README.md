<div align="center">

# 📦 tedarikapp

**1688.com Tedarik Listesi Yönetim Sistemi**

Panel Web Uygulaması · Chrome Eklentisi · Excel/PDF/HTML Çıktı Motoru

`PHP 8.1` · `Slim 4` · `MySQL` · `React 18 + Vite` · `Manifest V3`

🌐 Üretim: `tedarikapp.tilbehometoptan.com` · 🔒 Private · 📋 Durum: **Faz 0 — İş Emri #1 sahada**

</div>

---

## 🎯 Proje Nedir?

Çin'den (1688.com) **DDP sipariş** sürecini uçtan uca tek panelden yöneten bağımsız sistem:

> 1688'de gez → eklentiyle **tek tıkla ürünü yakala** → panelde **sipariş listesini kur** → firmaya **görsel gömülü Excel/PDF + video oynatan paylaşım linki** gönder → her ürünü **Verilecek → Verildi → Yolda → Geldi** adımlarıyla takip et.

Elle Excel doldurma, fiyat/görsel kopyalama, kur hesaplama ve "hangi ürün nerede kaldı" karmaşası tamamen ortadan kalkar. Tek seferlik değil, **sürekli operasyon aracıdır**.

## ✨ Özellikler

### 🧲 Ürün Yakalama (Chrome Eklentisi)
- 1688 ürün sayfasında tek tık: başlık, fiyat, **tüm SKU/varyasyon matrisi**, görseller, video, ürün ID, satıcı mağaza bilgisi
- Hibrit akış: varsayılan **Gelen Kutusu**'na düşer, istersen eklentiden hedef listeyi seç
- Tekrar-ekleme uyarısı (aynı 1688 ürünü ikinci kez eklenirken haber verir)
- Panel kapalıyken kuyrukta bekletir, bağlantı gelince gönderir
- 🧩 **Modüler parser mimarisi:** yeni site (Taobao, Alibaba…) = çekirdeğe dokunmadan yeni bir parser dosyası

### 📋 Sipariş Listeleri
- **Aktif / Pasif / Arşiv** sekmeleri, tek tıkla geçiş
- Liste kopyalama (tekrar siparişler), 30 günlük **çöp kutusu** (yanlış silmeye karşı)
- 💱 Kur listeye **kilitlenir** — eski listelerin TL değeri sonradan oynamaz
- Export sonrası bile serbest düzenleme: export bir **anlık görüntüdür**, liste yaşamaya devam eder
- Export geçmişi + **"çıktı güncel değil" rozeti** — firmaya asla eski dosya gitmez

### 🚚 Sipariş Takibi
- Ürün bazında durum makinesi: `Verilecek → Verildi → Yolda → Geldi` (backend'de zorlanır, atlama yok)
- Kargo/konteyner takip kodu, durum tarihçesi, liste ilerleme çubuğu, aktivite günlüğü

### 📤 Çıktılar
- **Excel (.xlsx):** mevcut sipariş formatıyla birebir — renkli Yuan/TL ve Dolar/TL alt sütunları, hücreye gömülü görseller, tıklanabilir linkler, altta **TOPLAM satırı**
- **PDF:** baskıya uygun, görselli, Türkçe karakter sorunsuz · **CSV:** düz veri
- **🔗 Paylaşım Linki:** firmaya tek link — girişsiz açılır, ürün kartları + **gömülü video oynatıcı**, her zaman canlı güncel, arama motorlarına kapalı

### 🔐 Güvenlik
- **Zorunlu 2FA (TOTP)** + kurtarma kodları · Argon2id şifre hash'i
- Artan bekleme + IP kilidi, CSRF koruması, güvenlik başlıkları (CSP/HSTS), hash'li API token
- `storage/` webden tamamen kapalı; sırlar yalnızca `.env`'de
- 🧙 **Kurulum sihirbazı:** WordPress tarzı tek seferlik kurulum (gereksinim denetimi → DB → migration → admin+2FA), sonra kendini kalıcı kilitler; güncellemeler tek tık migration

## 🏗️ Mimari

```
┌─ Chrome Eklentisi ─┐      ┌─────── Panel (React SPA) ───────┐
│ parser_1688.js     │ JSON │ Listeler · Gelen Kutusu · Takip │
│ popup · kuyruk     ├─────►│ Ayarlar · Aktivite · Çöp Kutusu │
└────────────────────┘  ▲   └───────────────┬─────────────────┘
                        │ Bearer token      │ REST API (CSRF, oturum)
                ┌───────┴───────────────────▼────────┐
                │  PHP 8.1 (Slim 4) — cPanel hosting │
                │  Durum makinesi · bcmath para      │
                │  Export motoru (xlsx/pdf/csv)      │
                │  Görsel indirme → public/media     │
                └───────┬────────────────────────────┘
                        ▼                         ▼
                     MySQL                🌍 Paylaşım sayfası
                                          (sunucu render, video, noindex)
```

Sunucu ortamı gerçek raporla doğrulanmıştır (bkz. docs/04 §7): tüm dış istekler cURL, `vendor/` lokalde kurulup taşınır, para asla float değil (`DECIMAL` + bcmath).

## 🗺️ Yol Haritası

| Faz | Kapsam | Teslimat | Sürüm |
|---|---|---|---|
| ✅ **Faz 0** | Belgeler, kararlar (K1–K17), repo iskeleti | 14 dosyalık belge seti · İş Emri #1 | — |
| 🔜 **Faz 1** | Panel çekirdeği: kurulum sihirbazı, 2FA giriş, listeler, elle ürün, durum takibi | Hosting'de çalışan panel | `v0.1.0` |
| ⏳ **Faz 2** | Excel/PDF/CSV export + paylaşım linki | Firmaya gerçek sipariş iletimi | `v0.2.0` |
| ⏳ **Faz 3** | Chrome eklentisi + Gelen Kutusu | Tek tıkla ürün yakalama | `v0.3.0` |
| ⏳ **Faz 4** | İstatistik, arşiv derinleştirme, opsiyonel oto-kur, cila | Üretim sürümü | `v1.0.0` |

Ayrıntılar ve kabul kriterleri: [docs/05-yol-haritasi.md](docs/05-yol-haritasi.md)

## 📚 Belge Haritası

| 📄 Belge | İçerik |
|---|---|
| [CLAUDE.md](CLAUDE.md) | 🤖 **Geliştirme anayasası** — Claude Code için bağlayıcı kurallar (stack sınırları, para/durum kuralları, veri sözleşmeleri) |
| [CHANGELOG.md](CHANGELOG.md) | 🕘 Sürüm geçmişi (SemVer) |
| [docs/00-calisma-protokolu.md](docs/00-calisma-protokolu.md) | 👥 Roller (PM/Ürün Sahibi/Geliştirici), iş emri döngüsü, GitHub süreci |
| [docs/01-vizyon-omurga.md](docs/01-vizyon-omurga.md) | 🎯 Amaç, kullanıcılar, sistem omurgası, ilkeler |
| [docs/02-moduller-kapsam.md](docs/02-moduller-kapsam.md) | 🧩 M1–M8 modül dökümü + Excel çıktı şablonu |
| [docs/03-kullanici-akislari.md](docs/03-kullanici-akislari.md) | 🔄 Adım adım kullanım senaryoları |
| [docs/04-teknik-tasarim.md](docs/04-teknik-tasarim.md) | ⚙️ Stack, veritabanı, API, durum makinesi, veri sözleşmesi, güvenlik, dizin ağacı, sunucu ortamı |
| [docs/05-yol-haritasi.md](docs/05-yol-haritasi.md) | 🗺️ Faz 0–4, teslimatlar, kabul kriterleri |
| [docs/06-test-ve-kabul.md](docs/06-test-ve-kabul.md) | 🧪 Test katmanları, faz kabul testleri, regresyon |
| [docs/07-deploy-runbook.md](docs/07-deploy-runbook.md) | 🚀 Kurulum sihirbazı, sürüm çıkarma, geri alma, yedekleme |
| [docs/08-risk-ve-karar-kaydi.md](docs/08-risk-ve-karar-kaydi.md) | ⚠️ Risk kaydı (R1–R8), karar günlüğü (K1–K17), fikir havuzu |
| [docs/09-arayuz-tasarim.md](docs/09-arayuz-tasarim.md) | 🎨 Tasarım ilkeleri, ekran envanteri (E1–E9 / P1 / X1–X2) |
| [docs/10-api-sozlesmesi.md](docs/10-api-sozlesmesi.md) | 🔌 API sözleşmesi — yanıt zarfı, hata kodları, sayfalama, uç bazlı gövdeler |

## 🤝 Çalışma Modeli

```
Claude (PM) ──İŞ EMRİ #N──► Bünyamin ──► Claude Code (Geliştirici)
     ▲                                        │
     └──── GitHub PR denetimi ◄── ÇIKTI RAPORU ┘
```

- Aynı anda **tek aktif iş emri** · her iş emri kendi dalında (`is-emri-N-slug`) + PR
- Commit standardı: `tip(kapsam): açıklama` · Faz sonu sürüm tag'i
- Kapsam disiplini: iş emrinde olmayan hiçbir şey yazılmaz; fikirler rapora önerilir
- Tek gerçek kaynak `docs/` — kararlar günlüğe, değişiklikler aynı PR'da belgeye işlenir

## ⚡ Kurulum (özet)

1. cPanel'de MySQL veritabanı + kullanıcı oluştur
2. Release zip'ini yükle, siteye gir → 🧙 kurulum sihirbazı gerisini yönetir (gereksinim denetimi, `.env`, migration, admin + 2FA)
3. Bitti — sihirbaz kendini kilitler. Ayrıntı: [docs/07-deploy-runbook.md](docs/07-deploy-runbook.md)

---

<div align="center">

**Tilbe Home** iç operasyon aracı · Tüm mimari kararlar için 📖 [karar günlüğü](docs/08-risk-ve-karar-kaydi.md)

</div>
