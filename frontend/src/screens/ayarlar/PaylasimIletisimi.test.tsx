import { beforeEach, describe, expect, test, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaylasimIletisimi from './PaylasimIletisimi';

/**
 * AYARLAR > PAYLAŞIM İLETİŞİM NUMARASI (İE#21 EK-4 · B7).
 *
 * Alanın sözü: numara girilirse kilit ekranında düğme belirir, boş bırakılırsa
 * belirmez. Panelin işi numarayı doğru göndermek ve sunucunun reddini kullanıcıya
 * göstermektir — doğrulama sunucudadır.
 */

const guncelleCasusu = vi.fn(async (_numara: string) => ({ share_contact_phone: null as string | null }));

vi.mock('../../api/endpoints', () => ({
  share: {
    updateContact: (numara: string) => guncelleCasusu(numara),
  },
}));

vi.mock('../../components/Toast', () => ({
  useToast: (secici: (durum: { push: (mesaj: string) => void }) => unknown) => secici({ push: () => {} }),
}));

function kur(mevcut: string | null = null) {
  const onSaved = vi.fn();
  render(<PaylasimIletisimi mevcut={mevcut} onSaved={onSaved} />);

  return { onSaved };
}

beforeEach(() => {
  guncelleCasusu.mockClear();
  guncelleCasusu.mockResolvedValue({ share_contact_phone: null });
});

describe('Kaydetme', () => {
  test('girilen numara sunucuya OLDUĞU GİBİ gider', async () => {
    const kullanici = userEvent.setup();
    kur();

    await kullanici.type(screen.getByTestId('paylasim-iletisim-numarasi'), '+90 532 123 45 67');
    await kullanici.click(screen.getByRole('button', { name: 'Numarayı kaydet' }));

    await waitFor(() => expect(guncelleCasusu).toHaveBeenCalledWith('+90 532 123 45 67'));
  });

  test('mevcut numara kutuda görünür', () => {
    kur('+90 532 123 45 67');

    expect((screen.getByTestId('paylasim-iletisim-numarasi') as HTMLInputElement).value).toBe('+90 532 123 45 67');
  });

  test('boş kaydetmek numarayı temizler', async () => {
    const kullanici = userEvent.setup();
    const { onSaved } = kur('905321234567');

    // Kutu, tarayıcı otomatik doldurmasına karşı odaklanana kadar salt-okunurdur
    // (lib/autofill.ts) — gerçek kullanıcı gibi önce tıklanır.
    await kullanici.click(screen.getByTestId('paylasim-iletisim-numarasi'));
    await kullanici.clear(screen.getByTestId('paylasim-iletisim-numarasi'));
    await kullanici.click(screen.getByRole('button', { name: 'Numarayı kaydet' }));

    await waitFor(() => expect(guncelleCasusu).toHaveBeenCalledWith(''));
    expect(onSaved).toHaveBeenCalled();
  });
});

describe('Beklenti metni', () => {
  test('anahtarın mesajda gitmediği YAZILI durur', () => {
    kur();

    expect(screen.getByText(/Anahtar mesajda gönderilmez/)).toBeTruthy();
  });

  test('boş bırakılınca düğmenin gösterilmeyeceği söylenir', () => {
    kur();

    expect(screen.getByText(/Boş bırakırsanız/)).toBeTruthy();
  });
});
