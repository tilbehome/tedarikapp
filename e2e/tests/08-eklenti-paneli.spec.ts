import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { expect, test } from '@playwright/test';

import { alanRaporu } from '../../extension/core/alanRaporu';
import { baslangicDurumu } from '../../extension/core/durumMakinesi';
import type { PanelGorunumu } from '../../extension/ui/v2/panel';
import type { ParseResult } from '../../extension/core/types';

/**
 * E2E-EKL-30 / E2E-EKL-31 — GERÇEK TARAYICIDA PANEL DAVRANIŞI (rc7).
 *
 * NEDEN GERÇEK TARAYICI: iki kabul ölçütü hesaplanmış düzen ister ve jsdom
 * bunu üretmez —
 *   · EKL-30: panel içeriği yatay taşmaz (scrollWidth ≤ clientWidth),
 *   · EKL-31: panel varsayılan KAPALI (hesaplanmış `display: none`).
 * Sahada (rc6) ikisi de kırıktı: çekmece `hidden` ile kapatılıyordu ama
 * `.tdk-cekmece { display:flex }` yazar kuralı UA'nın `[hidden]` kuralını
 * eziyordu; uzun URL'ler ve varyant çipleri paneli yatay kaydırıyordu.
 *
 * KURULUM: 1688 sayfası AÇILMAZ (dış ağ yok, K61 disiplini). Arayüz katmanı
 * boş bir sayfaya monte edilir; sınanan şey bizim çizimimizin düzen davranışıdır.
 */

const UZUN_ADRES =
  'https://detail.1688.com/offer/1062644236710.html?offerId=1062644236710&hotSaleSkuId=5310981234567&spm=a260k.home2024.recommendpart.9';

const UZUN_VARYANTLAR = [
  '绿色【足弓支撑 久站不累脚】加厚底 38/39',
  '蓝色【足弓支撑 久站不累脚】加厚底 40/41',
  '灰色【足弓支撑 久站不累脚】加厚底 42/43',
  '黑色【足弓支撑 久站不累脚】加厚底 44/45',
  '粉色【足弓支撑 久站不累脚】加厚底 36/37',
  '米色【足弓支撑 久站不累脚】加厚底 46/47',
];

function ayristirma(): ParseResult {
  return {
    ok: true,
    missing: [],
    source: {
      platform: '1688',
      external_id: '1062644236710',
      url: UZUN_ADRES,
      seller_name: '义乌市盎燕电子商务有限公司',
      seller_url:
        'https://shop1234567890.1688.com/page/offerlist.htm?spm=a2615.7691456.wp_pc_common_topnav_38975102',
      captured_at: '2026-08-26T10:00:00+03:00',
    },
    raw: {
      title: '洞洞鞋男士2025夏季新款外穿包头拖鞋 EVA 防滑厚底凉拖鞋',
      images: ['a.jpg'],
      normalized_attributes: { 材质: 'EVA' },
      min_order: 2,
      unit: '双',
      breadcrumb: [],
    },
    normalized: {
      name: 'EVA Kaymaz Kalın Taban Erkek Sandalet',
      price_yuan: '15.90',
      price_tiers: [{ min_qty: 1, price_yuan: '15.90' }],
      images: ['a.jpg'],
      sku_matrix: UZUN_VARYANTLAR.map((ad) => ({ props: { varyant: ad } })),
      video_url: null,
    },
  } as unknown as ParseResult;
}

function gorunum(fark: Partial<PanelGorunumu> = {}): PanelGorunumu {
  return {
    makine: baslangicDurumu(),
    rapor: alanRaporu(ayristirma()),
    urunAdi: 'EVA Kaymaz Kalın Taban Erkek Sandalet',
    orijinalAd: '洞洞鞋男士2025夏季新款外穿包头拖鞋 EVA 防滑厚底凉拖鞋',
    varyantlar: UZUN_VARYANTLAR,
    seciliVaryant: UZUN_VARYANTLAR[0],
    listeler: [{ id: null, ad: 'Gelen Kutusu (varsayılan)' }],
    hedef: { listeId: null, miktar: 1, not: '', etiketler: ['yaz-2027'] },
    duranlar: [],
    disclosureGerekli: false,
    urunId: null,
    baglanti: 'BAGLI',
    baglantiMesaj: 'Panele bağlı',
    otomatikSuruyor: false,
    ...fark,
  };
}

