import { Monitor, Moon, Sun } from 'lucide-react';
import { panorama as panoramaApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../../components/ui';
import { temaAyarla, temaEtiketleri, useTema, type Tema } from '../../../lib/tema';
import { DurumCipi } from '../BolumBasligi';

/**
 * AYARLAR > GÖRÜNÜM & PANORAMA (V3-B yeniden tasarım).
 *
 * İki şey: panelin teması ve "Bugün ne var?" özet ekranının ne ölçtüğü.
 *
 * PANORAMA BURADA AYARLANMAZ, AÇIKLANIR. Eşik ayarı (kaç brifing, hangi
 * öncelik) eklemedim ve bu bilinçli: hangi brifingin gürültü olduğunu
 * bilmeden eşik koymak, kullanıcıya kendi kendine kapatacağı bir sistem
 * vermektir. Önce neyin ölçüldüğü görülür — ölçülmeyenler de AÇIKÇA yazılı.
 */
export default function GorunumPanorama() {
  const tema = useTema();
  const durum = useAsync(() => panoramaApi.read(), []);

  return (
    <>
      <section className="card mb-4 p-4" data-testid="tema-secici">
        <h3 className="mb-1 text-sm font-semibold text-ink-2">Görünüm</h3>
        <p className="mb-3 text-xs text-ink-3">
          "Sistem" seçiliyken panel, işletim sisteminizin açık/koyu tercihini izler.
        </p>
        <div className="flex flex-wrap gap-2">
          {(['acik', 'koyu', 'sistem'] as Tema[]).map((secenek) => (
            <button
              key={secenek}
              type="button"
              className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors ${
                tema === secenek
                  ? 'border-blue bg-blue-soft font-medium text-blue'
                  : 'border-line text-ink-2 hover:bg-g50'
              }`}
              onClick={() => temaAyarla(secenek)}
              aria-pressed={tema === secenek}
              data-testid={`tema-${secenek}`}
            >
              {secenek === 'acik' ? (
                <Sun size={15} aria-hidden />
              ) : secenek === 'koyu' ? (
                <Moon size={15} aria-hidden />
              ) : (
                <Monitor size={15} aria-hidden />
              )}
              {temaEtiketleri[secenek]}
            </button>
          ))}
        </div>
      </section>

      <section className="card p-4">
        <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
          <h3 className="text-sm font-semibold text-ink-2">Panorama brifingleri</h3>
          {durum.data ? (
            <DurumCipi
              metin={`${durum.data.brifingler.length} etkin`}
              ton={durum.data.brifingler.length > 0 ? 'uyari' : 'iyi'}
            />
          ) : null}
        </div>
        <p className="mb-3 text-xs text-ink-3">
          Ana ekrandaki "Bugün ne var?" bölümü bu koşulları izler. Ölçülemeyen konular
          "koşul sağlanmadı" sayılmaz; aşağıda ayrıca listelenir.
        </p>

        {durum.loading ? (
          <Skeleton rows={2} />
        ) : durum.error ? (
          <ErrorNote message={durum.error} onRetry={durum.reload} />
        ) : durum.data ? (
          <>
            <dl className="space-y-1.5 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-ink-3">İzlenen konu</dt>
                <dd className="font-medium text-ink">
                  {durum.data.brifingler.length + durum.data.olculmeyen.length -
                    durum.data.olculmeyen.length}
                  {' etkin · '}
                  {durum.data.olculmeyen.length} henüz ölçülmüyor
                </dd>
              </div>
            </dl>
            <ul className="mt-2 space-y-1 border-t border-line-soft pt-2 text-xs text-ink-3">
              {durum.data.olculmeyen.map((satir) => (
                <li key={satir.id} className="flex gap-2">
                  <b className="shrink-0 font-medium text-ink-2">{satir.id}</b>
                  <span>{satir.sebep}</span>
                </li>
              ))}
            </ul>
          </>
        ) : null}
      </section>
    </>
  );
}
