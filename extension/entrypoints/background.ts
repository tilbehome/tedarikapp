/**
 * Background service worker (MV3): popup ↔ panel API köprüsü.
 * Ağ istekleri BURADAN yapılır — token content script'e/sayfaya asla inmez (K34).
 */

import { defineBackground } from 'wxt/sandbox';

import { panelApi } from '../core/api';
import type { CapturePayload } from '../core/types';

export default defineBackground(() => {
  chrome.runtime.onMessage.addListener((message: { type: string; payload?: unknown }, _sender, sendResponse) => {
    const respond = (promise: Promise<unknown>) => {
      promise
        .then((data) => sendResponse({ ok: true, data }))
        .catch((error: unknown) => sendResponse({ ok: false, error: error instanceof Error ? error.message : String(error) }));
      return true; // async yanıt
    };

    switch (message.type) {
      case 'SELECTORS':
        return respond(panelApi.selectors('1688'));
      case 'LISTS':
        return respond(panelApi.lists());
      case 'CAPTURE':
        return respond(panelApi.capture(message.payload as CapturePayload));
      default:
        return false;
    }
  });
});
