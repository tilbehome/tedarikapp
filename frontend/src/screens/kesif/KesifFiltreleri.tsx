import { X } from 'lucide-react';
import type { KesifSonucu } from '../../api/kesif';

/**
 * KEŞİF FİLTRELERİ (İE#21 B1 · E2E-PNL-01/13).
 *
 * İki kural bu bileşenin tamamını açıklar:
 *
 * 1. **AKTİF FİLTRE ÇİP OLARAK GÖRÜNÜR.** Seçili ama görünmeyen bir filtre,
 *    kullanıcıya "sonuç neden bu kadar az?" diye sordurur ve cevabı bulunmaz.
 *    Her çipin kendi × düğmesi vardır: kaldırmak için filtreyi yeniden bulmak
 *    gerekmez.
 * 2. **FİLTRELER VE İLE BİRLEŞİR.** Bunu sunucu uygular; arayüz de aynı dili
 *    konuşur — çipler yan yana dizilir ve aralarında "veya" YAZMAZ.
 */

export interface FiltreDurumu {
  q: string;
  platform: string[];
  kategori: string[];
  skor_bandi: string[];
  fiyat_min: string;
  fiyat_max: string;
  moq_max: string;
  puan_min: string;
  video: boolean;
  listede: '' | '1' | '0';
  mod: string;
}

export const BOS_FILTRE: FiltreDurumu = {
  q: '',
  platform: [],
  kategori: [],
  skor_bandi: [],
  fiyat_min: '',
  fiyat_max: '',
  moq_max: '',
  puan_min: '',
  video: false,
  listede: '',
  mod: '',
};

const BANT_ETIKET: Record<string, string> = {
  yuksek: 'Yüksek',
  orta: 'Orta',
  dusuk: 'Düşük',
  gizli: 'Skor gizli',
};

const MOD_ETIKET: Record<string, string> = {
  yeni_yukselen: 'Yeni + Yükselen',
  kanitlanmis_cok_satan: 'Kanıtlanmış Çok Satan',
  mavi_okyanus: 'Mavi Okyanus',
  ucuz_yuksek_puan: 'Ucuz + Yüksek Puan',
};

interface Props {
  filtre: FiltreDurumu;
  secenekler: KesifSonucu['secenekler'] | undefined;
  onDegis: (yeni: Partial<FiltreDurumu>) => void;
  onTemizle: () => void;
}

