import { useId, useState, type ReactNode } from 'react';
import { ArrowRight, Check, Eye, EyeOff } from 'lucide-react';

/**
 * Giriş formunun kutulu alanı (İE#13 EK-B): üst etiket, odakta mavi halka,
 * geçerli e-postada yeşil tik, şifrede göster/gizle gözü.
 *
 * Etiket metni `<label>` içinde durur (görsel olarak büyük harf, DOM'da normal):
 * ekran okuyucu ve testler "E-posta" adını görmeye devam eder.
 */
export function GirisAlani({
  label,
  type,
  value,
  onChange,
  autoComplete,
  autoFocus,
  gecerli,
  sag,
  hata,
}: {
  label: string;
  type: 'email' | 'password' | 'text';
  value: string;
  onChange: (value: string) => void;
  autoComplete?: string;
  autoFocus?: boolean;
  gecerli?: boolean;
  sag?: ReactNode;
  hata?: string;
}) {
  const id = useId();

  return (
    <div>
      <div
        className={`group rounded-xl border bg-surface px-3.5 py-2 transition-colors focus-within:border-blue focus-within:ring-2 focus-within:ring-blue/25 ${
          hata ? 'border-err/40' : 'border-line'
        }`}
      >
        <label htmlFor={id} className="block text-[10px] font-bold uppercase tracking-[0.14em] text-ink-3">
          {label}
        </label>
        <div className="flex items-center gap-2">
          <input
            id={id}
            type={type}
            className="min-h-8 w-full bg-transparent text-[15px] text-ink outline-none placeholder:text-g300"
            value={value}
            onChange={(event) => onChange(event.target.value)}
            autoComplete={autoComplete}
            autoFocus={autoFocus}
            required
          />
          {gecerli ? <Check className="h-4 w-4 shrink-0 text-ok" aria-label="Geçerli" /> : null}
          {sag}
        </div>
      </div>
      {hata ? <p className="mt-1 text-xs font-medium text-err">{hata}</p> : null}
    </div>
  );
}

/** Şifre alanı: göster/gizle düğmesi alanın içinde durur. */
export function SifreAlani({
  value,
  onChange,
  hata,
}: {
  value: string;
  onChange: (value: string) => void;
  hata?: string;
}) {
  const [acik, setAcik] = useState(false);

  return (
    <GirisAlani
      label="Şifre"
      type={acik ? 'text' : 'password'}
      value={value}
      onChange={onChange}
      autoComplete="current-password"
      hata={hata}
      sag={
        <button
          type="button"
          className="shrink-0 text-ink-3 transition-colors hover:text-ink-2"
          onClick={() => setAcik((value) => !value)}
          aria-label={acik ? 'Şifreyi gizle' : 'Şifreyi göster'}
        >
          {acik ? <EyeOff className="h-4 w-4" aria-hidden /> : <Eye className="h-4 w-4" aria-hidden />}
        </button>
      }
    />
  );
}

/** Lacivert-altın anahtar düğmesi ("Beni hatırla"). */
export function AnahtarDugmesi({
  checked,
  onChange,
  children,
}: {
  checked: boolean;
  onChange: (value: boolean) => void;
  children: ReactNode;
}) {
  return (
    <label className="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-ink-2">
      <input type="checkbox" className="sr-only" checked={checked} onChange={(event) => onChange(event.target.checked)} />
      <span
        aria-hidden
        className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${checked ? 'bg-navy' : 'bg-g300'}`}
      >
        <span
          className={`absolute top-0.5 h-4 w-4 rounded-full transition-all ${
            checked ? 'left-4.5 bg-gold' : 'left-0.5 bg-surface'
          }`}
        />
      </span>
      {children}
    </label>
  );
}

/** Ana eylem düğmesi: lacivert zemin, altın ok. */
export function PaneleGirDugmesi({ busy, label }: { busy: boolean; label: string }) {
  return (
    <button
      type="submit"
      disabled={busy}
      className="btn w-full bg-navy text-white hover:bg-navy-2 disabled:opacity-60"
    >
      {label}
      {!busy ? <ArrowRight className="h-4 w-4 text-gold" aria-hidden /> : null}
    </button>
  );
}
