import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, Download, FileSpreadsheet, ImageOff, Plus, Search, Share2, Trash2 } from 'lucide-react';
import { exports as exportsApi, lists as listsApi, products as productsApi, share as shareApi } from '../api/endpoints';
import type { ListStatus, Product, ProductStatus } from '../api/types';
import { useAsync, messageOf } from '../lib/useAsync';
import { count, dateTime, money, rate } from '../lib/format';
import { listStatusLabels, productStatusLabels } from '../locales/tr';
import { EmptyState, ErrorNote, ListStatusBadge, PageHeader, Skeleton } from '../components/ui';
import StatusMenu from '../components/StatusMenu';
import { useReference } from '../store/reference';
import { useToast } from '../components/Toast';

/**
 * E4 — Liste Detayı.
 *
 * Telefonda ürünler kart, masaüstünde tablo (docs/09 §5). TOPLAM satırı
 * backend'in `totals` alanından gelir — panel hiçbir toplama yapmaz (K14/K29).
 * Export ve Paylaşım butonları görünür ama Faz 2'ye kadar pasiftir.
 */

type SortKey = 'sort_no' | 'name' | 'qty' | 'price_yuan' | 'line_total_yuan_tl' | 'status';

/** Sayı duyarlı dize karşılaştırması — aritmetik DEĞİL, sıralama karşılaştırmasıdır. */
const numericCollator = new Intl.Collator('tr', { numeric: true });

