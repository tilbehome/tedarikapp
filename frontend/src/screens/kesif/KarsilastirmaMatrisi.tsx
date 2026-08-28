import { Check, X } from 'lucide-react';
import type { KesifSatiri } from '../../api/kesif';
import { metniNormalize } from '../../lib/metin';

/**
 * KARŞILAŞTIRMA MATRİSİ (İE#21 B1 · E2E-PNL-09/10/11).
 *
 * 2–6 ürün yan yana. Üst sınır 6'dır ve bu bir ekran kararı değil, bir OKUNABİLİRLİK
 * kararıdır: yedinci sütunda hücreler daralır, sayılar alt alta kayar ve matris
 * "karşılaştırma" olmaktan çıkar. Sınır sunucuda da uygulanır — arayüzü atlayan
 * istemci de aynı cevabı alır.
 *
 * İKİ GÖRSEL İŞARET:
 *  • **FARKLI HÜCRE VURGUSU** — bütün ürünlerde aynı olan değer soluk basılır.
 *    Karşılaştırmanın amacı FARKI görmektir; aynı olan satır dikkat çalmamalıdır.
 *  • **SATIR BAŞINA "EN İYİ"** — hangi üründe hangi ölçüt en iyi, tik ile işaretlenir.
 *    "En iyi" ölçüte göre değişir: fiyatta ve MOQ'da düşük, skorda ve puanda yüksek.
 */

interface Props {
  urunler: KesifSatiri[];
  enIyiler: Record<string, number | null>;
  onKapat: () => void;
}

interface Satir {
  alan: string;
  etiket: string;
  deger: (u: KesifSatiri) => string;
}

const SATIRLAR: Satir[] = [
  { alan: 'skor', etiket: 'Skor', deger: (u) => (u.skor === null ? 'Veri yetersiz' : String(u.skor)) },
  { alan: 'birim_fiyat', etiket: 'Birim fiyat', deger: (u) => (u.birim_fiyat ? `¥${u.birim_fiyat}` : '—') },
  { alan: 'moq', etiket: 'MOQ', deger: (u) => (u.moq === null ? '—' : String(u.moq)) },
  { alan: 'satis', etiket: 'Satış (30g)', deger: (u) => (u.satis === null ? '—' : String(u.satis)) },
  { alan: 'puan', etiket: 'Puan', deger: (u) => (u.puan === null ? '—' : u.puan.toFixed(2)) },
  { alan: 'yorum', etiket: 'Yorum', deger: (u) => (u.yorum === null ? '—' : String(u.yorum)) },
  { alan: 'satici', etiket: 'Satıcı', deger: (u) => u.satici ?? '—' },
  { alan: 'platform', etiket: 'Platform', deger: (u) => u.platform ?? '—' },
  { alan: 'kategori', etiket: 'Kategori', deger: (u) => u.kategori ?? 'Atanmadı' },
  { alan: 'video_var', etiket: 'Video', deger: (u) => (u.video_var ? 'Var' : 'Yok') },
];

export default function KarsilastirmaMatrisi({ urunler, enIyiler, onKapat }: Props) {
  return (
    <section className="card mb-4 p-4" data-testid="karsilastirma-matrisi">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-ink-2">
          Karşılaştırma · {urunler.length} ürün
        </h2>
        <button type="button" className="btn-ghost !min-h-8 !px-2" onClick={onKapat} aria-label="Karşılaştırmayı kapat">
          <X className="h-4 w-4" aria-hidden />
        </button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead>
            <tr>
              <th className="w-32 border-b border-line px-2 py-2 text-left text-xs font-medium text-ink-3">
                Ölçüt
              </th>
              {urunler.map((urun) => (
                <th key={urun.id} className="border-b border-line px-2 py-2 text-left">
                  <span className="block truncate text-xs font-semibold text-ink" title={urun.ad}>
                    {metniNormalize(urun.ad)}
                  </span>
                  {urun.ad_orijinal ? (
                    <span className="block truncate text-[11px] font-normal text-ink-3" title={urun.ad_orijinal}>
                      {metniNormalize(urun.ad_orijinal)}
                    </span>
                  ) : null}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {SATIRLAR.map((satir) => {
              const degerler = urunler.map((u) => satir.deger(u));
              // Hepsi aynıysa satır SOLUK: fark yoksa dikkat çalmamalı.
              const farkVar = new Set(degerler).size > 1;

              return (
                <tr key={satir.alan} className={farkVar ? '' : 'opacity-60'}>
                  <th className="border-b border-line-soft px-2 py-1.5 text-left text-xs font-medium text-ink-3">
                    {satir.etiket}
                  </th>
                  {urunler.map((urun, i) => {
                    const enIyi = enIyiler[satir.alan] === urun.id && farkVar;

                    return (
                      <td
                        key={urun.id}
                        className={`border-b border-line-soft px-2 py-1.5 text-xs ${
                          enIyi ? 'font-semibold text-ok' : 'text-ink-2'
                        }`}
                      >
                        <span className="inline-flex items-center gap-1">
                          {enIyi ? <Check className="h-3.5 w-3.5 shrink-0" aria-label="en iyi" /> : null}
                          {degerler[i]}
                        </span>
                      </td>
                    );
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </section>
  );
}
