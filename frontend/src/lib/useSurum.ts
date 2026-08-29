import { useEffect, useState } from 'react';
import { system as systemApi } from '../api/endpoints';

/**
 * ÇALIŞAN SÜRÜM (V3-B bulgu #2).
 *
 * ÖNCESİ: marka bloğundaki rozet `import.meta.env.VITE_SURUM ?? '1.0'`
 * yazıyordu ve `VITE_SURUM` HİÇBİR YERDE TANIMLI DEĞİLDİ — yani rozet her
 * zaman "1.0" basıyordu. Uygulama 1.2.0 iken kullanıcıya 1.0 gösteriliyordu;
 * destek isteyen kişi yanlış sürümü söylerdi.
 *
 * SONRASI: değer SUNUCUDAN gelir (`/api/system/status` → `app_version`), yani
 * `AppVersion::VALUE` — release paketinin damgaladığı tek kaynak. Derleme
 * zamanında gömmek cazipti ama yanlış olurdu: paket, panel derlendikten SONRA
 * damgalanıyor; gömülü değer damgadan sapabilirdi.
 *
 * ALINAMAZSA ROZET BASILMAZ (null döner). Uydurma bir sürüm göstermek, hiç
 * göstermemekten kötüdür — "bilinmeyen ≠ varsayılan" (K67 ile aynı disiplin).
 *
 * Modül düzeyinde önbelleklenir: kabuk her gezinmede yeniden kurulsa da istek
 * bir kez atılır.
 */
let onbellek: string | null = null;
let istekSuruyor: Promise<void> | null = null;

export function useSurum(): string | null {
  const [surum, setSurum] = useState<string | null>(onbellek);

  useEffect(() => {
    if (onbellek !== null) return;

    istekSuruyor ??= systemApi
      .status()
      .then((durum) => {
        onbellek = durum.app_version;
      })
      .catch(() => {
        // Sürüm alınamadı: rozet basılmaz. Panel çalışmaya devam eder.
      })
      .finally(() => {
        istekSuruyor = null;
      });

    let iptal = false;
    void istekSuruyor.then(() => {
      if (!iptal) setSurum(onbellek);
    });

    return () => {
      iptal = true;
    };
  }, []);

  return surum;
}
