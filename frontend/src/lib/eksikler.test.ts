import { describe, expect, test } from 'vitest';
import { eksikAlanlar, eksikEtiketleri, eksikMi, etiketHaritasi, hazirOlabilirMi } from './eksikler';
import type { Product, UrunEksigi } from '../api/types';

/**
 * ÜRÜN EKSİKLERİ — panel SUNUCUNUN kararını okur (İE#21 B2 · C8).
 *
 * Burada sınanan şey bir kural motoru değil, bir DİSİPLİN: panel kendi "eksik"
 * tanımını kurmaz, `hazir_eksikleri` ne diyorsa onu sayar ve süzer. Eski sürümde
 * bu dosya PHP'deki kuralların JS kopyasını sınıyordu; kopya kaldırıldı, çünkü
 * iki tanım bir gün ayrışır ve kullanıcı çiple kayıt hatası arasında çelişki görür.
 */

const KATEGORI: UrunEksigi = { alan: 'category_id', etiket: 'Kategori' };
const FIYAT: UrunEksigi = { alan: 'price_yuan', etiket: 'Birim fiyat' };

function urun(eksikler: UrunEksigi[] = []): Product {
  return {
    id: 1,
    list_id: 1,
    sort_no: 1,
    category_id: 4,
    platform: '1688',
    external_id: 'X-1',
    name: 'Sebze doğrayıcı',
    name_original: null,
    detail: null,
    url: 'https://detail.1688.com/offer/1.html',
    vendor_name: null,
    vendor_url: null,
    sku_selection: { renk: 'siyah' },
    sku_matrix: null,
    main_image: 'https://cdn/x.jpg',
    video_url: null,
    qty: 240,
    price_yuan: '26.90',
    price_ddp_usd: '0.00',
    price_target_try: null,
    unit_profit_try: null,
    line_profit_try: null,
    price_yuan_tl: '0.00',
    price_ddp_tl: '0.00',
    line_total_yuan: '0.00',
    line_total_yuan_tl: '0.00',
    units_per_carton: null,
    tracking_no: null,
    status: 'to_order',
    hazir: eksikler.length === 0,
    hazir_eksikleri: eksikler,
    note: null,
    images: [],
    created_at: '2026-08-23T10:00:00+03:00',
    updated_at: '2026-08-23T10:00:00+03:00',
    deleted_at: null,
  } as Product;
}

describe('Sunucunun dökümü okunur', () => {
  test('eksik yoksa kapı açıktır', () => {
    expect(eksikAlanlar(urun())).toEqual([]);
    expect(hazirOlabilirMi(urun())).toBe(true);
  });

  test('alan kimliği ve etiket ayrı ayrı okunur', () => {
    const p = urun([KATEGORI, FIYAT]);

    expect(eksikAlanlar(p)).toEqual(['category_id', 'price_yuan']);
    expect(eksikEtiketleri(p)).toEqual(['Kategori', 'Birim fiyat']);
    expect(hazirOlabilirMi(p)).toBe(false);
  });

  test('tek alan sorgusu süzgeç içindir', () => {
    expect(eksikMi(urun([KATEGORI]), 'category_id')).toBe(true);
    expect(eksikMi(urun([KATEGORI]), 'price_yuan')).toBe(false);
  });
});

describe('Etiket haritası ekrandaki ürünlerden toplanır', () => {
  test('aynı alan bir kez, etiketiyle', () => {
    const harita = etiketHaritasi([urun([KATEGORI]), urun([KATEGORI, FIYAT])]);

    expect(harita.get('category_id')).toBe('Kategori');
    expect(harita.get('price_yuan')).toBe('Birim fiyat');
    expect(harita.size).toBe(2);
  });
});

describe('Eksik alan gelmezse panel çökmez', () => {
  test('eski/kırpılmış yanıtta boş sayılır', () => {
    const eksiksiz: Record<string, unknown> = { ...urun() };
    delete eksiksiz.hazir_eksikleri;

    // Sunucu bir gün alanı göndermezse ekran boş çip listesiyle çalışmalı;
    // "undefined.map" ile beyaz ekran vermek en kötü hata biçimidir.
    expect(eksikAlanlar(eksiksiz as unknown as Product)).toEqual([]);
    expect(hazirOlabilirMi(eksiksiz as unknown as Product)).toBe(true);
  });
});
