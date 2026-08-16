import { useEffect, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { productStatusLabels } from '../locales/tr';
import { StatusBadge } from './ui';
import { useReference } from '../store/reference';
import type { ProductStatus } from '../api/types';

/**
 * Ürün durumu menüsü — tek dokunuşla açılır, ikinci dokunuşta durum değişir.
 *
 * Seçenekler API'den gelen izin haritasından üretilir (`/api/system/state-machine`);
 * geçersiz geçiş kullanıcıya HİÇ gösterilmez. Kural yine de backend'de zorlanır —
 * burası yalnızca yanlış seçeneği sunmama katmanıdır (docs/04 §2b).
 */
export default function StatusMenu({
  status,
  onChange,
  busy,
}: {
  status: ProductStatus;
  onChange: (next: ProductStatus) => void;
  busy?: boolean;
}) {
  const machine = useReference((state) => state.machine);
  const [open, setOpen] = useState(false);
  const container = useRef<HTMLDivElement>(null);

  const allowed = machine?.product[status] ?? [];

  useEffect(() => {
    if (!open) return;
    const close = (event: MouseEvent) => {
      if (!container.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, [open]);

  if (allowed.length === 0) {
    // Kapalı durum (Geldi/İptal): ilerletilecek yer yok, yalnızca rozet.
    return <StatusBadge status={status} />;
  }

  return (
    <div className="relative inline-block" ref={container}>
      <button
        type="button"
        className="inline-flex min-h-11 items-center gap-1 rounded-xl px-1 disabled:opacity-50"
        aria-haspopup="listbox"
        aria-expanded={open}
        disabled={busy}
        onClick={() => setOpen((value) => !value)}
      >
        <StatusBadge status={status} />
        <ChevronDown className="h-4 w-4 text-slate-400" aria-hidden />
      </button>

      {open && (
        <ul
          className="absolute right-0 z-20 mt-1 min-w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
          role="listbox"
        >
          {allowed.map((next) => (
            <li key={next}>
              <button
                type="button"
                role="option"
                aria-selected={false}
                className="flex min-h-11 w-full items-center px-3 text-left text-sm hover:bg-slate-50"
                onClick={() => {
                  setOpen(false);
                  onChange(next);
                }}
              >
                {productStatusLabels[next]}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
