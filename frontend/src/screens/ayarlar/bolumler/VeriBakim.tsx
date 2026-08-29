import { settings as settingsApi, system as systemApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { count, dateTime } from '../../../lib/format';
import { mediaModeLabels } from '../../../locales/tr';
import { ErrorNote, Skeleton } from '../../../components/ui';
import IslemDurumu from '../../../components/IslemDurumu';
import { useUzunIslem } from '../../../lib/useUzunIslem';
import Satir from './Satir';
import Gunluk from './Gunluk';

/**
 * AYARLAR > 16 VERİ & BAKIM (V3-B C1).
 *
 * PM eşlemesi: görsel arşivi, yedekleme (medya yedeği DAHİL), migration
 * eylemleri ve sistem durumu bu sekmede toplanır. Dördü de aynı soruyu
 * cevaplar: "verim güvende mi ve sistem sağlıklı mı?"
 */
export default function VeriBakim() {
  const settingsState = useAsync(() => settingsApi.read(), []);
  const statusState = useAsync(() => systemApi.status(), []);

  return (
    <>
      <MediaArchiveCard
        mode={settingsState.data?.media_mode ?? null}
        writable={settingsState.data?.media_writable ?? null}
        loading={settingsState.loading}
        error={settingsState.error}
        onRetry={settingsState.reload}
      />

      <BackupCard />

      {/* V3-B F2: sunucuya girmeden hata görme. */}
      <div className="mb-4">
        <Gunluk />
      </div>

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
        {statusState.data && statusState.data.migrations.pending_count > 0 ? (
          <MigrationActions onDone={statusState.reload} />
        ) : null}
      </section>
    </>
  );
}

/**
 * K47 — Görsel arşivi kartı: aktif mod + yazılabilirlik durumu ve "Görselleri arşive
 * taşı" düğmesi. Taşıma parti parti çalışır: uç tek çağrıda en fazla bir parti işler,
 * kart "kalan" sıfırlanana dek (ya da ilerleme durana dek) tekrar çağırır.
 */
function MediaArchiveCard({
  mode,
  writable,
  loading,
  error,
  onRetry,
}: {
  mode: 'download' | 'hotlink' | null;
  writable: boolean | null;
  loading: boolean;
  error: string | null;
  onRetry: () => void;
}) {
  // İE#14 C2: taşıma ve denetim aynı uzun-işlem desenini kullanır.
  const tasima = useUzunIslem();
  const denetim = useUzunIslem();

  const migrate = () =>
    void tasima.baslat(async (rapor, iptalIstendi) => {
      let migrated = 0;
      let failed = 0;
      let remaining = 0;
      // İE#10 5b: başarısız kimlikler tur belleğinde birikir ve sonraki partilerde
      // dışlanır — kalıcı-başarısızlar sırayı tutmaz, denenmemişlere sıra gelir.
      const excludeProducts: number[] = [];
      const excludeImages: number[] = [];
      for (let batch = 0; batch < 100; batch++) {
        // İptal PARTİ ARASINDA denetlenir: yarım parti bırakılmaz, taşınan taşınmış kalır.
        if (iptalIstendi()) break;
        const result = await systemApi.mediaMigrate({
          exclude_products: excludeProducts,
          exclude_images: excludeImages,
        });
        migrated += result.migrated;
        failed += result.failed.length;
        remaining = result.remaining;
        for (const failure of result.failed) {
          (failure.kind === 'main_image' ? excludeProducts : excludeImages).push(failure.id);
        }
        // Gerçek ilerleme: sahte yüzde değil, sayılan iş.
        rapor(`${migrated} taşındı · ${failed} başarısız · ${remaining} kaldı`);
        if (remaining <= excludeProducts.length + excludeImages.length || result.scanned === 0) break;
      }

      return `${migrated} görsel arşive taşındı · ${failed} başarısız · ${remaining} kaldı.` +
        (failed > 0 ? ' Başarısız olanlar bozulmadı, tekrar denenebilir.' : '');
    });

  // İE#10 5d: DB↔disk bütünlük denetimi — dosyası kayıp yerel kayıtları kaynağından onarır.
  const check = () =>
    void denetim.baslat(async () => {
      const result = await systemApi.mediaCheck();

      return `${result.checked} kayıt denetlendi · ${result.missing} kayıp · ${result.repaired} onarıldı` +
        (result.failed.length > 0 ? ` · ${result.failed.length} kayıt onarılamadı (kaynağı yok).` : '.');
    });

  const running = tasima.calisiyor || denetim.calisiyor;

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-3 text-sm font-semibold text-ink-2">Görsel arşivi</h2>
      {/* İE#14 C3: okunurken "okunuyor…", hata varsa bu kartın kendi tekrar denemesi. */}
      {error ? (
        <ErrorNote message={error} onRetry={onRetry} />
      ) : (
        <dl className="space-y-2 text-sm">
          <Satir
            label="Aktif mod"
            value={
              loading
                ? 'okunuyor…'
                : mode === 'download'
                  ? mediaModeLabels.download
                  : mode === 'hotlink'
                    ? mediaModeLabels.hotlink
                    : '—'
            }
          />
          <Satir
            label="Medya klasörü (public/media)"
            value={
              loading ? 'okunuyor…' : writable === null ? '—' : writable ? 'Yazılabilir' : 'Yazılamıyor — arşivleme kapalı'
            }
          />
        </dl>
      )}
      <p className="mt-3 text-xs text-ink-3">
        1688 görselleri orijinal adresinden gösterilemiyor (CDN Referer koruması). Bu düğme hotlink döneminden kalan
        uzak görselleri sunucu arşivine indirir; başarısız olanlar bozulmaz ve tekrar denenebilir.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <button type="button" className="btn-primary" disabled={running || writable !== true} onClick={migrate}>
          {tasima.calisiyor ? 'Taşınıyor…' : 'Görselleri arşive taşı'}
        </button>
        <button type="button" className="btn-ghost" disabled={running || writable !== true} onClick={check}>
          {denetim.calisiyor ? 'Denetleniyor…' : 'Eksik dosyaları denetle/onar'}
        </button>
      </div>
      <IslemDurumu islem={tasima} fiil="Görseller arşive taşınıyor" onTekrar={migrate} />
      <IslemDurumu islem={denetim} fiil="Görsel kayıtları denetleniyor" onTekrar={check} />
    </section>
  );
}

