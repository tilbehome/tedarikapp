import { useCallback, useEffect, useState } from 'react';
import { bildirimler as bildirimApi } from '../api/endpoints';

/**
 * OKUNMAMIŞ BİLDİRİM SAYACI (V3-B A4).
 *
 * `useGelenKutusuSayisi` ile aynı kalıptadır: ucuz bir sayaç ucu okunur, sonuç
 * üst çubuk rozetine verilir.
 *
 * YOKLAMA ARALIĞI YOKTUR. Bir zamanlayıcı koymak cazip görünür ama yanlıştır:
 * tek kullanıcılı bir panelde saniyede bir istek atmak, sunucuyu boşuna
 * meşgul eder ve paylaşımlı hostingte istek kotasını yer. Sayaç şu üç anda
 * tazelenir: ekran açılışı, bildirim merkezinin kapanışı ve okundu işareti.
 * Bunlar kullanıcının sayıya BAKTIĞI anlardır — arada geçen sürede sayının
 * bir eksik olması kimseyi yanıltmaz.
 */
export function useBildirimSayisi(): { sayi: number; tazele: () => void; ayarla: (n: number) => void } {
  const [sayi, setSayi] = useState(0);

  const tazele = useCallback((): void => {
    bildirimApi
      .sayac()
      .then((veri) => setSayi(veri.okunmamis))
      // Sayaç YARDIMCIDIR: alınamazsa rozet basılmaz, panel çalışmaya devam
      // eder. Kullanıcıya hata göstermek, bir rozet için gürültü olurdu.
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    tazele();
  }, [tazele]);

  return { sayi, tazele, ayarla: setSayi };
}
