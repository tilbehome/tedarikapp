import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TopluEylemCubugu from './TopluEylemCubugu';
import type { Product } from '../../api/types';

/**
 * TOPLU EYLEM ÇUBUĞU (E2E-PNL-24 · E2E-PNL-31).
 *
 * İki söz sınanır:
 *   · canlı toplam — seçim değiştikçe adet toplamı değişir,
 *   · çift tık mükerrer işlem üretmez — istek uçarken düğmeler kapanır.
 */

function urun(id: number, qty: number): Product {
  return {
    id,
    list_id: 1,
    sort_no: id,
    category_id: null,
    platform: null,
    external_id: null,
    name: `Ürün ${id}`,
    name_original: null,
    detail: null,
    url: null,
    vendor_name: null,
    vendor_url: null,
    sku_selection: null,
    sku_matrix: null,
    main_image: null,
    main_image_uzak: false,
    main_image_gosterim: null,
    media_pending: false,
    video_url: null,
    qty,
    price_yuan: '10.00',
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
    created_at: '2026-08-24T10:00:00+03:00',
    updated_at: '2026-08-24T10:00:00+03:00',
    deleted_at: null,
  } as Product;
}

const URUNLER = [urun(1, 240), urun(2, 60), urun(3, 5)];

function kur(secili: number[], mesgul = false) {
  const onDurum = vi.fn();
  const onSil = vi.fn();
  const onTemizle = vi.fn();

  render(
    <TopluEylemCubugu
      secili={secili}
      urunler={URUNLER}
      mesgul={mesgul}
      onDurum={onDurum}
      onSil={onSil}
      onTemizle={onTemizle}
    />,
  );

  return { onDurum, onSil, onTemizle };
}

describe('Görünürlük', () => {
  test('seçim yoksa çubuk çizilmez', () => {
    kur([]);

    expect(screen.queryByTestId('toplu-eylem-cubugu')).toBeNull();
  });
});

describe('E2E-PNL-31 — canlı toplam', () => {
  test('seçilen ürünlerin adedi toplanır', () => {
    kur([1, 2]);

    expect(screen.getByTestId('toplu-ozet').textContent).toBe('2 ürün seçildi · 300 adet');
  });

  test('seçim değişince toplam da değişir', () => {
    kur([3]);

    expect(screen.getByTestId('toplu-ozet').textContent).toBe('1 ürün seçildi · 5 adet');
  });

  test('para toplamı GÖSTERİLMEZ — panel kuruş toplamaz (K14)', () => {
    kur([1, 2]);

    const metin = screen.getByTestId('toplu-eylem-cubugu').textContent ?? '';
    expect(metin).not.toContain('₺');
    expect(metin).not.toContain('¥');
  });
});

describe('E2E-PNL-24 — çift tık mükerrer işlem üretmez', () => {
  test('istek uçarken tüm düğmeler kapalıdır', () => {
    kur([1], true);

    expect((screen.getByTestId('toplu-ordered') as HTMLButtonElement).disabled).toBe(true);
    expect((screen.getByTestId('toplu-sil') as HTMLButtonElement).disabled).toBe(true);
    expect((screen.getByTestId('toplu-temizle') as HTMLButtonElement).disabled).toBe(true);
  });

  test('kapalı düğmeye ikinci tık eylem tetiklemez', async () => {
    const kullanici = userEvent.setup();
    const { onDurum } = kur([1], true);

    await kullanici.click(screen.getByTestId('toplu-ordered'));

    expect(onDurum).not.toHaveBeenCalled();
  });
});

describe('Eylemler', () => {
  test('durum düğmesi hedefi yukarı verir', async () => {
    const kullanici = userEvent.setup();
    const { onDurum } = kur([1, 2]);

    await kullanici.click(screen.getByTestId('toplu-in_transit'));

    expect(onDurum).toHaveBeenCalledWith('in_transit');
  });

  test('çöp kutusu ve temizleme ayrı eylemlerdir', async () => {
    const kullanici = userEvent.setup();
    const { onSil, onTemizle } = kur([1]);

    await kullanici.click(screen.getByTestId('toplu-sil'));
    await kullanici.click(screen.getByTestId('toplu-temizle'));

    expect(onSil).toHaveBeenCalledTimes(1);
    expect(onTemizle).toHaveBeenCalledTimes(1);
  });
});