/**
 * Güncelleme eylemleri — bekleyen migration varken görünür (İE#11 sonrası düzeltme:
 * panelde "migrate" düğmesi YOKTU, kullanıcı yeni sürümü kuramıyordu).
 *
 *  • "Güncellemeyi çalıştır" (migrate): bekleyen migration'ları uygular — ASIL yol.
 *  • "Defteri eşitle" (K49 baseline): tablolar VAR ama defter geride kalmışsa
 *    kayıtları KOŞMADAN işler; DDL çalıştırmaz, idempotenttir.
 */
function MigrationActions({ onDone }: { onDone: () => void }) {
  // İE#14 C2: migration TEK ATIMLIK bir iştir — iptal isteği sunucudaki işi
  // yarıda bırakmaz (yarım migration tehlikelidir), yalnız sonucu işaretler.
  const guncelleme = useUzunIslem();
  const defter = useUzunIslem();

  const migrate = () =>
    void guncelleme.baslat(async () => {
      const result = await systemApi.migrate();
      onDone();

      return result.applied_count === 0
        ? 'Uygulanacak yeni migration yoktu.'
        : `Güncelleme tamam: ${count(result.applied_count)} migration uygulandı.`;
    });

  const baseline = () =>
    void defter.baslat(async () => {
      const result = await systemApi.migrateBaseline();
      onDone();

      return result.skipped.length === 0
        ? `Defter eşitlendi: ${count(result.recorded.length)} kayıt işlendi, bekleyen ${count(result.pending_count)}.`
        : `${count(result.recorded.length)} kayıt işlendi; ${count(result.skipped.length)} kayıt atlandı (nesnesi yok) — bekleyen ${count(result.pending_count)}.`;
    });

  const busy = guncelleme.calisiyor || defter.calisiyor;

  return (
    <div className="mt-3 border-t border-line-soft pt-3">
      <p className="text-xs text-ink-3">
        Yeni sürüm veritabanı güncellemesi bekliyor. "Güncellemeyi çalıştır" bekleyen migration'ları uygular.
        Tablolar zaten varsa (defter geride kalmışsa) "Defteri eşitle" kullanılır — o işlem tablo oluşturmaz.
      </p>
      <div className="mt-2 flex flex-wrap gap-2">
        <button type="button" className="btn-primary" disabled={busy} onClick={migrate}>
          {guncelleme.calisiyor ? 'Çalışıyor…' : 'Güncellemeyi çalıştır'}
        </button>
        <button type="button" className="btn-ghost" disabled={busy} onClick={baseline}>
          {defter.calisiyor ? 'Eşitleniyor…' : 'Defteri eşitle'}
        </button>
      </div>
      <IslemDurumu islem={guncelleme} fiil="Veritabanı güncelleniyor" onTekrar={migrate} />
      <IslemDurumu islem={defter} fiil="Migration defteri eşitleniyor" onTekrar={baseline} />
    </div>
  );
}

