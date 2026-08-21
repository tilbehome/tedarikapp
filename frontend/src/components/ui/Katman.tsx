import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

/**
 * ÜST KATMANLAR (İE#16 D1.3): modal · çekmece · popover · ipucu.
 *
 * Üçünde de aynı sözleşme geçerlidir:
 *  • Esc kapatır, dışarı tıklamak kapatır (yıkıcı bir iş yapmadıkları için).
 *  • Açılınca odak içeri alınır, kapanınca ÇAĞIRAN ÖĞEYE geri döner —
 *    klavye kullanıcısı listede yerini kaybetmez.
 *  • Arka plan kaydırması durur; sayfa altta kaymaz.
 */

function useKatmanDavranisi(acik: boolean, onKapat: () => void) {
  const kap = useRef<HTMLDivElement>(null);
  const oncekiOdak = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!acik) return;

    oncekiOdak.current = document.activeElement as HTMLElement | null;
    const govdeTasma = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    // Odağı katmanın içine al: ilk odaklanabilir öğe, yoksa kabın kendisi.
    requestAnimationFrame(() => {
      const hedef = kap.current?.querySelector<HTMLElement>(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
      );
      (hedef ?? kap.current)?.focus();
    });

    const dinle = (olay: KeyboardEvent) => {
      if (olay.key === 'Escape') {
        olay.stopPropagation();
        onKapat();
      }
    };
    window.addEventListener('keydown', dinle);

    return () => {
      window.removeEventListener('keydown', dinle);
      document.body.style.overflow = govdeTasma;
      oncekiOdak.current?.focus();
    };
  }, [acik, onKapat]);

  return kap;
}

export function Modal({
  acik,
  baslik,
  onKapat,
  children,
  eylemler,
  genislik = 'max-w-lg',
}: {
  acik: boolean;
  baslik: string;
  onKapat: () => void;
  children: ReactNode;
  eylemler?: ReactNode;
  genislik?: string;
}) {
  const kap = useKatmanDavranisi(acik, onKapat);
  if (!acik) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-g900/45 p-4"
      onClick={(olay) => {
        if (olay.target === olay.currentTarget) onKapat();
      }}
    >
      <div
        ref={kap}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-label={baslik}
        className={`w-full ${genislik} overflow-hidden rounded-lg border border-line bg-surface shadow-3`}
      >
        <div className="flex items-center justify-between border-b border-line px-4 py-3">
          <h2 className="text-lg font-semibold text-ink">{baslik}</h2>
          <button
            type="button"
            className="flex size-8 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
            onClick={onKapat}
            aria-label="Kapat"
          >
            <X size={16} aria-hidden />
          </button>
        </div>
        <div className="max-h-[70vh] overflow-y-auto p-4">{children}</div>
        {eylemler && <div className="flex justify-end gap-2 border-t border-line px-4 py-3">{eylemler}</div>}
      </div>
    </div>
  );
}

/**
 * SAĞ ÇEKMECE — ayrıntı paneli (Keşif havuzunun ana okuma yüzeyi, Dilim 4).
 * Modal'dan farkı: sayfa arkada GÖRÜNÜR kalır; kullanıcı listedeki yerini kaybetmez.
 */
export function Cekmece({
  acik,
  baslik,
  onKapat,
  children,
  altBar,
}: {
  acik: boolean;
  baslik: string;
  onKapat: () => void;
  children: ReactNode;
  altBar?: ReactNode;
}) {
  const kap = useKatmanDavranisi(acik, onKapat);
  if (!acik) return null;

  return (
    <div className="fixed inset-0 z-50">
      <div className="absolute inset-0 bg-g900/35" onClick={onKapat} />
      <aside
        ref={kap}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-label={baslik}
        className="absolute inset-y-0 right-0 flex w-full max-w-[480px] flex-col border-l border-line bg-surface shadow-3"
      >
        <div className="flex items-center justify-between border-b border-line px-4 py-3">
          <h2 className="truncate text-lg font-semibold text-ink">{baslik}</h2>
          <button
            type="button"
            className="flex size-8 shrink-0 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
            onClick={onKapat}
            aria-label="Kapat"
          >
            <X size={16} aria-hidden />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto p-4">{children}</div>
        {altBar && <div className="border-t border-line p-3">{altBar}</div>}
      </aside>
    </div>
  );
}

/**
 * POPOVER — düğmeye bağlı küçük katman (filtre paneli, sütun seçici).
 * Modal DEĞİLDİR: arka plan kilitlenmez, sayfa kullanılabilir kalır.
 */
export function Popover({
  acik,
  onKapat,
  children,
  hizalama = 'sol',
}: {
  acik: boolean;
  onKapat: () => void;
  children: ReactNode;
  hizalama?: 'sol' | 'sag';
}) {
  const kap = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!acik) return;

    const disariTikla = (olay: MouseEvent) => {
      if (kap.current !== null && !kap.current.contains(olay.target as Node)) onKapat();
    };
    const escBas = (olay: KeyboardEvent) => {
      if (olay.key === 'Escape') onKapat();
    };

    document.addEventListener('mousedown', disariTikla);
    window.addEventListener('keydown', escBas);

    return () => {
      document.removeEventListener('mousedown', disariTikla);
      window.removeEventListener('keydown', escBas);
    };
  }, [acik, onKapat]);

  if (!acik) return null;

  return (
    <div
      ref={kap}
      className={`absolute top-[calc(100%+6px)] z-40 min-w-[240px] rounded-lg border border-line bg-surface p-3 shadow-2 ${
        hizalama === 'sag' ? 'right-0' : 'left-0'
      }`}
    >
      {children}
    </div>
  );
}

/** İPUCU — yalnız açıklayıcı metin; kritik bilgi ipucuna SAKLANMAZ. */
export function Ipucu({ metin, children }: { metin: string; children: ReactNode }) {
  return (
    <span className="group relative inline-flex">
      {children}
      <span
        role="tooltip"
        className="pointer-events-none absolute bottom-[calc(100%+6px)] left-1/2 z-40 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-g900 px-2 py-1 text-xs text-white group-hover:block"
      >
        {metin}
      </span>
    </span>
  );
}
