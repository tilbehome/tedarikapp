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
import { Kuyruk, MAKS_DENEME, chromeDeposu } from '../core/kuyruk';
import { GonderimIzi, toparlanacakKayitlar } from '../core/toparlama';
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

const kuyruk = new Kuyruk(chromeDeposu());
const gonderimIzi = new GonderimIzi(chromeDeposu());

/**
 * YAKALAMAYI KUYRUKLA GÖNDER (İE#21 A4).
 *
 * Sıra: önce KUYRUĞA YAZ, sonra gönder. Ters sırada yazsaydık, gönderim
 * sırasında worker uyutulduğunda yakalama hiçbir yerde durmuyor olurdu.
 */
async function kuyrukluGonder(yuk: CapturePayload): Promise<unknown> {
  const simdi = Date.now();
  await kuyruk.ekle(yuk, new Date(simdi).toISOString());
  await gonderimIzi.isaretle(yuk.capture_id, simdi);

  try {
    const yanit = await panelApi.capture(yuk);
    await kuyruk.dusur(yuk.capture_id);
    await gonderimIzi.temizle(yuk.capture_id);

    return yanit;
  } catch (hata) {
    const mesaj = hata instanceof Error ? hata.message : String(hata);
    await kuyruk.basarisiz(yuk.capture_id, mesaj);
    await gonderimIzi.temizle(yuk.capture_id);

    throw hata;
  }
}

/**
 * MV3 UYANIŞ TOPARLAMASI (İE#21 sertleştirme).
 *
 * Sahipsiz "gönderiliyor" damgaları temizlenir ve hakkı kalan kayıtlar yeniden
 * denenir. Hakkı biten kayıtlar DOKUNULMADAN kalır: arayüz onları rozetle
 * gösterir, kullanıcı komutu bekler (dead-letter deseni · B11 ikizi).
 */
export async function uyanistaToparla(): Promise<number> {
  const simdi = Date.now();
  await gonderimIzi.sahipsizleriKurtar(simdi);

  const bekleyenler = toparlanacakKayitlar(await kuyruk.liste(), await gonderimIzi.damgalar(), simdi, MAKS_DENEME);
  let gonderilen = 0;

  for (const kayit of bekleyenler) {
    try {
      await kuyrukluGonder(kayit.yuk);
      gonderilen += 1;
    } catch {
      // Hata zaten kuyruğa işlendi; sıradaki kayda geçilir.
    }
  }

  return gonderilen;
}

/** Duran (hakkı bitmiş) kayıtlar — arayüzdeki rozet bunları gösterir. */
async function duranKayitlar(): Promise<{ captureId: string; ad: string; sonHata: string | null }[]> {
  return (await kuyruk.liste())
    .filter((kayit) => kayit.deneme >= MAKS_DENEME)
    .map((kayit) => ({
      captureId: kayit.captureId,
      ad: kayit.yuk.normalized?.name ?? kayit.captureId,
      sonHata: kayit.sonHata,
    }));
}

export default defineBackground(() => {
  chrome.runtime.onInstalled.addListener(() => {
    void acikSekmelereEnjekteEt();
    void uyanistaToparla();
  });

  // Uyanışta toparlama: MV3'te worker her an uyutulur.
  chrome.runtime.onStartup?.addListener(() => {
    void uyanistaToparla();
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
        return respond(kuyrukluGonder(message.payload as CapturePayload));
      case 'KUYRUK_DURANLAR':
        return respond(duranKayitlar());
      case 'KUYRUK_EYLEM': {
        const { captureId, eylem } = (message.payload ?? {}) as { captureId?: string; eylem?: string };
        if (typeof captureId !== 'string') return respond(Promise.resolve({ ok: false }));
        if (eylem === 'VAZGEC') return respond(kuyruk.dusur(captureId).then(() => ({ ok: true })));
        // YENIDEN ve DUZELT: hak sıfırlanır. DUZELT'te kullanıcı panelde düzeltir;
        // ikisinde de kayıt otomatik toparlamaya yeniden UYGUN hâle gelir.
        return respond(kuyruk.denemeleriSifirla(captureId).then(() => uyanistaToparla()));
      }
      default:
        return false;
    }
  });
});
