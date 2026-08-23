import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Bookmark, Compass, Layers, Save, Scale, Search, Trash2 } from 'lucide-react';
import { kesif as kesifApi, type KesifGorunumu, type KesifSatiri } from '../api/kesif';
import { useAsync, messageOf } from '../lib/useAsync';
import { metniNormalize } from '../lib/metin';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';
import KesifFiltreleri, { type FiltreDurumu } from './kesif/KesifFiltreleri';
import KarsilastirmaMatrisi from './kesif/KarsilastirmaMatrisi';

/**
 * KEŞİF HAVUZU (İE#21 B1) — V3'ün kalbi.
 *
 * Soru "hangisini alalım" değil, "elimizde ne var ve hangisi iyi"dir.
 *
 * DURUM URL'DE YAŞAR (E2E-PNL-01/12). Bunun üç somut karşılığı var: sayfa
 * yenilendiğinde filtre kaybolmaz · görünüm bağlantı olarak paylaşılabilir ·
 * tarayıcının geri tuşu filtreyi geri alır. Durumu bileşen içinde tutup URL'i
 * "sonradan" güncellemek üçünü de bozar; bu yüzden TEK kaynak arama parametreleridir.
 */

const SIRALAMALAR: { deger: string; etiket: string }[] = [
  { deger: 'skor', etiket: 'Skor' },
  { deger: 'ivme', etiket: 'İvme' },
  { deger: 'satis', etiket: 'Satış' },
  { deger: 'puan', etiket: 'Puan' },
  { deger: 'fiyat', etiket: 'Fiyat' },
  { deger: 'tarih', etiket: 'Yakalanma' },
];

const BANT_STIL: Record<string, string> = {
  yuksek: 'bg-ok-soft text-ok ring-ok/20',
  orta: 'bg-warn-soft text-warn ring-warn/20',
  dusuk: 'bg-g100 text-ink-3 ring-line',
  gizli: 'bg-g100 text-ink-3 ring-line',
};

const BANT_ETIKET: Record<string, string> = {
  yuksek: 'Yüksek',
  orta: 'Orta',
  dusuk: 'Düşük',
  gizli: 'Veri yetersiz',
};

