import { useEffect, useState } from 'react';
import { settings as settingsApi } from '../api/endpoints';

/**
 * Medya modu (K33): `download` → görseller sunucuda arşivlenir, `hotlink` → arşivleme
 * kapalı, orijinal bağlantı gösterilir. Ürün formu buna göre rozet gösterir.
 *
 * Oturum boyunca bir kez okunur; hata durumunda ekran çalışmaya devam eder
 * (rozet gösterilmez), çünkü bu bilgi ürün eklemenin ön şartı değildir.
 */
let cached: 'download' | 'hotlink' | null = null;

export function useMediaMode(): { mode: 'download' | 'hotlink' | null } {
  const [mode, setMode] = useState<'download' | 'hotlink' | null>(cached);

  useEffect(() => {
    if (cached !== null) return;
    let alive = true;
    settingsApi
      .read()
      .then((data) => {
        if (!alive || data.media_mode === null || data.media_mode === undefined) return;
        cached = data.media_mode;
        setMode(data.media_mode);
      })
      .catch(() => {
        /* Rozet gösterilemezse ekran yine de çalışır. */
      });
    return () => {
      alive = false;
    };
  }, []);

  return { mode };
}
