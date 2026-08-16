import { useState } from 'react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { trash as trashApi } from '../api/endpoints';
import { useAsync, messageOf } from '../lib/useAsync';
import { count, dateTime } from '../lib/format';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';

/**
 * E7 — Çöp Kutusu: silinen liste ve ürünler, kalan gün, geri al / kalıcı sil.
 *
 * Kalıcı silme geri alınamaz; bu yüzden satır içinde açık bir onay ister.
 */
export default function TrashScreen() {
  const push = useToast((state) => state.push);
  const state = useAsync(() => trashApi.read(), []);
  const [confirming, setConfirming] = useState<string | null>(null);

  const restore = async (type: 'lists' | 'products', id: number) => {
    try {
      await trashApi.restore(type, id);
      state.reload();
      push(type === 'lists' ? 'Liste geri alındı.' : 'Ürün geri alındı.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  const purge = async (type: 'lists' | 'products', id: number) => {
    try {
      await trashApi.purge(type, id);
      setConfirming(null);
      state.reload();
      push('Kalıcı olarak silindi.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  if (state.loading) return <Skeleton rows={3} />;
  if (state.error) return <ErrorNote message={state.error} onRetry={state.reload} />;

  const data = state.data;
  const empty = !data || (data.lists.length === 0 && data.products.length === 0);

  return (
    <>
      <PageHeader
        title="Çöp Kutusu"
        subtitle={data ? `Silinen kayıtlar ${count(data.retention_days)} gün saklanır` : undefined}
      />

      {empty ? (
        <EmptyState title="Çöp kutusu boş" description="Sildiğin liste ve ürünler burada bekler, geri alabilirsin." />
      ) : (
        <div className="space-y-6">
          {data.lists.length > 0 && (
            <section>
              <h2 className="mb-2 text-sm font-semibold text-slate-700">Listeler</h2>
              <ul className="card divide-y divide-slate-100">
                {data.lists.map((entry) => (
                  <li key={`list-${entry.id}`} className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">{entry.name}</span>
                        <span className="block text-xs text-slate-500">
                          {dateTime(entry.deleted_at)} · {count(entry.days_left)} gün kaldı
                        </span>
                      </span>
                      <Actions
                        onRestore={() => void restore('lists', entry.id)}
                        onPurge={() => setConfirming(`lists-${entry.id}`)}
                      />
                    </div>
                    {confirming === `lists-${entry.id}` && (
                      <Confirm onCancel={() => setConfirming(null)} onConfirm={() => void purge('lists', entry.id)} />
                    )}
                  </li>
                ))}
              </ul>
            </section>
          )}

          {data.products.length > 0 && (
            <section>
              <h2 className="mb-2 text-sm font-semibold text-slate-700">Ürünler</h2>
              <ul className="card divide-y divide-slate-100">
                {data.products.map((entry) => (
                  <li key={`product-${entry.id}`} className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">{entry.name}</span>
                        <span className="block text-xs text-slate-500">
                          {entry.list_name}
                          {entry.list_deleted ? ' (listesi de silinmiş)' : ''} · {count(entry.days_left)} gün kaldı
                        </span>
                      </span>
                      <Actions
                        onRestore={() => void restore('products', entry.id)}
                        onPurge={() => setConfirming(`products-${entry.id}`)}
                      />
                    </div>
                    {entry.list_deleted && (
                      <p className="mt-2 text-xs text-amber-700">
                        Bu ürünü geri almak için önce listesini geri alman gerekir.
                      </p>
                    )}
                    {confirming === `products-${entry.id}` && (
                      <Confirm onCancel={() => setConfirming(null)} onConfirm={() => void purge('products', entry.id)} />
                    )}
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>
      )}
    </>
  );
}

function Actions({ onRestore, onPurge }: { onRestore: () => void; onPurge: () => void }) {
  return (
    <span className="flex shrink-0 gap-1">
      <button
        type="button"
        className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-brand-700"
        aria-label="Geri al"
        onClick={onRestore}
      >
        <RotateCcw className="h-4 w-4" aria-hidden />
      </button>
      <button
        type="button"
        className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-rose-600"
        aria-label="Kalıcı sil"
        onClick={onPurge}
      >
        <Trash2 className="h-4 w-4" aria-hidden />
      </button>
    </span>
  );
}

function Confirm({ onCancel, onConfirm }: { onCancel: () => void; onConfirm: () => void }) {
  return (
    <div className="mt-2 flex flex-col gap-2 rounded-xl bg-rose-50 p-3 text-sm text-rose-900 sm:flex-row sm:items-center sm:justify-between">
      <span>Kalıcı olarak silinecek, bu işlem geri alınamaz.</span>
      <span className="flex gap-2">
        <button type="button" className="btn-ghost" onClick={onCancel}>
          Vazgeç
        </button>
        <button type="button" className="btn-danger" onClick={onConfirm}>
          Kalıcı sil
        </button>
      </span>
    </div>
  );
}
