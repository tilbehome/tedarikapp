/**
 * Content script — detail.1688.com (İE#11 C2/C3).
 *
 * MAIN world: `window.context`e doğrudan erişim (rapor A.0 — en temiz yol).
 * İki iş: (1) popup istediğinde sayfa verisini toplayıp döndürmek, (2) sayfaya
 * "Tedarikapp'e Gönder" düğmesi basmak (düğme popup'ı açamaz — MV3 kısıtı — bunun
 * yerine yakalama isteğini rozetle işaretler; kullanıcı eklenti simgesine tıklar).
 */

import { defineContentScript } from 'wxt/sandbox';

export default defineContentScript({
  matches: ['https://detail.1688.com/*'],
  world: 'MAIN',
  main() {
    // Popup, chrome.scripting yerine postMessage köprüsüyle veri ister (MAIN world'de
    // chrome.* yoktur). ISOLATED köprü scripti aşağıdaki mesajları taşır.
    window.addEventListener('message', (event: MessageEvent) => {
      if (event.source !== window || (event.data as { type?: string })?.type !== 'TEDARIKAPP_CONTEXT_REQUEST') {
        return;
      }
      const w = window as unknown as { context?: unknown };
      const og = (property: string): string | null =>
        document.querySelector<HTMLMetaElement>(`meta[property='${property}']`)?.content ?? null;

      window.postMessage(
        {
          type: 'TEDARIKAPP_CONTEXT_RESPONSE',
          context: w.context ?? null,
          dom: {
            ogTitle: og('og:title'),
            ogImage: og('og:image'),
            domTitle: document.querySelector('h1, .title-content .title-text')?.textContent?.trim() ?? null,
            domPrice: document.querySelector('.price-content .price-text, .price-column .price')?.textContent?.trim() ?? null,
          },
          url: location.href,
        },
        '*',
      );
    });
  },
});
