import { describe, expect, it } from 'vitest';

import { GOMULU_SECICILER, secicileriSec, surum } from '../core/secici';
import { fiksturdenGecer } from '../core/fiksturKapisi';
import type { SelectorSet } from '../core/types';

/**
 * SEÇİCİ SÜRÜMLEME + FİKSTÜR ÖN-KAPISI (İE#21 A6).
 *
 * Sözleşme: bozuk/eski/yabancı paket ASLA kabul edilmez ve reddedildiğinde
 * eklenti çalışmaya DEVAM eder — gömülü paket son savunmadır.
 */

function uzakPaket(fark: Partial<SelectorSet> & { updated_at?: string } = {}): SelectorSet {
  return {
    ...(JSON.parse(JSON.stringify(GOMULU_SECICILER)) as SelectorSet),
    updated_at: '2099-01-01',
    ...fark,
  } as SelectorSet;
}

describe('Gömülü paket', () => {
  it('eklentinin içinde vardır ve fikstür kapısından geçer', () => {
    expect(GOMULU_SECICILER.platform).toBe('1688');
    expect(GOMULU_SECICILER.schema_version).toBe(2);
    expect(fiksturdenGecer(GOMULU_SECICILER)).toBe(true);
  });

  it('sürümü ISO gün olarak taşır', () => {
    expect(surum(GOMULU_SECICILER)).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });
});

describe('Ağ yoksa mevcut paket korunur', () => {
  it('null/undefined yanıt sessizce yok sayılır', () => {
    const sonuc = secicileriSec(GOMULU_SECICILER, null, fiksturdenGecer);

    expect(sonuc.secilen).toBe(GOMULU_SECICILER);
    expect(sonuc.aciklama).toBeNull();
  });
});

describe('Biçim ve şema kapısı', () => {
  it('beklenmeyen biçim reddedilir', () => {
    const sonuc = secicileriSec(GOMULU_SECICILER, { hepsi: 'yanlış' }, fiksturdenGecer);

    expect(sonuc.sebep).toBe('UZAK_EKSIK_ALAN');
    expect(sonuc.secilen).toBe(GOMULU_SECICILER);
  });

  it('desteklenmeyen şema sürümü DENENMEZ', () => {
    const sonuc = secicileriSec(GOMULU_SECICILER, uzakPaket({ schema_version: 9 }), fiksturdenGecer);

    expect(sonuc.sebep).toBe('UZAK_SEMA_DESTEKSIZ');
    expect(sonuc.aciklama).toContain('şema 9');
  });

  it('başka platformun paketi kabul edilmez', () => {
    const sonuc = secicileriSec(GOMULU_SECICILER, uzakPaket({ platform: 'taobao' }), fiksturdenGecer);

    expect(sonuc.sebep).toBe('UZAK_PLATFORM_UYUSMUYOR');
  });

  it('zorunlu yolu eksik paket reddedilir ve hangi yol eksikse söylenir', () => {
    const bozuk = uzakPaket();
    delete (bozuk.paths as Record<string, unknown>).title;

    const sonuc = secicileriSec(GOMULU_SECICILER, bozuk, fiksturdenGecer);

    expect(sonuc.sebep).toBe('UZAK_EKSIK_ALAN');
    expect(sonuc.aciklama).toContain('title');
  });
});

describe('Sürüm kıyası', () => {
  it('eski ya da aynı sürüm değiştirme yapmaz', () => {
    const eski = uzakPaket({ updated_at: '2000-01-01' });
    const ayni = uzakPaket({ updated_at: surum(GOMULU_SECICILER) });

    expect(secicileriSec(GOMULU_SECICILER, eski, fiksturdenGecer).sebep).toBe('UZAK_ESKI_SURUM');
    expect(secicileriSec(GOMULU_SECICILER, ayni, fiksturdenGecer).sebep).toBe('UZAK_ESKI_SURUM');
  });

  it('yeni ve sağlam paket KABUL edilir', () => {
    const yeni = uzakPaket({ updated_at: '2099-01-01' });

    const sonuc = secicileriSec(GOMULU_SECICILER, yeni, fiksturdenGecer);

    expect(sonuc.sebep).toBe('UZAK_KABUL');
    expect(surum(sonuc.secilen)).toBe('2099-01-01');
  });
});

describe('Fikstür ön-kapısı', () => {
  it('yeni ama AYRIŞTIRAMAYAN paket reddedilir; eski paket çalışmaya devam eder', () => {
    const bozukYollar = uzakPaket();
    bozukYollar.paths.title = ['bu.yol.yok'];
    bozukYollar.paths.current_prices = ['bu.yol.da.yok'];
    bozukYollar.fallbacks = { ...bozukYollar.fallbacks, title_from_dom: '' };

    const sonuc = secicileriSec(GOMULU_SECICILER, bozukYollar, fiksturdenGecer);

    expect(sonuc.sebep).toBe('UZAK_FIKSTUR_KAPISI');
    expect(sonuc.secilen).toBe(GOMULU_SECICILER);
    expect(sonuc.aciklama).toContain('örnek sayfayı ayrıştıramadı');
  });

  it('kapı çökerse ret sayılır — çökme kabul DEĞİLDİR', () => {
    const patlayan = uzakPaket();
    // Geçersiz regex: parser içinde RegExp kurulumunda hata fırlatır.
    patlayan.fallbacks = { ...patlayan.fallbacks, offer_id_from_url: '([' };

    expect(fiksturdenGecer(patlayan)).toBe(false);
    expect(secicileriSec(GOMULU_SECICILER, patlayan, fiksturdenGecer).sebep).toBe('UZAK_FIKSTUR_KAPISI');
  });
});
