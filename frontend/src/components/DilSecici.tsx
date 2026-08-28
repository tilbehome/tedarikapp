import { Languages } from 'lucide-react';

/**
 * DİL SEÇİCİ (İE#20 C5) — ZH (orijinal) · TR · EN.
 *
 * Ürün Sahibi kararı (22 Ağu): TedarikApp TAM ÜÇ DİLLİDİR. Bu bileşen o kararın
 * arayüzdeki karşılığıdır ve üç dili de KOŞULSUZ gösterir — "EN yalnız anahtar
 * açıksa" koşulu KALDIRILDI.
 *
 * ZH bir çeviri DEĞİLDİR: kaynaktaki metnin kendisidir. Bu ayrım kullanıcıya
 * görünür kılınır (etiket "Orijinal"), çünkü "çeviri" ile "kaynak" arasındaki
 * fark, bir alanı düzeltirken neyi düzelttiğini bilmek demektir.
 *
 * Çevirisi OLMAYAN dil seçilebilir ama işaretlenir: kullanıcı boş bir alana
 * bakıp "sistem bozuk" dememeli, "bu dil henüz üretilmemiş" demeli.
 */

export type UrunDili = 'zh' | 'tr' | 'en';

export interface DilSeceneği {
  kod: UrunDili;
  etiket: string;
  /** Bu dilde metin var mı? Yoksa seçenek "—" işaretiyle görünür. */
  mevcut: boolean;
}

interface Props {
  secili: UrunDili;
  secenekler: DilSeceneği[];
  onSec: (dil: UrunDili) => void;
  className?: string;
}

export default function DilSecici({ secili, secenekler, onSec, className = '' }: Props) {
  return (
    <div
      className={`inline-flex items-center gap-1 rounded-lg border border-line bg-g50 p-0.5 ${className}`}
      role="group"
      aria-label="Görüntüleme dili"
    >
      <Languages className="ml-1.5 h-3.5 w-3.5 text-ink-3" aria-hidden />
      {secenekler.map((secenek) => {
        const aktif = secenek.kod === secili;

        return (
          <button
            key={secenek.kod}
            type="button"
            className={`rounded-md px-2 py-1 text-xs font-medium transition ${
              aktif ? 'bg-surface text-ink shadow-sm' : 'text-ink-2 hover:text-ink'
            }`}
            aria-pressed={aktif}
            title={secenek.mevcut ? secenek.etiket : `${secenek.etiket} — bu dilde metin henüz üretilmedi`}
            onClick={() => onSec(secenek.kod)}
          >
            {secenek.etiket}
            {secenek.mevcut ? null : <span className="ml-1 text-ink-3">—</span>}
          </button>
        );
      })}
    </div>
  );
}

/**
 * Ürünün hangi dillerde metni olduğunu çıkarır.
 *
 * ZH kaynaktan gelir (`name_original`), TR panelin kendi alanıdır (`name`),
 * EN çeviri katmanından gelir. "Mevcut" olmayan dil GİZLENMEZ, İŞARETLENİR:
 * gizlemek, kullanıcıya sistemin o dili hiç desteklemediğini düşündürür.
 */
export function dilSecenekleri(urun: {
  name?: string | null;
  name_original?: string | null;
  ceviriler?: Partial<Record<string, string>> | null;
}): DilSeceneği[] {
  const ceviriler = urun.ceviriler ?? {};

  return [
    { kod: 'zh', etiket: 'ZH · Orijinal', mevcut: Boolean(urun.name_original?.trim()) },
    { kod: 'tr', etiket: 'TR', mevcut: Boolean(urun.name?.trim()) },
    { kod: 'en', etiket: 'EN', mevcut: Boolean(ceviriler.en?.trim()) },
  ];
}

/** Seçili dildeki metni döndürür; yoksa null (arayüz "—" basar, uydurmaz). */
export function dildekiMetin(
  dil: UrunDili,
  urun: { name?: string | null; name_original?: string | null; ceviriler?: Partial<Record<string, string>> | null },
): string | null {
  const deger =
    dil === 'zh' ? urun.name_original : dil === 'tr' ? urun.name : (urun.ceviriler ?? {}).en;

  return deger?.trim() ? deger : null;
}
