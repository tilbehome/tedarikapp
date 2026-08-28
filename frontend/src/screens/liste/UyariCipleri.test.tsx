import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import UyariCipleri from './UyariCipleri';
import type { Product } from '../../api/types';

/**
 * UYARI ÇİPLERİ (İE#21 B2).
 *
 * Çipin sözü: haber verir VE götürür. Testler ikisini de tutar — sayı doğru,
 * tıklayınca süzgeç açılıyor, ikinci tıkta kapanıyor.
 */

const KATEGORI = { alan: 'category_id', etiket: 'Kategori' };
const FIYAT = { alan: 'price_yuan', etiket: 'Birim fiyat' };
const GORSEL = { alan: 'main_image', etiket: 'Ana görsel' };

function urun(id: number, fark: Partial<Product> = {}): Product {
  return {
    id,
    list_id: 1,
    sort_no: id,
    category_id: 4,
    platform: '1688',
    external_id: `X-${id}`,
    name: `Ürün ${id}`,
    name_original: null,
    detail: null,
    url: 'https://detail.1688.com/offer/1.html',
    vendor_name: null,
    vendor_url: null,
    sku_selection: { renk: 'siyah' },
    sku_matrix: null,
    main_image: 'https://cdn/x.jpg',
    video_url: null,
    qty: 10,
    price_yuan: '26.90',
    price_ddp_usd: '0.00',
    price_target_try: null,
    unit_profit_try: null,
    line_profit_try: null,
    price_yuan_tl: '0.00',
    price_ddp_tl: '0.00',
    line_total_yuan: '0.00',
    line_total_yuan_tl: '0.00',
    units_per_carton: null,
    tracking_no: null,
    status: 'to_order',
    hazir: false,
    hazir_eksikleri: [],
    note: null,
    images: [],
    created_at: '2026-08-23T10:00:00+03:00',
    updated_at: '2026-08-23T10:00:00+03:00',
    deleted_at: null,
    ...fark,
  } as Product;
}

describe('Çip sayar', () => {
  test('kaç üründe hangi eksik var — doğru sayıyla', () => {
    render(
      <UyariCipleri
        urunler={[urun(1, { hazir_eksikleri: [KATEGORI] }), urun(2, { hazir_eksikleri: [KATEGORI] }), urun(3, { hazir_eksikleri: [FIYAT] })]}
        secili={null}
        kurKilitli
        onSec={vi.fn()}
      />,
    );

    expect(screen.getByTestId('uyari-cip-category_id').textContent).toContain('2 üründe');
    expect(screen.getByTestId('uyari-cip-price_yuan').textContent).toContain('1 üründe');
  });

  test('eksik yoksa ve kur kilitliyse şerit hiç çizilmez', () => {
    render(<UyariCipleri urunler={[urun(1)]} secili={null} kurKilitli onSec={vi.fn()} />);

    expect(screen.queryByTestId('uyari-cipleri')).toBeNull();
  });

  test('kur kilitli değilse bu bilgi tek başına görünür', () => {
    render(<UyariCipleri urunler={[urun(1)]} secili={null} kurKilitli={false} onSec={vi.fn()} />);

    expect(screen.getByText('Kur henüz kilitlenmedi')).toBeTruthy();
  });
});

describe('Çip GÖTÜRÜR', () => {
  test('tıklayınca o alanın süzgeci istenir', async () => {
    const kullanici = userEvent.setup();
    const onSec = vi.fn();
    render(<UyariCipleri urunler={[urun(1, { hazir_eksikleri: [GORSEL] })]} secili={null} kurKilitli onSec={onSec} />);

    await kullanici.click(screen.getByTestId('uyari-cip-main_image'));

    expect(onSec).toHaveBeenCalledWith('main_image');
  });

  test('açık çipe tekrar tıklamak süzgeci KALDIRIR', async () => {
    const kullanici = userEvent.setup();
    const onSec = vi.fn();
    render(<UyariCipleri urunler={[urun(1, { hazir_eksikleri: [GORSEL] })]} secili="main_image" kurKilitli onSec={onSec} />);

    const cip = screen.getByTestId('uyari-cip-main_image');
    expect(cip.getAttribute('aria-pressed')).toBe('true');

    await kullanici.click(cip);
    expect(onSec).toHaveBeenCalledWith(null);
  });
});
