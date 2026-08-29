import { system as systemApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { count, dateTime } from '../../../lib/format';
import { mediaModeLabels } from '../../../locales/tr';
import { ErrorNote, Skeleton } from '../../../components/ui';
import Satir from './Satir';
import MigrationActions from './MigrationEylemleri';

/**
 * AYARLAR > SİSTEM DURUMU (V3-B yeniden tasarım).
 *
 * Referansta "Sistem & Yedekler"ten AYRI bir bölüm ve doğrusu bu: yedekleme
 * bir BAKIM işidir (kullanıcı bir şey yapar), sistem durumu bir TEŞHİS
 * yüzeyidir (kullanıcı bir şey okur). İkisini aynı kartta toplamak, "bir
 * sorun var mı?" diye bakan kişiyi yedek düğmelerinin arasında bırakıyordu.
 *
 * K99 kataloglarını ve K102 bildirim hata sayacını burası basar.
 */
export default function SistemDurumu() {
  const statusState = useAsync(() => systemApi.status(), []);

  return (
    <>
      <section className="card p-4">
        <h2 className="mb-3 text-sm font-semibold text-ink-2">Sistem durumu</h2>
        {statusState.loading ? (
          <Skeleton rows={2} />
        ) : statusState.error ? (
          <ErrorNote message={statusState.error} onRetry={statusState.reload} />
        ) : statusState.data ? (
          <dl className="space-y-2 text-sm">
            <Satir label="Uygulama sürümü" value={statusState.data.app_version} />
            <Satir label="PHP" value={statusState.data.php_version} />
            <Satir label="Veritabanı" value={statusState.data.db_version ?? '—'} />
            <Satir label="Uygulanan migration" value={count(statusState.data.migrations.applied)} />
            <Satir
              label="Bekleyen migration"
              value={
                statusState.data.migrations.pending_count === 0
                  ? 'Yok'
                  : `${count(statusState.data.migrations.pending_count)} adet`
              }
            />
            <Satir
              label="Görsel arşivi"
              value={
                statusState.data.media.mode === 'download'
                  ? mediaModeLabels.download
                  : statusState.data.media.mode === 'hotlink'
                    ? mediaModeLabels.hotlink
                    : '—'
              }
            />
            <Satir label="Kurulum tarihi" value={dateTime(statusState.data.installed_at)} />
          </dl>
        ) : null}
        {/* K99: çalışma zamanı katalogları. Eksik bir katalog, bağlı özelliğin
            SESSİZCE ölü olması demektir — bu yüzden kırmızı ve gerekçeli. */}
        {(statusState.data?.kataloglar ?? []).length > 0 ? (
          <div className="mt-3 border-t border-line-soft pt-3" data-testid="katalog-durumu">
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-3">
              Çalışma zamanı katalogları
            </h3>
            <ul className="space-y-1.5 text-sm">
              {(statusState.data?.kataloglar ?? []).map((katalog) => (
                <li key={katalog.kod} className="flex flex-wrap items-baseline gap-1.5">
                  <span
                    className={`rounded px-1.5 py-0.5 text-xs font-semibold ${
                      katalog.saglikli ? 'bg-ok-bg text-ok' : 'bg-err-bg text-err'
                    }`}
                    data-testid={`katalog-${katalog.kod}`}
                  >
                    {katalog.saglikli ? 'yüklü' : 'EKSİK'}
                  </span>
                  <span className="text-ink-2">{katalog.ad}</span>
                  <code className="text-xs text-ink-3">{katalog.yol}</code>
                  {katalog.hata !== null ? (
                    <span className="w-full text-xs text-err">{katalog.hata}</span>
                  ) : null}
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        {/* K102: kayıt sonrası yazılamayan bildirim. Birincil eylem düşmedi
            ama olay KAYBOLDU — sessiz kalmamalı. */}
        {(statusState.data?.bildirim_hatalari.sayi ?? 0) > 0 ? (
          <div
            className="mt-3 rounded-lg border border-err/40 bg-err-bg p-3 text-sm"
            data-testid="bildirim-hatasi"
          >
            <b className="text-err">
              {statusState.data?.bildirim_hatalari.sayi} bildirim yazılamadı
            </b>
            <p className="mt-0.5 text-xs text-ink-2">
              İşlemleriniz kaydedildi ama bu olaylar bildirim merkezine düşmedi.
              Yukarıdaki katalog satırları kırmızıysa sebebi odur.
            </p>
            {statusState.data?.bildirim_hatalari.son !== null ? (
              <code className="mt-1 block break-all text-xs text-ink-3">
                {statusState.data?.bildirim_hatalari.son}
              </code>
            ) : null}
          </div>
        ) : null}

        {statusState.data && statusState.data.migrations.pending_count > 0 ? (
          <MigrationActions onDone={statusState.reload} />
        ) : null}
      </section>
    </>
  );
}
