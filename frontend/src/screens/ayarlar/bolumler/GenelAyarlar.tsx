import { Monitor, Moon, Sun } from 'lucide-react';
import { settings as settingsApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../../components/ui';
import { temaAyarla, temaEtiketleri, useTema, type Tema } from '../../../lib/tema';
import UygulamaAdresi from '../UygulamaAdresi';

/**
 * AYARLAR > 1 GENEL (V3-B C1 + D1).
 *
 * İki şey: panelin dış adresi (rc8/K4 — paylaşım linkleri ve QR bu adresten
 * üretilir) ve görünüm teması.
 *
 * TEMA SEÇİCİ BURADA VE FOOTER MENÜSÜNDE OLMAK ÜZERE İKİ YERDEDİR ama tek
 * kaynağa yazar (`lib/tema.ts`): footer hızlı geçiş içindir, bu sekme ise
 * "ayar nerede?" diye arayan kullanıcı içindir. İkisi de aynı depoyu okuyup
 * yazdığı için ayrışamazlar.
 */
export default function GenelAyarlar() {
  const durum = useAsync(() => settingsApi.read(), []);
  const tema = useTema();

  return (
    <>
      {durum.loading ? (
        <Skeleton rows={2} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : durum.data ? (
        <UygulamaAdresi
          mevcut={durum.data.app_url}
          kanonik={durum.data.app_url_kanonik}
          onSaved={durum.reload}
        />
      ) : null}

      <section className="card p-4" data-testid="tema-secici">
        <h2 className="mb-1 text-sm font-semibold text-ink-2">Görünüm</h2>
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
    </>
  );
}
