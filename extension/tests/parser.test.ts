/**
 * 1688 parser fixture testleri (İE#11 kabul + F20): CANLI istek YOK — fixture'lar
 * docs/arastirma/1688-parser-raporu.md'deki gerçek yapı örneklerinden türetildi.
 * Senaryolar: tekli fiyat · kademeli fiyat · SKU'lu · videolu · eksik alanlı + $ref.
 */

import { describe, expect, it } from 'vitest';
import { cleanImageUrl, extractTiers, parse1688 } from '../modules/m1688/parser';
import { extractContext, resolveRefs } from '../core/jsonpath';
import selectorsJson from './fixtures/selectors-1688.json';
import type { SelectorSet } from '../core/types';

const selectors = selectorsJson as unknown as SelectorSet;
const PAGE_URL = 'https://detail.1688.com/offer/895133432293.html';

/** Rapordaki yapıya birebir mini context üretici. */
function baseContext(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    result: {
      data: {
        detailModel: {
          offerDetail: { offerId: 895133432293, subject: '跨境榨汁机便携式小型水果机' },
          parametersMap: { loginId: 'testSeller', offerId: '895133432293' },
          orderParamModel: {
            orderParam: {
              skuParam: {
                currentPrices: [{ price: '9.00', beginAmount: 2 }],
                skuRangePrices: null,
                skuProps: null,
              },
            },
          },
        },
        gallery: {
          fields: {
            offerImgList: ['https://cbu01.alicdn.com/img/ibank/a.jpg_.webp', 'http://cbu01.alicdn.com/img/ibank/b.jpg'],
            videoId: null,
            videoCoverUrl: null,
          },
        },
        sellerInfo: { fields: { companyName: '永康市测试有限公司' } },
        productAttribute: { fields: { attributes: { 材质: '304不锈钢' } } },
        ...overrides,
      },
    },
  } as Record<string, unknown>;
}

