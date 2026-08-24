/**
 * ÇEKMECE KABUĞU (İE#21 A1) — panel gövdesini sayfaya bağlayan katman.
 *
 * Montaj (`montaj.ts`) düğmeyi basar, akış (`akis.ts`) durumu tutar, gövde
 * (`panel.ts`) içeriği çizer; bu dosya üçünü bir araya getirir: çekmeceyi açar,
 * kapatır, odak turunu ve Esc'i yönetir.
 *
 * KAPALI SHADOW: çekmece de düğmeyle aynı shadow ağacındadır — 1688'in stili
 * içeri, bizimki dışarı sızmaz (EKL-26).
 */

import { panelGovdesi, type PanelEylemleri, type PanelGorunumu } from './panel';
import { V2_CSS } from './stil';

export interface Cekmece {
  ac(): void;
  kapat(): void;
  ciz(gorunum: PanelGorunumu): void;
  acikMi(): boolean;
  kok(): ShadowRoot;
}

export interface CekmeceSecenekleri {
  belge?: Document;
  eylemler: PanelEylemleri;
  /** Kapanınca odağın döneceği düğme. */
  tetikleyici?: HTMLElement | null;
}

const CEKMECE_ID = 'tedarikapp-v2-cekmece';

export function cekmeceKur(secenekler: CekmeceSecenekleri): Cekmece {
  const belge = secenekler.belge ?? document;
  const mevcut = belge.getElementById(CEKMECE_ID);
  mevcut?.remove();

  const kap = belge.createElement('div');
  kap.id = CEKMECE_ID;
  const shadow = kap.attachShadow({ mode: 'closed' });

  const stil = belge.createElement('style');
  stil.textContent = V2_CSS;

  const ortu = belge.createElement('div');
  ortu.className = 'tdk-ortu';
  ortu.hidden = true;

  const panel = belge.createElement('aside');
  panel.className = 'tdk-cekmece';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  panel.setAttribute('aria-label', 'TedarikApp yakalama önizlemesi');
  panel.hidden = true;

  const ust = belge.createElement('div');
  ust.className = 'tdk-ust';
  const kup = belge.createElement('span');
  kup.className = 'kup';
  kup.setAttribute('aria-hidden', 'true');
  const marka = belge.createElement('div');
  marka.className = 'tdk-marka';
  marka.textContent = 'tedarikapp';
  const altMarka = belge.createElement('small');
  altMarka.textContent = 'ÜRÜN TEDARİK ASİSTANI';
  marka.append(altMarka);
  const kapatDugmesi = belge.createElement('button');
  kapatDugmesi.type = 'button';
  kapatDugmesi.className = 'tdk-kapat';
  kapatDugmesi.setAttribute('aria-label', 'Kapat');
  kapatDugmesi.textContent = '×';
  ust.append(kup, marka, kapatDugmesi);

  const govdeKabi = belge.createElement('div');
  govdeKabi.setAttribute('data-govde', '');

  const alt = belge.createElement('div');
  alt.className = 'tdk-alt';
  const gonder = belge.createElement('button');
  gonder.type = 'button';
  gonder.className = 'tdk-gonder';
  gonder.setAttribute('data-eylem', 'gonder');
  gonder.textContent = 'Yakala ve Gönder';
  const ipucu = belge.createElement('div');
  ipucu.className = 'tdk-ipucu';
  ipucu.textContent = 'Bağlantı yoksa yakalama cihaz kuyruğunda bekler, bağlanınca gönderilir.';
  alt.append(gonder, ipucu);

  panel.append(ust, govdeKabi, alt);
  shadow.append(stil, ortu, panel);
  belge.body.append(kap);

  let acik = false;

  const kapat = (): void => {
    acik = false;
    ortu.hidden = true;
    panel.hidden = true;
    secenekler.eylemler.onKapat();
    // Odak turu (EKL-25): kapanınca tetikleyen düğmeye dönülür.
    secenekler.tetikleyici?.focus();
  };

  kapatDugmesi.addEventListener('click', kapat);
  ortu.addEventListener('click', kapat);
  gonder.addEventListener('click', () => secenekler.eylemler.onGonder());
  panel.addEventListener('keydown', (olay) => {
    if ((olay as KeyboardEvent).key === 'Escape') {
      olay.preventDefault();
      kapat();
    }
  });

  return {
    ac() {
      acik = true;
      ortu.hidden = false;
      panel.hidden = false;
      kapatDugmesi.focus();
    },
    kapat,
    acikMi: () => acik,
    kok: () => shadow,
    ciz(gorunum: PanelGorunumu) {
      govdeKabi.replaceChildren(panelGovdesi(gorunum, secenekler.eylemler));
      // Gönder düğmesi yalnız gönderilebilir durumlarda etkindir; kilidi
      // durum makinesi belirler, arayüz kendi kuralını uydurmaz.
      const gonderilebilirDurumlar = ['D3_ONIZLEME', 'D4_KISMI', 'D8_MUKERRER', 'D10_SUNUCU_HATASI'];
      gonder.disabled = !gonderilebilirDurumlar.includes(gorunum.makine.durum) || gorunum.disclosureGerekli;
    },
  };
}
