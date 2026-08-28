import type { ReactNode } from 'react';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';

/**
 * TABLO (İE#16 D1.3 · kanon §4: "tablo kalitesi ürünün yüzüdür").
 *
 * Sağladıkları: YAPIŞKAN BAŞLIK (uzun listede sütun adı kaybolmaz) ·
 * sıralanabilir başlık · satır hover · seçim · YOĞUNLUK (ferah/sıkı).
 *
 * Sayı sütunları `sagaHizali` ile işaretlenir ve tabular rakamla dizilir —
 * binlik basamaklar alt alta hizalanır, göz rakamları karşılaştırabilir.
 */

export type SiralamaYonu = 'artan' | 'azalan';
export type Yogunluk = 'ferah' | 'sik';

export interface Siralama {
  alan: string;
  yon: SiralamaYonu;
}

export function Tablo({
  children,
  yogunluk = 'ferah',
  className = '',
}: {
  children: ReactNode;
  yogunluk?: Yogunluk;
  className?: string;
}) {
  return (
    <div className={`table-scroll rounded-lg border border-line bg-surface ${className}`}>
      <table className={`w-full border-collapse text-md ${yogunluk === 'sik' ? '[--satir:44px]' : '[--satir:62px]'}`}>
        {children}
      </table>
    </div>
  );
}

export function TabloBaslik({ children }: { children: ReactNode }) {
  return (
    <thead className="sticky top-0 z-10 bg-g50 text-left text-xs font-bold tracking-wide text-ink-3">
      {children}
    </thead>
  );
}

export function BaslikHucre({
  children,
  alan,
  siralama,
  onSirala,
  sagaHizali = false,
  genislik,
}: {
  children: ReactNode;
  /** Verilirse sütun sıralanabilir olur. */
  alan?: string;
  siralama?: Siralama | null;
  onSirala?: (alan: string) => void;
  sagaHizali?: boolean;
  genislik?: string;
}) {
  const aktif = alan !== undefined && siralama?.alan === alan;
  const Simge = !aktif ? ChevronsUpDown : siralama?.yon === 'artan' ? ChevronUp : ChevronDown;

  return (
    <th
      scope="col"
      style={genislik === undefined ? undefined : { width: genislik }}
      className={`border-b border-line px-3 py-2.5 font-bold uppercase ${sagaHizali ? 'text-right' : 'text-left'}`}
      aria-sort={aktif ? (siralama?.yon === 'artan' ? 'ascending' : 'descending') : undefined}
    >
      {alan === undefined || onSirala === undefined ? (
        children
      ) : (
        <button
          type="button"
          className={`inline-flex items-center gap-1 hover:text-ink ${aktif ? 'text-ink' : ''} ${
            sagaHizali ? 'flex-row-reverse' : ''
          }`}
          onClick={() => onSirala(alan)}
        >
          {children}
          <Simge size={12} className="opacity-70" aria-hidden />
        </button>
      )}
    </th>
  );
}

export function TabloGovde({ children }: { children: ReactNode }) {
  return <tbody className="divide-y divide-line-soft">{children}</tbody>;
}

export function Satir({
  children,
  secili = false,
  onClick,
}: {
  children: ReactNode;
  secili?: boolean;
  onClick?: () => void;
}) {
  return (
    <tr
      className={`h-[var(--satir)] transition-colors ${secili ? 'bg-blue-soft' : 'hover:bg-g50'} ${
        onClick ? 'cursor-pointer' : ''
      }`}
      onClick={onClick}
    >
      {children}
    </tr>
  );
}

export function Hucre({
  children,
  sagaHizali = false,
  className = '',
}: {
  children: ReactNode;
  sagaHizali?: boolean;
  className?: string;
}) {
  return (
    <td className={`px-3 py-2 align-middle ${sagaHizali ? 'text-right tabular-nums' : ''} ${className}`}>
      {children}
    </td>
  );
}

/** Sıralama durumunu çeviren yardımcı: aynı alana tekrar tıklamak yönü değiştirir. */
export function siralamaCevir(mevcut: Siralama | null, alan: string): Siralama {
  if (mevcut === null || mevcut.alan !== alan) return { alan, yon: 'artan' };

  return { alan, yon: mevcut.yon === 'artan' ? 'azalan' : 'artan' };
}
