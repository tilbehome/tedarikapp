import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { GERI_AL_PENCERESI_MS, useGeriAl } from './GeriAlToast';
import { useToast } from '../Toast';

/**
 * K105 §2.1/§2.6 — geri alınabilir eylem: onay kutusu YOK, 5 sn pencere.
 *   · `geriAlinabilir`: eylem hemen; toast'taki geri al tersini çalıştırır.
 *   · `ertelenmis`: eylem pencere kapanana kadar YAPILMAZ; geri al basılırsa hiç yapılmaz;
 *     `flush` bekleyeni hemen işler (sayfadan ayrılırken kayıp yok).
 */
describe('useGeriAl', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    useToast.setState({ items: [] });
  });
  afterEach(() => {
    vi.useRealTimers();
  });

  test('geriAlinabilir: eylem hemen yapılır, geri al tersini çalıştırır', async () => {
    const uygula = vi.fn(async () => undefined);
    const geriAl = vi.fn(async () => undefined);
    const { result } = renderHook(() => useGeriAl());

    await act(async () => {
      await result.current.geriAlinabilir('Silindi.', uygula, geriAl);
    });
    expect(uygula).toHaveBeenCalledTimes(1);
    const toast = useToast.getState().items.at(-1);
    expect(toast?.message).toBe('Silindi.');
    expect(toast?.undo).toBeDefined();

    await act(async () => {
      await toast?.undo?.();
    });
    expect(geriAl).toHaveBeenCalledTimes(1);
  });

  test('ertelenmis: pencere içinde geri al → eylem hiç çağrılmaz; pencere dolunca çağrılır', async () => {
    const sil = vi.fn(async () => undefined);
    const { result } = renderHook(() => useGeriAl());

    act(() => result.current.ertelenmis('Silinecek.', sil));
    expect(sil).not.toHaveBeenCalled();
    const toast = useToast.getState().items.at(-1);
    await act(async () => {
      await toast?.undo?.();
    });
    await act(async () => {
      vi.advanceTimersByTime(GERI_AL_PENCERESI_MS + 1000);
    });
    expect(sil).not.toHaveBeenCalled();

    act(() => result.current.ertelenmis('Silinecek 2.', sil));
    await act(async () => {
      vi.advanceTimersByTime(GERI_AL_PENCERESI_MS + 1000);
    });
    expect(sil).toHaveBeenCalledTimes(1);
  });

  test('flush: bekleyen ertelenmiş eylem hemen işlenir', async () => {
    const sil = vi.fn(async () => undefined);
    const { result } = renderHook(() => useGeriAl());

    act(() => result.current.ertelenmis('Silinecek.', sil));
    await act(async () => {
      await result.current.flush();
    });
    expect(sil).toHaveBeenCalledTimes(1);
  });
});
