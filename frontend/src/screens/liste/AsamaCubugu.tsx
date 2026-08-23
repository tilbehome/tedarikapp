import { Check, Lock } from 'lucide-react';
import type { ListStatus } from '../../api/types';
import { listStatusLabels } from '../../locales/tr';

/**
 * AŞAMA ÇUBUĞU (İE#21 B2 · referans: `liste-ici.png`).
 *
 * ÇUBUK v1.0'DA 5B DURUM MAKİNESİYLE KURULUR (PM kararı, 23 Ağu 2026):
 *
 *   Taslak → İletildi → Sipariş Verildi → Tamamlandı   (+ İptal)
 *
 * Referans karesinde görünen sonraki faz aşamaları (Üretim/Tedarik, Sevkiyat,
 * Teslim edildi, Kapandı) burada PASİF "Yakında" olarak durur: tıklanmaz, ileri
 * götürmez, söz vermez. Gerekçe C1 kapsam sınırıdır — çalışmayan bir adımı canlı
 * adım gibi göstermek kullanıcıya olmayan bir yetenek vaat eder ve ilk denemede
 * güveni kırar.
 *
 * İLERLETME İZNİ SUNUCUDAN GELİR: hangi geçişin serbest olduğunu `machine.list`
 * söyler (durum makinesi backend'de zorlanır — CLAUDE.md §4). Panel kendi kuralını
 * uydurmaz; uydursaydı iki ayrı gerçek olurdu.
 */

/** v1.0'da GERÇEKTEN çalışan aşamalar — tek kaynak durum makinesidir (5B). */
const CANLI_ASAMALAR: ListStatus[] = ['draft', 'sent', 'ordered', 'completed'];

/** Sonraki fazların aşamaları: görünür ama PASİF (V3-C / V3-D). */
export const YAKINDA_ASAMALAR = ['Üretim / Tedarik', 'Sevkiyat', 'Teslim edildi', 'Kapandı'] as const;

interface Props {
  durum: ListStatus;
  izinliGecisler: ListStatus[];
  kurKilitli: boolean;
  mesgul?: boolean;
  onGecis: (hedef: ListStatus) => void;
}

export default function AsamaCubugu({ durum, izinliGecisler, kurKilitli, mesgul = false, onGecis }: Props) {
  // İptal, çubuğun üstünde bir adım değildir: akışın dışına çıkıştır. Çubuğa
  // koysaydık "İptal"i bir ilerleme aşaması gibi göstermiş olurduk.
  const iptalEdildi = durum === 'cancelled';
  const mevcutSira = iptalEdildi ? -1 : CANLI_ASAMALAR.indexOf(durum);

  return (
    <section className="card mb-4 p-4" aria-label="Liste aşamaları" data-testid="asama-cubugu">
      <ol className="flex flex-wrap items-start gap-x-2 gap-y-4">
        {CANLI_ASAMALAR.map((asama, sira) => {
          const gecmis = mevcutSira > sira;
          const aktif = mevcutSira === sira;
          const izinli = izinliGecisler.includes(asama);

          return (
            <li key={asama} className="flex min-w-[8rem] flex-1 flex-col items-center gap-1 text-center">
              <div className="flex w-full items-center">
                <span className={cizgi(sira === 0, gecmis || aktif)} aria-hidden />
                <button
                  type="button"
                  disabled={!izinli || mesgul}
                  onClick={() => onGecis(asama)}
                  aria-current={aktif ? 'step' : undefined}
                  data-testid={`asama-${asama}`}
                  data-durum={gecmis ? 'gecmis' : aktif ? 'aktif' : izinli ? 'izinli' : 'kapali'}
                  title={izinli ? `${listStatusLabels[asama]} yap` : undefined}
                  className={nokta(gecmis, aktif, izinli)}
                >
                  {gecmis ? <Check className="h-3.5 w-3.5" aria-hidden /> : null}
                  <span className="sr-only">{listStatusLabels[asama]}</span>
                </button>
                <span className={cizgi(false, gecmis)} aria-hidden />
              </div>
              <span className={`text-xs ${aktif ? 'font-semibold text-navy' : 'text-ink-3'}`}>
                {listStatusLabels[asama]}
              </span>
            </li>
          );
        })}

        {YAKINDA_ASAMALAR.map((etiket) => (
          <li
            key={etiket}
            className="flex min-w-[8rem] flex-1 flex-col items-center gap-1 text-center opacity-50"
            data-testid="asama-yakinda"
          >
            <span className="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-line text-ink-3">
              <Lock className="h-3 w-3" aria-hidden />
            </span>
            <span className="text-xs text-ink-3">{etiket}</span>
            <span className="badge bg-g50 text-ink-3 ring-line">Yakında</span>
          </li>
        ))}
      </ol>

      <p className="mt-3 text-xs text-ink-3" data-testid="asama-ipucu">
        {iptalEdildi
          ? 'Bu liste iptal edildi; aşama çubuğu ilerlemez.'
          : kurKilitli
            ? 'Kur bu listeye kilitlendi; ₺ karşılıkları kilitli kurla hesaplanır.'
            : 'Firmaya iletildiğinde kur bu listeye kilitlenir.'}
      </p>
    </section>
  );
}

function nokta(gecmis: boolean, aktif: boolean, izinli: boolean): string {
  const taban = 'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 transition-colors';
  if (gecmis) return `${taban} border-ok bg-ok text-white`;
  if (aktif) return `${taban} border-navy bg-white text-navy ring-4 ring-navy/15`;
  if (izinli) return `${taban} border-navy/40 bg-white hover:border-navy hover:bg-navy/10`;
  return `${taban} cursor-not-allowed border-line bg-white`;
}

function cizgi(ilk: boolean, dolu: boolean): string {
  if (ilk) return 'h-0.5 flex-1 bg-transparent';
  return `h-0.5 flex-1 ${dolu ? 'bg-navy' : 'bg-line'}`;
}
