import { create } from 'zustand';
import { useEffect } from 'react';
import { Check, Undo2, X } from 'lucide-react';

/**
 * Bildirim şeridi. docs/09 §1 "affedicilik": yıkıcı işlem sonrası "Geri Al"
 * eylemi doğrudan bildirimin içinde sunulur.
 */

interface ToastItem {
  id: number;
  message: string;
  tone: 'success' | 'error';
  undo?: () => void | Promise<void>;
}

interface ToastState {
  items: ToastItem[];
  push: (message: string, tone?: 'success' | 'error', undo?: () => void | Promise<void>) => void;
  dismiss: (id: number) => void;
}

let sequence = 0;

export const useToast = create<ToastState>((set) => ({
  items: [],
  push: (message, tone = 'success', undo) => {
    sequence += 1;
    const item: ToastItem = { id: sequence, message, tone, undo };
    set((state) => ({ items: [...state.items, item] }));
  },
  dismiss: (id) => set((state) => ({ items: state.items.filter((item) => item.id !== id) })),
}));

function Row({ item }: { item: ToastItem }) {
  const dismiss = useToast((state) => state.dismiss);

  useEffect(() => {
    // "Geri Al" sunulan bildirim daha uzun durur — kullanıcı kararını versin.
    const timeout = window.setTimeout(() => dismiss(item.id), item.undo ? 9000 : 4000);
    return () => window.clearTimeout(timeout);
  }, [dismiss, item.id, item.undo]);

  const tone =
    item.tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900';

  return (
    <div className={`pointer-events-auto flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm shadow-lg ${tone}`}>
      {item.tone === 'success' && <Check className="h-4 w-4 shrink-0" aria-hidden />}
      <span className="flex-1">{item.message}</span>
      {item.undo && (
        <button
          type="button"
          className="inline-flex min-h-9 items-center gap-1 rounded-lg bg-white/70 px-3 font-semibold"
          onClick={() => {
            void item.undo?.();
            dismiss(item.id);
          }}
        >
          <Undo2 className="h-4 w-4" aria-hidden />
          Geri Al
        </button>
      )}
      <button type="button" aria-label="Kapat" className="rounded p-1" onClick={() => dismiss(item.id)}>
        <X className="h-4 w-4" aria-hidden />
      </button>
    </div>
  );
}

export function Toaster() {
  const items = useToast((state) => state.items);

  return (
    <div
      className="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-6"
      role="status"
      aria-live="polite"
    >
      {items.map((item) => (
        <Row key={item.id} item={item} />
      ))}
    </div>
  );
}
