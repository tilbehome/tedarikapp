/**
 * ISOLATED köprü (İE#11 C3): MAIN world'deki context yanıtını chrome.runtime'a taşır.
 * Popup "PAGE_DATA" ister → köprü MAIN'e postMessage → yanıtı popup'a döndürür.
 */

import { defineContentScript } from 'wxt/sandbox';

export default defineContentScript({
  matches: ['https://detail.1688.com/*'],
  main() {
    chrome.runtime.onMessage.addListener((message: { type: string }, _sender, sendResponse) => {
      if (message.type !== 'PAGE_DATA') return false;

      const timeout = setTimeout(() => sendResponse({ ok: false, error: 'Sayfa verisi zaman aşımı.' }), 3000);
      const onMessage = (event: MessageEvent) => {
        const data = event.data as { type?: string; context?: unknown; dom?: unknown; url?: string };
        if (event.source !== window || data?.type !== 'TEDARIKAPP_CONTEXT_RESPONSE') return;
        clearTimeout(timeout);
        window.removeEventListener('message', onMessage);
        sendResponse({ ok: true, context: data.context, dom: data.dom, url: data.url });
      };
      window.addEventListener('message', onMessage);
      window.postMessage({ type: 'TEDARIKAPP_CONTEXT_REQUEST' }, '*');

      return true; // async yanıt
    });
  },
});