/**
 * Köprü paketinin MUTLAK yolu.
 *
 * Önceki hâl `new URL(...).pathname.replace(/^\//, '')` idi: Windows'ta
 * `/C:/...` → `C:/...` düzeltmesi doğru çalışıyor, Linux'ta ise `/home/...`
 * → `home/...` olup GÖRECELİ bir yola dönüşüyordu. CI'da dört senaryo
 * "ENOENT home/runner/..." ile düştü; yerelde hiç görünmedi. `fileURLToPath`
 * bu ayrımı platformun kendisine bırakır.
 */
const KOPRU_YOLU = fileURLToPath(new URL('../dist/arayuz.js', import.meta.url));

test.describe('E2E-EKL-31 — panel varsayılan KAPALI (rc7 EK-1 §5)', () => {
  test('beş ardışık yüklemede 5/5 kapalı başlar ve düğme görünür', async ({ page }) => {
    for (let yukleme = 1; yukleme <= 5; yukleme++) {
      await page.setViewportSize({ width: 1440, height: 900 });
      await page.setContent(
        '<!doctype html><html><body><div class="price-content">¥15.90</div><div id="kok"></div></body></html>',
      );
      await page.addScriptTag({ path: KOPRU_YOLU });

      const sonuc = await page.evaluate(() => {
        const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
        const eylemler = new Proxy({}, { get: () => () => {} }) as never;
        const cekmece = (api.cekmeceKur as (s: unknown) => { acikMi(): boolean; kok(): ShadowRoot })({
          eylemler,
        });
        const montaj = (api.montajYap as (s: unknown) => { tur: string })({
          adres: 'https://detail.1688.com/offer/1062644236710.html?offerId=1062644236710',
          onTikla: () => {},
        });

        const panel = cekmece.kok().querySelector('.tdk-cekmece') as HTMLElement;
        const gorunurluk = getComputedStyle(panel).display;

        return { acik: cekmece.acikMi(), gorunurluk, montajTuru: montaj.tur, kapVar: document.getElementById('tedarikapp-v2-kap') !== null };
      });

      expect(sonuc.acik, `yükleme ${yukleme}: acikMi()`).toBe(false);
      // ASIL ÖLÇÜM: hesaplanmış display. rc6'da bu "flex"ti — panel hep açıktı.
      expect(sonuc.gorunurluk, `yükleme ${yukleme}: hesaplanmış display`).toBe('none');
      expect(sonuc.kapVar, `yükleme ${yukleme}: düğme kabı`).toBe(true);
      expect(sonuc.montajTuru, `yükleme ${yukleme}: montaj türü`).toBe('SATIRICI');
    }
  });

  test('montaj hedefi yoksa sağ-alt pill yedeği basılır ve GÖRÜNÜR', async ({ page }) => {
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const sonuc = await page.evaluate(() => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      (api.cekmeceKur as (s: unknown) => unknown)({ eylemler: new Proxy({}, { get: () => () => {} }) });
      const montaj = (api.montajYap as (s: unknown) => { tur: string; dugme: HTMLElement | null })({
        adres: 'https://detail.1688.com/offer/1062644236710.html',
        onTikla: () => {},
      });

      // Kap düğümü position:fixed bir çocuğu sarar; kendi yüksekliği SIFIRDIR.
      // Ölçülmesi gereken düğmenin kendisidir.
      const kutu = montaj.dugme?.getBoundingClientRect();
      const stil = montaj.dugme === null ? null : getComputedStyle(montaj.dugme);

      return {
        tur: montaj.tur,
        gorunur: (kutu?.width ?? 0) > 0 && (kutu?.height ?? 0) > 0,
        gorunurluk: stil?.display ?? 'yok',
        sagAlt: (kutu?.right ?? 0) > window.innerWidth - 200 && (kutu?.bottom ?? 0) > window.innerHeight - 200,
      };
    });

    // Panel kapalı olduğu için pill'i örten bir şey yoktur (rc6'da çekmece örtüyordu).
    expect(sonuc.tur).toBe('PILL');
    expect(sonuc.gorunur, 'pill ölçülebilir bir kutuya sahip').toBe(true);
    expect(sonuc.gorunurluk).not.toBe('none');
    // Mockup: yedek düğme sağ ALTTA durur ve panel kapalı olduğu için örtülmez.
    expect(sonuc.sagAlt, 'pill sağ alt köşede').toBe(true);
  });
});

