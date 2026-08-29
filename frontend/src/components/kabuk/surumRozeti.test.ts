import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';

const kok = resolve(__dirname, '../../..');
const oku = (goreli: string): string => readFileSync(resolve(kok, goreli), 'utf8');

/**
 * BULGU #2 BEKÇİSİ — SÜRÜM ROZETİ SABİT METİN DEĞİL.
 *
 * Marka bloğundaki rozet `import.meta.env.VITE_SURUM ?? '1.0'` yazıyordu ve
 * `VITE_SURUM` hiçbir yerde tanımlı değildi: rozet uygulama 1.2.0 iken bile
 * her zaman "1.0" basıyordu. Destek isteyen kullanıcı yanlış sürüm söylerdi.
 *
 * Bu bekçi iki şeyi zorlar: (1) sabit sürüm metni geri gelmesin, (2) değer
 * sunucudan (AppVersion) okunsun.
 */
describe('sürüm rozeti', () => {
  const yanMenu = oku('src/components/kabuk/YanMenu.tsx');

  test('sabit sürüm metni YOK', () => {
    expect(yanMenu).not.toContain("VITE_SURUM");
    // Herhangi bir "x.y" biçimli sabit sürüm dizesi de olmamalı.
    const sabitSurum = /['"]\d+\.\d+(\.\d+)?['"]/.exec(
      yanMenu.split('\n').filter((s) => !s.trim().startsWith('*') && !s.trim().startsWith('//')).join('\n'),
    );
    expect(sabitSurum, `sabit sürüm dizesi: ${sabitSurum?.[0] ?? ''}`).toBeNull();
  });

  test('değer sunucudan okunur', () => {
    expect(yanMenu).toContain('useSurum()');
    expect(oku('src/lib/useSurum.ts')).toContain('systemApi\n      .status()');
    expect(oku('src/lib/useSurum.ts')).toContain('durum.app_version');
  });

  test('sürüm alınamazsa rozet BASILMAZ', () => {
    // Uydurma sürüm göstermek, hiç göstermemekten kötüdür.
    expect(yanMenu).toContain('surum !== null ?');
    expect(oku('src/lib/useSurum.ts')).toContain('return surum;');
  });
});
