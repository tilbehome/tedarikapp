import { useState, type ReactNode } from 'react';
import { Copy, Eraser, Eye, History } from 'lucide-react';
import { useToast } from '../Toast';

/**
 * K105 §2.2 — ALAN EYLEMLERİ: kopyala · temizle · orijinali göster · geçmiş.
 * Hover'da belirir; kopyalayınca kısa onay; temizleme `undo` ile geri gelir
 * (çağıran `GeriAlToast` verir); orijinal K56 hattıdır (çevrilmiş alanın ham
 * değeri görülebilmeli); geçmiş "kim, ne zaman, neyi".
 *
 * Boş alan "—" DEĞİL EYLEMDİR (§2.2): `deger` boşsa `bosEylem` ("+ ekle")
 * basılır. Ekranlar bu davranışın kendi kopyasını yazmaz (§3).
 */
export interface AlanGecmisiKaydi {
  kim: string;
  neZaman: string;
  eski: string | null;
  yeni: string | null;
}

export default function AlanEylemleri({
  deger,
  etiket,
  orijinal,
  gecmis,
  onTemizle,
  bosEylem,
  children,
}: {
  deger: string | null;
  /** Erişilebilirlik ve toast metni için alan adı ("Liste adı"). */
  etiket: string;
  /** Çevrilmiş/normalize alanın ham değeri (K56). */
  orijinal?: string | null;
  gecmis?: AlanGecmisiKaydi[];
  onTemizle?: () => void;
  /** Boş alanda gösterilecek davet ("+ Dönem ekle") — tıklanınca düzenleme açılır. */
  bosEylem?: { etiket: string; onClick: () => void };
  children?: ReactNode;
}) {
  const push = useToast((state) => state.push);
  const [orijinalAcik, setOrijinalAcik] = useState(false);
  const [gecmisAcik, setGecmisAcik] = useState(false);

  if ((deger === null || deger === '') && bosEylem) {
    return (
      <button type="button" className="text-navy underline decoration-navy/30 underline-offset-2 hover:decoration-navy" onClick={bosEylem.onClick}>
        {bosEylem.etiket}
      </button>
    );
  }

  const kopyala = () => {
    void navigator.clipboard?.writeText(deger ?? '');
    push(`${etiket} kopyalandı.`);
  };

  return (
    <span className="group/alan relative inline-flex max-w-full items-center gap-1">
      <span className="min-w-0 truncate">{children ?? deger}</span>
      <span className="ml-1 hidden shrink-0 items-center gap-0.5 group-hover/alan:inline-flex group-focus-within/alan:inline-flex">
        <button type="button" className="rounded p-0.5 text-ink-3 hover:bg-g100 hover:text-ink" aria-label={`${etiket} kopyala`} onClick={kopyala}>
          <Copy className="h-3.5 w-3.5" aria-hidden />
        </button>
        {onTemizle ? (
          <button type="button" className="rounded p-0.5 text-ink-3 hover:bg-g100 hover:text-ink" aria-label={`${etiket} temizle`} onClick={onTemizle}>
            <Eraser className="h-3.5 w-3.5" aria-hidden />
          </button>
        ) : null}
        {orijinal !== undefined && orijinal !== null && orijinal !== deger ? (
          <button type="button" className="rounded p-0.5 text-ink-3 hover:bg-g100 hover:text-ink" aria-label={`${etiket} orijinalini göster`} onClick={() => setOrijinalAcik((v) => !v)}>
            <Eye className="h-3.5 w-3.5" aria-hidden />
          </button>
        ) : null}
        {gecmis && gecmis.length > 0 ? (
          <button type="button" className="rounded p-0.5 text-ink-3 hover:bg-g100 hover:text-ink" aria-label={`${etiket} değişiklik geçmişi`} onClick={() => setGecmisAcik((v) => !v)}>
            <History className="h-3.5 w-3.5" aria-hidden />
          </button>
        ) : null}
      </span>
      {orijinalAcik && orijinal ? (
        <span className="absolute left-0 top-full z-40 mt-1 max-w-xs rounded-md border border-line bg-surface p-2 text-xs text-ink-2 shadow-2" role="note">
          <b>Orijinal:</b> {orijinal}
        </span>
      ) : null}
      {gecmisAcik && gecmis ? (
        <ul className="absolute left-0 top-full z-40 mt-1 max-w-sm space-y-1 rounded-md border border-line bg-surface p-2 text-xs text-ink-2 shadow-2" role="list">
          {gecmis.map((k, i) => (
            <li key={i}>
              <b>{k.kim}</b> · {k.neZaman}: <s className="text-ink-3">{k.eski ?? '∅'}</s> → {k.yeni ?? '∅'}
            </li>
          ))}
        </ul>
      ) : null}
    </span>
  );
}
