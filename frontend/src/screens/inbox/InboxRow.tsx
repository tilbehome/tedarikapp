import EylemDugmesi from '../../components/EylemDugmesi';
import { useState } from 'react';
import { Languages } from 'lucide-react';
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
  onTasi: () => Promise<unknown>;
  onSil: () => Promise<unknown>;
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
          <p className="truncate font-medium hover:text-navy">{gosterilenAd}</p>
        </button>
        <p className="text-xs text-ink-3">
          {item.platform}
          {item.price_yuan ? ` · ¥${item.price_yuan}` : ''} · {dateTime(item.created_at)}
          {item.url ? (
            <>
              {' · '}
              <a className="text-navy" href={item.url} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()}>
                kaynak
              </a>
            </>
          ) : null}
        </p>

        {secilenAd !== undefined ? (
          <p className="mt-1 text-xs text-ok">Türkçe ad kullanılacak — orijinal başlık korunur.</p>
        ) : oneri !== null ? (
          <p className="mt-1 flex flex-wrap items-center gap-2 rounded-lg bg-blue-soft px-2 py-1 text-xs">
            <span className="font-semibold uppercase tracking-wide text-navy">Türkçe öneri</span>
            <span className="min-w-0 flex-1 text-ink-2">{oneri}</span>
            <button type="button" className="btn-ghost px-2 py-0.5 text-xs" onClick={() => onAdSec(oneri)}>
              Kullan
            </button>
          </p>
        ) : ceviriDurumu === 'yok' ? (
          <p className="mt-1 text-xs text-ink-3">Çeviri önerisi alınamadı.</p>
        ) : ceviriDurumu === 'hata' ? (
          <p className="mt-1 text-xs text-warn">{hata}</p>
        ) : item.name !== null && cjk.test(item.name) ? (
          <button
            type="button"
            className="mt-1 inline-flex items-center gap-1 text-xs text-ink-3 hover:text-navy"
            disabled={ceviriDurumu === 'bekliyor'}
            onClick={() => void cevir()}
          >
            <Languages className="h-3.5 w-3.5" aria-hidden />
            {ceviriDurumu === 'bekliyor' ? 'Çevriliyor…' : 'Türkçe öneri al'}
          </button>
        ) : null}

        {item.status === 'error' ? (
          <p className="mt-1 text-xs text-warn">
            Eksik veri: {item.error_note ?? 'doğrulanamadı'} — taşınırsa yeniden denetlenir.
          </p>
        ) : null}
      </div>

      {/* C10/B11: satır eylemi de meşgul/başarı/hata üçlüsünü gösterir. */}
      <EylemDugmesi className="btn-ghost" mesgulEtiketi="Taşınıyor" disabled={busy} onEylem={onTasi}>
        Listeye taşı
      </EylemDugmesi>
      <EylemDugmesi
        className="btn-ghost text-err"
        mesgulEtiketi="Siliniyor"
        disabled={busy}
        onEylem={onSil}
        aria-label="Sil"
      >
        Sil
      </EylemDugmesi>
    </li>
  );
}
