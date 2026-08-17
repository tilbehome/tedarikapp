# tedarikapp — Yol Haritası

> Durum: v1.1 — İE#9 güncellemesi (17.08.2026): Faz 0 TAMAMLANDI; Faz 1 geliştirmesi
> İE#3–İE#8 ile bitti, **kapanış İE#9 sağlamlaştırma sprintinin kabulüne bağlı**
> (İE#9 kabulü = üretim kurulumunun ÖN ŞARTI).
> Her faz, bir önceki bitip ONAYLANMADAN başlamaz. Her fazın sonunda çalışan, test edilmiş bir teslimat vardır.

## Faz 0 — Proje Tanımı ve İstişare ✅ (tamamlandı — 16.08.2026)

**Teslimat:** Belge seti (docs/00–10 + kökte README, CLAUDE.md, CHANGELOG.md), repo'da yayında.
**Kabul kriteri:** Bünyamin tüm belgeleri okuyup onaylar; açık sorular kapanır.

## Faz 1 — Panel Çekirdeği 🔄 (geliştirme bitti; kapanış = İE#9 kabulü)

**Kapsam:** M1 (giriş, ayarlar, kur, kategoriler) + M2 (sipariş listeleri) + M3 (elle ürün ekleme, arama/filtre, toplu işlem) + M5 (durum takibi).
**Gerçekleşme:** İE#3 çekirdek · İE#4 auth+2FA · İE#5 kurulum sihirbazı · İE#6 veri katmanı · İE#7 K33 sunucu uyumu · İE#8 React paneli · **İE#9 sağlamlaştırma (K37) — kapanış sprinti**.
**Teslimat:** Kurulum sihirbazıyla hosting'e kurulmuş, 2FA'lı girişi yapılan, örnek Excel'deki listenin sıfırdan oluşturulabildiği panel.
**Kabul kriterleri:**
- Kurulum sihirbazı temiz sunucuda uçtan uca çalışıyor ve bitince kendini kilitliyor; giriş şifre + 2FA ile yapılıyor.
- Mevcut ÜRÜN TEDARİK LİSTESİ.xlsx içeriği panele girilebiliyor, TL fiyatları doğru hesaplanıyor.
- Telefondan tüm işlemler rahat yapılabiliyor.
- Durumlar tek tıkla ilerletilebiliyor, tarihçesi tutuluyor.

## Faz 2 — Export ve Paylaşım

**Kapsam:** M6 (Excel/PDF/CSV) + M7 (paylaşım linki).
**Teslimat:** Firmaya gerçek bir siparişin iletilebildiği sürüm.
**Kabul kriterleri:**
- Excel çıktısı örnek dosyayla aynı formatta, görseller gömülü açılıyor.
- PDF baskıda düzgün görünüyor, Türkçe karakterler sorunsuz.
- Export geçmişi kaydediliyor; son çıktıdan sonra değişen liste "çıktı güncel değil" rozeti gösteriyor; Excel'de TOPLAM satırı doğru.
- Paylaşım linki telefonda açılıyor, videolar oynuyor.
- Paylaş menüsü: WhatsApp hazır mesajı ve e-posta taslağı doğru içerikle (liste adı + link) açılıyor.

> Bu fazın sonunda araç gerçek işte kullanılmaya başlar (ürün girişi henüz elle).

## Faz 3 — Chrome Eklentisi

**Kapsam:** M4 + Gelen Kutusu akışı.
**Teslimat:** Chrome'a yüklenen eklenti + panelde Gelen Kutusu.
**Kabul kriterleri:**
- En az 10 farklı gerçek 1688 ürün sayfasında başlık/fiyat/görsel/video doğru çekiliyor.
- Tek tık → Gelen Kutusu → listeye atama akışı 30 saniyenin altında.
- Panel kapalıyken toplanan ürünler kaybolmuyor (kuyruk).

## Faz 4 — İyileştirme ve Cila

**Kapsam:** M8 istatistikler, liste kopyalama iyileştirmeleri, arşiv/arama derinleştirme, opsiyonel otomatik kur çekme, günlük yedek otomasyonu, performans/tasarım cilası.
**Kabul kriteri:** Bir aylık gerçek kullanımdan çıkan geri bildirim listesi kapanmış olur.

> **Faz dışı ön şart (İE#4 REV2):** off-site (sunucu dışı) yedek, Faz 4 fikri olmaktan çıkarılıp **canlıya alma ön şartı** yapılmıştır — kurulumu docs/07 §7'de. Ayrıca her PR'da CI koşar (K26); deploy manuel kalır (K13).

## Çalışma Düzeni

- Uygulama Claude Code ile lokalde geliştirilir, GitHub'a (`tilbehome/tedarikapp`) push edilir, hosting'e deploy edilir.
- Her faz başında: fazın detay görev listesi çıkarılır → onay → geliştirme → test → teslim.
- Belgeler yaşayan belgelerdir; her fazda alınan kararlar ilgili MD'ye işlenir.
