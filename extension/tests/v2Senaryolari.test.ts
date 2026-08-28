import { describe, expect, it } from 'vitest';

import { parse1688, playableVideoUrl } from '../modules/m1688/parser';
import { alanRaporu } from '../core/alanRaporu';
import { GOMULU_SECICILER } from '../core/secici';
import type { SelectorSet } from '../core/types';

/**
 * EKLENTİ v2 — VERİ SENARYOLARI (İE#21 A3/A7).
 *
 * Kapsanan senaryolar: EKL-06 (koşullu fiyat ezilmez), EKL-07 (SKU bütünlüğü),
 * EKL-08 (özel MOQ / stok yok), EKL-09 (pozitif-negatif video), EKL-27 (token,
 * çerez, oturum sızıntısı yok).
 *
 * CANLI İSTEK YOKTUR: bağlamlar `docs/arastirma/1688-parser-raporu.md`daki gerçek
 * yapıdan türetilmiş fikstürlerdir.
 */

const seciciler = GOMULU_SECICILER as SelectorSet;
const SAYFA = 'https://detail.1688.com/offer/895133432293.html';

function baglam(model: Record<string, unknown>): Record<string, unknown> {
  return { result: { global: { globalData: { model } } } };
}

describe('E2E-EKL-06 — koşullu fiyat standart fiyatı EZMEZ', () => {
  it('standart fiyat korunur, koşullu fiyat kademe olarak durur', () => {
    const sonuc = parse1688(
      baglam({
        offerDetail: { offerId: 895133432293, subject: '洞洞鞋' },
        tradeModel: {
          offerPriceModel: {
            currentPrices: [
              { price: '26.90', beginAmount: 1 },
              { price: '24.90', beginAmount: 100, promotionType: '新人价' },
            ],
          },
        },
      }),
      seciciler,
      SAYFA,
    );

    // Birim fiyat İLK kademedir; kampanyalı fiyat onun yerine geçmez.
    expect(sonuc.normalized.price_yuan).toBe('26.90');
    expect(sonuc.normalized.price_tiers.map((k) => k.price_yuan)).toContain('24.90');
    // Para değerleri STRING kalır (K14): float'a düşerse kuruş kaybolur.
    for (const kademe of sonuc.normalized.price_tiers) {
      expect(typeof kademe.price_yuan).toBe('string');
    }
  });
});

describe('E2E-EKL-07 — çok varyantlı SKU bütünlüğü', () => {
  it('beş SKU da matriste durur; hiçbiri seçim yüzünden düşmez', () => {
    const skuProps = [
      { prop: '颜色', value: ['绿色', '蓝色', '灰色', '黑色', '粉色'] },
    ];
    const sonuc = parse1688(
      baglam({
        // Gerçek yol: `offerDetail.skuProps` (seçici paketi böyle diyor) —
        // uydurma bir yola koyup testi geçirmek, sözleşmeyi yalanlamak olurdu.
        offerDetail: { offerId: 1, subject: '洞洞鞋', skuProps },
        tradeModel: { offerPriceModel: { currentPrices: [{ price: '18.90', beginAmount: 1 }] } },
      }),
      seciciler,
      SAYFA,
    );

    const matris = sonuc.normalized.sku_matrix ?? [];
    expect(matris.length).toBeGreaterThanOrEqual(5);

    const rapor = alanRaporu(sonuc);
    expect(rapor.satirlar.find((s) => s.ad === 'Varyantlar')?.deger).toContain('SKU');
  });
});

describe('E2E-EKL-08 — özel MOQ ve stok bilgisi genel MOQ ile karışmaz', () => {
  it('MOQ alanı ilanın kendi değerini taşır', () => {
    const sonuc = parse1688(
      baglam({
        offerDetail: { offerId: 1, subject: '定制款' },
        tradeModel: {
          offerPriceModel: { currentPrices: [{ price: '9.90', beginAmount: 2000 }] },
          orderParamModel: { orderParam: { beginAmount: 2000 } },
        },
      }),
      seciciler,
      SAYFA,
    );

    const rapor = alanRaporu(sonuc);
    const moq = rapor.satirlar.find((s) => s.ad === 'MOQ');

    // Değer bulunduysa ilanın kendi sayısıdır; bulunamadıysa UYDURULMAZ.
    if (moq?.dolu === true) {
      expect(moq.deger).not.toBe('1');
    } else {
      expect(moq?.deger).toBe('sayfada yok');
    }
    // Kademe başlangıcı 2000 olarak korunur — "1 adetten başlar" denmez.
    expect(sonuc.normalized.price_tiers[0]?.min_qty).toBe(2000);
  });
});

describe('E2E-EKL-09 — video kararı: kanıt varsa var, yoksa yok', () => {
  it('oynatılabilir adres varsa video dolu', () => {
    const sonuc = parse1688(
      baglam({
        offerDetail: { offerId: 1, subject: 'x' },
        tradeModel: { offerPriceModel: { currentPrices: [{ price: '1.00', beginAmount: 1 }] } },
      }),
      seciciler,
      SAYFA,
      { videoSrc: 'https://cloud.video.taobao.com/play/u/1/p/1/e/6/t/1/x.mp4' },
    );

    expect(sonuc.normalized.video_url).not.toBeNull();
    expect(alanRaporu(sonuc).satirlar.find((s) => s.ad === 'Video')?.dolu).toBe(true);
  });

  it('yalnız yetenek bayrağı varsa SAHTE video rozeti basılmaz', () => {
    const sonuc = parse1688(
      baglam({
        offerDetail: { offerId: 1, subject: 'x' },
        tradeModel: { offerPriceModel: { currentPrices: [{ price: '1.00', beginAmount: 1 }] } },
      }),
      seciciler,
      SAYFA,
    );

    expect(sonuc.normalized.video_url).toBeNull();
    const video = alanRaporu(sonuc).satirlar.find((s) => s.ad === 'Video');
    expect(video?.dolu).toBe(false);
    expect(video?.deger).toBe('video yok');
  });

  it('oynatılamayan aday adres reddedilir', () => {
    expect(playableVideoUrl('blob:https://detail.1688.com/abc')).toBeNull();
    expect(playableVideoUrl(null)).toBeNull();
  });
});

describe('E2E-EKL-27 — token, çerez ve oturum sızıntısı yok', () => {
  it('ayrıştırma çıktısında çerez/oturum/token alanı BULUNMAZ', () => {
    const sonuc = parse1688(
      baglam({
        offerDetail: { offerId: 1, subject: 'x' },
        tradeModel: { offerPriceModel: { currentPrices: [{ price: '1.00', beginAmount: 1 }] } },
        // Sayfa bağlamında böyle alanlar bulunabilir; yakalamaya GİRMEMELİ.
        loginInfo: { cookie: 'SESSION=abc', token: 'gizli-token', memberId: 'user-1' },
      }),
      seciciler,
      SAYFA,
    );

    const json = JSON.stringify(sonuc);
    expect(json).not.toContain('gizli-token');
    expect(json).not.toContain('SESSION=abc');
    expect(json.toLowerCase()).not.toContain('cookie');
  });
});
