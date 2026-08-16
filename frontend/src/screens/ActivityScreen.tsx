import { useState } from 'react';
import { activity as activityApi } from '../api/endpoints';
import { useAsync } from '../lib/useAsync';
import { dateTime } from '../lib/format';
import { actionLabel, activityFilters, entityLabel } from '../lib/activityLabels';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';

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
            {items.map((entry) => (
              <li key={entry.id} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <span className="min-w-0">
                  <span className="block font-medium">{actionLabel(entry.action)}</span>
                  <span className="block truncate text-xs text-slate-500">
                    {entityLabel(entry.entity_type)}
                    {entry.detail ? ` · ${entry.detail}` : ''}
                  </span>
                </span>
                <span className="shrink-0 text-xs text-slate-500">{dateTime(entry.created_at)}</span>
              </li>
            ))}
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
