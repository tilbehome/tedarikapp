/**
 * Fiyat gösterimi testleri (İE#13 A3) — canlı vakayı çivileyen süit:
 * "1+ → ¥35 · 1+ → ¥1040" bir daha üretilemez.
 */

import { describe, expect, it } from 'vitest';
import { compareDecimal, normalizeTiers, priceLabel, skuPriceRange, variationLabel } from '../modules/m1688/format';
import { parse1688 } from '../modules/m1688/parser';
import selectorsJson from './fixtures/selectors-1688.json';
import type { SelectorSet } from '../core/types';

const selectors = selectorsJson as unknown as SelectorSet;

describe('compareDecimal — float kullanmadan ondalık karşılaştırma (K14)', () => {
  it('basamak sayısına göre büyüklüğü doğru bulur', () => {
    expect(compareDecimal('1040', '35')).toBeGreaterThan(0);
    expect(compareDecimal('35', '1040')).toBeLessThan(0);
    expect(compareDecimal('9', '10')).toBeLessThan(0);
  });

  it('ondalık kısmı hizalayarak karşılaştırır ve eşitliği görür', () => {
    expect(compareDecimal('12.5', '12.50')).toBe(0);
    expect(compareDecimal('12.50', '12.49')).toBeGreaterThan(0);
    expect(compareDecimal('0.10', '0.9')).toBeLessThan(0);
    expect(compareDecimal('007.20', '7.2')).toBe(0);
  });
});

describe('normalizeTiers — sahte kademeler erir', () => {
  it('aynı min_qty birden çok fiyatla geldiyse EN DÜŞÜK kalır', () => {
    expect(
      normalizeTiers([
        { min_qty: 1, price_yuan: '1040' },
        { min_qty: 1, price_yuan: '35' },
      ]),
    ).toEqual([{ min_qty: 1, price_yuan: '35' }]);
  });

  it('gerçek kademeleri min_qty artan sırada korur', () => {
    expect(
      normalizeTiers([
        { min_qty: 100, price_yuan: '11.80' },
        { min_qty: 2, price_yuan: '12.50' },
      ]),
    ).toEqual([
      { min_qty: 2, price_yuan: '12.50' },
      { min_qty: 100, price_yuan: '11.80' },
    ]);
  });
});

describe('priceLabel — üç ayrı gösterim', () => {
  it('gerçek kademeleri kademe olarak yazar', () => {
    expect(
      priceLabel({
        price_tiers: [
          { min_qty: 2, price_yuan: '12.50' },
          { min_qty: 100, price_yuan: '11.80' },
        ],
        price_yuan: '12.50',
        sku_matrix: null,
      }),
    ).toBe('2+ → ¥12.50 · 100+ → ¥11.80');
  });

  it('SKU fiyatları farklıysa ARALIK yazar (canlı vaka)', () => {
    expect(
      priceLabel({
        price_tiers: [{ min_qty: 1, price_yuan: '35' }],
        price_yuan: '35',
        sku_matrix: [
          { props: { seçenek: 'A' }, price_yuan: '35' },
          { props: { seçenek: 'B' }, price_yuan: '1040' },
        ],
      }),
    ).toBe('¥35–¥1040');
  });

  it('tüm SKU fiyatları aynıysa tek fiyat yazar', () => {
    expect(
      priceLabel({
        price_tiers: [{ min_qty: 1, price_yuan: '48.00' }],
        price_yuan: '48.00',
        sku_matrix: [
          { props: { seçenek: 'A' }, price_yuan: '48.00' },
          { props: { seçenek: 'B' }, price_yuan: '48.00' },
        ],
      }),
    ).toBe('¥48.00');
  });

  it('fiyat hiç yoksa boş döner (çağıran uyarı gösterir)', () => {
    expect(priceLabel({ price_tiers: [], price_yuan: '', sku_matrix: null })).toBe('');
  });
});

describe('skuPriceRange / variationLabel', () => {
  it('fiyatsız matriste aralık yoktur', () => {
    expect(skuPriceRange([{ props: { renk: 'Gri' } }])).toBeNull();
  });

  it('varyasyon özeti seçenek sayısını verir, uzun listeyi kısaltır', () => {
    expect(variationLabel([{ props: { renk: 'Gri' } }, { props: { renk: 'Mavi' } }])).toBe('Gri · Mavi (2 seçenek)');
    expect(variationLabel(Array.from({ length: 5 }, (_, i) => ({ props: { renk: `R${i}` } })))).toBe(
      'R0 · R1 · R2 … (5 seçenek)',
    );
    expect(variationLabel(null)).toBe('');
  });
});

describe('parse1688 — SKU fiyatları sahte kademeye dönüşmez (A3 regresyonu)', () => {
  it('beginAmount taşımayan SKU fiyatları tek kademeye iner, birim fiyat EN DÜŞÜK olur', () => {
    const ctx = {
      result: {
        global: {
          globalData: {
            model: {
              offerDetail: { offerId: 895133432293, subject: '测试商品', mainImageList: [] },
              tradeModel: {
                offerPriceModel: { currentPrices: [] },
                skuMap: [
                  { skuId: 1, specAttrs: 'Büyük', price: '1040.00' },
                  { skuId: 2, specAttrs: 'Küçük', price: '35.00' },
                ],
              },
            },
          },
        },
      },
    };

    const result = parse1688(ctx, selectors, 'https://detail.1688.com/offer/895133432293.html');

    expect(result.normalized.price_tiers).toEqual([{ min_qty: 1, price_yuan: '35.00' }]);
    expect(result.normalized.price_yuan).toBe('35.00');
    expect(priceLabel(result.normalized)).toBe('¥35.00–¥1040.00');
  });
});
