import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, Check, CheckCheck, Info, X } from 'lucide-react';
import { bildirimler as bildirimApi, type Bildirim } from '../../api/endpoints';
import { messageOf } from '../../lib/useAsync';
import { dateTime } from '../../lib/format';

/**
 * BİLDİRİM MERKEZİ PANELİ (V3-B A4).
 *
 * Zil düğmesine basılınca açılan liste. Modal DEĞİLDİR (A5 kararı): sayfayı
 * bloklamaz, dışarı tıklamayla kapanır, kullanıcı ne yapıyorsa yapmaya devam
 * edebilir.
 *
 * Okundu işareti SATIRA TIKLAYINCA değil, AÇIK BİR DÜĞMEYLE verilir. Sebebi
 * bildirim merkezlerinin klasik tuzağıdır: liste açılır açılmaz her şeyi
 * okunmuş saymak, kullanıcının göz ucuyla baktığı kritik satırı da siler.
 */
export default function BildirimMerkezi({ onKapat, onSayac }: { onKapat: () => void; onSayac: (n: number) => void }) {
  const [liste, setListe] = useState<Bildirim[]>([]);
  const [yukleniyor, setYukleniyor] = useState(true);
  const [hata, setHata] = useState<string | null>(null);
  const kutu = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let iptal = false;
    bildirimApi
      .read()
      .then((veri) => {
        if (iptal) return;
        setListe(veri.bildirimler);
        onSayac(veri.okunmamis);
      })
      .catch((e) => !iptal && setHata(messageOf(e)))
      .finally(() => !iptal && setYukleniyor(false));

    return () => {
      iptal = true;
    };
  }, [onSayac]);

  // Dışarı tıklama ve Esc ile kapanır — bloklayıcı olmadığının kanıtı.
  useEffect(() => {
    const disariTikla = (olay: MouseEvent) => {
      if (kutu.current && !kutu.current.contains(olay.target as Node)) onKapat();
    };
    const escBas = (olay: KeyboardEvent) => olay.key === 'Escape' && onKapat();
    document.addEventListener('mousedown', disariTikla);
    document.addEventListener('keydown', escBas);

    return () => {
      document.removeEventListener('mousedown', disariTikla);
      document.removeEventListener('keydown', escBas);
    };
  }, [onKapat]);

  const okunduYap = async (id: number): Promise<void> => {
    try {
      const sonuc = await bildirimApi.okundu(id);
      setListe((eski) => eski.map((b) => (b.id === id ? { ...b, okundu: true } : b)));
      onSayac(sonuc.okunmamis);
    } catch (e) {
      setHata(messageOf(e));
    }
  };

  const hepsiniOkuduYap = async (): Promise<void> => {
    try {
      await bildirimApi.hepsiOkundu();
      setListe((eski) => eski.map((b) => ({ ...b, okundu: true })));
      onSayac(0);
    } catch (e) {
      setHata(messageOf(e));
    }
  };

  const okunmamisVar = liste.some((b) => !b.okundu);

  return (
    <div
      ref={kutu}
      className="absolute right-0 top-11 z-40 max-h-[70vh] w-[min(24rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-line bg-surface shadow-3"
      role="dialog"
      aria-label="Bildirimler"
      data-testid="bildirim-merkezi"
    >
      <div className="flex items-center justify-between border-b border-line px-3 py-2">
        <b className="text-md font-semibold text-ink">Bildirimler</b>
        <div className="flex items-center gap-1">
          {okunmamisVar ? (
            <button
              type="button"
              className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-ink-3 hover:bg-g50 hover:text-ink"
              onClick={hepsiniOkuduYap}
              data-testid="bildirim-hepsi-okundu"
            >
              <CheckCheck size={14} aria-hidden />
              Hepsini okundu say
            </button>
          ) : null}
          <button
            type="button"
            className="flex size-7 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
            onClick={onKapat}
            aria-label="Kapat"
          >
            <X size={15} aria-hidden />
          </button>
        </div>
      </div>

      <div className="max-h-[calc(70vh-2.5rem)] overflow-y-auto">
        {yukleniyor ? (
          <p className="px-3 py-6 text-center text-sm text-ink-3">Yükleniyor…</p>
        ) : hata !== null ? (
          <p className="px-3 py-6 text-center text-sm text-err">{hata}</p>
        ) : liste.length === 0 ? (
          <p className="px-3 py-8 text-center text-sm text-ink-3">
            Henüz bildirim yok. Önemli her işlem burada görünür.
          </p>
        ) : (
          <ul className="divide-y divide-line-soft">
            {liste.map((bildirim) => (
              <BildirimSatiri key={bildirim.id} bildirim={bildirim} onOkundu={okunduYap} onKapat={onKapat} />
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

const onemIkonu = {
  bilgi: Info,
  uyari: AlertTriangle,
  kritik: AlertTriangle,
} as const;

const onemRengi = {
  bilgi: 'text-ink-3',
  uyari: 'text-warn',
  kritik: 'text-err',
} as const;

function BildirimSatiri({
  bildirim,
  onOkundu,
  onKapat,
}: {
  bildirim: Bildirim;
  onOkundu: (id: number) => Promise<void>;
  onKapat: () => void;
}) {
  const Ikon = onemIkonu[bildirim.onem];

  return (
    <li className={`flex gap-2 px-3 py-2.5 ${bildirim.okundu ? 'opacity-60' : 'bg-g50/50'}`}>
      <Ikon size={15} className={`mt-0.5 shrink-0 ${onemRengi[bildirim.onem]}`} aria-hidden />
      <div className="min-w-0 flex-1">
        <div className="flex items-start gap-1.5">
          <b className="text-md font-semibold text-ink">{bildirim.baslik}</b>
          {/* Birleşen olay sayısı: 1 ise rozet BASILMAZ (kanon §3). */}
          {bildirim.birlesen_sayi > 1 ? (
            <span
              className="mt-0.5 shrink-0 rounded-full bg-blue-soft px-1.5 text-xs font-semibold text-blue"
              data-testid="bildirim-birlesen"
            >
              ×{bildirim.birlesen_sayi}
            </span>
          ) : null}
        </div>
        <p className="mt-0.5 text-sm text-ink-2">{bildirim.govde}</p>
        <div className="mt-1 flex items-center gap-2 text-xs text-ink-3">
          <span>{dateTime(bildirim.created_at)}</span>
          {bildirim.eylem_linki !== null ? (
            <Link
              to={bildirim.eylem_linki.replace(/^\/panel/, '')}
              className="font-medium text-blue hover:underline"
              onClick={onKapat}
            >
              Aç
            </Link>
          ) : null}
        </div>
      </div>
      {!bildirim.okundu ? (
        <button
          type="button"
          className="flex size-7 shrink-0 items-center justify-center self-start rounded-lg text-ink-3 hover:bg-surface hover:text-ink"
          onClick={() => void onOkundu(bildirim.id)}
          title="Okundu say"
          aria-label={`"${bildirim.baslik}" bildirimini okundu say`}
        >
          <Check size={14} aria-hidden />
        </button>
      ) : null}
    </li>
  );
}
