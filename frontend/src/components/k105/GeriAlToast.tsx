import { useCallback, useRef } from 'react';
import { useToast } from '../Toast';
import { messageOf } from '../../lib/useAsync';

/**
 * K105 §2.1/§2.6 — GERİ ALINABİLİR EYLEM: 5 sn toast, onay kutusu YOK.
 * Mevcut `Toast` kalıbı korunur (D1.8: geri alma bildirimin içindedir,
 * imleç üstündeyken sayaç durur); bu hook onu iki kipte sarar:
 *
 *   · `geriAlinabilir(mesaj, uygula, geriAl)` — eylem HEMEN yapılır, toast
 *     içindeki "Geri al" tersini çalıştırır (çöp kutusu olan kayıtlar).
 *   · `ertelenmis(mesaj, uygula)` — eylem toast KAPANANA KADAR ertelenir;
 *     "Geri al" basılırsa hiç yapılmaz (çöp kutusu OLMAYAN kayıtlar: liste
 *     şablonu). Sayfa kapanırsa bekleyen eylemler `flush` ile hemen işlenir —
 *     geri alma penceresi kaybolur ama eylem de kaybolmaz.
 *
 * Her iki kipte hata toast'a düşer (§2.5: hiçbir eylem sessiz çalışmaz).
 */
export interface GeriAl {
  geriAlinabilir: (mesaj: string, uygula: () => Promise<unknown>, geriAl: () => Promise<unknown>, sonra?: () => void) => Promise<void>;
  ertelenmis: (mesaj: string, uygula: () => Promise<unknown>, sonra?: () => void) => void;
  /** Bekleyen ertelenmiş eylemleri hemen çalıştırır (sayfadan ayrılırken). */
  flush: () => Promise<void>;
}

export const GERI_AL_PENCERESI_MS = 5000;

export function useGeriAl(): GeriAl {
  const push = useToast((state) => state.push);
  const bekleyenler = useRef<Map<number, { calistir: () => Promise<void>; zamanlayici: number }>>(new Map());
  const sira = useRef(0);

  const geriAlinabilir = useCallback<GeriAl['geriAlinabilir']>(
    async (mesaj, uygula, geriAl, sonra) => {
      try {
        await uygula();
        sonra?.();
        push(mesaj, 'success', async () => {
          try {
            await geriAl();
            sonra?.();
            push('Geri alındı.');
          } catch (hata) {
            push(messageOf(hata), 'error');
          }
        });
      } catch (hata) {
        push(messageOf(hata), 'error');
      }
    },
    [push],
  );

  const ertelenmis = useCallback<GeriAl['ertelenmis']>(
    (mesaj, uygula, sonra) => {
      sira.current += 1;
      const id = sira.current;
      let iptal = false;
      const calistir = async () => {
        const kayit = bekleyenler.current.get(id);
        if (!kayit) return;
        bekleyenler.current.delete(id);
        window.clearTimeout(kayit.zamanlayici);
        if (iptal) return;
        try {
          await uygula();
          sonra?.();
        } catch (hata) {
          push(messageOf(hata), 'error');
        }
      };
      // Toast 5 sn'de kapanır; eylem de aynı pencerede — iki sayaç aynı süreyi paylaşır.
      const zamanlayici = window.setTimeout(() => void calistir(), GERI_AL_PENCERESI_MS + 250);
      bekleyenler.current.set(id, { calistir, zamanlayici });
      push(mesaj, 'success', () => {
        iptal = true;
        const kayit = bekleyenler.current.get(id);
        if (kayit) {
          window.clearTimeout(kayit.zamanlayici);
          bekleyenler.current.delete(id);
        }
        sonra?.();
        push('Geri alındı; hiçbir şey silinmedi.');
      });
    },
    [push],
  );

  const flush = useCallback(async () => {
    const isler = Array.from(bekleyenler.current.values()).map((k) => k.calistir());
    await Promise.all(isler);
  }, []);

  return { geriAlinabilir, ertelenmis, flush };
}
