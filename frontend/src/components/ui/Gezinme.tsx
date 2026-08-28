import type { ReactNode } from 'react';
import { Check, ChevronLeft, ChevronRight } from 'lucide-react';

/**
 * GEZİNME PARÇALARI (İE#16 D1.3): sekme · sayfalama · adım göstergesi.
 *
 * Sekmeler URL'e yazılabilir olsun diye DEĞER/DEĞİŞTİR sözleşmesiyle çalışır
 * (D1.4: ekran durumu linke yazılır) — bileşen kendi içinde durum tutmaz.
 */

export interface Sekme {
  deger: string;
  etiket: string;
  sayi?: number;
}

export function Sekmeler({
  sekmeler,
  aktif,
  onDegis,
  sag,
}: {
  sekmeler: Sekme[];
  aktif: string;
  onDegis: (deger: string) => void;
  /** Sağ tarafa eklenecek eylem (örn. "+ Görünüm"). */
  sag?: ReactNode;
}) {
  return (
    <div className="mb-3 flex items-end justify-between gap-3 border-b border-line">
      <div role="tablist" className="flex gap-1 overflow-x-auto">
        {sekmeler.map((sekme) => {
          const secili = sekme.deger === aktif;

          return (
            <button
              key={sekme.deger}
              type="button"
              role="tab"
              aria-selected={secili}
              className={`relative whitespace-nowrap px-3 py-2 text-md font-medium transition-colors ${
                secili ? 'text-navy' : 'text-ink-3 hover:text-ink-2'
              }`}
              onClick={() => onDegis(sekme.deger)}
            >
              {sekme.etiket}
              {/* Sayı SIFIRSA basılmaz — boş parantez gürültüdür. */}
              {sekme.sayi !== undefined && sekme.sayi > 0 && (
                <span className="ml-1.5 rounded-full bg-g100 px-1.5 py-0.5 text-xs text-ink-3">{sekme.sayi}</span>
              )}
              {secili && <span className="absolute inset-x-0 -bottom-px h-[2px] rounded bg-navy" aria-hidden />}
            </button>
          );
        })}
      </div>
      {sag}
    </div>
  );
}

export function Sayfalama({
  sayfa,
  sonMu,
  onDegis,
  toplam,
}: {
  sayfa: number;
  sonMu: boolean;
  onDegis: (sayfa: number) => void;
  toplam?: number;
}) {
  return (
    <div className="mt-4 flex items-center justify-between gap-3">
      <button type="button" className="btn-ghost btn-sm" disabled={sayfa <= 1} onClick={() => onDegis(sayfa - 1)}>
        <ChevronLeft size={14} aria-hidden />
        Önceki
      </button>
      <span className="text-md text-ink-3">
        Sayfa {sayfa}
        {toplam !== undefined && toplam > 0 ? ` · ${toplam} kayıt` : ''}
      </span>
      <button type="button" className="btn-ghost btn-sm" disabled={sonMu} onClick={() => onDegis(sayfa + 1)}>
        Sonraki
        <ChevronRight size={14} aria-hidden />
      </button>
    </div>
  );
}

/** Adım göstergesi — çok adımlı akışlarda nerede olduğumuzu söyler. */
export function Adimlar({ adimlar, aktif }: { adimlar: string[]; aktif: number }) {
  return (
    <ol className="mb-4 flex flex-wrap items-center gap-2">
      {adimlar.map((adim, index) => {
        const bitti = index < aktif;
        const simdi = index === aktif;

        return (
          <li key={adim} className="flex items-center gap-2">
            <span
              className={`flex size-6 items-center justify-center rounded-full text-xs font-bold ${
                bitti
                  ? 'bg-ok-soft text-ok'
                  : simdi
                    ? 'bg-navy text-white'
                    : 'bg-g100 text-ink-3'
              }`}
            >
              {bitti ? <Check size={12} aria-hidden /> : index + 1}
            </span>
            <span className={`text-md ${simdi ? 'font-semibold text-ink' : 'text-ink-3'}`}>{adim}</span>
            {index < adimlar.length - 1 && <span className="mx-1 h-px w-6 bg-line" aria-hidden />}
          </li>
        );
      })}
    </ol>
  );
}
