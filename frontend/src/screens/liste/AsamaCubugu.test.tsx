import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AsamaCubugu, { YAKINDA_ASAMALAR } from './AsamaCubugu';
import type { ListStatus } from '../../api/types';

/**
 * AŞAMA ÇUBUĞU (İE#21 B2 · PM kararı: v1.0 = 5B durum makinesi).
 *
 * Bu testler tek bir sözü tutar: ÇUBUK YALNIZ ÇALIŞAN ADIMLARI VAAT EDER.
 * Sonraki fazların aşamaları görünür ama tıklanmaz; ilerletme izni sunucudan
 * gelen `machine.list` listesiyle sınırlıdır.
 */

function kur(durum: ListStatus, izinli: ListStatus[] = [], kurKilitli = false) {
  const onGecis = vi.fn();
  render(
    <AsamaCubugu durum={durum} izinliGecisler={izinli} kurKilitli={kurKilitli} onGecis={onGecis} />,
  );

  return { onGecis };
}

describe('5B aşamaları', () => {
  test('dört canlı aşama görünür', () => {
    kur('draft');

    for (const etiket of ['Taslak', 'İletildi', 'Sipariş Verildi', 'Tamamlandı']) {
      expect(screen.getAllByText(etiket).length).toBeGreaterThan(0);
    }
  });

  test('mevcut aşama işaretlidir, öncekiler tamamlanmıştır', () => {
    kur('ordered');

    expect(screen.getByTestId('asama-ordered').getAttribute('data-durum')).toBe('aktif');
    expect(screen.getByTestId('asama-draft').getAttribute('data-durum')).toBe('gecmis');
    expect(screen.getByTestId('asama-sent').getAttribute('data-durum')).toBe('gecmis');
  });
});

describe('Sonraki faz aşamaları PASİF durur', () => {
  test('"Yakında" rozetiyle görünür ve düğme DEĞİLDİR', () => {
    kur('draft');

    const yakindalar = screen.getAllByTestId('asama-yakinda');
    expect(yakindalar).toHaveLength(YAKINDA_ASAMALAR.length);
    expect(screen.getAllByText('Yakında')).toHaveLength(YAKINDA_ASAMALAR.length);

    // Tıklanabilir bir şey OLMAMALI: çalışmayan adım vaat edilmez (C1).
    for (const kutu of yakindalar) {
      expect(kutu.querySelector('button')).toBeNull();
    }
  });
});

describe('İlerletme izni SUNUCUDAN gelir', () => {
  test('izinli aşama tıklanır ve geçişi tetikler', async () => {
    const kullanici = userEvent.setup();
    const { onGecis } = kur('draft', ['sent', 'cancelled']);

    await kullanici.click(screen.getByTestId('asama-sent'));

    expect(onGecis).toHaveBeenCalledWith('sent');
  });

  test('izinsiz aşama tıklanamaz — atlama YOK', async () => {
    const kullanici = userEvent.setup();
    const { onGecis } = kur('draft', ['sent']);

    const tamamlandi = screen.getByTestId('asama-completed');
    expect(tamamlandi.hasAttribute('disabled')).toBe(true);

    await kullanici.click(tamamlandi);
    expect(onGecis).not.toHaveBeenCalled();
  });
});

describe('Kur ipucu durumu anlatır', () => {
  test('kilitlenmemişken ne olacağını söyler', () => {
    kur('draft', ['sent']);

    expect(screen.getByTestId('asama-ipucu').textContent).toContain('kur bu listeye kilitlenir');
  });

  test('kilitliyken ₺ hesabının kaynağını söyler', () => {
    kur('sent', ['ordered'], true);

    expect(screen.getByTestId('asama-ipucu').textContent).toContain('kilitli kurla');
  });

  test('iptal edilen listede çubuk ilerlemez', () => {
    kur('cancelled');

    expect(screen.getByTestId('asama-ipucu').textContent).toContain('iptal edildi');
    expect(screen.getByTestId('asama-draft').getAttribute('data-durum')).toBe('kapali');
  });
});
