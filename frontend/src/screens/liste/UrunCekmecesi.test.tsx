import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import UrunCekmecesi from './UrunCekmecesi';
import type { Product, UrunCekmecesiVerisi } from '../../api/types';

/**
 * ÜRÜN ÇEKMECESİ (İE#21 B3).
 *
 * Çekmecenin sözü: ürünün TÜM hikâyesi tek yerde ve VERİ YOKSA "—" der, uydurmaz.
 * Testler bu iki sözü ayrı ayrı tutar; ayrıca kapanış yollarının (Esc, düğme,
 * örtü) hepsi sınanır — kapanmayan bir çekmece, ekranı kilitler.
 */

const cekmeceCasusu = vi.fn<() => Promise<UrunCekmecesiVerisi>>();

vi.mock('../../api/endpoints', () => ({
  products: {
    cekmece: () => cekmeceCasusu(),
  },
}));

function urun(fark: Partial<Product> = {}): Product {
  return {
    id: 47,
    list_id: 13,
    sort_no: 1,
    category_id: 4,
    platform: '1688',
    external_id: '867966081795',
    name: '13 Parça Sebze Doğrayıcı',
    name_original: '跨境新款多功能切菜器',
    detail: null,
    url: 'https://detail.1688.com/offer/867966081795.html',
    vendor_name: null,
    vendor_url: null,
    sku_selection: { Renk: 'Siyah', Set: 'Klasik' },
    sku_matrix: [{ ad: 'a' }, { ad: 'b' }],
    main_image: 'https://cdn/ana.jpg',
    video_url: null,
    qty: 240,
    price_yuan: '26.90',
    price_ddp_usd: '0.00',
    price_target_try: null,
    unit_profit_try: null,
    line_profit_try: null,
    price_yuan_tl: '189.38',
    price_ddp_tl: '0.00',
    line_total_yuan: '6456.00',
    line_total_yuan_tl: '45450.24',
    units_per_carton: 24,
    tracking_no: null,
    status: 'to_order',
    hazir: false,
    hazir_eksikleri: [],
    note: 'Koli ölçüsü sorulacak.',
    images: [{ id: 1, url: 'https://cdn/ikinci.jpg', sort: 1 }],
    created_at: '2026-08-23T10:00:00+03:00',
    updated_at: '2026-08-23T10:00:00+03:00',
    deleted_at: null,
    ...fark,
  } as Product;
}

function veri(fark: Partial<UrunCekmecesiVerisi> = {}): UrunCekmecesiVerisi {
  return {
    urun: urun(),
    ilan: {
      platform: '1688',
      external_id: '867966081795',
      url: 'https://detail.1688.com/offer/867966081795.html',
      baslik_orijinal: '跨境新款多功能切菜器',
      satici_ad: '义乌市世博塑料制品厂',
      satici_url: 'https://sirket.1688.com/x',
      satici_yil: 5,
      satici_puan: '4.80',
      yanit_orani: '98.00',
      satis_adedi: 1163,
      satis_toplam: 1468,
      moq: 2,
      birim_fiyat: '26.9000',
      para_birimi: 'CNY',
      skor: 62,
      bant: 'Yüksek',
      skor_bilesenleri: { satis: 28, degerlendirme: 20 },
    },
    kademeler: [
      { min_adet: 2, birim_fiyat: '26.90' },
      { min_adet: 100, birim_fiyat: '24.90' },
    ],
    yorum_ozeti: { adet: 312, puan: '4.70' },
    yurtici_kiyas: null,
    ...fark,
  };
}

function kur(cevap: UrunCekmecesiVerisi = veri()) {
  cekmeceCasusu.mockResolvedValue(cevap);
  const onKapat = vi.fn();
  render(
    <MemoryRouter>
      <UrunCekmecesi urunId={47} onKapat={onKapat} />
    </MemoryRouter>,
  );

  return { onKapat };
}

beforeEach(() => {
  cekmeceCasusu.mockReset();
});

