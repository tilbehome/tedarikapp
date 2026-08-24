import { beforeEach, describe, expect, it } from 'vitest';

import {
  DISCLOSURE_ANAHTARI,
  DISCLOSURE_METNI,
  DISCLOSURE_SURUMU,
  Disclosure,
  onayGecerli,
  type Depo,
} from '../core/disclosure';

/**
 * PROMINENT DISCLOSURE (İE#21 A8 · E2E-EKL-24).
 *
 * Sözleşme: onay yoksa yakalama yok; metin ne toplandığını ve NE TOPLANMADIĞINI
 * söyler; metin değişirse onay yeniden istenir.
 */

function sahteDepo(): Depo & { veri: Record<string, unknown> } {
  const veri: Record<string, unknown> = {};

  return {
    veri,
    async get(anahtar: string) {
      return anahtar in veri ? { [anahtar]: veri[anahtar] } : {};
    },
    async set(deger: Record<string, unknown>) {
      Object.assign(veri, deger);
    },
  };
}

let depo: ReturnType<typeof sahteDepo>;
let disclosure: Disclosure;

beforeEach(() => {
  depo = sahteDepo();
  disclosure = new Disclosure(depo);
});

describe('İlk kullanım', () => {
  it('kayıt yokken onay YOKTUR', async () => {
    expect(await disclosure.onayliMi()).toBe(false);
  });

  it('onay kaydedilir ve kalıcıdır', async () => {
    await disclosure.onayla('2026-08-24T10:00:00+03:00');

    expect(await disclosure.onayliMi()).toBe(true);
    expect(depo.veri[DISCLOSURE_ANAHTARI]).toMatchObject({ surum: DISCLOSURE_SURUMU, onaylandi: true });
  });

  it('ret de kaydedilir — metin her açılışta dayatılmaz', async () => {
    await disclosure.reddet('2026-08-24T10:00:00+03:00');

    expect(await disclosure.onayliMi()).toBe(false);
    expect(depo.veri[DISCLOSURE_ANAHTARI]).toMatchObject({ onaylandi: false });
  });
});

describe('Sürümlü onay', () => {
  it('eski sürümlü onay GEÇERSİZDİR — kapsam değiştiyse yeniden sorulur', () => {
    expect(onayGecerli({ surum: DISCLOSURE_SURUMU - 1, onaylandi: true, tarih: 'x' })).toBe(false);
    expect(onayGecerli({ surum: DISCLOSURE_SURUMU, onaylandi: true, tarih: 'x' })).toBe(true);
  });

  it('bozuk kayıt onay sayılmaz', () => {
    expect(onayGecerli(null)).toBe(false);
    expect(onayGecerli('evet')).toBe(false);
    expect(onayGecerli({ onaylandi: true })).toBe(false);
  });
});

describe('Metin sözleşmesi', () => {
  it('ne toplandığı, nereye gittiği ve NE TOPLANMADIĞI yazar', () => {
    expect(DISCLOSURE_METNI.toplananlar.length).toBeGreaterThanOrEqual(3);
    expect(DISCLOSURE_METNI.toplanmayanlar.length).toBeGreaterThanOrEqual(3);
    expect(DISCLOSURE_METNI.gonderilenYer).toContain('TedarikApp');
  });

  it('çerez/oturum okunmadığı AÇIKÇA söylenir (EKL-27 ile aynı söz)', () => {
    const hepsi = DISCLOSURE_METNI.toplanmayanlar.join(' ');

    expect(hepsi).toContain('Çerezleriniz');
    expect(hepsi).toContain('üçüncü taraflara gönderilmez');
  });

  it('iki düğme vardır: onay ve ret', () => {
    expect(DISCLOSURE_METNI.onayDugmesi.trim()).not.toBe('');
    expect(DISCLOSURE_METNI.redDugmesi.trim()).not.toBe('');
  });
});
