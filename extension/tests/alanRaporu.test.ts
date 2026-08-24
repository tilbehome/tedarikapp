import { describe, expect, it } from 'vitest';

import { alanRaporu, dolulukYuzdesi, gonderimiEngelleyenler } from '../core/alanRaporu';
import type { ParseResult } from '../core/types';

/**
 * ALAN RAPORU (İE#21 A3) — 16+ alan, kanal etiketi, doluluk ve eksik uyarısı.
 *
 * Kapsanan senaryolar: EKL-05 (tam yakalama önizlemesi), EKL-07 (SKU bütünlüğü),
 * EKL-09 (video pozitif/negatif), EKL-10 (kısmi okuma uyarısı).
 */

function sonuc(fark: Partial<ParseResult> = {}): ParseResult {
  return {
    ok: true,
    missing: [],
    source: {
      platform: '1688',
      external_id: '895133432293',
      url: 'https://detail.1688.com/offer/895133432293.html',
      seller_name: '义乌市盎燕电子商务有限公司',
      seller_url: 'https://shop.1688.com/x',
      captured_at: '2026-08-24T10:00:00+03:00',
    },
    raw: {
      title: '洞洞鞋男士2025夏季新款',
      price_blocks: null,
      images: ['a.jpg'],
      video: { id: '123', poster: 'p.jpg' },
      normalized_attributes: { 材质: 'EVA', 品牌: 'X' },
      min_order: 2,
      unit: '双',
      breadcrumb: ['家居鞋', '拖鞋'],
      origin_text: '中国',
    },
    normalized: {
      name: 'EVA Kaymaz Terlik',
      price_yuan: '15.90',
      price_tiers: [
        { min_qty: 1, price_yuan: '18.90' },
        { min_qty: 100, price_yuan: '15.90' },
      ],
      images: ['a.jpg', 'b.jpg'],
      sku_matrix: [
        { props: { renk: 'siyah' } },
        { props: { renk: 'gri' } },
      ],
      video_url: 'https://cdn/video.mp4',
      country_of_origin: 'CN',
    },
    ...fark,
  };
}

describe('Alan sayısı ve sıra', () => {
  it('16+ alan raporlanır', () => {
    const rapor = alanRaporu(sonuc());

    expect(rapor.toplam).toBeGreaterThanOrEqual(16);
  });

  it('ilk alanlar sipariş kararını etkileyenlerdir', () => {
    const adlar = alanRaporu(sonuc()).satirlar.map((s) => s.ad);

    expect(adlar.slice(0, 5)).toEqual([
      'Ürün adı',
      'Orijinal başlık',
      'Birim fiyat',
      'Fiyat kademeleri',
      'Varyantlar',
    ]);
  });
});

describe('E2E-EKL-05/07 — dolu alanlar ve kanal etiketi', () => {
  it('değerler özetlenir ve kanal işaretlenir', () => {
    const rapor = alanRaporu(sonuc());
    const bul = (ad: string) => rapor.satirlar.find((s) => s.ad === ad);

    expect(bul('Fiyat kademeleri')).toMatchObject({ deger: '2 kademe', dolu: true, kanal: 'GOMULU' });
    expect(bul('Varyantlar')).toMatchObject({ deger: '2 SKU', dolu: true });
    expect(bul('Kategori yolu')).toMatchObject({ deger: '家居鞋 › 拖鞋', kanal: 'SAYFA' });
    expect(bul('Birim fiyat')?.deger).toBe('¥15.90');
  });

  it('doluluk yüzdesi hesaplanır', () => {
    const rapor = alanRaporu(sonuc());

    expect(rapor.dolu).toBeGreaterThan(10);
    expect(dolulukYuzdesi(rapor)).toBeGreaterThan(60);
    expect(dolulukYuzdesi(rapor)).toBeLessThanOrEqual(100);
  });
});

describe('E2E-EKL-09 — video kararı pozitif ve negatif', () => {
  it('oynatılabilir video adresi varsa dolu sayılır', () => {
    const bul = alanRaporu(sonuc()).satirlar.find((s) => s.ad === 'Video');

    expect(bul?.dolu).toBe(true);
  });

  it('adres yok ama ilan video taşıyorsa "ilanda var" denir', () => {
    const veri = sonuc();
    veri.normalized.video_url = null;

    const bul = alanRaporu(veri).satirlar.find((s) => s.ad === 'Video');

    expect(bul).toMatchObject({ dolu: true, deger: 'ilanda var' });
  });

  it('hiç video yoksa UYDURULMAZ', () => {
    const veri = sonuc();
    veri.normalized.video_url = null;
    veri.raw.video = null;

    const bul = alanRaporu(veri).satirlar.find((s) => s.ad === 'Video');

    expect(bul).toMatchObject({ dolu: false, deger: 'video yok', kanal: null });
  });
});

describe('E2E-EKL-10 — eksik alanlar açıkça söylenir', () => {
  it('sayfada olmayan alanlar "panelde elle girilir" der', () => {
    const rapor = alanRaporu(sonuc());
    const koli = rapor.satirlar.find((s) => s.ad === 'Koli ölçüsü');

    expect(koli).toMatchObject({ dolu: false });
    expect(koli?.deger).toContain('elle girilir');
    expect(rapor.eksikler).toContain('Koli ölçüsü');
  });

  it('boş SKU listesi "varyant bulunamadı" der, sıfır SKU yazmaz', () => {
    const veri = sonuc();
    veri.normalized.sku_matrix = null;

    const bul = alanRaporu(veri).satirlar.find((s) => s.ad === 'Varyantlar');

    expect(bul).toMatchObject({ dolu: false, deger: 'varyant bulunamadı' });
  });
});

describe('Gönderimi engelleyen eksikler', () => {
  it('ad/fiyat/adres eksikse gönderim engellenir', () => {
    const veri = sonuc();
    veri.normalized.name = '';
    veri.normalized.price_yuan = '';

    const engel = gonderimiEngelleyenler(alanRaporu(veri));

    expect(engel).toEqual(['Ürün adı', 'Birim fiyat']);
  });

  it('yalnız bilgi eksikleri gönderimi engellemez', () => {
    const veri = sonuc();
    veri.raw.breadcrumb = [];

    const rapor = alanRaporu(veri);

    expect(rapor.eksikler).toContain('Kategori yolu');
    expect(gonderimiEngelleyenler(rapor)).toEqual([]);
  });
});
