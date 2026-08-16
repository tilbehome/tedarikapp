import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '../api/client';
import { errorMessages } from '../locales/tr';

/** ApiError'ı kullanıcıya gösterilecek Türkçe metne çevirir. */
export function messageOf(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message || errorMessages[error.code] || 'Beklenmeyen bir hata oluştu.';
  }
  return 'Beklenmeyen bir hata oluştu.';
}

interface AsyncState<T> {
  data: T | null;
  loading: boolean;
  error: string | null;
}

/**
 * Veri çekme kancası: yükleme/hata/veri üçlüsünü tek yerde tutar, böylece
 * her ekran aynı iskelet + hata + boş durum davranışını gösterir (docs/09 §5).
 *
 * `deps` değişince yeniden çeker; bileşen sökülürse sonucu yazmaz.
 */
export function useAsync<T>(loader: () => Promise<T>, deps: unknown[]): AsyncState<T> & { reload: () => void } {
  const [state, setState] = useState<AsyncState<T>>({ data: null, loading: true, error: null });
  const [tick, setTick] = useState(0);
  const alive = useRef(true);

  // Bağımlılıklar çağıran ekranın elinde; loader her render'da yeni referans olur.
  const run = useCallback(loader, deps);

  useEffect(() => {
    alive.current = true;
    setState((previous) => ({ ...previous, loading: true, error: null }));

    run()
      .then((data) => {
        if (alive.current) setState({ data, loading: false, error: null });
      })
      .catch((error: unknown) => {
        if (alive.current) setState({ data: null, loading: false, error: messageOf(error) });
      });

    return () => {
      alive.current = false;
    };
  }, [run, tick]);

  return { ...state, reload: () => setTick((value) => value + 1) };
}
