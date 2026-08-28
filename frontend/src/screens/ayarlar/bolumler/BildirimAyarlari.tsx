import { Bell } from 'lucide-react';
import { bildirimler as bildirimApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../../components/ui';
import { dateTime } from '../../../lib/format';

/**
 * AYARLAR > 13 BİLDİRİMLER (V3-B C1).
 *
 * Bu sekme bugün AYAR DEĞİL DURUM gösterir: bildirim merkezinin ne ürettiğini
 * ve birleştirmenin çalışıp çalışmadığını. Eşik/sessize alma ayarları
 * eklenmedi ve bu bilinçli: hangi olayın gürültü olduğunu bilmeden eşik
 * koymak, kullanıcıya kendi kendine kapatacağı bir sistem vermek olurdu.
 * Önce ne üretildiği görülür, sonra ayarı konuşulur.
 */
export default function BildirimAyarlari() {
  const durum = useAsync(() => bildirimApi.read(), []);

  return (
    <section className="card p-4">
      <h2 className="mb-1 flex items-center gap-2 text-sm font-semibold text-ink-2">
        <Bell className="h-4 w-4 text-navy" aria-hidden />
        Bildirim merkezi
      </h2>
      <p className="mb-3 text-xs text-ink-3">
        Önemli her işlem bildirim üretir. Sık tekrar eden olaylar tek satırda birleşir ve
        satırda "×N" olarak sayılır. Kritik bildirimler ayrıca sağ üstte bir kartla duyurulur.
      </p>

      {durum.loading ? (
        <Skeleton rows={2} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : durum.data ? (
        <dl className="space-y-2 text-sm">
          <div className="flex justify-between gap-3">
            <dt className="text-ink-3">Okunmamış</dt>
            <dd className="font-medium text-ink">{durum.data.okunmamis}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-ink-3">Kayıtlı bildirim</dt>
            <dd className="font-medium text-ink">{durum.data.bildirimler.length}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-ink-3">Son bildirim</dt>
            <dd className="font-medium text-ink">
              {durum.data.bildirimler[0] !== undefined
                ? dateTime(durum.data.bildirimler[0].created_at)
                : 'Henüz yok'}
            </dd>
          </div>
        </dl>
      ) : null}
    </section>
  );
}
