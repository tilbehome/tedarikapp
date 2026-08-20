import { useState } from 'react';
import { Languages, Trash2 } from 'lucide-react';
import { translate as translateApi, type InboxItem } from '../../api/endpoints';
import { messageOf } from '../../lib/useAsync';
import { dateTime } from '../../lib/format';
import InboxThumb from './InboxThumb';

/** Çince/Japonca/Korece karakter var mı? Zaten Türkçe başlık için çeviri istenmez. */
const cjk = /[㐀-䶿一-鿿豈-﫿぀-ヿ가-힯]/;

interface Props {
  item: InboxItem;
  secili: boolean;
  onSec: () => void;
  onAc: () => void;
  onTasi: () => void;
  onSil: () => void;
  /** Kullanıcı çeviriyi kabul ettiyse taşımada kullanılacak ad (K54). */
  secilenAd: string | undefined;
  onAdSec: (ad: string) => void;
  busy: boolean;
}

/**
 * Kuyruk satırı (İE#13 B3/B4/B6).
 *
 * K54: "Türkçe öneri" düğmesi öneriyi YALNIZ gösterir; "Kullan" denmedikçe hiçbir
 * ad değişmez ve orijinal başlık her koşulda korunur.
 */
export default function InboxRow({ item, secili, onSec, onAc, onTasi, onSil, secilenAd, onAdSec, busy }: Props) {
  const [oneri, setOneri] = useState<string | null>(null);
  const [ceviriDurumu, setCeviriDurumu] = useState<'bos' | 'bekliyor' | 'yok' | 'hata'>('bos');
  const [hata, setHata] = useState('');

  const cevir = async () => {
    if (item.name === null || item.name === '') return;
    setCeviriDurumu('bekliyor');
    try {
      const sonuc = await translateApi.suggest(item.name);
      if (sonuc.suggestion === null) {
        setCeviriDurumu('yok');
        return;
      }
      setOneri(sonuc.suggestion);
      setCeviriDurumu('bos');
    } catch (caught) {
      setHata(messageOf(caught));
      setCeviriDurumu('hata');
    }
  };

  const gosterilenAd = secilenAd ?? item.name ?? '(adsız yakalama)';

  return (
    <li className="card flex items-start gap-3 p-3">
      <input type="checkbox" className="mt-1" checked={secili} onChange={onSec} aria-label="Seç" />

      <button type="button" onClick={onAc} aria-label="Detayı aç" className="shrink-0">
        <InboxThumb src={item.image_url} className="h-14 w-14" />
      </button>

      <div className="min-w-0 flex-1">
        <button type="button" onClick={onAc} className="block w-full text-left">
          <p className="truncate font-medium hover:text-brand-600">{gosterilenAd}</p>
        </button>
        <p className="text-xs text-slate-500">
          {item.platform}
          {item.price_yuan ? ` · ¥${item.price_yuan}` : ''} · {dateTime(item.created_at)}
          {item.url ? (
            <>
              {' · '}
              <a className="text-brand-600" href={item.url} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()}>
                kaynak
              </a>
            </>
          ) : null}
        </p>

        {secilenAd !== undefined ? (
          <p className="mt-1 text-xs text-emerald-700">Türkçe ad kullanılacak — orijinal başlık korunur.</p>
        ) : oneri !== null ? (
          <p className="mt-1 flex flex-wrap items-center gap-2 rounded-lg bg-brand-50 px-2 py-1 text-xs">
            <span className="font-semibold uppercase tracking-wide text-brand-700">Türkçe öneri</span>
            <span className="min-w-0 flex-1 text-slate-700">{oneri}</span>
            <button type="button" className="btn-ghost px-2 py-0.5 text-xs" onClick={() => onAdSec(oneri)}>
              Kullan
            </button>
          </p>
        ) : ceviriDurumu === 'yok' ? (
          <p className="mt-1 text-xs text-slate-400">Çeviri önerisi alınamadı.</p>
        ) : ceviriDurumu === 'hata' ? (
          <p className="mt-1 text-xs text-amber-700">{hata}</p>
        ) : item.name !== null && cjk.test(item.name) ? (
          <button
            type="button"
            className="mt-1 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-brand-600"
            disabled={ceviriDurumu === 'bekliyor'}
            onClick={() => void cevir()}
          >
            <Languages className="h-3.5 w-3.5" aria-hidden />
            {ceviriDurumu === 'bekliyor' ? 'Çevriliyor…' : 'Türkçe öneri al'}
          </button>
        ) : null}

        {item.status === 'error' ? (
          <p className="mt-1 text-xs text-amber-700">
            Eksik veri: {item.error_note ?? 'doğrulanamadı'} — taşınırsa yeniden denetlenir.
          </p>
        ) : null}
      </div>

      <button type="button" className="btn-ghost" disabled={busy} onClick={onTasi}>
        Taşı
      </button>
      <button type="button" className="btn-ghost text-red-600" disabled={busy} onClick={onSil} aria-label="Sil">
        <Trash2 className="h-4 w-4" aria-hidden />
      </button>
    </li>
  );
}
