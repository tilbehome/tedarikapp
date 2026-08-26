# Chrome Web Store Yayın Paketi (İE#11 C5 — K38: Liste dışı/Unlisted)

> Zip: `extension/dist/tedarikapp-eklenti-2.0.1-chrome.zip` (CI her PR'da yeniden üretir;
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

## Kurulum: CORS allowlist adımı (İE#11 EK-3 — ATLANIRSA EKLENTİ BAĞLANMAZ)

Eklenti panele `chrome-extension://<id>` origin'iyle gelir; panel bu origin'i
tanımıyorsa tarayıcı isteği bloklar ve popup "bağlantı yok" der. `EXTENSION_ALLOWED_ORIGINS`
**boş gelir** (güvenli varsayılan) — kurulumda doldurulmalıdır:

1. **Paketlenmemiş kurulum (geliştirme/deneme):** `chrome://extensions` → Geliştirici modu →
   "Paketlenmemiş öğe yükle" → `extension/dist/chrome-mv3` klasörü. Karttaki **Kimlik**
   değerini kopyala.
2. Sunucuda `config.php`'ye (veya `.env`) yaz:
   `'EXTENSION_ALLOWED_ORIGINS' => 'chrome-extension://<kimlik>'`
3. **Store yayınından sonra** kalıcı kimlik farklıdır: virgülle ekle veya geçiciyi değiştir:
   `chrome-extension://<gecici>,chrome-extension://<store-kimligi>`
4. Panelde Ayarlar > Güvenlik'ten token üret → eklentinin ayar ekranına panel adresi +
   token gir → "Kaydet ve bağlan" → durum "bağlı ✓" olmalı.

> Not: Bu ayar dosya yapılandırmasındadır (DB'de değil) — sır sınıfı değildir ama
> allowlist'tir; wildcard yazılmaz (K30).
