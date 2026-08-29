import { settings as settingsApi } from '../../../api/endpoints';
import { useAsync } from '../../../lib/useAsync';
import { dateTime, rate } from '../../../lib/format';
import { ErrorNote, Skeleton } from '../../../components/ui';

/**
 * AYARLAR > 7 KUR GEÇMİŞİ (V3-B C1 · koruma maddesi 3).
 *
 * İE#22 A3 ile uç ÜÇ YENİ ALAN döndürmeye başladı ve bu ekran onları
 * GÖSTERMEK ZORUNDA — göstermezse kur omurgasının getirdiği tek görünür
 * kazanç kaybolur:
 *
 *   · `aktif`          → "hangisi şu an geçerli?" sorusunun cevabı. Bu soru
 *                        İE#22 öncesinde HİÇBİR YERDE cevaplanmıyordu.
 *   · `kaynak`         → değer elle mi onaylandı, TCMB önerisinden mi geldi.
 *   · `superseded_at`  → satırın ne zaman devre dışı kaldığı.
 *
 * `set_at` alan adı KORUNUR: uç onu `effective_from` yerine bu adla döndürüyor
 * ve yeniden adlandırmak ekran sözleşmesini kırardı (İE#22 A3 kararı).
 */
export default function KurTarihcesi() {
  const durum = useAsync(() => settingsApi.rateHistory(), []);

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-3 text-sm font-semibold text-ink-2">Kur geçmişi</h2>
      {durum.loading ? (
        <Skeleton rows={2} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : (durum.data ?? []).length === 0 ? (
        <p className="text-sm text-ink-3">Henüz kur değişikliği kaydedilmedi.</p>
      ) : (
        <div className="table-scroll">
          <table className="w-full text-sm" data-testid="kur-tarihcesi">
            <thead className="text-left text-xs uppercase tracking-wide text-ink-3">
              <tr>
                <th className="py-2">Para birimi</th>
                <th className="py-2 text-right">Kur</th>
                <th className="py-2">Kaynak</th>
                <th className="py-2 text-right">Geçerlilik</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line-soft">
              {(durum.data ?? []).map((satir) => (
                <tr key={satir.id} className={satir.aktif ? 'bg-ok-bg/40' : undefined}>
                  {/* Uç para birimini ISO koduyla verir (CNY/USD) — ekranda Türkçe karşılığı. */}
                  <td className="py-2">
                    {satir.currency === 'CNY' ? 'Yuan → TL' : 'Dolar → TL'}
                    {satir.aktif ? (
                      <span
                        className="ml-1.5 rounded-full bg-ok px-1.5 py-0.5 text-xs font-semibold text-white"
                        data-testid="kur-aktif-rozeti"
                      >
                        geçerli
                      </span>
                    ) : null}
                  </td>
                  <td className="py-2 text-right font-medium">{rate(satir.rate)}</td>
                  <td className="py-2 text-ink-3" data-testid="kur-kaynak">
                    {satir.kaynak === 'tcmb' ? 'TCMB önerisi' : 'Elle onay'}
                  </td>
                  <td className="py-2 text-right text-ink-3">
                    {dateTime(satir.set_at)}
                    {satir.superseded_at !== null ? (
                      <span className="block text-xs" data-testid="kur-bitis">
                        → {dateTime(satir.superseded_at)}
                      </span>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
