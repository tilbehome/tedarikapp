/**
 * ISOLATED köprü (İE#11 C3 + İE#13 A1): iki iş yapar.
 *  1. Popup "PAGE_DATA" ister → MAIN world'e postMessage → yanıtı popup'a döndürür.
 *  2. Sayfaya mini paneli basar (aynı sayfa verisi okuyucusunu paylaşır).
 *
 * chrome.* API'si yalnız BU dünyada vardır; MAIN world script'i sayfanın
 * `window.context`ine bakar. Sayfa scriptleri bu dünyaya erişemez.
 */

import { defineContentScript } from 'wxt/sandbox';

import { mountMiniPanel } from '../ui/miniPanel';
import type { PageData } from '../core/types';

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

export default defineContentScript({
  matches: ['https://detail.1688.com/*'],
  main() {
    chrome.runtime.onMessage.addListener((message: { type: string }, _sender, sendResponse) => {
      if (message.type !== 'PAGE_DATA') return false;
      void sayfaVerisiniOku().then(sendResponse);

      return true; // async yanıt
    });

    // Sayfa içi yakalama (A1): gövde hazır olunca düğmeyi bas.
    if (document.body !== null) {
      mountMiniPanel(sayfaVerisiniOku);
    } else {
      document.addEventListener('DOMContentLoaded', () => mountMiniPanel(sayfaVerisiniOku), { once: true });
    }
  },
});
