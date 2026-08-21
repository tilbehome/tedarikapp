import { create } from 'zustand';
import { useEffect, useRef, useState } from 'react';
import { Check, TriangleAlert, Undo2, X } from 'lucide-react';

/**
 * BİLDİRİM ŞERİDİ (İE#16 D1.8 · kanon §9 "affedicilik").
 *
 * Yıkıcı işlemden sonra "Geri al" DOĞRUDAN bildirimin içindedir: kullanıcı
 * onay penceresiyle durdurulmaz, hata yaparsa geri alır.
 *
 * SÜRE: geri alınabilir bildirim 5 saniye durur (D1.8). Kısa görünebilir, bu
 * yüzden İMLEÇ ÜZERİNDEYKEN SAYAÇ DURUR — okumak için eğilen kullanıcının
 * elinden kaçmaz. Bilgi bildirimleri 4 saniyede kapanır.
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

/** Geri alma penceresi (D1.8). */
const GERI_AL_MS = 5000;
const BILGI_MS = 4000;

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
  const [duraklatildi, setDuraklatildi] = useState(false);
  const kalan = useRef(item.undo ? GERI_AL_MS : BILGI_MS);
  // Zaman ölçümü RENDER SIRASINDA yapılmaz (saf olmayan çağrı): ilk değer 0'dır,
  // gerçek başlangıç efektin içinde damgalanır.
  const baslangic = useRef(0);

  useEffect(() => {
    if (duraklatildi) {
      kalan.current -= Date.now() - baslangic.current;

      return;
    }

    baslangic.current = Date.now();
    const zamanlayici = window.setTimeout(() => dismiss(item.id), Math.max(500, kalan.current));

    return () => window.clearTimeout(zamanlayici);
  }, [dismiss, item.id, duraklatildi]);

  const ton =
    item.tone === 'success'
      ? 'border-ok/25 bg-ok-soft text-ok'
      : 'border-err/25 bg-err-soft text-err';

  return (
    <div
      className={`pointer-events-auto flex items-center gap-3 rounded-lg border px-4 py-3 text-md shadow-2 ${ton}`}
      onMouseEnter={() => setDuraklatildi(true)}
      onMouseLeave={() => setDuraklatildi(false)}
    >
      {item.tone === 'success' ? (
        <Check className="size-4 shrink-0" aria-hidden />
      ) : (
        <TriangleAlert className="size-4 shrink-0" aria-hidden />
      )}
      <span className="flex-1">{item.message}</span>
      {item.undo && (
        <button
          type="button"
          className="inline-flex min-h-9 items-center gap-1 rounded-lg bg-surface/70 px-3 font-semibold"
          onClick={() => {
            void item.undo?.();
            dismiss(item.id);
          }}
        >
          <Undo2 className="size-4" aria-hidden />
          Geri al
        </button>
      )}
      <button type="button" aria-label="Kapat" className="rounded p-1" onClick={() => dismiss(item.id)}>
        <X className="size-4" aria-hidden />
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
