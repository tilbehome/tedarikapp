import { describe, expect, it } from 'vitest';
import { ekranAdi, menuGruplari } from './menu';

/**
 * İE#20 C8 — "Yakında" öğeleri canlıda gizlenir.
 *
 * Test GELİŞTİRME modunda koşar (vitest `import.meta.env.PROD === false`), yani
 * burada tüm öğeler görünür olmalıdır. Üretim davranışını dolaylı olarak
 * doğruluyoruz: filtrenin varlığı ve `ekranAdi`nin gizlenen yolları hâlâ
 * tanıması. `ekranAdi` gizlenen bir yolu tanımazsa, o adrese elle giden
 * kullanıcı boş bir başlık görür.
 */
describe('menü', () => {
  it('gelistirmede YOL HARITASI ogeleri gorunur', () => {
    const tumOgeler = menuGruplari.flatMap((grup) => grup.ogeler);

    expect(tumOgeler.some((oge) => !oge.hazir)).toBe(true);
  });

  it('bos grup birakilmaz', () => {
    for (const grup of menuGruplari) {
      expect(grup.ogeler.length, `"${grup.baslik}" grubu boş`).toBeGreaterThan(0);
    }
  });

  it('ekranAdi GIZLENEN yollari da tanir', () => {
    // Üretimde menüden düşen "Keşif" yoluna elle gidilirse başlık yine doğru olmalı.
    expect(ekranAdi('/kesif')).toEqual(['Çalışma', 'Keşif']);
  });

  it('bilinmeyen yol PANORAMAYA duser', () => {
    expect(ekranAdi('/boyle-bir-yol-yok')).toEqual(['Çalışma', 'Panorama']);
  });

  it('her ogenin bolumu ve etiketi vardir', () => {
    for (const oge of menuGruplari.flatMap((grup) => grup.ogeler)) {
      expect(oge.label.trim(), `${oge.to} etiketi boş`).not.toBe('');
      expect(oge.bolum.trim(), `${oge.to} bölümü boş`).not.toBe('');
    }
  });
});