test.describe('E2E-EKL-30 — panel yatay TAŞMAZ (rc7 D10-b)', () => {
  test('en uzun URL ve 6 varyant çipiyle yatay kaydırma YOK', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const olcum = await page.evaluate((gorunumJson) => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      const cekmece = (
        api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
      )({ eylemler: new Proxy({}, { get: () => () => {} }) });

      cekmece.ciz(JSON.parse(gorunumJson));
      cekmece.ac();

      const kok = cekmece.kok();
      const panel = kok.querySelector('.tdk-cekmece') as HTMLElement;
      const govde = kok.querySelector('.tdk-govde') as HTMLElement;
      const tasanlar: string[] = [];

      for (const dugum of Array.from(kok.querySelectorAll('.tdk-govde *'))) {
        const oge = dugum as HTMLElement;
        if (oge.getBoundingClientRect().right > panel.getBoundingClientRect().right + 1) {
          tasanlar.push((oge.className || oge.tagName) + ' :: ' + (oge.textContent ?? '').slice(0, 40));
        }
      }

      return {
        panelScroll: panel.scrollWidth,
        panelClient: panel.clientWidth,
        govdeScroll: govde.scrollWidth,
        govdeClient: govde.clientWidth,
        tasanlar: tasanlar.slice(0, 5),
      };
    }, JSON.stringify(gorunum()));

    // Saha kanıtı K2: yatay kaydırma çubuğu çıkıyor, çipler panel dışına taşıyordu.
    expect(olcum.govdeScroll, 'gövde yatay taşma').toBeLessThanOrEqual(olcum.govdeClient);
    expect(olcum.panelScroll, 'panel yatay taşma').toBeLessThanOrEqual(olcum.panelClient);
    expect(olcum.tasanlar, 'panel sınırını aşan öğeler').toEqual([]);
  });

  test('dar görünümde (360 px) de taşma yok', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 780 });
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const olcum = await page.evaluate((gorunumJson) => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      const cekmece = (
        api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
      )({ eylemler: new Proxy({}, { get: () => () => {} }) });
      cekmece.ciz(JSON.parse(gorunumJson));
      cekmece.ac();

      const govde = cekmece.kok().querySelector('.tdk-govde') as HTMLElement;

      return { scroll: govde.scrollWidth, client: govde.clientWidth };
    }, JSON.stringify(gorunum()));

    expect(olcum.scroll).toBeLessThanOrEqual(olcum.client);
  });
});

/**
 * EKL-32 için BOL İÇERİKLİ görünüm: sahadaki panel 1080p'de bile taşıyordu
 * (uzun ürün adı + çok varyant + dolu alan listesi). 6 varyantlık EKL-30
 * fikstürü 1080 px'de taşmıyor; taşmayan bir senaryoda kaydırma testinin
 * ölçtüğü bir şey kalmaz. Varyant sayısı sahadaki tipik ürüne çıkarılır.
 */
function bolGorunum(): PanelGorunumu {
  const varyantlar = Array.from(
    { length: 24 },
    (_, sira) => `${UZUN_VARYANTLAR[sira % UZUN_VARYANTLAR.length]} · parti ${sira + 1}`,
  );

  return gorunum({ varyantlar, seciliVaryant: varyantlar[0] });
}

