import type { ReactNode } from 'react';
import { AlertTriangle, Inbox, Loader2 } from 'lucide-react';
import { listStatusLabels, listStatusTone, productStatusLabels, productStatusTone } from '../../locales/tr';
import type { ListStatus, ProductStatus } from '../../api/types';

/**
 * TEMEL PARÇALAR (İE#16 D1.3 — bileşen kitaplığı).
 *
 * Hepsi TOKEN'lardan beslenir: burada sabit renk (Tailwind'in slate/rose gibi
 * hazır tonları) YOKTUR, bu yüzden koyu tema ek CSS olmadan çalışır.
 */

export function StatusBadge({ status }: { status: ProductStatus }) {
  return <span className={`badge ${productStatusTone[status]}`}>{productStatusLabels[status]}</span>;
}

export function ListStatusBadge({ status }: { status: ListStatus }) {
  return <span className={`badge ${listStatusTone[status]}`}>{listStatusLabels[status]}</span>;
}

/** Henüz açılmamış özellikler için pasif rozet. */
export function SoonBadge({ children = 'Yakında' }: { children?: ReactNode }) {
  return <span className="badge bg-g100 text-ink-3 ring-line">{children}</span>;
}

/** Çıkarılabilir çip — seçili filtreler (Keşif havuzu, Dilim 4). */
export function Chip({ children, onRemove }: { children: ReactNode; onRemove?: () => void }) {
  return (
    <span className="chip">
      {children}
      {onRemove && (
        <button
          type="button"
          className="-mr-1 flex size-4 items-center justify-center rounded-full text-ink-3 hover:bg-g100 hover:text-ink"
          onClick={onRemove}
          aria-label="Filtreyi kaldır"
        >
          ×
        </button>
      )}
    </span>
  );
}

export function Spinner({ label = 'Yükleniyor…' }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-2 py-10 text-ink-3" role="status">
      <Loader2 className="size-5 animate-spin" aria-hidden />
      <span className="text-md">{label}</span>
    </div>
  );
}

/** İskelet yükleme — boş beyaz sayfa bırakılmaz. */
export function Skeleton({ rows = 3 }: { rows?: number }) {
  return (
    <div className="space-y-3" aria-hidden>
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="card animate-pulse p-4">
          <div className="h-4 w-1/3 rounded bg-g200" />
          <div className="mt-3 h-3 w-2/3 rounded bg-g100" />
        </div>
      ))}
    </div>
  );
}

export function ErrorNote({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="card flex flex-col gap-3 border-err/30 bg-err-soft p-4 text-md text-err sm:flex-row sm:items-center sm:justify-between">
      <span className="flex items-start gap-2">
        <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
        {message}
      </span>
      {onRetry && (
        <button type="button" className="btn-ghost btn-sm" onClick={onRetry}>
          Yeniden dene
        </button>
      )}
    </div>
  );
}

/** Boş durum — yönlendirici mesaj + aksiyon. */
export function EmptyState({
  title,
  description,
  action,
  icon,
}: {
  title: string;
  description: string;
  action?: ReactNode;
  icon?: ReactNode;
}) {
  return (
    <div className="card flex flex-col items-center gap-3 p-10 text-center">
      {icon ?? <Inbox className="size-10 text-g300" aria-hidden />}
      <h3 className="text-lg font-semibold text-ink">{title}</h3>
      <p className="max-w-sm text-md text-ink-2">{description}</p>
      {action}
    </div>
  );
}

export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: string;
  subtitle?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <header className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 className="text-xl font-bold tracking-tight text-ink">{title}</h1>
        {subtitle && <div className="mt-0.5 text-md text-ink-2">{subtitle}</div>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </header>
  );
}

export function Field({
  label,
  hint,
  error,
  children,
}: {
  label: string;
  hint?: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <label className="block">
      <span className="field-label">{label}</span>
      {children}
      {hint && !error && <span className="mt-1 block text-sm text-ink-3">{hint}</span>}
      {/* Form hataları METİNLE bildirilir (renk tek başına anlam taşımaz). */}
      {error && <span className="mt-1 block text-sm font-medium text-err">{error}</span>}
    </label>
  );
}

/** Yıkıcı işlemler onay ister. */
export function ConfirmBar({
  question,
  confirmLabel,
  onConfirm,
  onCancel,
  busy,
}: {
  question: string;
  confirmLabel: string;
  onConfirm: () => void;
  onCancel: () => void;
  busy?: boolean;
}) {
  return (
    <div className="card flex flex-col gap-3 border-warn/30 bg-warn-soft p-4 text-md sm:flex-row sm:items-center sm:justify-between">
      <span className="text-warn">{question}</span>
      <div className="flex gap-2">
        <button type="button" className="btn-ghost btn-sm" onClick={onCancel} disabled={busy}>
          Vazgeç
        </button>
        <button type="button" className="btn-danger btn-sm" onClick={onConfirm} disabled={busy}>
          {confirmLabel}
        </button>
      </div>
    </div>
  );
}
