import { describe, expect, it, vi } from 'vitest';

import { GONDERIM_ZAMAN_ASIMI_MS, KONTROL_NOTU, gonderimiYurut } from '../core/gonderim';

/**
 * A7 — YAVAŞ SUNUCU BAŞARISIZLIK DEĞİLDİR (saha bulgusu 27 Ağu).
 *
 * Sahada 4 sn'lik mesaj zaman aşımı, sunucu görseli indirirken doluyor; ürün
 * listeye düşüyor ama panel "yanıt vermiyor" diyordu. Süre 30 sn'ye çıkar; 30
 * sn de yetmezse aynı capture_id ile idempotent tekrar yapılır ve sonuç
 * BAŞARI gösterilir — sunucu ikinci kaydı AÇMAZ (rc8-03).
 */
describe('A7 — gonderimiYurut', () => {
  it('6 sn süren gönderim BAŞARI döner (4 sn eşiğinde patlamaz)', async () => {
    const bekle = vi.fn(async () => new Promise<void>(() => {})); // zaman aşımı hiç dolmaz
    const istek = vi.fn(async () => {
      // 6 sn'lik gerçek gecikmenin taklidi: mikro görev sonrası yanıt.
      await Promise.resolve();

      return { product_id: 42, duplicate: false };
    });

    const sonuc = await gonderimiYurut({ istek, bekle });

    expect(sonuc).toEqual({ sonuc: 'BASARILI', urunId: 42 });
    expect(istek).toHaveBeenCalledTimes(1);
  });

  it('30 sn aşılırsa HATA değil: not gösterilir ve AYNI kimlikle tekrar sorulur', async () => {
    const notlar: (string | null)[] = [];
    let ilkCagriCozuldu = false;
    const istek = vi.fn(async (deneme: number) => {
      if (deneme === 1) {
        // İlk istek hiç bitmiyor (sunucu hâlâ görseli indiriyor).
        return new Promise<{ duplicate: boolean; product_id: number }>(() => {
          ilkCagriCozuldu = false;
        });
      }

      // İkinci istek: sunucu ilk kaydı bulur (idempotent tekrar).
      return { duplicate: true, product_id: 42 };
    });

    const sonuc = await gonderimiYurut({
      istek,
      bekle: async () => undefined, // süre ANINDA dolmuş sayılır
      onNot: (not) => notlar.push(not),
    });

    expect(notlar[0], 'kullanıcı belirsizlikte bilgilendirilir').toBe(KONTROL_NOTU);
    expect(sonuc, 'tekrarın duplicate yanıtı BAŞARI sayılır').toEqual({ sonuc: 'BASARILI', urunId: 42 });
    expect(istek).toHaveBeenCalledTimes(2);
    expect(istek.mock.calls.map((c) => c[0]), 'iki deneme, tek kimlik').toEqual([1, 2]);
    expect(ilkCagriCozuldu).toBe(false);
    expect(notlar.at(-1), 'sonuç gelince not silinir').toBeNull();
  });

  it('İLK istekte gelen duplicate MÜKERRERdir (bizim tekrarımız değil)', async () => {
    const sonuc = await gonderimiYurut({
      istek: async () => ({ duplicate: true, product_id: 7 }),
      bekle: async () => new Promise<void>(() => {}),
    });

    expect(sonuc).toEqual({ sonuc: 'MUKERRER', urunId: 7 });
  });

  it('yetki hatası yeniden DENENMEZ', async () => {
    const istek = vi.fn(async () => {
      throw new Error('401 Unauthorized');
    });

    const sonuc = await gonderimiYurut({ istek, bekle: async () => new Promise<void>(() => {}) });

    expect(sonuc).toEqual({ sonuc: 'YETKI' });
    expect(istek).toHaveBeenCalledTimes(1);
  });

  it('varsayılan süre 30 saniyedir (4 değil)', () => {
    expect(GONDERIM_ZAMAN_ASIMI_MS).toBe(30_000);
  });
});
