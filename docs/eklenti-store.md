# Chrome Web Store Yayın Paketi (İE#11 C5 — K38: Liste dışı/Unlisted)

> Zip: `extension/dist/tedarikapp-eklenti-1.0.0-chrome.zip` (CI her PR'da yeniden üretir;
> yükleme öncesi lokalde `cd extension && npm run zip`).
> Yayın türü: **Unlisted** — yalnız linki bilen kurabilir; mağaza aramasında görünmez.
> Sabit extension ID yayında oluşur → `EXTENSION_ALLOWED_ORIGINS` config'ine
> `chrome-extension://<ID>` yazılır (K30 CORS allowlist) ve SUNUCU-PROFILI'ne işlenir.

## Mağaza metinleri

**Ad:** Tedarikapp — Ürün Yakalama
**Kısa açıklama (132 krk sınırı):**
1688 ürün sayfasından tek tıkla Tedarikapp paneline ürün gönderin: ad, fiyat kademeleri, varyasyonlar ve görseller otomatik yakalanır.

**Ayrıntılı açıklama:**
Tedarikapp (Ürün Tedarik Asistanı) kullanıcıları içindir. detail.1688.com ürün
sayfasında eklenti simgesine tıklayın: ürün adı, fiyat/adet kademeleri, varyasyon
matrisi, görseller ve satıcı bilgisi önizlemede görünür; hedef liste seçin veya
Gelen Kutusu'na gönderin. Bağlantı, panelinizden ürettiğiniz kişisel token ile
kurulur; token yalnız tarayıcınızın eklenti deposunda saklanır.

Bu eklenti yalnız Tedarikapp paneli olan kullanıcılar için anlamlıdır (kapalı,
tek-işletme aracı — Unlisted yayın bunun içindir).

**Kategori:** Alışveriş · **Dil:** Türkçe

## İzin gerekçeleri (inceleme formu)

| İzin | Gerekçe |
|---|---|
| `storage` | Panel adresi + kişisel API token'ı yalnız yerelde saklanır |
| `activeTab` | Yalnız kullanıcı tıkladığında aktif sekmenin adresini okumak |
| `host: https://detail.1688.com/*` | Ürün verisini sayfanın kendi gömülü JSON'undan okumak (content script) |

Uzak kod YOK; analitik YOK; veri satışı YOK. Tek ağ hedefi kullanıcının kendi
panel adresidir (kullanıcı ayarı; Bearer token ile).

## Gizlilik formu özeti

- Toplanan veri: yok (ürün verisi kullanıcının KENDİ paneline gider, bize değil).
- Uzaktan kod çalıştırma: yok (seçici JSON'ı VERİDİR, kod değildir — K53).

## Yükleme adımları (yayıncı hesabında)

1. developer.chrome.com/dashboard → yeni öğe → zip'i yükle.
2. Görünürlük: **Unlisted**. Metinler yukarıdan; ekran görüntüsü: popup önizlemesi.
3. Yayın onayı sonrası ID'yi al → config `EXTENSION_ALLOWED_ORIGINS` + SUNUCU-PROFILI + bu dosyaya işle.
