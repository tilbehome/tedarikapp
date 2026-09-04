import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import ListsScreen from './ListsScreen';
import { Toaster } from '../components/Toast';
import type { ListeSablonu, ListelerMeta, SupplyList } from '../api/types';

/**
 * LİSTELER MERKEZİ (V3-C Blok E · onaylı tasarım referansı).
 *
 * Sınanan kurallar:
 *   · Sekme çipleri SUNUCUNUN `meta.sayimlar`ını basar; tıklayınca `?sekme=`
 *     ile yeniden ister (türetim sunucuda, panel yalnız gösterir).
 *   · "18/25 fiyatlandı" çubuğu satırda görünür.
 *   · K105: `⋯` ve SAĞ TIK aynı menüyü açar; çöpe atma ONAY SORMAZ, geri
 *     alınabilir toast verir; çoklu seçimde alt çubuk çıkar; `/` aramaya odaklar.
 *   · Şablon paneli: "Bu şablondan liste aç" şablon ucunu çağırır; silme
 *     ertelenmiş kiptir — toast kapanmadan uç ÇAĞRILMAZ.
 */

function liste(ustyaz: Partial<SupplyList>): SupplyList {
  return {
    id: 1,
    name: 'Mutfak ürünleri',
    period: 'Eylül 2026',
    supplier_name: 'Yok Yok AVM',
    note: null,
    status: 'sent',
    visibility: 'active',
    yuan_rate: '7.0400',
    usd_rate: '41.5000',
    rate_locked_at: '2026-09-01T09:00:00+03:00',
    revision: 1,
    share_token_prefix: null,
    share_expires_at: null,
    product_count: 25,
    progress: { to_order: 25, ordered: 0, in_transit: 0, received: 0, cancelled: 0 },
    totals: { qty: 600, yuan: '1000.00', yuan_tl: '7040.00', ddp_usd: '7500.00', ddp_tl: '312600.00' },
    last_export: null,
    is_export_stale: false,
    created_at: '2026-09-01T09:00:00+03:00',
    updated_at: '2026-09-03T09:00:00+03:00',
    archived_at: null,
    deleted_at: null,
    sekme: 'fiyat_bekleniyor',
    fiyatlama: { fiyatlanan: 18, toplam: 25, yuzde: 72, kaynak: 'tur' },
    tur_ozeti: { id: 9, tur_no: 1, state: 'SENT', sent_at: new Date(Date.now() - 6 * 86_400_000).toISOString(), first_viewed_at: null, responded_at: null, valid_until: null },
    saglik: ['fiyat_bekleyen'],
    ...ustyaz,
  };
}

const meta: ListelerMeta = {
  sayimlar: { tumu: 3, fiyat_bekleniyor: 1, degerlendirmede: 1, hazirlaniyor: 1 },
  kpi: { fiyat_bekleyen_liste: 1, karar_bekleyen_liste: 1, suresi_dolan_teklif: 0, fiyatlanmayan_satir: 7 },
};

const veri = [
  liste({}),
  liste({ id: 2, name: 'Banyo koleksiyonu', supplier_name: 'Marmara Dış Ticaret', sekme: 'degerlendirmede', fiyatlama: { fiyatlanan: 12, toplam: 12, yuzde: 100, kaynak: 'tur' }, saglik: [], tur_ozeti: { id: 10, tur_no: 1, state: 'RESPONDED', sent_at: '2026-09-01T09:00:00+03:00', first_viewed_at: '2026-09-01T10:00:00+03:00', responded_at: '2026-09-02T10:00:00+03:00', valid_until: null } }),
  liste({ id: 3, name: 'Deneme', status: 'draft', sekme: 'hazirlaniyor', fiyatlama: { fiyatlanan: 9, toplam: 20, yuzde: 45, kaynak: 'urun' }, tur_ozeti: null, saglik: [], rate_locked_at: null }),
];

