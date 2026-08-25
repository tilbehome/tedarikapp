/**
 * SAYFA İÇİ MONTAJ (İE#21 A1 · EKL-01/02/23/25/26).
 *
 * Üç iş yapar ve üçü de KULLANICI TETİKLİDİR:
 *  1. Sayfa desteklenen bir ürün detayı mı? Değilse HİÇBİR ŞEY basılmaz — düğme
 *     de, panel de, parser da yok (EKL-01).
 *  2. Düğmeyi ürün bilgisinin yanına (satır içi) monte eder; uygun yer yoksa
 *     sağ alt köşeye PILL olarak düşer. Sayfa yapısı değişince kullanıcı
 *     düğmesiz kalmasın diye iki montaj yolu vardır.
 *  3. Arayüzü KAPALI shadow DOM içinde tutar: 1688'in CSS'i içeri, bizim
 *     stilimiz dışarı sızmaz; sayfa scriptleri panele DOM'dan erişemez (EKL-26).
 *
 * TEMBEL BAŞLANGIÇ (EKL-02): montaj yalnız düğme kabuğunu kurar. Ayrıştırma ve
 * ağ, kullanıcı düğmeye basana kadar ÇALIŞMAZ.
 */

import { V2_CSS } from './stil';

/** Ürün detay adresi mi? (offer/{id}.html) */
export const URUN_ADRESI = /^https:\/\/detail\.1688\.com\/offer\/(\d+)\.html/;

export function urunSayfasiMi(adres: string): boolean {
  return URUN_ADRESI.test(adres);
}

export function offerId(adres: string): string | null {
  return URUN_ADRESI.exec(adres)?.[1] ?? null;
}

/** Satır içi montaj için aranan yerler — sırayla denenir. */
export const SATIRICI_HEDEFLER = [
  '.od-pc-offer-price',
  '.price-content',
  '.detail-info',
  '.mod-detail-title',
  'h1',
];

export type MontajTuru = 'SATIRICI' | 'PILL' | 'YOK';

export interface MontajSonucu {
  tur: MontajTuru;
  kap: HTMLElement | null;
  shadow: ShadowRoot | null;
  dugme: HTMLButtonElement | null;
}

export const KAP_ID = 'tedarikapp-v2-kap';

function dugmeKur(belge: Document, tur: MontajTuru, etiket: string, rozet: string | null): HTMLButtonElement {
  const dugme = belge.createElement('button');
  dugme.type = 'button';
  dugme.className = tur === 'SATIRICI' ? 'tdk-btn' : 'tdk-pill';
  dugme.setAttribute('data-tdk-dugme', tur);
  dugme.setAttribute('aria-haspopup', 'dialog');

  const kup = belge.createElement('span');
  kup.className = 'kup';
  kup.setAttribute('aria-hidden', 'true');

  const metin = belge.createElement('span');
  metin.className = 'etiket';
  metin.textContent = etiket;

  dugme.append(kup, metin);

  if (rozet !== null) {
    const rozetDugumu = belge.createElement('span');
    rozetDugumu.className = tur === 'SATIRICI' ? 'rozet' : 'sayac';
    rozetDugumu.textContent = rozet;
    dugme.append(rozetDugumu);
  }

  return dugme;
}

export interface MontajSecenekleri {
  belge?: Document;
  adres?: string;
  etiket?: string;
  /** Düğme üstündeki küçük bilgi: "panelde yok" / bekleyen kuyruk sayısı. */
  rozet?: string | null;
  onTikla?: () => void;
}

/**
 * Düğmeyi sayfaya monte eder.
 *
 * İKİNCİ ÇAĞRI ETKİSİZDİR: SPA yönlendirmelerinde bu fonksiyon birden çok kez
 * çalışabilir; her seferinde yeni düğme basmak sayfayı düğme çöplüğüne çevirirdi.
 */
export function montajYap(secenekler: MontajSecenekleri = {}): MontajSonucu {
  const belge = secenekler.belge ?? document;
  const adres = secenekler.adres ?? belge.location?.href ?? '';

  if (!urunSayfasiMi(adres)) {
    return { tur: 'YOK', kap: null, shadow: null, dugme: null };
  }

  // D10-NİHAİ: AYNI KAP VARSA ESKİSİ SÖKÜLÜR.
  //
  // Eskiden mevcut kap bulununca ERKEN DÖNÜLÜYORDU ve yeni `onTikla` bağlanmadan
  // kalıyordu: SPA yönlendirmesinden sonra düğme duruyor ama TIKLAMA HİÇBİR ŞEY
  // YAPMIYORDU. Sahada "aynı sayfada üç yenilemede üç farklı hâl" şikâyetinin
  // kaynağı buydu — düğmenin çalışıp çalışmaması montaj sırasına bağlıydı.
  //
  // Sökülüp yeniden basmak ucuzdur (tek düğüm) ve sonucu BELİRLENİMCİ kılar:
  // ekranda her zaman TEK düğme vardır ve o düğme her zaman bağlıdır.
  belge.getElementById(KAP_ID)?.remove();

  const hedef = SATIRICI_HEDEFLER.map((secici) => belge.querySelector(secici)).find((dugum) => dugum !== null) ?? null;
  const tur: MontajTuru = hedef === null ? 'PILL' : 'SATIRICI';

  const kap = belge.createElement('div');
  kap.id = KAP_ID;
  kap.setAttribute('data-tur', tur);

  // KAPALI shadow: sayfa scriptleri `element.shadowRoot` ile içeri bakamaz.
  const shadow = kap.attachShadow({ mode: 'closed' });
  const stil = belge.createElement('style');
  stil.textContent = V2_CSS;

  const dugme = dugmeKur(belge, tur, secenekler.etiket ?? "TedarikApp'e Ekle", secenekler.rozet ?? null);
  if (secenekler.onTikla !== undefined) {
    dugme.addEventListener('click', secenekler.onTikla);
  }

  shadow.append(stil, dugme);

  if (tur === 'SATIRICI' && hedef !== null) {
    hedef.insertAdjacentElement('afterend', kap);
  } else {
    belge.body.append(kap);
  }

  return { tur, kap, shadow, dugme };
}

/** SPA yönlendirmesinde temizlik (EKL-23): kap kaldırılır, sonraki montaj tazedir. */
export function montajiKaldir(belge: Document = document): void {
  belge.getElementById(KAP_ID)?.remove();
}

/**
 * Esc ve odak turu (EKL-25).
 *
 * Panel açıkken Esc kapatır; kapanınca odak TETİKLEYEN düğmeye döner — kullanıcı
 * klavyeyle geldiyse klavyeyle devam edebilmeli.
 */
export function escKapatmaBagla(
  hedef: { addEventListener(tur: 'keydown', dinleyici: (olay: KeyboardEvent) => void): void; removeEventListener(tur: 'keydown', dinleyici: (olay: KeyboardEvent) => void): void },
  onKapat: () => void,
): () => void {
  const dinleyici = (olay: KeyboardEvent): void => {
    if (olay.key === 'Escape') {
      olay.preventDefault();
      onKapat();
    }
  };

  hedef.addEventListener('keydown', dinleyici);

  return () => hedef.removeEventListener('keydown', dinleyici);
}
