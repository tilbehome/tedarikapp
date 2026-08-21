import { useEffect } from 'react';
import { ExternalLink, X } from 'lucide-react';
import { inbox as inboxApi, type InboxDetail } from '../../api/endpoints';
import { useAsync } from '../../lib/useAsync';
import { dateTime } from '../../lib/format';
import { ErrorNote, Skeleton } from '../../components/ui';
import InboxThumb from './InboxThumb';

/**
 * E3 detay çekmecesi (İE#13 B3): yakalanan ham veriyi okunur hâlde gösterir —
 * büyük görseller, fiyat kademeleri, varyasyonlar, özellikler, kaynak linki.
 *
 * Pazarlama metni YOKTUR: burada yalnız yakalanan gerçek veri durur.
 */
export default function InboxDetailDrawer({ id, onClose }: { id: number; onClose: () => void }) {
  const state = useAsync<InboxDetail>(() => inboxApi.detail(id), [id]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const detail = state.data;

  return (
    <div className="fixed inset-0 z-40 flex justify-end" role="dialog" aria-modal="true" aria-label="Yakalama detayı">
      <button type="button" className="absolute inset-0 bg-g900/40" aria-label="Kapat" onClick={onClose} />

      <aside className="relative z-10 flex h-full w-full max-w-lg flex-col overflow-y-auto bg-surface shadow-2xl">
        <header className="sticky top-0 flex items-start justify-between gap-3 border-b border-line-soft bg-surface p-4">
          <div className="min-w-0">
            <p className="text-xs uppercase tracking-wide text-ink-3">Yakalama detayı</p>
            <h2 className="truncate text-base font-semibold">{detail?.name ?? '…'}</h2>
          </div>
          <button type="button" className="btn-ghost" onClick={onClose} aria-label="Kapat">
            <X className="h-4 w-4" aria-hidden />
          </button>
        </header>

        <div className="space-y-5 p-4">
          {state.loading ? (
            <Skeleton rows={4} />
          ) : state.error ? (
            <ErrorNote message={state.error} onRetry={state.reload} />
          ) : detail ? (
            <>
              {detail.images.length > 0 ? (
                <div className="grid grid-cols-3 gap-2">
                  {detail.images.slice(0, 9).map((src) => (
                    <InboxThumb key={src} src={src} className="aspect-square w-full rounded-xl" />
                  ))}
                </div>
              ) : null}

              <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <dt className="text-ink-3">Platform</dt>
                <dd>{detail.platform}</dd>
                <dt className="text-ink-3">Ürün no</dt>
                <dd className="truncate">{detail.external_id ?? '—'}</dd>
                <dt className="text-ink-3">Satıcı</dt>
                <dd className="truncate">{detail.seller_name ?? '—'}</dd>
                <dt className="text-ink-3">Yakalanma</dt>
                <dd>{dateTime(detail.created_at)}</dd>
              </dl>

              {detail.raw_title ? (
                <section>
                  <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-3">Orijinal başlık</h3>
                  {/* K54: orijinal (Çince) başlık her koşulda korunur ve burada görünür. */}
                  <p className="text-sm text-ink-2">{detail.raw_title}</p>
                </section>
              ) : null}

              {detail.price_tiers.length > 0 ? (
                <section>
                  <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-3">Fiyat kademeleri</h3>
                  <ul className="text-sm">
                    {detail.price_tiers.map((tier) => (
                      <li key={`${tier.min_qty}-${tier.price_yuan}`} className="flex justify-between border-b border-line-soft py-1">
                        <span className="text-ink-2">{tier.min_qty}+ adet</span>
                        <span className="font-medium">¥{tier.price_yuan}</span>
                      </li>
                    ))}
                  </ul>
                </section>
              ) : null}

              {detail.sku_matrix.length > 0 ? (
                <section>
                  <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-3">
                    Varyasyonlar ({detail.sku_matrix.length})
                  </h3>
                  <ul className="text-sm">
                    {detail.sku_matrix.map((sku, index) => (
                      <li key={`${sku.label}-${index}`} className="flex justify-between gap-3 border-b border-line-soft py-1">
                        <span className="min-w-0 truncate text-ink-2">{sku.label}</span>
                        <span className="shrink-0 font-medium">{sku.price_yuan ? `¥${sku.price_yuan}` : '—'}</span>
                      </li>
                    ))}
                  </ul>
                </section>
              ) : null}

              {Object.keys(detail.attributes).length > 0 ? (
                <section>
                  <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-3">Yakalanan özellikler</h3>
                  <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    {Object.entries(detail.attributes).map(([key, value]) => (
                      <div key={key} className="contents">
                        <dt className="truncate text-ink-3">{key}</dt>
                        <dd className="truncate">{value}</dd>
                      </div>
                    ))}
                  </dl>
                </section>
              ) : null}

              {detail.url ? (
                <a className="btn-ghost inline-flex items-center gap-1" href={detail.url} target="_blank" rel="noreferrer">
                  Kaynak sayfaya git <ExternalLink className="h-3.5 w-3.5" aria-hidden />
                </a>
              ) : null}
            </>
          ) : null}
        </div>
      </aside>
    </div>
  );
}
