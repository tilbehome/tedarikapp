import { AlertTriangle, Info, X } from 'lucide-react';
import type { Product } from '../../api/types';
import { type EksikAlan, eksikAlanlar, etiketHaritasi } from '../../lib/eksikler';
import { count } from '../../lib/format';

/**
 * UYARI ÇİPLERİ (İE#21 B2 · referans: `liste-ici.png` sarı şerit).
 *
 * Çip yalnız haber vermez, GÖTÜRÜR: "2 üründe kategori eksik" yazısına tıklayan
 * kullanıcı o iki ürünü görür. Haber verip yerini söylemeyen bir uyarı, 300
 * satırlık listede kullanıcıyı tek tek aramaya mahkûm eder — sarı şerit o
 * durumda yardım değil suçlamadır.
 *
 * Aynı çip ikinci kez tıklanınca süzgeç KALKAR; seçili çip her zaman görünür
 * bir "×" taşır, böylece kullanıcı süzgecin açık olduğunu unutup "ürünlerim
 * nerede?" demez.
 */

interface Props {
  urunler: Product[];
  secili: EksikAlan | null;
  kurKilitli: boolean;
  onSec: (alan: EksikAlan | null) => void;
}

export default function UyariCipleri({ urunler, secili, kurKilitli, onSec }: Props) {
  const sayim = new Map<EksikAlan, number>();
  for (const urun of urunler) {
    for (const alan of eksikAlanlar(urun)) {
      sayim.set(alan, (sayim.get(alan) ?? 0) + 1);
    }
  }

  const etiketler = etiketHaritasi(urunler);
  const cipler = [...sayim.entries()]
    .map(([alan, adet]) => ({ alan, adet, etiket: etiketler.get(alan) ?? alan }))
    .sort((a, b) => b.adet - a.adet || a.etiket.localeCompare(b.etiket, 'tr'));

  if (cipler.length === 0 && kurKilitli) return null;

  return (
    <div className="mb-3 flex flex-wrap items-center gap-2" data-testid="uyari-cipleri">
      {cipler.map(({ alan, adet, etiket }) => {
        const acik = secili === alan;

        return (
          <button
            key={alan}
            type="button"
            onClick={() => onSec(acik ? null : alan)}
            aria-pressed={acik}
            data-testid={`uyari-cip-${alan}`}
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 transition-colors ${
              acik
                ? 'bg-warn text-white ring-warn'
                : 'bg-warn-soft text-warn ring-warn/20 hover:bg-warn/20'
            }`}
          >
            <AlertTriangle className="h-3.5 w-3.5" aria-hidden />
            {count(adet)} üründe {etiket.toLocaleLowerCase('tr')} eksik
            {acik ? <X className="h-3.5 w-3.5" aria-hidden /> : null}
          </button>
        );
      })}

      {!kurKilitli ? (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-g50 px-3 py-1.5 text-xs text-ink-2 ring-1 ring-line">
          <Info className="h-3.5 w-3.5" aria-hidden />
          Kur henüz kilitlenmedi
        </span>
      ) : null}
    </div>
  );
}
