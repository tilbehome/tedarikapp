/**
 * Popup akışı (İE#11 C2, İE#13 A1'de sadeleşti): ayar ekranı + ORTAK yakalama formu.
 *
 * Formun kendisi `ui/captureForm`tedir ve sayfa içi mini panelle paylaşılır —
 * burada yalnız popup'a özgü olan kalır: ayar (panel adresi/token) ekranı, bölüm
 * geçişleri ve sayfa verisini AKTİF SEKMEDEN okuma.
 */

import { getSettings, saveSettings } from '../../core/api';
import { mountCaptureForm } from '../../ui/captureForm';
import { CAPTURE_CSS } from '../../ui/styles';
import type { PageData } from '../../core/types';

const $ = <T extends HTMLElement>(id: string): T => document.getElementById(id) as T;

const durum = $('durum');

function send<T>(message: { type: string; payload?: unknown }): Promise<T> {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(message, (response: { ok: boolean; data?: T; error?: string }) => {
      if (chrome.runtime.lastError) return reject(new Error(chrome.runtime.lastError.message));
      if (!response?.ok) return reject(new Error(response?.error ?? 'Bilinmeyen hata'));
      resolve(response.data as T);
    });
  });
}

function goster(bolum: 'ayarlar' | 'yakala' | 'desteklenmiyor'): void {
  for (const id of ['ayarlar', 'yakala', 'desteklenmiyor']) {
    $(id).hidden = id !== bolum;
  }
}

async function ayarlariYukle(): Promise<void> {
  const settings = await getSettings();
  ($('panel-url') as HTMLInputElement).value = settings.panelUrl;
  ($('token') as HTMLInputElement).value = settings.token;
}

/** Aktif sekmeden sayfa verisi: adres uygun değilse ya da content script yoksa ok:false. */
async function aktifSekmeyiOku(): Promise<PageData> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab?.id || !tab.url || !/^https:\/\/detail\.1688\.com\//.test(tab.url)) {
    return { ok: false, error: 'Bu sayfa desteklenmiyor. Eklenti detail.1688.com ürün sayfalarında çalışır.' };
  }

  const yanit = await new Promise<PageData | undefined>((resolve) =>
    chrome.tabs.sendMessage(tab.id as number, { type: 'PAGE_DATA' }, (cevap: PageData | undefined) => {
      // lastError okunmazsa konsola "Unchecked runtime.lastError" düşer.
      void chrome.runtime.lastError;
      resolve(cevap);
    }),
  );
  if (yanit?.ok !== true) {
    // İE#13 A2 ile kurulum anında enjeksiyon yapılıyor; yine de eski bir sekmede
    // (ör. eklenti devre dışı bırakılıp açılmışsa) yanıt gelmeyebilir.
    return { ok: false, error: 'Sayfa verisi okunamadı. Sayfayı yenileyin (F5) ve tekrar deneyin.' };
  }

  return yanit;
}

const form = mountCaptureForm({
  send,
  readPage: aktifSekmeyiOku,
  onStatus: (metin, tur) => {
    durum.textContent = metin;
    durum.className = 'durum' + (tur === 'bilgi' ? '' : ' ' + tur);
  },
  onNeedSettings: (sebep) => {
    const kutu = $('baglanti-hata');
    kutu.textContent = sebep;
    kutu.hidden = false;
    goster('ayarlar');
  },
  onPageUnavailable: (sebep) => {
    $('desteklenmiyor-metin').textContent = sebep;
    goster('desteklenmiyor');
  },
});

async function init(): Promise<void> {
  // Form stili mini panelle ORTAK kaynaktan gelir (iki yüzey ayrışmasın).
  const style = document.createElement('style');
  style.textContent = CAPTURE_CSS;
  document.head.append(style);

  $('yakala').append(form.element);
  await ayarlariYukle();

  $('kaydet').addEventListener('click', () => {
    void (async () => {
      await saveSettings({
        panelUrl: ($('panel-url') as HTMLInputElement).value.trim(),
        token: ($('token') as HTMLInputElement).value.trim(),
      });
      $('baglanti-hata').hidden = true;
      goster('yakala');
      await form.baslat();
    })();
  });
  for (const id of ['ayar-ac', 'ayar-ac-2']) {
    $(id).addEventListener('click', () => goster('ayarlar'));
  }

  goster('yakala');
  await form.baslat();
}

void init();
