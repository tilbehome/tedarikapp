import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TeklifTurlari from './TeklifTurlari';
import type { TeklifTuru } from '../../api/types';

/**
 * LİSTE DETAYI › TEKLİF TURLARI SEKMESİ (V3-C Aşama 2.1).
 *
 * Sahibin döngüsü tek sekmede: tur aç → firmaya gönder → onayla / revizyon
 * iste / vazgeç. Üç kural sınanır:
 *   · GÖNDER geri alınamaz (RFQ donar, link üretilir): K105 §2.6 gereği
 *     onaydan önce NE OLACAĞI yazılır ve düğmede eylem adı durur ("Gönder",
 *     "Tamam" değil).
 *   · Bağlantı ve 6 haneli anahtar AYRI KUTULARDA gösterilir ve aynı kanaldan
 *     gitmemesi gerektiği yazılıdır (mesaj kalıpları §2).
 *   · Hangi düğmenin görüneceği DURUMA bağlıdır: taslakta "Gönder", yanıtlanmış
 *     turda "Onayla" ve "Revizyon iste"; sahip VIEWED yazamaz (düğme yok).
 */

function tur(ustyaz: Partial<TeklifTuru>): TeklifTuru {
  return {
    id: 1,
    list_id: 10,
    liste_adi: 'Eylül siparişi',
    supplier_id: 5,
    firma_adi: 'Yiwu Test Co',
    tur_no: 1,
    parent_round_id: null,
    state: 'DRAFT',
    etiket: 'R1 taslak',
    cikti_terimi: 'status.preparing',
    nihai: false,
    state_reason: null,
    rfq_snapshot_id: null,
    rate_snapshot_id: null,
    rate_policy: 'inherit',
    kur: { para_birimi: 'CNY', deger: '7.0400', kaynak: 'ayar', kilit_at: null },
    share_id: null,
    gecerlilik_gun: 15,
    valid_until: null,
    portal_dili: 'zh',
    goruntulendi: false,
    bekleme_gun: null,
    drafted_at: '2026-09-01 09:00:00',
    sent_at: null,
    first_viewed_at: null,
    responded_at: null,
    approved_at: null,
    revision_requested_at: null,
    partial_submission_count: 0,
    created_at: '2026-09-01 09:00:00',
    updated_at: '2026-09-01 09:00:00',
    ...ustyaz,
  };
}

const turlarCasusu = vi.fn(async (_listId: number) => [tur({})]);
const firmalarCasusu = vi.fn(async () => [{ id: 5, ad: 'Yiwu Test Co', varsayilan_dil: 'zh' }]);
const acCasusu = vi.fn(async (_listId: number, _govde: { firma_id: number }) => tur({ id: 2, tur_no: 1 }));
const gonderCasusu = vi.fn(async (_turId: number, _govde: object) => ({
  ...tur({ state: 'SENT', etiket: 'R1 gönderildi', rfq_snapshot_id: 3, sent_at: '2026-09-01 10:00:00' }),
  share_url: 'https://ornek.test/liste/abc123',
  share_token: 'abc123',
  erisim_anahtari: '482913',
  satir_sayisi: 2,
}));
const onaylaCasusu = vi.fn(async (_turId: number) => tur({ state: 'APPROVED', etiket: 'R1 onaylandı', nihai: true }));
const revizyonCasusu = vi.fn(async (_turId: number, _govde: { sebep: string }) => tur({ id: 3, tur_no: 2, etiket: 'R2 taslak', parent_round_id: 1 }));
const vazgecCasusu = vi.fn(async (_turId: number, _govde: { sebep?: string }) => tur({ state: 'ABANDONED', etiket: 'R1 vazgeçildi', nihai: true }));

vi.mock('../../api/endpoints', () => ({
  teklifler: {
    listeninTurlari: (listId: number) => turlarCasusu(listId),
    firmalar: () => firmalarCasusu(),
    ac: (listId: number, govde: { firma_id: number }) => acCasusu(listId, govde),
    gonder: (turId: number, govde: object) => gonderCasusu(turId, govde),
    onayla: (turId: number) => onaylaCasusu(turId),
    revizyon: (turId: number, govde: { sebep: string }) => revizyonCasusu(turId, govde),
    vazgec: (turId: number, govde: { sebep?: string }) => vazgecCasusu(turId, govde),
  },
}));