const allWithMetaCasusu = vi.fn(async (params: { sekme?: string }) => ({
  data: params.sekme ? veri.filter((l) => l.sekme === params.sekme) : veri,
  meta,
}));
const removeCasusu = vi.fn(async (_id: number) => undefined);
const restoreCasusu = vi.fn(async () => ({ type: 'lists', id: 1 }));
const sablonlarCasusu = vi.fn(async (): Promise<ListeSablonu[]> => [
  { id: 5, ad: 'Sonbahar seti', aciklama: null, urun_sayisi: 40, ornek_urunler: ['Termos', 'Bardak'], kaynak_list_id: 1, kullanim_sayisi: 2, son_kullanim_at: null, created_at: '2026-09-01T09:00:00+03:00', updated_at: '2026-09-01T09:00:00+03:00' },
]);
const listeOlusturCasusu = vi.fn(async () => liste({ id: 77, name: 'Sonbahar seti', product_count: 40 }));
const sablonSilCasusu = vi.fn(async (_id: number) => undefined);

vi.mock('../api/endpoints', () => ({
  lists: {
    allWithMeta: (params: { sekme?: string }) => allWithMetaCasusu(params),
    remove: (id: number) => removeCasusu(id),
    update: async () => liste({}),
    duplicate: async () => liste({ id: 4, name: 'Mutfak ürünleri (kopya)' }),
    create: async () => liste({ id: 6 }),
  },
  sablonlar: {
    hepsi: () => sablonlarCasusu(),
    listeOlustur: () => listeOlusturCasusu(),
    sil: (id: number) => sablonSilCasusu(id),
    listedenOlustur: async () => undefined,
  },
  trash: { restore: () => restoreCasusu() },
  gorunumler: { hepsi: async () => ({ gorunumler: [] }), kaydet: async () => ({ gorunumler: [] }), sil: async () => ({ gorunumler: [] }) },
}));

function ekran(baslangic = '/listeler') {
  return render(
    <MemoryRouter initialEntries={[baslangic]}>
      <ListsScreen />
      <Toaster />
    </MemoryRouter>,
  );
}

beforeEach(() => {
  allWithMetaCasusu.mockClear();
  removeCasusu.mockClear();
  restoreCasusu.mockClear();
  sablonSilCasusu.mockClear();
  listeOlusturCasusu.mockClear();
  window.localStorage.clear();
});

