import { Link } from 'react-router-dom';
import { Clock3, Eye, EyeOff, FileText } from 'lucide-react';
import { teklifler as tekliflerApi } from '../api/endpoints';
import type { TeklifTuru } from '../api/types';
import { useAsync } from '../lib/useAsync';
import { dateTime } from '../lib/format';
import { EmptyState, ErrorNote, PageHeader, Skeleton } from '../components/ui';

/**
 * TEKLİFLER (V3-C Aşama 2.1 · yol haritası §7.6 "bizim taraf").
 *
 * İki kolon: AÇIK turlar (bekleyen iş) · GEÇMİŞ turlar (karar verilmiş).
 * Ana kolon "açıldı mı / kaç gündür bekliyor"dur — "gönderildi" tek başına
 * bilgi değildir; firma link'i hiç açmadıysa hatırlatma zamanı gelmiştir.
 *
 * Tur etiketi sunucudan gelir ("R2 gönderildi"): tur numarası durum adına
 * gömülmez, arayüz ikisini yan yana okur (#15 §2).
 *
 * Tur karşılaştırma, karşı teklif ve hatırlatma bu aşamada YOK: Blok C
 * (portal) firma yanıtını getirdiğinde "iki tur yan yana" anlam kazanır.
 */
export default function TekliflerScreen() {
  const state = useAsync(() => tekliflerApi.hepsi(), []);

  if (state.loading) return <Skeleton rows={4} />;
  if (state.error) return <ErrorNote message={state.error} onRetry={state.reload} />;

  const acik = state.data?.acik ?? [];
  const gecmis = state.data?.gecmis ?? [];

  return (
    <>
      <PageHeader title="Teklifler" subtitle="Firmalara açılan teklif turları — kim açtı, kaç gündür bekliyor, ne karar verildi." />

      {acik.length === 0 && gecmis.length === 0 ? (
        <div data-testid="teklifler-bos">
          <EmptyState
            title="Henüz teklif turu yok"
            description="Bir listenin detayında 'Yeni tur aç' ile firmaya teklif turu açın; tur buraya düşer."
          />
        </div>
      ) : null}

      <section className="card mb-4 p-4" data-testid="acik-turlar" aria-label="Açık turlar">
        <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-ink-2">
          <Clock3 className="h-4 w-4" aria-hidden />
          Açık turlar
          <span className="badge bg-g100 text-ink-3 ring-line">{acik.length}</span>
        </h2>
        {acik.length === 0 ? (
          <p className="text-sm text-ink-3">Bekleyen tur yok.</p>
        ) : (
          <ul className="divide-y divide-line-soft">
            {acik.map((tur) => (
              <TurSatiri key={tur.id} tur={tur} />
            ))}
          </ul>
        )}
      </section>

      <section className="card mb-4 p-4" data-testid="gecmis-turlar" aria-label="Geçmiş turlar">
        <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-ink-2">
          <FileText className="h-4 w-4" aria-hidden />
          Geçmiş turlar
          <span className="badge bg-g100 text-ink-3 ring-line">{gecmis.length}</span>
        </h2>
        {gecmis.length === 0 ? (
          <p className="text-sm text-ink-3">Karar verilmiş tur yok.</p>
        ) : (
          <ul className="divide-y divide-line-soft">
            {gecmis.map((tur) => (
              <TurSatiri key={tur.id} tur={tur} />
            ))}
          </ul>
        )}
      </section>
    </>
  );
}

/**
 * Tek tur satırı. "Açıldı / Açılmadı" bir GÖZLEMDİR (sahip yazamaz);
 * bekleme günü gönderimden itibaren sayılır.
 */
function TurSatiri({ tur }: { tur: TeklifTuru }) {
  const bekleme = tur.bekleme_gun;

  return (
    <li className="flex flex-wrap items-center gap-3 py-2 text-sm" data-testid={`tur-${tur.id}`}>
      <Link to={`/listeler/${tur.list_id}`} className="min-w-0 flex-1 truncate font-medium text-navy hover:underline">
        {tur.liste_adi}
      </Link>
      <span className="text-ink-2">{tur.firma_adi}</span>
      <span className={`badge ${tonu(tur.state)}`} title={tur.state}>
        {tur.etiket}
      </span>
      {tur.sent_at !== null && !tur.nihai ? (
        <span
          className={`inline-flex items-center gap-1 text-xs ${tur.goruntulendi ? 'text-ok' : 'text-warn'}`}
          title={tur.first_viewed_at ? `İlk açılış: ${dateTime(tur.first_viewed_at)}` : 'Firma bağlantıyı henüz açmadı'}
        >
          {tur.goruntulendi ? <Eye className="h-3.5 w-3.5" aria-hidden /> : <EyeOff className="h-3.5 w-3.5" aria-hidden />}
          {tur.goruntulendi ? 'Açıldı' : 'Açılmadı'}
        </span>
      ) : null}
      {bekleme !== null ? (
        <span className={`text-xs ${bekleme >= 5 ? 'font-medium text-warn' : 'text-ink-3'}`}>
          {bekleme === 0 ? 'bugün gönderildi' : `${bekleme} gündür bekliyor`}
        </span>
      ) : null}
      {tur.sent_at ? <span className="text-xs text-ink-3">· {dateTime(tur.sent_at)}</span> : null}
    </li>
  );
}

function tonu(durum: TeklifTuru['state']): string {
  switch (durum) {
    case 'DRAFT':
      return 'bg-g100 text-ink-2 ring-line';
    case 'SENT':
    case 'VIEWED':
    case 'PRICING':
      return 'bg-warn-soft text-warn ring-warn/20';
    case 'RESPONDED':
      return 'bg-navy/10 text-navy ring-navy/20';
    case 'APPROVED':
      return 'bg-ok-bg text-ok ring-ok/20';
    default:
      return 'bg-err-bg text-err ring-err/20';
  }
}