test.describe('E2E-EKL-32 — panel gövdesi KENDİ İÇİNDE kaydırılır (v1.0 saha bulgusu, 27 Ağu)', () => {
  /**
   * SAHA: 1080p ekranda çekmece içeriği viewport'tan uzundu; "Nereye gitsin"
   * ve "Yakala ve Gönder" altta kalıyor, çekmece kendi içinde kaydırmıyor,
   * sayfa kaydırması da çekmeceyi taşımıyordu (panel `position: fixed`).
   * Kullanıcı gönder düğmesine ULAŞAMIYORDU — eklenti işlevsizdi.
   *
   * KÖK NEDEN: `.tdk-govde`nin `flex:1; overflow-y:auto` kuralı, yüksekliği
   * TANIMSIZ bir sarmalayıcının (`[data-govde]`) içindeydi. Sarmalayıcı
   * içerikle büyüyor, `.tdk-alt`ı aşağı itiyor; kaydırıcı hiç devreye
   * girmiyordu. rc7'deki yatay `min-width:auto` kusurunun DİKEY ikizi.
   *
   * ÖLÇÜM: hesaplanmış düzen ister — jsdom üretmez, gerçek tarayıcı şart.
   */
  for (const [genislik, yukseklik] of [
    [1366, 768],
    [1920, 1080],
  ]) {
    test(`${genislik}×${yukseklik}: "Yakala ve Gönder" görünür ve TIKLANABİLİR`, async ({ page }) => {
      await page.setViewportSize({ width: genislik, height: yukseklik });
      await page.setContent('<!doctype html><html><body></body></html>');
      await page.addScriptTag({ path: KOPRU_YOLU });

      const olcum = await page.evaluate((gorunumJson) => {
        const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
        const cekmece = (
          api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
        )({ eylemler: new Proxy({}, { get: () => () => {} }) });

        cekmece.ciz(JSON.parse(gorunumJson));
        cekmece.ac();

        const kok = cekmece.kok();
        const panel = kok.querySelector('.tdk-cekmece') as HTMLElement;
        const kaydirici = kok.querySelector('[data-govde]') as HTMLElement;
        const gonder = kok.querySelector('[data-eylem="gonder"]') as HTMLElement;
        const kutu = gonder.getBoundingClientRect();

        // TIKLANABİLİRLİK: düğmenin merkezinde gerçekten düğme mi duruyor?
        // Görünür ama başka bir katmanın altında kalan düğme de "görünür"dür;
        // kullanıcı için fark yoktur — ölçüm elementFromPoint ile yapılır.
        const merkez = kok.elementFromPoint(kutu.left + kutu.width / 2, kutu.top + kutu.height / 2);

        return {
          panelYukseklik: panel.getBoundingClientRect().height,
          viewport: window.innerHeight,
          dugmeAlt: kutu.bottom,
          dugmeYukseklik: kutu.height,
          merkezdekiDugmeMi: merkez === gonder || gonder.contains(merkez),
          govdeScroll: kaydirici.scrollHeight,
          govdeClient: kaydirici.clientHeight,
        };
      }, JSON.stringify(bolGorunum()));

      // Panel viewport'u aşmaz — aşarsa alt çubuk ekranın dışında kalır.
      expect(olcum.panelYukseklik, 'panel yüksekliği').toBeLessThanOrEqual(olcum.viewport + 1);
      // Gövde gerçekten taşıyor olmalı, yoksa test kusuru göstermez.
      expect(olcum.govdeScroll, 'gövde içeriği taşmalı (senaryo geçerli mi)').toBeGreaterThan(
        olcum.govdeClient,
      );
      // ASIL KABUL: düğme ekranda ve üstünde başka katman yok.
      expect(olcum.dugmeYukseklik, 'düğme ölçülebilir').toBeGreaterThan(0);
      expect(olcum.dugmeAlt, '"Yakala ve Gönder" viewport içinde').toBeLessThanOrEqual(
        olcum.viewport + 1,
      );
      expect(olcum.merkezdekiDugmeMi, '"Yakala ve Gönder" tıklanabilir').toBe(true);
    });
  }

  test('gövde kaydırılınca alt çubuk YERİNDE kalır', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const olcum = await page.evaluate((gorunumJson) => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      const cekmece = (
        api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
      )({ eylemler: new Proxy({}, { get: () => () => {} }) });
      cekmece.ciz(JSON.parse(gorunumJson));
      cekmece.ac();

      const kok = cekmece.kok();
      const kaydirici = kok.querySelector('[data-govde]') as HTMLElement;
      const gonder = kok.querySelector('[data-eylem="gonder"]') as HTMLElement;
      const ust = kok.querySelector('.tdk-ust') as HTMLElement;

      const oncekiDugme = gonder.getBoundingClientRect().top;
      const oncekiUst = ust.getBoundingClientRect().top;
      kaydirici.scrollTop = kaydirici.scrollHeight;

      return {
        kaydi: kaydirici.scrollTop > 0,
        dugmeFarki: Math.abs(gonder.getBoundingClientRect().top - oncekiDugme),
        ustFarki: Math.abs(ust.getBoundingClientRect().top - oncekiUst),
      };
    }, JSON.stringify(bolGorunum()));

    expect(olcum.kaydi, 'gövde kaydırılabilir olmalı').toBe(true);
    expect(olcum.dugmeFarki, 'alt aksiyon çubuğu kaymamalı').toBeLessThanOrEqual(1);
    expect(olcum.ustFarki, 'üst başlık kaymamalı').toBeLessThanOrEqual(1);
  });
});

