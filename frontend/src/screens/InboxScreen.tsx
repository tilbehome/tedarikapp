import { useState } from 'react';
import { inbox as inboxApi, lists as listsApi } from '../api/endpoints';
import { useAsync, messageOf } from '../lib/useAsync';
import { count } from '../lib/format';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';
import InboxRow from './inbox/InboxRow';
import InboxDetailDrawer from './inbox/InboxDetailDrawer';

/**
 * E3 — Gelen Kutusu v2 (İE#13 Blok B).
 *
 * Kayıtlar üründen ÖNCEKİ ham hâldir: tek tek veya toplu "listeye taşı" ile ürüne
 * dönüşür (K25 mükerrer uyarısı yakalama anında verilmişti — taşıma bilinçli seçimdir).
 *
 * SİLME UYARISI: Gelen Kutusu kaydı çöp kutusuna GİRMEZ (docs/10, İE#11) — ham
 * yakalama verisidir. Bu yüzden hem tekil hem toplu silme onay ister.
 */
export default function InboxScreen() {
  const push = useToast((state) => state.push);

  const [q, setQ] = useState('');
  const [aramaMetni, setAramaMetni] = useState('');
  const [platform, setPlatform] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);

  const state = useAsync(() => inboxApi.queue({ q, platform, from, to, page }), [q, platform, from, to, page]);
  const listsState = useAsync(() => listsApi.all({ visibility: 'active' }), []);

  const [selected, setSelected] = useState<number[]>([]);
  const [adlar, setAdlar] = useState<Record<number, string>>({});
  const [targetList, setTargetList] = useState<number | '' | 'YENI'>('');
  const [yeniListeAdi, setYeniListeAdi] = useState('');
  const [acikDetay, setAcikDetay] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);

  const items = state.data?.data ?? [];
  const meta = state.data?.meta ?? {};
  const total = Number(meta.total ?? items.length);
  const perPage = Number(meta.per_page ?? 20);
  const platformlar = Array.isArray(meta.platforms) ? (meta.platforms as string[]) : [];
  const sonSayfa = Math.max(1, Math.ceil(total / perPage));

  const toggle = (id: number) =>
    setSelected((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]));

  const tumunuSec = () => setSelected(selected.length === items.length ? [] : items.map((item) => item.id));

  const araNoktala = (event: React.FormEvent) => {
    event.preventDefault();
    setPage(1);
    setQ(aramaMetni.trim());
  };

  const filtreleriTemizle = () => {
    setAramaMetni('');
    setQ('');
    setPlatform('');
    setFrom('');
    setTo('');
    setPage(1);
  };

  /** Hedef listeyi çözer; "+ Yeni liste oluştur…" seçiliyse önce listeyi AÇAR (B2). */
  const hedefListeyiCoz = async (): Promise<number | null> => {
    if (targetList === 'YENI') {
      const ad = yeniListeAdi.trim();
      if (ad === '') {
        push('Yeni liste için ad girin.', 'error');
        return null;
      }
      const liste = await listsApi.create({ name: ad });
      listsState.reload();
      setTargetList(liste.id);
      setYeniListeAdi('');
      return liste.id;
    }
    if (targetList === '') {
      push('Önce hedef liste seçin.', 'error');
      return null;
    }

    return targetList;
  };

  const assign = async (ids: number[]) => {
    setBusy(true);
    try {
      const listId = await hedefListeyiCoz();
      if (listId === null) return;

      const secilenAdlar = Object.fromEntries(
        Object.entries(adlar).filter(([id]) => ids.includes(Number(id))),
      ) as Record<number, string>;

      const result = await inboxApi.assign(ids, listId, secilenAdlar);
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

  const remove = async (ids: number[]) => {
    const soru =
      ids.length === 1
        ? 'Bu yakalama kaydı KALICI olarak silinsin mi? (Gelen Kutusu kaydı çöp kutusuna gitmez.)'
        : `Seçili ${ids.length} yakalama kaydı KALICI olarak silinsin mi? (Gelen Kutusu kaydı çöp kutusuna gitmez.)`;
    if (!window.confirm(soru)) return;

    setBusy(true);
    try {
      if (ids.length === 1) {
        await inboxApi.remove(ids[0] as number);
        push('Kayıt silindi.');
      } else {
        const sonuc = await inboxApi.removeMany(ids);
        push(`${count(sonuc.deleted)} kayıt silindi.`);
      }
      setSelected([]);
      state.reload();
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const filtreVar = q !== '' || platform !== '' || from !== '' || to !== '';

  return (
    <>
      <PageHeader title="Gelen Kutusu" subtitle="Eklentiden yakalanan ürünler — listeye taşıyın" />

      <div className="card mb-4 space-y-3 p-3">
        <div className="flex flex-wrap items-end gap-2">
          <form className="flex items-end gap-2" onSubmit={araNoktala}>
            <label className="text-xs text-slate-500">
              Ara (başlıkta)
              <input
                type="search"
                className="field-input mt-1 w-48"
                value={aramaMetni}
                onChange={(event) => setAramaMetni(event.target.value)}
                placeholder="ürün adı…"
              />
            </label>
            <button type="submit" className="btn-ghost">
              Ara
            </button>
          </form>

          <label className="text-xs text-slate-500">
            Platform
            <select
              className="field-input mt-1 w-36"
              value={platform}
              onChange={(event) => {
                setPage(1);
                setPlatform(event.target.value);
              }}
            >
              <option value="">Tümü</option>
              {platformlar.map((name) => (
                <option key={name} value={name}>
                  {name}
                </option>
              ))}
            </select>
          </label>

          <label className="text-xs text-slate-500">
            Başlangıç
            <input
              type="date"
              className="field-input mt-1"
              value={from}
              onChange={(event) => {
                setPage(1);
                setFrom(event.target.value);
              }}
            />
          </label>
          <label className="text-xs text-slate-500">
            Bitiş
            <input
              type="date"
              className="field-input mt-1"
              value={to}
              onChange={(event) => {
                setPage(1);
                setTo(event.target.value);
              }}
            />
          </label>

          {filtreVar ? (
            <button type="button" className="btn-ghost" onClick={filtreleriTemizle}>
              Filtreleri temizle
            </button>
          ) : null}
        </div>

        <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-sm">
          <label className="flex items-center gap-2 text-slate-600">
            <input
              type="checkbox"
              checked={items.length > 0 && selected.length === items.length}
              onChange={tumunuSec}
              aria-label="Tümünü seç"
            />
            Tümünü seç
          </label>

          <span className="text-slate-600">Hedef liste:</span>
          <select
            className="field-input max-w-56"
            value={targetList}
            onChange={(event) => {
              const value = event.target.value;
              setTargetList(value === '' ? '' : value === 'YENI' ? 'YENI' : Number(value));
            }}
          >
            <option value="">Seçin…</option>
            {(listsState.data ?? []).map((list) => (
              <option key={list.id} value={list.id}>
                {list.name}
              </option>
            ))}
            <option value="YENI">+ Yeni liste oluştur…</option>
          </select>

          {targetList === 'YENI' ? (
            <input
              type="text"
              className="field-input w-48"
              value={yeniListeAdi}
              onChange={(event) => setYeniListeAdi(event.target.value)}
              placeholder="Yeni liste adı"
            />
          ) : null}

          <button
            type="button"
            className="btn-primary"
            disabled={busy || selected.length === 0}
            onClick={() => void assign(selected)}
          >
            Seçilenleri taşı ({selected.length})
          </button>
          <button
            type="button"
            className="btn-ghost text-red-600"
            disabled={busy || selected.length === 0}
            onClick={() => void remove(selected)}
          >
            Seçilenleri sil
          </button>
        </div>
      </div>

      {state.loading ? (
        <Skeleton rows={3} />
      ) : state.error ? (
        <ErrorNote message={state.error} onRetry={state.reload} />
      ) : items.length === 0 ? (
        <EmptyState
          title={filtreVar ? 'Filtreye uyan kayıt yok' : 'Gelen kutusu boş'}
          description={
            filtreVar
              ? 'Arama veya tarih aralığını genişletin.'
              : "1688'de ürün sayfasında eklentinin 'Panele Gönder' düğmesine bastığınızda ürünler burada birikir."
          }
        />
      ) : (
        <>
          <ul className="space-y-3">
            {items.map((item) => (
              <InboxRow
                key={item.id}
                item={item}
                secili={selected.includes(item.id)}
                onSec={() => toggle(item.id)}
                onAc={() => setAcikDetay(item.id)}
                onTasi={() => void assign([item.id])}
                onSil={() => void remove([item.id])}
                secilenAd={adlar[item.id]}
                onAdSec={(ad) => setAdlar((current) => ({ ...current, [item.id]: ad }))}
                busy={busy}
              />
            ))}
          </ul>

          <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
            <span>
              {count(total)} kayıt · sayfa {page}/{sonSayfa}
            </span>
            <div className="flex gap-2">
              <button type="button" className="btn-ghost" disabled={page === 1} onClick={() => setPage((v) => v - 1)}>
                Önceki
              </button>
              <button type="button" className="btn-ghost" disabled={page >= sonSayfa} onClick={() => setPage((v) => v + 1)}>
                Sonraki
              </button>
            </div>
          </div>
        </>
      )}

      {acikDetay !== null ? <InboxDetailDrawer id={acikDetay} onClose={() => setAcikDetay(null)} /> : null}
    </>
  );
}
