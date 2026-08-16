import type { ReactNode } from 'react';
import { AlertTriangle, Inbox, Loader2 } from 'lucide-react';
import { listStatusLabels, listStatusTone, productStatusLabels, productStatusTone } from '../locales/tr';
import type { ListStatus, ProductStatus } from '../api/types';

/** Ortak, küçük arayüz parçaları. Ekranlar bunları birleştirir. */

export function StatusBadge({ status }: { status: ProductStatus }) {
  return <span className={`badge ${productStatusTone[status]}`}>{productStatusLabels[status]}</span>;
}

export function ListStatusBadge({ status }: { status: ListStatus }) {
  return <span className={`badge ${listStatusTone[status]}`}>{listStatusLabels[status]}</span>;
}

/** "Faz 2" gibi henüz açılmamış özellikler için pasif rozet (İE#8 §2). */
export function SoonBadge({ children = 'Faz 2' }: { children?: ReactNode }) {
  return <span className="badge bg-slate-100 text-slate-500 ring-slate-200">{children}</span>;
}

export function Spinner({ label = 'Yükleniyor…' }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-2 py-10 text-slate-500" role="status">
      <Loader2 className="h-5 w-5 animate-spin" aria-hidden />
      <span className="text-sm">{label}</span>
    </div>
  );
}

/** İskelet yükleme — boş beyaz sayfa bırakılmaz (docs/09 §5). */
export function Skeleton({ rows = 3 }: { rows?: number }) {
  return (
    <div className="space-y-3" aria-hidden>
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="card animate-pulse p-4">
          <div className="h-4 w-1/3 rounded bg-slate-200" />
          <div className="mt-3 h-3 w-2/3 rounded bg-slate-100" />
        </div>
      ))}
    </div>
  );
}

export function ErrorNote({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="card flex flex-col gap-3 border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 sm:flex-row sm:items-center sm:justify-between">
      <span className="flex items-start gap-2">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
        {message}
      </span>
      {onRetry && (
        <button type="button" className="btn-ghost" onClick={onRetry}>
          Yeniden dene
        </button>
      )}
    </div>
  );
}

/** Boş durum — yönlendirici mesaj + aksiyon (docs/09 §5). */
export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <div className="card flex flex-col items-center gap-3 p-10 text-center">
      <Inbox className="h-10 w-10 text-slate-300" aria-hidden />
      <h3 className="text-base font-semibold text-slate-800">{title}</h3>
      <p className="max-w-sm text-sm text-slate-500">{description}</p>
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
        <h1 className="text-xl font-bold tracking-tight text-slate-900">{title}</h1>
        {subtitle && <div className="mt-0.5 text-sm text-slate-500">{subtitle}</div>}
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
      {hint && !error && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
      {/* Form hataları METİNLE bildirilir (docs/09 erişilebilirlik). */}
      {error && <span className="mt-1 block text-xs font-medium text-rose-700">{error}</span>}
    </label>
  );
}

/** Yıkıcı işlemler onay ister (docs/09 §1 affedicilik). */
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
    <div className="card flex flex-col gap-3 border-amber-200 bg-amber-50 p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
      <span className="text-amber-900">{question}</span>
      <div className="flex gap-2">
        <button type="button" className="btn-ghost" onClick={onCancel} disabled={busy}>
          Vazgeç
        </button>
        <button type="button" className="btn-danger" onClick={onConfirm} disabled={busy}>
          {confirmLabel}
        </button>
      </div>
    </div>
  );
}