test.describe('E2E-EKL-33 — panel metin disiplini (v1.0 A2/A3/A4, saha 27 Ağu)', () => {
  test('A3: HTML varlıkları çipte ve alan değerinde LİTERAL görünmez', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const sonuc = await page.evaluate((gorunumJson) => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      const cekmece = (
        api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
      )({ eylemler: new Proxy({}, { get: () => () => {} }) });
      cekmece.ciz(JSON.parse(gorunumJson));
      cekmece.ac();

      const kok = cekmece.kok();
      const cipler = Array.from(kok.querySelectorAll('.tdk-varyant .cip')).map(
        (d) => (d as HTMLElement).textContent ?? '',
      );

      return {
        cipler,
        // XSS: çözülen metin işaretlemeye DÖNÜŞMEMELİ.
        enjekteEdilenEtiket: kok.querySelector('img') !== null,
        panelMetni: (kok.querySelector('.tdk-cekmece') as HTMLElement).textContent ?? '',
      };
    }, JSON.stringify(gorunum({ varyantlar: ['A&gt;B', '&lt;img src=x onerror=alert(1)&gt;'], seciliVaryant: 'A&gt;B' })));

    expect(sonuc.cipler[0], 'entity çözülmeli').toBe('A>B');
    expect(sonuc.cipler[1], 'işaretleme METİN olarak basılmalı').toBe('<img src=x onerror=alert(1)>');
    expect(sonuc.enjekteEdilenEtiket, 'XSS: <img> düğümü OLUŞMAMALI').toBe(false);
    expect(sonuc.panelMetni).not.toContain('&gt;');
  });

  test('A2: öneri orijinalle AYNIYSA rozet yok, açıklama var', async ({ page }) => {
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const sonuc = await page.evaluate(
      ([esitJson, farkliJson]) => {
        const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
        const ciz = (json: string) => {
          const cekmece = (
            api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
          )({ eylemler: new Proxy({}, { get: () => () => {} }) });
          cekmece.ciz(JSON.parse(json));
          cekmece.ac();
          const kok = cekmece.kok();

          return {
            rozet: kok.querySelector('.tdk-oneri') !== null,
            not: (kok.querySelector('.tdk-not') as HTMLElement | null)?.textContent ?? '',
          };
        };

        return { esit: ciz(esitJson), farkli: ciz(farkliJson) };
      },
      [
        JSON.stringify(gorunum({ urunAdi: '洞洞鞋男士2025夏季新款', orijinalAd: '洞洞鞋男士2025夏季新款' })),
        JSON.stringify(gorunum({ urunAdi: 'EVA Erkek Sandalet', orijinalAd: '洞洞鞋男士2025夏季新款' })),
      ],
    );

    expect(sonuc.esit.rozet, 'sözlük eşleşmedi — rozet KONMAMALI').toBe(false);
    expect(sonuc.esit.not).toContain('sunucuda üretilir');
    expect(sonuc.farkli.rozet, 'gerçek öneri varsa rozet kalır').toBe(true);
    expect(sonuc.farkli.not, 'gerçek öneride açıklama satırı olmaz').toBe('');
  });

  test('A4: adres satırında native title YOK, kopyala düğmesi VAR', async ({ page }) => {
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const sonuc = await page.evaluate((gorunumJson) => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      const cekmece = (
        api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
      )({ eylemler: new Proxy({}, { get: () => () => {} }) });
      cekmece.ciz(JSON.parse(gorunumJson));
      cekmece.ac();

      const kok = cekmece.kok();
      const titleliler = Array.from(kok.querySelectorAll('[title]')).map(
        (d) => (d as HTMLElement).className,
      );

      return {
        titleliler,
        kopyalaSayisi: kok.querySelectorAll('[data-eylem="kopyala"]').length,
      };
    }, JSON.stringify(gorunum()));

    expect(sonuc.titleliler, 'panelde native title balonu kalmamalı').toEqual([]);
    expect(sonuc.kopyalaSayisi, 'adres satırında kopyala düğmesi').toBeGreaterThan(0);
  });
});

