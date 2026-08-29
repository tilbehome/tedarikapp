import { useState } from 'react';
import { Bell, ChevronRight, PanelLeft, Search } from 'lucide-react';
import { menuyuCevir } from '../../lib/kabukDurumu';
import BildirimMerkezi from '../bildirim/BildirimMerkezi';

/**
 * ÜST ÇUBUK (İE#16 D1.6): kırıntı yolu (Bölüm › Ekran) + komut kutusu + zil.
 *
 * Komut kutusu bir arama alanı DEĞİLDİR, komut paletinin düğmesidir: tıklanınca
 * Ctrl+K ile aynı paleti açar. Tek bir giriş noktası olması bilinçlidir —
 * kullanıcı "nereye yazacağım" diye düşünmesin.
 */
export default function UstCubuk({
  bolum,
  ekran,
  onKomut,
  bildirimSayisi = 0,
  onBildirimSayaci,
}: {
  bolum: string;
  ekran: string;
  onKomut: () => void;
  bildirimSayisi?: number;
  /** Merkez kapanınca ya da okundu işaretlenince rozeti tazeler. */
  onBildirimSayaci?: (n: number) => void;
}) {
  const [merkezAcik, setMerkezAcik] = useState(false);

  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-line bg-surface px-3 md:px-4">
      <button
        type="button"
        className="flex size-9 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
        onClick={menuyuCevir}
        title="Menüyü daralt (Ctrl+B)"
        aria-label="Menüyü daralt"
      >
        <PanelLeft size={17} aria-hidden />
      </button>

      <nav aria-label="Kırıntı yolu" className="hidden min-w-0 items-center gap-1.5 text-md sm:flex">
        <span className="truncate text-ink-3">{bolum}</span>
        <ChevronRight size={14} className="shrink-0 text-ink-3" aria-hidden />
        <b className="truncate font-semibold text-ink">{ekran}</b>
      </nav>

      <button
        type="button"
        className="ml-auto flex min-h-9 max-w-md flex-1 items-center gap-2 rounded-xl border border-line bg-g50 px-3 text-md text-ink-3 transition-colors hover:border-g300 hover:text-ink-2"
        onClick={onKomut}
      >
        <Search size={15} aria-hidden />
        <span className="flex-1 truncate text-left">Ara veya komut çalıştır</span>
        <kbd className="hidden rounded-md border border-line bg-surface px-1.5 py-0.5 text-xs sm:block">Ctrl K</kbd>
      </button>

      <div className="relative shrink-0">
        <button
          type="button"
          className="relative flex size-9 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
          title="Bildirimler"
          aria-label={bildirimSayisi > 0 ? `Bildirimler (${bildirimSayisi} okunmamış)` : 'Bildirimler'}
          aria-expanded={merkezAcik}
          onClick={() => setMerkezAcik((acik) => !acik)}
          data-testid="bildirim-zili"
        >
          <Bell size={17} aria-hidden />
          {/* Rozet: sıfırsa basılmaz (kanon §3). */}
          {bildirimSayisi > 0 && (
            <span
              className="absolute right-1 top-1 size-2 rounded-full bg-gold"
              aria-hidden
              data-testid="bildirim-rozeti"
            />
          )}
        </button>
        {merkezAcik ? (
          <BildirimMerkezi
            onKapat={() => setMerkezAcik(false)}
            onSayac={(n) => onBildirimSayaci?.(n)}
          />
        ) : null}
      </div>
    </header>
  );
}
