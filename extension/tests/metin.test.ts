import { describe, expect, it } from 'vitest';

import { metinNormalize } from '../core/metin';

/**
 * A3 — HTML VARLIKLARI ÇİPTE LİTERAL GÖRÜNMEZ (saha bulgusu 27 Ağu).
 *
 * G9'un eklenti ikizi. Sunucu tarafındaki sözleşmeyle aynı üç kural sınanır:
 * bir kez çöz · görünmez boşlukları at · XSS regresyonu bırakma.
 */
describe('A3 — metinNormalize', () => {
  it('adlandırılmış varlıkları çözer', () => {
    expect(metinNormalize('A&gt;B')).toBe('A>B');
    expect(metinNormalize('英文版&gt;1')).toBe('英文版>1');
    expect(metinNormalize('Renk &amp; Beden')).toBe('Renk & Beden');
    expect(metinNormalize('&quot;kalıp&quot;')).toBe('"kalıp"');
  });

  it('sayısal varlıkları çözer (ondalık ve onaltılık)', () => {
    expect(metinNormalize('A&#62;B')).toBe('A>B');
    expect(metinNormalize('A&#x3E;B')).toBe('A>B');
  });

  it('İKİ KEZ çözmez — bilerek kaçırılmış metin korunur', () => {
    // "&amp;lt;" kaynakta "&lt;" demektir; "<" değil.
    expect(metinNormalize('&amp;lt;')).toBe('&lt;');
  });

  it('tanımadığı varlığa dokunmaz', () => {
    expect(metinNormalize('50&deg; sıcak')).toBe('50&deg; sıcak');
  });

  it('görünmez boşlukları temizler ve boşlukları teke indirir', () => {
    expect(metinNormalize('kalın taban')).toBe('kalın taban');
    expect(metinNormalize('a​b')).toBe('ab');
    expect(metinNormalize('  iki   boşluk  ')).toBe('iki boşluk');
  });

  it('XSS REGRESYONU: işaretleme metne dönüşür, çalıştırılabilir hâle GELMEZ', () => {
    // Çözülen değer metindir; eklenti onu daima textContent ile basar.
    expect(metinNormalize('&lt;img src=x onerror=alert(1)&gt;')).toBe('<img src=x onerror=alert(1)>');
    expect(metinNormalize('<img onerror=alert(1)>')).toBe('<img onerror=alert(1)>');
  });
});
