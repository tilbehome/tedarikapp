/**
 * 1688 parser fixture testleri (İE#11 kabul + F20): CANLI istek YOK — fixture'lar
 * docs/arastirma/1688-parser-raporu.md'deki gerçek yapı örneklerinden türetildi.
 * Senaryolar: tekli fiyat · kademeli fiyat · SKU'lu · videolu · eksik alanlı + $ref.
 */

import { describe, expect, it } from 'vitest';
import { cleanImageUrl, extractBreadcrumb, extractTiers, parse1688, playableVideoUrl } from '../modules/m1688/parser';
import { extractContext, resolveRefs } from '../core/jsonpath';
import selectorsJson from './fixtures/selectors-1688.json';
import type { SelectorSet } from '../core/types';

const selectors = selectorsJson as unknown as SelectorSet;
const PAGE_URL = 'https://detail.1688.com/offer/895133432293.html';

/**
 * GERÇEK sayfa yapısı (İE#11 EK-3 (1) — rapor §13 ve §1017): birincil kaynak
 * `result.global.globalData.model`; UI modüllerinin `fields`'ı BOŞ gelir
 * (skuSelection/productAttributes) — testler bu gerçeği kanıtlar.
 */
function baseContext(modelOverrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    result: {
      global: {
        globalData: {
          traceId: 'test-trace',
          parametersMap: { offerId: '895133432293', loginId: 'tb688704032941' },
          model: {
            offerDetail: {
              offerId: 895133432293,
              subject: '跨境榨汁机便携式小型水果机',
              mainImageList: [
                { fullPathImageURI: 'https://cbu01.alicdn.com/img/ibank/a.jpg_.webp' },
                { fullPathImageURI: 'http://cbu01.alicdn.com/img/ibank/b.jpg' },
              ],
              skuProps: null,
              featureAttributes: [
                { fid: 2176, name: '品牌', value: '总裁小姐', values: ['总裁小姐'] },
                { fid: 2340, name: '容量', value: '350ml', values: ['350ml'] },
              ],
              wirelessVideo: { videoId: 0, state: 0 },
              leafCategoryName: '榨汁机',
            },
            tradeModel: {
              beginAmount: 2,
              unit: '个',
              offerPriceModel: { currentPrices: [{ price: '9.00', beginAmount: 2 }] },
              skuMap: null,
            },
            sellerModel: {
              companyName: '永康市测试有限公司',
              loginId: 'tb688704032941',
              winportUrl: 'https://shop.1688.com/x',
            },
            ...modelOverrides,
          },
        },
      },
      // UI modülleri: veri YOK (gerçek sayfada bu modüllerin fields'ı boş gelir).
      data: {
        skuSelection: { fields: { uiType: 'od_sku_selection', label: '规格' } },
        productAttributes: { fields: { uiType: 'od_product_attributes', label: '商品参数' } },
        gallery: { fields: {} },
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
    expect(result.source.seller_url).toBe('https://shop.1688.com/x');
    // EK-3 (1) kanıtı: modül fields BOŞken globalData.model dalından okundu.
    expect((baseContext().result as any).data.productAttributes.fields.attributes).toBeUndefined();
    expect(result.raw.normalized_attributes).toEqual({ 品牌: '总裁小姐', 容量: '350ml' });
    // wirelessVideo.videoId === 0 → video YOK (rapor §A.6).
    expect(result.raw.video).toBeNull();
    expect(result.raw.min_order).toBe(2);
    expect(result.raw.unit).toBe('个');
  });

  it('kademeli fiyatta EN DÜŞÜK kademe birim fiyat olur, tüm kademeler taşınır', () => {
    const ctx = baseContext();
    (ctx.result as any).global.globalData.model.tradeModel.offerPriceModel.currentPrices = [
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
    // GERÇEK yapı (rapor §4.2): tradeModel.skuMap[] → {skuId, specAttrs, price, discountPrice}
    const ctx = baseContext();
    (ctx.result as any).global.globalData.model.tradeModel.skuMap = [
      { skuId: 5745692521573, specAttrs: 'Z03榨汁杯（3.7V单电池）', price: '48.00', discountPrice: '48.00', canBookCount: 9998 },
      { skuId: 5745692521572, specAttrs: 'Z03榨汁杯（7.4V双电池）', price: '58.00', discountPrice: '58.00', canBookCount: 9972 },
    ];

    const result = parse1688(ctx, selectors, PAGE_URL);

    expect(result.normalized.sku_matrix).toHaveLength(2);
    expect(result.normalized.sku_matrix?.[0]).toEqual({
      props: { 'seçenek': 'Z03榨汁杯（3.7V单电池）' },
      price_yuan: '48.00',
      min_qty: 1,
    });
  });

  it('videolu sayfada video id + poster RAW blokta taşınır', () => {
    const ctx = baseContext();
    (ctx.result as any).global.globalData.model.offerDetail.wirelessVideo = {
      videoId: 452123999,
      state: 1,
      imageUrl: 'https://cbu01.alicdn.com/kf/poster.jpg',
    };

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

describe('EK-3 — ikincil dal ve menşe', () => {
  it('globalData YOKKEN ikincil detailModel dalından okur (geriye uyum)', () => {
    const eski = {
      result: {
        data: {
          detailModel: {
            offerDetail: { offerId: 111222333, subject: 'Eski yapı ürünü' },
            orderParamModel: { orderParam: { skuParam: { currentPrices: [{ price: '4.20', beginAmount: 5 }] } } },
          },
        },
      },
    };

    const result = parse1688(eski, selectors, PAGE_URL);

    expect(result.ok).toBe(true);
    expect(result.source.external_id).toBe('111222333');
    expect(result.normalized.name).toBe('Eski yapı ürünü');
    expect(result.normalized.price_yuan).toBe('4.20');
  });

  it('menşe özniteliği varsa ülke CN olur, yoksa null (uydurma yok)', () => {
    const ctx = baseContext();
    (ctx.result as any).global.globalData.model.offerDetail.featureAttributes.push({
      fid: 9999,
      name: '产地',
      value: '浙江',
      values: ['浙江'],
    });

    const menseli = parse1688(ctx, selectors, PAGE_URL);
    expect(menseli.raw.origin_text).toBe('浙江');
    expect(menseli.normalized.country_of_origin).toBe('CN');

    expect(parse1688(baseContext(), selectors, PAGE_URL).normalized.country_of_origin).toBeNull();
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

/**
 * İE#14 A4 — KIRINTI YOLU (面包屑): kategori bilgisinin kaynağı.
 *
 * Panelde "Kategorisiz" basılmasının nedeni buydu: kategori hiç yakalanmıyordu.
 * Üç biçim de (dizi, nesne dizisi, ayraçlı metin) aynı listeye inmelidir.
 */
describe('extractBreadcrumb — kategori kırıntı yolu (İE#14 A4)', () => {
  it('düz metin dizisini olduğu gibi alır', () => {
    expect(extractBreadcrumb(['家居', '厨房用品', '保温杯'])).toEqual(['家居', '厨房用品', '保温杯']);
  });

  it('nesne dizisinden ad alanlarını çıkarır', () => {
    expect(
      extractBreadcrumb([{ name: '家居' }, { categoryName: '厨房用品' }, { text: '保温杯' }]),
    ).toEqual(['家居', '厨房用品', '保温杯']);
  });

  it('ayraçlı tek metni böler', () => {
    expect(extractBreadcrumb('家居 > 厨房用品 > 收纳')).toEqual(['家居', '厨房用品', '收纳']);
  });

  it('JSON yoksa DOM yedeğine düşer, tekrarları ve boşları eler', () => {
    expect(extractBreadcrumb(null, ['家居', '', '家居', '厨房用品'])).toEqual(['家居', '厨房用品']);
  });

  it('hiçbir kaynak yoksa boş liste döner (uydurma YOK)', () => {
    expect(extractBreadcrumb(undefined, null)).toEqual([]);
  });

  it('parse1688 çıktısında raw.breadcrumb taşınır', () => {
    const context = {
      result: {
        global: {
          globalData: {
            model: {
              offerDetail: {
                offerId: 833438962156,
                subject: '双层不锈钢保温饭盒',
                categoryPath: ['首页', '家居', '保温杯'],
              },
            },
          },
        },
      },
    };
    const sonuc = parse1688(context, selectors, 'https://detail.1688.com/offer/833438962156.html', {
      domPrice: '12.00',
    });

    expect(sonuc.raw.breadcrumb).toEqual(['首页', '家居', '保温杯']);
  });
});

/**
 * İE#15 E2 — VİDEO ADRESİ: yalnız GERÇEKTEN oynatılabilecek adres taşınır.
 *
 * Çalışmayacak bir adresi taşımak "video yok" demekten kötüdür: paylaşım
 * sayfasında rozet çıkar, modal açılır ve boş kalır.
 */
describe('playableVideoUrl — oynatılabilir video adresi (İE#15 E2)', () => {
  it('https mp4/m3u8/webm kabul eder', () => {
    expect(playableVideoUrl('https://cloud.video.taobao.com/play/x.mp4')).toBe(
      'https://cloud.video.taobao.com/play/x.mp4',
    );
    expect(playableVideoUrl('https://x/y.m3u8?auth=1')).toBe('https://x/y.m3u8?auth=1');
  });

  it('blob/data/http ve uzantısız adresleri REDDEDER', () => {
    expect(playableVideoUrl('blob:https://detail.1688.com/abc')).toBeNull();
    expect(playableVideoUrl('data:video/mp4;base64,AAAA')).toBeNull();
    expect(playableVideoUrl('http://x/y.mp4')).toBeNull();
    expect(playableVideoUrl('https://detail.1688.com/offer/1.html')).toBeNull();
    expect(playableVideoUrl(null)).toBeNull();
  });

  it('DOM video adresi parse çıktısına taşınır', () => {
    const sonuc = parse1688(
      { result: { global: { globalData: { model: { offerDetail: { offerId: 1, subject: 'x' } } } } } },
      selectors,
      'https://detail.1688.com/offer/1.html',
      { domPrice: '9.90', videoSrc: 'https://cloud.video.taobao.com/play/x.mp4' },
    );

    expect(sonuc.normalized.video_url).toBe('https://cloud.video.taobao.com/play/x.mp4');
  });
});
