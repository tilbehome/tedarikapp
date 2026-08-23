import { useCallback, useEffect, useRef, useState } from 'react';
import { ArrowDown, ArrowLeft, ArrowRight, Undo2, X } from 'lucide-react';
import type { InboxItem } from '../../api/endpoints';
import { metniNormalize } from '../../lib/metin';
import InboxThumb from './InboxThumb';

/**
 * DESTE MODU (İE#21 B4 · E2E-PNL-16/17/18/19).
 *
 * Amaç tek cümlede: 40 ürünü 2 dakikada elemek. Bunun için üç şey gerekir —
 * tek ürün büyük görselle, tek tuşla karar, ve yanlış karara tek tuşla dönüş.
 *
 * KLAVYE SÖZLEŞMESİ EKRANDA YAZAR. Gizli kısayol öğrenilmez; yazılı olan öğrenilir.
 *   J / ↓ sonraki · K / ↑ önceki  — YALNIZ ODAK değişir, hiçbir mutasyon olmaz
 *   Space  seçimi aç/kapa (sayfayı KAYDIRMAZ)
 *   ←      çöpe · ↓ havuza · →  listeye
 *   Z      son eylemi geri al
 *
 * NEDEN "J/K yalnız odak" ayrı bir söz (E2E-PNL-16): gezinirken kaza eseri veri
 * değiştiren bir arayüzde kullanıcı hızlanamaz — her tuşa basmadan önce durup
 * düşünür ve deste modunun bütün değeri kaybolur.
 */

export type DesteHedefi = 'cop' | 'havuz' | 'liste';

export interface DesteSonucu {
  inbox_id: number;
  urun_id: number | null;
  hedef: DesteHedefi;
  geri_alinabilir: boolean;
}

interface Props {
  kartlar: InboxItem[];
  hedefListeAdi: string | null;
  onEylem: (hedef: DesteHedefi, kart: InboxItem) => Promise<DesteSonucu | null>;
  onGeriAl: (sonuc: DesteSonucu) => Promise<void>;
  onKapat: () => void;
}

