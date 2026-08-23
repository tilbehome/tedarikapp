import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import MiktarHucresi from './MiktarHucresi';

/**
 * SATIR İÇİ MİKTAR (İE#21 B2).
 *
 * Kutunun sözü: yazdığın sayı ya kaydedilir ya da kutu gerçeğe döner. Ekranda
 * kaydedilmemiş bir sayının kalması, kullanıcının olmayan bir siparişi doğru
 * sanmasına yol açar — bu testler tam olarak onu engeller.
 */

function kur(onKaydet = vi.fn(async () => {}), deger = 240, kapali = false) {
  render(<MiktarHucresi deger={deger} etiket="Sebze doğrayıcı" kapali={kapali} onKaydet={onKaydet} />);

  return { kutu: screen.getByTestId('miktar-hucresi') as HTMLInputElement, onKaydet };
}

describe('Kaydetme', () => {
  test('Enter yeni miktarı gönderir', async () => {
    const kullanici = userEvent.setup();
    const { kutu, onKaydet } = kur();

    await kullanici.clear(kutu);
    await kullanici.type(kutu, '300{Enter}');

    expect(onKaydet).toHaveBeenCalledWith(300);
  });

  test('odak kaybı da kaydeder — sekme değiştirince veri kaybolmaz', async () => {
    const kullanici = userEvent.setup();
    const { kutu, onKaydet } = kur();

    await kullanici.clear(kutu);
    await kullanici.type(kutu, '12');
    await kullanici.tab();

    expect(onKaydet).toHaveBeenCalledWith(12);
  });

  test('değer DEĞİŞMEDİYSE istek gönderilmez', async () => {
    const kullanici = userEvent.setup();
    const { kutu, onKaydet } = kur();

    await kullanici.click(kutu);
    await kullanici.tab();

    expect(onKaydet).not.toHaveBeenCalled();
  });
});

describe('Geçersiz değer', () => {
  test.each(['0', '-5', ''])('"%s" reddedilir ve kutu eski değere döner', async (girdi) => {
    const kullanici = userEvent.setup();
    const { kutu, onKaydet } = kur();

    await kullanici.clear(kutu);
    if (girdi !== '') await kullanici.type(kutu, girdi);
    await kullanici.keyboard('{Enter}');

    expect(onKaydet).not.toHaveBeenCalled();
    expect(kutu.value).toBe('240');
  });
});

describe('Vazgeçme ve reddedilme', () => {
  test('Esc taslağı atar', async () => {
    const kullanici = userEvent.setup();
    const { kutu, onKaydet } = kur();

    await kullanici.clear(kutu);
    await kullanici.type(kutu, '999{Escape}');

    expect(kutu.value).toBe('240');
    expect(onKaydet).not.toHaveBeenCalled();
  });

  test('sunucu reddederse kutu GERÇEĞE döner', async () => {
    const kullanici = userEvent.setup();
    const patlayan = vi.fn(async () => {
      throw new Error('Liste donmuş.');
    });
    const { kutu } = kur(patlayan as never);

    await kullanici.clear(kutu);
    await kullanici.type(kutu, '300{Enter}');

    expect(kutu.value).toBe('240');
  });

  test('donmuş listede kutu kapalıdır', () => {
    const { kutu } = kur(vi.fn(async () => {}), 240, true);

    expect(kutu.disabled).toBe(true);
  });
});
