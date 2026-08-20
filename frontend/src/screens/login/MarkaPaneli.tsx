import { Package, ShieldCheck } from 'lucide-react';
import type { GirisVitrini } from '../../lib/girisVitrini';

/**
 * Giriş ekranının sol marka paneli (İE#13 EK-B — onaylı maket).
 *
 * Rakamlar SUNUCUDAN gelir: panel index.html'ine render anında gömülen meta
 * etiketinden okunur (girişsiz API ucu AÇILMAZ — PM şartı) ve yuvarlanmıştır.
 * Veri yoksa kartlar gizlenir; slogan ve kimlik her koşulda durur.
 *
 * Mobilde bu panel üstte ince bir banda katlanır (yalnız logo + kimlik görünür).
 */
export default function MarkaPaneli({ vitrin }: { vitrin: GirisVitrini }) {
  const rakamlarVar = vitrin.products !== '' && vitrin.volume !== '';

  return (
    <section className="relative isolate overflow-hidden bg-lacivert-900 px-6 py-6 text-white sm:px-10 lg:py-12">
      {/* Geometrik derinlik: iki daire, içerik akışını etkilemez. */}
      <span
        aria-hidden
        className="pointer-events-none absolute -right-24 -top-28 hidden h-64 w-64 rounded-full bg-lacivert-700 lg:block"
      />
      <span
        aria-hidden
        className="pointer-events-none absolute -bottom-16 -left-20 hidden h-44 w-44 rounded-full bg-lacivert-700/70 lg:block"
      />

      <div className="relative flex h-full flex-col">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-altin-500 text-lacivert-900 shadow-lg shadow-black/20">
            <Package className="h-6 w-6" aria-hidden />
          </span>
          <span>
            <span className="block text-lg font-bold leading-tight">tedarikapp</span>
            <span className="block text-[11px] font-semibold uppercase tracking-[0.18em] text-white/60">Tilbe Home</span>
          </span>
        </div>

        {/* Slogan ve tanıtım yalnız geniş ekranda: mobilde panel ince banda katlanır. */}
        <div className="hidden lg:mt-10 lg:block">
          <p className="font-serif text-3xl leading-snug">
            Çin'den rafa,
            <br />
            tek panelden.
          </p>
          <span className="mt-5 block h-px w-16 bg-altin-500" aria-hidden />
          <p className="mt-5 max-w-xs text-sm leading-relaxed text-white/70">
            Yakala, listele, fiyatla, paylaş — tedarik sürecinin tamamı tek çatıda.
          </p>
        </div>

        {rakamlarVar ? (
          <dl className="mt-8 hidden gap-3 lg:grid lg:grid-cols-2">
            <div className="rounded-xl bg-lacivert-700 px-4 py-3">
              <dt className="text-[11px] uppercase tracking-wide text-white/60">Yakalanan ürün</dt>
              <dd className="mt-0.5 text-2xl font-bold">{vitrin.products}</dd>
            </div>
            <div className="rounded-xl bg-lacivert-700 px-4 py-3">
              <dt className="text-[11px] uppercase tracking-wide text-white/60">Yönetilen sipariş</dt>
              <dd className="mt-0.5 text-2xl font-bold">{vitrin.volume}</dd>
            </div>
          </dl>
        ) : null}

        <p className="mt-auto hidden items-center gap-1.5 pt-8 text-[11px] text-white/50 lg:flex">
          <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
          Uçtan uca şifreli{vitrin.version ? ` · v${vitrin.version}` : ''}
        </p>
      </div>
    </section>
  );
}
