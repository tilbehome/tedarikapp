# tedarikapp — Vizyon ve Sistem Omurgası

> Durum: v1.0 — ONAYLANDI (16.08.2026)
> Repo: github.com/tilbehome/tedarikapp
> Tarih: 16 Ağustos 2026

## 1. Problem

Çin'den (1688.com) DDP sipariş verilecek ürünler şu an Excel'de elle tutuluyor. Her ürün için fiyat, görsel, link, kur çevirimi elle kopyalanıyor; liste firmaya Excel/PDF olarak iletiliyor; video paylaşılamıyor; sipariş verildikten sonra hangi ürün nerede (verildi / yolda / geldi) takip edilemiyor. Bu iş tek seferlik değil, sürekli tekrar eden bir operasyon.

## 2. Çözüm

**tedarikapp**: Tek panelden yönetilen, Chrome eklentisiyle senkronize çalışan bir web uygulaması.

Omurga üç parçadan oluşur:

1. **Panel (web uygulaması)** — Sipariş listelerinin oluşturulduğu, ürünlerin yönetildiği, durumların takip edildiği, export'ların alındığı merkez. Hosting sunucusunda barınır, her cihazdan (özellikle mobilden) erişilir.
2. **Chrome eklentisi** — 1688 ürün sayfasındayken tek tıkla ürünün başlığını, fiyatını, ana görselini ve varsa videosunu panele gönderir. Kullanıcı yalnızca adet/kategori gibi kendi bilgilerini girer.
3. **Paylaşım katmanı** — Her sipariş listesi için firmaya iletilecek çıktılar: örnek dosya formatına uygun Excel (görsel gömülü), PDF ve videoların da izlenebildiği herkese açık (tokenli) HTML link.

## 3. Kullanıcılar

| Kullanıcı | Rol | Erişim |
|---|---|---|
| Bünyamin (admin) | Listeleri oluşturur, ürün ekler, durum günceller, export alır | Panele giriş (tek kullanıcı) |
| Tedarikçi firma | Kendisine iletilen listeyi görüntüler (görsel + video dahil) | Sadece paylaşım linki, salt okunur, giriş yok |

## 4. Temel İlkeler

- **Hız her şeyden önce gelir.** Ürün ekleme akışı saniyeler sürmeli; eklenti bunun için var. Elle giriş her zaman yedek yol olarak kalır (eklenti çalışmazsa iş durmaz).
- **Mobile-first.** Panel telefonda uygulama kalitesinde çalışmalı (Tilbe Home standartı).
- **Excel çıktısı mevcut formatla birebir uyumlu.** Firma alıştığı formatı görmeye devam eder: NO, ÜRÜN GÖRSELİ, KATEGORİ, ÜRÜN ADI, ÜRÜN DETAY, ÜRÜN LİNKİ, MİKTAR, 1688 FİYATI (Yuan/TL), DDP FİYATI (Dolar/TL).
- **Bağımsız sistem.** Başka hiçbir projeye (WooCommerce, TilbeOS vb.) bağımlı değildir; kendi veritabanı ve kendi API'si vardır.
- **Modüler platform mimarisi.** Sistem WordPress mantığında genişleyebilir: her kaynak site (bugün 1688, yarın Taobao/Alibaba/Temu) ayrı bir parser modülüdür. Yeni site eklemek çekirdeğe dokunmadan yeni bir modül yazmaktır; veri modeli (`platform` alanı) ve eklenti mimarisi buna hazırdır.
- **Kur manuel girilir.** Güncel Yuan→TL ve Dolar→TL kurunu admin girer, sistem yalnızca çarpar. (Otomatik kur ileriki faz için opsiyoneldir.)

## 5. Kapsam Dışı (v1 için)

- Çoklu kullanıcı / rol yönetimi
- 1688 dışındaki platformlardan (Taobao, Alibaba.com) veri çekme — eklenti mimarisi buna hazır olur ama v1'de yalnızca 1688
- Otomatik sipariş verme / 1688 hesabıyla entegrasyon
- Muhasebe ve ödeme takibi (ileride ayrı modül olarak değerlendirilebilir)

## 6. Belge Haritası

Tam ve güncel belge haritası repo kökündeki [README.md](../README.md)'dedir. Set: kökte CLAUDE.md (geliştirme anayasası) + CHANGELOG.md; `docs/` altında 00 (çalışma protokolü), 01 (bu belge), 02 (modüller), 03 (akışlar), 04 (teknik tasarım), 05 (yol haritası), 06 (test/kabul), 07 (deploy runbook), 08 (risk/karar kaydı), 09 (arayüz), 10 (API sözleşmesi) + `is-emirleri/` arşivi.
