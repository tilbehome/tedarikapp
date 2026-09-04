import { useEffect, useRef, useState, type MouseEvent, type ReactNode } from 'react';
import { MoreHorizontal } from 'lucide-react';

/**
 * K105 §2.1 — SATIR EYLEM MENÜSÜ: `⋯` düğmesi ve SAĞ TIK aynı menüyü açar,
 * tek tanım. Sağ tık tarayıcı menüsünü bastırır. Ekranlar bu davranışın
 * kendi kopyasını YAZMAZ (K105 §3): menü öğeleri veri olarak verilir.
 *
 * Kullanım:
 *   const menu = useSatirEylemMenusu();
 *   <tr onContextMenu={menu.sagTik}> … <SatirEylemMenusu menu={menu} ogeler={[...]} />
 *
 * Klavye: `Esc` kapatır, ↑/↓ öğeler arasında gezer, `Enter` seçer. Tehlikeli
 * öğe (çöpe at) kırmızı yazılır ama ONAY SORMAZ — geri alma onaydan iyidir
 * (§2.6); ekran `GeriAlToast` ile geri alma verir.
 */
export interface SatirEylemi {
  etiket: string;
  onClick: () => void;
  simge?: ReactNode;
  tehlikeli?: boolean;
  /** Öğeden ÖNCE ayraç çizgisi. */
  ayrac?: boolean;
  devreDisi?: boolean;
  /** Kısayol ipucu (yalnız görüntü). */
  kisayol?: string;
}

export interface SatirEylemMenusuDurumu {
  acik: boolean;
  konum: { x: number; y: number } | null;
  /** Klavye odağındaki öğe (açılışta 0). */
  odak: number;
  setOdak: (guncelle: (i: number) => number) => void;
  ac: () => void;
  kapat: () => void;
  /** `onContextMenu` için — tarayıcı menüsünü bastırır, menüyü imlecin yanında açar. */
  sagTik: (olay: MouseEvent) => void;
}

export function useSatirEylemMenusu(): SatirEylemMenusuDurumu {
  const [acik, setAcik] = useState(false);
  const [konum, setKonum] = useState<{ x: number; y: number } | null>(null);
  const [odak, setOdakDurumu] = useState(0);

  return {
    acik,
    konum,
    odak,
    setOdak: (guncelle) => setOdakDurumu(guncelle),
    ac: () => {
      setKonum(null);
      setOdakDurumu(0);
      setAcik(true);
    },
    kapat: () => setAcik(false),
    sagTik: (olay) => {
      olay.preventDefault();
      setKonum({ x: olay.clientX, y: olay.clientY });
      setOdakDurumu(0);
      setAcik(true);
    },
  };
}

export default function SatirEylemMenusu({
  menu,
  ogeler,
  etiket = 'Satır eylemleri',
}: {
  menu: SatirEylemMenusuDurumu;
  ogeler: SatirEylemi[];
  etiket?: string;
}) {
  const kap = useRef<HTMLDivElement>(null);
  const { odak, setOdak } = menu;

  useEffect(() => {
    if (!menu.acik) return;
    const disari = (olay: globalThis.MouseEvent) => {
      if (kap.current && !kap.current.contains(olay.target as Node)) menu.kapat();
    };
    const tus = (olay: KeyboardEvent) => {
      if (olay.key === 'Escape') {
        olay.preventDefault();
        menu.kapat();
      }
    };
    document.addEventListener('mousedown', disari);
    window.addEventListener('keydown', tus);
    return () => {
      document.removeEventListener('mousedown', disari);
      window.removeEventListener('keydown', tus);
    };
    // menu nesnesi her render'da yeni; yalnız açık/kapalı geçişi izlenir.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [menu.acik]);

  const aktifler = ogeler.filter((o) => !o.devreDisi);

  const klavye = (olay: React.KeyboardEvent) => {
    if (olay.key === 'ArrowDown') {
      olay.preventDefault();
      setOdak((i) => Math.min(aktifler.length - 1, i + 1));
    } else if (olay.key === 'ArrowUp') {
      olay.preventDefault();
      setOdak((i) => Math.max(0, i - 1));
    } else if (olay.key === 'Enter') {
      olay.preventDefault();
      const oge = aktifler[odak];
      if (oge) {
        menu.kapat();
        oge.onClick();
      }
    }
  };

  const sabit = menu.konum !== null;

  return (
    <div className="relative inline-flex" ref={kap}>
      <button
        type="button"
        className="btn-ghost !min-h-8 !px-2"
        aria-label={etiket}
        aria-haspopup="menu"
        aria-expanded={menu.acik}
        onClick={(olay) => {
          olay.stopPropagation();
          if (menu.acik) menu.kapat();
          else menu.ac();
        }}
      >
        <MoreHorizontal className="h-4 w-4" aria-hidden />
      </button>
      {menu.acik ? (
        <div
          role="menu"
          aria-label={etiket}
          tabIndex={-1}
          onKeyDown={klavye}
          className={`z-50 min-w-[200px] rounded-lg border border-line bg-surface p-1 text-sm shadow-2 ${
            sabit ? 'fixed' : 'absolute right-0 top-[calc(100%+4px)]'
          }`}
          style={sabit && menu.konum ? { left: menu.konum.x, top: menu.konum.y } : undefined}
          ref={(el) => el?.focus()}
        >
          {ogeler.map((oge, i) => (
            <div key={`${oge.etiket}-${i}`}>
              {oge.ayrac ? <div className="my-1 border-t border-line-soft" role="separator" /> : null}
              <button
                type="button"
                role="menuitem"
                disabled={oge.devreDisi}
                className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-g50 disabled:opacity-40 ${
                  oge.tehlikeli ? 'text-err' : 'text-ink'
                } ${aktifler[odak] === oge ? 'bg-g50' : ''}`}
                onClick={(olay) => {
                  olay.stopPropagation();
                  menu.kapat();
                  oge.onClick();
                }}
              >
                {oge.simge ? <span className="shrink-0 text-ink-3">{oge.simge}</span> : null}
                <span className="flex-1">{oge.etiket}</span>
                {oge.kisayol ? <kbd className="text-xs text-ink-3">{oge.kisayol}</kbd> : null}
              </button>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
