import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import {
  AlertTriangle,
  Archive,
  ArrowRight,
  BookTemplate,
  Check,
  Clock3,
  Copy,
  EyeOff,
  Gavel,
  Plus,
  RotateCcw,
  Search,
  Trash2,
  TriangleAlert,
} from 'lucide-react';
import { lists as listsApi, sablonlar as sablonlarApi, trash as trashApi } from '../api/endpoints';
import type { ListeSablonu, ListeSekmesi, SupplyList, Visibility } from '../api/types';
import { useAsync, messageOf } from '../lib/useAsync';
import { useAramaSorgusu } from '../lib/useAramaSorgusu';
import { count, dateTime, money } from '../lib/format';
import { visibilityLabels } from '../locales/tr';
import { EmptyState, ErrorNote, Field, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';
import SatirEylemMenusu, { useSatirEylemMenusu, type SatirEylemi } from '../components/k105/SatirEylemMenusu';
import SecimCubugu from '../components/k105/SecimCubugu';
import { useGeriAl } from '../components/k105/GeriAlToast';
import TabloAyarlari, { tabloTercihiOku, type SutunTanimi, type TabloTercihi } from '../components/k105/TabloAyarlari';

/**
 * LİSTELER MERKEZİ (V3-C Blok E · docs/v3 §7.4 · onaylı tasarım referansı
 * `tasarim-referans/listeler.png`).
 *
 * Ekran SAKİN açılır (prototip OKUBENI §0): KPI şeridi → görünürlük sekmeleri
 * (Aktif/Pasif/Arşiv) → durum çipleri (Hazırlanıyor · Fiyat bekleniyor ·
 * Değerlendirmede · Onaylı · Tamamlandı) → araç çubuğu → dönem gruplu tablo.
 * Sekme SUNUCUDA türetilir (`sekme`, liste durumu + teklif turları); panel
 * yalnız gösterir. "18/25 fiyatlandı" çubuğu da sunucudan gelir.
 *
 * K105 (her yeni ekran ortak bileşenlerle doğar — §3):
 *   · `SatirEylemMenusu`  ⋯ + sağ tık aynı menü
 *   · `SecimCubugu`       çoklu seçim alt çubuğu (sayfa/eşleşen ayrımı)
 *   · `GeriAlToast`       geri alınabilir sil/arşivle (onay kutusu YOK);
 *                          şablon silme ERTELENMİŞ kip (çöp kutusu yok)
 *   · `TabloAyarlari`     sütun · yoğunluk · kaydedilmiş görünüm
 *   · URL'de durum: gorunurluk · sekme · q · sirala · grup · sayfa
 *   · Klavye: `/` arama · `J/K` satır · `Enter` aç · `Space` seç · `?` kısayollar
 *
 * Para: `totals.*` DİZEDİR; footer toplamı tam sayı KURUŞ üzerinden toplanır
 * (CLAUDE.md §3: JS'te aritmetik yalnız tam sayı kuruşla).
 */
const EKRAN = 'listeler';
const SAYFA_BOYU = 25;
const GORUNURLUKLER: Visibility[] = ['active', 'passive', 'archived'];

const SEKMELER: { deger: ListeSekmesi | 'tumu'; etiket: string }[] = [
  { deger: 'tumu', etiket: 'Tümü' },
  { deger: 'hazirlaniyor', etiket: 'Hazırlanıyor' },
  { deger: 'fiyat_bekleniyor', etiket: 'Fiyat bekleniyor' },
  { deger: 'degerlendirmede', etiket: 'Değerlendirmede' },
  { deger: 'onayli', etiket: 'Onaylı / Sipariş' },
  { deger: 'tamamlandi', etiket: 'Tamamlandı' },
];

const SEKME_ETIKETI: Record<ListeSekmesi, string> = {
  hazirlaniyor: 'Hazırlanıyor',
  fiyat_bekleniyor: 'Fiyat bekleniyor',
  degerlendirmede: 'Değerlendirmede',
  onayli: 'Sipariş verildi',
  tamamlandi: 'Tamamlandı',
  iptal: 'İptal',
};

const SEKME_TONU: Record<ListeSekmesi, string> = {
  hazirlaniyor: 'bg-g100 text-ink-2 ring-line',
  fiyat_bekleniyor: 'bg-warn-soft text-warn ring-warn/20',
  degerlendirmede: 'bg-info-soft text-info ring-info/20',
  onayli: 'bg-ok-soft text-ok ring-ok/20',
  tamamlandi: 'bg-ok-soft text-ok ring-ok/20',
  iptal: 'bg-err-soft text-err ring-err/20',
};

const ADIMLAR = ['Hazır', 'Fiyat bekliyor', 'Değerlendirme', 'Sipariş', 'Sevkiyat'];
const ADIM_INDEKSI: Record<ListeSekmesi, number> = { hazirlaniyor: 0, fiyat_bekleniyor: 1, degerlendirmede: 2, onayli: 3, tamamlandi: 4, iptal: -1 };

const SUTUNLAR: SutunTanimi[] = [
  { anahtar: 'liste', etiket: 'Liste / Firma', sabit: true },
  { anahtar: 'durum', etiket: 'Durum ve ilerleme' },
  { anahtar: 'urun', etiket: 'Ürün' },
  { anahtar: 'fiyatlama', etiket: 'Fiyatlanma' },
  { anahtar: 'ddp', etiket: 'DDP toplam' },
  { anahtar: 'teklif', etiket: 'Teklif / Son işlem' },
];

type Siralama = 'son_islem' | 'ad' | 'urun' | 'fiyatlama';

export default function ListsScreen() {
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();
  const push = useToast((state) => state.push);
  const geriAl = useGeriAl();

  const gorunurluk = (GORUNURLUKLER.includes(params.get('gorunurluk') as Visibility) ? params.get('gorunurluk') : 'active') as Visibility;
  const sekme = (params.get('sekme') ?? 'tumu') as ListeSekmesi | 'tumu';
  const sirala = (params.get('sirala') ?? 'son_islem') as Siralama;
  const grup = params.get('grup') === 'yok' ? 'yok' : 'donem';
  const sayfa = Math.max(1, Number(params.get('sayfa') ?? '1') || 1);

  const arama = useAramaSorgusu(params.get('q') ?? '');
  const [tercih, setTercih] = useState<TabloTercihi>(() => tabloTercihiOku(EKRAN, { gizli: [], yogunluk: 'ferah' }));
  const [secili, setSecili] = useState<Set<number>>(new Set());
  const [odak, setOdak] = useState(0);
  const [olusturAcik, setOlusturAcik] = useState(false);
  const [sablonAcik, setSablonAcik] = useState(false);
  const [sablonKaynak, setSablonKaynak] = useState<SupplyList | null>(null);
  const [kisayollar, setKisayollar] = useState(false);
  const aramaKutusu = useRef<HTMLInputElement>(null);

  const paramYaz = (yeni: Record<string, string | null>) => {
    const p = new URLSearchParams(params);
    for (const [k, v] of Object.entries(yeni)) {
      if (v === null || v === '' || (k === 'sekme' && v === 'tumu') || (k === 'gorunurluk' && v === 'active') || (k === 'sayfa' && v === '1')) p.delete(k);
      else p.set(k, v);
    }
    setParams(p, { replace: true });
  };

  useEffect(() => {
    if ((params.get('q') ?? '') !== arama.gecikmeli) paramYaz({ q: arama.gecikmeli, sayfa: null });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [arama.gecikmeli]);

  const state = useAsync(
    (signal) => listsApi.allWithMeta({ visibility: gorunurluk, q: arama.gecikmeli || undefined, sekme: sekme === 'tumu' ? undefined : sekme }, signal),
    [gorunurluk, arama.gecikmeli, sekme],
  );
  const sablonlar = useAsync(() => sablonlarApi.hepsi(), []);

  const listeler = useMemo(() => state.data?.data ?? [], [state.data]);
  const meta = state.data?.meta;

  const sirali = useMemo(() => {
    const kopya = [...listeler];
    kopya.sort((a, b) => {
      switch (sirala) {
        case 'ad':
          return a.name.localeCompare(b.name, 'tr');
        case 'urun':
          return b.product_count - a.product_count;
        case 'fiyatlama':
          return b.fiyatlama.yuzde - a.fiyatlama.yuzde;
        default:
          return b.updated_at.localeCompare(a.updated_at);
      }
    });
    return kopya;
  }, [listeler, sirala]);

  const toplamSayfa = Math.max(1, Math.ceil(sirali.length / SAYFA_BOYU));
  const sayfadakiler = sirali.slice((sayfa - 1) * SAYFA_BOYU, sayfa * SAYFA_BOYU);

  const gruplar = useMemo(() => {
    const m = new Map<string, SupplyList[]>();
    for (const l of sayfadakiler) {
      const k = grup === 'donem' ? (l.period ?? 'Dönemsiz') : '';
      const b = m.get(k);
      if (b) b.push(l);
      else m.set(k, [l]);
    }
    return [...m.entries()];
  }, [sayfadakiler, grup]);

  const saglik = useMemo(() => {
    const s = { fiyat_bekleyen: 0, kur_sapmasi: 0, teklif_suresi: 0, cikti_guncel_degil: 0 };
    for (const l of listeler) for (const b of l.saglik) s[b] += 1;
    return s;
  }, [listeler]);

  const kurusToplam = useMemo(() => {
    let toplam = 0;
    for (const l of listeler) toplam += kurus(l.totals.ddp_tl);
    return toplam;
  }, [listeler]);

  const sec = (id: number) =>
    setSecili((eski) => {
      const y = new Set(eski);
      if (y.has(id)) y.delete(id);
      else y.add(id);
      return y;
    });

  // ── klavye (K105 §2.3) ──
  useEffect(() => {
    const tus = (olay: KeyboardEvent) => {
      const hedef = olay.target as HTMLElement | null;
      // Metin giren alanlar kısayolu yutar; onay kutusu/düğme yutmaz (Space seçim için ayrı ele alınır).
      const metinAlani = hedef?.tagName === 'INPUT' && !['checkbox', 'radio', 'button'].includes((hedef as HTMLInputElement).type);
      const yaziyor = hedef !== null && (metinAlani || hedef.tagName === 'TEXTAREA' || hedef.isContentEditable);
      if (olay.key === ' ' && hedef?.tagName === 'INPUT' && (hedef as HTMLInputElement).type === 'checkbox') return;
      if (olay.key === '/' && !yaziyor) {
        olay.preventDefault();
        aramaKutusu.current?.focus();
      } else if (olay.key === '?' && !yaziyor) {
        setKisayollar((v) => !v);
      } else if ((olay.key === 'j' || olay.key === 'ArrowDown') && !yaziyor) {
        olay.preventDefault();
        setOdak((i) => Math.min(sayfadakiler.length - 1, i + 1));
      } else if ((olay.key === 'k' || olay.key === 'ArrowUp') && !yaziyor) {
        olay.preventDefault();
        setOdak((i) => Math.max(0, i - 1));
      } else if (olay.key === 'Enter' && !yaziyor && sayfadakiler[odak]) {
        navigate(`/listeler/${sayfadakiler[odak].id}`);
      } else if (olay.key === ' ' && !yaziyor && sayfadakiler[odak]) {
        olay.preventDefault();
        sec(sayfadakiler[odak].id);
      } else if (olay.key === 'Escape') {
        setSecili(new Set());
        setKisayollar(false);
      }
    };
    window.addEventListener('keydown', tus);
    return () => window.removeEventListener('keydown', tus);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sayfadakiler, odak]);

  // ── eylemler (hepsi geri alınabilir ya da toast'lı — §2.5 sessiz eylem yok) ──
  const gorunurlukDegistir = (l: SupplyList, yeni: Visibility) =>
    geriAl.geriAlinabilir(
      yeni === 'archived' ? `"${l.name}" arşivlendi.` : yeni === 'passive' ? `"${l.name}" pasife alındı.` : `"${l.name}" aktifleştirildi.`,
      () => listsApi.update(l.id, { visibility: yeni }),
      () => listsApi.update(l.id, { visibility: l.visibility }),
      state.reload,
    );

  const copeAt = (l: SupplyList) =>
    geriAl.geriAlinabilir(`"${l.name}" çöp kutusuna taşındı (30 gün).`, () => listsApi.remove(l.id), () => trashApi.restore('lists', l.id), state.reload);

  const kopyala = async (l: SupplyList, tekrarSiparis = false) => {
    try {
      const yeni = await listsApi.duplicate(l.id, tekrarSiparis ? `${l.name} (tekrar sipariş)` : undefined);
      push(tekrarSiparis ? `Tekrar sipariş taslağı açıldı: "${yeni.name}".` : `Kopya oluşturuldu: "${yeni.name}".`);
      state.reload();
    } catch (hata) {
      push(messageOf(hata), 'error');
    }
  };

  const topluGorunurluk = async (yeni: Visibility) => {
    const hedefler = listeler.filter((l) => secili.has(l.id));
    try {
      await Promise.all(hedefler.map((l) => listsApi.update(l.id, { visibility: yeni })));
      push(`${hedefler.length} liste ${yeni === 'archived' ? 'arşivlendi' : yeni === 'passive' ? 'pasife alındı' : 'aktifleştirildi'}.`, 'success', async () => {
        await Promise.all(hedefler.map((l) => listsApi.update(l.id, { visibility: l.visibility })));
        state.reload();
        push('Geri alındı.');
      });
      setSecili(new Set());
      state.reload();
    } catch (hata) {
      push(messageOf(hata), 'error');
    }
  };

  const topluCop = async () => {
    const hedefler = listeler.filter((l) => secili.has(l.id));
    try {
      await Promise.all(hedefler.map((l) => listsApi.remove(l.id)));
      push(`${hedefler.length} liste çöp kutusuna taşındı (30 gün).`, 'success', async () => {
        await Promise.all(hedefler.map((l) => trashApi.restore('lists', l.id)));
        state.reload();
        push('Geri alındı.');
      });
      setSecili(new Set());
      state.reload();
    } catch (hata) {
      push(messageOf(hata), 'error');
    }
  };

  const sablonSil = (s: ListeSablonu) =>
    geriAl.ertelenmis(`"${s.ad}" şablonu silinecek.`, () => sablonlarApi.sil(s.id), sablonlar.reload);

  const sablondanListe = async (s: ListeSablonu) => {
    try {
      const liste = await sablonlarApi.listeOlustur(s.id, {});
      push(`"${s.ad}" şablonundan taslak açıldı: ${liste.product_count} ürün.`);
      sablonlar.reload();
      navigate(`/listeler/${liste.id}`);
    } catch (hata) {
      push(messageOf(hata), 'error');
    }
  };

  const sorguDurumu: Record<string, string> = { gorunurluk, sekme, sirala, grup };

  return (
    <>
      <PageHeader
        title="Listeler"
        subtitle="Tedarik listelerini ve ilerleyen süreçleri tek merkezden yönetin."
        actions={
          <div className="flex gap-2">
            <button type="button" className="btn-ghost" onClick={() => setSablonAcik((v) => !v)} aria-expanded={sablonAcik}>
              <BookTemplate className="h-4 w-4" aria-hidden />
              Şablonlar{sablonlar.data && sablonlar.data.length ? ` (${sablonlar.data.length})` : ''}
            </button>
            <button type="button" className="btn-primary" onClick={() => setOlusturAcik((v) => !v)}>
              <Plus className="h-4 w-4" aria-hidden />
              Yeni liste
            </button>
          </div>
        }
      />

      {meta ? (
        <div className="mb-4 grid gap-3 sm:grid-cols-3" data-testid="kpi-seridi">
          <KpiKarti
            simge={<Clock3 className="h-5 w-5" aria-hidden />}
            baslik={`${count(meta.kpi.fiyat_bekleyen_liste)} liste fiyat bekliyor`}
            alt={meta.kpi.fiyatlanmayan_satir ? `${count(meta.kpi.fiyatlanmayan_satir)} satır fiyatlanmadı` : 'Firma cevabı bekleniyor'}
            eylem="İncele"
            onClick={() => paramYaz({ sekme: 'fiyat_bekleniyor', sayfa: null })}
          />
          <KpiKarti
            simge={<Gavel className="h-5 w-5" aria-hidden />}
            baslik={`${count(meta.kpi.karar_bekleyen_liste)} liste kararını bekliyor`}
            alt="Firma cevapladı"
            eylem="Karar ver"
            onClick={() => paramYaz({ sekme: 'degerlendirmede', sayfa: null })}
          />
          <KpiKarti
            simge={<AlertTriangle className="h-5 w-5" aria-hidden />}
            baslik={`${count(meta.kpi.suresi_dolan_teklif)} teklifin süresi doluyor`}
            alt="48 saat içinde"
            eylem="Teklifleri aç"
            onClick={() => navigate('/teklifler')}
          />
        </div>
      ) : null}

      {olusturAcik ? (
        <CreateForm
          onCancel={() => setOlusturAcik(false)}
          onCreated={() => {
            setOlusturAcik(false);
            paramYaz({ gorunurluk: 'active', sekme: null, sayfa: null });
            state.reload();
            push('Liste oluşturuldu.');
          }}
        />
      ) : null}

      {sablonAcik ? (
        <section className="card mb-4 p-4" data-testid="sablon-paneli" aria-label="Liste şablonları">
          <h2 className="mb-2 text-sm font-semibold text-ink-2">Liste şablonları</h2>
          {sablonlar.loading ? <Skeleton rows={2} /> : null}
          {sablonlar.error ? <ErrorNote message={sablonlar.error} onRetry={sablonlar.reload} /> : null}
          {sablonlar.data && sablonlar.data.length === 0 ? (
            <p className="text-sm text-ink-3">Henüz şablon yok. Bir listenin ⋯ menüsünden "Şablon olarak kaydet" ile başlayın; her sezon aynı ürün kümesi tek tıkla taslak olur.</p>
          ) : null}
          <ul className="divide-y divide-line-soft">
            {(sablonlar.data ?? []).map((s) => (
              <li key={s.id} className="flex flex-wrap items-center gap-3 py-2 text-sm" data-testid={`sablon-${s.id}`}>
                <div className="min-w-0 flex-1">
                  <div className="font-medium text-ink">{s.ad}</div>
                  <div className="text-xs text-ink-3">
                    {count(s.urun_sayisi)} ürün · {s.ornek_urunler.join(', ')}
                    {s.kullanim_sayisi ? ` · ${count(s.kullanim_sayisi)} kez kullanıldı` : ''}
                  </div>
                </div>
                <button type="button" className="btn-primary !min-h-8 !text-xs" onClick={() => void sablondanListe(s)}>
                  Bu şablondan liste aç
                </button>
                <button type="button" className="btn-ghost !min-h-8 !text-xs text-err" onClick={() => sablonSil(s)}>
                  <Trash2 className="h-3.5 w-3.5" aria-hidden /> Sil
                </button>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {sablonKaynak ? (
        <SablonKaydetFormu
          liste={sablonKaynak}
          onKapat={() => setSablonKaynak(null)}
          onKaydedildi={(ad) => {
            setSablonKaynak(null);
            sablonlar.reload();
            push(`"${ad}" şablonu kaydedildi.`);
          }}
        />
      ) : null}

      <div className="mb-3 flex flex-wrap items-center gap-2">
        <div className="flex overflow-hidden rounded-xl border border-line bg-surface" role="tablist" aria-label="Görünürlük">
          {GORUNURLUKLER.map((g) => (
            <button
              key={g}
              type="button"
              role="tab"
              aria-selected={gorunurluk === g}
              className={`min-h-10 px-4 text-sm font-semibold ${gorunurluk === g ? 'bg-navy text-white' : 'text-ink-2 hover:bg-g50'}`}
              onClick={() => {
                setSecili(new Set());
                paramYaz({ gorunurluk: g, sayfa: null });
              }}
            >
              {visibilityLabels[g]}
            </button>
          ))}
        </div>
        <div className="flex flex-wrap gap-1" role="tablist" aria-label="Durum">
          {SEKMELER.map((s) => {
            const sayi = meta?.sayimlar[s.deger] ?? 0;
            return (
              <button
                key={s.deger}
                type="button"
                role="tab"
                aria-selected={sekme === s.deger}
                className={`inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm ${sekme === s.deger ? 'bg-navy text-white' : 'bg-g100 text-ink-2 hover:bg-line'}`}
                onClick={() => {
                  setSecili(new Set());
                  paramYaz({ sekme: s.deger, sayfa: null });
                }}
              >
                {s.etiket}
                {sayi > 0 ? <span className={`rounded-full px-1.5 text-xs ${sekme === s.deger ? 'bg-white/20' : 'bg-surface'}`}>{sayi}</span> : null}
              </button>
            );
          })}
        </div>
      </div>

      <div className="mb-3 flex flex-wrap items-center gap-2">
        <label className="relative min-w-[220px] flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" aria-hidden />
          <input ref={aramaKutusu} className="field-input pl-9" placeholder="Liste, firma veya ürün ara  ( / )" value={arama.deger} onChange={(olay) => arama.yaz(olay.target.value)} aria-label="Liste ara" />
        </label>
        <label className="flex items-center gap-1 text-sm text-ink-2">
          Sırala:
          <select className="field-input !min-h-9 !w-auto" value={sirala} onChange={(olay) => paramYaz({ sirala: olay.target.value })} aria-label="Sıralama">
            <option value="son_islem">Son işlem</option>
            <option value="ad">Ad</option>
            <option value="urun">Ürün sayısı</option>
            <option value="fiyatlama">Fiyatlanma</option>
          </select>
        </label>
        <label className="flex items-center gap-1 text-sm text-ink-2">
          Grupla:
          <select className="field-input !min-h-9 !w-auto" value={grup} onChange={(olay) => paramYaz({ grup: olay.target.value })} aria-label="Gruplama">
            <option value="donem">Dönem</option>
            <option value="yok">Yok</option>
          </select>
        </label>
        <TabloAyarlari ekran={EKRAN} sutunlar={SUTUNLAR} tercih={tercih} onTercih={setTercih} sorgu={sorguDurumu} onGorunumUygula={(s) => paramYaz({ ...s, sayfa: null })} />
        <button type="button" className="btn-ghost !min-h-9 !px-2 text-xs" onClick={() => setKisayollar((v) => !v)} aria-label="Klavye kısayolları">
          ?
        </button>
      </div>

      {kisayollar ? (
        <div className="mb-3 rounded-lg border border-line bg-surface-2 p-3 text-xs text-ink-2" data-testid="kisayol-karti">
          <b>Klavye:</b> <kbd>/</kbd> arama · <kbd>J</kbd>/<kbd>K</kbd> satır · <kbd>Enter</kbd> aç · <kbd>Space</kbd> seç · <kbd>Esc</kbd> seçimi temizle · <kbd>?</kbd> bu kart · sağ tık satır menüsü
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-[1fr_240px]">
        <div>
          {state.loading ? (
            <Skeleton rows={4} />
          ) : state.error ? (
            <ErrorNote message={state.error} onRetry={state.reload} />
          ) : listeler.length === 0 ? (
            <EmptyState
              title={sekme === 'tumu' && gorunurluk === 'active' && !arama.gecikmeli ? 'Henüz listen yok' : 'Bu kesitte liste yok'}
              description={
                sekme === 'tumu' && gorunurluk === 'active' && !arama.gecikmeli
                  ? 'Tedarik işini bir listeyle başlat; ürünleri sonra tek tek ekleyebilirsin.'
                  : 'Filtreyi değiştir ya da arama sözcüğünü sadeleştir.'
              }
              action={
                sekme === 'tumu' && gorunurluk === 'active' && !arama.gecikmeli ? (
                  <button type="button" className="btn-primary" onClick={() => setOlusturAcik(true)}>
                    İlk listeni oluştur
                  </button>
                ) : (
                  <button type="button" className="btn-ghost" onClick={() => paramYaz({ sekme: null, q: null, sayfa: null })}>
                    Filtreleri temizle
                  </button>
                )
              }
            />
          ) : (
            <div className="table-scroll rounded-lg border border-line bg-surface">
              <table className={`w-full border-collapse text-sm ${tercih.yogunluk === 'sik' ? '[--satir:44px]' : '[--satir:72px]'}`} data-testid="listeler-tablosu">
                <thead className="sticky top-0 z-10 bg-g50 text-left text-xs font-bold tracking-wide text-ink-3">
                  <tr>
                    <th className="w-8 px-3 py-2">
                      <input
                        type="checkbox"
                        aria-label="Bu sayfadaki tümünü seç"
                        checked={sayfadakiler.length > 0 && sayfadakiler.every((l) => secili.has(l.id))}
                        onChange={(olay) => setSecili(olay.target.checked ? new Set(sayfadakiler.map((l) => l.id)) : new Set())}
                      />
                    </th>
                    {SUTUNLAR.filter((s) => !tercih.gizli.includes(s.anahtar)).map((s) => (
                      <th key={s.anahtar} className={`px-3 py-2 ${s.anahtar === 'urun' || s.anahtar === 'ddp' ? 'text-right' : ''}`}>
                        {s.etiket}
                      </th>
                    ))}
                    <th className="w-24 px-3 py-2 text-right">
                      <span className="sr-only">Eylemler</span>
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line-soft">
                  {gruplar.map(([donem, grupListeleri]) => (
                    <GrupSatirlari key={donem || '_'} baslik={grup === 'donem' ? donem : null} sutunSayisi={SUTUNLAR.length + 2}>
                      {grupListeleri.map((l) => (
                        <ListeSatiri
                          key={l.id}
                          liste={l}
                          gizli={tercih.gizli}
                          secili={secili.has(l.id)}
                          odakta={sayfadakiler[odak]?.id === l.id}
                          onSec={() => sec(l.id)}
                          eylemler={[
                            { etiket: 'Aç', onClick: () => navigate(`/listeler/${l.id}`), simge: <ArrowRight className="h-4 w-4" />, kisayol: 'Enter' },
                            { etiket: 'Kopyala', onClick: () => void kopyala(l), simge: <Copy className="h-4 w-4" /> },
                            ...(l.sekme === 'onayli' || l.sekme === 'tamamlandi'
                              ? [{ etiket: 'Tekrar sipariş', onClick: () => void kopyala(l, true), simge: <RotateCcw className="h-4 w-4" /> }]
                              : []),
                            { etiket: 'Şablon olarak kaydet', onClick: () => setSablonKaynak(l), simge: <BookTemplate className="h-4 w-4" />, devreDisi: l.product_count === 0 },
                            ...(l.visibility !== 'passive' ? [{ etiket: 'Pasife al', onClick: () => void gorunurlukDegistir(l, 'passive'), simge: <EyeOff className="h-4 w-4" />, ayrac: true }] : []),
                            ...(l.visibility !== 'archived' ? [{ etiket: 'Arşivle', onClick: () => void gorunurlukDegistir(l, 'archived'), simge: <Archive className="h-4 w-4" /> }] : []),
                            ...(l.visibility !== 'active' ? [{ etiket: 'Aktife al', onClick: () => void gorunurlukDegistir(l, 'active'), simge: <RotateCcw className="h-4 w-4" /> }] : []),
                            { etiket: 'Çöpe at (geri alınabilir)', onClick: () => void copeAt(l), simge: <Trash2 className="h-4 w-4" />, tehlikeli: true, ayrac: true },
                          ]}
                        />
                      ))}
                    </GrupSatirlari>
                  ))}
                </tbody>
              </table>
              <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line px-3 py-2 text-xs text-ink-3">
                <span>
                  {count(listeler.length)} liste · {count(listeler.reduce((t, l) => t + l.product_count, 0))} ürün · ₺{money(kurusMetin(kurusToplam))} DDP
                </span>
                {toplamSayfa > 1 ? (
                  <span className="flex items-center gap-2">
                    <button type="button" className="btn-ghost !min-h-7 !px-2" disabled={sayfa <= 1} onClick={() => paramYaz({ sayfa: String(sayfa - 1) })}>
                      ‹
                    </button>
                    Sayfa {sayfa} / {toplamSayfa}
                    <button type="button" className="btn-ghost !min-h-7 !px-2" disabled={sayfa >= toplamSayfa} onClick={() => paramYaz({ sayfa: String(sayfa + 1) })}>
                      ›
                    </button>
                  </span>
                ) : null}
                <span>Sütunlar ve filtreler görünüm bazında hatırlanır.</span>
              </div>
            </div>
          )}

          <SecimCubugu seciliSayisi={secili.size} sayfadaki={sayfadakiler.length} eslesenToplam={listeler.length} onTemizle={() => setSecili(new Set())} birim="liste">
            <button type="button" className="btn-ghost !min-h-8 !text-xs !text-white hover:!bg-white/10" onClick={() => void topluGorunurluk('passive')}>
              <EyeOff className="h-3.5 w-3.5" aria-hidden /> Pasife al
            </button>
            <button type="button" className="btn-ghost !min-h-8 !text-xs !text-white hover:!bg-white/10" onClick={() => void topluGorunurluk('archived')}>
              <Archive className="h-3.5 w-3.5" aria-hidden /> Arşivle
            </button>
            <button type="button" className="btn-ghost !min-h-8 !text-xs !text-white hover:!bg-white/10" onClick={() => void topluCop()}>
              <Trash2 className="h-3.5 w-3.5" aria-hidden /> Çöpe at
            </button>
          </SecimCubugu>
        </div>

        <aside className="card h-fit p-4 text-sm" aria-label="Liste sağlığı" data-testid="liste-sagligi">
          <h2 className="mb-2 font-semibold text-ink-2">Liste sağlığı</h2>
          <ul className="space-y-1">
            <SaglikSatiri sayi={saglik.fiyat_bekleyen} etiket="fiyat bekleyen" onClick={() => paramYaz({ sekme: 'fiyat_bekleniyor', sayfa: null })} />
            <SaglikSatiri sayi={saglik.kur_sapmasi} etiket="kur sapması" onClick={() => paramYaz({ sirala: 'son_islem' })} />
            <SaglikSatiri sayi={saglik.teklif_suresi} etiket="teklif süresi" onClick={() => paramYaz({ sekme: 'fiyat_bekleniyor', sayfa: null })} />
            <SaglikSatiri sayi={saglik.cikti_guncel_degil} etiket="çıktı güncel değil" onClick={() => paramYaz({ sirala: 'son_islem' })} />
          </ul>
          <p className="mt-2 text-xs text-ink-3">Tablodaki sonuçları filtreler.</p>
        </aside>
      </div>
    </>
  );
}

// ── parçalar ────────────────────────────────────────────────────────

function KpiKarti({ simge, baslik, alt, eylem, onClick }: { simge: React.ReactNode; baslik: string; alt: string; eylem: string; onClick: () => void }) {
  return (
    <button type="button" className="card flex items-center gap-3 p-4 text-left hover:bg-g50" onClick={onClick}>
      <span className="rounded-full bg-warn-soft p-2 text-warn">{simge}</span>
      <span className="min-w-0 flex-1">
        <span className="block truncate font-semibold text-ink">{baslik}</span>
        <span className="block text-xs text-ink-3">{alt}</span>
      </span>
      <span className="inline-flex items-center gap-1 text-sm font-medium text-navy">
        {eylem} <ArrowRight className="h-4 w-4" aria-hidden />
      </span>
    </button>
  );
}

function SaglikSatiri({ sayi, etiket, onClick }: { sayi: number; etiket: string; onClick: () => void }) {
  return (
    <li>
      <button type="button" className="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left hover:bg-g50" onClick={onClick}>
        {sayi > 0 ? <TriangleAlert className="h-4 w-4 text-warn" aria-hidden /> : <Check className="h-4 w-4 text-ok" aria-hidden />}
        <span className={sayi > 0 ? 'font-medium text-ink' : 'text-ink-3'}>
          {count(sayi)} {etiket}
        </span>
      </button>
    </li>
  );
}

function GrupSatirlari({ baslik, sutunSayisi, children }: { baslik: string | null; sutunSayisi: number; children: React.ReactNode }) {
  return (
    <>
      {baslik !== null ? (
        <tr className="bg-surface-2">
          <th colSpan={sutunSayisi} className="px-3 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-3">
            {baslik}
          </th>
        </tr>
      ) : null}
      {children}
    </>
  );
}

function ListeSatiri({
  liste,
  gizli,
  secili,
  odakta,
  onSec,
  eylemler,
}: {
  liste: SupplyList;
  gizli: string[];
  secili: boolean;
  odakta: boolean;
  onSec: () => void;
  eylemler: SatirEylemi[];
}) {
  const menu = useSatirEylemMenusu();
  const navigate = useNavigate();
  const adim = ADIM_INDEKSI[liste.sekme];
  const goster = (anahtar: string) => !gizli.includes(anahtar);
  const bekleme = liste.tur_ozeti?.sent_at ? gunFarki(liste.tur_ozeti.sent_at) : null;

  return (
    <tr
      className={`h-[var(--satir)] transition-colors ${secili ? 'bg-blue-soft' : odakta ? 'bg-g50' : 'hover:bg-g50'}`}
      data-testid={`liste-${liste.id}`}
      onContextMenu={menu.sagTik}
      onDoubleClick={() => navigate(`/listeler/${liste.id}`)}
    >
      <td className="px-3 py-2 align-middle">
        <input type="checkbox" aria-label={`Seç: ${liste.name}`} checked={secili} onChange={onSec} />
      </td>
      {goster('liste') ? (
        <td className="px-3 py-2 align-middle">
          <Link to={`/listeler/${liste.id}`} className="block truncate font-semibold text-ink hover:underline">
            {liste.name}
          </Link>
          <div className="truncate text-xs text-ink-3">{liste.supplier_name ?? 'Firma belirtilmedi'}</div>
        </td>
      ) : null}
      {goster('durum') ? (
        <td className="px-3 py-2 align-middle">
          <span className={`badge ${SEKME_TONU[liste.sekme]}`}>{SEKME_ETIKETI[liste.sekme]}</span>
          <ol className="mt-1 flex items-center gap-1" aria-label="İlerleme">
            {ADIMLAR.map((a, i) => (
              <li key={a} className="flex items-center gap-1 text-[10px] text-ink-3" title={a}>
                <span className={`inline-block h-2.5 w-2.5 rounded-full ${i < adim ? 'bg-ok' : i === adim ? 'bg-warn' : 'bg-g200'}`} />
                {i < ADIMLAR.length - 1 ? <span className="h-px w-3 bg-line" /> : null}
              </li>
            ))}
          </ol>
        </td>
      ) : null}
      {goster('urun') ? <td className="px-3 py-2 text-right tabular-nums align-middle">{count(liste.product_count)} ürün</td> : null}
      {goster('fiyatlama') ? (
        <td className="px-3 py-2 align-middle" data-testid={`fiyatlama-${liste.id}`}>
          <div className="text-sm tabular-nums">
            {liste.fiyatlama.fiyatlanan}/{liste.fiyatlama.toplam}, %{liste.fiyatlama.yuzde}
          </div>
          <div className="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-g100">
            <span className={`block h-full ${liste.fiyatlama.yuzde >= 100 ? 'bg-ok' : liste.fiyatlama.yuzde > 0 ? 'bg-warn' : 'bg-g300'}`} style={{ width: `${liste.fiyatlama.yuzde}%` }} />
          </div>
        </td>
      ) : null}
      {goster('ddp') ? <td className="px-3 py-2 text-right tabular-nums align-middle">{liste.totals.ddp_tl !== '0.00' ? `₺${money(liste.totals.ddp_tl)}` : <span className="text-ink-3">—</span>}</td> : null}
      {goster('teklif') ? (
        <td className="px-3 py-2 align-middle text-xs">
          {liste.sekme === 'degerlendirmede' ? (
            <Link to={`/listeler/${liste.id}`} className="btn-primary !min-h-8 !text-xs">
              Karar ver
            </Link>
          ) : liste.tur_ozeti && bekleme !== null ? (
            <>
              <div>{bekleme === 0 ? 'bugün gönderildi' : `${bekleme} gündür bekliyor`}</div>
              {!liste.tur_ozeti.first_viewed_at && bekleme >= 3 ? (
                <div className="text-warn">
                  <TriangleAlert className="mr-0.5 inline h-3 w-3" aria-hidden /> Firma hatırlatılmalı
                </div>
              ) : null}
            </>
          ) : (
            <div className="text-ink-3">{dateTime(liste.updated_at)} güncellendi</div>
          )}
          {liste.saglik.includes('cikti_guncel_degil') ? <span className="badge mt-1 bg-warn-soft text-warn ring-warn/20">Çıktı güncel değil</span> : null}
          {liste.saglik.includes('kur_sapmasi') ? <span className="badge mt-1 bg-warn-soft text-warn ring-warn/20">Kur sapması</span> : null}
        </td>
      ) : null}
      <td className="px-3 py-2 text-right align-middle">
        <span className="inline-flex items-center gap-1">
          <SatirEylemMenusu menu={menu} ogeler={eylemler} etiket={`Eylemler: ${liste.name}`} />
          <Link to={`/listeler/${liste.id}`} className="btn-ghost !min-h-8 !px-2" aria-label={`Aç: ${liste.name}`}>
            <ArrowRight className="h-4 w-4" aria-hidden />
          </Link>
        </span>
      </td>
    </tr>
  );
}

function SablonKaydetFormu({ liste, onKapat, onKaydedildi }: { liste: SupplyList; onKapat: () => void; onKaydedildi: (ad: string) => void }) {
  const [ad, setAd] = useState(liste.name);
  const [aciklama, setAciklama] = useState('');
  const [busy, setBusy] = useState(false);
  const [hata, setHata] = useState<string | null>(null);

  const gonder = async (olay: FormEvent) => {
    olay.preventDefault();
    setBusy(true);
    setHata(null);
    try {
      await sablonlarApi.listedenOlustur(liste.id, { ad: ad.trim(), aciklama: aciklama.trim() || undefined });
      onKaydedildi(ad.trim());
    } catch (caught) {
      setHata(messageOf(caught));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={gonder} className="card mb-4 space-y-3 p-4" data-testid="sablon-kaydet-formu">
      <p className="text-sm text-ink-2">
        <b>{liste.name}</b> listesinin {count(liste.product_count)} ürünü şablon olarak dondurulacak; şablon listeye bağlı değildir.
      </p>
      <Field label="Şablon adı">
        <input className="field-input" value={ad} onChange={(olay) => setAd(olay.target.value)} required autoFocus />
      </Field>
      <Field label="Açıklama" hint="İsteğe bağlı">
        <input className="field-input" value={aciklama} onChange={(olay) => setAciklama(olay.target.value)} />
      </Field>
      {hata ? <p className="text-sm font-medium text-err">{hata}</p> : null}
      <div className="flex gap-2">
        <button type="submit" className="btn-primary" disabled={busy || ad.trim() === ''}>
          Şablonu kaydet
        </button>
        <button type="button" className="btn-ghost" onClick={onKapat}>
          Vazgeç
        </button>
      </div>
    </form>
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
      await listsApi.create({ name: name.trim(), period: period.trim() || undefined, supplier_name: supplier.trim() || undefined });
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
        <Field label="Firma">
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

// ── yardımcılar ─────────────────────────────────────────────────────

/** "1234.56" → 123456 (tam sayı kuruş). Para aritmetiği YALNIZ burada ve yalnız toplama. */
function kurus(deger: string): number {
  const m = /^(\d+)(?:\.(\d{1,2}))?$/.exec(deger);
  if (!m) return 0;
  return Number(m[1]) * 100 + Number((m[2] ?? '0').padEnd(2, '0'));
}

function kurusMetin(k: number): string {
  return `${Math.floor(k / 100)}.${String(k % 100).padStart(2, '0')}`;
}

function gunFarki(iso: string): number {
  const fark = Date.now() - new Date(iso).getTime();
  return Math.max(0, Math.floor(fark / 86_400_000));
}
