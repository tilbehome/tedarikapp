import { useState } from 'react';

import { settings as settingsApi, system as systemApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { dateTime } from '../../../lib/format';
import { mediaModeLabels } from '../../../locales/tr';
import { ErrorNote, Skeleton } from '../../../components/ui';
import IslemDurumu from '../../../components/IslemDurumu';
import { useUzunIslem } from '../../../lib/useUzunIslem';
import Satir from './Satir';

/**
 * AYARLAR > 16 VERİ & BAKIM (V3-B C1).
 *
 * PM eşlemesi: görsel arşivi, yedekleme (medya yedeği DAHİL), migration
 * eylemleri ve sistem durumu bu sekmede toplanır. Dördü de aynı soruyu
 * cevaplar: "verim güvende mi ve sistem sağlıklı mı?"
 */
export default function VeriBakim() {
  const settingsState = useAsync(() => settingsApi.read(), []);

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

      <KurtarmaAnahtariCard />


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
        : ' Uzak hedef yapılandırılmadı — parçaları indirip ayrı bir yerde saklayın.';
      // Atlanan medya SESSİZ GEÇMEZ: yedek "alındı" görünüp görsellerin
      // eksik olması, tam da geri yüklerken öğrenilecek türden bir sürprizdir.
      const medya = result.backup.medya_atlandi
        ? ' UYARI: tek başına boyut sınırını aşan bazı görseller sete girmedi.'
        : '';

      return `Yedek seti alındı: ${result.backup.parca_sayisi} parça.${offsite}${medya}`;
    });

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
            <YedekSetiSatiri key={entry.name} set={entry} onDogrulandi={state.reload} />
          ))}
        </ul>
      ) : (
        <p className="text-sm text-ink-3">Henüz yedek alınmadı.</p>
      )}
      <p className="mt-2 text-xs text-ink-3">
        Yedek bir SETTİR: şifreli veritabanı dökümü + ayarlar + medya parçaları (çözme APP_KEY
        ister). Parçalar tek tek indirilir — tamamını tek bir zip'e koymak, paylaşımlı
        sunucuda gigabaytlarca medyayı geçici bir arşive yazmak demek olurdu. İndirdiğiniz
        dosyanın sağlam olduğunu SHA-256 ile karşılaştırarak doğrulayabilirsiniz. Saklama: eski
        setler otomatik silinir, en yeni 5 her koşulda korunur.{' '}
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

/** Parça türünün insan dili — dosya adı teknik, tür anlaşılır olmalı. */
const PARCA_TURLERI: Record<string, string> = {
  sql: 'Veritabanı',
  config: 'Ayarlar',
  medya: 'Görseller',
};

/**
 * v1.2.2 B4 — YEDEK SETİ SATIRI.
 *
 * PM kararı (3 Eyl): "tümünü zip indir" düğmesi YOK. Bu bilinçli bir tercih ve
 * bir bedeli var: kullanıcı artık hangi parçaları indirdiğini kendi takip
 * eder. Kart bu bedeli ödenebilir kılmak zorunda — yoksa karar, kullanıcıyı
 * yalnız bırakmak olur:
 *
 *   · parçalar SIRAYLA listelenir (geri yükleme sırası budur),
 *   · her parçanın SHA-256'sı görünür ve kopyalanabilir — indirmenin
 *     bozulmadığını karşı tarafta doğrulamanın tek yolu odur,
 *   · manifest ayrıca indirilebilir: setin içindekilerin kaydı odur,
 *   · "Yedeği doğrula" sunucuda manifesti diske karşı sınar ve GERİ YÜKLEME
 *     YAPMAZ — bakmak yıkıcı olmadığı için kullanıcı istediği an bakabilir.
 */