describe('Listeler merkezi', () => {
  test('sekme çipleri sunucu sayımlarını basar; çip tıklanınca ?sekme= ile yeniden ister; fiyatlama çubuğu görünür', async () => {
    const kullanici = userEvent.setup();
    ekran();

    await waitFor(() => expect(screen.getByTestId('listeler-tablosu')).toBeInTheDocument());
    const durum = screen.getByRole('tablist', { name: 'Durum' });
    expect(within(durum).getByRole('tab', { name: /Fiyat bekleniyor/ })).toHaveTextContent('1');
    expect(within(durum).getByRole('tab', { name: /Değerlendirmede/ })).toHaveTextContent('1');
    expect(screen.getByTestId('fiyatlama-1')).toHaveTextContent('18/25, %72');
    expect(screen.getByTestId('kpi-seridi')).toHaveTextContent('1 liste fiyat bekliyor');
    expect(screen.getByTestId('liste-1')).toHaveTextContent('6 gündür bekliyor');
    expect(screen.getByTestId('liste-1')).toHaveTextContent('Firma hatırlatılmalı');
    expect(within(screen.getByTestId('liste-2')).getByRole('link', { name: 'Karar ver' })).toBeInTheDocument();

    await kullanici.click(within(durum).getByRole('tab', { name: /Değerlendirmede/ }));

    await waitFor(() => expect(allWithMetaCasusu).toHaveBeenLastCalledWith(expect.objectContaining({ sekme: 'degerlendirmede' })));
    await waitFor(() => expect(screen.queryByTestId('liste-1')).not.toBeInTheDocument());
    expect(screen.getByTestId('liste-2')).toBeInTheDocument();
  });

  test('K105: ⋯ ve sağ tık aynı menüyü açar; çöpe atma onay sormaz, geri alınabilir toast verir', async () => {
    const kullanici = userEvent.setup();
    ekran();
    await waitFor(() => expect(screen.getByTestId('liste-1')).toBeInTheDocument());

    await kullanici.click(within(screen.getByTestId('liste-1')).getByRole('button', { name: 'Eylemler: Mutfak ürünleri' }));
    const menu = screen.getByRole('menu', { name: 'Eylemler: Mutfak ürünleri' });
    expect(within(menu).getByRole('menuitem', { name: /Çöpe at/ })).toBeInTheDocument();
    expect(within(menu).queryByRole('menuitem', { name: /Tekrar sipariş/ })).not.toBeInTheDocument();
    await kullanici.keyboard('{Escape}');
    expect(screen.queryByRole('menu')).not.toBeInTheDocument();

    await kullanici.pointer({ keys: '[MouseRight]', target: screen.getByTestId('liste-1') });
    const sagTikMenu = screen.getByRole('menu', { name: 'Eylemler: Mutfak ürünleri' });
    await kullanici.click(within(sagTikMenu).getByRole('menuitem', { name: /Çöpe at/ }));

    await waitFor(() => expect(removeCasusu).toHaveBeenCalledWith(1));
    expect(screen.queryByText(/Emin misin/)).not.toBeInTheDocument();
    expect(await screen.findByText(/çöp kutusuna taşındı/)).toBeInTheDocument();
    await kullanici.click(screen.getByRole('button', { name: /Geri al/ }));
    await waitFor(() => expect(restoreCasusu).toHaveBeenCalled());
  });

  test('K105: çoklu seçimde alt çubuk sayfa/eşleşen ayrımıyla çıkar; / aramaya odaklar', async () => {
    const kullanici = userEvent.setup();
    ekran();
    await waitFor(() => expect(screen.getByTestId('liste-1')).toBeInTheDocument());

    await kullanici.click(screen.getByLabelText('Seç: Mutfak ürünleri'));
    await kullanici.click(screen.getByLabelText('Seç: Deneme'));
    expect(screen.getByTestId('secim-cubugu')).toHaveTextContent('Bu sayfada 2 liste seçili');

    await kullanici.keyboard('{Escape}');
    expect(screen.queryByTestId('secim-cubugu')).not.toBeInTheDocument();

    await kullanici.keyboard('/');
    expect(screen.getByLabelText('Liste ara')).toHaveFocus();
  });

  test('şablon paneli: şablondan liste açar; silme ertelenmiştir — geri al basılınca uç hiç çağrılmaz', async () => {
    const kullanici = userEvent.setup();
    ekran();
    await waitFor(() => expect(screen.getByTestId('liste-1')).toBeInTheDocument());

    await kullanici.click(screen.getByRole('button', { name: /Şablonlar/ }));
    const panel = await screen.findByTestId('sablon-paneli');
    expect(panel).toHaveTextContent('Sonbahar seti');
    expect(panel).toHaveTextContent('40 ürün');

    await kullanici.click(within(panel).getByRole('button', { name: 'Bu şablondan liste aç' }));
    await waitFor(() => expect(listeOlusturCasusu).toHaveBeenCalledTimes(1));

    await kullanici.click(within(panel).getByRole('button', { name: /Sil/ }));
    expect(await screen.findByText(/şablonu silinecek/)).toBeInTheDocument();
    expect(sablonSilCasusu).not.toHaveBeenCalled();
    await kullanici.click(screen.getByRole('button', { name: /Geri al/ }));
    expect(await screen.findByText(/hiçbir şey silinmedi/)).toBeInTheDocument();
    await new Promise((r) => setTimeout(r, 30));
    expect(sablonSilCasusu).not.toHaveBeenCalled();
  });
});
