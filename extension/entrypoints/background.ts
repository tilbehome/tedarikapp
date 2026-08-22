/**
 * Background service worker (MV3): popup ↔ panel API köprüsü.
 * Ağ istekleri BURADAN yapılır — token content script'e/sayfaya asla inmez (K34).
 *
 * İE#13 A2: kurulum/güncelleme anında AÇIK 1688 sekmelerine content script'leri
 * elle enjekte eder. Chrome, kurulumdan önce açılmış sekmelere script basmaz;
 * bu yüzden kullanıcı "sayfayı yenileyin (F5)" duvarına çarpıyordu (canlı vaka).
 */

import { defineBackground } from 'wxt/utils/define-background';

import { panelApi } from '../core/api';
import type { CapturePayload } from '../core/types';

interface ManifestContentScript {
  matches?: string[];
  js?: string[];
  world?: 'MAIN' | 'ISOLATED';
  all_frames?: boolean;
}

/**
 * Manifest'teki content script tanımlarını açık sekmelere uygular.
 * Manifest'ten okur (dosya adlarını sabitlemez) — derleme çıktısı değişse de doğru kalır.
 * Zaten enjekte edilmiş sekmede ikinci kez çalışmak zararsızdır: köprü aynı dinleyiciyi
 * kurar, mini panel ise kabı id ile bulup çıkar (mountMiniPanel idempotenttir).
 */
export async function acikSekmelereEnjekteEt(): Promise<number> {
  const manifest = chrome.runtime.getManifest() as { content_scripts?: ManifestContentScript[] };
  const tanimlar = manifest.content_scripts ?? [];
  let sayac = 0;

  for (const tanim of tanimlar) {
    const files = tanim.js ?? [];
    const matches = tanim.matches ?? [];
    if (files.length === 0 || matches.length === 0) continue;

    const tabs = await chrome.tabs.query({ url: matches });
    for (const tab of tabs) {
      if (tab.id === undefined) continue;
      try {
        await chrome.scripting.executeScript({
          target: { tabId: tab.id, allFrames: tanim.all_frames === true },
          files,
          world: tanim.world ?? 'ISOLATED',
        });
        sayac += 1;
      } catch {
        // Sekme kapanmış / izin yok / chrome:// sayfası — sessizce atlanır, akış bloklanmaz.
      }
    }
  }

  return sayac;
}

export default defineBackground(() => {
  chrome.runtime.onInstalled.addListener(() => {
    void acikSekmelereEnjekteEt();
  });

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
      case 'TRANSLATE':
        return respond(panelApi.translate(String((message.payload as { text?: unknown })?.text ?? '')));
      case 'CAPTURE':
        return respond(panelApi.capture(message.payload as CapturePayload));
      default:
        return false;
    }
  });
});