function YedekSetiSatiri({
  set,
  onDogrulandi,
}: {
  set: {
    name: string;
    size: number;
    created_at: string;
    tam: boolean;
    kismi: boolean;
    parca_sayisi: number;
    parcalar: { ad: string; tur: string; sira: number; boyut: number; sha256: string }[];
  };
  onDogrulandi: () => void;
}) {
  const [acik, setAcik] = useState(false);
  const dogrulama = useUzunIslem();

  const sizeOf = (bytes: number) =>
    bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

  const dogrula = () =>
    void dogrulama.baslat(async () => {
      const sonuc = await systemApi.backupVerify(set.name);
      onDogrulandi();
      // Rapor sunucudan gelir: "geçerli" kelimesini burada yeniden üretmek,
      // iki yerde iki farklı doğruluk ölçütü demek olurdu.
      return sonuc.rapor;
    });

  return (
    <li className="py-2">
      <div className="flex items-center justify-between gap-3">
        <button
          type="button"
          className="min-w-0 flex-1 truncate text-left font-mono text-xs hover:underline"
          aria-expanded={acik}
          onClick={() => setAcik((onceki) => !onceki)}
        >
          {acik ? '▾' : '▸'} {set.name}
        </button>
        {set.kismi ? (
          <span className="badge bg-warn-soft text-warn ring-warn/20" title="Görsel parçası yok — veritabanı ve ayarlar geri yüklenebilir.">
            görselsiz
          </span>
        ) : null}
        <span className="text-ink-3">{set.parca_sayisi} parça</span>
        <span className="text-ink-3">{sizeOf(set.size)}</span>
        <span className="text-ink-3">{dateTime(set.created_at)}</span>
        <button type="button" className="btn-ghost" disabled={dogrulama.calisiyor} onClick={dogrula}>
          {dogrulama.calisiyor ? 'Doğrulanıyor…' : 'Doğrula'}
        </button>
      </div>
      {acik ? (
        <ul className="mt-2 space-y-1 rounded bg-surface-2 p-2 text-xs">
          {set.parcalar.map((parca) => (
            <li key={parca.ad} className="flex items-center justify-between gap-2">
              <span className="w-20 shrink-0 text-ink-3">{PARCA_TURLERI[parca.tur] ?? parca.tur}</span>
              <span className="min-w-0 flex-1 truncate font-mono">{parca.ad}</span>
              <span className="text-ink-3">{sizeOf(parca.boyut)}</span>
              <code
                className="hidden max-w-[10rem] truncate text-ink-3 sm:inline"
                title={`SHA-256: ${parca.sha256}`}
              >
                {parca.sha256.slice(0, 12)}…
              </code>
              <a className="btn-ghost" href={systemApi.backupFileUrl(set.name, parca.ad)}>
                İndir
              </a>
            </li>
          ))}
          <li className="flex items-center justify-between gap-2 border-t border-line-soft pt-1">
            <span className="w-20 shrink-0 text-ink-3">Manifest</span>
            <span className="min-w-0 flex-1 truncate font-mono">MANIFEST.json</span>
            <span className="text-ink-3">parçaların listesi ve SHA-256'ları</span>
            <a className="btn-ghost" href={systemApi.backupFileUrl(set.name, 'MANIFEST.json')}>
              İndir
            </a>
          </li>
        </ul>
      ) : null}
      <IslemDurumu islem={dogrulama} fiil="Yedek doğrulanıyor" onTekrar={dogrula} />
    </li>
  );
}