/**
 * FİKSTÜRLER (A5/A8) — ÜRÜN SAHİBİ'NİN GERÇEK SAYFA DÖKÜMLERİ (27 Ağu 2026).
 *
 * "Web Sayfası, Tamamı" kaydından alınan render edilmiş DOM'lar. `_files`
 * klasörleri repoya GİRMEZ: dış CSS/JS/görsel yüklenmez, Playwright ağı zaten
 * bloklar. Sonuç: DÜZEN gerçek sayfanın pikseli değildir, ama AĞAÇ gerçektir —
 * bu senaryonun sınadığı şey de zaten hedefin bulunup bulunmadığı ve düğmenin
 * hangi düğümün önüne girdiğidir.
 *
 * İki döküm de OTURUMSUZ alındı: sipariş düğmelerinin yerinde giriş çağrısı
 * var. Sipariş modülü (`[data-spm="submitOrder"]`) iki skinde de aynı düğümdür.
 */
const FIKSTURLER = [
  { ad: '1688 ZH', dosya: 'detay-zh.html', satinAlma: '[data-spm="submitOrder"]', komsu: '.module-od-shop' },
  {
    ad: 'AliTrading TR',
    dosya: 'detay-tr-alitrading.html',
    satinAlma: '[data-spm="submitOrder"]',
    komsu: '.module-od-shop',
  },
];

function fiksturHtml(dosya: string): string {
  return readFileSync(fileURLToPath(new URL(`../fixtures/1688/${dosya}`, import.meta.url)), 'utf8');
}

