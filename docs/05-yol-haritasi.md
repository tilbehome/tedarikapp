# tedarikapp — Yol Haritası

> Durum: v1.0 — ONAYLANDI (16.08.2026)
> Her faz, bir önceki bitip ONAYLANMADAN başlamaz. Her fazın sonunda çalışan, test edilmiş bir teslimat vardır.

## Faz 0 — Proje Tanımı ve İstişare (şu an)

**Teslimat:** 5 belgelik doc seti, repo'da `docs/` altında.
**Kabul kriteri:** Bünyamin tüm belgeleri okuyup onaylar; açık sorular kapanır.

## Faz 1 — Panel Çekirdeği

**Kapsam:** M1 (giriş, ayarlar, kur, kategoriler) + M2 (sipariş listeleri) + M3 (elle ürün ekleme, arama/filtre, toplu işlem) + M5 (durum takibi).
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

## Çalışma Düzeni

- Uygulama Claude Code ile lokalde geliştirilir, GitHub'a (`tilbehome/tedarikapp`) push edilir, hosting'e deploy edilir.
- Her faz başında: fazın detay görev listesi çıkarılır → onay → geliştirme → test → teslim.
- Belgeler yaşayan belgelerdir; her fazda alınan kararlar ilgili MD'ye işlenir.
