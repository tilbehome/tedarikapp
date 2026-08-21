import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, Languages, Loader2 } from 'lucide-react';
import { products as productsApi, translate as translateApi } from '../api/endpoints';
import { ApiError } from '../api/client';
import type { Product } from '../api/types';
import { useAsync, messageOf } from '../lib/useAsync';
import { Field, PageHeader, Skeleton, ErrorNote } from '../components/ui';
import { useReference } from '../store/reference';
import { useToast } from '../components/Toast';
import { isSupportedSourceUrl } from '../lib/sourceUrl';
import { useMediaMode } from '../lib/useMediaMode';

/**
 * E5 — Ürün Ekle/Düzenle.
 *
 * Fiyatlar METİN olarak gönderilir; panel hesap yapmaz, TL karşılığı kaydettikten
 * sonra backend'den okunur (K14/K29). Görsel URL'si sunucudaki MediaService'e
 * teslim edilir; hotlink modunda arşivleme yapılmadığı rozetle söylenir (K33).
 */
export default function ProductFormScreen() {
  const { id, productId } = useParams();
  const listId = Number(id);
  const editing = productId !== undefined;
  const navigate = useNavigate();
  const push = useToast((state) => state.push);
  const categories = useReference((state) => state.categories);
  const media = useMediaMode();

  const existing = useAsync<Product | null>(async () => {
    if (!editing) return null;
    const items = await productsApi.forList(listId);
    return items.find((item) => item.id === Number(productId)) ?? null;
  }, [editing, listId, productId]);

  // İE#14 C1: "Türkçe öneri" — kaynak ORİJİNAL BAŞLIK, yoksa ürün adı. Öneri
  // KULLANICI ONAYIYLA yalnız "Ürün adı" alanına yazılır; orijinal başlığa ve
  // başka hiçbir alana dokunulmaz (K54).
  const [oneri, setOneri] = useState<string | null>(null);
  const [oneriKaynagi, setOneriKaynagi] = useState<string | null>(null);
  const [oneriYukleniyor, setOneriYukleniyor] = useState(false);

  const [form, setForm] = useState({
    name: '',
    name_original: '',
    detail: '',
    category_id: '',
    qty: '1',
    price_yuan: '',
    price_ddp_usd: '',
    // İE#13 F5: hedef satış fiyatı — yalnız iç kopya çıktısında kullanılır.
    price_target_try: '',
    units_per_carton: '',
    url: '',
    vendor_name: '',
    main_image: '',
    tracking_no: '',
    note: '',
  });
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [duplicate, setDuplicate] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const product = existing.data;
    if (!product) return;
    // Yüklenen ürün formu tohumlar — react-hooks 7 bu deseni uyarıyor; formun sunucu
    // verisiyle ilklenmesi mevcut davranıştır ve F41 kapsamında ele alınacak.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setForm({
      name: product.name,
      name_original: product.name_original ?? '',
      detail: product.detail ?? '',
      category_id: product.category_id === null ? '' : String(product.category_id),
      qty: String(product.qty),
      price_yuan: product.price_yuan,
      price_ddp_usd: product.price_ddp_usd,
      price_target_try: product.price_target_try ?? '',
      units_per_carton: product.units_per_carton === null ? '' : String(product.units_per_carton),
      url: product.url ?? '',
      vendor_name: product.vendor_name ?? '',
      main_image: product.main_image ?? '',
      tracking_no: product.tracking_no ?? '',
      note: product.note ?? '',
    });
  }, [existing.data]);

  const set = (key: keyof typeof form, value: string) => setForm((current) => ({ ...current, [key]: value }));

  const payload = (force: boolean) => ({
    name: form.name.trim(),
    name_original: form.name_original.trim() || null,
    detail: form.detail.trim() || null,
    category_id: form.category_id === '' ? null : Number(form.category_id),
    // qty tam sayıdır (para değil); fiyatlar METİN olarak gider — K14/K29.
    qty: Number(form.qty),
    price_yuan: form.price_yuan.trim().replace(',', '.'),
    price_ddp_usd: form.price_ddp_usd.trim() === '' ? '0' : form.price_ddp_usd.trim().replace(',', '.'),
    // Boş bırakılırsa hedef TEMİZLENİR (null) — kâr sütunları "—" basar.
    price_target_try: form.price_target_try.trim() === '' ? null : form.price_target_try.trim().replace(',', '.'),
    units_per_carton: form.units_per_carton === '' ? null : Number(form.units_per_carton),
    url: form.url.trim() || null,
    vendor_name: form.vendor_name.trim() || null,
    main_image: form.main_image.trim() || null,
    tracking_no: form.tracking_no.trim() || null,
    note: form.note.trim() || null,
    ...(force ? { force: true } : {}),
  });

  const submit = async (event: FormEvent, force = false) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setFields({});
    setDuplicate(null);
    try {
      if (editing) {
        await productsApi.update(Number(productId), payload(false));
        push('Ürün güncellendi.');
      } else {
        await productsApi.create(listId, payload(force));
        push('Ürün eklendi.');
      }
      navigate(`/listeler/${listId}`);
    } catch (caught) {
      if (caught instanceof ApiError && caught.code === 'DUPLICATE_WARNING') {
        const meta = caught.meta['existing'] as { list_name?: string } | undefined;
        setDuplicate(
          meta?.list_name
            ? `Bu ürün "${meta.list_name}" listesinde zaten var. Yine de eklemek ister misin?`
            : 'Bu ürün sistemde zaten var. Yine de eklemek ister misin?',
        );
      } else {
        setError(messageOf(caught));
        if (caught instanceof ApiError) setFields(caught.fields);
      }
    } finally {
      setBusy(false);
    }
  };

  // Çeviri yalnız CJK (Çince/Japonca/Korece) kaynakta anlamlıdır; Latin harfli
  // başlıkta düğme PASİFTİR — boşuna istek atıp kota harcamayız.
  const cjkVar = (metin: string): boolean => /[\u4E00-\u9FFF\u3400-\u4DBF\u3040-\u30FF\uAC00-\uD7AF]/.test(metin);
  const oneriKaynakMetni = (form.name_original.trim() || form.name.trim()).slice(0, 500);
  const oneriMumkun = oneriKaynakMetni !== '' && cjkVar(oneriKaynakMetni);

  const oneriIste = async () => {
    if (!oneriMumkun || oneriYukleniyor) return; // çift tıklama koruması
    setOneriYukleniyor(true);
    setOneri(null);
    try {
      const sonuc = await translateApi.suggest(oneriKaynakMetni);
      // Öneri yoksa SESSİZ geçilir (İE#13 K54 ilkesi): çeviri akışın zorunlu
      // parçası değildir, hata baloncuğu çıkarmak kullanıcıyı boşuna telaşlandırır.
      if (sonuc.suggestion !== null && sonuc.suggestion !== '') {
        setOneri(sonuc.suggestion);
        setOneriKaynagi(sonuc.source ?? sonuc.provider);
      } else {
        setOneri('');
      }
    } catch {
      setOneri('');
    } finally {
      setOneriYukleniyor(false);
    }
  };

  const oneriyiKullan = () => {
    if (oneri === null || oneri === '') return;
    set('name', oneri);
    setOneri(null);
    push('Türkçe öneri ürün adına yazıldı. Orijinal başlık değişmedi.');
  };

  if (editing && existing.loading) return <Skeleton rows={4} />;
  if (editing && existing.error) return <ErrorNote message={existing.error} onRetry={existing.reload} />;
  if (editing && existing.data === null) return <ErrorNote message="Ürün bulunamadı." />;

  return (
    <>
      <Link to={`/listeler/${listId}`} className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft className="h-4 w-4" aria-hidden />
        Liste detayı
      </Link>

      <PageHeader title={editing ? 'Ürünü düzenle' : 'Ürün ekle'} subtitle="Elle giriş" />

      <form onSubmit={(event) => void submit(event)} className="card space-y-4 p-4">
        <Field label="Ürün adı" error={fields['name']}>
          <div className="flex gap-2">
            <input
              className="field-input flex-1"
              value={form.name}
              onChange={(event) => set('name', event.target.value)}
              required
              autoFocus
            />
            {/* İE#14 C1: öneri düğmesi — kaynak Latin harfliyse pasif, çünkü
                çevrilecek bir şey yoktur. */}
            <button
              type="button"
              className="btn-ghost shrink-0"
              disabled={!oneriMumkun || oneriYukleniyor}
              title={
                oneriMumkun
                  ? 'Orijinal başlıktan Türkçe ad önerisi al'
                  : 'Öneri için Çince bir orijinal başlık gerekir'
              }
              onClick={() => void oneriIste()}
            >
              {oneriYukleniyor ? (
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
              ) : (
                <Languages className="h-4 w-4" aria-hidden />
              )}
              <span className="ml-1">Türkçe öneri</span>
            </button>
          </div>
          {oneri ? (
            <div className="mt-2 rounded-xl border border-brand-200 bg-brand-50/60 p-2 text-sm">
              <p className="text-slate-700">
                <span className="font-medium">Öneri:</span> {oneri}
                {oneriKaynagi === 'sozluk' ? (
                  <span className="ml-2 text-xs text-slate-500">(sözlükten)</span>
                ) : oneriKaynagi !== null ? (
                  <span className="ml-2 text-xs text-slate-500">(makine çevirisi — gözden geçirin)</span>
                ) : null}
              </p>
              <div className="mt-2 flex gap-2">
                <button type="button" className="btn-primary !min-h-8 !px-3 !text-xs" onClick={oneriyiKullan}>
                  Kullan
                </button>
                <button
                  type="button"
                  className="btn-ghost !min-h-8 !px-3 !text-xs"
                  onClick={() => setOneri(null)}
                >
                  Kapat
                </button>
              </div>
            </div>
          ) : null}
        </Field>

        <Field label="Orijinal başlık" hint="1688'deki Çince başlık (opsiyonel)" error={fields['name_original']}>
          <input className="field-input" value={form.name_original} onChange={(event) => set('name_original', event.target.value)} />
        </Field>

        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Kategori">
            <select
              className="field-input"
              value={form.category_id}
              onChange={(event) => set('category_id', event.target.value)}
            >
              <option value="">Kategorisiz</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </Field>

          <Field label="Adet" error={fields['qty']}>
            <input
              className="field-input"
              type="number"
              min={1}
              step={1}
              inputMode="numeric"
              value={form.qty}
              onChange={(event) => set('qty', event.target.value)}
              required
            />
          </Field>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Birim fiyat (¥)" hint="Örn. 12,50" error={fields['price_yuan']}>
            <input
              className="field-input"
              inputMode="decimal"
              value={form.price_yuan}
              onChange={(event) => set('price_yuan', event.target.value)}
              required
            />
          </Field>

          <Field
          label="Hedef satış fiyatı (₺)"
          hint="Yalnız İÇ KOPYA çıktısında kâr hesabı için — firmaya giden belgede ve paylaşım sayfasında GÖRÜNMEZ"
          error={fields['price_target_try']}
        >
          <input
            className="field-input"
            inputMode="decimal"
            value={form.price_target_try}
            onChange={(event) => set('price_target_try', event.target.value)}
            placeholder="Örn. 150,00"
          />
        </Field>

        <Field label="DDP birim fiyat ($)" hint="Bilinmiyorsa boş bırak" error={fields['price_ddp_usd']}>
            <input
              className="field-input"
              inputMode="decimal"
              value={form.price_ddp_usd}
              onChange={(event) => set('price_ddp_usd', event.target.value)}
            />
          </Field>
        </div>

        <p className="text-xs text-slate-500">
          TL karşılıkları listenin kuruyla sunucuda hesaplanır; kaydettikten sonra liste detayında görünür.
        </p>

        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Koli içi adet" error={fields['units_per_carton']}>
            <input
              className="field-input"
              type="number"
              min={1}
              step={1}
              inputMode="numeric"
              value={form.units_per_carton}
              onChange={(event) => set('units_per_carton', event.target.value)}
            />
          </Field>

          <Field label="Kargo takip no" error={fields['tracking_no']}>
            <input className="field-input" value={form.tracking_no} onChange={(event) => set('tracking_no', event.target.value)} />
          </Field>
        </div>

        <Field label="Ürün linki" error={fields['url']}>
          <input
            className="field-input"
            inputMode="url"
            placeholder="https://detail.1688.com/…"
            value={form.url}
            onChange={(event) => set('url', event.target.value)}
          />
          {/* İE#11 EK-2 (4): yumuşak uyarı — kaydetmeyi ENGELLEMEZ (canlı gözlem:
              alana panel adresi girilmişti). Eklenti yakalamalarında bu form hiç
              kullanılmadığı için uyarı orada görünmez. */}
          {form.url.trim() !== '' && !isSupportedSourceUrl(form.url.trim()) ? (
            <p className="mt-1 text-xs text-amber-700">
              Bu bir 1688 ürün linki gibi görünmüyor — firmanın ürünü bulacağı adres bu alana girilmelidir.
            </p>
          ) : null}
        </Field>

        <Field label="Satıcı adı" error={fields['vendor_name']}>
          <input className="field-input" value={form.vendor_name} onChange={(event) => set('vendor_name', event.target.value)} />
        </Field>

        <Field
          label="Görsel adresi"
          hint="Yalnızca izin verilen alan adları kabul edilir (alicdn.com, 1688.com)."
          error={fields['main_image']}
        >
          <input
            className="field-input"
            inputMode="url"
            placeholder="https://cbu01.alicdn.com/…"
            value={form.main_image}
            onChange={(event) => set('main_image', event.target.value)}
          />
        </Field>

        {media.mode === 'hotlink' && (
          <p className="badge bg-amber-50 text-amber-800 ring-amber-200">
            Arşivleme kapalı — görsel sunucuya indirilmez, 1688 bağlantısından gösterilir
          </p>
        )}

        <Field label="Detay" error={fields['detail']}>
          <textarea
            className="field-input min-h-24 py-2"
            value={form.detail}
            onChange={(event) => set('detail', event.target.value)}
          />
        </Field>

        <Field label="Not" error={fields['note']}>
          <textarea className="field-input min-h-20 py-2" value={form.note} onChange={(event) => set('note', event.target.value)} />
        </Field>

        {error && <p className="text-sm font-medium text-rose-700">{error}</p>}

        {duplicate && (
          <div className="card flex flex-col gap-3 border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
            <span className="flex items-start gap-2">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              {duplicate}
            </span>
            <button type="button" className="btn-primary" onClick={(event) => void submit(event, true)} disabled={busy}>
              Yine de ekle
            </button>
          </div>
        )}

        <div className="flex gap-2">
          <button type="submit" className="btn-primary" disabled={busy}>
            {busy ? 'Kaydediliyor…' : editing ? 'Değişiklikleri kaydet' : 'Ürünü ekle'}
          </button>
          <Link to={`/listeler/${listId}`} className="btn-ghost">
            Vazgeç
          </Link>
        </div>
      </form>
    </>
  );
}