test.describe('E2E-EKL-34 — inline düğme SATIN ALMA BLOĞUNUN ÜSTÜNDE (v1.0 A5/A8)', () => {
  /**
   * AĞ KAPALI (K61). Dökümler gerçek sayfadan geldiği için içlerinde 1688 ve
   * CDN adresleri var. Hiçbiri istenmez: dış istek hem CI'yı ağa bağımlı kılar
   * hem de senaryoyu 1688'in o günkü hâline teslim ederdi. İstekler kaynağında
   * durdurulur; enjekte edilen köprü dosyadan gelir, ağ gerektirmez.
   */
  test.beforeEach(async ({ page }) => {
    await page.route('**/*', (yol) => void yol.abort());
  });

  for (const fikstur of FIKSTURLER) {
    test(`${fikstur.ad}: 5/5 yüklemede düğme blok üstünde ve hiçbir öğeyi örtmez`, async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 });

      for (let yukleme = 1; yukleme <= 5; yukleme++) {
        await page.setContent(fiksturHtml(fikstur.dosya));
        await page.addScriptTag({ path: KOPRU_YOLU });

        const olcum = await page.evaluate((satinAlmaSecici) => {
          const kutuAl = (dugum: Element | null | undefined): DOMRect | null =>
            dugum?.getBoundingClientRect() ?? null;
          const blokDugumu = document.querySelector(satinAlmaSecici);
          if (blokDugumu === null) return { hedefYok: true } as Record<string, unknown>;

          // KOMŞU SINIF ADIYLA DEĞİL, AĞAÇTAN alınır: sipariş modülünün hemen
          // ÖNÜNDEKİ kardeş, sayfanın kendi düzenindeki gerçek komşudur ve
          // dağıtım sınıf adı değiştirse de yerinde kalır.
          const oncekiKardes = blokDugumu.previousElementSibling;
          const oncesiKardesUst = kutuAl(oncekiKardes)?.top ?? null;
          const oncesiBlokUst = kutuAl(blokDugumu)?.top ?? null;

          const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
          const montaj = (api.montajYap as (s: unknown) => { tur: string; dugme: HTMLElement | null })({
            adres: 'https://detail.1688.com/offer/1062644236710.html',
            onTikla: () => {},
          });

          const kutu = kutuAl(montaj.dugme);
          const blok = kutuAl(blokDugumu);
          const kardesUst = kutuAl(oncekiKardes)?.top ?? null;
          const kesisir = (a: DOMRect | null, b: DOMRect | null): boolean =>
            a !== null && b !== null && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;

          return {
            hedefYok: false,
            tur: montaj.tur,
            genislik: kutu?.width ?? 0,
            yukseklik: kutu?.height ?? 0,
            // Düğme bloğun ÜSTÜNDE: alt kenarı bloğun üst kenarını geçmez.
            blokUstunde: (kutu?.bottom ?? Number.MAX_SAFE_INTEGER) <= (blok?.top ?? 0) + 1,
            blokKesisir: kesisir(kutu, blok),
            kardesKesisir: kesisir(kutu, kutuAl(oncekiKardes)),
            // Önceki kardeş YERİNDEN OYNAMADI: düğme yalnız kendinden SONRASINI
            // iter; üstteki içerik (mağaza satırı dâhil) kaymaz, örtülmez.
            kardesKaydi: oncesiKardesUst === null || kardesUst === null ? false : Math.abs(kardesUst - oncesiKardesUst) > 1,
            // Blok gerçekten aşağı itildi: düğme akışa GİRDİ, üstüne binmedi.
            // Kaç piksel ittiği İDDİA EDİLMEZ — kenar boşluğu birleşmesi (margin
            // collapsing) yüzünden itilme düğme yüksekliğine eşit olmak zorunda
            // değildir (ölçüldü: 46 px düğme, 32 px itme). Sınanan şey yön.
            blokItildi: oncesiBlokUst === null || blok === null ? false : blok.top - oncesiBlokUst > 0,
            // Sayfada TEK kap vardır (SPA yönlendirmelerinde çoğalmamalı).
            kapSayisi: document.querySelectorAll('#tedarikapp-v2-kap').length,
          };
        }, fikstur.satinAlma);

        expect(olcum.hedefYok, `yükleme ${yukleme}: satın alma bloğu fikstürde bulunmalı`).toBe(false);
        expect(olcum.tur, `yükleme ${yukleme}: montaj türü`).toBe('SATIRICI');
        expect(olcum.blokUstunde, `yükleme ${yukleme}: satın alma bloğunun ÜSTÜNDE`).toBe(true);
        expect(olcum.blokKesisir, `yükleme ${yukleme}: satın alma bloğunu ÖRTMEZ`).toBe(false);
        expect(olcum.kardesKesisir, `yükleme ${yukleme}: önceki komşuyu ÖRTMEZ`).toBe(false);
        expect(olcum.kardesKaydi, `yükleme ${yukleme}: üstteki içerik KAYMAZ`).toBe(false);
        expect(olcum.blokItildi, `yükleme ${yukleme}: düğme araya girdi (blok aşağı itildi)`).toBe(true);
        expect(olcum.kapSayisi, `yükleme ${yukleme}: sayfada tek kap`).toBe(1);
        expect(olcum.yukseklik, `yükleme ${yukleme}: düğme ölçülebilir`).toBeGreaterThan(0);
        expect(olcum.genislik, `yükleme ${yukleme}: tam genişlik`).toBeGreaterThan(300);
      }
    });

    test(`${fikstur.ad}: DİL GEÇİŞİ (yenilemesiz yeniden çizim) düğmeyi geri getirir`, async ({ page }) => {
      await page.setContent(fiksturHtml(fikstur.dosya));
      await page.addScriptTag({ path: KOPRU_YOLU });

      // Gerçek akışta nöbeti köprü kurar; burada aynı sözleşme kurulur.
      await page.evaluate(() => {
        const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
        const monteEt = () =>
          (api.montajYap as (s: unknown) => { tur: string })({
            adres: 'https://detail.1688.com/offer/1062644236710.html',
            onTikla: () => {},
          }).tur;
        monteEt();
        (api.montajNobeti as (f: () => string) => void)(monteEt);
      });

      for (let gecis = 1; gecis <= 5; gecis++) {
        // 1688'in dil geçişi sağ sütunu YENİDEN ÇİZER: adres değişmez, bizim
        // düğümümüz sökülür. Sahada düğme bir daha geri gelmiyordu.
        await page.evaluate(() => {
          const sutun = document.querySelector('[data-spm="submitOrder"]')?.parentElement ?? null;
          if (sutun !== null) sutun.innerHTML = sutun.innerHTML;
        });

        await expect
          .poll(
            () => page.evaluate(() => document.getElementById('tedarikapp-v2-kap') !== null),
            { message: `dil geçişi ${gecis}: düğme geri gelmeli`, timeout: 5000 },
          )
          .toBe(true);
      }
    });
  }

  test('hedef yoksa PILL yedeği 5/5 görünür', async ({ page }) => {
    for (let yukleme = 1; yukleme <= 5; yukleme++) {
      await page.setContent('<!doctype html><html><body><div>boş sayfa</div></body></html>');
      await page.addScriptTag({ path: KOPRU_YOLU });

      const olcum = await page.evaluate(() => {
        const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
        const montaj = (api.montajYap as (s: unknown) => { tur: string; dugme: HTMLElement | null })({
          adres: 'https://detail.1688.com/offer/1062644236710.html',
          onTikla: () => {},
        });
        const kutu = montaj.dugme?.getBoundingClientRect();

        return {
          tur: montaj.tur,
          gorunur: (kutu?.width ?? 0) > 0 && (kutu?.height ?? 0) > 0,
          gorunurluk: montaj.dugme === null ? 'yok' : getComputedStyle(montaj.dugme).display,
        };
      });

      expect(olcum.tur, `yükleme ${yukleme}`).toBe('PILL');
      expect(olcum.gorunur, `yükleme ${yukleme}: pill ölçülebilir`).toBe(true);
      expect(olcum.gorunurluk, `yükleme ${yukleme}: pill display`).not.toBe('none');
    }
  });
});

