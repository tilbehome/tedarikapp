# Tedarik App Marka Paketi

Bu paket Chrome eklentisi, web paneli, ürün/listeler, Excel-PDF çıktıları ve kurumsal iletişim için tek bir turuncu-beyaz görsel sistem içerir.

## Dosyalar

- `chrome/icon-16.png`, `icon-32.png`, `icon-48.png`, `icon-128.png`: Chrome Extension Manifest ikonları.
- `favicon/favicon.ico`: 16, 32 ve 48 piksel katmanlı gerçek ICO favicon.
- `favicon/favicon.svg`: Modern tarayıcılar için ölçeklenebilir favicon.
- `favicon/apple-touch-icon.png`: iOS ana ekran ikonu, 180 × 180.
- `favicon/android-chrome-192.png`, `android-chrome-512.png`: PWA/Android ikonları.
- `logo/tedarik-app-logo-horizontal.svg|png`: Açık/beyaz zemin için ana logo.
- `logo/tedarik-app-logo-horizontal-simple.svg|png`: Alt metinsiz sade yatay logo.
- `logo/tedarik-app-logo-horizontal-dark.svg|png`: Koyu zemin için ters logo.
- `logo/tedarik-app-logo-compact.svg|png`: Dar/dikey alanlar için logo.
- `logo/tedarik-app-mark-mono-dark.svg|png`: Tek renk ve siyah-beyaz kullanım.
- `source/tedarik-app-mark.svg`: Ana vektör amblem.
- `brand-guide/Tedarik-App-Marka-Kimligi-Rehberi.pdf`: 12 sayfalık uygulama kılavuzu.
- `brand-guide/MARKA-KIMLIGI.md`: Metin tabanlı marka kuralları.
- `brand-guide/SES-VE-METIN-STANDARTLARI.md`: Arayüz metni ve marka sesi.
- `design-system/tokens.css`, `tokens.json`: Panel ve eklenti tasarım değişkenleri.
- `design-system/tailwind-preset.js`: Tailwind uyumlu tema ön ayarı.
- `design-system/status-map.json`: Tedarik akışı durumlarının TR/EN/ZH eşlemesi.
- `chrome-web-store/`: Store ikonu, 440 × 280 small promo, 1400 × 560 marquee ve 1280 × 800 ekran şablonu.
- `ui-assets/`: Popup üst alanı, boş durum, ürün placeholder, başarı/hata/senkron durumları ve splash.
- `documents/`: Liste başlığı, footer, kapak, filigran, HTML çıktı şablonu ve çıktı tema verisi.
- `social/`: 1200 × 630 OG görseli ve profil ikonu.
- `email/`: Kurumsal e-posta imza şablonu.
- `developer/`: Panel bileşen demosu, favicon kodu ve uygulama kontrol listesi.

## Kurumsal renkler

- Sinyal turuncusu: `#FF6B00`
- Amber: `#FFB000`
- Canlı mercan: `#FF4D3D`
- Kömür: `#1F2530`
- Sıcak beyaz: `#FFFDF8`
- Yardımcı gri: `#6B7280`

## Chrome manifest örneği

```json
{
  "icons": {
    "16": "icons/icon-16.png",
    "32": "icons/icon-32.png",
    "48": "icons/icon-48.png",
    "128": "icons/icon-128.png"
  },
  "action": {
    "default_icon": {
      "16": "icons/icon-16.png",
      "32": "icons/icon-32.png"
    }
  }
}
```

## Web favicon örneği

```html
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
```

Amblem çevresinde en az amblem genişliğinin `%12`si kadar boşluk bırakın. İkonu esnetmeyin, döndürmeyin veya farklı renklerle yeniden boyamayın.

## En hızlı uygulama sırası

1. `logo/` ve `favicon/` dosyalarını uygulamanın ortak varlık klasörüne alın.
2. `design-system/tokens.css` dosyasını panel ve eklenti stillerinden önce yükleyin.
3. Panel durumlarını `design-system/status-map.json` ile eşleyin.
4. Chrome ikon yollarını `chrome/manifest-icons-snippet.json` örneğine göre Manifest V3 dosyasına ekleyin.
5. Liste ve PDF çıktılarında `documents/` şablonlarını kullanın.
6. Son kontrol için `developer/UYGULAMA-KONTROL-LISTESI.md` dosyasını izleyin.
