import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TabloDenetimleri from './TabloDenetimleri';
import { VARSAYILAN, type TabloTercihi } from '../../lib/tabloTercihi';

/**
 * TABLO DENETİMLERİ (İE#21 B2 cilaları).
 *
 * Denetimin tek işi tercihi DEĞİŞTİRMEK; kalıcılığı `tabloTercihi` üstlenir.
 * Bu yüzden testler "hangi tercih yukarı verildi" sorusuna bakar.
 */

/** İlk çağrının tercihini döner — `calls[0][0]` tip olarak "belki yok"tur. */
function ilkCagri(casus: ReturnType<typeof vi.fn<(yeni: TabloTercihi) => void>>): TabloTercihi {
  const cagri = casus.mock.calls[0];
  if (cagri === undefined) throw new Error('onDegis hiç çağrılmadı');

  return cagri[0];
}

function kur(tercih = VARSAYILAN) {
  const onDegis = vi.fn<(yeni: TabloTercihi) => void>();
  render(<TabloDenetimleri tercih={tercih} onDegis={onDegis} />);

  return { onDegis };
}

describe('Sütun menüsü', () => {
  test('kapalı başlar, düğmeyle açılır', async () => {
    const kullanici = userEvent.setup();
    kur();

    expect(screen.queryByTestId('sutun-menusu')).toBeNull();

    await kullanici.click(screen.getByTestId('sutun-menusu-dugmesi'));
    expect(screen.getByTestId('sutun-menusu')).toBeTruthy();
  });

  test('açık sütunun işareti kaldırılınca tercihten DÜŞER', async () => {
    const kullanici = userEvent.setup();
    const { onDegis } = kur();

    await kullanici.click(screen.getByTestId('sutun-menusu-dugmesi'));
    await kullanici.click(screen.getByLabelText('$ DDP'));

    expect(onDegis).toHaveBeenCalledTimes(1);
    expect(ilkCagri(onDegis).sutunlar).not.toContain('ddp_usd');
  });

  test('kapalı sütun işaretlenince tercihe EKLENİR', async () => {
    const kullanici = userEvent.setup();
    const { onDegis } = kur({ ...VARSAYILAN, sutunlar: ['adet'] });

    await kullanici.click(screen.getByTestId('sutun-menusu-dugmesi'));
    await kullanici.click(screen.getByLabelText('Kategori'));

    expect(ilkCagri(onDegis).sutunlar).toEqual(['adet', 'kategori']);
  });

  test('düğme kaç sütunun açık olduğunu söyler', () => {
    kur({ ...VARSAYILAN, sutunlar: ['adet', 'durum'] });

    expect(screen.getByTestId('sutun-menusu-dugmesi').textContent).toContain('(2)');
  });
});

describe('Yoğunluk ve gruplama', () => {
  test('yoğunluk seçimi yukarı verilir', async () => {
    const kullanici = userEvent.setup();
    const { onDegis } = kur();

    await kullanici.selectOptions(screen.getByTestId('yogunluk-secici'), 'sik');

    expect(ilkCagri(onDegis).yogunluk).toBe('sik');
  });

  test('gruplama seçimi yukarı verilir', async () => {
    const kullanici = userEvent.setup();
    const { onDegis } = kur();

    await kullanici.selectOptions(screen.getByTestId('grupla-secici'), 'kategori');

    expect(ilkCagri(onDegis).grupla).toBe('kategori');
  });

  test('mevcut tercih seçili görünür', () => {
    kur({ ...VARSAYILAN, yogunluk: 'sik', grupla: 'durum' });

    expect((screen.getByTestId('yogunluk-secici') as HTMLSelectElement).value).toBe('sik');
    expect((screen.getByTestId('grupla-secici') as HTMLSelectElement).value).toBe('durum');
  });
});
