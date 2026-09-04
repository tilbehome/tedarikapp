import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import TekliflerScreen from './TekliflerScreen';
import type { TeklifTuru } from '../api/types';

/**
 * TEKLİFLER EKRANI (V3-C Aşama 2.1 · yol haritası §7.6 "bizim taraf").
 *
 * Üç söz sınanır:
 *   · açık turlar ile geçmiş turlar AYRI listelenir (menü: "Açık turlar ·
 *     Geçmiş turlar"),
 *   · ana kolon "açıldı mı / kaç gündür bekliyor"dur — firma link'i açmadıysa
 *     bunun görünmesi gerekir; "gönderildi" tek başına bir bilgi değildir,
 *   · tur etiketi "R2 gönderildi" gibi TUR NUMARASIYLA okunur (#15 §2: tur
 *     numarası durum adına gömülmez, arayüz birleştirir).
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
    state: 'SENT',
    etiket: 'R1 gönderildi',
    cikti_terimi: 'status.sent',
    nihai: false,
    state_reason: null,
    rfq_snapshot_id: 3,
    rate_snapshot_id: null,
    rate_policy: 'inherit',
    kur: { para_birimi: 'CNY', deger: '7.0400', kaynak: 'ayar', kilit_at: '2026-09-01 10:00:00' },
    share_id: 9,
    gecerlilik_gun: 15,
    valid_until: '2026-09-16 10:00:00',
    portal_dili: 'zh',
    goruntulendi: false,
    bekleme_gun: 3,
    drafted_at: '2026-09-01 09:00:00',
    sent_at: '2026-09-01 10:00:00',
    first_viewed_at: null,
    responded_at: null,
    approved_at: null,
    revision_requested_at: null,
    partial_submission_count: 0,
    created_at: '2026-09-01 09:00:00',
    updated_at: '2026-09-01 10:00:00',
    ...ustyaz,
  };
}

const tekliflerCasusu = vi.fn(async () => ({
  acik: [
    tur({ id: 1 }),
    tur({ id: 2, firma_adi: 'Ningbo Ltd', tur_no: 2, etiket: 'R2 görüntülendi', state: 'VIEWED', goruntulendi: true, bekleme_gun: 1, first_viewed_at: '2026-09-02 08:00:00' }),
  ],
  gecmis: [tur({ id: 3, firma_adi: 'Eski Firma', state: 'APPROVED', etiket: 'R1 onaylandı', nihai: true, bekleme_gun: null, approved_at: '2026-08-20 10:00:00' })],
}));

vi.mock('../api/endpoints', () => ({
  teklifler: {
    hepsi: () => tekliflerCasusu(),
  },
}));

function kur() {
  return render(
    <MemoryRouter>
      <TekliflerScreen />
    </MemoryRouter>,
  );
}

describe('Teklifler ekranı', () => {
  beforeEach(() => {
    tekliflerCasusu.mockClear();
  });

  test('açık ve geçmiş turlar ayrı bölümlerde listelenir', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('acik-turlar')).toBeTruthy());
    expect(screen.getByTestId('acik-turlar').textContent).toContain('Yiwu Test Co');
    expect(screen.getByTestId('acik-turlar').textContent).toContain('Ningbo Ltd');
    expect(screen.getByTestId('gecmis-turlar').textContent).toContain('Eski Firma');
    expect(screen.getByTestId('acik-turlar').textContent).not.toContain('Eski Firma');
  });

  test('ana kolon: açıldı mı ve kaç gündür bekliyor', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());
    const acilmamis = screen.getByTestId('tur-1');
    expect(acilmamis.textContent).toContain('Açılmadı');
    expect(acilmamis.textContent).toContain('3 gündür bekliyor');

    const acilmis = screen.getByTestId('tur-2');
    expect(acilmis.textContent).toContain('Açıldı');
  });

  test('tur etiketi tur numarasıyla okunur', async () => {
    kur();

    await waitFor(() => expect(screen.getByText('R2 görüntülendi')).toBeTruthy());
    expect(screen.getByText('R1 gönderildi')).toBeTruthy();
  });

  test('tur satırı liste detayına bağlanır', async () => {
    kur();

    await waitFor(() => expect(screen.getByTestId('tur-1')).toBeTruthy());
    const link = screen.getByTestId('tur-1').querySelector('a[href="/listeler/10"]');
    expect(link).not.toBeNull();
  });

  test('boş durum: hiç tur yoksa ekran bunu söyler', async () => {
    tekliflerCasusu.mockResolvedValueOnce({ acik: [], gecmis: [] });
    kur();

    await waitFor(() => expect(screen.getByTestId('teklifler-bos')).toBeTruthy());
  });
});
