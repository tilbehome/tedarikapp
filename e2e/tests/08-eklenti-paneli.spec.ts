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
