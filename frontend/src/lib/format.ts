/**
 * Biçimlendirme yardımcıları.
 *
 * ⚠️ PARA: Bu dosyada hiçbir aritmetik YOKTUR (K14/K29). Gelen dize yalnızca
 * KARAKTER düzeyinde işlenir — binlik ayracı eklenir, ondalık noktası virgüle
 * çevrilir. `Number()`, `parseFloat`, `+`, `*` kullanılmaz; kullanılırsa kuruş
 * kaybı ve backend ile tutarsızlık doğar.
 */

/** "2553.76" → "2.553,76"  ·  "9" → "9,00"  (yalnızca dize işlemi) */
export function money(value: string | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '0,00';
  }

  const negative = value.startsWith('-');
  const raw = negative ? value.slice(1) : value;

  const dot = raw.indexOf('.');
  const whole = dot === -1 ? raw : raw.slice(0, dot);
  const fractionRaw = dot === -1 ? '' : raw.slice(dot + 1);

  // İki haneye tamamla/kırp — yuvarlama YAPILMAZ, backend zaten yuvarlanmış gönderir.
  const fraction = (fractionRaw + '00').slice(0, 2);

  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  return `${negative ? '-' : ''}${grouped},${fraction}`;
}

/** Kur: dört haneli gösterim — "7.0400" → "7,0400" */
export function rate(value: string | null | undefined): string {
  if (!value) return '0,0000';
  const [whole = '0', fractionRaw = ''] = value.split('.');
  const fraction = (fractionRaw + '0000').slice(0, 4);
  return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${fraction}`;
}

const currencySymbols = { TRY: '₺', CNY: '¥', USD: '$' } as const;

export function withCurrency(value: string | null | undefined, currency: keyof typeof currencySymbols): string {
  return `${currencySymbols[currency]}${money(value)}`;
}

/** ISO 8601 → "16 Ağu 2026 19:58" */
export function dateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) return '—';
  return new Intl.DateTimeFormat('tr-TR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed);
}

/** ISO 8601 → "16 Ağustos 2026" */
export function dateOnly(iso: string | null | undefined): string {
  if (!iso) return '—';
  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) return '—';
  return new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' }).format(parsed);
}

/** Adet gibi TAM SAYI alanlar — bunlar zaten number tipinde gelir, para değildir. */
export function count(value: number): string {
  return new Intl.NumberFormat('tr-TR').format(value);
}
