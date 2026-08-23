import { describe, expect, test, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DesteModu, { type DesteSonucu } from './DesteModu';
import type { InboxItem } from '../../api/endpoints';

/**
 * DESTE MODU — A sınıfı (sahte veri, gerçek tuş olayları).
 *
 * Kapsanan senaryolar: E2E-PNL-16 (J/K yalnız odak) · E2E-PNL-17 (Space seçim).
 * Katalogun kuralı gereği store metodu DOĞRUDAN çağrılmaz; fiziksel tuş olayları
 * gönderilir — kısayolun gerçekten bağlı olup olmadığı ancak böyle sınanır.
 */

function kart(id: number, ad: string): InboxItem {
  return {
    id,
    status: 'pending',
    platform: '1688',
    external_id: `DM-00${id}`,
    name: ad,
    price_yuan: '12.50',
    image_url: null,
    url: null,
    error_note: null,
    created_at: '2026-08-23T10:00:00+03:00',
  } as InboxItem;
}

const KARTLAR = [kart(1, 'Birinci'), kart(2, 'İkinci'), kart(3, 'Üçüncü')];

function kur(onEylem = vi.fn<() => Promise<DesteSonucu | null>>()) {
  const onGeriAl = vi.fn(async () => {});
  const onKapat = vi.fn();
  render(
    <DesteModu
      kartlar={KARTLAR}
      hedefListeAdi="Eylül Listesi"
      onEylem={onEylem as never}
      onGeriAl={onGeriAl}
      onKapat={onKapat}
    />,
  );

  return { onEylem, onGeriAl, onKapat };
}

function odaktakiKart(): string {
  return screen.getByTestId('deste-kart').getAttribute('data-inbox-id') ?? '';
}

describe('E2E-PNL-16 — J/K ile odak, mutasyon YOK', () => {
  test('J odağı ilerletir, K geri alır', async () => {
    const kullanici = userEvent.setup();
    kur();

    expect(odaktakiKart()).toBe('1');

    await kullanici.keyboard('j');
    expect(odaktakiKart()).toBe('2');

    await kullanici.keyboard('j');
    await kullanici.keyboard('k');
    await kullanici.keyboard('k');
    expect(odaktakiKart()).toBe('1');
  });

  test('odak SINIRLARDA durur — taşma yok', async () => {
    const kullanici = userEvent.setup();
    kur();

    await kullanici.keyboard('kkk');
    expect(odaktakiKart()).toBe('1');

    await kullanici.keyboard('jjjjj');
    expect(odaktakiKart()).toBe('3');
  });

  test('gezinme HİÇBİR eylem tetiklemez', async () => {
    const kullanici = userEvent.setup();
    const { onEylem, onGeriAl } = kur();

    await kullanici.keyboard('jjkk');

    // Gezinirken veri değiştiren arayüzde kullanıcı hızlanamaz.
    expect(onEylem).not.toHaveBeenCalled();
    expect(onGeriAl).not.toHaveBeenCalled();
  });
});

describe('E2E-PNL-17 — Space seçimi açar/kapatır', () => {
  test('ilk basış seçer, ikinci basış bırakır', async () => {
    const kullanici = userEvent.setup();
    kur();

    expect(screen.queryByTestId('secim-rozeti')).toBeNull();

    await kullanici.keyboard(' ');
    expect(screen.getByTestId('secim-rozeti')).toBeTruthy();
    expect(screen.getByText('1 / 3 · 1 seçili')).toBeTruthy();

    await kullanici.keyboard(' ');
    expect(screen.queryByTestId('secim-rozeti')).toBeNull();
    expect(screen.getByText('1 / 3 · 0 seçili')).toBeTruthy();
  });

  test('Space eylem çağırmaz', async () => {
    const kullanici = userEvent.setup();
    const { onEylem } = kur();

    await kullanici.keyboard(' ');

    expect(onEylem).not.toHaveBeenCalled();
  });
});

describe('Ok tuşları hedeflere bağlanır (E2E-PNL-18 arayüz yüzü)', () => {
  test('← çöp · ↓ havuz · → liste', async () => {
    const kullanici = userEvent.setup();
    const onEylem = vi.fn(async () => null);
    kur(onEylem as never);

    await kullanici.keyboard('{ArrowLeft}');
    await kullanici.keyboard('{ArrowDown}');
    await kullanici.keyboard('{ArrowRight}');

    expect(onEylem.mock.calls.map((c) => (c as unknown as [string])[0])).toEqual(['cop', 'havuz', 'liste']);
  });
});

describe('Geri al tek kullanımlıktır (E2E-PNL-19 arayüz yüzü)', () => {
  test('geri alınamaz eylemden sonra düğme kapalı kalır', async () => {
    const kullanici = userEvent.setup();
    const onEylem = vi.fn(async () => ({
      inbox_id: 1,
      urun_id: null,
      hedef: 'cop' as const,
      geri_alinabilir: false,
    }));
    kur(onEylem as never);

    await kullanici.keyboard('{ArrowLeft}');

    expect(screen.getByTestId('geri-al').hasAttribute('disabled')).toBe(true);
  });

  test('geri alınabilir eylemden sonra bir kez çalışır', async () => {
    const kullanici = userEvent.setup();
    const onEylem = vi.fn(async () => ({
      inbox_id: 3,
      urun_id: 42,
      hedef: 'liste' as const,
      geri_alinabilir: true,
    }));
    const { onGeriAl } = kur(onEylem as never);

    await kullanici.keyboard('{ArrowRight}');
    expect(screen.getByTestId('geri-al').hasAttribute('disabled')).toBe(false);

    await kullanici.keyboard('z');
    expect(onGeriAl).toHaveBeenCalledTimes(1);

    // İkinci basış ETKİSİZ: sayaç bozulmaz, ürün iki kez geri gelmez.
    await kullanici.keyboard('z');
    expect(onGeriAl).toHaveBeenCalledTimes(1);
  });
});
