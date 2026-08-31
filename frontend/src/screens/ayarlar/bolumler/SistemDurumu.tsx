import { useState } from 'react';
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

        {/* A6-EK: boş sözlükle çevrilmiş ürünler. SIFIRDA GİZLİ — sıfır
            gösteren bir uyarı bir süre sonra okunmaz hâle gelir ve gerçek
            uyarıyı da görünmez kılar. */}
        {(statusState.data?.sozluksuz_ceviri ?? 0) > 0 ? (
          <SozluksuzCeviriKarti
            sayi={statusState.data?.sozluksuz_ceviri ?? 0}
            onDone={statusState.reload}
          />
        ) : null}

        {statusState.data && statusState.data.migrations.pending_count > 0 ? (
          <MigrationActions onDone={statusState.reload} />
        ) : null}
      </section>
    </>
  );
}

/**
 * A6-EK — "Sözlüksüz çevrilmiş ürün" kartı.
 *
 * NE ANLATIYOR: kuyruk yolu bir dönem boş sözlükle koştu. Ürünlere yanlış
 * metin YAZILMADI (çeviri bir öneridir, ürüne yazılmaz) ama üretilen önbellek
 * satırları başka bir anahtara düştü ve panel onları hiç bulamıyor. Yani
 * toplu çeviri "başarılı" göründü, ürün çevrilmemiş kaldı.
 *
 * Tek düğme, tek eylem: ürünleri MEVCUT çeviri kuyruğuna geri koy. Kuyruk
 * bütçeli ve parçalı çalışır; iş anahtarı idempotenttir, iki kez basmak iki
 * iş açmaz. Veri silinmez — eski satırlar oldukları yerde kalır, yenileri
 * onların yerine OKUNUR.
 */
function SozluksuzCeviriKarti({ sayi, onDone }: { sayi: number; onDone: () => void }) {
  const [calisiyor, setCalisiyor] = useState(false);
  const [sonuc, setSonuc] = useState<string | null>(null);
  const [hata, setHata] = useState<string | null>(null);

  async function yenile() {
    setCalisiyor(true);
    setHata(null);
    try {
      const cevap = await systemApi.sozluksuzCeviriYenile();
      setSonuc(`${count(cevap.kuyruga_alinan)} ürün çeviri kuyruğuna alındı.`);
      onDone();
    } catch (e) {
      // Sessiz yutma yok: kullanıcı düğmeye bastı, sonucu görmeli.
      setHata(e instanceof Error ? e.message : 'Kuyruğa alma başarısız.');
    } finally {
      setCalisiyor(false);
    }
  }

  return (
    <div
      className="mt-3 rounded-lg border border-warn/40 bg-warn-bg p-3 text-sm"
      data-testid="sozluksuz-ceviri"
    >
      <b className="text-warn">Sözlüksüz çevrilmiş ürün: {count(sayi)}</b>
      <p className="mt-0.5 text-xs text-ink-2">
        Bu ürünler için üretilen çeviriler sözlük olmadan hesaplandı ve panel
        onları okuyamıyor — ürün çevrilmemiş görünür. Yeniden çevirmek sorunu
        kapatır; elle düzelttiğiniz alanlar korunur.
      </p>
      <button
        type="button"
        className="btn btn-sm mt-2"
        onClick={yenile}
        disabled={calisiyor}
        data-testid="sozluksuz-ceviri-yenile"
      >
        {calisiyor ? 'Kuyruğa alınıyor…' : `Yeniden çevir (${count(sayi)} ürün)`}
      </button>
      {sonuc !== null ? <p className="mt-1 text-xs text-ok">{sonuc}</p> : null}
      {hata !== null ? <p className="mt-1 text-xs text-err">{hata}</p> : null}
    </div>
  );
}
