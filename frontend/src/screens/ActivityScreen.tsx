import { useUrlDurumu } from '../lib/useUrlDurumu';
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
  // İE#16 D1.4: süzgeç ve sayfa ADRESTE durur — link paylaşılabilir, geri tuşu çalışır.
  const [durum, setDurum] = useUrlDurumu({ tur: '', page: 1 });
  const entityType = durum.tur;
  const page = durum.page;

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
              entityType === filter.value ? 'bg-navy text-white' : 'border border-line bg-surface text-ink-2'
            }`}
            onClick={() => setDurum({ tur: filter.value, page: 1 })}
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
          <ul className="card divide-y divide-line-soft">
            {items.map((entry) => {
              const tur = actionIcon(entry.action, entry.entity_type);
              const Simge = icons[tur];
              const hedef = activityLink(entry);
              const govde = (
                <>
                  <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-xl ${
                      tur === 'uyari' ? 'bg-err-soft text-err' : 'bg-g100 text-ink-3'
                    }`}
                    aria-hidden="true"
                  >
                    <Simge size={17} />
                  </span>
                  <span className="min-w-0">
                    <span className="block font-medium">{actionLabel(entry.action)}</span>
                    <span className="block truncate text-xs text-ink-3">
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
                  <span className="shrink-0 text-xs text-ink-3">{dateTime(entry.created_at)}</span>
                </li>
              );
            })}
          </ul>

          <div className="mt-4 flex items-center justify-between">
            <button type="button" className="btn-ghost" disabled={page === 1} onClick={() => setDurum({ page: page - 1 })}>
              Önceki
            </button>
            <span className="text-sm text-ink-3">Sayfa {page}</span>
            <button
              type="button"
              className="btn-ghost"
              disabled={items.length < PAGE_SIZE}
              onClick={() => setDurum({ page: page + 1 })}
            >
              Sonraki
            </button>
          </div>
        </>
      )}
    </>
  );
}
