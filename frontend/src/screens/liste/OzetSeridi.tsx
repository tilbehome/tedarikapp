import type { Product, SupplyList } from '../../api/types';
import { count, money } from '../../lib/format';
import { eksikMi } from '../../lib/eksikler';

/**
 * ÖZET ŞERİDİ (İE#21 B2 · referans: `liste-ici.png` üst şerit).
 *
 * Listeyi açan kişinin ilk sorusu "nerede kaldım?"dır: kaç ürün var, kaçı
 * fiyatlanmış, toplam ne tutuyor. Bu şerit o üç soruyu tabloyu okumaya gerek
 * kalmadan yanıtlar.
 *
 * PARA TOPLAMLARI SUNUCUDAN GELİR (K14/K29): panel kuruş bile toplamaz —
 * `list.totals` ne diyorsa o basılır. Buradaki tek istemci hesabı ADET
 * SAYMAKTIR (kaç üründe fiyat var), ki o da tam sayıdır ve para değildir.
 */

interface Props {
  liste: SupplyList;
  urunler: Product[];
}

export default function OzetSeridi({ liste, urunler }: Props) {
  // "Fiyatlandı" ölçütü de sunucunun eksik dökümünden gelir (C8): panelde ikinci
  // bir "fiyat girilmiş sayılır mı?" kuralı yaşamaz.
  const fiyatli = urunler.filter((urun) => !eksikMi(urun, 'price_yuan')).length;

  return (
    <section
      className="card mb-3 grid grid-cols-2 divide-line-soft sm:grid-cols-3 lg:grid-cols-5 lg:divide-x"
      aria-label="Liste özeti"
      data-testid="ozet-seridi"
    >
      <Kutu etiket="Ürün" deger={count(liste.product_count)} />
      <Kutu etiket="Toplam miktar" deger={count(liste.totals.qty)} />
      <Kutu
        etiket="Fiyatlandı"
        deger={`${count(fiyatli)} / ${count(urunler.length)}`}
        vurgu={urunler.length > 0 && fiyatli < urunler.length ? 'uyari' : undefined}
        testId="ozet-fiyatlandi"
      />
      <Kutu etiket="Kaynak toplam" deger={`¥${money(liste.totals.yuan)}`} alt={`₺${money(liste.totals.yuan_tl)}`} />
      <Kutu etiket="DDP toplam" deger={`$${money(liste.totals.ddp_usd)}`} alt={`₺${money(liste.totals.ddp_tl)}`} />
    </section>
  );
}

function Kutu({
  etiket,
  deger,
  alt,
  vurgu,
  testId,
}: {
  etiket: string;
  deger: string;
  alt?: string;
  vurgu?: 'uyari';
  testId?: string;
}) {
  return (
    <div className="px-4 py-3 text-center" data-testid={testId}>
      <div className={`text-lg font-semibold ${vurgu === 'uyari' ? 'text-warn' : 'text-ink'}`}>{deger}</div>
      <div className="text-xs text-ink-3">{etiket}</div>
      {alt ? <div className="text-xs text-ink-3">{alt}</div> : null}
    </div>
  );
}
