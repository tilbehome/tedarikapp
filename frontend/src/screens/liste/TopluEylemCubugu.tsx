import { Trash2, X } from 'lucide-react';
import type { Product, ProductStatus } from '../../api/types';
import { count } from '../../lib/format';
import { productStatusLabels } from '../../locales/tr';

/**
 * TOPLU EYLEM ÇUBUĞU (İE#21 B2 · E2E-PNL-24/31).
 *
 * Seçim yapıldığı anda görünür ve iki soruyu yanıtlar: "kaç ürün seçtim, toplamı
 * ne?" ve "ne yapabilirim?". Miktar toplamı seçimle birlikte ANINDA değişir —
 * kullanıcı 40 satırlık bir listede seçtiği kalemin ağırlığını böyle görür.
 *
 * PARA TOPLAMI BURADA YOK (K14/K29): panel kuruş toplamaz. Seçime göre para
 * toplamı göstermek istemek doğaldır ama o hesabı istemcide yapmak, kuruş
 * yuvarlamasını iki ayrı yerde tanımlamak demektir. Miktar TAM SAYIDIR, para
 * değildir; onu saymak güvenlidir.
 *
 * ÇİFT TIK MÜKERRER İŞLEM ÜRETMEZ (E2E-PNL-24): istek uçarken bütün düğmeler
 * kapanır. Kapanmasaydı ikinci tık ikinci bir toplu geçiş başlatır, ilk isteğin
 * yanıtı geldiğinde sayaç ve seçim tutarsız kalırdı.
 */

const HEDEFLER: ProductStatus[] = ['ordered', 'in_transit', 'received', 'cancelled'];

interface Props {
  secili: number[];
  urunler: Product[];
  mesgul: boolean;
  onDurum: (hedef: ProductStatus) => void;
  onSil: () => void;
  onTemizle: () => void;
}

export default function TopluEylemCubugu({ secili, urunler, mesgul, onDurum, onSil, onTemizle }: Props) {
  if (secili.length === 0) return null;

  const seciliUrunler = urunler.filter((urun) => secili.includes(urun.id));
  const miktar = seciliUrunler.reduce((toplam, urun) => toplam + urun.qty, 0);

  return (
    <div
      className="card mb-3 flex flex-wrap items-center gap-2 border-blue/30 bg-blue-soft p-3 text-sm"
      role="region"
      aria-label="Toplu işlemler"
      data-testid="toplu-eylem-cubugu"
    >
      <span className="font-semibold text-navy" data-testid="toplu-ozet">
        {count(secili.length)} ürün seçildi · {count(miktar)} adet
      </span>

      {HEDEFLER.map((hedef) => (
        <button
          key={hedef}
          type="button"
          className="btn-ghost !min-h-9 !text-xs"
          disabled={mesgul}
          data-testid={`toplu-${hedef}`}
          onClick={() => onDurum(hedef)}
        >
          {productStatusLabels[hedef]} yap
        </button>
      ))}

      <button
        type="button"
        className="btn-ghost !min-h-9 !text-xs !text-err"
        disabled={mesgul}
        data-testid="toplu-sil"
        onClick={onSil}
      >
        <Trash2 className="h-3.5 w-3.5" aria-hidden />
        Çöp kutusuna
      </button>

      <button
        type="button"
        className="btn-ghost !min-h-9 !text-xs"
        disabled={mesgul}
        data-testid="toplu-temizle"
        onClick={onTemizle}
      >
        <X className="h-3.5 w-3.5" aria-hidden />
        Seçimi temizle
      </button>
    </div>
  );
}
