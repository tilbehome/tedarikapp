# Uçtan uca testler (Playwright) — İE#13 Blok E / F22

Bu süit **çalışan bir kuruluma** bakar: gerçek PHP sunucusu, gerçek MySQL, derlenmiş
panel. SQLite kullanılmaz (E1) — birim/entegrasyon testlerinden farkı budur.

## Kapsam (E2)

| Dosya | Senaryo |
|---|---|
| `tests/01-panel.spec.ts` | 2FA'sız giriş · yanlış şifre reddi · liste oluştur → ürün ekle → liste durumunu ilerlet |
| `tests/02-export.spec.ts` | Excel + PDF üret ve **indir** (dosya gerçekten iniyor mu) · çıktı geçmişi kaydı |
| `tests/03-paylasim.spec.ts` | Paylaşım linki üret → **girişsiz** bağlamda aç → iptal → sabit 404 (K51) · uydurma token 404 |
| `tests/04-gelen-kutusu.spec.ts` | Bearer'lı sahte yakalama → Gelen Kutusu → detay çekmecesi → listeye taşı → üründe gör |

## Kapsam DIŞI (E3 — bilinçli sınır)

**Chrome eklentisinin kendisi E2E'de sürülmez.** Playwright'ın MV3 desteği kırılgandır
(service worker yaşam döngüsü, `chrome://extensions` etkileşimi, kalıcı profil zorunluluğu)
ve elde edilen test, gerçek hatadan çok kendi kırılganlığını raporlar. Eklenti tarafı şöyle
kapsanır:

- **Parser:** `extension/tests/*.test.ts` — gerçek `window.context` yapılarından türetilmiş
  fixture'larla (canlı 1688 isteği yok, K35).
- **Sözleşme:** `04-gelen-kutusu.spec.ts` eklentinin KULLANDIĞI uçları (Bearer + capture v2)
  uçtan uca sürer; yani eklenti ile panel arasındaki anlaşma her koşuda sınanır.
- **Arayüz:** popup/mini panel kabulü manuel listeyle yapılır (K35: otomatik UI testi yok).

## Seçici disiplini (F43 — zorunlu)

Bir Playwright seçicisi yazmadan önce **gerçek arayüz kaynağına bakılır** (ilgili
`.tsx` bileşeni ya da sunucu şablonu). Düğme ve etiket metni tahmin EDİLMEZ.

Tercih sırası:

1. **Kalıcı test id** (`data-testid`) — henüz yaygın değil; kritik akış öğelerine
   eklenmesi V3-B arayüz turunda değerlendirilecek.
2. **Tam metin** — `getByLabel('Şifre', { exact: true })`, `getByRole('button', { name: 'Ürünü ekle' })`.
3. **Rol + ad** — yalnız yukarıdakiler mümkün değilse.

Ek kurallar (hepsi CI'da canlı hatadan öğrenildi):

- Panel aynı veriyi **masaüstü tablosu + mobil kart** olarak basar; `.first()` CSS ile
  GİZLİ kopyayı seçebilir. `gorunen()` yardımcısı (`filter({ visible: true }).first()`)
  kullanılır.
- Gevşek etiket eşleşmesi komşu öğelere takılır: "Şifre" ↔ "Şifreyi göster",
  "Adet" ↔ "Koli içi adet" → `exact: true`.
- Kip'e göre değişen düğme adları (yeni/düzenleme) testte sabitlenmez; hangi kipte
  koşulduğu belliyse o kipin adı yazılır.
- Sayfa geçişi bekleniyorsa `expect(page).toHaveURL(...)` ile doğrulanır; bir sonraki
  adım erken çalışıp yanıltmasın.

## CI

`.github/workflows/ci.yml` → **E2E (Playwright)** job'ı: MySQL 8.4 servisi → composer +
panel derlemesi → `.env` → `bin/migrate.php` → `bin/user-create.php --no-totp` →
`php -S 127.0.0.1:8099 -t public e2e/router.php` → `npm test`. Hata hâlinde sunucu
günlüğü basılır ve Playwright HTML raporu artefakt olarak yüklenir.

## Lokalde koşum

```bash
# 1) MySQL ayakta ve .env doğru olmalı (bkz. docs/07)
php bin/migrate.php
php bin/user-create.php --email=e2e@tedarikapp.test --password=e2e-cok-gizli-sifre --no-totp
cd frontend && npm ci && npm run build && cd ..
php -S 127.0.0.1:8099 -t public e2e/router.php &

# 2) Süit
cd e2e
npm ci
npx playwright install chromium
E2E_BASE_URL=http://127.0.0.1:8099 npm test
```

> `--no-totp` bayrağı **yalnız test tohumlaması** içindir; üretimde kullanıcı kurulum
> sihirbazından açılır ve ikinci faktör kurulur.

`e2e/router.php` yalnız PHP yerleşik sunucusu içindir: var olan statik dosyaları olduğu
gibi servis eder (aksi hâlde `/panel/assets/*.js` SPA fallback'ine düşer). Üretimde
Apache/LiteSpeed + `public/.htaccess` bu işi yapar.
