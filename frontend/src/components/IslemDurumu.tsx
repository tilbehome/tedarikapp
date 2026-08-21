import { AlertTriangle, CheckCircle2, Loader2, XCircle } from 'lucide-react';
import type { UzunIslem } from '../lib/useUzunIslem';

/**
 * Uzun süren işlemin GÖRÜNEN yüzü (İE#14 C2) — `useUzunIslem` ile birlikte kullanılır.
 *
 * Çalışırken: dönen simge + ne yapıldığı + geçen süre (+ varsa gerçek ilerleme).
 * 60 sn sonra: "beklenenden uzun sürüyor" uyarısı ve İptal düğmesi.
 * Bitince: sonuç kartı (başarılı/başarısız) — kullanıcı ne olduğunu okuyup
 * gerekirse tekrar deneyebilir. Sonuç kendiliğinden kaybolmaz; bildirim baloncuğu
 * kaçırılabilir, kart kalıcıdır.
 */
export default function IslemDurumu({
  islem,
  fiil,
  onTekrar,
}: {
  islem: UzunIslem;
  /** Şu an ne yapılıyor: "Görseller arşive taşınıyor" gibi. */
  fiil: string;
  onTekrar?: () => void;
}) {
  if (islem.calisiyor) {
    return (
      <div
        className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
        role="status"
        aria-live="polite"
      >
        <p className="flex items-center gap-2 font-medium text-slate-700">
          <Loader2 className="h-4 w-4 animate-spin text-brand-600" aria-hidden />
          {fiil}… <span className="font-normal text-slate-500">({sure(islem.gecenSaniye)})</span>
        </p>
        {islem.ilerleme ? <p className="mt-1 pl-6 text-xs text-slate-600">{islem.ilerleme}</p> : null}
        {islem.uzunSuruyor ? (
          <div className="mt-2 flex flex-wrap items-center gap-2 pl-6">
            <span className="inline-flex items-center gap-1 text-xs font-medium text-amber-700">
              <AlertTriangle className="h-3.5 w-3.5" aria-hidden />
              Beklenenden uzun sürüyor. Sayfayı kapatmayın; işlem sunucuda devam ediyor.
            </span>
            <button type="button" className="btn-ghost !min-h-8 !px-2 !text-xs" onClick={islem.iptalEt}>
              İptal et
            </button>
          </div>
        ) : null}
      </div>
    );
  }

  if (!islem.sonuc) return null;

  const { basarili, metin } = islem.sonuc;

  return (
    <div
      className={`mt-3 rounded-xl border p-3 text-sm ${
        basarili ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900'
      }`}
      role="status"
      aria-live="polite"
    >
      <p className="flex items-start gap-2">
        {basarili ? (
          <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
        ) : (
          <XCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
        )}
        <span>{metin}</span>
      </p>
      <div className="mt-2 flex gap-2">
        {!basarili && onTekrar ? (
          <button type="button" className="btn-ghost !min-h-8 !px-2 !text-xs" onClick={onTekrar}>
            Tekrar dene
          </button>
        ) : null}
        <button type="button" className="btn-ghost !min-h-8 !px-2 !text-xs" onClick={islem.temizle}>
          Kapat
        </button>
      </div>
    </div>
  );
}

function sure(saniye: number): string {
  if (saniye < 60) return `${saniye} sn`;
  const dakika = Math.floor(saniye / 60);

  return `${dakika} dk ${saniye % 60} sn`;
}