export default function ListDetailScreen() {
  const { id } = useParams();
  const listId = Number(id);
  const push = useToast((state) => state.push);
  const categoryName = useReference((state) => state.categoryName);
  const machine = useReference((state) => state.machine);

  const [shareOpen, setShareOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<ProductStatus | ''>('');
  const [sort, setSort] = useState<{ key: SortKey; asc: boolean }>({ key: 'sort_no', asc: true });
  const [selected, setSelected] = useState<number[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);

  const listState = useAsync(() => listsApi.find(listId), [listId]);
  const productState = useAsync(
    () => productsApi.forList(listId, { q: query || undefined, status: statusFilter || undefined }),
    [listId, query, statusFilter],
  );

  const list = listState.data;
  const items = useMemo(() => {
    const rows = [...(productState.data ?? [])];
    const direction = sort.asc ? 1 : -1;
    rows.sort((a, b) => {
      switch (sort.key) {
        case 'name':
          return direction * a.name.localeCompare(b.name, 'tr');
        case 'qty':
          return direction * numericCollator.compare(String(a.qty), String(b.qty));
        case 'price_yuan':
          return direction * numericCollator.compare(a.price_yuan, b.price_yuan);
        case 'line_total_yuan_tl':
          return direction * numericCollator.compare(a.line_total_yuan_tl, b.line_total_yuan_tl);
        case 'status':
          return direction * productStatusLabels[a.status].localeCompare(productStatusLabels[b.status], 'tr');
        default:
          return direction * numericCollator.compare(String(a.sort_no), String(b.sort_no));
      }
    });
    return rows;
  }, [productState.data, sort]);

  const refresh = () => {
    listState.reload();
    productState.reload();
  };

  const changeStatus = async (product: Product, next: ProductStatus) => {
    setBusyId(product.id);
    try {
      await productsApi.setStatus(product.id, next);
      refresh();
      push(`"${product.name}" → ${productStatusLabels[next]}`);
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusyId(null);
    }
  };

  const removeProduct = async (product: Product) => {
    try {
      await productsApi.remove(product.id);
      refresh();
      push('Ürün çöp kutusuna atıldı.', 'success');
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  const bulkStatus = async (next: ProductStatus) => {
    try {
      const result = await productsApi.bulk({ ids: selected, action: 'status', status: next });
      setSelected([]);
      refresh();
      push(
        result.failed.length === 0
          ? `${count(result.updated)} ürün güncellendi.`
          : `${count(result.updated)} ürün güncellendi, ${count(result.failed.length)} ürün bu geçişe uygun değildi.`,
        result.failed.length === 0 ? 'success' : 'error',
      );
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  const changeListStatus = async (next: ListStatus) => {
    try {
      await listsApi.update(listId, { status: next });
      refresh();
      push(`Liste durumu: ${listStatusLabels[next]}`);
    } catch (caught) {
      push(messageOf(caught), 'error');
    }
  };

  if (listState.loading) return <Skeleton rows={4} />;
  if (listState.error) return <ErrorNote message={listState.error} onRetry={listState.reload} />;
  if (!list) return <ErrorNote message="Liste bulunamadı." />;

  const allowedListStatuses = machine?.list[list.status] ?? [];

  return (
    <>
      <Link to="/listeler" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="h-4 w-4" aria-hidden />
        Listeler
      </Link>

      <PageHeader
        title={list.name}
        subtitle={
          <span className="flex flex-wrap items-center gap-2">
            <ListStatusBadge status={list.status} />
            {list.period && <span>{list.period}</span>}
            {list.supplier_name && <span>· {list.supplier_name}</span>}
            <span>
              · Kur: ¥ {rate(list.yuan_rate)} / $ {rate(list.usd_rate)}
              {list.rate_locked_at ? ` (kilitli — ${dateTime(list.rate_locked_at)})` : ' (taslak, güncel kuru izliyor)'}
            </span>
            {list.is_export_stale && list.last_export && (
              <span className="badge bg-amber-50 text-amber-800 ring-amber-200">Çıktı güncel değil</span>
            )}
          </span>
        }
        actions={
          <>
            <Link to={`/listeler/${listId}/urun/yeni`} className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden />
              Ürün ekle
            </Link>
            {/* İE#11 Görev E: üretim CSRF'li POST — dosya blob olarak iner, rozet tazelenir. */}
            <button type="button" className="btn-ghost" onClick={() => void exportsApi.create(listId, 'xlsx').then(refresh).catch((c) => push(messageOf(c), 'error'))}>
              <FileSpreadsheet className="h-4 w-4" aria-hidden />
              Excel
            </button>
            <button type="button" className="btn-ghost" onClick={() => void exportsApi.create(listId, 'pdf').then(refresh).catch((c) => push(messageOf(c), 'error'))}>
              <Download className="h-4 w-4" aria-hidden />
              PDF
            </button>
            <button type="button" className="btn-ghost" onClick={() => setShareOpen((value) => !value)}>
              <Share2 className="h-4 w-4" aria-hidden />
              Paylaş
            </button>
          </>
        }
      />

      {shareOpen ? <SharePanel listId={listId} tokenPrefix={list.share_token_prefix} onChanged={refresh} /> : null}

      {allowedListStatuses.length > 0 && (
        <div className="card mb-4 flex flex-wrap items-center gap-2 p-3 text-sm">
          <span className="text-slate-600">Liste durumunu ilerlet:</span>
          {allowedListStatuses.map((next) => (
            <button key={next} type="button" className="btn-ghost" onClick={() => void changeListStatus(next)}>
              {listStatusLabels[next]}
            </button>
          ))}
          {list.status === 'draft' && (
            <span className="text-xs text-slate-500">"İletildi" seçildiğinde kur bu listeye kilitlenir.</span>
          )}
        </div>
      )}

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <label className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden />
          <input
            className="field-input pl-9"
            placeholder="Ürün ara"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            aria-label="Ürün ara"
          />
        </label>
        <select
          className="field-input sm:w-56"
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value as ProductStatus | '')}
          aria-label="Duruma göre süz"
        >
          <option value="">Tüm durumlar</option>
          {Object.entries(productStatusLabels).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {selected.length > 0 && (
        <div className="card mb-3 flex flex-wrap items-center gap-2 border-brand-200 bg-brand-50 p-3 text-sm">
          <span className="font-semibold text-brand-800">{count(selected.length)} ürün seçildi</span>
          {(['ordered', 'in_transit', 'received', 'cancelled'] as ProductStatus[]).map((next) => (
            <button key={next} type="button" className="btn-ghost" onClick={() => void bulkStatus(next)}>
              {productStatusLabels[next]} yap
            </button>
          ))}
          <button type="button" className="btn-ghost" onClick={() => setSelected([])}>
            Seçimi temizle
          </button>
        </div>
      )}

      {productState.loading ? (
        <Skeleton rows={3} />
      ) : productState.error ? (
        <ErrorNote message={productState.error} onRetry={productState.reload} />
      ) : items.length === 0 ? (
        <EmptyState
          title={query || statusFilter ? 'Eşleşen ürün yok' : 'Bu listede henüz ürün yok'}
          description={
            query || statusFilter
              ? 'Aramayı veya süzgeci değiştirip tekrar dene.'
              : 'İlk ürünü elle ekleyebilir, Faz 3\'ten sonra eklentiyle 1688\'den yakalayabilirsin.'
          }
          action={
            <Link to={`/listeler/${listId}/urun/yeni`} className="btn-primary">
              Ürün ekle
            </Link>
          }
        />
      ) : (
        <>
          {/* Telefon: kart görünümü */}
          <ul className="space-y-3 md:hidden">
            {items.map((product) => (
              <li key={product.id} className="card p-3">
                <div className="flex gap-3">
                  <Thumb product={product} onChanged={productState.reload} />
                  <div className="min-w-0 flex-1">
                    <Link to={`/listeler/${listId}/urun/${product.id}`} className="block truncate font-semibold">
                      {product.name}
                    </Link>
                    <div className="text-xs text-slate-500">{categoryName(product.category_id)}</div>
                    <div className="mt-1 text-sm">
                      {count(product.qty)} adet × ¥{money(product.price_yuan)}
                    </div>
                    <div className="text-sm font-semibold">₺{money(product.line_total_yuan_tl)}</div>
                  </div>
                  <div className="flex flex-col items-end justify-between">
                    <StatusMenu
                      status={product.status}
                      busy={busyId === product.id}
                      onChange={(next) => void changeStatus(product, next)}
                    />
                    <button
                      type="button"
                      className="mt-2 inline-flex h-11 w-11 items-center justify-center rounded-xl text-rose-600"
                      aria-label="Ürünü sil"
                      onClick={() => void removeProduct(product)}
                    >
                      <Trash2 className="h-4 w-4" aria-hidden />
                    </button>
                  </div>
                </div>
              </li>
            ))}
          </ul>

          {/* Masaüstü: tablo görünümü */}
          <div className="card hidden md:block">
            <div className="table-scroll">
              <table className="w-full text-sm">
                <thead className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th className="w-10 px-3 py-3">
                      <input
                        type="checkbox"
                        aria-label="Tümünü seç"
                        className="h-4 w-4"
                        checked={selected.length === items.length && items.length > 0}
                        onChange={(event) => setSelected(event.target.checked ? items.map((item) => item.id) : [])}
                      />
                    </th>
                    <th className="w-16 px-3 py-3">Görsel</th>
                    <SortHeader label="Ürün" sortKey="name" sort={sort} onSort={setSort} />
                    <th className="px-3 py-3">Kategori</th>
                    <SortHeader label="Adet" sortKey="qty" sort={sort} onSort={setSort} align="right" />
                    <SortHeader label="¥ Birim" sortKey="price_yuan" sort={sort} onSort={setSort} align="right" />
                    <th className="px-3 py-3 text-right">¥ Satır</th>
                    <th className="px-3 py-3 text-right">₺ Birim</th>
                    <th className="px-3 py-3 text-right">$ DDP</th>
                    <SortHeader label="₺ Satır" sortKey="line_total_yuan_tl" sort={sort} onSort={setSort} align="right" />
                    <SortHeader label="Durum" sortKey="status" sort={sort} onSort={setSort} />
                    <th className="w-12 px-3 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {items.map((product) => (
                    <tr key={product.id} className="hover:bg-slate-50">
                      <td className="px-3 py-2">
                        <input
                          type="checkbox"
                          className="h-4 w-4"
                          aria-label={`${product.name} seç`}
                          checked={selected.includes(product.id)}
                          onChange={(event) =>
                            setSelected((current) =>
                              event.target.checked
                                ? [...current, product.id]
                                : current.filter((value) => value !== product.id),
                            )
                          }
                        />
                      </td>
                      <td className="px-3 py-2">
                        <Thumb product={product} onChanged={productState.reload} />
                      </td>
                      <td className="max-w-xs px-3 py-2">
                        <Link to={`/listeler/${listId}/urun/${product.id}`} className="block truncate font-medium">
                          {product.name}
                        </Link>
                        {product.detail && <span className="block truncate text-xs text-slate-500">{product.detail}</span>}
                      </td>
                      <td className="px-3 py-2 text-slate-600">{categoryName(product.category_id)}</td>
                      <td className="px-3 py-2 text-right">{count(product.qty)}</td>
                      <td className="px-3 py-2 text-right">¥{money(product.price_yuan)}</td>
                      <td className="px-3 py-2 text-right">¥{money(product.line_total_yuan)}</td>
                      <td className="px-3 py-2 text-right">₺{money(product.price_yuan_tl)}</td>
                      <td className="px-3 py-2 text-right">${money(product.price_ddp_usd)}</td>
                      <td className="px-3 py-2 text-right font-semibold">₺{money(product.line_total_yuan_tl)}</td>
                      <td className="px-3 py-2">
                        <StatusMenu
                          status={product.status}
                          busy={busyId === product.id}
                          onChange={(next) => void changeStatus(product, next)}
                        />
                      </td>
                      <td className="px-3 py-2">
                        <button
                          type="button"
                          className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-rose-600"
                          aria-label="Ürünü sil"
                          onClick={() => void removeProduct(product)}
                        >
                          <Trash2 className="h-4 w-4" aria-hidden />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
                {/* TOPLAM satırı: değerler backend'in MoneyService'inden gelir. */}
                <tfoot className="border-t-2 border-slate-200 bg-slate-50 font-semibold">
                  <tr>
                    <td className="px-3 py-3" colSpan={4}>
                      TOPLAM
                    </td>
                    {/* Hiza: Adet → boş(¥Birim) → ¥Satır toplamı → boş(₺Birim) → $ → ₺Satır toplamı */}
                    <td className="px-3 py-3 text-right">{count(list.totals.qty)}</td>
                    <td className="px-3 py-3" />
                    <td className="px-3 py-3 text-right">¥{money(list.totals.yuan)}</td>
                    <td className="px-3 py-3" />
                    <td className="px-3 py-3 text-right">${money(list.totals.ddp_usd)}</td>
                    <td className="px-3 py-3 text-right">₺{money(list.totals.yuan_tl)}</td>
                    <td className="px-3 py-3" colSpan={2} />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          {/* Telefonda toplam ayrı kartta durur — tablo dayatılmaz. */}
          <div className="card mt-3 p-4 md:hidden">
            <div className="text-xs uppercase tracking-wide text-slate-500">Toplam</div>
            <dl className="mt-2 space-y-1 text-sm">
              <Row label="Adet" value={count(list.totals.qty)} />
              <Row label="Yuan" value={`¥${money(list.totals.yuan)}`} />
              <Row label="TL" value={`₺${money(list.totals.yuan_tl)}`} strong />
              <Row label="DDP $" value={`$${money(list.totals.ddp_usd)}`} />
              <Row label="DDP ₺" value={`₺${money(list.totals.ddp_tl)}`} />
            </dl>
          </div>
        </>
      )}

      <ExportHistory listId={listId} refreshKey={list.revision + (list.last_export?.created_at ?? '')} />
    </>
  );
}

/**
 * İE#10 Blok 4 — paylaşım paneli: link üret/yenile/iptal + hızlı paylaşım (K20:
 * WhatsApp wa.me, e-posta mailto, kopyala). Tam token YALNIZ üretim yanıtında
 * görünür; sayfa yenilenince yalnız önek kalır — link o an kopyalanmalıdır.
 */
/**
 * İE#10.5 ek (a): tam link yalnız üretim yanıtında gelir; veri tazelemesi ekranı
 * yeniden kurunca kaybolmamalı — oturum ömürlü bellek önbelleğinde tutulur
 * (sayfa YENİLENİRSE kaybolur, bu bilinçli: link kalıcı saklanmaz — K51).
 */
const shareUrlCache = new Map<number, string>();

function SharePanel({ listId, tokenPrefix, onChanged }: { listId: number; tokenPrefix: string | null; onChanged: () => void }) {
  const push = useToast((state) => state.push);
  const [busy, setBusy] = useState(false);
  const [url, setUrlState] = useState<string | null>(shareUrlCache.get(listId) ?? null);
  const setUrl = (value: string | null) => {
    if (value === null) {
      shareUrlCache.delete(listId);
    } else {
      shareUrlCache.set(listId, value);
    }
    setUrlState(value);
  };

  const create = async () => {
    setBusy(true);
    try {
      const result = await shareApi.create(listId);
      setUrl(result.share_url);
      onChanged();
      push('Paylaşım linki hazır — bu link yalnız şimdi görünür, kopyalayın.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const revoke = async () => {
    setBusy(true);
    try {
      await shareApi.revoke(listId);
      setUrl(null);
      onChanged();
      push('Paylaşım linki iptal edildi — eski link artık açılmaz.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const copy = () => {
    if (url) {
      void navigator.clipboard.writeText(url).then(() => push('Link kopyalandı.'));
    }
  };

  const message = url ? `Sipariş listemiz hazır, buradan inceleyebilirsiniz: ${url}` : '';

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-2 text-sm font-semibold text-slate-700">Paylaşım linki</h2>
      {url ? (
        <>
          <p className="break-all rounded-lg bg-slate-50 p-2 font-mono text-xs">{url}</p>
          <p className="mt-1 text-xs text-amber-700">
            Bu link yalnız şimdi görünür (güvenlik gereği kaydedilmez) — kopyalamadan sayfadan ayrılmayın.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="btn-primary" onClick={copy}>Bağlantıyı kopyala</button>
            <a className="btn-ghost" href={`https://wa.me/?text=${encodeURIComponent(message)}`} target="_blank" rel="noreferrer">
              WhatsApp
            </a>
            <a className="btn-ghost" href={`mailto:?subject=${encodeURIComponent('Sipariş listesi')}&body=${encodeURIComponent(message)}`}>
              E-posta
            </a>
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => void revoke()}>Linki iptal et</button>
          </div>
        </>
      ) : (
        <>
          <p className="text-xs text-slate-500">
            {tokenPrefix
              ? `Aktif bir paylaşım linki var (${tokenPrefix}…). Yenilemek eski linki öldürür; iptal etmek sayfayı kapatır.`
              : 'Firma için girişsiz, salt-okunur bir sayfa linki üretilir. Liste güncellendikçe sayfa da güncel kalır.'}
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="btn-primary" disabled={busy} onClick={() => void create()}>
              {tokenPrefix ? 'Linki yenile' : 'Link üret'}
            </button>
            {tokenPrefix ? (
              <button type="button" className="btn-ghost" disabled={busy} onClick={() => void revoke()}>Linki iptal et</button>
            ) : null}
          </div>
        </>
      )}
    </section>
  );
}

/** İE#10 Blok 1 — export geçmişi: tarih + tür + indir (kayıt snapshot'ından yeniden üretim). */
function ExportHistory({ listId, refreshKey }: { listId: number; refreshKey: string | number }) {
  const state = useAsync(() => exportsApi.history(listId), [listId, refreshKey]);

  if (state.loading || state.error || (state.data ?? []).length === 0) return null;

  return (
    <section className="card mt-4 p-4">
      <h2 className="mb-2 text-sm font-semibold text-slate-700">Export geçmişi</h2>
      <ul className="divide-y divide-slate-100 text-sm">
        {(state.data ?? []).map((entry) => (
          <li key={entry.id} className="flex items-center justify-between gap-3 py-2">
            <span className="uppercase text-slate-500">{entry.format}</span>
            <span className="flex-1 text-slate-600">{dateTime(entry.created_at)}</span>
            <a className="btn-ghost" href={exportsApi.fileUrl(entry.id)}>
              <Download className="h-4 w-4" aria-hidden />
              İndir
            </a>
          </li>
        ))}
      </ul>
      <p className="mt-2 text-xs text-slate-500">
        Her indirme, kaydın üretildiği ANDAKİ anlık görüntüyü verir — liste sonradan değiştiyse yeni export alın.
      </p>
    </section>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between">
      <dt className="text-slate-500">{label}</dt>
      <dd className={strong ? 'font-bold' : 'font-medium'}>{value}</dd>
    </div>
  );
}

/**
 * K47 kırık-görsel dayanıklılığı: uzak görsel yüklenemezse (alicdn Referer ACL,
 * 403/404) ürün kartı bozulmaz — yer tutucu + "yeniden dene" gösterilir. Yeniden
 * dene, kaynak URL'yi tekrar kaydeder; arşiv modunda backend görseli indirip yerel
 * yola çevirir ve sonraki yüklemede görsel kendi sunucumuzdan gelir.
 */
function Thumb({ product, onChanged }: { product: Product; onChanged?: () => void }) {
  const [broken, setBroken] = useState(false);
  const [retrying, setRetrying] = useState(false);
  const source = product.main_image ?? product.images[0]?.url ?? null;
  if (!source) {
    return <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-400">—</span>;
  }

  if (broken) {
    return (
      <span className="flex h-14 w-14 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center">
        <ImageOff className="h-4 w-4 text-slate-400" aria-hidden />
        <button
          type="button"
          className="text-[10px] font-medium text-brand-600 disabled:opacity-50"
          disabled={retrying}
          onClick={() => {
            setRetrying(true);
            // İE#10 5d: onarım ucu iki durumu da çözer — uzak URL'yi arşive alır,
            // yerel-ama-dosyası-kayıp görseli kayıtlı kaynağından yeniden indirir.
            productsApi
              .mediaRepair(product.id)
              .then(() => {
                setBroken(false);
                onChanged?.();
              })
              .catch(() => {
                /* Görsel yine gelmezse yer tutucu kalır; kart çalışmaya devam eder. */
              })
              .finally(() => setRetrying(false));
          }}
        >
          {retrying ? '…' : 'yeniden dene'}
        </button>
      </span>
    );
  }

  return (
    <img
      src={source}
      alt=""
      loading="lazy"
      onError={() => setBroken(true)}
      className="h-14 w-14 shrink-0 rounded-xl border border-slate-200 object-cover"
    />
  );
}

function SortHeader({
  label,
  sortKey,
  sort,
  onSort,
  align = 'left',
}: {
  label: string;
  sortKey: SortKey;
  sort: { key: SortKey; asc: boolean };
  onSort: (value: { key: SortKey; asc: boolean }) => void;
  align?: 'left' | 'right';
}) {
  const active = sort.key === sortKey;
  return (
    <th className={`px-3 py-3 ${align === 'right' ? 'text-right' : 'text-left'}`}>
      <button
        type="button"
        className="inline-flex items-center gap-1 uppercase tracking-wide"
        onClick={() => onSort({ key: sortKey, asc: active ? !sort.asc : true })}
        aria-sort={active ? (sort.asc ? 'ascending' : 'descending') : 'none'}
      >
        {label}
        {active && <span aria-hidden>{sort.asc ? '↑' : '↓'}</span>}
      </button>
    </th>
  );
}
