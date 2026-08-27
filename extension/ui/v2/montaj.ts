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

/**
 * SATIN ALMA BLOĞU — DÜĞMENİN GERÇEK YERİ (v1.0/A5, Ürün Sahibi kararı 27 Ağu).
 *
 * Düğme sağ ürün sütununda, satın alma bloğunun HEMEN ÜSTÜNDE durur: 1688'de
 * 立即订购 / 加入进货单, AliTrading TR görünümünde "Sepete ekle" / "Giriş
 * yaparak…" alanının üstü. Mağaza adı satırına DOKUNULMAZ — sahada düğme
 * oraya biniyor ve "Takip ediliyor"u örtüyordu.
 *
 * NEDEN ÖNCE CSS, SONRA METİN: sınıf adları 1688'in dağıtımına göre değişiyor
 * ve iki dil skini farklı ağaçlar üretiyor. Sınıf bulunamazsa blok, satın alma
 * DÜĞMESİNİN METNİNDEN bulunur; metin iki skinde de sabittir (A8 kök nedeni:
 * yalnız sınıfa bakan eski liste ZH görünümünde hiçbir şey bulamıyordu).
 */
export const SATINALMA_HEDEFLERI = [
  // GERÇEK DOM'DAN (27 Ağu dökümleri, e2e/fixtures/1688): sipariş modülü iki
  // skinde de AYNI düğümdür ve sayfada TEK kez geçer. Kullanıcı oturumsuzken
  // içi "登录查看全部规格 / Giriş yaparak hepsini görün" olur, oturumluyken
  // 立即订购 / 加入进货单 düğmeleri gelir — kap değişmez.
  '[data-spm="submitOrder"]',
  '.module-od-submit-order',
  // Eski dağıtımlar / olası varyantlar.
  '.od-pc-offer-trade',
  '.obj-action',
  '.order-container',
  '.detail-action',
  '[class*="offer-trade"]',
  '[class*="action-container"]',
];

/** Satın alma düğmelerinin iki skindeki metinleri (küçük harfe indirgenmiş). */
export const SATINALMA_METINLERI = [
  // Oturumlu görünüm.
  '立即订购',
  '加入进货单',
  '立即购买',
  'sepete ekle',
  'hemen sipariş',
  // OTURUMSUZ görünüm: sipariş düğmelerinin yerini giriş çağrısı alır. Ürün
  // Sahibi'nin dökümleri bu hâldeydi; yalnız sipariş metinlerine bakan bir
  // liste burada hiçbir şey bulamazdı.
  '登录查看全部规格',
  '一键登录',
  'giriş yaparak',
];

/** Son çare: eski davranış — fiyat/başlık bloklarının ardına eklenir. */
export const SATIRICI_HEDEFLER = [
  '.od-pc-offer-price',
  '.price-content',
  '.detail-info',
  '.mod-detail-title',
  'h1',
];

/** Ölçülebilir bir kutusu var mı? Gizli düğüm hedef olamaz. */
function gorunurMu(dugum: Element): boolean {
  const kutu = dugum.getBoundingClientRect?.();
  if (kutu === undefined) return true; // ölçüm yoksa (jsdom) eleme yapma

  return kutu.width > 0 || kutu.height > 0;
}

/**
 * Satın alma düğmesinden bloğa çıkar.
 *
 * Düğmenin kendisinin üstüne girmek, 1688'in kendi düğme satırını ikiye böler.
 * Bir üst kapsayıcıya çıkılır; ama kök kapsayıcıya kadar tırmanmak da düğmeyi
 * tüm sütunun tepesine atardı. Bu yüzden EN FAZLA üç kademe çıkılır ve adı
 * eylem/sipariş çağrıştıran ilk kapsayıcıda durulur.
 */
function blogaCik(dugme: Element): Element {
  let dugum: Element = dugme;
  for (let kademe = 0; kademe < 3; kademe++) {
    const ust = dugum.parentElement;
    if (ust === null || ust.tagName === 'BODY') break;
    dugum = ust;
    if (/action|trade|order|buy|btn|cart|sepet/i.test(ust.className || '')) break;
  }

  return dugum;
}

/**
 * Sayfadaki satın alma bloğunu bulur; bulunamazsa null.
 *
 * @param belge aranacak belge
 */