function kur() {
  return render(<TeklifTurlari listId={10} listeKapali={false} />);
}

describe('Teklif turları sekmesi', () => {
  beforeEach(() => {
    turlarCasusu.mockClear();
    gonderCasusu.mockClear();
    onaylaCasusu.mockClear();
    revizyonCasusu.mockClear();
  });

  test('taslak turda GÖNDER görünür; onay ne olacağını söyler ve eylem adını taşır', async () => {
    const kullanici = userEvent.setup();
    kur();
    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());

    await kullanici.click(screen.getByRole('button', { name: 'Firmaya gönder' }));

    // K105 §2.6: "Emin misiniz?" yetmez — neyin donacağı yazılır.
    const onay = screen.getByTestId('gonder-onay');
    expect(onay.textContent).toMatch(/kilitlen|donar|değiştirilemez/i);
    expect(screen.getByRole('button', { name: 'Gönder' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Tamam' })).toBeNull();

    await kullanici.click(screen.getByRole('button', { name: 'Gönder' }));
    await waitFor(() => expect(gonderCasusu).toHaveBeenCalledTimes(1));
  });

  test('gönderim sonrası bağlantı ve anahtar AYRI kutularda, uyarı yazılı', async () => {
    const kullanici = userEvent.setup();
    kur();
    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());
    await kullanici.click(screen.getByRole('button', { name: 'Firmaya gönder' }));
    await kullanici.click(screen.getByRole('button', { name: 'Gönder' }));

    await waitFor(() => expect(screen.getByTestId('tur-baglanti')).toBeTruthy());
    expect(screen.getByTestId('tur-baglanti').textContent).toContain('https://ornek.test/liste/abc123');
    expect(screen.getByTestId('tur-anahtar').textContent).toContain('482913');
    expect(screen.getByTestId('tur-kanal-uyarisi').textContent).toMatch(/ayrı/i);
  });

  test('yanıtlanmış turda Onayla ve Revizyon iste görünür; Gönder görünmez', async () => {
    turlarCasusu.mockResolvedValueOnce([
      tur({ state: 'RESPONDED', etiket: 'R1 yanıtlandı', rfq_snapshot_id: 3, sent_at: '2026-09-01 10:00:00', responded_at: '2026-09-03 10:00:00' }),
    ]);
    kur();
    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());

    expect(screen.getByRole('button', { name: 'Onayla' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Revizyon iste' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Firmaya gönder' })).toBeNull();
  });

  test('revizyon gerekçesiz gönderilmez; gerekçeyle yeni tur açılır', async () => {
    const kullanici = userEvent.setup();
    turlarCasusu.mockResolvedValueOnce([
      tur({ state: 'RESPONDED', etiket: 'R1 yanıtlandı', rfq_snapshot_id: 3, sent_at: '2026-09-01 10:00:00' }),
    ]);
    kur();
    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());

    await kullanici.click(screen.getByRole('button', { name: 'Revizyon iste' }));
    const gonderDugmesi = screen.getByRole('button', { name: 'Revizyon iste ve yeni tur aç' });
    expect((gonderDugmesi as HTMLButtonElement).disabled).toBe(true);

    await kullanici.type(screen.getByLabelText('Revizyon gerekçesi'), 'MOQ çok yüksek');
    await kullanici.click(gonderDugmesi);

    await waitFor(() => expect(revizyonCasusu).toHaveBeenCalledWith(1, expect.objectContaining({ sebep: 'MOQ çok yüksek' })));
  });

  test('gönderilmiş turda sahip için durum düğmesi yok — VIEWED yazılamaz', async () => {
    turlarCasusu.mockResolvedValueOnce([
      tur({ state: 'SENT', etiket: 'R1 gönderildi', rfq_snapshot_id: 3, sent_at: '2026-09-01 10:00:00', bekleme_gun: 2 }),
    ]);
    kur();
    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());

    expect(screen.queryByRole('button', { name: /görüntülendi/i })).toBeNull();
    expect(screen.getByTestId('tur-1').textContent).toContain('2 gündür bekliyor');
  });

  test('kapalı listede yeni tur açılamaz', async () => {
    render(<TeklifTurlari listId={10} listeKapali={true} />);
    await waitFor(() => expect(turlarCasusu).toHaveBeenCalled());

    expect(screen.queryByRole('button', { name: 'Yeni tur aç' })).toBeNull();
  });
});
