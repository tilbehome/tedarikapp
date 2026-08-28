import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';

/**
 * EKRAN DURUMU URL'DE (İE#16 D1.4).
 *
 * Filtre, sıralama, sayfa ve sekme seçimi bileşen state'inde DEĞİL adres
 * çubuğunda durur. Üç kazanç:
 *   1. Link paylaşılabilir: "şu süzgeçle bak" demek adresi göndermektir.
 *   2. Geri tuşu doğru çalışır — filtre değiştirmek bir adımdır, geri alınır.
 *   3. Sayfa yenilenince görünüm kaybolmaz.
 *
 * VARSAYILAN DEĞER ADRESE YAZILMAZ: "sayfa=1" ya da boş süzgeç URL'i kirletmez;
 * temiz adres paylaşılabilir adrestir.
 */
export function useUrlDurumu<T extends Record<string, string | number>>(
  varsayilanlar: T,
): [T, (yeni: Partial<T>) => void] {
  const [params, setParams] = useSearchParams();

  const durum = useMemo(() => {
    const cikti = { ...varsayilanlar };
    for (const anahtar of Object.keys(varsayilanlar) as (keyof T)[]) {
      const ham = params.get(String(anahtar));
      if (ham === null) continue;
      cikti[anahtar] = (
        typeof varsayilanlar[anahtar] === 'number' ? (Number(ham) || varsayilanlar[anahtar]) : ham
      ) as T[keyof T];
    }

    return cikti;
    // params referansı her gezinmede değişir; varsayılanlar sabittir.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [params]);

  const guncelle = useCallback(
    (yeni: Partial<T>) => {
      const sonraki = new URLSearchParams(params);
      for (const [anahtar, deger] of Object.entries(yeni)) {
        const varsayilan = varsayilanlar[anahtar as keyof T];
        if (deger === undefined || deger === '' || deger === varsayilan) {
          sonraki.delete(anahtar);
        } else {
          sonraki.set(anahtar, String(deger));
        }
      }
      // Süzgeç değişince sayfa başa döner: 3. sayfadayken filtre daraltmak
      // kullanıcıyı boş sayfada bırakırdı.
      if (!('page' in yeni) && 'page' in varsayilanlar) sonraki.delete('page');

      setParams(sonraki, { replace: false });
    },
    [params, setParams, varsayilanlar],
  );

  return [durum, guncelle];
}
