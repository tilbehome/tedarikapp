# tedarikapp — Risk Kaydı ve Karar Günlüğü

> Durum: YAŞAYAN BELGE — her fazda güncellenir.

## 1. Risk Kaydı

| # | Risk | Olasılık | Etki | Önlem |
|---|---|---|---|---|
| R1 | 1688 sayfa yapısını değiştirir, eklenti parser'ı bozulur | Yüksek | Orta | Parser ayrı modül; elle giriş her zaman yedek yol; test sayfa setiyle hızlı doğrulama |
| R2 | 1688/alicdn görsel hotlink'i veya erişimi kapatır | Orta | Orta | Görseller yakalandığı an sunucuya indirilir; liste asla dış linke bağımlı kalmaz |
| R3 | Hosting kısıtları (yazma izni, PHP sürüm değişikliği) | Orta | Yüksek | Runbook'ta kurulum kontrol listesi; sunucu raporu scripti repo'da tutulur, sorun anında yeniden koşulur |
| R4 | Veri kaybı (DB/görsel) | Düşük | Çok yüksek | Günlük cron yedeği + aylık geri yükleme denemesi |
| R5 | Kur/fiyat hesap hatası → yanlış siparişe para bağlanır | Düşük | Çok yüksek | Kur listeye kilitlenir; hesap tek fonksiyonda, birim testli; export öncesi panelde toplam kontrol satırı |
| R6 | Paylaşım linki istenmeyen kişilere yayılır | Orta | Düşük | Uzun rastgele token, tek tıkla iptal/yenileme |
| R7 | Tek geliştirici/tek karar verici — bilgi tek yerde | Orta | Orta | Her karar bu belgeye işlenir; belgeler repo'da; iş emirleri arşivlenir |
| R8 | Kapsam şişmesi (yeni fikirler fazları uzatır) | Yüksek | Orta | Yeni fikirler "Faz 4+ havuzu"na yazılır, aktif iş emri kapsamı değişmez |

## 2. Karar Günlüğü (ADR)

| # | Tarih | Karar | Gerekçe |
|---|---|---|---|
| K1 | Ağu 2026 | Platform: web uygulaması (masaüstü değil) | Paylaşım linki doğal üretimi, her cihazdan erişim, kurulumsuz |
| K2 | 16 Ağu 2026 | Veri yakalama: Chrome eklentisi + panel senkron (hibrit) | Resmi 1688 API'si erişilemez; scraping API ücretli ve B2B fiyatları eksik dönebiliyor; eklenti kullanıcı oturumuyla çalışır, engellenmez, bedava |
| K3 | 16 Ağu 2026 | Ana birim: sipariş listesi (ürün değil) | Gerçek operasyon liste bazlı: firmaya liste iletilir, liste takip edilir |
| K4 | 16 Ağu 2026 | Kur: elle girilir, listeye kilitlenir | Otomatik kur bağımlılık yaratır; kilitleme eski listelerin TL değerini korur — **ONAYLANDI 16 Ağu** |
| K5 | 16 Ağu 2026 | Stack: PHP 8.1 (Slim 4) + MySQL + React (Vite) | Sunucu raporuyla doğrulandı; mevcut hosting'de sıfır ek kurulum — **ONAYLANDI 16 Ağu** (uygulama kararları Ürün Sahibi tarafından PM'e delege) |
| K6 | 16 Ağu 2026 | Görseller sunucuya indirilir, hotlink yapılmaz | R2 riski + 1688 hotlink koruması |
| K7 | 16 Ağu 2026 | Adres: tedarikapp.tilbehometoptan.com | Subdomain kuruldu, sunucu raporu alındı |
| K8 | 16 Ağu 2026 | Dış istekler yalnızca cURL; e-postasız şifre kurtarma; vendor lokalde kurulup yüklenir | Sunucu kısıtları: allow_url_fopen ve mail() kapalı, composer yok |
| K9 | 16 Ağu 2026 | Çalışma modeli: Claude=PM, Bünyamin=Ürün Sahibi/Koordinatör, Claude Code=Geliştirici; iş emri döngüsü | 00-calisma-protokolu.md |
| K10 | 16 Ağu 2026 | Eklenti yakalama hibrit: varsayılan Gelen Kutusu + eklentiden opsiyonel hedef liste seçimi | Toplu ürün gezerken kutuya at, tek ürün eklerken direkt listeye — hız + esneklik |
| K11 | 16 Ağu 2026 | Paylaşım linkinde fiyatlar her zaman görünür; fiyat gizleme özelliği kapsam dışı | Ürün Sahibi kararı — ihtiyaç yok, gereksiz karmaşıklık eklenmez |
| K12 | 16 Ağu 2026 | Dış mimari görüşten alınanlar: SKU/varyasyon matrisi yakalama, 1688 ürün ID + tekrar uyarısı, mağaza adı/linki, orijinal başlık saklama, takip kodu alanı, DB indeksleri | Ürün Sahibi'nin paylaştığı dış öneri incelendi; operasyona gerçek değer katanlar alındı |
| K13 | 16 Ağu 2026 | Dış görüşten REDDEDİLENLER: FastAPI/PostgreSQL/Redis/Docker/S3/Next.js stack'i, gümrük-lojistik maliyet ayrıştırması, çoklu rol/onay, WebSocket, CI/CD | Paylaşımlı cPanel sunucuda çalışmaz / DDP modelinde gereksiz / solo operasyonda aşırı mühendislik. K5 stack kararı geçerli |
| K14 | 16 Ağu 2026 | AI bağlam mühendisliği benimsendi: repo köküne CLAUDE.md anayasası; durum makinesi geçiş kuralları backend'de zorlanır; eklenti→API JSON şeması sabitlendi; para asla float değil (DECIMAL+bcmath); para/kur/durum fonksiyonları test-first | İkinci dış bilgi paylaşımından; PM→Claude Code modelinde sapma ve halüsinasyonu yapısal olarak engeller |
| K15 | 16 Ağu 2026 | Liste yaşam döngüsü genişletildi: Aktif/Pasif/Arşiv görünümleri; listeler export sonrası dahil her zaman düzenlenebilir (export = anlık görüntü); export geçmişi + "çıktı güncel değil" rozeti; silme = 30 gün çöp kutusu; aktivite günlüğü; panel ve Excel'de TOPLAM satırı | Ürün Sahibi talebi + PM eklemeleri (kaza koruması ve firma iletişim güvenliği) |

Yeni kararlar bu tabloya eklenir; bir karar değişirse silinmez, üzeri çizilip yeni satır açılır (tarihçe korunur).

## 3. Fikir Havuzu (Faz 4+)

Kapsamı şişirmemek için buraya park edilir: otomatik kur çekme, çoklu tedarikçi karşılaştırma, Taobao/Alibaba.com desteği (platform alanı ve modüler parser altyapısı şimdiden hazır), maliyet-kâr analizi ve gümrük/lojistik kalem ayrıştırması (TilbeSync ile veri alışverişi), Çince başlık otomatik çeviri, WhatsApp'a hazır mesaj şablonu.
