import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * TOKEN BEKÇİLERİ (İE#20 C11 · İE#16 D1.1).
 *
 * İki kural burada koda bağlanır:
 *
 *  1. `src/styles/tokens.css`, `docs/v3/prototip/tasarim-tokenlari.css`in BİREBİR
 *     kopyasıdır. Şartname değişince önce prototip güncellenir, sonra bu dosya
 *     eşitlenir. İki dosya ayrışırsa "tasarım tek kaynaktan gelir" iddiası çöker
 *     ve kimse hangisinin doğru olduğunu bilemez.
 *
 *  2. Bileşenlerde SABİT renk yazılmaz. Bir bileşen `#0F2557` yazdığı an koyu
 *     tema o noktada kırılır — tema yalnız token değişimiyle çalıştığı için
 *     sabit renk temanın göremediği bir ada olur.
 */

const kok = resolve(__dirname, '../../..');

describe('tasarım tokenları', () => {
  it('prototipteki HER token panelde AYNI değerle vardır', () => {
    // Karşılaştırma DEĞERLER üzerindedir, dosya baytları üzerinde değil: panel
    // dosyası ek açıklama yorumları taşıyabilir (nasıl kullanılacağını anlatan
    // notlar oraya aittir), ama bir tokenın DEĞERİ ayrışırsa tasarım iki farklı
    // gerçeğe bölünür. Bekçinin koruduğu şey budur.
    const degerler = (css: string): Map<string, string> => {
      const harita = new Map<string, string>();
      for (const eslesme of css.matchAll(/(--[a-z0-9-]+)\s*:\s*([^;}]+)/gi)) {
        const ad = eslesme[1];
        const deger = eslesme[2];
        if (ad === undefined || deger === undefined) continue;
        harita.set(ad, deger.trim().replace(/\s+/g, ' '));
      }

      return harita;
    };

    const panel = degerler(readFileSync(resolve(kok, 'frontend/src/styles/tokens.css'), 'utf8'));
    const prototip = degerler(readFileSync(resolve(kok, 'docs/v3/prototip/tasarim-tokenlari.css'), 'utf8'));

    expect(prototip.size, 'Prototipte hiç token bulunamadı — yol yanlış olabilir').toBeGreaterThan(30);

    const ayrisan: string[] = [];
    for (const [ad, deger] of prototip) {
      if (!panel.has(ad)) {
        ayrisan.push(`${ad}: panelde YOK`);
      } else if (panel.get(ad) !== deger) {
        ayrisan.push(`${ad}: prototip "${deger}" ≠ panel "${panel.get(ad)}"`);
      }
    }

    expect(ayrisan, 'Tasarım tek kaynaktan gelmeli — önce docs/v3/prototip güncellenir').toEqual([]);
  });

  it('koyu tema için ayrı bir renk seti tanımlamaz (yalnız token değişimi)', () => {
    const tokens = readFileSync(resolve(kok, 'frontend/src/styles/tokens.css'), 'utf8');

    expect(tokens).toMatch(/\[data-theme=["']?dark/);
  });
});

describe('bileşenlerde sabit renk yasağı', () => {
  /**
   * V3-B D2 — KAPSAM `.tsx`TEN `.ts` VE `.css`E GENİŞLETİLDİ.
   *
   * Nöbet Raporu 5'in bulgusu: bekçi yalnız `.tsx` tarıyordu. O gün `.ts` ve
   * `.css` dosyaları temizdi ama KORUMASIZDI — bir yardımcı dosyaya yazılan
   * hex, koyu temada sessizce yanlış renk verirdi ve test bunu hiç görmezdi.
   *
   * `tokens.css` KENDİSİ hariçtir: paletin tanımlandığı yer orasıdır.
   */
  it('src altındaki .tsx/.ts/.css dosyalarında ham hex renk YOKTUR', async () => {
    const { globSync } = await import('node:fs');
    const dosyalar = [
      ...globSync('src/**/*.tsx', { cwd: resolve(kok, 'frontend') }),
      ...globSync('src/**/*.ts', { cwd: resolve(kok, 'frontend') }),
      ...globSync('src/**/*.css', { cwd: resolve(kok, 'frontend') }),
    ].filter((dosya) => !dosya.replace(/\\/g, '/').endsWith('styles/tokens.css'));

    const ihlaller: string[] = [];
    for (const dosya of dosyalar) {
      const icerik = readFileSync(resolve(kok, 'frontend', dosya), 'utf8');
      // SVG/ikon dolgu değerleri ve yorum satırları hariç: kod içinde geçen hex.
      for (const satir of icerik.split('\n')) {
        const kirpik = satir.trim();
        if (kirpik.startsWith('*') || kirpik.startsWith('//') || kirpik.startsWith('/*')) continue;
        if (/#[0-9a-fA-F]{6}\b/.test(kirpik)) {
          ihlaller.push(`${dosya}: ${kirpik.slice(0, 90)}`);
        }
      }
    }

    expect(ihlaller, 'Sabit renk koyu temada kırılır — token kullanın').toEqual([]);
  });

  it('taranan dosya kümesi BOŞ DEĞİL — bekçi gerçekten bakıyor', async () => {
    // Bir glob deseni bozulursa test "0 ihlal" der ve YEŞİL kalır. Hiçbir şeye
    // bakmayan bir bekçi, olmayan bekçiden daha tehlikelidir.
    const { globSync } = await import('node:fs');

    expect(globSync('src/**/*.tsx', { cwd: resolve(kok, 'frontend') }).length).toBeGreaterThan(20);
    expect(globSync('src/**/*.ts', { cwd: resolve(kok, 'frontend') }).length).toBeGreaterThan(10);
    expect(globSync('src/**/*.css', { cwd: resolve(kok, 'frontend') }).length).toBeGreaterThan(0);
  });
});
