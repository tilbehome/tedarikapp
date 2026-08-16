import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { Activity, ArrowRight, ListChecks, Plus, Truck } from 'lucide-react';
import { activity as activityApi, lists as listsApi } from '../api/endpoints';
import { useAsync } from '../lib/useAsync';
import { count, dateTime } from '../lib/format';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';
import { actionLabel } from '../lib/activityLabels';

/**
 * E2 — Ana Ekran: özet kartları, son aktiviteler, hızlı erişim.
 *
 * Karttaki sayılar ADET'tir (tam sayı), para değil; toplamlar liste detayında
 * backend'den gelir.
 */
export default function HomeScreen() {
  const listsState = useAsync(() => listsApi.all({ visibility: 'active' }), []);
  const activityState = useAsync(() => activityApi.read({ page: 1 }), []);

  const activeLists = listsState.data ?? [];
  const inTransit = activeLists.reduce((total, list) => total + (list.progress.in_transit ?? 0), 0);
  const toOrder = activeLists.reduce((total, list) => total + (list.progress.to_order ?? 0), 0);

  return (
    <>
      <PageHeader
        title="Ana Ekran"
        subtitle="Aktif işlerin özeti"
        actions={
          <Link to="/listeler" className="btn-primary">
            <Plus className="h-4 w-4" aria-hidden />
            Yeni liste
          </Link>
        }
      />

      {listsState.loading ? (
        <Skeleton rows={2} />
      ) : listsState.error ? (
        <ErrorNote message={listsState.error} onRetry={listsState.reload} />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <SummaryCard
              icon={<ListChecks className="h-5 w-5" aria-hidden />}
              label="Aktif liste"
              value={count(activeLists.length)}
              to="/listeler"
            />
            <SummaryCard
              icon={<Truck className="h-5 w-5" aria-hidden />}
              label="Yoldaki ürün"
              value={count(inTransit)}
              to="/listeler"
            />
            <SummaryCard
              icon={<Activity className="h-5 w-5" aria-hidden />}
              label="Verilecek ürün"
              value={count(toOrder)}
              to="/listeler"
            />
          </div>

          <section className="mt-6">
            <h2 className="mb-2 text-sm font-semibold text-slate-700">Aktif listeler</h2>
            {activeLists.length === 0 ? (
              <EmptyState
                title="Henüz listen yok"
                description="Tedarik işini bir listeyle başlat; ürünleri sonra tek tek ekleyebilirsin."
                action={
                  <Link to="/listeler" className="btn-primary">
                    İlk listeni oluştur
                  </Link>
                }
              />
            ) : (
              <ul className="space-y-2">
                {activeLists.slice(0, 5).map((list) => (
                  <li key={list.id}>
                    <Link to={`/listeler/${list.id}`} className="card flex items-center gap-3 p-4 hover:bg-slate-50">
                      <div className="min-w-0 flex-1">
                        <div className="truncate font-semibold">{list.name}</div>
                        <div className="text-xs text-slate-500">
                          {list.period ?? 'Dönemsiz'} · {count(list.product_count)} ürün
                        </div>
                      </div>
                      <ArrowRight className="h-4 w-4 shrink-0 text-slate-400" aria-hidden />
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}

      <section className="mt-6">
        <h2 className="mb-2 text-sm font-semibold text-slate-700">Son aktiviteler</h2>
        {activityState.loading ? (
          <Skeleton rows={2} />
        ) : activityState.error ? (
          <ErrorNote message={activityState.error} onRetry={activityState.reload} />
        ) : (activityState.data ?? []).length === 0 ? (
          <p className="text-sm text-slate-500">Henüz kayıt yok.</p>
        ) : (
          <ul className="card divide-y divide-slate-100">
            {(activityState.data ?? []).slice(0, 6).map((entry) => (
              <li key={entry.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <span className="min-w-0 flex-1 truncate">{actionLabel(entry.action)}</span>
                <span className="shrink-0 text-xs text-slate-500">{dateTime(entry.created_at)}</span>
              </li>
            ))}
          </ul>
        )}
        <Link to="/aktivite" className="mt-2 inline-flex text-sm font-medium text-brand-700 hover:underline">
          Tüm aktiviteler
        </Link>
      </section>
    </>
  );
}

function SummaryCard({
  icon,
  label,
  value,
  to,
}: {
  icon: ReactNode;
  label: string;
  value: string;
  to: string;
}) {
  return (
    <Link to={to} className="card flex items-center gap-3 p-4 hover:bg-slate-50">
      <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">{icon}</span>
      <span>
        <span className="block text-2xl font-bold leading-tight">{value}</span>
        <span className="block text-xs text-slate-500">{label}</span>
      </span>
    </Link>
  );
}
