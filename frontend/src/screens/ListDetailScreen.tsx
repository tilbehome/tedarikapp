import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, Download, ImageOff, Plus, Search, Share2, Trash2 } from 'lucide-react';
import { exports as exportsApi, lists as listsApi, products as productsApi, share as shareApi } from '../api/endpoints';
import CiktiSecenekleri, { paylasimAdresiAnahtari } from './liste/CiktiSecenekleri';
import AsamaCubugu from './liste/AsamaCubugu';
import OzetSeridi from './liste/OzetSeridi';
import UyariCipleri from './liste/UyariCipleri';
import MiktarHucresi from './liste/MiktarHucresi';
import { type EksikAlan, eksikAlanlar, eksikEtiketleri } from '../lib/eksikler';
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
  // Uyarı çipi süzgeci: "2 üründe kategori eksik" çipine basınca yalnız o ürünler.
  const [uyariFiltresi, setUyariFiltresi] = useState<EksikAlan | null>(null);
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
    return uyariFiltresi === null ? rows : rows.filter((row) => eksikAlanlar(row).includes(uyariFiltresi));
  }, [productState.data, sort, uyariFiltresi]);

  /** Çipler ve özet TÜM listeye bakar — süzgeç açıkken sayılar küçülmemeli. */
  const tumUrunler = useMemo(() => productState.data ?? [], [productState.data]);

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

  const changeQty = async (product: Product, qty: number) => {
    try {
      await productsApi.update(product.id, { qty });
      refresh();
      push(`"${product.name}" miktarı ${count(qty)} oldu.`);
    } catch (caught) {
      push(messageOf(caught), 'error');
      // Hücrenin eski değerine dönebilmesi için hata YUKARI verilir.
      throw caught;
    }
  };

  const toggleHazir = async (product: Product) => {
    setBusyId(product.id);
    try {
      const sonuc = await productsApi.setHazir(product.id, !product.hazir);
      refresh();
      push(sonuc.hazir ? `"${product.name}" HAZIR işaretlendi.` : `"${product.name}" hazır işareti kaldırıldı.`);
    } catch (caught) {
      // Kapı sunucudadır: eksik varsa gerekçesi mesajda gelir (C8).
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
      <Link to="/listeler" className="mb-3 inline-flex items-center gap-1 text-sm text-ink-3 hover:text-ink">
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
              <span className="badge bg-warn-soft text-warn ring-warn/20">Çıktı güncel değil</span>
            )}
          </span>
        }
        actions={
          <>
            <Link to={`/listeler/${listId}/urun/yeni`} className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden />
              Ürün ekle
            </Link>
            {/* İE#11 Görev E: üretim CSRF'li POST · İE#13 F2/F5/F6: kopya türü, durum filtresi, QR. */}
            <CiktiSecenekleri listId={listId} onDone={refresh} />
            <button type="button" className="btn-ghost" onClick={() => setShareOpen((value) => !value)}>
              <Share2 className="h-4 w-4" aria-hidden />
              Paylaş
            </button>
          </>
        }
      />

      {shareOpen ? <SharePanel listId={listId} tokenPrefix={list.share_token_prefix} onChanged={refresh} /> : null}

      {/* İE#21 B2: komuta merkezi — aşama çubuğu (5B) · özet şerit · uyarı çipleri. */}
      <AsamaCubugu
        durum={list.status}
        izinliGecisler={allowedListStatuses}
        kurKilitli={list.rate_locked_at !== null}
        onGecis={(next) => void changeListStatus(next)}
      />

      {/* İptal, çubuğun dışında bir çıkıştır: ayrı ve sessiz durur. */}
      {allowedListStatuses.includes('cancelled') && (
        <div className="mb-4 text-right">
          <button type="button" className="btn-ghost !text-xs" onClick={() => void changeListStatus('cancelled')}>
            Listeyi iptal et
          </button>
        </div>
      )}

      <OzetSeridi liste={list} urunler={tumUrunler} />

      <UyariCipleri
        urunler={tumUrunler}
        secili={uyariFiltresi}
        kurKilitli={list.rate_locked_at !== null}
        onSec={setUyariFiltresi}
      />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <label className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" aria-hidden />
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
        <div className="card mb-3 flex flex-wrap items-center gap-2 border-blue/30 bg-blue-soft p-3 text-sm">
          <span className="font-semibold text-navy">{count(selected.length)} ürün seçildi</span>
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
                    <div className="text-xs text-ink-3">{categoryName(product.category_id)}</div>
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
                      className="mt-2 inline-flex h-11 w-11 items-center justify-center rounded-xl text-err"
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
                <thead className="border-b border-line text-left text-xs uppercase tracking-wide text-ink-3">
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
                    <th className="px-3 py-3">Hazır</th>
                    <th className="w-12 px-3 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-line-soft">
                  {items.map((product) => (
                    <tr key={product.id} className="hover:bg-g50">
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
                        {product.detail && <span className="block truncate text-xs text-ink-3">{product.detail}</span>}
                        <SatirUyarilari urun={product} />
                      </td>
                      <td className="px-3 py-2 text-ink-2">{categoryName(product.category_id)}</td>
                      <td className="px-3 py-2 text-right">
                        <MiktarHucresi
                          deger={product.qty}
                          etiket={product.name}
                          kapali={list.status === 'completed' || list.status === 'cancelled'}
                          onKaydet={(yeni) => changeQty(product, yeni)}
                        />
                      </td>
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
                        <HazirDugmesi
                          urun={product}
                          mesgul={busyId === product.id}
                          onDegistir={() => void toggleHazir(product)}
                        />
                      </td>
                      <td className="px-3 py-2">
                        <button
                          type="button"
                          className="inline-flex h-11 w-11 items-center justify-center rounded-xl text-err"
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
                <tfoot className="border-t-2 border-line bg-g50 font-semibold">
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
                    <td className="px-3 py-3" colSpan={3} />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          {/* Telefonda toplam ayrı kartta durur — tablo dayatılmaz. */}
          <div className="card mt-3 p-4 md:hidden">
            <div className="text-xs uppercase tracking-wide text-ink-3">Toplam</div>
            <dl className="mt-2 space-y-1 text-sm">
              <Row label="Adet" value={count(list.totals.qty)} />
              <Row label="Yuan" value={`¥${money(list.totals.yuan)}`} />
              <Row label="Yaklaşık ürün bedeli (₺)" value={`₺${money(list.totals.yuan_tl)}`} strong />
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
 * HAZIR DÜĞMESİ (İE#21 B2 · C8 kalite kapısı).
 *
 * Kapının kararı sunucudadır: eksik alan varsa `PATCH /hazir` 422 ile reddeder.
 * Panel bu yüzden düğmeyi eksik üründe KAPATMAZ — kullanıcı basar, gerekçeyi
 * okur. Kapatsaydık "neden basamıyorum?" sorusu cevapsız kalırdı.
 */
function HazirDugmesi({ urun, mesgul, onDegistir }: { urun: Product; mesgul: boolean; onDegistir: () => void }) {
  const eksik = eksikEtiketleri(urun);

  return (
    <button
      type="button"
      disabled={mesgul}
      onClick={onDegistir}
      aria-pressed={urun.hazir}
      data-testid="hazir-dugmesi"
      title={eksik.length > 0 ? `Eksik: ${eksik.join(' · ')}` : undefined}
      className={`badge ${urun.hazir ? 'bg-ok-soft text-ok ring-ok/20' : 'bg-g50 text-ink-3 ring-line'}`}
    >
      {urun.hazir ? 'HAZIR' : eksik.length > 0 ? `${count(eksik.length)} eksik` : 'İşaretle'}
    </button>
  );
}

/**
 * SATIR UYARILARI (İE#21 B2) — hangi ürünün nesi eksik, satırda görünür.
 *
 * Üstteki çipler "kaç üründe" der; satırdaki rozetler "bu üründe ne" der. İkisi
 * aynı kelimeyi kullanır (`EKSIK_ETIKETLERI`), yoksa kullanıcı iki ayrı sorun
 * olduğunu sanar.
 */
function SatirUyarilari({ urun }: { urun: Product }) {
  const eksik = eksikEtiketleri(urun);
  if (eksik.length === 0) return null;

  return (
    <span className="mt-0.5 flex flex-wrap gap-1" data-testid="satir-uyarilari">
      {eksik.map((etiket) => (
        <span key={etiket} className="badge bg-warn-soft text-warn ring-warn/20">
          {etiket} yok
        </span>
      ))}
    </span>
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
      // F6: QR için tam adres GEREKİR ama sunucuda saklanmaz (K51). Sekme ömrü kadar
      // sessionStorage'da tutulur; sekme kapanınca silinir, kalıcı bir yere yazılmaz.
      sessionStorage.setItem(paylasimAdresiAnahtari(listId), result.share_url);
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
      sessionStorage.removeItem(paylasimAdresiAnahtari(listId));
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
      {/* Çatışma çözümü (İE#20 Bölüm B): V3 renk token'ı (text-ink-2) KORUNDU,
          main'den gelen erişim anahtarı satırı (K62) EKLENDİ. İkisi de gerekliydi:
          v3-faz1 paneli canlıda anahtarı gösteremiyordu (kullanıcının "NEREDE?"
          sorusunun kaynağı), main'in paneli ise V3 token setini kullanmıyordu. */}
      <h2 className="mb-2 text-sm font-semibold text-ink-2">Paylaşım linki</h2>
      <ErisimAnahtari listId={listId} />
      {url ? (
        <>
          <p className="break-all rounded-lg bg-g50 p-2 font-mono text-xs">{url}</p>
          <p className="mt-1 text-xs text-warn">
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
          <p className="text-xs text-ink-3">
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
      <h2 className="mb-2 text-sm font-semibold text-ink-2">Export geçmişi</h2>
      <ul className="divide-y divide-line-soft text-sm">
        {(state.data ?? []).map((entry) => (
          <li key={entry.id} className="flex items-center justify-between gap-3 py-2">
            <span className="uppercase text-ink-3">{entry.format}</span>
            <span className="flex-1 text-ink-2">{dateTime(entry.created_at)}</span>
            <a className="btn-ghost" href={exportsApi.fileUrl(entry.id)}>
              <Download className="h-4 w-4" aria-hidden />
              İndir
            </a>
          </li>
        ))}
      </ul>
      <p className="mt-2 text-xs text-ink-3">
        Her indirme, kaydın üretildiği ANDAKİ anlık görüntüyü verir — liste sonradan değiştiyse yeni export alın.
      </p>
    </section>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between">
      <dt className="text-ink-3">{label}</dt>
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
    return <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-g100 text-xs text-ink-3">—</span>;
  }

  if (broken) {
    return (
      <span className="flex h-14 w-14 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-g300 bg-g50 text-center">
        <ImageOff className="h-4 w-4 text-ink-3" aria-hidden />
        <button
          type="button"
          className="text-[10px] font-medium text-navy disabled:opacity-50"
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
      className="h-14 w-14 shrink-0 rounded-xl border border-line object-cover"
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

/**
 * ERİŞİM ANAHTARI (İE#18 G6-a/f · K62).
 *
 * Paylaşım sayfası artık "linki bilen görür" değildir: firma 6 haneli anahtarı
 * girmeden liste verisi render EDİLMEZ. Bu blok anahtarı gösterir, kopyalar,
 * yeniler ve kapıyı açıp kapatır.
 *
 * ANAHTAR OTOMATİK OLARAK KANAL METİNLERİNE YAZILMAZ (G6-f): link ile anahtarın
 * aynı kanaldan gitmesi korumayı anlamsız kılar — kullanıcı ayrı göndersin diye
 * yanında ipucu durur.
 */
function ErisimAnahtari({ listId }: { listId: number }) {
  const push = useToast((state) => state.push);
  const durum = useAsync(() => shareApi.key(listId), [listId]);
  const [anahtar, setAnahtar] = useState<string | null>(null);
  const [acik, setAcik] = useState<boolean | null>(null);
  const [busy, setBusy] = useState(false);

  const gecerliAnahtar = anahtar ?? durum.data?.key ?? '';
  const gecerliAcik = acik ?? durum.data?.enabled ?? true;

  const yenile = async () => {
    setBusy(true);
    try {
      const sonuc = await shareApi.rotateKey(listId);
      setAnahtar(sonuc.key);
      setAcik(true);
      push('Yeni anahtar üretildi — eski anahtar artık geçersiz. Firmaya yeni anahtarı iletin.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const cevir = async (yeniDurum: boolean) => {
    setBusy(true);
    try {
      await shareApi.toggleKey(listId, yeniDurum);
      setAcik(yeniDurum);
      push(
        yeniDurum
          ? 'Erişim anahtarı açıldı — sayfa artık anahtar soracak.'
          : 'Erişim anahtarı kapatıldı — linki bilen herkes görebilir.',
      );
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  if (durum.loading) return <p className="mb-3 text-xs text-ink-3">Erişim anahtarı okunuyor…</p>;
  if (durum.error) return <ErrorNote message={durum.error} onRetry={durum.reload} />;

  return (
    <div className="mb-3 rounded-xl border border-line bg-g50 p-3">
      <div className="flex flex-wrap items-center gap-3">
        <span className="text-xs font-semibold text-ink-2">Erişim anahtarı</span>
        <code className="rounded-lg border border-line bg-surface px-3 py-1 font-mono text-base font-bold tracking-[0.3em]">
          {gecerliAcik ? gecerliAnahtar : '—'}
        </code>
        <button
          type="button"
          className="btn-ghost !min-h-8 !px-3 !text-xs"
          disabled={!gecerliAcik || gecerliAnahtar === ''}
          onClick={() => {
            void navigator.clipboard?.writeText(gecerliAnahtar);
            push('Anahtar kopyalandı.');
          }}
        >
          Kopyala
        </button>
        <button type="button" className="btn-ghost !min-h-8 !px-3 !text-xs" disabled={busy} onClick={() => void yenile()}>
          Yenile
        </button>
        <label className="ml-auto flex items-center gap-2 text-xs text-ink-2">
          <input
            type="checkbox"
            checked={gecerliAcik}
            disabled={busy}
            onChange={(olay) => void cevir(olay.target.checked)}
          />
          Anahtar sorulsun
        </label>
      </div>
      <p className="mt-2 text-xs text-warn">
        Anahtarı <strong>linkten ayrı bir kanaldan</strong> gönderin (ör. link e-postayla, anahtar WhatsApp'tan) —
        ikisi aynı yerden giderse koruma anlamsız kalır. Yenilemek eski anahtarı anında geçersiz kılar.
      </p>
    </div>
  );
}
