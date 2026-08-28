import { useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertCircle, ArrowRight, ChevronDown, ChevronUp, CircleDashed, Info } from 'lucide-react';
import { panorama as panoramaApi } from '../../api/endpoints';
import { useAsync } from '../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../components/ui';

/**
 * PANORAMA BÖLÜMÜ (V3-B B3) — "Bugün ne var?".
 *
 * Ana ekranın ÜSTÜNE oturur; mevcut özet kartları altında kalır. Yerini
 * TAMAMEN almaz çünkü özet kartları farklı bir soruya cevap verir ("kaç aktif
 * liste var?"), panorama ise "neye müdahale etmeliyim?" sorusuna.
 *
 * ÜÇ GÖRÜNÜM DURUMU, üçü de AYRI:
 *   · brifing var        → öncelik sırasına dizili satırlar
 *   · brifing yok        → katalogdan boş gün cümlesi
 *   · ölçülmeyen alanlar → AYRI, katlanabilir bir bölüm
 *
 * Üçüncüsü emrin açık şartıdır ve önemlidir: ölçülmeyen bir alanı "koşul
 * sağlanmadı" saymak, panelin "her şey yolunda" demesine yol açar — oysa o
 * alana HİÇ BAKILMIYOR. Kullanıcı neyin izlendiğini, neyin izlenmediğini
 * görebilmeli.
 */
export default function PanoramaBolumu() {
  const durum = useAsync(() => panoramaApi.read(), []);
  const [olculmeyenAcik, setOlculmeyenAcik] = useState(false);

  if (durum.loading) return <Skeleton rows={2} />;
  if (durum.error) return <ErrorNote message={durum.error} onRetry={durum.reload} />;
  if (!durum.data) return null;

  const { brifingler, olculmeyen, bos_gun: bosGun } = durum.data;

  return (
    <section className="mb-4" aria-labelledby="panorama-basligi" data-testid="panorama">
      <div className="mb-2 flex items-baseline justify-between gap-2">
        <h2 id="panorama-basligi" className="text-md font-semibold text-ink">
          Bugün ne var?
        </h2>
        {brifingler.length > 0 ? (
          <span className="text-xs text-ink-3">
            {brifingler.length} konu müdahale bekliyor
          </span>
        ) : null}
      </div>

      {brifingler.length === 0 ? (
        <p
          className="card flex items-center gap-2 p-4 text-sm text-ink-2"
          data-testid="panorama-bos-gun"
        >
          <Info size={16} className="shrink-0 text-ink-3" aria-hidden />
          {bosGun}
        </p>
      ) : (
        <ul className="flex flex-col gap-2" data-testid="panorama-brifingler">
          {brifingler.map((brifing) => (
            <li
              key={brifing.id}
              className="card flex items-start gap-3 p-3"
              data-testid={`panorama-${brifing.id}`}
            >
              <OncelikIsareti oncelik={brifing.oncelik} />
              <p className="min-w-0 flex-1 text-sm text-ink">{brifing.cumle}</p>
              {brifing.eylem_linki !== null ? (
                <Link
                  to={brifing.eylem_linki}
                  className="flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-blue hover:bg-blue-soft"
                >
                  {brifing.eylem}
                  <ArrowRight size={14} aria-hidden />
                </Link>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {olculmeyen.length > 0 ? (
        <div className="mt-2">
          <button
            type="button"
            className="flex items-center gap-1.5 text-xs text-ink-3 hover:text-ink-2"
            onClick={() => setOlculmeyenAcik((acik) => !acik)}
            aria-expanded={olculmeyenAcik}
            data-testid="panorama-olculmeyen-dugmesi"
          >
            <CircleDashed size={13} aria-hidden />
            {olculmeyen.length} konu henüz ölçülmüyor
            {olculmeyenAcik ? <ChevronUp size={13} aria-hidden /> : <ChevronDown size={13} aria-hidden />}
          </button>
          {olculmeyenAcik ? (
            <ul className="mt-1.5 flex flex-col gap-1 rounded-lg border border-line-soft bg-g50 p-2.5" data-testid="panorama-olculmeyen">
              {olculmeyen.map((satir) => (
                <li key={satir.id} className="flex gap-2 text-xs text-ink-3">
                  <b className="shrink-0 font-medium text-ink-2">{satir.id}</b>
                  <span>{satir.sebep}</span>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}

/**
 * Öncelik işareti — sayı DEĞİL renk ve ikon. "Öncelik 1" yazmak kullanıcıya
 * bir sıralama sistemi öğretmek demektir; renk ise anında okunur.
 */
function OncelikIsareti({ oncelik }: { oncelik: number }) {
  const stil =
    oncelik === 1 ? 'text-err' : oncelik === 2 ? 'text-warn' : 'text-ink-3';
  const etiket = oncelik === 1 ? 'Acil' : oncelik === 2 ? 'Önemli' : 'Bilgi';

  return (
    <span className="shrink-0" title={etiket}>
      <AlertCircle size={16} className={stil} aria-hidden />
      <span className="sr-only">{etiket}</span>
    </span>
  );
}
