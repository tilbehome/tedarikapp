/* eslint-env serviceworker */

/**
 * PANEL SERVICE WORKER (V3-B Blok E).
 *
 * DÖRT KURAL, dördü de emrin şartı ve her birinin somut bir gerekçesi var:
 *
 * 1. KAPSAM YALNIZ `/panel/`. Bu dosya `public/panel/sw.js` olarak yayımlanır
 *    ve tarayıcı bir SW'nin kapsamını KONUMUNDAN türetir. Kök dizine
 *    (`/sw.js`) konsaydı kapsam TÜM SİTE olurdu ve `/liste/{token}` altındaki
 *    OTURUMSUZ paylaşım sayfası da önbelleğe alınabilir hâle gelirdi. Firmaya
 *    giden sayfanın tarayıcıda kalıcılaşması K62/K82 ile bağdaşmaz.
 *
 * 2. YALNIZ KABUK VARLIKLARI ÖNBELLEĞE ALINIR (HTML/JS/CSS/ikon). `/api/*`
 *    HER ZAMAN ağa gider ve HİÇBİR koşulda önbellekten yanıtlanmaz. Sebebi
 *    tek cümleyle: BAYAT KUR YANLIŞ PARADIR. Bir liste ekranı önbellekten 12
 *    saat önceki kuru gösterirse, kullanıcı yanlış TL karşılığına bakarak
 *    sipariş verir. Aynısı liste durumu için de geçerlidir.
 *
 * 3. ÖNBELLEK ADI DERLEME DAMGASINA BAĞLIDIR. v0.11.2'de başka bir dalın panel
 *    derlemesi canlıya çıkmış ve kimse fark etmemişti; `BUILD.json` damgası o
 *    dersin ürünü. Önbellek adı o damgadaki commit'i taşır — yeni sürüm
 *    yüklendiğinde ad değişir, eski önbellek silinir. Sabit bir ad kullansaydık
 *    kullanıcı güncellemeden sonra da ESKİ paneli görmeye devam ederdi.
 *
 * 4. ÇEVRİMDIŞINDA VERİ UYDURULMAZ. Ağ yoksa kabuk açılır ve "çevrimdışısınız"
 *    denir; liste, kur ya da ürün verisi GÖSTERİLMEZ. Boş bir ekran, yanlış
 *    bir ekrandan iyidir.
 */

const DAMGA_YOLU = '/panel/BUILD.json';
const ONBELLEK_ONEKI = 'tedarikapp-kabuk-';

/** Kabuk varlıkları: uygulamanın açılması için gereken en küçük küme. */
const KABUK_VARLIKLARI = [
  '/panel/',
  '/panel/index.html',
  '/panel/favicon.svg',
  '/panel/site.webmanifest',
];

/**
 * Önbellek adı — derleme damgasındaki commit'ten üretilir.
 *
 * Damga okunamazsa `bilinmiyor` kullanılır: SW yine çalışır ama her kurulumda
 * aynı ada yazar. Bu, damgasız bir derlemede güncellemenin gecikmesi demektir;
 * kabul edilebilir, çünkü `bin/release.php` damgasız paketi zaten REDDEDİYOR.
 */
async function onbellekAdi() {
  try {
    const yanit = await fetch(DAMGA_YOLU, { cache: 'no-store' });
    if (!yanit.ok) return ONBELLEK_ONEKI + 'bilinmiyor';
    const damga = await yanit.json();

    return ONBELLEK_ONEKI + String(damga.commit || 'bilinmiyor');
  } catch {
    return ONBELLEK_ONEKI + 'bilinmiyor';
  }
}

self.addEventListener('install', (olay) => {
  olay.waitUntil(
    (async () => {
      const ad = await onbellekAdi();
      const onbellek = await caches.open(ad);
      // Tek tek eklenir: bir varlık 404 olursa TAMAMI düşmesin. `addAll`
      // atomiktir ve tek eksik dosya kurulumu tamamen başarısız kılardı.
      await Promise.all(
        KABUK_VARLIKLARI.map((yol) => onbellek.add(yol).catch(() => undefined)),
      );
      // Yeni sürüm beklemeden devralır: kullanıcı sekmeyi kapatıp açmak
      // zorunda kalmasın.
      await self.skipWaiting();
    })(),
  );
});

self.addEventListener('activate', (olay) => {
  olay.waitUntil(
    (async () => {
      const guncel = await onbellekAdi();
      const adlar = await caches.keys();
      // ESKİ ÖNBELLEKLER SİLİNİR. Silinmezse disk şişer ve daha kötüsü, bir
      // hata durumunda eski kabuk geri dönebilir.
      await Promise.all(
        adlar
          .filter((ad) => ad.startsWith(ONBELLEK_ONEKI) && ad !== guncel)
          .map((ad) => caches.delete(ad)),
      );
      await self.clients.claim();
    })(),
  );
});

self.addEventListener('fetch', (olay) => {
  const istek = olay.request;

  // Yalnız GET önbelleklenir; POST/PUT/DELETE her zaman ağa gider.
  if (istek.method !== 'GET') return;

  const url = new URL(istek.url);

  // BAŞKA ORİJİN: dokunulmaz (CDN görselleri, TCMB vb.).
  if (url.origin !== self.location.origin) return;

  // API: HER ZAMAN AĞ. Önbellek yedeği bile YOKTUR — bayat veri göstermektense
  // hata göstermek doğrudur.
  if (url.pathname.startsWith('/api/')) return;

  // KAPSAM DIŞI YOLLAR: paylaşım sayfası (`/liste/...`), medya, kurulum.
  // Tarayıcı kapsam gereği bunları zaten buraya getirmez; bu kontrol ikinci
  // bir emniyet kilididir — SW yanlışlıkla başka bir kapsamla kaydedilirse
  // paylaşım sayfası yine de önbelleğe girmez.
  if (!url.pathname.startsWith('/panel/')) return;

  olay.respondWith(
    (async () => {
      const ad = await onbellekAdi();
      const onbellek = await caches.open(ad);

      try {
        // ÖNCE AĞ: panel güncelse hep güncel sürüm görünür.
        const yanit = await fetch(istek);
        if (yanit.ok && yanit.type === 'basic') {
          await onbellek.put(istek, yanit.clone());
        }

        return yanit;
      } catch {
        // AĞ YOK: önbellekten kabuk. Gezinme isteğiyse index.html verilir
        // (istemci tarafı yönlendirme oradan devam eder).
        const kayitli = await onbellek.match(istek);
        if (kayitli) return kayitli;

        if (istek.mode === 'navigate') {
          const kabuk = await onbellek.match('/panel/index.html');
          if (kabuk) return kabuk;
        }

        return new Response(
          '<!doctype html><meta charset="utf-8"><title>Çevrimdışı</title>'
          + '<p style="font:14px system-ui;padding:2rem">Bağlantı yok. '
          + 'Panel açıldığında veriler yeniden yüklenecek.</p>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
        );
      }
    })(),
  );
});
