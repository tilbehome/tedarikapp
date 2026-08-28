import { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { inbox as inboxApi } from '../api/endpoints';

/**
 * Menüdeki Gelen Kutusu rozeti (İE#16 D1.5).
 *
 * Sayı SIFIRSA rozet basılmaz (kanon §3), bu yüzden hook 0 döndürmekten
 * çekinmez. Değer, gelen kutusundan her ayrılışta tazelenir: kullanıcı kutuyu
 * boşalttıktan sonra menüde eski sayının durması yanlış olurdu.
 *
 * Yoklama (polling) YAPILMAZ: sürekli istek paylaşımlı hostingde gereksiz yüktür
 * ve rozetin saniyelik güncelliği bir değer taşımaz.
 */
export function useGelenKutusuSayisi(): number {
  const [sayi, setSayi] = useState(0);
  const konum = useLocation();

  useEffect(() => {
    let iptal = false;

    inboxApi
      .queue({ page: 1 })
      .then((yanit) => {
        if (iptal) return;
        const toplam = Number(yanit.meta?.total ?? (yanit.data ?? []).length);
        setSayi(Number.isFinite(toplam) ? toplam : 0);
      })
      .catch(() => {
        // Rozet ikincil bilgidir: hata durumunda sessizce 0 kalır, ekran bozulmaz.
        if (!iptal) setSayi(0);
      });

    return () => {
      iptal = true;
    };
    // Gelen kutusundan ÇIKARKEN de tazelensin diye yola bağlı.
  }, [konum.pathname]);

  return sayi;
}
