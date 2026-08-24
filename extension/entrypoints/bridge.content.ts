/**
 * ISOLATED köprü (İE#11 C3 · İE#13 A1 · İE#21 A1 ile v2'ye geçti).
 *
 * Üç iş:
 *  1. MAIN world'den sayfa verisini ister (popup ve sayfa içi arayüz aynı okuyucuyu
 *     paylaşır),
 *  2. sayfaya v2 arayüzünü basar (satır içi düğme / pill + çekmece),
 *  3. SPA yönlendirmelerini izleyip önizlemeyi temizler (EKL-23).
 *
 * chrome.* API'si yalnız BU dünyada vardır; MAIN world script'i sayfanın
 * `window.context`ine bakar. Sayfa scriptleri bu dünyaya erişemez ve token bu
 * katmana hiç inmez (K34) — ağ işini background yapar.
 */

import { defineContentScript } from 'wxt/utils/define-content-script';

import { Disclosure } from '../core/disclosure';
import { GOMULU_SECICILER, secicileriSec } from '../core/secici';
import { fiksturdenGecer } from '../core/fiksturKapisi';
import { parse1688 } from '../modules/m1688/parser';
import { Akis, type GonderimYaniti } from '../ui/v2/akis';
import { cekmeceKur } from '../ui/v2/cekmece';
import { montajYap, montajiKaldir, offerId } from '../ui/v2/montaj';
import type { PageData, ParseResult, SelectorSet } from '../core/types';
import type { DuranKayit, HedefSecimi } from '../ui/v2/panel';

const ZAMAN_ASIMI_MS = 3000;

/** MAIN world'den sayfa verisini ister; yanıt gelmezse hata değil, ok:false döner. */
function sayfaVerisiniOku(): Promise<PageData> {
  return new Promise((resolve) => {
    const timeout = setTimeout(() => {
      window.removeEventListener('message', onMessage);
      resolve({ ok: false, error: 'Sayfa verisi zaman aşımı.' });
    }, ZAMAN_ASIMI_MS);

    const onMessage = (event: MessageEvent) => {
      const data = event.data as { type?: string; context?: unknown; dom?: Record<string, string | null>; url?: string };
      if (event.source !== window || data?.type !== 'TEDARIKAPP_CONTEXT_RESPONSE') return;
      clearTimeout(timeout);
      window.removeEventListener('message', onMessage);
      resolve({ ok: true, context: data.context, dom: data.dom, url: data.url });
    };

    window.addEventListener('message', onMessage);
    window.postMessage({ type: 'TEDARIKAPP_CONTEXT_REQUEST' }, '*');
  });
}

function arkaPlan<T>(type: string, payload?: unknown): Promise<T> {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage({ type, payload }, (yanit: { ok: boolean; data?: T; error?: string }) => {
      if (yanit?.ok === true) resolve(yanit.data as T);
      else reject(new Error(yanit?.error ?? 'BILINMEYEN_HATA'));
    });
  });
}

/** Seçici paketi: panelden iner, fikstür kapısından geçerse kabul edilir (A6). */
async function seciciler(): Promise<SelectorSet> {
  try {
    const uzak = await arkaPlan<unknown>('SELECTORS');

    return secicileriSec(GOMULU_SECICILER, uzak, fiksturdenGecer).secilen;
  } catch {
    // Ağ yok / ayar eksik: gömülü paketle çalışmaya devam.
    return GOMULU_SECICILER;
  }
}

async function ayristir(): Promise<ParseResult> {
  const sayfa = await sayfaVerisiniOku();
  if (!sayfa.ok) throw new Error(sayfa.error ?? 'SAYFA_OKUNAMADI');

  const set = await seciciler();

  return parse1688(sayfa.context, set, sayfa.url ?? location.href, sayfa.dom ?? {});
}

function yukKur(captureId: string, hedef: HedefSecimi, sonuc: ParseResult, surum: string): Record<string, unknown> {
  return {
    capture_id: captureId,
    schema_version: 2,
    extension_version: surum,
    parser_version: sonuc.source.platform === '1688' ? '1688-2026.08.2' : 'bilinmiyor',
    target_list_id: hedef.listeId,
    qty: hedef.miktar,
    units_per_carton: null,
    note: hedef.not === '' ? null : hedef.not,
    source: sonuc.source,
    raw: sonuc.raw,
    normalized: sonuc.normalized,
  };
}

