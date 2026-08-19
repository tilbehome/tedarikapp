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
  // react-hooks 7 dizi LİTERALİ ister — bu kanca deps'i bilerek dışarıdan alır
  // (jenerik veri çekme kancasının tüm anlamı bu). F41: kancanın imzası v2'de
  // gözden geçirilecek; şimdi davranış değişmemeli.
  // eslint-disable-next-line react-hooks/exhaustive-deps, react-hooks/use-memo
  const run = useCallback(loader, deps);

  useEffect(() => {
    alive.current = true;
    // react-hooks 7 "set-state-in-effect": burada amaç DIŞ SİSTEMLE (API) eşitlenmek;
    // yükleme bayrağı isteğin başladığı anda kalkmalı. F41 kapsamında ele alınacak.
    // eslint-disable-next-line react-hooks/set-state-in-effect
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