describe('parse1688 — fixture senaryoları', () => {
  it('tekli fiyatlı sayfayı doğru çıkarır', () => {
    const result = parse1688(baseContext(), selectors, PAGE_URL);

    expect(result.ok).toBe(true);
    expect(result.source.external_id).toBe('895133432293'); // number → String (rapor A.1)
    expect(result.source.platform).toBe('1688');
    expect(result.normalized.name).toBe('跨境榨汁机便携式小型水果机');
    expect(result.normalized.price_yuan).toBe('9.00');
    expect(result.normalized.price_tiers).toEqual([{ min_qty: 2, price_yuan: '9.00' }]);
    // Görsel temizliği: lazy sonek düştü, http→https yükseldi.
    expect(result.normalized.images[0]).toBe('https://cbu01.alicdn.com/img/ibank/a.jpg');
    expect(result.normalized.images[1]).toBe('https://cbu01.alicdn.com/img/ibank/b.jpg');
    expect(result.source.seller_name).toBe('永康市测试有限公司');
  });

  it('kademeli fiyatta EN DÜŞÜK kademe birim fiyat olur, tüm kademeler taşınır', () => {
    const ctx = baseContext();
    const skuParam = (ctx.result as any).data.detailModel.orderParamModel.orderParam.skuParam;
    skuParam.currentPrices = [
      { price: '12.50', beginAmount: 2 },
      { price: '11.80', beginAmount: 100 },
      { price: '10.90', beginAmount: 500 },
    ];

    const result = parse1688(ctx, selectors, PAGE_URL);

    expect(result.normalized.price_tiers).toHaveLength(3);
    expect(result.normalized.price_tiers[0]).toEqual({ min_qty: 2, price_yuan: '12.50' });
    expect(result.normalized.price_yuan).toBe('12.50');
  });

  it("SKU'lu sayfada varyasyon matrisi yakalanır", () => {
    const ctx = baseContext();
    const skuParam = (ctx.result as any).data.detailModel.orderParamModel.orderParam.skuParam;
    skuParam.skuRangePrices = [
      { attributes: { 颜色: '白色' }, rangePrices: [{ price: '9.00', beginAmount: 24 }] },
      { attributes: { 颜色: '粉色' }, rangePrices: [{ price: '9.50', beginAmount: 24 }] },
    ];

    const result = parse1688(ctx, selectors, PAGE_URL);

    expect(result.normalized.sku_matrix).toHaveLength(2);
    expect(result.normalized.sku_matrix?.[0]).toEqual({ props: { 颜色: '白色' }, price_yuan: '9.00', min_qty: 24 });
  });

  it('videolu sayfada video id + poster RAW blokta taşınır', () => {
    const ctx = baseContext({
      gallery: {
        fields: {
          offerImgList: ['https://cbu01.alicdn.com/img/ibank/a.jpg'],
          videoId: 452123999,
          videoCoverUrl: 'https://cbu01.alicdn.com/kf/poster.jpg',
        },
      },
    });

    const result = parse1688(ctx, selectors, PAGE_URL);

    expect(result.raw.video).toEqual({ id: '452123999', poster: 'https://cbu01.alicdn.com/kf/poster.jpg' });
  });

  it('eksik alanlı sayfada ok:false + eksikler isim isim; og/DOM yedekleri devreye girer', () => {
    const bos = { result: { data: {} } };

    const yedeksiz = parse1688(bos, selectors, PAGE_URL);
    expect(yedeksiz.ok).toBe(false);
    expect(yedeksiz.missing).toContain('normalized.name');
    expect(yedeksiz.missing).toContain('normalized.price_yuan');
    // URL'den offerId yedeği yine çalışır (üç dallı fallback).
    expect(yedeksiz.source.external_id).toBe('895133432293');

    const yedekli = parse1688(bos, selectors, PAGE_URL, {
      ogTitle: 'OG Başlık Yedeği',
      ogImage: 'http://cbu01.alicdn.com/img/og.jpg',
      domPrice: '¥ 7.90',
    });
    expect(yedekli.ok).toBe(true);
    expect(yedekli.normalized.name).toBe('OG Başlık Yedeği');
    expect(yedekli.normalized.price_yuan).toBe('7.90');
    expect(yedekli.normalized.images[0]).toBe('https://cbu01.alicdn.com/img/og.jpg');
  });
});

describe('jsonpath — $ref ve HTML çıkarımı (rapor A.0)', () => {
  it('fastjson $ref göstergelerini çözer', () => {
    const ctx = {
      result: { data: { a: { weight: '1.2kg' }, b: { skuWeight: { $ref: '$.result.data.a.weight' } } } },
    };

    const resolved = resolveRefs(ctx) as any;

    expect(resolved.result.data.b.skuWeight).toBe('1.2kg');
  });

  it('HTML kaynağından window.context regex ile çıkar (fixture HTML)', () => {
    const html = `<html><head></head><body>
      <script>window.contextPath = "/default";
      window.context={"result":{"data":{"detailModel":{"offerDetail":{"offerId":123,"subject":"测试"}}}}} ;</script>
      </body></html>`;

    const ctx = extractContext(html, selectors.context.html_regex) as any;

    expect(ctx?.result?.data?.detailModel?.offerDetail?.subject).toBe('测试');
  });
});

describe('cleanImageUrl / extractTiers uç durumları', () => {
  it('bilinen lazy sonekleri temizler, bilinmeyene dokunmaz', () => {
    const strip = selectors.image_cleanup.strip_suffixes;
    expect(cleanImageUrl('https://x/a.jpg_.webp', strip)).toBe('https://x/a.jpg');
    expect(cleanImageUrl('https://x/a.jpg_310x310.jpg', strip)).toBe('https://x/a.jpg');
    expect(cleanImageUrl('https://x/a.png', strip)).toBe('https://x/a.png');
  });

  it('bozuk fiyat girdilerini eler (para STRING — K14)', () => {
    expect(extractTiers([{ price: 'abc', beginAmount: 1 }, { price: '5.5', beginAmount: 10 }])).toEqual([
      { min_qty: 10, price_yuan: '5.5' },
    ]);
    expect(extractTiers('bozuk')).toEqual([]);
  });
});