/**
 * v1.2.2 B2 — KURTARMA ANAHTARI (APP_KEY) EMANETİ.
 *
 * Yedekler APP_KEY ile şifrelenir ve anahtar sunucuda durur. Sunucu tamamen
 * giderse elinizde AÇILAMAYAN şifreli dosyalar kalır: yedeğin varlığı hiçbir
 * şey ifade etmez. Bu kart, anahtarın sunucu dışında bir kopyasını almanın
 * tek yoludur.
 *
 * UYARI KALICIDIR — "0'da gizle" kuralı burada GEÇMEZ (PM kararı). O kural,
 * sıfır olduğunda anlamsızlaşan SAYAÇLAR içindir. Buradaki uyarı bir sayı
 * bildirmiyor; HENÜZ YAPILMAMIŞ bir işi hatırlatıyor. Gizlenirse, tam da
 * hatırlatması gereken durumda susmuş olur.
 *
 * Anahtar sunucuda "gösterildi mi" diye bir bayrak TUTULMAZ: kullanıcı
 * anahtarı gerçekten güvenli bir yere koydu mu, sunucunun bilmesi mümkün
 * değil. Bildiğini varsaymak, yanlış bir güven duygusu üretirdi.
 */
function KurtarmaAnahtariCard() {
  const [sifre, setSifre] = useState('');
  const [anahtar, setAnahtar] = useState<{ app_key: string; kurtarma_metni: string } | null>(null);
  const gosterim = useUzunIslem();

  const goster = () =>
    void gosterim.baslat(async () => {
      const sonuc = await systemApi.appKeyReveal(sifre);
      setAnahtar(sonuc);
      setSifre('');

      return 'Kurtarma anahtarı gösterildi — sunucu DIŞINDA bir yere kaydedin.';
    });

  const indir = () => {
    if (anahtar === null) return;
    const url = URL.createObjectURL(new Blob([anahtar.kurtarma_metni + '\n\nANAHTAR: ' + anahtar.app_key + '\n'], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'tedarikapp-kurtarma-anahtari.txt';
    link.click();
    URL.revokeObjectURL(url);
  };

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-2 text-sm font-semibold text-ink-2">Kurtarma anahtarı</h2>
      <p className="mb-3 rounded border border-warn/30 bg-warn-soft p-2 text-sm text-warn">
        Yedekleriniz bu anahtarla şifrelenir. Sunucu tamamen kaybolursa (hesap kapanması, disk
        arızası, sağlayıcı değişikliği) yedekleriniz bu anahtar olmadan açılamaz. Anahtarın sunucu
        DIŞINDA bir kopyası yoksa, yedekleriniz sizi felaketten korumaz.
      </p>
      {anahtar === null ? (
        <>
          <label className="block text-sm text-ink-2" htmlFor="kurtarma-sifre">
            Anahtarı görmek için şifrenizi yeniden girin
          </label>
          <input
            id="kurtarma-sifre"
            type="password"
            autoComplete="current-password"
            className="input mt-1 w-full max-w-xs"
            value={sifre}
            onChange={(olay) => setSifre(olay.target.value)}
          />
          <p className="mt-1 text-xs text-ink-3">
            Açık oturum tek başına yetmez: bilgisayarınıza erişen biri anahtarı alamasın diye
            şifre ikinci kapıdır. Her görüntüleme aktivite kaydına yazılır.
          </p>
          <button
            type="button"
            className="btn-primary mt-3"
            disabled={gosterim.calisiyor || sifre === ''}
            onClick={goster}
          >
            {gosterim.calisiyor ? 'Doğrulanıyor…' : 'Anahtarı göster'}
          </button>
        </>
      ) : (
        <>
          <code className="block break-all rounded bg-surface-2 p-2 font-mono text-xs">{anahtar.app_key}</code>
          <div className="mt-3 flex gap-2">
            <button type="button" className="btn-primary" onClick={indir}>
              Anahtarı ve yönergeyi indir
            </button>
            <button type="button" className="btn-ghost" onClick={() => setAnahtar(null)}>
              Gizle
            </button>
          </div>
          <pre className="mt-3 max-h-48 overflow-auto whitespace-pre-wrap rounded bg-surface-2 p-2 text-xs text-ink-2">
            {anahtar.kurtarma_metni}
          </pre>
        </>
      )}
      <IslemDurumu islem={gosterim} fiil="Şifre doğrulanıyor" onTekrar={goster} />
    </section>
  );
}
