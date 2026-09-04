import type { ReactNode } from 'react';
import { X } from 'lucide-react';

/**
 * K105 §2.1/§2.3 — ÇOKLU SEÇİM ALT ÇUBUĞU. Seçim varken belirir; "bu sayfadaki
 * N" ile "eşleşen M'nin tümü" AYRI ve AÇIKÇA yazılır (§2.3 "sayfalar arası
 * tümünü seç"): kullanıcı 50 satır seçtim sanıp 1.284 satıra işlem yapmaz.
 *
 * Eylemler slot olarak gelir; çubuk yalnız sayım, tümünü seç ve temizle'yi
 * taşır. Ekranlar bu davranışın kendi kopyasını yazmaz (§3).
 */
export default function SecimCubugu({
  seciliSayisi,
  sayfadaki,
  eslesenToplam,
  tumuSecili,
  onTumunuSec,
  onTemizle,
  children,
  birim = 'kayıt',
}: {
  seciliSayisi: number;
  /** Bu sayfada görünen kayıt sayısı. */
  sayfadaki: number;
  /** Filtreyle eşleşen toplam (sayfalar arası). */
  eslesenToplam: number;
  /** Eşleşen tümü seçili mi (sayfalar arası)? */
  tumuSecili?: boolean;
  onTumunuSec?: () => void;
  onTemizle: () => void;
  children?: ReactNode;
  birim?: string;
}) {
  if (seciliSayisi === 0) return null;

  return (
    <div
      role="region"
      aria-label="Seçim çubuğu"
      data-testid="secim-cubugu"
      className="sticky bottom-3 z-30 mt-3 flex flex-wrap items-center gap-3 rounded-xl border border-navy/20 bg-navy px-4 py-2 text-sm text-white shadow-2"
    >
      <span className="font-semibold">
        {tumuSecili ? `Eşleşen ${eslesenToplam} ${birim} seçili` : `Bu sayfada ${seciliSayisi} ${birim} seçili`}
      </span>
      {onTumunuSec && !tumuSecili && eslesenToplam > sayfadaki ? (
        <button type="button" className="underline decoration-white/40 underline-offset-2 hover:decoration-white" onClick={onTumunuSec}>
          Eşleşen {eslesenToplam} {birim}nın tümünü seç
        </button>
      ) : null}
      <span className="ml-auto flex flex-wrap items-center gap-2">{children}</span>
      <button type="button" className="inline-flex items-center gap-1 rounded-md px-2 py-1 hover:bg-white/10" onClick={onTemizle} aria-label="Seçimi temizle">
        <X className="h-4 w-4" aria-hidden /> Temizle
      </button>
    </div>
  );
}
