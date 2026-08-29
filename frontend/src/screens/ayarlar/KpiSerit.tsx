/**
 * KPI ŞERİDİ (V3-B madde 4).
 *
 * TEK KURAL: ÖLÇÜLEMEYEN KART RENDER EDİLMEZ.
 *
 * PM sapma #2 bunu açıkça söylüyor: referanstaki "Depolama" kartı gibi,
 * güvenilir okunamayan bir değeri tahminle doldurmak kullanıcıyı olmayan bir
 * veriye göre karar vermeye iter. Bu yüzden kart listesi `null` girdileri
 * SÜZER ve hiç kart kalmazsa şerit hiç basılmaz — boş bir çerçeve "ölçüm
 * yapıldı ama sıfır çıktı" izlenimi verirdi.
 *
 * Aynı disiplin Panorama'daki "henüz ölçülmüyor" ayrımı ve K67'nin
 * "bilinmeyen ≠ sıfır" kuralıyla aynıdır.
 */

export interface KpiKarti {
  etiket: string;
  deger: string;
  /** İkincil satır: ölçünün bağlamı ("30 saati aştı" gibi). */
  alt?: string | null;
  ton?: 'iyi' | 'uyari' | 'notr';
}

const TON_STILI = {
  iyi: 'text-ok',
  uyari: 'text-warn',
  notr: 'text-ink',
} as const;

export default function KpiSerit({ kartlar }: { kartlar: (KpiKarti | null)[] }) {
  const gorunenler = kartlar.filter((kart): kart is KpiKarti => kart !== null);

  // Ölçülebilen hiçbir şey yoksa şerit YOK.
  if (gorunenler.length === 0) return null;

  return (
    <div
      className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"
      data-testid="kpi-serit"
    >
      {gorunenler.map((kart) => (
        <div key={kart.etiket} className="card p-3" data-testid="kpi-kart">
          <div className="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-3">
            {kart.etiket}
          </div>
          <div className={`mt-1 text-lg font-bold tabular-nums ${TON_STILI[kart.ton ?? 'notr']}`}>
            {kart.deger}
          </div>
          {kart.alt !== null && kart.alt !== undefined && kart.alt !== '' ? (
            <div className="mt-0.5 text-xs text-ink-3">{kart.alt}</div>
          ) : null}
        </div>
      ))}
    </div>
  );
}
