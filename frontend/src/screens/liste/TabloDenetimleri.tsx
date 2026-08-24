import { useEffect, useRef, useState } from 'react';
import { Columns3, Layers, Rows3 } from 'lucide-react';
import { SUTUNLAR, type Gruplama, type SutunAnahtari, type TabloTercihi, type Yogunluk } from '../../lib/tabloTercihi';

/**
 * TABLO DENETİMLERİ (İE#21 B2 cilaları · referans: `liste-ici.png`).
 *
 * Üç denetim, üç somut soruya cevap:
 *   · **Sütunlar** — "bu sütunlar bana gürültü" (fiyat çalışan ile kargo takip eden
 *     aynı tabloya bakmaz),
 *   · **Yoğunluk** — "ekrana daha çok satır sığsın",
 *   · **Grupla** — "kategoriye göre görmek istiyorum".
 *
 * Denetimler URL'e değil cihaza yazılır (`tabloTercihi`): bunlar paylaşılacak bir
 * görünüm değil, kişisel bir konfor ayarıdır. Paylaşılması gerekenler (arama,
 * durum süzgeci) URL'de durmaya devam eder.
 */

interface Props {
  tercih: TabloTercihi;
  onDegis: (yeni: TabloTercihi) => void;
}

export default function TabloDenetimleri({ tercih, onDegis }: Props) {
  const [sutunMenusu, setSutunMenusu] = useState(false);
  const kapsayici = useRef<HTMLDivElement>(null);

  // Dışa tıklayınca menü kapanır: açık kalan bir menü, altındaki tabloyu gizler.
  useEffect(() => {
    if (!sutunMenusu) return;

    const dinle = (olay: MouseEvent) => {
      if (kapsayici.current && !kapsayici.current.contains(olay.target as Node)) {
        setSutunMenusu(false);
      }
    };
    document.addEventListener('mousedown', dinle);

    return () => document.removeEventListener('mousedown', dinle);
  }, [sutunMenusu]);

  const sutunDegistir = (anahtar: SutunAnahtari) => {
    const acik = tercih.sutunlar.includes(anahtar);
    onDegis({
      ...tercih,
      sutunlar: acik ? tercih.sutunlar.filter((ad) => ad !== anahtar) : [...tercih.sutunlar, anahtar],
    });
  };

  return (
    <div className="mb-3 flex flex-wrap items-center gap-2" data-testid="tablo-denetimleri">
      <div className="relative" ref={kapsayici}>
        <button
          type="button"
          className="btn-ghost !min-h-9 !px-3 !text-xs"
          aria-expanded={sutunMenusu}
          onClick={() => setSutunMenusu((acik) => !acik)}
          data-testid="sutun-menusu-dugmesi"
        >
          <Columns3 className="h-4 w-4" aria-hidden />
          Sütunlar ({tercih.sutunlar.length})
        </button>

        {sutunMenusu ? (
          <div
            className="absolute left-0 z-20 mt-1 w-56 rounded-xl border border-line bg-surface p-2 shadow-lg"
            role="group"
            aria-label="Görünür sütunlar"
            data-testid="sutun-menusu"
          >
            {(Object.keys(SUTUNLAR) as SutunAnahtari[]).map((anahtar) => (
              <label
                key={anahtar}
                className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-g50"
              >
                <input
                  type="checkbox"
                  className="h-4 w-4"
                  checked={tercih.sutunlar.includes(anahtar)}
                  onChange={() => sutunDegistir(anahtar)}
                />
                {SUTUNLAR[anahtar]}
              </label>
            ))}
          </div>
        ) : null}
      </div>

      <label className="inline-flex items-center gap-1.5 text-xs text-ink-3">
        <Rows3 className="h-4 w-4" aria-hidden />
        <span className="sr-only sm:not-sr-only">Yoğunluk</span>
        <select
          className="field-input !h-9 !w-auto !text-xs"
          value={tercih.yogunluk}
          aria-label="Satır yoğunluğu"
          data-testid="yogunluk-secici"
          onChange={(olay) => onDegis({ ...tercih, yogunluk: olay.target.value as Yogunluk })}
        >
          <option value="rahat">Rahat</option>
          <option value="sik">Sık</option>
        </select>
      </label>

      <label className="inline-flex items-center gap-1.5 text-xs text-ink-3">
        <Layers className="h-4 w-4" aria-hidden />
        <span className="sr-only sm:not-sr-only">Grupla</span>
        <select
          className="field-input !h-9 !w-auto !text-xs"
          value={tercih.grupla}
          aria-label="Gruplama"
          data-testid="grupla-secici"
          onChange={(olay) => onDegis({ ...tercih, grupla: olay.target.value as Gruplama })}
        >
          <option value="yok">Gruplama yok</option>
          <option value="kategori">Kategori</option>
          <option value="durum">Durum</option>
        </select>
      </label>
    </div>
  );
}