export function satinAlmaBlogu(belge: Document): Element | null {
  for (const secici of SATINALMA_HEDEFLERI) {
    const dugum = belge.querySelector(secici);
    if (dugum !== null && gorunurMu(dugum)) return dugum;
  }

  const adaylar = Array.from(belge.querySelectorAll('button, a, [role="button"], input[type="submit"]'));
  for (const aday of adaylar) {
    const metin = (
      (aday as HTMLInputElement).value ??
      aday.textContent ??
      ''
    )
      .trim()
      .toLowerCase();
    if (metin === '') continue;
    if (!SATINALMA_METINLERI.some((kalip) => metin.includes(kalip))) continue;
    if (!gorunurMu(aday)) continue;

    return blogaCik(aday);
  }

  return null;
}

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

  // A5: ÖNCE satın alma bloğu (düğme onun ÜSTÜNE girer), sonra eski liste
  // (fiyat/başlık bloklarının ARDINA). İkisi de yoksa PILL yedeği.
  const satinAlma = satinAlmaBlogu(belge);
  const eskiHedef =
    satinAlma !== null
      ? null
      : (SATIRICI_HEDEFLER.map((secici) => belge.querySelector(secici)).find((dugum) => dugum !== null) ?? null);
  const hedef = satinAlma ?? eskiHedef;
  const tur: MontajTuru = hedef === null ? 'PILL' : 'SATIRICI';

  const kap = belge.createElement('div');
  kap.id = KAP_ID;
  kap.setAttribute('data-tur', tur);
  // Kap sayfanın kendi düzenine girer: blok yerleşimde tam genişlik ister,
  // yoksa 1688'in flex satırında sıfır genişlikte kalabilir.
  kap.style.setProperty('width', '100%');
  kap.style.setProperty('margin', '0 0 8px');

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
    // Satın alma bloğunun ÜSTÜNE, eski hedeflerin ise ARDINA girilir: biri
    // "şuradan sipariş verilir"in girişi, diğeri başlık/fiyat bilgisinin sonu.
    hedef.insertAdjacentElement(satinAlma !== null ? 'beforebegin' : 'afterend', kap);
    if (satinAlma !== null) {
      // Tam genişlik: 1688'in kendi düğmeleriyle aynı hizada durur.
      kap.setAttribute('data-yerlesim', 'blok');
      dugme.classList.add('tdk-btn--blok');
    }
  } else {
    belge.body.append(kap);
  }

  return { tur, kap, shadow, dugme };
}

/**
 * MONTAJ NÖBETİ (v1.0/A8, saha 27 Ağu) — DÜĞME KAYBOLURSA GERİ BASILIR.
 *
 * SAHA: Çince görünümde ne satır içi düğme ne de pill yedeği görünüyordu.
 * İki neden birlikte çalışıyordu:
 *   1. Montaj TEK SEFER, content script koştuğu anda deneniyordu. 1688'in ürün
 *      sütunu istemcide çiziliyor; o an ne satın alma bloğu ne fiyat bloğu
 *      vardı, montaj PILL'e düşüyordu.
 *   2. Sayfa kendi yeniden çiziminde (dil geçişi dâhil, ADRES DEĞİŞMEDEN) bizim
 *      düğümümüzü de söküyordu. Adres değişmediği için 1 sn'lik offer sayacı
 *      hiç tetiklenmiyor, düğme bir daha geri gelmiyordu.
 *
 * NÖBET: DOM değişimlerini izler ve iki durumda yeniden monte eder —
 *   · kabımız sayfadan silinmişse,
 *   · PILL'deyken satır içi hedef sonradan belirmişse (daha iyisi mümkün oldu).
 * Değişiklikler yığınla geldiği için kontrol bir sonraki çerçeveye ertelenir;
 * her mutation'da yeniden montaj sayfayı kilitlerdi.
 */
export function montajNobeti(
  yenidenMonteEt: () => MontajTuru,
  belge: Document = document,
): () => void {
  if (typeof MutationObserver === 'undefined' || belge.body === null) {
    return () => {};
  }

  let sonTur: MontajTuru = (belge.getElementById(KAP_ID)?.getAttribute('data-tur') as MontajTuru | null) ?? 'YOK';
  let bekleyen = false;

  const denetle = (): void => {
    bekleyen = false;
    const kap = belge.getElementById(KAP_ID);
    const kayipMi = kap === null;
    const dahaIyisiVar = sonTur === 'PILL' && satinAlmaBlogu(belge) !== null;
    if (!kayipMi && !dahaIyisiVar) return;

    sonTur = yenidenMonteEt();
  };

  const gozlemci = new MutationObserver(() => {
    if (bekleyen) return;
    bekleyen = true;
    // Kendi montajımız da bir mutation üretir; aynı turda tekrar tetiklenmemek
    // için kontrol bir sonraki çerçeveye bırakılır.
    (typeof requestAnimationFrame === 'function'
      ? requestAnimationFrame
      : (geri: () => void) => setTimeout(geri, 16))(denetle);
  });

  gozlemci.observe(belge.body, { childList: true, subtree: true });

  return () => gozlemci.disconnect();
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
