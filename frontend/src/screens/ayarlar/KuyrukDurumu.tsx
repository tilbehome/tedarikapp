import { AlertTriangle, CheckCircle2, ListChecks, PencilLine, RotateCcw, Trash2 } from 'lucide-react';
import { system as systemApi } from '../../api/endpoints';
import { useAsync, messageOf } from '../../lib/useAsync';
import { ErrorNote, Skeleton } from '../../components/ui';
import { useToast } from '../../components/Toast';
import EylemDugmesi from '../../components/EylemDugmesi';

/**
 * AYARLAR > KUYRUK DURUMU (İE#20 C3).
 *
 * Kuyruğun panelde görünür olması bir süs değil, bir ZORUNLULUKTUR: arka planda
 * koşan iş, görünmezse hiç koşmadığında da görünmez. İki sinyal asıl önemlidir:
 *
 *  • **Ölü iş** — bir şey KALICI olarak başarısız oldu (yanlış API anahtarı,
 *    silinmiş ürün). Sessiz kalırsa kullanıcı "çeviri neden gelmedi?" diye sorar
 *    ve cevabı hiçbir yerde bulamaz.
 *  • **En eski bekleyen işin yaşı** — büyüyorsa işler akmıyordur. Bekleyen sayısı
 *    tek başına bunu göstermez (az iş de olsa saatlerdir bekliyor olabilir).
 *
 * D12 — CRON ARTIK ZORUNLU DEĞİL. İşler panel ziyaretinde, yakalamadan sonra ve
 * "Çevir" düğmelerinde de akar; cron varsa yalnız fazlalıktır. Bu yüzden kart
 * "cron koşmuyor olabilir" DEMEZ: kullanıcının kurmadığı bir şeyi hatırlatmak,
 * olmayan bir arızayı bildirmekti. Uyarı yalnız GERÇEK tıkanmada görünür —
 * birikme var VE hiçbir tetikleyici tur açamıyor.
 */
