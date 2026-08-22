import { useEffect, useRef, useState } from 'react';

/**
 * ARAMA KUTUSU DİSİPLİNİ (İE#19 E12).
 *
 * Panel aramaları her tuş vuruşunda istek atıyordu. İki somut sonucu vardı:
 *
 *  1. "mutfak" yazmak 6 istek üretiyordu; beşi daha yanıt dönmeden gereksizdi.
 *     Paylaşımlı hostingde bu, arama sırasında panelin geri kalanını yavaşlatıyordu.
 *  2. YARIŞ: "mu" isteği ağda takılıp "mutfak" isteğinden SONRA dönerse, ekranda
 *     kullanıcının yazdığından farklı bir sonuç kümesi kalıyordu. Kullanıcı bunu
 *     "arama bozuk" diye rapor ediyor, biz de tekrar üretemiyorduk.
 *
 * Çözüm iki parçalıdır ve ikisi de gereklidir: bekleme (debounce) istek SAYISINI
 * düşürür, iptal (AbortController) YARIŞI ortadan kaldırır. Yalnız debounce
 * yetmez — yavaş ağda iki istek yine üst üste biner.
 *
 * Kullanım:
 *   const { deger, gecikmeli, yaz, signal } = useAramaSorgusu();
 *   <input value={deger} onChange={(e) => yaz(e.target.value)} />
 *   useEffect(() => { ...api.get(..., { signal }) }, [gecikmeli]);
 */

/** Emirdeki aralık: 250–300 ms. */
export const ARAMA_GECIKMESI_MS = 280;

export interface AramaSorgusu {
  /** Kutuya yazılan anlık değer (girdi bunu gösterir — yazarken takılma olmaz). */
  deger: string;
  /** Bekleme sonrası sabitlenen değer — istek BUNA göre atılır. */
  gecikmeli: string;
  yaz: (yeni: string) => void;
  temizle: () => void;
  /** Yeni istek açarken verilir; önceki istek otomatik iptal edilir. */
  yeniSignal: () => AbortSignal;
}

export function useAramaSorgusu(baslangic = '', gecikmeMs = ARAMA_GECIKMESI_MS): AramaSorgusu {
  const [deger, setDeger] = useState(baslangic);
  const [gecikmeli, setGecikmeli] = useState(baslangic);
  const controllerRef = useRef<AbortController | null>(null);

  useEffect(() => {
    if (deger === gecikmeli) return;
    const zaman = window.setTimeout(() => setGecikmeli(deger), gecikmeMs);
    return () => window.clearTimeout(zaman);
  }, [deger, gecikmeli, gecikmeMs]);

  // Ekran kapanırken uçan istek de iptal edilir (kaybolmuş bileşene setState yok).
  useEffect(() => () => controllerRef.current?.abort(), []);

  return {
    deger,
    gecikmeli,
    yaz: setDeger,
    temizle: () => setDeger(''),
    yeniSignal: () => {
      controllerRef.current?.abort();
      const taze = new AbortController();
      controllerRef.current = taze;
      return taze.signal;
    },
  };
}
