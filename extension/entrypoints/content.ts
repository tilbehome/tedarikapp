/**
 * Content script — detail.1688.com (İE#11 C2/C3).
 *
 * MAIN world: `window.context`e doğrudan erişim (rapor A.0 — en temiz yol).
 * İki iş: (1) popup istediğinde sayfa verisini toplayıp döndürmek, (2) sayfaya
 * "TedarikApp'e Gönder" düğmesi basmak (düğme popup'ı açamaz — MV3 kısıtı — bunun
 * yerine yakalama isteğini rozetle işaretler; kullanıcı eklenti simgesine tıklar).
 */

import { defineContentScript } from 'wxt/utils/define-content-script';

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
            // İE#14 A4: kırıntı yolu (面包屑) — kategori bilgisinin en güvenilir kaynağı.
            // JSON'da bulunamazsa bu liste kullanılır; kök adımlar backend'de elenir.
            breadcrumb: Array.from(
              document.querySelectorAll('.breadcrumb a, .detail-path a, [class*="breadcrumb"] a, .crumbs a'),
            )
              .map((node) => node.textContent?.trim() ?? '')
              .filter((text) => text !== '')
              .slice(0, 8),
            // İE#15 E2: sayfada gerçekten oynatılabilir bir <video> varsa adresi alınır.
            // 1688'in ana videosu imzalı MTOP isteği ister; DOM'da bulunursa bedava kazanç,
            // bulunamazsa raw.video (id+poster) yine "video var" bilgisini taşır.
            videoSrc:
              document.querySelector<HTMLVideoElement>('video[src]')?.src ??
              document.querySelector<HTMLSourceElement>('video source[src]')?.src ??
              null,
          },
          url: location.href,
        },
        '*',
      );
    });
  },
});