export default function DesteModu({ kartlar, hedefListeAdi, onEylem, onGeriAl, onKapat }: Props) {
  const [odak, setOdak] = useState(0);
  const [secili, setSecili] = useState<number[]>([]);
  const [sonEylem, setSonEylem] = useState<DesteSonucu | null>(null);
  const [mesgul, setMesgul] = useState(false);
  const kapsayici = useRef<HTMLDivElement>(null);

  const aktif = kartlar[odak] ?? null;

  // FOCUS-TRAP: deste açıkken odak dışarı kaçmaz. Kaçarsa tuşlar arkadaki
  // ekrana gider ve kullanıcı "tuşlar çalışmıyor" der.
  useEffect(() => {
    kapsayici.current?.focus();
  }, []);

  const eylemCalistir = useCallback(
    async (hedef: DesteHedefi) => {
      if (!aktif || mesgul) return;
      setMesgul(true);
      try {
        const sonuc = await onEylem(hedef, aktif);
        if (sonuc) setSonEylem(sonuc.geri_alinabilir ? sonuc : null);
        // Kart desteden düştüğü için odak YERİNDE kalır: bir sonraki kart aynı
        // indekse kayar. Odak ilerletmek, bir kartı atlamak demek olurdu.
        setOdak((mevcut) => Math.min(mevcut, Math.max(0, kartlar.length - 2)));
        setSecili((mevcut) => mevcut.filter((id) => id !== aktif.id));
      } finally {
        setMesgul(false);
      }
    },
    [aktif, mesgul, onEylem, kartlar.length],
  );

  const geriAl = useCallback(async () => {
    if (!sonEylem || mesgul) return;
    setMesgul(true);
    try {
      await onGeriAl(sonEylem);
      // Tek kullanımlık: ikinci basış etkisizdir (E2E-PNL-19).
      setSonEylem(null);
    } finally {
      setMesgul(false);
    }
  }, [sonEylem, mesgul, onGeriAl]);

  useEffect(() => {
    const dinle = (olay: KeyboardEvent) => {
      const hedef = olay.target as HTMLElement | null;
      if (hedef && ['INPUT', 'TEXTAREA', 'SELECT'].includes(hedef.tagName)) return;

      switch (olay.key) {
        case 'j':
        case 'J':
          olay.preventDefault();
          setOdak((m) => Math.min(m + 1, kartlar.length - 1));
          break;
        case 'k':
        case 'K':
          olay.preventDefault();
          setOdak((m) => Math.max(m - 1, 0));
          break;
        case ' ':
          // preventDefault ŞART: yoksa Space sayfayı kaydırır (E2E-PNL-17).
          olay.preventDefault();
          if (aktif) {
            setSecili((m) => (m.includes(aktif.id) ? m.filter((x) => x !== aktif.id) : [...m, aktif.id]));
          }
          break;
        case 'ArrowLeft':
          olay.preventDefault();
          void eylemCalistir('cop');
          break;
        case 'ArrowDown':
          olay.preventDefault();
          void eylemCalistir('havuz');
          break;
        case 'ArrowRight':
          olay.preventDefault();
          void eylemCalistir('liste');
          break;
        case 'z':
        case 'Z':
          olay.preventDefault();
          void geriAl();
          break;
        case 'Escape':
          olay.preventDefault();
          onKapat();
          break;
        default:
          break;
      }
    };

    window.addEventListener('keydown', dinle);

    return () => window.removeEventListener('keydown', dinle);
  }, [aktif, kartlar.length, eylemCalistir, geriAl, onKapat]);

  if (!aktif) {
    return (
      <div className="card p-10 text-center" data-testid="deste-bos">
        <p className="text-lg font-semibold text-ink">Deste bitti</p>
        <p className="mt-1 text-sm text-ink-2">Gelen Kutusu'nda karar bekleyen ürün kalmadı.</p>
        <button type="button" className="btn-ghost mt-4" onClick={onKapat}>Kapat</button>
      </div>
    );
  }

  return (
    <div
      ref={kapsayici}
      tabIndex={-1}
      className="card p-4 outline-none"
      data-testid="deste-modu"
      role="region"
      aria-label="Deste modu"
    >
      <div className="mb-3 flex items-center justify-between">
        <p className="text-xs text-ink-3">
          {odak + 1} / {kartlar.length} · {secili.length} seçili
        </p>
        <button type="button" className="btn-ghost !min-h-8 !px-2" onClick={onKapat} aria-label="Deste modunu kapat">
          <X className="h-4 w-4" aria-hidden />
        </button>
      </div>

      <div
        className="mb-4 flex flex-col items-center gap-3 rounded-xl border-2 border-navy/30 p-4"
        data-testid="deste-kart"
        data-inbox-id={aktif.id}
        aria-current="true"
      >
        <InboxThumb src={aktif.image_url} className="h-48 w-48" />
        <p className="text-center text-base font-semibold text-ink">
          {metniNormalize(aktif.name ?? '(adsız yakalama)')}
        </p>
        <p className="text-xs text-ink-3">
          {aktif.platform}
          {aktif.price_yuan ? ` · ¥${aktif.price_yuan}` : ''}
          {aktif.external_id ? ` · ${aktif.external_id}` : ''}
        </p>
        {secili.includes(aktif.id) ? (
          <span className="rounded-full bg-navy px-2 py-0.5 text-xs text-white" data-testid="secim-rozeti">
            Seçili
          </span>
        ) : null}
      </div>

      <div className="grid grid-cols-3 gap-2">
        <DesteDugmesi
          simge={<ArrowLeft className="h-4 w-4" aria-hidden />}
          etiket="Çöpe"
          kisayol="←"
          mesgul={mesgul}
          onClick={() => void eylemCalistir('cop')}
        />
        <DesteDugmesi
          simge={<ArrowDown className="h-4 w-4" aria-hidden />}
          etiket="Havuza"
          kisayol="↓"
          mesgul={mesgul}
          onClick={() => void eylemCalistir('havuz')}
        />
        <DesteDugmesi
          simge={<ArrowRight className="h-4 w-4" aria-hidden />}
          etiket={hedefListeAdi ? `Listeye: ${hedefListeAdi}` : 'Listeye'}
          kisayol="→"
          mesgul={mesgul || !hedefListeAdi}
          onClick={() => void eylemCalistir('liste')}
        />
      </div>

      <div className="mt-3 flex items-center justify-between">
        <p className="text-[11px] text-ink-3">
          J/K gez · Space seç · ← çöp · ↓ havuz · → liste · Z geri al · Esc kapat
        </p>
        <button
          type="button"
          className="btn-ghost !min-h-8 !px-2 !text-xs"
          disabled={!sonEylem || mesgul}
          onClick={() => void geriAl()}
          data-testid="geri-al"
        >
          <span className="inline-flex items-center gap-1">
            <Undo2 className="h-3.5 w-3.5" aria-hidden />
            Geri al
          </span>
        </button>
      </div>
    </div>
  );
}

function DesteDugmesi({
  simge,
  etiket,
  kisayol,
  mesgul,
  onClick,
}: {
  simge: React.ReactNode;
  etiket: string;
  kisayol: string;
  mesgul: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      className="flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-xl border border-line bg-surface text-xs font-medium text-ink-2 transition-colors hover:border-navy disabled:opacity-50"
      disabled={mesgul}
      onClick={onClick}
    >
      <span className="inline-flex items-center gap-1.5">
        {simge}
        <span className="truncate">{etiket}</span>
      </span>
      <span className="text-[10px] text-ink-3">{kisayol}</span>
    </button>
  );
}
