import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaylasPenceresi from './PaylasPenceresi';

/**
 * PAYLAŞ PENCERESİ (İE#21 B6).
 *
 * Üç söz sınanır:
 *   · bitiş tarihi GERÇEKTEN gönderilir (API baştan kabul ediyordu, panel sormuyordu),
 *   · kanal metni SUNUCUDAN gelir ve `{link}` panelde dolar (token istek satırına düşmez),
 *   · anahtar ile bağlantının aynı kanaldan gitmemesi gerektiği YAZILI durur.
 */

const uretCasusu = vi.fn(async (_listId: number, _govde: { expires_at?: string }) => ({
  share_url: 'https://ornek.test/liste/abc',
  share_token_prefix: 'abc12345',
  share_expires_at: null as string | null,
}));
const iptalCasusu = vi.fn(async () => undefined);
const metinCasusu = vi.fn(async (_listId: number, lang: 'tr' | 'en' | 'zh') => ({
  dil: lang,
  dil_adi: lang,
  mesaj: lang === 'en' ? 'Supply list: {link}' : 'Tedarik listesi: {link}',
  konu: lang === 'en' ? 'Supply list' : 'Tedarik listesi',
}));

vi.mock('../../api/endpoints', () => ({
  share: {
    create: (listId: number, govde: { expires_at?: string }) => uretCasusu(listId, govde),
    revoke: () => iptalCasusu(),
    text: (listId: number, lang: 'tr' | 'en' | 'zh') => metinCasusu(listId, lang),
  },
}));

function kur(adres: string | null = null, tokenPrefix: string | null = null) {
  const onAdres = vi.fn();
  const onDegisti = vi.fn();
  const onKapat = vi.fn();

  render(
    <PaylasPenceresi
      listId={13}
      tokenPrefix={tokenPrefix}
      adres={adres}
      onAdres={onAdres}
      onDegisti={onDegisti}
      onKapat={onKapat}
      anahtarBlogu={<span data-testid="anahtar-blogu">4 7 2 9 1 8</span>}
    />,
  );

  return { onAdres, onDegisti, onKapat };
}

beforeEach(() => {
  uretCasusu.mockClear();
  iptalCasusu.mockClear();
  metinCasusu.mockClear();
});

describe('Bağlantı bölümü', () => {
  test('bitiş tarihi girilirse isteğe KONUR', async () => {
    const kullanici = userEvent.setup();
    kur();

    await kullanici.type(screen.getByTestId('paylas-bitis'), '2026-12-31');
    await kullanici.click(screen.getByTestId('paylas-uret'));

    await waitFor(() => expect(uretCasusu).toHaveBeenCalledTimes(1));
    expect(uretCasusu.mock.calls[0]?.[1]).toEqual({ expires_at: '2026-12-31' });
  });

  test('tarih boşsa istek gövdesi de BOŞ olur — süresiz bağlantı', async () => {
    const kullanici = userEvent.setup();
    kur();

    await kullanici.click(screen.getByTestId('paylas-uret'));

    await waitFor(() => expect(uretCasusu).toHaveBeenCalledTimes(1));
    expect(uretCasusu.mock.calls[0]?.[1]).toEqual({});
  });

  test('üretilen adres yukarı verilir', async () => {
    const kullanici = userEvent.setup();
    const { onAdres, onDegisti } = kur();

    await kullanici.click(screen.getByTestId('paylas-uret'));

    await waitFor(() => expect(onAdres).toHaveBeenCalledWith('https://ornek.test/liste/abc'));
    expect(onDegisti).toHaveBeenCalled();
  });

  test('aktif bağlantı varken yenilemenin eski adresi öldürdüğü YAZAR', () => {
    kur(null, 'abc12345');

    expect(screen.getByTestId('paylas-baglanti').textContent).toContain('ANINDA öldürür');
    expect(screen.getByTestId('paylas-uret').textContent).toContain('yenile');
  });

  test('adres varken uyarı ve kopyalama görünür', () => {
    kur('https://ornek.test/liste/abc');

    expect(screen.getByTestId('paylas-adres').textContent).toContain('/liste/abc');
    expect(screen.getByTestId('paylas-baglanti').textContent).toContain('yalnız şimdi görünür');
  });
});

describe('Kanal metni', () => {
  test('adres yokken metin hazırlanmaz', () => {
    kur();

    expect(screen.getByTestId('paylas-metin').textContent).toContain('bağlantı üretildikten sonra');
    expect(metinCasusu).toHaveBeenCalled(); // şablon çekilir, ama basılmaz
    expect(screen.queryByTestId('paylas-mesaj')).toBeNull();
  });

  test('{link} yer tutucusu PANELDE dolar', async () => {
    kur('https://ornek.test/liste/abc');

    await waitFor(() => expect(screen.getByTestId('paylas-mesaj')).toBeTruthy());
    expect(screen.getByTestId('paylas-mesaj').textContent).toBe('Tedarik listesi: https://ornek.test/liste/abc');
  });

  test('dil değişince metin sunucudan yeniden istenir', async () => {
    const kullanici = userEvent.setup();
    kur('https://ornek.test/liste/abc');

    await waitFor(() => expect(screen.getByTestId('paylas-mesaj')).toBeTruthy());
    await kullanici.selectOptions(screen.getByTestId('paylas-dil'), 'en');

    await waitFor(() =>
      expect(screen.getByTestId('paylas-mesaj').textContent).toBe('Supply list: https://ornek.test/liste/abc'),
    );
    expect(metinCasusu.mock.calls.map((cagri) => cagri[1])).toContain('en');
  });

  test('WhatsApp ve e-posta bağlantıları metni taşır', async () => {
    kur('https://ornek.test/liste/abc');

    await waitFor(() => expect(screen.getByTestId('paylas-whatsapp')).toBeTruthy());
    expect(screen.getByTestId('paylas-whatsapp').getAttribute('href')).toContain(
      encodeURIComponent('https://ornek.test/liste/abc'),
    );
    expect(screen.getByTestId('paylas-eposta').getAttribute('href')).toContain('mailto:');
  });
});

describe('Erişim anahtarı bloğu', () => {
  test('blok pencerede durur ve ayrı kanal uyarısı yazar', () => {
    kur('https://ornek.test/liste/abc');

    expect(screen.getByTestId('anahtar-blogu')).toBeTruthy();
    expect(screen.getByTestId('paylas-anahtar').textContent).toContain('aynı kanaldan');
  });
});

describe('Kapanış', () => {
  test('düğme kapatır', async () => {
    const kullanici = userEvent.setup();
    const { onKapat } = kur();

    await kullanici.click(screen.getByTestId('paylas-kapat'));

    expect(onKapat).toHaveBeenCalled();
  });
});