export default defineContentScript({
  matches: ['https://detail.1688.com/*'],
  main() {
    chrome.runtime.onMessage.addListener((message: { type: string }, _sender, sendResponse) => {
      if (message.type !== 'PAGE_DATA') return false;
      void sayfaVerisiniOku().then(sendResponse);

      return true; // async yanıt
    });

    const disclosure = new Disclosure({
      get: (anahtar) => chrome.storage.local.get(anahtar),
      set: (deger) => chrome.storage.local.set(deger),
    });

    const kur = (): void => {
      const akis = new Akis({
        ayristir,
        gonder: async ({ captureId, hedef, sonuc }): Promise<GonderimYaniti> => {
          const surum = chrome.runtime.getManifest().version;
          try {
            const yanit = await arkaPlan<{ inbox_id?: number; product_id?: number | null; duplicate?: boolean }>(
              'CAPTURE',
              yukKur(captureId, hedef, sonuc, surum),
            );

            return yanit.duplicate === true
              ? { sonuc: 'MUKERRER', urunId: yanit.product_id ?? null }
              : { sonuc: 'BASARILI', urunId: yanit.product_id ?? null };
          } catch (hata) {
            const mesaj = hata instanceof Error ? hata.message : String(hata);

            return /401|403|AYAR_EKSIK|YETKI/i.test(mesaj)
              ? { sonuc: 'YETKI' }
              : { sonuc: 'SUNUCU', hata: mesaj };
          }
        },
        onayliMi: () => disclosure.onayliMi(),
        duranlar: async (): Promise<DuranKayit[]> => {
          try {
            return await arkaPlan<DuranKayit[]>('KUYRUK_DURANLAR');
          } catch {
            return [];
          }
        },
        listeler: async () => {
          try {
            const liste = await arkaPlan<{ id: number; name: string }[]>('LISTS');

            return [{ id: null, ad: 'Gelen Kutusu (varsayılan)' }, ...liste.map((l) => ({ id: l.id, ad: l.name }))];
          } catch {
            return [{ id: null, ad: 'Gelen Kutusu (varsayılan)' }];
          }
        },
        kimlikUret: () => crypto.randomUUID(),
        // EKL-22: son hedef liste hatırlanır (cihaz yerelinde).
        sonListeyiOku: async () => {
          const kayit = await chrome.storage.local.get('sonHedefListe');
          const deger = kayit.sonHedefListe;

          return typeof deger === 'number' ? deger : null;
        },
        sonListeyiYaz: async (listeId) => {
          await chrome.storage.local.set({ sonHedefListe: listeId });
        },
        // EKL-13/17: kayıt panelde açılır. Adres ayarlardan gelir; token gitmez.
        paneldeAc: (urunId) => {
          void chrome.storage.local.get('panelUrl').then((kayit) => {
            const taban = typeof kayit.panelUrl === 'string' ? kayit.panelUrl.replace(/\/+$/, '') : '';
            if (taban === '') return;
            const yol = urunId === null ? '/panel/gelen-kutusu' : `/panel/urun/${urunId}`;
            window.open(taban + yol, '_blank', 'noopener');
          });
        },
        ciz: (gorunum) => cekmece.ciz(gorunum),
      });

      const cekmece = cekmeceKur({
        eylemler: {
          onTara: () => void akis.tara(),
          onGonder: () => void akis.gonder(),
          onKapat: () => akis.kapat(),
          onDevam: () => akis.devam(),
          onMukerrer: (secenek) => void akis.mukerrerSecenek(secenek),
          onHedef: (hedef) => akis.hedefDegistir(hedef),
          onVaryant: (varyant) => akis.varyantSec(varyant),
          onDisclosure: (onay) => {
            void (onay
              ? disclosure.onayla(new Date().toISOString())
              : disclosure.reddet(new Date().toISOString())
            ).then(() => akis.disclosureKarari(onay));
          },
          onPaneldeAc: () => akis.paneldeAc(),
          onKuyruk: (captureId, eylem) => {
            void arkaPlan('KUYRUK_EYLEM', { captureId, eylem }).then(() => akis.duranlariTazele());
          },
        },
        tetikleyici: null,
      });

      const montaj = montajYap({
        onTikla: () => {
          cekmece.ac();
          void akis.ac().then(() => {
            if (!akis.gorunum().disclosureGerekli) void akis.tara();
          });
        },
      });

      // SPA yönlendirmesi: offer değişince önizleme temizlenir (EKL-23).
      let sonOffer = offerId(location.href);
      window.setInterval(() => {
        const simdiki = offerId(location.href);
        if (simdiki !== sonOffer) {
          sonOffer = simdiki;
          akis.sayfaDegisti();
          cekmece.kapat();
          montajiKaldir();
          kur();
        }
      }, 1000);

      if (montaj.tur === 'YOK') {
        // Ürün sayfası değil: hiçbir şey basılmadı, akış da başlatılmaz.
        cekmece.kapat();
      }
    };

    if (document.body !== null) {
      kur();
    } else {
      document.addEventListener('DOMContentLoaded', kur, { once: true });
    }
  },
});
