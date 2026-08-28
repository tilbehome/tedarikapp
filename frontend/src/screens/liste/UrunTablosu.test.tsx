import { describe, expect, test, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import UrunTablosu from './UrunTablosu';
import { VARSAYILAN, type TabloTercihi } from '../../lib/tabloTercihi';
import type { Product, SupplyList } from '../../api/types';

/**
 * ÜRÜN TABLOSU — sütun/yoğunluk/gruplama (İE#21 B2 cilaları).
 *
 * En önemli test "TOPLAM hizası": bir sütun gizlendiğinde toplam satırı kaymamalı.
 * Eski elle sayılan `<td>` düzeninde tam bu hata olurdu; sütunlar veri olarak
 * tanımlandığı için artık başlık, hücre ve toplam aynı kaynaktan üretiliyor.
 */

function liste(fark: Partial<SupplyList> = {}): SupplyList {
  return {
    id: 7,
    name: 'Mutfak Ürünleri',
    period: 'Eylül 2026',
    supplier_name: null,
    note: null,
    status: 'draft',
    visibility: 'active',
    yuan_rate: '7.0400',
    usd_rate: '41.5000',
    rate_locked_at: null,
    revision: 1,
    share_token_prefix: null,
    share_expires_at: null,
    product_count: 2,
    progress: {} as SupplyList['progress'],
    totals: { qty: 241, yuan: '6501.58', yuan_tl: '45771.12', ddp_usd: '0.00', ddp_tl: '0.00' },
    last_export: null,
    is_export_stale: false,
    created_at: '2026-08-23T10:00:00+03:00',
    updated_at: '2026-08-23T10:00:00+03:00',
    archived_at: null,
    deleted_at: null,
    ...fark,
  } as SupplyList;
}

function urun(id: number, fark: Partial<Product> = {}): Product {
  return {
    id,
    list_id: 7,
    sort_no: id,
    category_id: id,
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
    main_image: null,
    video_url: null,
    qty: 10,
    price_yuan: '26.90',
    price_ddp_usd: '0.00',
    price_target_try: null,
    unit_profit_try: null,
    line_profit_try: null,
    price_yuan_tl: '189.38',
    price_ddp_tl: '0.00',
    line_total_yuan: '269.00',
    line_total_yuan_tl: '1893.76',
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

const KATEGORILER: Record<number, string> = { 1: 'Mutfak', 2: 'Banyo' };

function kur(tercih: TabloTercihi = VARSAYILAN, urunler: Product[] = [urun(1), urun(2)]) {
  const onAc = vi.fn();
  const eylemler = {
    onDurum: vi.fn(),
    onMiktar: vi.fn(async () => {}),
    onHazir: vi.fn(),
    onSil: vi.fn(),
  };

  render(
    <MemoryRouter>
      <UrunTablosu
        liste={liste()}
        urunler={urunler}
        tercih={tercih}
        secili={[]}
        mesgulId={null}
        kategoriAdi={(id) => (id === null ? 'Kategorisiz' : KATEGORILER[id] ?? 'Kategorisiz')}
        gorsel={() => <span data-testid="gorsel-hucresi">gorsel</span>}
        siralamaBasligi={(anahtar, etiket) => <span data-testid={`sirala-${anahtar}`}>{etiket}</span>}
        eylemler={eylemler}
        onSecili={vi.fn()}
        onAc={onAc}
      />
    </MemoryRouter>,
  );

  return { eylemler, onAc };
}

describe('Sütun görünürlüğü', () => {
  test('tercihte olmayan sütun ÇİZİLMEZ', () => {
    kur({ ...VARSAYILAN, sutunlar: ['adet', 'durum'] });

    expect(screen.queryByTestId('gorsel-hucresi')).toBeNull();
    expect(screen.queryByText('$ DDP')).toBeNull();
    expect(screen.getByTestId('sirala-qty')).toBeTruthy();
  });

  test('Ürün sütunu her zaman durur — kapatılamaz', () => {
    kur({ ...VARSAYILAN, sutunlar: [] });

    expect(screen.getByTestId('sirala-name')).toBeTruthy();
    expect(screen.getAllByText('Ürün 1')).toHaveLength(1);
  });
});

describe('TOPLAM hizası sütun gizlenince BOZULMAZ', () => {
  test('görünür sütun sayısı başlıkta, satırda ve toplamda aynıdır', () => {
    kur({ ...VARSAYILAN, sutunlar: ['adet', 'satir_yuan'] });

    const tablo = screen.getByTestId('urun-tablosu');
    const basliklar = within(tablo).getAllByRole('columnheader');
    const satir = within(tablo).getAllByTestId('urun-satiri')[0];
    if (satir === undefined) throw new Error('Satır çizilmedi');
    const toplamSatiri = tablo.querySelector('tfoot tr');

    // seçim + Ürün + 2 sütun + silme = 5
    expect(basliklar).toHaveLength(5);
    expect(satir.querySelectorAll('td')).toHaveLength(5);
    // TOPLAM ilk iki hücreyi birleştirir: 1 (colSpan=2) + 2 sütun + 1 = 4
    expect(toplamSatiri?.querySelectorAll('td')).toHaveLength(4);
  });

  test('gizlenen sütunun toplamı da gizlenir', () => {
    kur({ ...VARSAYILAN, sutunlar: ['adet'] });

    // ₺ Satır sütunu kapalıyken onun toplamı ekranda kalmamalı.
    expect(screen.queryByText('₺45.771,12')).toBeNull();
    expect(screen.getByText('241')).toBeTruthy();
  });
});

describe('Yoğunluk', () => {
  test('tercih tabloya işlenir', () => {
    kur({ ...VARSAYILAN, yogunluk: 'sik' });

    expect(screen.getByTestId('urun-tablosu').getAttribute('data-yogunluk')).toBe('sik');
  });
});

describe('Gruplama', () => {
  test('kapalıyken grup başlığı yoktur', () => {
    kur();

    expect(screen.queryByTestId('grup-basligi')).toBeNull();
  });

  test('kategoriye göre gruplarken başlık ve sayı basılır', () => {
    kur({ ...VARSAYILAN, grupla: 'kategori' }, [urun(1), urun(2), urun(3, { category_id: 1 })]);

    const basliklar = screen.getAllByTestId('grup-basligi').map((satir) => satir.textContent);

    expect(basliklar).toHaveLength(2);
    expect(basliklar.join(' | ')).toContain('Mutfak · 2 ürün');
    expect(basliklar.join(' | ')).toContain('Banyo · 1 ürün');
  });

  test('duruma göre gruplama da çalışır', () => {
    kur({ ...VARSAYILAN, grupla: 'durum' }, [urun(1), urun(2, { status: 'ordered' })]);

    const basliklar = screen.getAllByTestId('grup-basligi').map((satir) => satir.textContent ?? '');

    expect(basliklar.some((metin) => metin.includes('Verilecek'))).toBe(true);
    expect(basliklar.some((metin) => metin.includes('Verildi'))).toBe(true);
  });

  test('gruplama ürün SAYISINI değiştirmez', () => {
    kur({ ...VARSAYILAN, grupla: 'kategori' }, [urun(1), urun(2), urun(3, { category_id: 1 })]);

    expect(screen.getAllByTestId('urun-satiri')).toHaveLength(3);
  });
});

describe('Ürüne tıklama çekmeceyi açar (B3)', () => {
  test('ad düğmesi ürünle birlikte onAc çağırır', async () => {
    const { onAc } = kur();

    const adlar = screen.getAllByTestId('urun-adi');
    adlar[0]?.click();

    expect(onAc).toHaveBeenCalledTimes(1);
    expect(onAc.mock.calls[0]?.[0]).toMatchObject({ id: 1 });
  });
});
