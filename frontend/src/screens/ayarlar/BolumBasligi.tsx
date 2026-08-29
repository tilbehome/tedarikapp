import type { LucideIcon } from 'lucide-react';

export type DurumTonu = 'iyi' | 'uyari' | 'notr';

/**
 * BÖLÜM BAŞLIĞI KARTI (V3-B madde 3).
 *
 * İkon + ad + alt başlık + sağda durum çipi.
 *
 * ÇİP YALNIZ GERÇEK DURUM GÖSTERİR: "Çalışıyor", "Zamanlama kapalı",
 * "Anahtar eksik". Ölçülemeyen bir şey için çip BASILMAZ — dekoratif bir
 * yeşil rozet, kullanıcıya kontrol edilmemiş bir şeyi kontrol edilmiş
 * gösterirdi. Bu, uydurma KPI yasağının (PM sapma #2) aynı kuralıdır.
 *
 * "Son düzenleme" de aynı disiplinde: değer yoksa satır hiç basılmaz,
 * "—" ya da "bilinmiyor" yazılmaz.
 */
export default function BolumBasligi({
  ikon: Ikon,
  ad,
  kapsam,
  cip,
  sonDuzenleme,
}: {
  ikon: LucideIcon;
  ad: string;
  kapsam: string;
  cip?: { metin: string; ton: DurumTonu } | null;
  sonDuzenleme?: string | null;
}) {
  return (
    <header className="card mb-4 flex flex-wrap items-start gap-3 p-4" data-testid="bolum-basligi">
      <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-navy text-white">
        <Ikon size={19} aria-hidden />
      </span>

      <div className="min-w-0 flex-1">
        <h2 className="text-base font-bold tracking-tight text-ink">{ad}</h2>
        <p className="mt-0.5 text-sm text-ink-3">{kapsam}</p>
      </div>

      <div className="flex shrink-0 flex-col items-end gap-1">
        {cip ? <DurumCipi metin={cip.metin} ton={cip.ton} /> : null}
        {/* Değer yoksa satır BASILMAZ — "—" bir bilgi değil, bir boşluktur. */}
        {sonDuzenleme !== null && sonDuzenleme !== undefined && sonDuzenleme !== '' ? (
          <span className="text-xs text-ink-3" data-testid="son-duzenleme">
            son düzenleme: {sonDuzenleme}
          </span>
        ) : null}
      </div>
    </header>
  );
}

const TON_STILI: Record<DurumTonu, string> = {
  iyi: 'bg-ok-bg text-ok',
  uyari: 'bg-warn-bg text-warn',
  notr: 'bg-g100 text-ink-3',
};

export function DurumCipi({ metin, ton }: { metin: string; ton: DurumTonu }) {
  return (
    <span
      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${TON_STILI[ton]}`}
      data-testid="durum-cipi"
    >
      {metin}
    </span>
  );
}
