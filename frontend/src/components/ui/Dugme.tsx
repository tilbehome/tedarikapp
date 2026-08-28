import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { Loader2 } from 'lucide-react';

/**
 * DÜĞME (İE#16 D1.3) — dört tür + yükleniyor durumu.
 *
 * `yukleniyor` iken düğme KENDİLİĞİNDEN kilitlenir: çağıran tarafın ayrıca
 * `disabled` vermesi gerekmez. Çift tıklama koruması budur; uzun işlemlerde
 * `useUzunIslem` ile birlikte kullanılır (D1.8).
 *
 * Etiket yükleniyorken DEĞİŞMEZ, yanına dönen simge eklenir — metnin yer
 * değiştirmesi düğmenin boyunu oynatır ve fare kayar.
 */

export type DugmeTuru = 'birincil' | 'ikincil' | 'ghost' | 'tehlikeli';

const siniflar: Record<DugmeTuru, string> = {
  birincil: 'btn-primary',
  ikincil: 'btn-secondary',
  ghost: 'btn-ghost',
  tehlikeli: 'btn-danger',
};

interface DugmeProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  tur?: DugmeTuru;
  yukleniyor?: boolean;
  kucuk?: boolean;
  simge?: ReactNode;
}

export default function Dugme({
  tur = 'ikincil',
  yukleniyor = false,
  kucuk = false,
  simge,
  children,
  className = '',
  disabled,
  ...rest
}: DugmeProps) {
  return (
    <button
      type="button"
      className={`${siniflar[tur]} ${kucuk ? 'btn-sm' : ''} ${className}`}
      disabled={disabled === true || yukleniyor}
      aria-busy={yukleniyor || undefined}
      {...rest}
    >
      {yukleniyor ? <Loader2 className="size-4 animate-spin" aria-hidden /> : simge}
      {children}
    </button>
  );
}