/**
 * İE#14 D1 — yaşın İNSAN dili: "3 saat önce". Yedek hiç yoksa bunu açıkça söyler;
 * "—" burada yanıltıcı olurdu.
 */
function yedekYasi(saniye: number | null): string {
  if (saniye === null) return 'hiç alınmadı';
  if (saniye < 3600) return `${Math.max(1, Math.round(saniye / 60))} dakika önce`;
  const saat = Math.round(saniye / 3600);
  if (saat < 48) return `${saat} saat önce`;

  return `${Math.round(saat / 24)} gün önce`;
}

function BackupCard() {
  const state = useAsync(() => systemApi.backupList(), []);
  // İE#14 C2: yedekleme dakikalarca sürebilir; çift tıklama iki yedek üretirdi.
  const yedekleme = useUzunIslem();

  const create = () =>
    void yedekleme.baslat(async () => {
      const result = await systemApi.backupCreate();
      state.reload();
      const offsite = result.offsite.attempted
        ? result.offsite.sent
          ? ` Uzak hedefe gönderildi (${result.offsite.via}).`
          : ` UZAK GÖNDERİM BAŞARISIZ: ${result.offsite.error ?? 'bilinmeyen hata'}`
        : ' Uzak hedef yapılandırılmadı — dosyayı indirip ayrı bir yerde saklayın.';

      return `Yedek alındı: ${result.backup.name}.${offsite}`;
    });

  const sizeOf = (bytes: number) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-ink-2">
        Yedekler
        {/* İE#14 D1: "yedek var mı" değil "NE ZAMAN alındı" sorusu yanıtlanır. */}
        {state.data?.gecikti ? (
          <span className="badge bg-warn-soft text-warn ring-warn/20">
            Gecelik yedek gecikti — cron çalışmıyor olabilir
          </span>
        ) : state.data?.stale ? (
          <span className="badge bg-warn-soft text-warn ring-warn/20">Son yedek 24 saatten eski</span>
        ) : null}
      </h2>
      {state.data ? (
        <p className={`mb-2 text-sm ${state.data.gecikti ? 'font-medium text-warn' : 'text-ink-2'}`}>
          Son yedek: {yedekYasi(state.data.last_age_seconds)}
          {state.data.cron ? (
            <span className="ml-2 text-xs text-ink-3">
              · son cron koşusu {yedekYasi(state.data.cron.age_seconds)}
              {state.data.cron.ok ? '' : ' (HATA ile bitti)'}
            </span>
          ) : (
            <span className="ml-2 text-xs text-ink-3">· cron kaydı yok (storage/logs/cron.log boş)</span>
          )}
        </p>
      ) : null}
      {state.data && !state.data.writable ? (
        <p className="text-sm text-err">
          Yedek klasörü yazılamıyor (storage/backups) — cPanel'den storage klasörüne yazma izni (775) verin.
        </p>
      ) : null}
      {state.loading ? (
        <Skeleton rows={2} />
      ) : state.error ? (
        <ErrorNote message={state.error} onRetry={state.reload} />
      ) : state.data && state.data.backups.length > 0 ? (
        <ul className="divide-y divide-line-soft text-sm">
          {state.data.backups.map((entry) => (
            <li key={entry.name} className="flex items-center justify-between gap-3 py-2">
              <span className="min-w-0 flex-1 truncate font-mono text-xs">{entry.name}</span>
              <span className="text-ink-3">{sizeOf(entry.size)}</span>
              <span className="text-ink-3">{dateTime(entry.created_at)}</span>
              <a className="btn-ghost" href={systemApi.backupFileUrl(entry.name)}>İndir</a>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-ink-3">Henüz yedek alınmadı.</p>
      )}
      <p className="mt-2 text-xs text-ink-3">
        Yedek, veritabanının şifreli dökümüdür (çözme APP_KEY ister). Saklama: eski yedekler
        otomatik silinir, en yeni 5 her koşulda korunur.{' '}
        {state.data?.offsite_configured
          ? 'Uzak hedef yapılandırılmış: her yedek otomatik gönderilir.'
          : 'Uzak hedef yapılandırılmamış: yedeği indirip bilgisayarınızda/bulutta saklayın. Otomatik için cPanel cron: php bin/backup.php'}
      </p>
      <button
        type="button"
        className="btn-primary mt-3"
        disabled={yedekleme.calisiyor || state.data?.writable === false}
        onClick={create}
      >
        {yedekleme.calisiyor ? 'Yedek alınıyor…' : 'Şimdi yedek al'}
      </button>
      <IslemDurumu islem={yedekleme} fiil="Veritabanı yedekleniyor" onTekrar={create} />
    </section>
  );
}