export default function KesifFiltreleri({ filtre, secenekler, onDegis, onTemizle }: Props) {
  const cokluDegis = (alan: 'platform' | 'kategori' | 'skor_bandi', deger: string) => {
    const mevcut = filtre[alan];
    onDegis({
      [alan]: mevcut.includes(deger) ? mevcut.filter((d) => d !== deger) : [...mevcut, deger],
    } as Partial<FiltreDurumu>);
  };

  const aktifCipler: { etiket: string; kaldir: () => void }[] = [
    ...filtre.platform.map((p) => ({ etiket: `Platform: ${p}`, kaldir: () => cokluDegis('platform', p) })),
    ...filtre.kategori.map((k) => ({ etiket: `Kategori: ${k}`, kaldir: () => cokluDegis('kategori', k) })),
    ...filtre.skor_bandi.map((b) => ({
      etiket: `Skor: ${BANT_ETIKET[b] ?? b}`,
      kaldir: () => cokluDegis('skor_bandi', b),
    })),
    ...(filtre.fiyat_min ? [{ etiket: `Fiyat ≥ ¥${filtre.fiyat_min}`, kaldir: () => onDegis({ fiyat_min: '' }) }] : []),
    ...(filtre.fiyat_max ? [{ etiket: `Fiyat ≤ ¥${filtre.fiyat_max}`, kaldir: () => onDegis({ fiyat_max: '' }) }] : []),
    ...(filtre.moq_max ? [{ etiket: `MOQ ≤ ${filtre.moq_max}`, kaldir: () => onDegis({ moq_max: '' }) }] : []),
    ...(filtre.puan_min ? [{ etiket: `Puan ≥ ${filtre.puan_min}`, kaldir: () => onDegis({ puan_min: '' }) }] : []),
    ...(filtre.video ? [{ etiket: 'Videolu', kaldir: () => onDegis({ video: false }) }] : []),
    ...(filtre.listede ? [{
      etiket: filtre.listede === '1' ? 'Listeye girmiş' : 'Listeye girmemiş',
      kaldir: () => onDegis({ listede: '' as const }),
    }] : []),
    ...(filtre.mod ? [{ etiket: `Mod: ${MOD_ETIKET[filtre.mod] ?? filtre.mod}`, kaldir: () => onDegis({ mod: '' }) }] : []),
  ];

  return (
    <div className="space-y-3">
      {/* Hazır modlar: bir filtre ÖNAYARIDIR, kullanıcının açık seçimini ezmez. */}
      <div className="flex flex-wrap gap-2">
        {(secenekler?.modlar ?? []).map((mod) => (
          <button
            key={mod}
            type="button"
            className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
              filtre.mod === mod
                ? 'border-navy bg-navy text-white'
                : 'border-line bg-surface text-ink-2 hover:border-navy'
            }`}
            onClick={() => onDegis({ mod: filtre.mod === mod ? '' : mod })}
          >
            {MOD_ETIKET[mod] ?? mod}
          </button>
        ))}
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Coklu
          etiket="Platform"
          secenekler={secenekler?.platformlar ?? []}
          secili={filtre.platform}
          onSec={(d) => cokluDegis('platform', d)}
        />
        <Coklu
          etiket="Kategori"
          secenekler={secenekler?.kategoriler ?? []}
          secili={filtre.kategori}
          onSec={(d) => cokluDegis('kategori', d)}
        />
        <Coklu
          etiket="Skor bandı"
          secenekler={secenekler?.bantlar ?? []}
          secili={filtre.skor_bandi}
          etiketle={(d) => BANT_ETIKET[d] ?? d}
          onSec={(d) => cokluDegis('skor_bandi', d)}
        />
        <div className="space-y-1">
          <span className="text-xs font-medium text-ink-3">Sayısal</span>
          <div className="grid grid-cols-2 gap-2">
            <input
              className="field-input !min-h-9 text-xs"
              inputMode="decimal"
              placeholder="Fiyat ≥"
              value={filtre.fiyat_min}
              onChange={(e) => onDegis({ fiyat_min: e.target.value })}
            />
            <input
              className="field-input !min-h-9 text-xs"
              inputMode="decimal"
              placeholder="Fiyat ≤"
              value={filtre.fiyat_max}
              onChange={(e) => onDegis({ fiyat_max: e.target.value })}
            />
            <input
              className="field-input !min-h-9 text-xs"
              inputMode="numeric"
              placeholder="MOQ ≤"
              value={filtre.moq_max}
              onChange={(e) => onDegis({ moq_max: e.target.value })}
            />
            <input
              className="field-input !min-h-9 text-xs"
              inputMode="decimal"
              placeholder="Puan ≥"
              value={filtre.puan_min}
              onChange={(e) => onDegis({ puan_min: e.target.value })}
            />
          </div>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-1.5 text-xs text-ink-2">
          <input type="checkbox" checked={filtre.video} onChange={(e) => onDegis({ video: e.target.checked })} />
          Videolu
        </label>
        <select
          className="field-input !min-h-9 !w-auto text-xs"
          value={filtre.listede}
          onChange={(e) => onDegis({ listede: e.target.value as FiltreDurumu['listede'] })}
        >
          <option value="">Listede: hepsi</option>
          <option value="1">Listeye girmiş</option>
          <option value="0">Listeye girmemiş</option>
        </select>
      </div>

      {aktifCipler.length > 0 ? (
        <div className="flex flex-wrap items-center gap-2" data-testid="aktif-cipler">
          {aktifCipler.map((cip) => (
            <span
              key={cip.etiket}
              className="inline-flex items-center gap-1 rounded-full bg-navy/10 px-2.5 py-1 text-xs text-navy"
            >
              {cip.etiket}
              <button type="button" aria-label={`${cip.etiket} filtresini kaldır`} onClick={cip.kaldir}>
                <X className="h-3 w-3" aria-hidden />
              </button>
            </span>
          ))}
          <button type="button" className="text-xs font-medium text-ink-3 underline" onClick={onTemizle}>
            Tümünü temizle
          </button>
        </div>
      ) : null}
    </div>
  );
}

function Coklu({
  etiket,
  secenekler,
  secili,
  onSec,
  etiketle,
}: {
  etiket: string;
  secenekler: string[];
  secili: string[];
  onSec: (deger: string) => void;
  etiketle?: (deger: string) => string;
}) {
  return (
    <div className="space-y-1">
      <span className="text-xs font-medium text-ink-3">{etiket}</span>
      <div className="flex max-h-24 flex-wrap gap-1 overflow-auto rounded-lg border border-line bg-surface p-1.5">
        {secenekler.length === 0 ? (
          <span className="px-1 text-xs text-ink-3">—</span>
        ) : (
          secenekler.map((deger) => (
            <button
              key={deger}
              type="button"
              className={`rounded px-2 py-0.5 text-xs transition-colors ${
                secili.includes(deger) ? 'bg-navy text-white' : 'bg-g100 text-ink-2 hover:bg-g200'
              }`}
              onClick={() => onSec(deger)}
            >
              {etiketle ? etiketle(deger) : deger}
            </button>
          ))
        )}
      </div>
    </div>
  );
}
