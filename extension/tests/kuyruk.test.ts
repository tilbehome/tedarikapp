import { beforeEach, describe, expect, it } from 'vitest';

import { Kuyruk, KUYRUK_ANAHTARI, MAKS_DENEME, type Depo } from '../core/kuyruk';
import type { CapturePayload } from '../core/types';

/**
 * KALICI KUYRUK (İE#21 A4) — EKL-14 (ağ kopması: veri korunur) ve MV3 uyanışında
 * toparlama sözünün birim karşılığı.
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

function yuk(captureId: string): CapturePayload {
  return {
    capture_id: captureId,
    schema_version: 2,
    extension_version: '2.0.0',
    parser_version: '1688-2026.08.2',
    target_list_id: null,
    qty: 1,
    units_per_carton: null,
    note: null,
    source: { platform: '1688', external_id: '1', url: 'https://detail.1688.com/offer/1.html', captured_at: '2026-08-24T10:00:00+03:00' },
    raw: { title: 'x', price_blocks: null, images: [] },
    normalized: { name: 'x', price_yuan: '1.00', price_tiers: [], images: [], sku_matrix: null, video_url: null },
  };
}

let depo: ReturnType<typeof sahteDepo>;
let kuyruk: Kuyruk;

beforeEach(() => {
  depo = sahteDepo();
  kuyruk = new Kuyruk(depo);
});

describe('Ekleme ve kalıcılık', () => {
  it('yakalama depoya YAZILIR — sekme kapansa da durur', async () => {
    await kuyruk.ekle(yuk('cap-1'), '2026-08-24T10:00:00+03:00');

    expect(depo.veri[KUYRUK_ANAHTARI]).toHaveLength(1);
    expect((await kuyruk.liste())[0]?.captureId).toBe('cap-1');
  });

  it('aynı capture_id ikinci kez eklenirse ÜSTÜNE yazar', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');
    await kuyruk.ekle(yuk('cap-1'), 'b');

    const liste = await kuyruk.liste();
    expect(liste).toHaveLength(1);
    expect(liste[0]?.eklendi).toBe('b');
  });

  it('sıra korunur: önce yakalanan önce gider', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');
    await kuyruk.ekle(yuk('cap-2'), 'b');
    await kuyruk.ekle(yuk('cap-3'), 'c');

    expect((await kuyruk.liste()).map((k) => k.captureId)).toEqual(['cap-1', 'cap-2', 'cap-3']);
  });
});

describe('Başarı ve başarısızlık', () => {
  it('gönderilen kayıt kuyruktan DÜŞER', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');
    await kuyruk.ekle(yuk('cap-2'), 'b');

    await kuyruk.dusur('cap-1');

    expect((await kuyruk.liste()).map((k) => k.captureId)).toEqual(['cap-2']);
  });

  it('başarısız deneme sayacı artar ve hata metni saklanır', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');

    await kuyruk.basarisiz('cap-1', 'HTTP 502');

    const kayit = (await kuyruk.liste())[0];
    expect(kayit?.deneme).toBe(1);
    expect(kayit?.sonHata).toBe('HTTP 502');
  });
});

describe('MV3 uyanışında toparlama', () => {
  it('deneme hakkı kalan kayıtlar toparlanır', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');
    await kuyruk.basarisiz('cap-1', 'ağ');

    expect((await kuyruk.toparlanacaklar()).map((k) => k.captureId)).toEqual(['cap-1']);
  });

  it('hakkı biten kayıt otomatik denenmez — sessiz sonsuz tekrar YOK', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');
    for (let i = 0; i < MAKS_DENEME; i++) {
      await kuyruk.basarisiz('cap-1', 'ağ');
    }

    expect(await kuyruk.toparlanacaklar()).toEqual([]);
    // …ama kayıt DURUR: veri kaybolmaz, kullanıcı komutunu bekler.
    expect(await kuyruk.liste()).toHaveLength(1);
  });

  it('kullanıcı "tekrar dene" derse hak geri gelir', async () => {
    await kuyruk.ekle(yuk('cap-1'), 'a');
    for (let i = 0; i < MAKS_DENEME; i++) {
      await kuyruk.basarisiz('cap-1', 'ağ');
    }

    await kuyruk.denemeleriSifirla('cap-1');

    expect((await kuyruk.toparlanacaklar()).map((k) => k.captureId)).toEqual(['cap-1']);
    expect((await kuyruk.liste())[0]?.sonHata).toBeNull();
  });
});

describe('Bozuk depo içeriği', () => {
  it('dizi olmayan kayıt boş kuyruk sayılır — eklenti çökmez', async () => {
    depo.veri[KUYRUK_ANAHTARI] = { bozuk: true };

    expect(await kuyruk.liste()).toEqual([]);
  });
});
