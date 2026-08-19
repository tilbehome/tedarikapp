import { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { inbox as inboxApi, lists as listsApi } from '../api/endpoints';
import { useAsync, messageOf } from '../lib/useAsync';
import { count, dateTime } from '../lib/format';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';

/**
 * E3 — Gelen Kutusu (İE#11 Görev D): eklenti yakalamalarının kuyruğu.
 *
 * Kayıtlar üründen ÖNCEKİ ham hâldir: tek tek veya toplu "listeye taşı" ile ürüne
 * dönüşür (K25 mükerrer uyarısı yakalama anında verilmişti — taşıma bilinçli seçimdir).
 * `error` kayıtları da görünür: eksik alan taşıma sırasında net mesajla söylenir.
 */
export default function InboxScreen() {
  const push = useToast((state) => state.push);
  const state = useAsync(() => inboxApi.queue(), []);
  const listsState = useAsync(() => listsApi.all({ visibility: 'active' }), []);
  const [selected, setSelected] = useState<number[]>([]);
  const [targetList, setTargetList] = useState<number | ''>('');
  const [busy, setBusy] = useState(false);

  const toggle = (id: number) =>
    setSelected((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]));

  const assign = async (ids: number[]) => {
    if (targetList === '') {
      push('Önce hedef liste seçin.', 'error');
      return;
    }
    setBusy(true);
    try {
      const result = await inboxApi.assign(ids, targetList);
      state.reload();
      setSelected([]);
      push(
        result.failed.length === 0
          ? `${count(result.moved)} ürün listeye taşındı.`
          : `${count(result.moved)} taşındı; ${count(result.failed.length)} kayıt taşınamadı (${result.failed[0]?.error ?? ''}).`,
        result.failed.length === 0 ? 'success' : 'error',
      );
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const remove = async (id: number) => {
    try {
      await inboxApi.remove(id);
      state.reload();
      push('Kayıt silindi.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  const items = state.data ?? [];

  return (
    <>
      <PageHeader title="Gelen Kutusu" subtitle="Eklentiden yakalanan ürünler — listeye taşıyın" />

      {state.loading ? (
        <Skeleton rows={3} />
      ) : state.error ? (
        <ErrorNote message={state.error} onRetry={state.reload} />
      ) : items.length === 0 ? (
        <EmptyState
          title="Gelen kutusu boş"
          description="1688'de ürün sayfasında eklentinin 'Panele Gönder' düğmesine bastığınızda ürünler burada birikir."
        />
      ) : (
        <>
          <div className="card mb-4 flex flex-wrap items-center gap-2 p-3 text-sm">
            <span className="text-slate-600">Hedef liste:</span>
            <select
              className="field-input max-w-56"
              value={targetList}
              onChange={(event) => setTargetList(event.target.value === '' ? '' : Number(event.target.value))}
            >
              <option value="">Seçin…</option>
              {(listsState.data ?? []).map((list) => (
                <option key={list.id} value={list.id}>
                  {list.name}
                </option>
              ))}
            </select>
            <button
              type="button"
              className="btn-primary"
              disabled={busy || selected.length === 0}
              onClick={() => void assign(selected)}
            >
              Seçilenleri taşı ({selected.length})
            </button>
          </div>

          <ul className="space-y-3">
            {items.map((item) => (
              <li key={item.id} className="card flex items-center gap-3 p-3">
                <input
                  type="checkbox"
                  checked={selected.includes(item.id)}
                  onChange={() => toggle(item.id)}
                  aria-label="Seç"
                />
                {item.image_url ? (
                  <img src={item.image_url} alt="" loading="lazy" className="h-14 w-14 shrink-0 rounded-xl border border-slate-200 object-cover" />
                ) : (
                  <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-400">—</span>
                )}
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium">{item.name ?? '(adsız yakalama)'}</p>
                  <p className="text-xs text-slate-500">
                    {item.platform}
                    {item.price_yuan ? ` · ¥${item.price_yuan}` : ''} · {dateTime(item.created_at)}
                    {item.url ? (
                      <>
                        {' · '}
                        <a className="text-brand-600" href={item.url} target="_blank" rel="noreferrer">kaynak</a>
                      </>
                    ) : null}
                  </p>
                  {item.status === 'error' ? (
                    <p className="mt-1 text-xs text-amber-700">Eksik veri: {item.error_note ?? 'doğrulanamadı'} — taşınırsa yeniden denetlenir.</p>
                  ) : null}
                </div>
                <button type="button" className="btn-ghost" disabled={busy} onClick={() => void assign([item.id])}>
                  Taşı
                </button>
                <button type="button" className="btn-ghost text-red-600" onClick={() => void remove(item.id)} aria-label="Sil">
                  <Trash2 className="h-4 w-4" aria-hidden />
                </button>
              </li>
            ))}
          </ul>
        </>
      )}
    </>
  );
}
