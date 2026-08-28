import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import EylemDugmesi from './EylemDugmesi';

/**
 * B11 kuralının testi (İE#20 C10/C11).
 *
 * Buradaki iddia görsel değil DAVRANIŞSALDIR, bu yüzden birim testi doğru araçtır:
 * "çift tık ikinci istek üretmez" ve "meşgul durumu görünür" — ikisi de bir
 * ekran görüntüsüyle değil, çağrı sayısıyla ve erişilebilirlik durumuyla ölçülür.
 */
describe('EylemDugmesi', () => {
  it('cift tik IKINCI istegi URETMEZ', async () => {
    const kullanici = userEvent.setup();
    let cozumle: (() => void) | undefined;
    const eylem = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          cozumle = resolve;
        }),
    );

    render(
      <EylemDugmesi mesgulEtiketi="Taşınıyor" onEylem={eylem}>
        Listeye taşı
      </EylemDugmesi>,
    );

    const dugme = screen.getByRole('button');
    await kullanici.click(dugme);
    await kullanici.click(dugme);
    await kullanici.click(dugme);

    expect(eylem).toHaveBeenCalledTimes(1);

    (cozumle as (() => void) | undefined)?.();
    await waitFor(() => expect(screen.getByRole('button')).not.toBeDisabled());
  });

  it('mesgulken FIIL ve aria-busy gosterir', async () => {
    const kullanici = userEvent.setup();
    let cozumle: (() => void) | undefined;
    const eylem = () =>
      new Promise<void>((resolve) => {
        cozumle = resolve;
      });

    render(
      <EylemDugmesi mesgulEtiketi="Siliniyor" onEylem={eylem}>
        Sil
      </EylemDugmesi>,
    );

    await kullanici.click(screen.getByRole('button'));

    const dugme = screen.getByRole('button');
    expect(dugme).toHaveAttribute('aria-busy', 'true');
    expect(dugme).toBeDisabled();
    expect(dugme).toHaveTextContent('Siliniyor…');

    (cozumle as (() => void) | undefined)?.();
    await waitFor(() => expect(screen.getByRole('button')).toHaveTextContent('Sil'));
  });

  it('hata onHata ile BILDIRILIR, dugme yeniden kullanilabilir olur', async () => {
    const kullanici = userEvent.setup();
    const hata = new Error('sunucu hatası');
    const eylem = vi.fn(() => Promise.reject(hata));
    const onHata = vi.fn();

    render(
      <EylemDugmesi mesgulEtiketi="Taşınıyor" onEylem={eylem} onHata={onHata}>
        Taşı
      </EylemDugmesi>,
    );

    await kullanici.click(screen.getByRole('button'));

    // İki iddia: hata GÖRÜNÜR oldu (sessiz yutma yok) ve düğme KİLİTLİ KALMADI
    // (kullanıcı yeniden deneyebilmeli — B11'in "hatada yeniden dene" ayağı).
    await waitFor(() => expect(onHata).toHaveBeenCalledWith(hata));
    expect(screen.getByRole('button')).not.toBeDisabled();
    expect(eylem).toHaveBeenCalledTimes(1);
  });

  it('disabled verilirse hic calismaz', async () => {
    const kullanici = userEvent.setup();
    const eylem = vi.fn(() => Promise.resolve());

    render(
      <EylemDugmesi mesgulEtiketi="Taşınıyor" onEylem={eylem} disabled>
        Taşı
      </EylemDugmesi>,
    );

    await kullanici.click(screen.getByRole('button'));

    expect(eylem).not.toHaveBeenCalled();
  });
});
