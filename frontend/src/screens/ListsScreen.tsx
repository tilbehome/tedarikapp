import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { Archive, ArrowRight, Copy, EyeOff, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { lists as listsApi } from '../api/endpoints';
import type { SupplyList, Visibility } from '../api/types';
import { useAsync, messageOf } from '../lib/useAsync';
import { useAramaSorgusu } from '../lib/useAramaSorgusu';
import { count, money } from '../lib/format';
import { visibilityLabels } from '../locales/tr';
import { EmptyState, ErrorNote, Field, ListStatusBadge, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';

/**
 * E3 — Listeler: Aktif/Pasif/Arşiv sekmeleri, dönem başlıkları, kart başına
 * ilerleme ve son export bilgisi; oluştur / kopyala / pasife al / arşivle.
 */

const tabs: Visibility[] = ['active', 'passive', 'archived'];

export default function ListsScreen() {
  const [visibility, setVisibility] = useState<Visibility>('active');
  // İE#19 E12: her tuşta istek YOK — 280 ms bekle, yeni istek eskisini iptal etsin.
  const arama = useAramaSorgusu();
  const [creating, setCreating] = useState(false);
  const push = useToast((state) => state.push);

  const state = useAsync(
    (signal) => listsApi.all({ visibility, q: arama.gecikmeli || undefined }, signal),
    [visibility, arama.gecikmeli],
  );
  const items = state.data ?? [];

  const act = async (label: string, action: () => Promise<unknown>, undo?: () => Promise<unknown>) => {
    try {
      await action();
      state.reload();
      push(label, 'success', undo ? async () => {
        try {
          await undo();
          state.reload();
        } catch (caught) {
          push(messageOf(caught), 'error');
        }
      } : undefined);
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  // Dönem başlıkları: aynı döneme ait listeler tek başlık altında toplanır.
  const groups = new Map<string, SupplyList[]>();
  for (const list of items) {
    const key = list.period ?? 'Dönemsiz';
    const bucket = groups.get(key);
    if (bucket) bucket.push(list);
    else groups.set(key, [list]);
  }

  return (
    <>
      <PageHeader
        title="Listeler"
        subtitle="Tedarik listelerin"
        actions={
          <button type="button" className="btn-primary" onClick={() => setCreating((value) => !value)}>
            <Plus className="h-4 w-4" aria-hidden />
            Yeni liste
          </button>
        }
      />

      {creating && (
        <CreateForm
          onCancel={() => setCreating(false)}
          onCreated={() => {
            setCreating(false);
            setVisibility('active');
            state.reload();
            push('Liste oluşturuldu.');
          }}
        />
      )}

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="flex overflow-hidden rounded-xl border border-line bg-surface" role="tablist">
          {tabs.map((tab) => (
            <button
              key={tab}
              type="button"
              role="tab"
              aria-selected={visibility === tab}
              className={`min-h-11 flex-1 px-4 text-sm font-semibold sm:flex-none ${
                visibility === tab ? 'bg-navy text-white' : 'text-ink-2 hover:bg-g50'
              }`}
              onClick={() => setVisibility(tab)}
            >
              {visibilityLabels[tab]}
            </button>
          ))}
        </div>

        <label className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" aria-hidden />
          <input
            className="field-input pl-9"
            placeholder="Liste veya tedarikçi ara"
            value={arama.deger}
            onChange={(event) => arama.yaz(event.target.value)}
            aria-label="Liste ara"
          />
        </label>
      </div>

      {state.loading ? (
        <Skeleton rows={3} />
      ) : state.error ? (
        <ErrorNote message={state.error} onRetry={state.reload} />
      ) : items.length === 0 ? (
        <EmptyState
          title={visibility === 'active' ? 'Henüz listen yok' : 'Bu sekmede liste yok'}
          description={
            visibility === 'active'
              ? 'Tedarik işini bir listeyle başlat; ürünleri sonra tek tek ekleyebilirsin.'
              : 'Listeleri pasife aldıkça veya arşivledikçe burada görünecekler.'
          }
          action={
            visibility === 'active' ? (
              <button type="button" className="btn-primary" onClick={() => setCreating(true)}>
                İlk listeni oluştur
              </button>
            ) : undefined
          }
        />
      ) : (
        <div className="space-y-6">
          {[...groups.entries()].map(([period, group]) => (
            <section key={period}>
              <h2 className="mb-2 text-sm font-semibold text-ink-3">{period}</h2>
              <ul className="space-y-3">
                {group.map((list) => (
                  <li key={list.id}>
                    <ListCard
                      list={list}
                      onDuplicate={() => act('Liste kopyalandı.', () => listsApi.duplicate(list.id))}
                      onVisibility={(next) =>
                        act(
                          next === 'archived' ? 'Liste arşivlendi.' : next === 'passive' ? 'Liste pasife alındı.' : 'Liste aktifleştirildi.',
                          () => listsApi.update(list.id, { visibility: next }),
                          () => listsApi.update(list.id, { visibility: list.visibility }),
                        )
                      }
                      onDelete={() =>
                        act('Liste çöp kutusuna atıldı.', () => listsApi.remove(list.id))
                      }
                    />
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>
      )}
    </>
  );
}

function ListCard({
  list,
  onDuplicate,
  onVisibility,
  onDelete,
}: {
  list: SupplyList;
  onDuplicate: () => void;
  onVisibility: (next: Visibility) => void;
  onDelete: () => void;
}) {
  const [confirming, setConfirming] = useState(false);

  return (
    <div className="card p-4">
      <div className="flex items-start gap-3">
        <Link to={`/listeler/${list.id}`} className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="truncate font-semibold">{list.name}</span>
            <ListStatusBadge status={list.status} />
            {list.is_export_stale && list.last_export && (
              <span className="badge bg-warn-soft text-warn ring-warn/20">Çıktı güncel değil</span>
            )}
          </div>
          <div className="mt-1 text-xs text-ink-3">
            {list.supplier_name ? `${list.supplier_name} · ` : ''}
            {count(list.product_count)} ürün · Toplam ₺{money(list.totals.yuan_tl)}
          </div>
        </Link>
        <ArrowRight className="mt-1 h-4 w-4 shrink-0 text-ink-3" aria-hidden />
      </div>

      <Progress list={list} />

      <div className="mt-3 flex flex-wrap gap-2">
        <button type="button" className="btn-ghost" onClick={onDuplicate}>
          <Copy className="h-4 w-4" aria-hidden />
          Kopyala
        </button>
        {list.visibility !== 'passive' && (
          <button type="button" className="btn-ghost" onClick={() => onVisibility('passive')}>
            <EyeOff className="h-4 w-4" aria-hidden />
            Pasife al
          </button>
        )}
        {list.visibility !== 'archived' && (
          <button type="button" className="btn-ghost" onClick={() => onVisibility('archived')}>
            <Archive className="h-4 w-4" aria-hidden />
            Arşivle
          </button>
        )}
        {list.visibility !== 'active' && (
          <button type="button" className="btn-ghost" onClick={() => onVisibility('active')}>
            <RotateCcw className="h-4 w-4" aria-hidden />
            Aktife al
          </button>
        )}
        <button type="button" className="btn-ghost text-err" onClick={() => setConfirming(true)}>
          <Trash2 className="h-4 w-4" aria-hidden />
          Sil
        </button>
      </div>

      {confirming && (
        <div className="mt-3 flex flex-col gap-2 rounded-xl bg-warn-soft p-3 text-sm text-warn sm:flex-row sm:items-center sm:justify-between">
          <span>Liste çöp kutusuna taşınacak. Emin misin?</span>
          <div className="flex gap-2">
            <button type="button" className="btn-ghost" onClick={() => setConfirming(false)}>
              Vazgeç
            </button>
            <button
              type="button"
              className="btn-danger"
              onClick={() => {
                setConfirming(false);
                onDelete();
              }}
            >
              Çöpe at
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

/** İlerleme çubuğu: hangi üründen kaç tane hangi durumda (docs/09 §1). */
function Progress({ list }: { list: SupplyList }) {
  const segments = [
    { key: 'received', tone: 'bg-ok', value: list.progress.received ?? 0 },
    { key: 'in_transit', tone: 'bg-info', value: list.progress.in_transit ?? 0 },
    { key: 'ordered', tone: 'bg-warn', value: list.progress.ordered ?? 0 },
    { key: 'to_order', tone: 'bg-g300', value: list.progress.to_order ?? 0 },
    { key: 'cancelled', tone: 'bg-err', value: list.progress.cancelled ?? 0 },
  ].filter((segment) => segment.value > 0);

  if (list.product_count === 0) {
    return <p className="mt-3 text-xs text-ink-3">Bu listede henüz ürün yok.</p>;
  }

  return (
    <div className="mt-3">
      <div className="flex h-2 overflow-hidden rounded-full bg-g100">
        {segments.map((segment) => (
          <span
            key={segment.key}
            className={segment.tone}
            // Genişlik yüzdesi ADET üzerinden hesaplanır; para değildir (K14 kapsamı dışı).
            style={{ width: `${(segment.value / list.product_count) * 100}%` }}
          />
        ))}
      </div>
      <div className="mt-1 text-xs text-ink-3">
        {count(list.progress.received ?? 0)} geldi · {count(list.progress.in_transit ?? 0)} yolda ·{' '}
        {count(list.progress.to_order ?? 0)} verilecek
      </div>
    </div>
  );
}

function CreateForm({ onCancel, onCreated }: { onCancel: () => void; onCreated: () => void }) {
  const [name, setName] = useState('');
  const [period, setPeriod] = useState('');
  const [supplier, setSupplier] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await listsApi.create({
        name: name.trim(),
        period: period.trim() || undefined,
        supplier_name: supplier.trim() || undefined,
      });
      onCreated();
    } catch (caught) {
      setError(messageOf(caught));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="card mb-4 space-y-3 p-4">
      <Field label="Liste adı">
        <input className="field-input" value={name} onChange={(event) => setName(event.target.value)} required autoFocus />
      </Field>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Dönem" hint="Örn. 2026 Sonbahar">
          <input className="field-input" value={period} onChange={(event) => setPeriod(event.target.value)} />
        </Field>
        <Field label="Tedarikçi">
          <input className="field-input" value={supplier} onChange={(event) => setSupplier(event.target.value)} />
        </Field>
      </div>
      {error && <p className="text-sm font-medium text-err">{error}</p>}
      <div className="flex gap-2">
        <button type="submit" className="btn-primary" disabled={busy}>
          {busy ? 'Oluşturuluyor…' : 'Oluştur'}
        </button>
        <button type="button" className="btn-ghost" onClick={onCancel}>
          Vazgeç
        </button>
      </div>
    </form>
  );
}
