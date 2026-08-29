import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const kok = resolve(__dirname, '../..');
const sw = readFileSync(resolve(kok, 'public/sw.js'), 'utf8');
const kayit = readFileSync(resolve(kok, 'src/lib/swKayit.ts'), 'utf8');

/**
 * V3-B BLOK E — SERVICE WORKER KAPSAM BEKÇİSİ.
 *
 * Bir SW'nin yanlış kapsamla kaydedilmesi, ÇALIŞMA ZAMANINDA fark edilmesi en
 * zor hatalardandır: her şey çalışır, yalnız fazladan bir şey de önbelleğe
 * alınır. Bu testin sınadığı somut felaket şudur — `/liste/{token}` altındaki
 * OTURUMSUZ paylaşım sayfasının kullanıcının tarayıcısında kalıcılaşması.
 * K62/K82 erişim anahtarına ömür tanımıyor; önbellekte kalan bir kopya, o
 * kararın anlamını yok ederdi.
 *
 * İkinci sınanan şey: `/api/*` isteklerinin ASLA önbellekten yanıtlanmaması.
 * Bayat kur, yanlış para demektir.
 */
describe('E1 — service worker kapsamı yalnız /panel/', () => {
  test('dosya panelin altında yayımlanır (kök dizinde DEĞİL)', () => {
    // Kapsam dosyanın KONUMUNDAN türer. `frontend/public/` içeriği
    // `public/panel/` altına kopyalanır → `/panel/sw.js`.
    expect(() => readFileSync(resolve(kok, 'public/sw.js'))).not.toThrow();
  });

  test('kayıt açıkça /panel/ kapsamı ister', () => {
    expect(kayit).toContain("register('/panel/sw.js', { scope: '/panel/' })");
  });

  test('kapsam dışı yol için ikinci emniyet kilidi var', () => {
    // Tarayıcı zaten kapsam dışını getirmez; yine de SW yanlış kaydedilirse
    // bu kontrol paylaşım sayfasını önbellekten uzak tutar.
    expect(sw).toContain("if (!url.pathname.startsWith('/panel/')) return;");
  });

  test('paylaşım sayfası ön eki hiçbir önbellek listesinde geçmez', () => {
    expect(sw).not.toContain("'/liste");
    expect(sw).not.toContain('"/liste');
  });
});

describe('E1 — /api/* her zaman ağa gider', () => {
  test('API yolu fetch işleyicisinden ERKEN döner', () => {
    expect(sw).toContain("if (url.pathname.startsWith('/api/')) return;");
  });

  test('API için önbellek yedeği YOKTUR', () => {
    // Erken dönüş, `respondWith` çağrılmadan önce olmalı: sonrasında olsaydı
    // hata durumunda önbellek yedeğine düşerdi.
    const apiSatiri = sw.indexOf("startsWith('/api/')");
    const respondSatiri = sw.indexOf('respondWith');

    expect(apiSatiri).toBeGreaterThan(0);
    expect(apiSatiri).toBeLessThan(respondSatiri);
  });
});

describe('E1 — önbellek adı derleme damgasına bağlı', () => {
  test('BUILD.json okunur ve commit önbellek adına girer', () => {
    expect(sw).toContain('/panel/BUILD.json');
    expect(sw).toContain('ONBELLEK_ONEKI + String(damga.commit');
  });

  test('eski önbellekler activate sırasında silinir', () => {
    expect(sw).toContain('caches.delete');
    expect(sw).toContain("ad !== guncel");
  });
});

describe('E2 — çevrimdışında veri uydurulmaz', () => {
  test('çevrimdışı yanıtı yalnız kabuk ve bilgi metni verir', () => {
    expect(sw).toContain('Çevrimdışı');
    expect(sw).toContain('Bağlantı yok');
    // 503: "şu an hizmet veremiyorum" — 200 dönmek, boş ekranı başarı gibi
    // gösterirdi.
    expect(sw).toContain('status: 503');
  });

  test('önbelleğe yalnız kabuk varlıkları yazılır', () => {
    expect(sw).toContain("'/panel/index.html'");
    expect(sw).toContain("'/panel/site.webmanifest'");
    // Veri uçları kabuk listesinde OLMAMALI. Yalnız DİZİ GÖVDESİ taranır:
    // tüm dosyada `/api/` aramak, fetch işleyicisindeki (doğru) kontrolü de
    // ihlal sanırdı — ilk denemede tam bu oldu.
    const bas = sw.indexOf('KABUK_VARLIKLARI = [');
    const dizi = sw.slice(bas, sw.indexOf('];', bas));

    expect(bas).toBeGreaterThan(0);
    expect(dizi).not.toContain('/api/');
    expect(dizi.split('\n').length).toBeLessThan(10);
  });
});
