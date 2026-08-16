import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { categories as categoriesApi } from '../api/endpoints';
import { useAsync, messageOf } from '../lib/useAsync';
import { count } from '../lib/format';
import { EmptyState, ErrorNote, Field, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';
import { useReference } from '../store/reference';

/**
 * Kategoriler — E8 Ayarlar'ın alt ekranı (docs/09 E8 "kategoriler" maddesi).
 *
 * Kullanımda olan kategori silinmeye çalışılırsa backend 409 döner; mesaj kaç
 * üründe kullanıldığını söyler ve kullanıcıya aynen gösterilir.
 */
export default function CategoriesScreen() {
  const push = useToast((state) => state.push);
  const refreshReference = useReference((state) => state.loadCategories);
  const state = useAsync(() => categoriesApi.all(), []);
  const items = state.data ?? [];

  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editingName, setEditingName] = useState('');

  const after = () => {
    state.reload();
    void refreshReference();
  };

  const create = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    try {
      await categoriesApi.create(name.trim());
      setName('');
      after();
      push('Kategori eklendi.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const rename = async (id: number) => {
    try {
      await categoriesApi.update(id, { name: editingName.trim() });
      setEditingId(null);
      after();
      push('Kategori güncellendi.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  const remove = async (id: number) => {
    try {
      await categoriesApi.remove(id);
      after();
      push('Kategori silindi.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  return (
    <>
      <Link to="/ayarlar" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="h-4 w-4" aria-hidden />
        Ayarlar
      </Link>

      <PageHeader title="Kategoriler" subtitle="Ürünleri gruplamak için" />

      <form onSubmit={(event) => void create(event)} className="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
        <div className="flex-1">
          <Field label="Yeni kategori">
            <input className="field-input" value={name} onChange={(event) => setName(event.target.value)} required />
          </Field>
        </div>
        <button type="submit" className="btn-primary" disabled={busy}>
          <Plus className="h-4 w-4" aria-hidden />
          Ekle
        </button>
      </form>

      {state.loading ? (
        <Skeleton rows={3} />
      ) : state.error ? (
        <ErrorNote message={state.error} onRetry={state.reload} />
      ) : items.length === 0 ? (
        <EmptyState title="Henüz kategori yok" description="İlk kategoriyi yukarıdaki alandan ekleyebilirsin." />
      ) : (
        <ul className="card divide-y divide-slate-100">
          {items.map((category) => (
            <li key={category.id} className="flex items-center gap-3 px-4 py-3">
              {editingId === category.id ? (
                <>
                  <input
                    className="field-input flex-1"
                    value={editingName}
                    onChange={(event) => setEditingName(event.target.value)}
                    aria-label="Kategori adı"
                    autoFocus
                  />
                  <button
                    type="button"
                    className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-emerald-700"
                    aria-label="Kaydet"
                    onClick={() => void rename(category.id)}
                  >
                    <Check className="h-4 w-4" aria-hidden />
                  </button>
                  <button
                    type="button"
                    className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-500"
                    aria-label="Vazgeç"
                    onClick={() => setEditingId(null)}
                  >
                    <X className="h-4 w-4" aria-hidden />
                  </button>
                </>
              ) : (
                <>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">{category.name}</span>
                    <span className="block text-xs text-slate-500">{count(category.product_count)} üründe kullanılıyor</span>
                  </span>
                  <button
                    type="button"
                    className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-500"
                    aria-label="Yeniden adlandır"
                    onClick={() => {
                      setEditingId(category.id);
                      setEditingName(category.name);
                    }}
                  >
                    <Pencil className="h-4 w-4" aria-hidden />
                  </button>
                  <button
                    type="button"
                    className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-rose-600"
                    aria-label="Sil"
                    onClick={() => void remove(category.id)}
                  >
                    <Trash2 className="h-4 w-4" aria-hidden />
                  </button>
                </>
              )}
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