export default function KuyrukDurumu() {
  const push = useToast((state) => state.push);
  const durum = useAsync((signal) => systemApi.kuyruk(signal), []);
  const veri = durum.data;

  return (
    <section className="card mb-4 p-4">
      <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-ink-2">
        <ListChecks className="h-4 w-4" aria-hidden />
        Arka plan işleri
      </h2>
      <p className="mb-3 text-xs text-ink-3" data-testid="kuyruk-aciklama">
        Çeviri ve görsel indirme gibi işler burada görünür. <strong>Hiçbir kurulum
        gerekmez:</strong> paneli kullandıkça işler kendiliğinden akar. Buradaki
        sayılar bir arıza olup olmadığını görebilmeniz içindir — sessiz çalışan
        hiçbir eylem yoktur.
      </p>

      {durum.loading ? (
        <Skeleton rows={1} />
      ) : durum.error ? (
        <ErrorNote message={durum.error} onRetry={durum.reload} />
      ) : !veri?.kurulu ? (
        <p className="text-sm text-ink-3">{veri?.mesaj ?? 'Kuyruk henüz kurulmadı.'}</p>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Kutu etiket="Bekleyen" deger={veri.bekleyen} />
            <Kutu etiket="Çalışan" deger={veri.calisan} />
            <Kutu etiket="Ölü" deger={veri.olu} vurgu={veri.olu > 0} />
            <Kutu
              etiket="En eski bekleyen"
              deger={veri.en_eski_bekleyen_dakika === null ? '—' : `${veri.en_eski_bekleyen_dakika} dk`}
              vurgu={(veri.en_eski_bekleyen_dakika ?? 0) > 60}
            />
          </div>

          {/* B11: ikinci satır ölçüm — bir saatlik pencere. Gün ortalaması,
              yarım saattir süren bir arızayı gizler. */}
          <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Kutu etiket="Son 1 saat · biten" deger={veri.saatlik_biten ?? 0} />
            <Kutu etiket="Son 1 saat · ölen" deger={veri.saatlik_olen ?? 0} vurgu={(veri.saatlik_olen ?? 0) > 0} />
            <Kutu
              etiket="Hata oranı"
              deger={`%${veri.hata_orani_yuzde ?? 0}`}
              vurgu={(veri.hata_orani_yuzde ?? 0) >= 30}
            />
            <Kutu
              etiket="Yeniden denenen"
              deger={veri.yeniden_denenen ?? 0}
              vurgu={(veri.yeniden_denenen ?? 0) > 0}
            />
          </div>

          {/* D6: ERTELENEN ≠ BAŞARISIZ. Bellek bütçesi dolduğu için sonraki tura
              kalan iş hata yapmamıştır; onu hata oranına katmak, bütçenin dolduğu
              bir geceyi "sağlayıcı arızası" gibi gösterirdi. */}
          {(veri.ertelenen ?? 0) > 0 ? (
            <p className="mt-2 text-xs text-ink-3" data-testid="kuyruk-ertelenen">
              {veri.ertelenen} iş ertelendi (bellek bütçesi / koşul) — hata değil, sonraki turda kaldığı yerden sürer.
            </p>
          ) : null}

          {veri.uyari ? (
            <p className="mt-3 flex items-start gap-2 rounded-lg bg-warn-bg p-2 text-xs text-warn">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              {veri.uyari}
            </p>
          ) : (
            <p className="mt-3 flex items-center gap-2 text-xs text-ok">
              <CheckCircle2 className="h-4 w-4" aria-hidden />
              Kuyruk sağlıklı.
            </p>
          )}

          {veri.olu_isler.length > 0 ? (
            <div className="mt-3 rounded-lg border border-line">
              <p className="border-b border-line px-3 py-2 text-xs font-semibold text-ink-2">
                Ölü raf — kalıcı olarak başarısız işler
              </p>
              <ul className="divide-y divide-line text-xs">
                {veri.olu_isler.map((is) => (
                  <li key={is.id} className="flex flex-wrap items-center gap-2 px-3 py-2">
                    <span className="font-mono text-ink-2">
                      {is.tur}
                      {is.anahtar ? ` · ${is.anahtar}` : ''}
                    </span>
                    <span className="text-err">{is.hata ?? 'hata kaydı yok'}</span>
                    <EylemDugmesi
                      className="btn-ghost ml-auto !min-h-8 !px-2 !text-xs"
                      mesgulEtiketi="Deneniyor"
                      onEylem={async () => {
                        await systemApi.kuyrukYenidenDene(is.id);
                        durum.reload();
                        push('İş yeniden kuyruğa alındı.');
                      }}
                      onHata={(hata) => push(messageOf(hata), 'error')}
                    >
                      <span className="inline-flex items-center gap-1">
                        <RotateCcw className="h-3.5 w-3.5" aria-hidden />
                        Yeniden dene
                      </span>
                    </EylemDugmesi>
                    {/* B11: "düzelt" — yükü değiştirip yeniden kuyruğa alır.
                        Denetim izi KOPMAZ: aynı satırda kalır, kaç kez denendiği
                        ve ne hata aldığı görünmeye devam eder. */}
                    <EylemDugmesi
                      className="btn-ghost !min-h-8 !px-2 !text-xs"
                      mesgulEtiketi="Kaydediliyor"
                      onEylem={async () => {
                        const mevcut = JSON.stringify(is.yuk ?? {}, null, 0);
                        const girilen = window.prompt(
                          'İşin yükünü düzeltin (JSON). Örn. {"urun_id": 12}',
                          mevcut,
                        );
                        if (girilen === null) return;
                        let yuk: Record<string, unknown>;
                        try {
                          yuk = JSON.parse(girilen) as Record<string, unknown>;
                        } catch {
                          push('Geçerli bir JSON girin.', 'error');
                          return;
                        }
                        await systemApi.kuyrukDuzelt(is.id, yuk);
                        durum.reload();
                        push('Yük düzeltildi, iş yeniden kuyruğa alındı.');
                      }}
                      onHata={(hata) => push(messageOf(hata), 'error')}
                    >
                      <span className="inline-flex items-center gap-1">
                        <PencilLine className="h-3.5 w-3.5" aria-hidden />
                        Düzelt
                      </span>
                    </EylemDugmesi>
                    <EylemDugmesi
                      className="btn-ghost !min-h-8 !px-2 !text-xs text-err"
                      mesgulEtiketi="Siliniyor"
                      onEylem={async () => {
                        if (!window.confirm('Bu iş kuyruktan SİLİNECEK. Geri alınamaz. Devam?')) return;
                        await systemApi.kuyrukVazgec(is.id);
                        durum.reload();
                        push('Ölü iş silindi.');
                      }}
                      onHata={(hata) => push(messageOf(hata), 'error')}
                    >
                      <span className="inline-flex items-center gap-1">
                        <Trash2 className="h-3.5 w-3.5" aria-hidden />
                        Vazgeç
                      </span>
                    </EylemDugmesi>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </>
      )}
    </section>
  );
}

function Kutu({ etiket, deger, vurgu = false }: { etiket: string; deger: number | string; vurgu?: boolean }) {
  return (
    <div className={`rounded-lg border p-2 ${vurgu ? 'border-warn bg-warn-bg' : 'border-line bg-g50'}`}>
      <p className="text-xs text-ink-3">{etiket}</p>
      <p className={`text-lg font-semibold ${vurgu ? 'text-warn' : 'text-ink'}`}>{deger}</p>
    </div>
  );
}
