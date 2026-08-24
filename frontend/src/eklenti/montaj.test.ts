import { beforeEach, describe, expect, test, vi } from 'vitest';

import {
  KAP_ID,
  escKapatmaBagla,
  montajYap,
  montajiKaldir,
  offerId,
  urunSayfasiMi,
} from '../../../extension/ui/v2/montaj';

/**
 * EKLENTİ v2 — SAYFA İÇİ MONTAJ (İE#21 A1).
 *
 * NEDEN BU TESTLER PANEL ÇALIŞMA ALANINDA: eklenti paketinin ÇALIŞMA ZAMANI
 * bağımlılığı sıfırdır (CLAUDE.md §2) ve geliştirme zincirine yalnız WXT/TS/Vitest
 * girer — jsdom orada YOKTUR. Panel çalışma alanında jsdom zaten kuruludur; DOM
 * testlerini oraya koymak, eklentiye yeni bir paket eklemeden gerçek DOM'da
 * doğrulama yapmayı sağlar (K19: liste dışı paket PM onayı ister).
 *
 * Kapsanan senaryolar: EKL-01 (ürün sayfası değilse düğme yok), EKL-02 (kapalı
 * durum, tembel başlangıç), EKL-23 (SPA değişiminde temizlik), EKL-25 (Esc),
 * EKL-26 (Shadow DOM izolasyonu ve yerleşim).
 */

const URUN_ADRESI = 'https://detail.1688.com/offer/895133432293.html';

beforeEach(() => {
  document.body.innerHTML = '';
});

describe('E2E-EKL-01 — desteklenmeyen sayfada arayüz YOK', () => {
  test('ana sayfa/kategori adresinde montaj yapılmaz', () => {
    const sonuc = montajYap({ adres: 'https://www.1688.com/', belge: document });

    expect(sonuc.tur).toBe('YOK');
    expect(document.getElementById(KAP_ID)).toBeNull();
  });

  test('offer yolu olmayan detay adresi de reddedilir', () => {
    expect(urunSayfasiMi('https://detail.1688.com/kampanya.html')).toBe(false);
    expect(urunSayfasiMi(URUN_ADRESI)).toBe(true);
    expect(offerId(URUN_ADRESI)).toBe('895133432293');
  });
});

describe('E2E-EKL-02/26 — kapalı durum, yerleşim ve izolasyon', () => {
  test('satır içi hedef varsa düğme ürün bilgisinin YANINA monte edilir', () => {
    document.body.innerHTML = '<div class="price-content">¥15.90</div>';

    const sonuc = montajYap({ adres: URUN_ADRESI, belge: document });

    expect(sonuc.tur).toBe('SATIRICI');
    expect(document.querySelector('.price-content')?.nextElementSibling?.id).toBe(KAP_ID);
  });

  test('uygun yer yoksa sağ alt PILL olarak düşer — kullanıcı düğmesiz kalmaz', () => {
    const sonuc = montajYap({ adres: URUN_ADRESI, belge: document });

    expect(sonuc.tur).toBe('PILL');
    expect(document.getElementById(KAP_ID)?.parentElement).toBe(document.body);
  });

  test('arayüz KAPALI shadow DOM içindedir — sayfa scriptleri göremez', () => {
    montajYap({ adres: URUN_ADRESI, belge: document });

    const kap = document.getElementById(KAP_ID);
    expect(kap).not.toBeNull();
    // Kapalı shadow: dışarıdan `shadowRoot` null görünür.
    expect(kap?.shadowRoot).toBeNull();
    // Düğme sayfanın light DOM'unda ARANMAZ:
    expect(document.querySelector('.tdk-btn')).toBeNull();
    expect(document.querySelector('.tdk-pill')).toBeNull();
  });

  test('tembel başlangıç: montaj yalnız düğme kurar, tıklama olmadan iş yapmaz', () => {
    const onTikla = vi.fn();
    const sonuc = montajYap({ adres: URUN_ADRESI, belge: document, onTikla });

    expect(sonuc.dugme?.getAttribute('data-tdk-dugme')).toBe('PILL');
    expect(onTikla).not.toHaveBeenCalled();

    sonuc.dugme?.click();
    expect(onTikla).toHaveBeenCalledTimes(1);
  });

  test('rozet metni düğmede görünür (panelde yok / bekleyen sayısı)', () => {
    const sonuc = montajYap({ adres: URUN_ADRESI, belge: document, rozet: '3' });

    expect(sonuc.dugme?.textContent).toContain('3');
  });
});

describe('İkinci montaj etkisizdir', () => {
  test('aynı sayfada iki kez çağrılırsa tek kap kalır', () => {
    montajYap({ adres: URUN_ADRESI, belge: document });
    montajYap({ adres: URUN_ADRESI, belge: document });

    expect(document.querySelectorAll(`#${KAP_ID}`)).toHaveLength(1);
  });
});

describe('E2E-EKL-23 — SPA offer değişiminde temizlik', () => {
  test('kaldırma sonrası montaj TAZE kurulur', () => {
    document.body.innerHTML = '<div class="price-content">¥1</div>';
    const ilk = montajYap({ adres: URUN_ADRESI, belge: document });
    expect(ilk.tur).toBe('SATIRICI');

    montajiKaldir(document);
    expect(document.getElementById(KAP_ID)).toBeNull();

    document.body.innerHTML = '';
    const ikinci = montajYap({ adres: 'https://detail.1688.com/offer/111.html', belge: document });
    expect(ikinci.tur).toBe('PILL');
  });
});

describe('E2E-EKL-25 — Esc kapatır', () => {
  test('Escape tuşu kapatma çağırır ve dinleyici sökülebilir', () => {
    const onKapat = vi.fn();
    const sok = escKapatmaBagla(window, onKapat);

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(onKapat).toHaveBeenCalledTimes(1);

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }));
    expect(onKapat).toHaveBeenCalledTimes(1);

    sok();
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(onKapat).toHaveBeenCalledTimes(1);
  });
});
