import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  Boxes,
  FileText,
  Inbox,
  KeyRound,
  ListChecks,
  Package,
  Server,
  Settings,
  Share2,
} from 'lucide-react';
import { activity as activityApi } from '../api/endpoints';
import { useAsync } from '../lib/useAsync';
import { dateTime } from '../lib/format';
import {
  actionIcon,
  actionLabel,
  activityFilters,
  activityLink,
  entityLabel,
  type ActivityIcon,
} from '../lib/activityLabels';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';

/** İE#14 A7: kayıt türü → simge. Uyarı kayıtları kırmızı, kalanı nötr. */
const icons: Record<ActivityIcon, typeof ListChecks> = {
  oturum: KeyRound,
  liste: ListChecks,
  urun: Package,
  kategori: Boxes,
  ayar: Settings,
  sistem: Server,
  belge: FileText,
  paylasim: Share2,
  gelen: Inbox,
  uyari: AlertTriangle,
};

/**
 * E9 — Aktivite: kim, ne, ne zaman.
 *
 * Sayfalama "daha fazla" ile ilerler: dönen kayıt sayısı sayfa boyutundan azsa
 * son sayfadayızdır (uç `meta` içinde toplamı da verir, burada sayfa sayısını
 * kullanıcıya göstermeye gerek yok).
 */
const PAGE_SIZE = 25;

export default function ActivityScreen() {
  const [entityType, setEntityType] = useState('');
  const [page, setPage] = useState(1);

  const state = useAsync(() => activityApi.read({ entity_type: entityType || undefined, page }), [entityType, page]);
  const items = state.data ?? [];

  return (
    <>
      <PageHeader title="Aktivite" subtitle="Sistemde yapılan işlemler" />

      <div className="mb-4 flex flex-wrap gap-2">
        {activityFilters.map((filter) => (
          <button
            key={filter.value}
            type="button"
            className={`min-h-11 rounded-xl px-3 text-sm font-semibold ${
              entityType === filter.value ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-600'
            }`}
            onClick={() => {
              setEntityType(filter.value);
              setPage(1);
            }}
          >
            {filter.label}
          </button>
        ))}
      </div>

      {state.loading ? (
        <Skeleton rows={4} />
      ) : state.error ? (
        <ErrorNote message={state.error} onRetry={state.reload} />
      ) : items.length === 0 ? (
        <EmptyState title="Kayıt yok" description="Bu süzgeçle eşleşen bir işlem bulunamadı." />
      ) : (
        <>
          <ul className="card divide-y divide-slate-100">
            {items.map((entry) => {
              const tur = actionIcon(entry.action, entry.entity_type);
              const Simge = icons[tur];
              const hedef = activityLink(entry);
              const govde = (
                <>
                  <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-xl ${
                      tur === 'uyari' ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500'
                    }`}
                    aria-hidden="true"
                  >
                    <Simge size={17} />
                  </span>
                  <span className="min-w-0">
                    <span className="block font-medium">{actionLabel(entry.action)}</span>
                    <span className="block truncate text-xs text-slate-500">
                      {entityLabel(entry.entity_type)}
                      {entry.detail ? ` · ${entry.detail}` : ''}
                    </span>
                  </span>
                </>
              );

              return (
                <li
                  key={entry.id}
                  className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  {hedef ? (
                    <Link to={hedef} className="flex min-w-0 items-center gap-3 hover:opacity-80">
                      {govde}
                    </Link>
                  ) : (
                    <span className="flex min-w-0 items-center gap-3">{govde}</span>
                  )}
                  <span className="shrink-0 text-xs text-slate-500">{dateTime(entry.created_at)}</span>
                </li>
              );
            })}
          </ul>

          <div className="mt-4 flex items-center justify-between">
            <button type="button" className="btn-ghost" disabled={page === 1} onClick={() => setPage((value) => value - 1)}>
              Önceki
            </button>
            <span className="text-sm text-slate-500">Sayfa {page}</span>
            <button
              type="button"
              className="btn-ghost"
              disabled={items.length < PAGE_SIZE}
              onClick={() => setPage((value) => value + 1)}
            >
              Sonraki
            </button>
          </div>
        </>
      )}
    </>
  );
}