test.describe('E2E-EKL-35 — varyant çipleri SALT GÖRÜNÜM Türkçe (v1.0.1 A9)', () => {
  test('renk ve bölge adları TR görünür, sunucuya giden değer ORİJİNAL kalır', async ({ page }) => {
    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({ path: KOPRU_YOLU });

    const sonuc = await page.evaluate((gorunumJson) => {
      const api = (window as unknown as { TDK: Record<string, unknown> }).TDK;
      const secilenler: string[] = [];
      const cekmece = (
        api.cekmeceKur as (s: unknown) => { ac(): void; ciz(g: unknown): void; kok(): ShadowRoot }
      )({
        eylemler: new Proxy(
          { onVaryant: (varyant: string) => secilenler.push(varyant) },
          { get: (hedef, ad) => (hedef as Record<string, unknown>)[ad as string] ?? (() => {}) },
        ),
      });
      cekmece.ciz(JSON.parse(gorunumJson));
      cekmece.ac();

      const kok = cekmece.kok();
      const cipler = Array.from(kok.querySelectorAll('.tdk-varyant .cip')) as HTMLElement[];
      // İlk çipe tıkla: geri çağrıya ORİJİNAL değer gitmeli.
      cipler[0]?.click();

      return {
        metinler: cipler.map((cip) => cip.textContent ?? ''),
        orijinaller: cipler.map((cip) => cip.getAttribute('data-orijinal') ?? ''),
        secilenler,
      };
    }, JSON.stringify(gorunum({ varyantlar: ['粉红色', '美规', '洞洞鞋专用鞋扣'], seciliVaryant: '粉红色' })));

    expect(sonuc.metinler[0], 'renk TR görünür').toBe('Pembe');
    expect(sonuc.metinler[1], 'voltaj bilgisi KORUNUR').toBe('ABD fişi (110V)');
    expect(sonuc.metinler[2], 'bilinmeyen terim AYNEN kalır').toBe('洞洞鞋专用鞋扣');

    // ASIL SÖZLEŞME: görünüm Türkçeleşse de veri orijinaldir (K90).
    expect(sonuc.orijinaller[0]).toBe('粉红色');
    expect(sonuc.secilenler[0], 'seçim geri çağrısı ORİJİNAL değeri taşır').toBe('粉红色');
  });
});
