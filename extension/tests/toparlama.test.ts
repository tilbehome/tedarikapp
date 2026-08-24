import { beforeEach, describe, expect, it } from 'vitest';

import { GonderimIzi, SAHIPSIZ_MS, toparlanacakKayitlar, type Depo } from '../core/toparlama';
import { MAKS_DENEME, type KuyrukKaydi } from '../core/kuyruk';
import type { CapturePayload } from '../core/types';

/**
 * MV3 BAŞLANGIÇ TOPARLAMASI (İE#21 A4/A5 sertleştirme).
 *
 * Söz: service worker gönderim ortasında uyutulursa kayıt "gönderiliyor"da
 * ASILI KALMAZ; uyanışta sahipsiz damga temizlenir ve kayıt yeniden gönderilir.
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

function kayit(captureId: string, deneme = 0): KuyrukKaydi {
  return {
    captureId,
    deneme,
    sonHata: null,
    eklendi: '2026-08-24T10:00:00+03:00',
    yuk: { capture_id: captureId } as unknown as CapturePayload,
  };
}

let depo: ReturnType<typeof sahteDepo>;
let iz: GonderimIzi;

beforeEach(() => {
  depo = sahteDepo();
  iz = new GonderimIzi(depo);
});

describe('Gönderim damgası', () => {
  it('işaretlenir ve temizlenir', async () => {
    await iz.isaretle('cap-1', 1000);
    expect(await iz.damgalar()).toHaveLength(1);

    await iz.temizle('cap-1');
    expect(await iz.damgalar()).toEqual([]);
  });

  it('aynı kayıt iki kez işaretlenirse tek damga kalır', async () => {
    await iz.isaretle('cap-1', 1000);
    await iz.isaretle('cap-1', 2000);

    const damgalar = await iz.damgalar();
    expect(damgalar).toHaveLength(1);
    expect(damgalar[0]?.baslangic).toBe(2000);
  });
});

describe('Uyanışta sahipsiz kurtarma', () => {
  it('süresi geçmiş damga silinir ve kimliği döner', async () => {
    await iz.isaretle('cap-eski', 0);
    await iz.isaretle('cap-taze', SAHIPSIZ_MS);

    const kurtarilan = await iz.sahipsizleriKurtar(SAHIPSIZ_MS + 1);

    expect(kurtarilan).toEqual(['cap-eski']);
    expect((await iz.damgalar()).map((d) => d.captureId)).toEqual(['cap-taze']);
  });

  it('hepsi tazeyse hiçbir şey silinmez', async () => {
    await iz.isaretle('cap-1', 1000);

    expect(await iz.sahipsizleriKurtar(1500)).toEqual([]);
    expect(await iz.damgalar()).toHaveLength(1);
  });
});

describe('Toparlanacak kayıt seçimi', () => {
  it('taze damgalı kayıt ATLANIR — aynı yakalama iki kez gönderilmez', () => {
    const secilen = toparlanacakKayitlar(
      [kayit('cap-1'), kayit('cap-2')],
      [{ captureId: 'cap-1', baslangic: 1000 }],
      1500,
      MAKS_DENEME,
    );

    expect(secilen.map((k) => k.captureId)).toEqual(['cap-2']);
  });

  it('sahipsiz damgalı kayıt yeniden gönderilir', () => {
    const secilen = toparlanacakKayitlar(
      [kayit('cap-1')],
      [{ captureId: 'cap-1', baslangic: 0 }],
      SAHIPSIZ_MS + 1,
      MAKS_DENEME,
    );

    expect(secilen.map((k) => k.captureId)).toEqual(['cap-1']);
  });

  it('deneme hakkı biten kayıt otomatik toparlanmaz', () => {
    const secilen = toparlanacakKayitlar([kayit('cap-1', MAKS_DENEME)], [], 5000, MAKS_DENEME);

    expect(secilen).toEqual([]);
  });
});