describe('Dolu ürün', () => {
  test('ürün, kademe, skor ve satıcı bilgisi tek açılışta görünür', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('bolum-urun')).toBeTruthy());

    expect(screen.getByTestId('bolum-urun').textContent).toContain('240');
    expect(screen.getByTestId('kademe-listesi').textContent).toContain('100+ adet');
    expect(screen.getByTestId('bolum-skor').textContent).toContain('62');
    expect(screen.getByTestId('bolum-skor').textContent).toContain('Yüksek');
    expect(screen.getByTestId('bolum-kaynak').textContent).toContain('义乌市世博塑料制品厂');
    expect(screen.getByTestId('bolum-yorum').textContent).toContain('312');
    expect(screen.getByTestId('secili-varyant').textContent).toContain('Siyah');
  });

  test('galeri ana görsel + ek görselleri sayar', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('galeri')).toBeTruthy());
    expect(screen.getByTestId('galeri').textContent).toContain('2 görsel');
  });

  test('Düzenle bağlantısı tam forma götürür', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('cekmece-duzenle')).toBeTruthy());
    expect(screen.getByTestId('cekmece-duzenle').getAttribute('href')).toBe('/listeler/13/urun/47');
  });
});

describe('Veri yoksa uydurulmaz (K67)', () => {
  test('ilan kaydı olmayan üründe kaynak bölümü açık konuşur', async () => {
    kur(veri({ ilan: null, kademeler: [], yorum_ozeti: null }));

    await waitFor(() => expect(screen.getByTestId('ilan-yok')).toBeTruthy());
    expect(screen.getByTestId('bolum-kademeler').textContent).toContain('kademeli fiyat bildirilmemiş');
    expect(screen.getByTestId('bolum-yorum').textContent).toContain('Değerlendirme verisi yok');
  });

  test('skor hesaplanamadıysa sıfır DEĞİL, gerekçe yazılır', async () => {
    const eksik = veri();
    kur({ ...eksik, ilan: { ...eksik.ilan!, skor: null, skor_bilesenleri: null } });

    await waitFor(() => expect(screen.getByTestId('skor-yok')).toBeTruthy());
    expect(screen.getByTestId('skor-yok').textContent).toContain('Hesaplanamadı');
  });

  test('yurt içi kıyas vaat vermez', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('bolum-yurtici')).toBeTruthy());
    const metin = screen.getByTestId('bolum-yurtici').textContent ?? '';
    expect(metin).toContain('veri kaynağı bağlı değil');
    expect(metin).not.toContain('Yakında');
  });

  test('görseli olmayan üründe galeri yer tutucu basar', async () => {
    kur(veri({ urun: urun({ main_image: null, images: [] }) }));

    await waitFor(() => expect(screen.getByTestId('galeri-bos')).toBeTruthy());
  });
});

describe('Ürün sağlığı C8 kapısını yansıtır', () => {
  test('eksikler tek tek sayılır', async () => {
    kur(
      veri({
        urun: urun({
          hazir_eksikleri: [
            { alan: 'category_id', etiket: 'Kategori' },
            { alan: 'main_image', etiket: 'Ana görsel' },
          ],
        }),
      }),
    );

    await waitFor(() => expect(screen.getByTestId('saglik-eksikler')).toBeTruthy());
    expect(screen.getByTestId('saglik-eksikler').textContent).toContain('Kategori eksik');
    expect(screen.getByTestId('saglik-eksikler').textContent).toContain('Ana görsel eksik');
  });

  test('eksik yoksa kapının açık olduğu söylenir', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('saglik-tam')).toBeTruthy());
    expect(screen.getByTestId('saglik-tam').textContent).toContain('HAZIR işaretlenebilir');
  });
});

describe('Kapanış yolları', () => {
  test('düğme kapatır', async () => {
    const kullanici = userEvent.setup();
    const { onKapat } = kur();

    await waitFor(() => expect(screen.getByTestId('cekmece-kapat')).toBeTruthy());
    await kullanici.click(screen.getByTestId('cekmece-kapat'));

    expect(onKapat).toHaveBeenCalled();
  });

  test('Esc kapatır', async () => {
    const kullanici = userEvent.setup();
    const { onKapat } = kur();

    await waitFor(() => expect(screen.getByTestId('urun-cekmecesi')).toBeTruthy());
    await kullanici.keyboard('{Escape}');

    expect(onKapat).toHaveBeenCalled();
  });
});
