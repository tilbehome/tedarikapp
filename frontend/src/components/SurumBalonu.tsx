import { useEffect, useState } from 'react';
import { Sparkles, X } from 'lucide-react';
import { surumNotu as surumApi } from '../api/endpoints';

/**
 * "YENİLİKLER" BALONU (V3-B B4).
 *
 * Yeni bir sürüm kurulduğunda BİR KEZ görünür. A5 desenine uyar: sağ alt
 * köşede durur, modal değildir, sayfayı bloklamaz, tek kapatma düğmesi vardır.
 *
 * Okundu işareti sunucudadır — kapatınca `POST /api/surum-notu/goruldu`
 * çağrılır. Yalnız tarayıcıda tutulsaydı kullanıcı ikinci cihazında aynı
 * balonu yeniden görürdü.
 *
 * Pazarlama dili YOKTUR: metin `docs/surum-notlari/` altındaki dosyadan gelir
 * ve orada da "harika yenilikler!" değil, ne değiştiği yazar.
 */
export default function SurumBalonu() {
  const [not, setNot] = useState<{ surum: string; maddeler: string[] } | null>(null);

  useEffect(() => {
    let iptal = false;

    surumApi
      .guncel()
      .then((veri) => {
        if (iptal || !veri.gorulmedi || veri.maddeler.length === 0) return;
        setNot({ surum: veri.surum, maddeler: veri.maddeler });
      })
      // Balon YARDIMCI yüzeydir: alınamazsa panel sessizce çalışır.
      .catch(() => undefined);

    return () => {
      iptal = true;
    };
  }, []);

  const kapat = (): void => {
    setNot(null);
    // İşaret sunucuya yazılamasa bile balon KAPANIR: kullanıcıyı kapanmayan
    // bir kutuyla baş başa bırakmak, işaretin kaybolmasından kötüdür.
    surumApi.goruldu().catch(() => undefined);
  };

  if (not === null) return null;

  return (
    <aside
      className="fixed bottom-4 right-4 z-40 w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-line bg-surface p-3 shadow-3"
      aria-labelledby="surum-balonu-basligi"
      data-testid="surum-balonu"
    >
      <div className="flex items-start gap-2">
        <Sparkles size={16} className="mt-0.5 shrink-0 text-gold" aria-hidden />
        <div className="min-w-0 flex-1">
          <b id="surum-balonu-basligi" className="block text-md font-semibold text-ink">
            Sürüm {not.surum} — yenilikler
          </b>
          <ul className="mt-1.5 flex list-disc flex-col gap-1 pl-4 text-sm text-ink-2">
            {not.maddeler.slice(0, 4).map((madde, sira) => (
              <li key={sira}>{madde.replace(/\*\*/g, '')}</li>
            ))}
          </ul>
        </div>
        <button
          type="button"
          className="flex size-7 shrink-0 items-center justify-center rounded-lg text-ink-3 hover:bg-g50 hover:text-ink"
          onClick={kapat}
          aria-label="Yenilikleri kapat"
          data-testid="surum-balonu-kapat"
        >
          <X size={15} aria-hidden />
        </button>
      </div>
    </aside>
  );
}
