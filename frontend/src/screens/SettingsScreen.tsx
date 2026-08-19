import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight, ShieldCheck } from 'lucide-react';
import { settings as settingsApi, system as systemApi } from '../api/endpoints';
import { useAsync, messageOf } from '../lib/useAsync';
import { count, dateTime, rate } from '../lib/format';
import { mediaModeLabels } from '../locales/tr';
import { ErrorNote, Field, PageHeader, Skeleton } from '../components/ui';
import { useToast } from '../components/Toast';

/**
 * E8 — Ayarlar: kurlar (tarihçeli), kategoriler, güvenlik, sistem durumu.
 *
 * Kur METİN olarak gönderilir ve METİN olarak gösterilir; panel dönüştürme yapmaz.
 * Kur değişimi yalnızca `draft` listelerin görünen TL'sini etkiler — kilitli
 * listeler etkilenmez (K4); bu kural ekranda da yazılıdır.
 */
export default function SettingsScreen() {
  const push = useToast((state) => state.push);
  const settingsState = useAsync(() => settingsApi.read(), []);
  const statusState = useAsync(() => systemApi.status(), []);
  const historyState = useAsync(() => settingsApi.rateHistory(), []);

  const [yuan, setYuan] = useState('');
  const [usd, setUsd] = useState('');
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const data = settingsState.data;
    if (!data) return;
    setYuan(data.yuan_tl);
    setUsd(data.usd_tl);
  }, [settingsState.data]);

  const save = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setFields({});
    try {
      const result = await settingsApi.updateRates({
        yuan_tl: yuan.trim().replace(',', '.'),
        usd_tl: usd.trim().replace(',', '.'),
      });
      settingsState.reload();
      historyState.reload();
      // 3b (K48 ek): aynı değerle basmak tarihçeye yazmaz — bildirim de bunu söyler.
      if (result.changes.length === 0) {
        push(`Kurlar zaten güncel (${rate(result.yuan_tl)} / ${rate(result.usd_tl)}).`);
      } else {
        const parts = result.changes.map(
          (change) => `${change.currency === 'CNY' ? 'Yuan' : 'Dolar'} ${rate(change.from)} → ${rate(change.to)}`,
        );
        push(`${parts.join(', ')} güncellendi. Kilitli listeler etkilenmedi.`);
      }
    } catch (caught) {
      push(messageOf(caught), 'error');
      const fieldErrors = (caught as { fields?: Record<string, string> }).fields;
      if (fieldErrors) setFields(fieldErrors);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <PageHeader title="Ayarlar" subtitle="Kurlar, kategoriler, güvenlik ve sistem" />

      <section className="card mb-4 p-4">
        <h2 className="mb-3 text-sm font-semibold text-slate-700">Kurlar</h2>
        {settingsState.loading ? (
          <Skeleton rows={1} />
        ) : settingsState.error ? (
          <ErrorNote message={settingsState.error} onRetry={settingsState.reload} />
        ) : (
          <form onSubmit={(event) => void save(event)} className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Yuan → TL" hint="Örn. 4,1250" error={fields['yuan_tl']}>
                <input className="field-input" inputMode="decimal" value={yuan} onChange={(event) => setYuan(event.target.value)} />
              </Field>
              <Field label="Dolar → TL" hint="Örn. 41,8000" error={fields['usd_tl']}>
                <input className="field-input" inputMode="decimal" value={usd} onChange={(event) => setUsd(event.target.value)} />
              </Field>
            </div>
            <p className="text-xs text-slate-500">
              Yeni kur yalnızca <strong>Taslak</strong> listelere işler. "İletildi" durumuna geçmiş listelerin kuru kilitlidir ve
              değişmez.
            </p>
            <button type="submit" className="btn-primary" disabled={busy}>
              {busy ? 'Kaydediliyor…' : 'Kurları güncelle'}
            </button>
          </form>
        )}
      </section>

      <section className="card mb-4 p-4">
        <h2 className="mb-3 text-sm font-semibold text-slate-700">Kur tarihçesi</h2>
        {historyState.loading ? (
          <Skeleton rows={2} />
        ) : historyState.error ? (
          <ErrorNote message={historyState.error} onRetry={historyState.reload} />
        ) : (historyState.data ?? []).length === 0 ? (
          <p className="text-sm text-slate-500">Henüz kur değişikliği kaydedilmedi.</p>
        ) : (
          <div className="table-scroll">
            <table className="w-full text-sm">
              <thead className="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="py-2">Para birimi</th>
                  <th className="py-2 text-right">Kur</th>
                  <th className="py-2 text-right">Tarih</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {(historyState.data ?? []).map((entry) => (
                  <tr key={entry.id}>
                    {/* Uç para birimini ISO koduyla verir (CNY/USD) — ekranda Türkçe karşılığı gösterilir. */}
                    <td className="py-2">{entry.currency === 'CNY' ? 'Yuan → TL' : 'Dolar → TL'}</td>
                    <td className="py-2 text-right font-medium">{rate(entry.rate)}</td>
                    <td className="py-2 text-right text-slate-500">{dateTime(entry.set_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <Link to="/ayarlar/kategoriler" className="card mb-4 flex items-center justify-between p-4 hover:bg-slate-50">
        <span>
          <span className="block font-semibold">Kategoriler</span>
          <span className="block text-xs text-slate-500">Ürün kategorilerini düzenle</span>
        </span>
        <ChevronRight className="h-5 w-5 text-slate-400" aria-hidden />
      </Link>

      <section className="card mb-4 p-4">
        <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700">
          <ShieldCheck className="h-4 w-4 text-brand-600" aria-hidden />
          Güvenlik
        </h2>
        <dl className="space-y-2 text-sm">
          <Line
            label="İki adımlı doğrulama"
            value={settingsState.data?.totp_enabled ? 'Etkin' : 'Kapalı'}
          />
          <Line
            label="Eklenti API token'ı"
            value={settingsState.data?.extension_token_preview ?? 'Henüz üretilmedi'}
          />
        </dl>
        <ExtensionTokenActions
          preview={settingsState.data?.extension_token_preview ?? null}
          onChanged={settingsState.reload}
        />
      </section>

      <MediaArchiveCard
        mode={settingsState.data?.media_mode ?? null}
        writable={settingsState.data?.media_writable ?? null}
      />

      <BackupCard />

      <section className="card p-4">
        <h2 className="mb-3 text-sm font-semibold text-slate-700">Sistem durumu</h2>
        {statusState.loading ? (
          <Skeleton rows={2} />
        ) : statusState.error ? (
          <ErrorNote message={statusState.error} onRetry={statusState.reload} />
        ) : statusState.data ? (
          <dl className="space-y-2 text-sm">
            <Line label="Uygulama sürümü" value={statusState.data.app_version} />
            <Line label="PHP" value={statusState.data.php_version} />
            <Line label="Veritabanı" value={statusState.data.db_version ?? '—'} />
            <Line label="Uygulanan migration" value={count(statusState.data.migrations.applied)} />
            <Line
              label="Bekleyen migration"
              value={
                statusState.data.migrations.pending_count === 0
                  ? 'Yok'
                  : `${count(statusState.data.migrations.pending_count)} adet`
              }
            />
            <Line
              label="Görsel arşivi"
              value={
                statusState.data.media.mode === 'download'
                  ? mediaModeLabels.download
                  : statusState.data.media.mode === 'hotlink'
                    ? mediaModeLabels.hotlink
                    : '—'
              }
            />
            <Line label="Kurulum tarihi" value={dateTime(statusState.data.installed_at)} />
          </dl>
        ) : null}
        {statusState.data && statusState.data.migrations.pending_count > 0 ? (
          <BaselineAction onDone={statusState.reload} />
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
function MediaArchiveCard({ mode, writable }: { mode: 'download' | 'hotlink' | null; writable: boolean | null }) {
  const push = useToast((state) => state.push);
  const [running, setRunning] = useState(false);
  const [summary, setSummary] = useState<string | null>(null);

  const migrate = async () => {
    setRunning(true);
    setSummary(null);
    try {
      let migrated = 0;
      let failed = 0;
      let remaining = 0;
      // İE#10 5b: başarısız kimlikler tur belleğinde birikir ve sonraki partilerde
      // dışlanır — kalıcı-başarısızlar sırayı tutmaz, denenmemişlere sıra gelir.
      const excludeProducts: number[] = [];
      const excludeImages: number[] = [];
      for (let batch = 0; batch < 100; batch++) {
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
        setSummary(`${migrated} taşındı, ${failed} başarısız, ${remaining} kaldı…`);
        if (remaining <= excludeProducts.length + excludeImages.length || result.scanned === 0) break;
      }
      setSummary(`${migrated} görsel arşive taşındı · ${failed} başarısız · ${remaining} kaldı.`);
      push(
        failed === 0 && remaining === 0
          ? 'Tüm görseller arşive taşındı.'
          : 'Taşıma bitti; başarısız kalanlar bozulmadı, tekrar deneyebilirsiniz.',
        failed === 0 ? 'success' : 'error',
      );
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setRunning(false);
    }
  };

  // İE#10 5d: DB↔disk bütünlük denetimi — dosyası kayıp yerel kayıtları kaynağından onarır.
  const check = async () => {
    setRunning(true);
    try {
      const result = await systemApi.mediaCheck();
      setSummary(`${result.checked} kayıt denetlendi · ${result.missing} kayıp · ${result.repaired} onarıldı.`);
      push(
        result.missing === 0
          ? 'Tüm görsel kayıtları diskle uyumlu.'
          : `${result.repaired} görsel onarıldı; ${result.failed.length} kayıt onarılamadı (kaynağı yok/erişilemedi).`,
        result.missing === 0 || result.repaired > 0 ? 'success' : 'error',
      );
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setRunning(false);
    }
  };

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-3 text-sm font-semibold text-slate-700">Görsel arşivi</h2>
      <dl className="space-y-2 text-sm">
        <Line
          label="Aktif mod"
          value={mode === 'download' ? mediaModeLabels.download : mode === 'hotlink' ? mediaModeLabels.hotlink : '—'}
        />
        <Line
          label="Medya klasörü (public/media)"
          value={writable === null ? '—' : writable ? 'Yazılabilir' : 'Yazılamıyor — arşivleme kapalı'}
        />
      </dl>
      <p className="mt-3 text-xs text-slate-500">
        1688 görselleri orijinal adresinden gösterilemiyor (CDN Referer koruması). Bu düğme hotlink döneminden kalan
        uzak görselleri sunucu arşivine indirir; başarısız olanlar bozulmaz ve tekrar denenebilir.
      </p>
      {summary ? <p className="mt-2 text-xs font-medium text-slate-600">{summary}</p> : null}
      <div className="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          className="btn-primary"
          disabled={running || writable !== true}
          onClick={() => void migrate()}
        >
          {running ? 'Taşınıyor…' : 'Görselleri arşive taşı'}
        </button>
        <button
          type="button"
          className="btn-ghost"
          disabled={running || writable !== true}
          onClick={() => void check()}
        >
          Eksik dosyaları denetle/onar
        </button>
      </div>
    </section>
  );
}

/**
 * K49 — "Defteri eşitle": migrations defteri gerçeğin gerisindeyse (canlı vaka:
 * tablolar var ama defter boş, "Bekleyen 17") kayıtları KOŞMADAN deftere işler.
 * DDL çalıştırmaz, idempotenttir; yalnız bekleyen migration varken görünür.
 */
function BaselineAction({ onDone }: { onDone: () => void }) {
  const push = useToast((state) => state.push);
  const [busy, setBusy] = useState(false);

  const run = async () => {
    setBusy(true);
    try {
      const result = await systemApi.migrateBaseline();
      push(
        result.skipped.length === 0
          ? `Defter eşitlendi: ${count(result.recorded.length)} kayıt işlendi, bekleyen ${count(result.pending_count)}.`
          : `${count(result.recorded.length)} kayıt işlendi; ${count(result.skipped.length)} kayıt atlandı (nesnesi yok) — bekleyen ${count(result.pending_count)}.`,
        result.skipped.length === 0 ? 'success' : 'error',
      );
      onDone();
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mt-3 border-t border-slate-100 pt-3">
      <p className="text-xs text-slate-500">
        Tablolar mevcutken defter "bekleyen" gösteriyorsa kayıtlar eşitlenebilir. Bu işlem tablo oluşturmaz/değiştirmez;
        yalnız var olduğu doğrulanan kayıtları deftere işler.
      </p>
      <button type="button" className="btn-ghost mt-2" disabled={busy} onClick={() => void run()}>
        {busy ? 'Eşitleniyor…' : 'Defteri eşitle'}
      </button>
    </div>
  );
}

/**
 * İE#10.5 Blok 1 — Yedekler kartı: elle yedek al (+ off-site yapılandırıldıysa gönderir),
 * son yedekler (tarih/boyut/indir), son yedek 24 saatten eskiyse uyarı rozeti.
 * Dosyalar şifrelidir (AES-256-GCM, anahtar APP_KEY'den türetilir) ve web'den erişilemez.
 */
function BackupCard() {
  const push = useToast((state) => state.push);
  const state = useAsync(() => systemApi.backupList(), []);
  const [busy, setBusy] = useState(false);

  const create = async () => {
    setBusy(true);
    try {
      const result = await systemApi.backupCreate();
      state.reload();
      const offsite = result.offsite.attempted
        ? result.offsite.sent
          ? ` Uzak hedefe gönderildi (${result.offsite.via}).`
          : ` UZAK GÖNDERİM BAŞARISIZ: ${result.offsite.error ?? 'bilinmeyen hata'}`
        : ' Uzak hedef yapılandırılmadı — dosyayı indirip ayrı bir yerde saklayın.';
      push(`Yedek alındı: ${result.backup.name}.${offsite}`, result.offsite.attempted && !result.offsite.sent ? 'error' : 'success');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const sizeOf = (bytes: number) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
        Yedekler
        {state.data?.stale ? (
          <span className="badge bg-amber-50 text-amber-800 ring-amber-200">Son yedek 24 saatten eski</span>
        ) : null}
      </h2>
      {state.data && !state.data.writable ? (
        <p className="text-sm text-red-600">
          Yedek klasörü yazılamıyor (storage/backups) — cPanel'den storage klasörüne yazma izni (775) verin.
        </p>
      ) : null}
      {state.data && state.data.backups.length > 0 ? (
        <ul className="divide-y divide-slate-100 text-sm">
          {state.data.backups.map((entry) => (
            <li key={entry.name} className="flex items-center justify-between gap-3 py-2">
              <span className="min-w-0 flex-1 truncate font-mono text-xs">{entry.name}</span>
              <span className="text-slate-500">{sizeOf(entry.size)}</span>
              <span className="text-slate-500">{dateTime(entry.created_at)}</span>
              <a className="btn-ghost" href={systemApi.backupFileUrl(entry.name)}>İndir</a>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-slate-500">Henüz yedek alınmadı.</p>
      )}
      <p className="mt-2 text-xs text-slate-500">
        Yedek, veritabanının şifreli dökümüdür (çözme APP_KEY ister). Saklama: eski yedekler
        otomatik silinir, en yeni 5 her koşulda korunur.{' '}
        {state.data?.offsite_configured
          ? 'Uzak hedef yapılandırılmış: her yedek otomatik gönderilir.'
          : 'Uzak hedef yapılandırılmamış: yedeği indirip bilgisayarınızda/bulutta saklayın. Otomatik için cPanel cron: php bin/backup.php'}
      </p>
      <button type="button" className="btn-primary mt-3" disabled={busy || state.data?.writable === false} onClick={() => void create()}>
        {busy ? 'Yedek alınıyor…' : 'Şimdi yedek al'}
      </button>
    </section>
  );
}

/**
 * İE#11 — eklenti token'ı üret/iptal: tam token YALNIZ üretim yanıtında bir kez
 * görünür (K34; DB'de hash). Tek kullanıcı çok cihaz: aynı token her tarayıcıya
 * girilebilir; yenileme/iptal hepsini birden düşürür.
 */
function ExtensionTokenActions({ preview, onChanged }: { preview: string | null; onChanged: () => void }) {
  const push = useToast((state) => state.push);
  const [busy, setBusy] = useState(false);
  const [token, setToken] = useState<string | null>(null);

  const create = async () => {
    setBusy(true);
    try {
      const result = await settingsApi.extensionTokenCreate();
      setToken(result.token);
      onChanged();
      push('Token üretildi — yalnız şimdi görünür, eklentiye şimdi yapıştırın.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  const revoke = async () => {
    setBusy(true);
    try {
      await settingsApi.extensionTokenRevoke();
      setToken(null);
      onChanged();
      push('Token iptal edildi — tüm cihazlardaki eklentiler düştü.');
    } catch (caught) {
      push(messageOf(caught), 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mt-3">
      {token ? (
        <>
          <p className="break-all rounded-lg bg-slate-50 p-2 font-mono text-xs">{token}</p>
          <p className="mt-1 text-xs text-amber-700">
            Bu token yalnız şimdi görünür (güvenlik gereği kaydedilmez) — eklentinin ayar ekranına yapıştırın.
          </p>
          <button type="button" className="btn-primary mt-2" onClick={() => void navigator.clipboard.writeText(token).then(() => push('Token kopyalandı.'))}>
            Kopyala
          </button>
        </>
      ) : (
        <div className="flex flex-wrap gap-2">
          <button type="button" className="btn-primary" disabled={busy} onClick={() => void create()}>
            {preview ? 'Token yenile' : "Eklenti token'ı üret"}
          </button>
          {preview ? (
            <button type="button" className="btn-ghost" disabled={busy} onClick={() => void revoke()}>
              Token iptal et
            </button>
          ) : null}
        </div>
      )}
      <p className="mt-2 text-xs text-slate-500">
        Chrome eklentisi bu token ile panele bağlanır. Yenileme/iptal, token'ı kullanan TÜM cihazları düşürür.
      </p>
    </div>
  );
}

function Line({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-start justify-between gap-4">
      <dt className="text-slate-500">{label}</dt>
      <dd className="text-right font-medium">{value}</dd>
    </div>
  );
}
