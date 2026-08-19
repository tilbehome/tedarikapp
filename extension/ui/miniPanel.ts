/**
 * Sayfa içi mini panel (İE#13 A1): detail.1688.com sayfasının sağ altına sabit
 * "Tedarikapp'e Ekle" düğmesi + tıklanınca açılan yakalama paneli.
 *
 * İZOLASYON: tüm arayüz KAPALI shadow DOM içindedir — 1688'in CSS'i içeri, bizim
 * stilimiz dışarı sızmaz ve sayfa scriptleri panele DOM üzerinden erişemez.
 * Ağ işi yine background'dadır; token bu katmana hiç inmez (K34).
 *
 * Tarayıcı popup'ı aynen durur: aynı formu (ui/captureForm) iki yüzey paylaşır.
 */

import { mountCaptureForm } from './captureForm';
import { CAPTURE_CSS, INLINE_CSS } from './styles';
import type { PageData } from '../core/types';

const KAP_ID = 'tedarikapp-mini-panel';

export function mountMiniPanel(readPage: () => Promise<PageData>): void {
  if (document.getElementById(KAP_ID) !== null) return;

  const kap = document.createElement('div');
  kap.id = KAP_ID;
  const shadow = kap.attachShadow({ mode: 'closed' });

  const style = document.createElement('style');
  style.textContent = INLINE_CSS + CAPTURE_CSS;

  const tetik = document.createElement('button');
  tetik.type = 'button';
  tetik.className = 'tdk-tetik';
  tetik.textContent = "＋ Tedarikapp'e Ekle";

  const panel = document.createElement('div');
  panel.className = 'tdk-panel';
  panel.hidden = true;

  const baslik = document.createElement('div');
  baslik.className = 'tdk-baslik';
  const marka = document.createElement('strong');
  marka.textContent = 'Tedarikapp';
  const durum = document.createElement('span');
  durum.className = 'tdk-durum';
  const kapat = document.createElement('button');
  kapat.type = 'button';
  kapat.className = 'tdk-kapat';
  kapat.title = 'Kapat';
  kapat.textContent = '×';
  const baslikSol = document.createElement('div');
  baslikSol.append(marka, document.createTextNode(' '), durum);
  baslik.append(baslikSol, kapat);

  const uyari = document.createElement('p');
  uyari.className = 'tdk-uyari';
  uyari.hidden = true;

  const form = mountCaptureForm({
    send: (message) =>
      new Promise((resolve, reject) => {
        chrome.runtime.sendMessage(message, (yanit: { ok: boolean; data?: unknown; error?: string }) => {
          if (chrome.runtime.lastError) return reject(new Error(chrome.runtime.lastError.message));
          if (!yanit?.ok) return reject(new Error(yanit?.error ?? 'Bilinmeyen hata'));
          resolve(yanit.data as never);
        });
      }),
    readPage,
    onStatus: (metin, tur) => {
      durum.textContent = metin;
      durum.className = 'tdk-durum' + (tur === 'bilgi' ? '' : ' ' + tur);
    },
    // Mini panelde token girilmez (sayfa yüzeyine sır alanı konmaz — K34):
    // kullanıcı tarayıcı çubuğundaki eklenti simgesine yönlendirilir.
    onNeedSettings: (sebep) => {
      uyari.textContent = `${sebep} Bağlantıyı tarayıcı çubuğundaki Tedarikapp simgesinden kurun.`;
      uyari.hidden = false;
      form.element.hidden = true;
    },
    onPageUnavailable: (sebep) => {
      uyari.textContent = sebep;
      uyari.hidden = false;
      form.element.hidden = true;
    },
  });

  panel.append(baslik, uyari, form.element);
  shadow.append(style, tetik, panel);
  document.body.append(kap);

  let baslatildi = false;
  tetik.addEventListener('click', () => {
    panel.hidden = false;
    tetik.hidden = true;
    if (!baslatildi) {
      baslatildi = true;
      void form.baslat();
    }
  });
  kapat.addEventListener('click', () => {
    panel.hidden = true;
    tetik.hidden = false;
  });
}
