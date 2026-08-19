/**
 * Giriş ekranı vitrin verisi (İE#13 EK-B).
 *
 * Sunucu, panel index.html'ine `<meta name="tedarikapp-giris">` etiketi gömer —
 * GİRİŞSİZ BİR API UCU AÇILMAZ (PM şartı). Burada o etiket okunur; yoksa ya da
 * bozuksa boş değerler döner ve arayüz ilgili blokları gizler.
 */
export interface GirisVitrini {
  /** Yuvarlanmış ürün sayısı, ör. "248". */
  products: string;
  /** Yuvarlanmış hacim, ör. "₺2,1M". */
  volume: string;
  /** Sistemde iki adımlı doğrulama kurulu mu (kart yalnız açıksa görünür). */
  twoFactor: boolean;
  /** Sürüm damgası, ör. "0.11.0". */
  version: string;
}

const BOS: GirisVitrini = { products: '', volume: '', twoFactor: false, version: '' };

export function girisVitriniOku(): GirisVitrini {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="tedarikapp-giris"]');
  if (meta === null || meta.content === '') return BOS;

  try {
    const ham = JSON.parse(meta.content) as Partial<{
      products: string;
      volume: string;
      two_factor: boolean;
      version: string;
    }>;

    return {
      products: typeof ham.products === 'string' && ham.products !== '—' ? ham.products : '',
      volume: typeof ham.volume === 'string' && !ham.volume.includes('—') ? ham.volume : '',
      twoFactor: ham.two_factor === true,
      version: typeof ham.version === 'string' ? ham.version : '',
    };
  } catch {
    return BOS;
  }
}
