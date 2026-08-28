import { describe, expect, it } from 'vitest';

import { gorunumIcinTurkce } from '../core/gorunumSozlugu';

/**
 * A9 — SALT GÖRÜNÜM TÜRKÇELEŞTİRME (v1.0.1).
 *
 * Sınanan üç şey: bilinen terim çevrilir, bileşik değerde parça parça çalışır,
 * bilinmeyen terim AYNEN kalır. Dördüncüsü sözleşmenin kendisidir: bu fonksiyon
 * girdi metnini değiştirmez, kopya üretir — sunucuya orijinal gider.
 */
describe('A9 — gorunumIcinTurkce', () => {
  it('bilinen renk ve bölge terimlerini çevirir', () => {
    expect(gorunumIcinTurkce('粉红色')).toBe('Pembe');
    expect(gorunumIcinTurkce('美规')).toBe('ABD fişi (110V)');
    expect(gorunumIcinTurkce('均码')).toBe('Tek beden');
  });

  it('VOLTAJ BİLGİSİNİ KORUR — "ABD" demek yetmez', () => {
    // Yanlış voltajla gelen bir cihaz, çevirinin sessiz maliyetidir.
    expect(gorunumIcinTurkce('美规')).toContain('110V');
    expect(gorunumIcinTurkce('欧规')).toContain('220V');
  });

  it('bileşik değerde parça parça çalışır, ayraçları korur', () => {
    expect(gorunumIcinTurkce('颜色: 粉红色')).toBe('Renk: Pembe');
    expect(gorunumIcinTurkce('粉红色 / 美规')).toBe('Pembe / ABD fişi (110V)');
  });

  it('bilinmeyen terime DOKUNMAZ', () => {
    expect(gorunumIcinTurkce('洞洞鞋专用鞋扣')).toBe('洞洞鞋专用鞋扣');
    expect(gorunumIcinTurkce('EVA Slipper')).toBe('EVA Slipper');
  });

  it('kısmen bilinen bileşikte yalnız bilineni çevirir', () => {
    expect(gorunumIcinTurkce('粉红色 / 特殊款')).toBe('Pembe / 特殊款');
  });

  it('boş ve boşluklu değeri olduğu gibi bırakır', () => {
    expect(gorunumIcinTurkce('')).toBe('');
    expect(gorunumIcinTurkce('   ')).toBe('   ');
  });
});