export default function KesifScreen() {
  const [params, setParams] = useSearchParams();
  const push = useToast((state) => state.push);

  const filtre = useMemo<FiltreDurumu>(
    () => ({
      q: params.get('q') ?? '',
      platform: params.getAll('platform'),
      kategori: params.getAll('kategori'),
      skor_bandi: params.getAll('skor_bandi'),
      fiyat_min: params.get('fiyat_min') ?? '',
      fiyat_max: params.get('fiyat_max') ?? '',
      moq_max: params.get('moq_max') ?? '',
      puan_min: params.get('puan_min') ?? '',
      video: params.get('video') === '1',
      listede: (params.get('listede') ?? '') as FiltreDurumu['listede'],
      mod: params.get('mod') ?? '',
    }),
    [params],
  );

  const siralama = params.get('siralama') ?? 'skor';
  const kumeli = params.get('kumele') === '1';
  // Sayfa numarası PARA DEĞİLDİR ama proje kuralı `parseInt`i tümden yasaklar
  // (K14/K29 — para JS'te hesaplanmaz). Kuralın etrafından dolaşmak yerine
  // sayısal dönüşüm Number() ile yapılır; geçersiz değer 1'e düşer.
  const sayfaHam = Number(params.get('sayfa') ?? '1');
  const sayfa = Number.isFinite(sayfaHam) && sayfaHam >= 1 ? Math.floor(sayfaHam) : 1;

  const [aramaKutusu, setAramaKutusu] = useState(filtre.q);
  const [secili, setSecili] = useState<number[]>([]);
  const [karsilastirma, setKarsilastirma] = useState<{
    urunler: KesifSatiri[];
    enIyiler: Record<string, number | null>;
  } | null>(null);

  // Arama kutusu URL'den TOHUMLANIR (geri tuşu ve kaydedilmiş görünüm için).
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setAramaKutusu(filtre.q);
  }, [filtre.q]);

  const sorguDizesi = useMemo(() => {
    const sorgu = new URLSearchParams();
    if (filtre.q) sorgu.set('q', filtre.q);
    filtre.platform.forEach((p) => sorgu.append('platform', p));
    filtre.kategori.forEach((k) => sorgu.append('kategori', k));
    filtre.skor_bandi.forEach((b) => sorgu.append('skor_bandi', b));
    if (filtre.fiyat_min) sorgu.set('fiyat_min', filtre.fiyat_min);
    if (filtre.fiyat_max) sorgu.set('fiyat_max', filtre.fiyat_max);
    if (filtre.moq_max) sorgu.set('moq_max', filtre.moq_max);
    if (filtre.puan_min) sorgu.set('puan_min', filtre.puan_min);
    if (filtre.video) sorgu.set('video', '1');
    if (filtre.listede) sorgu.set('listede', filtre.listede);
    if (filtre.mod) sorgu.set('mod', filtre.mod);
    sorgu.set('siralama', siralama);
    if (kumeli) sorgu.set('kumele', '1');
    if (sayfa > 1) sorgu.set('sayfa', String(sayfa));

    return sorgu;
  }, [filtre, siralama, kumeli, sayfa]);

  const durum = useAsync((signal) => kesifApi.ara(sorguDizesi, signal), [sorguDizesi.toString()]);
  const gorunumler = useAsync((signal) => kesifApi.gorunumler(signal), []);

  const veri = durum.data;

  /** URL'i günceller — sayfa numarası filtre değişince BAŞA döner. */
  const filtreDegis = useCallback(
    (yeni: Partial<FiltreDurumu>) => {
      const sonraki = { ...filtre, ...yeni };
      const sorgu = new URLSearchParams();
      if (sonraki.q) sorgu.set('q', sonraki.q);
      sonraki.platform.forEach((p) => sorgu.append('platform', p));
      sonraki.kategori.forEach((k) => sorgu.append('kategori', k));
      sonraki.skor_bandi.forEach((b) => sorgu.append('skor_bandi', b));
      if (sonraki.fiyat_min) sorgu.set('fiyat_min', sonraki.fiyat_min);
      if (sonraki.fiyat_max) sorgu.set('fiyat_max', sonraki.fiyat_max);
      if (sonraki.moq_max) sorgu.set('moq_max', sonraki.moq_max);
      if (sonraki.puan_min) sorgu.set('puan_min', sonraki.puan_min);
      if (sonraki.video) sorgu.set('video', '1');
      if (sonraki.listede) sorgu.set('listede', sonraki.listede);
      if (sonraki.mod) sorgu.set('mod', sonraki.mod);
      sorgu.set('siralama', siralama);
      if (kumeli) sorgu.set('kumele', '1');
      setParams(sorgu);
    },
    [filtre, siralama, kumeli, setParams],
  );

  const tekilDegis = (alan: string, deger: string) => {
    const sorgu = new URLSearchParams(sorguDizesi);
    if (deger === '') sorgu.delete(alan);
    else sorgu.set(alan, deger);
    sorgu.delete('sayfa');
    setParams(sorgu);
  };

  const gorunumUygula = (gorunum: KesifGorunumu) => {
    const sorgu = new URLSearchParams();
    Object.entries(gorunum.sorgu).forEach(([anahtar, deger]) => {
      if (deger !== '') sorgu.set(anahtar, String(deger));
    });
    setParams(sorgu);
  };

  // Varsayılan görünüm, URL BOŞSA uygulanır — kullanıcının açık seçimi ezilmez.
  useEffect(() => {
    if (params.toString() !== '' || !gorunumler.data) return;
    const varsayilan = gorunumler.data.gorunumler.find((g) => g.varsayilan);
    if (varsayilan) gorunumUygula(varsayilan);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gorunumler.data]);

  const secimDegis = (id: number) => {
    setSecili((mevcut) => (mevcut.includes(id) ? mevcut.filter((x) => x !== id) : [...mevcut, id]));
  };

  const karsilastir = async () => {
    try {
      const sonuc = await kesifApi.karsilastir(secili);
      setKarsilastirma({ urunler: sonuc.urunler, enIyiler: sonuc.en_iyiler });
    } catch (hata) {
      push(messageOf(hata), 'error');
    }
  };

  const gorunumKaydet = async () => {
    const ad = window.prompt('Görünüm adı:');
    if (!ad) return;
    const sorgu: Record<string, string> = {};
    sorguDizesi.forEach((deger, anahtar) => {
      sorgu[anahtar] = deger;
    });
    try {
      await kesifApi.gorunumKaydet(ad, sorgu, false);
      gorunumler.reload();
      push('Görünüm kaydedildi.');
    } catch (hata) {
      push(messageOf(hata), 'error');
    }
  };

  const satirlar = veri?.satirlar ?? [];
  const toplamSayfa = veri ? Math.max(1, Math.ceil(veri.toplam / veri.limit)) : 1;

  return (
    <>
      <PageHeader
        title="Keşif"
        subtitle="Havuzdaki ürünleri süz, aynı ürünün kaynaklarını karşılaştır"
      />

      <section className="card mb-4 p-4">
        <form
          className="mb-3 flex gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            filtreDegis({ q: aramaKutusu.trim() });
          }}
        >
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-3" aria-hidden />
            <input
              className="field-input pl-9"
              placeholder="Türkçe ya da Çince ara — ilan no ve satıcı da aranır"
              value={aramaKutusu}
              onChange={(e) => setAramaKutusu(e.target.value)}
              aria-label="Havuzda ara"
            />
          </div>
          <button type="submit" className="btn-primary">Ara</button>
        </form>

        <KesifFiltreleri
          filtre={filtre}
          secenekler={veri?.secenekler}
          onDegis={filtreDegis}
          onTemizle={() => setParams(new URLSearchParams())}
        />
      </section>

      <div className="mb-3 flex flex-wrap items-center gap-2">
        <select
          className="field-input !min-h-9 !w-auto text-xs"
          value={siralama}
          onChange={(e) => tekilDegis('siralama', e.target.value)}
          aria-label="Sıralama"
        >
          {SIRALAMALAR.map((s) => (
            <option key={s.deger} value={s.deger}>{s.etiket}</option>
          ))}
        </select>

        <button
          type="button"
          className={kumeli ? 'btn-primary !min-h-9 !px-3 !text-xs' : 'btn-ghost !min-h-9 !px-3 !text-xs'}
          onClick={() => tekilDegis('kumele', kumeli ? '' : '1')}
        >
          <span className="inline-flex items-center gap-1.5">
            <Layers className="h-3.5 w-3.5" aria-hidden />
            Aynı ürünleri kümele
          </span>
        </button>

        <button type="button" className="btn-ghost !min-h-9 !px-3 !text-xs" onClick={() => void gorunumKaydet()}>
          <span className="inline-flex items-center gap-1.5">
            <Save className="h-3.5 w-3.5" aria-hidden />
            Görünümü kaydet
          </span>
        </button>

        {(gorunumler.data?.gorunumler ?? []).map((gorunum) => (
          <span key={gorunum.ad} className="inline-flex items-center gap-1 rounded-full bg-g100 px-2 py-1 text-xs">
            <button type="button" className="inline-flex items-center gap-1" onClick={() => gorunumUygula(gorunum)}>
              <Bookmark className="h-3 w-3" aria-hidden />
              {gorunum.ad}
            </button>
            <button
              type="button"
              aria-label={`${gorunum.ad} görünümünü sil`}
              onClick={async () => {
                await kesifApi.gorunumSil(gorunum.ad);
                gorunumler.reload();
              }}
            >
              <Trash2 className="h-3 w-3 text-ink-3" aria-hidden />
            </button>
          </span>
        ))}

        {secili.length >= 2 ? (
          <button type="button" className="btn-primary !min-h-9 !px-3 !text-xs ml-auto" onClick={() => void karsilastir()}>
            <span className="inline-flex items-center gap-1.5">
              <Scale className="h-3.5 w-3.5" aria-hidden />
              {secili.length} ürünü karşılaştır
            </span>
          </button>
        ) : null}
      </div>

      {karsilastirma ? (
        <KarsilastirmaMatrisi
          urunler={karsilastirma.urunler}
          enIyiler={karsilastirma.enIyiler}
          onKapat={() => setKarsilastirma(null)}
        />
      ) : null}

      {durum.loading ? (
        <Skeleton rows={6} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : !veri?.kurulu ? (
        <EmptyState
          icon={<Compass className="size-10 text-g300" aria-hidden />}
          title="Keşif havuzu hazır değil"
          description={veri?.mesaj ?? 'Veritabanı güncellemesi bekliyor olabilir.'}
        />
      ) : satirlar.length === 0 ? (
        <EmptyState
          icon={<Compass className="size-10 text-g300" aria-hidden />}
          title="Bu süzgeçle ürün yok"
          description="Filtreleri gevşetin ya da tümünü temizleyin."
          action={
            <button type="button" className="btn-ghost" onClick={() => setParams(new URLSearchParams())}>
              Filtreleri temizle
            </button>
          }
        />
      ) : (
        <>
          <p className="mb-2 text-xs text-ink-3">
            {veri.toplam} ürün · sayfa {veri.sayfa}/{toplamSayfa}
            {kumeli && veri.kumeler ? ` · ${veri.kumeler.length} küme` : ''}
          </p>

          <div className="card overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm" data-testid="kesif-tablosu">
              <thead>
                <tr className="border-b border-line text-left text-xs text-ink-3">
                  <th className="w-8 px-2 py-2"><span className="sr-only">Seç</span></th>
                  <th className="px-2 py-2">Ürün</th>
                  <th className="px-2 py-2">Kategori</th>
                  <th className="px-2 py-2">Platform</th>
                  <th className="px-2 py-2 text-right">Skor</th>
                  <th className="px-2 py-2 text-right">Fiyat</th>
                  <th className="px-2 py-2 text-right">MOQ</th>
                  <th className="px-2 py-2 text-right">Satış</th>
                  <th className="px-2 py-2 text-right">Puan</th>
                </tr>
              </thead>
              <tbody>
                {(kumeli && veri.kumeler ? veri.kumeler.map((k) => k.temsilci) : satirlar).map((satir) => {
                  const kume = kumeli
                    ? veri.kumeler?.find((k) => k.temsilci.id === satir.id)
                    : undefined;

                  return (
                    <tr key={satir.id} className="border-b border-line-soft" data-ilan={satir.ilan_no ?? ''}>
                      <td className="px-2 py-2">
                        <input
                          type="checkbox"
                          checked={secili.includes(satir.id)}
                          onChange={() => secimDegis(satir.id)}
                          aria-label={`${satir.ad} ürününü karşılaştırmaya ekle`}
                        />
                      </td>
                      <td className="max-w-[280px] px-2 py-2">
                        <span className="block truncate font-medium text-ink">{metniNormalize(satir.ad)}</span>
                        {satir.ad_orijinal ? (
                          <span className="block truncate text-xs text-ink-3">
                            {metniNormalize(satir.ad_orijinal)}
                          </span>
                        ) : null}
                        {kume && kume.kaynak_sayisi > 1 ? (
                          <span className="mt-0.5 inline-block rounded bg-navy/10 px-1.5 py-0.5 text-[11px] text-navy">
                            {kume.kaynak_sayisi} kaynak · en ucuz ¥{kume.en_ucuz}
                          </span>
                        ) : null}
                      </td>
                      <td className="px-2 py-2 text-xs text-ink-2">{satir.kategori ?? '—'}</td>
                      <td className="px-2 py-2 text-xs text-ink-2">{satir.platform ?? '—'}</td>
                      <td className="px-2 py-2 text-right">
                        <span
                          className={`inline-block rounded-full px-2 py-0.5 text-xs ring-1 ${BANT_STIL[satir.bant]}`}
                          title={satir.kapsam_disi ? 'Kapsam dışı: üst banda çıkamaz' : undefined}
                        >
                          {satir.skor === null ? BANT_ETIKET.gizli : `${satir.skor} · ${BANT_ETIKET[satir.bant]}`}
                        </span>
                      </td>
                      <td className="px-2 py-2 text-right text-xs tabular-nums">
                        {satir.birim_fiyat ? `¥${satir.birim_fiyat}` : '—'}
                      </td>
                      <td className="px-2 py-2 text-right text-xs tabular-nums">{satir.moq ?? '—'}</td>
                      <td className="px-2 py-2 text-right text-xs tabular-nums">{satir.satis ?? '—'}</td>
                      <td className="px-2 py-2 text-right text-xs tabular-nums">
                        {satir.puan === null ? '—' : satir.puan.toFixed(2)}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {toplamSayfa > 1 ? (
            <div className="mt-3 flex items-center justify-center gap-2">
              <button
                type="button"
                className="btn-ghost !min-h-9 !px-3 !text-xs"
                disabled={sayfa <= 1}
                onClick={() => tekilDegis('sayfa', String(sayfa - 1))}
              >
                Önceki
              </button>
              <span className="text-xs text-ink-3">{sayfa} / {toplamSayfa}</span>
              <button
                type="button"
                className="btn-ghost !min-h-9 !px-3 !text-xs"
                disabled={sayfa >= toplamSayfa}
                onClick={() => tekilDegis('sayfa', String(sayfa + 1))}
              >
                Sonraki
              </button>
            </div>
          ) : null}
        </>
      )}
    </>
  );
}
