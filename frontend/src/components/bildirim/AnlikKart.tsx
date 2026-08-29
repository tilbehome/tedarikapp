import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, X } from 'lucide-react';
import { bildirimler as bildirimApi, type Bildirim } from '../../api/endpoints';

/**
 * ANLIK BİLDİRİM KARTI (V3-B A5 — PM görünüm kararı).
 *
 * SÖZLEŞME, madde madde:
 *   · SAĞ ÜST köşede durur; modal DEĞİLDİR, sayfayı BLOKLAMAZ.
 *   · Başlık + kısa açıklama + TEK eylem düğmesi + kapatma (X).
 *   · YALNIZ `onem = kritik` olaylarda çıkar. Kararı SUNUCU verir (`anlik`
 *     alanı); panel önem eşiğini kendi yorumlamaz.
 *   · Aynı olay OTURUM BAŞINA BİR KEZ gösterilir. `sessionStorage` bunun için
 *     doğru yerdir: sekme kapanınca sıfırlanır, `localStorage` olsaydı kritik
 *     bir olay haftalarca bir daha görünmezdi.
 *   · Kapatılan kart bildirimi SİLMEZ ve OKUNDU SAYMAZ — merkezde durmaya
 *     devam eder. "Kapat" bir eylem değil, bir erteleme.
 *   · Pazarlama dili ve emoji YOKTUR; metin katalogdan gelir.
 */
export default function AnlikKart() {
  const [kart, setKart] = useState<Bildirim | null>(null);

  useEffect(() => {
    let iptal = false;

    bildirimApi
      .read(true)
      .then((veri) => {
        if (iptal || veri.anlik === null) return;
        if (gosterildiMi(veri.anlik.id)) return;
        setKart(veri.anlik);
        gosterildiIsaretle(veri.anlik.id);
      })
      // Anlık kart YARDIMCI bir yüzeydir: alınamazsa panel sessizce çalışır.
      // Kullanıcıya "bildirim kartı yüklenemedi" demek gürültüden başka bir şey
      // değildir — bildirim merkezi zaten aynı satırı gösteriyor.
      .catch(() => undefined);

    return () => {
      iptal = true;
    };
  }, []);

  if (kart === null) return null;

  return (
    <div
      className="fixed right-4 top-16 z-50 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-err/40 bg-surface p-3 shadow-3"
      role="status"
      aria-live="polite"
      data-testid="bildirim-anlik-kart"
    >
      <div className="flex items-start gap-2">
        <AlertTriangle size={16} className="mt-0.5 shrink-0 text-err" aria-hidden />
        <div className="min-w-0 flex-1">
          <b className="block text-md font-semibold text-ink">{kart.baslik}</b>
          <p className="mt-0.5 text-sm text-ink-2">{kart.govde}</p>
          {kart.eylem_linki !== null ? (
            <Link
              to={kart.eylem_linki.replace(/^\/panel/, '')}
              className="mt-2 inline-block rounded-lg bg-blue px-3 py-1.5 text-sm font-medium text-white hover:bg-navy-2"
              onClick={() => setKart(null)}
            >
              Aç
            </Link>
          ) : null}
        </div>
        <button
          type="button"
          className="flex size-7 shrink-0 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
          onClick={() => setKart(null)}
          aria-label="Kartı kapat"
        >
          <X size={15} aria-hidden />
        </button>
      </div>
    </div>
  );
}

const ANAHTAR = 'tdk-anlik-gosterilen';

/** Bu bildirim bu oturumda gösterildi mi? */
function gosterildiMi(id: number): boolean {
  try {
    const ham = sessionStorage.getItem(ANAHTAR);

    return ham !== null && ham.split(',').includes(String(id));
  } catch {
    // Depolama kapalıysa (gizli sekme, katı gizlilik ayarı) kart HER AÇILIŞTA
    // görünür. Kritik bir olayı hiç göstermemektense fazla göstermek yeğdir.
    return false;
  }
}

function gosterildiIsaretle(id: number): void {
  try {
    const ham = sessionStorage.getItem(ANAHTAR);
    const liste = ham === null || ham === '' ? [] : ham.split(',');
    liste.push(String(id));
    sessionStorage.setItem(ANAHTAR, liste.slice(-20).join(','));
  } catch {
    // Yazılamıyorsa sorun değil; yukarıdaki okuma da başarısız olacak.
  }
}
