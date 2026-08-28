/**
 * LİSTE TABLOSU TERCİHLERİ (İE#21 B2 cilaları · referans: `liste-ici.png` araç çubuğu).
 *
 * Referans karede tablonun üstünde üç denetim var: **Sütunlar**, **Yoğunluk** ve
 * **Grupla**. Üçü de aynı ihtiyaçtan doğar: 300 satırlık bir listede herkesin
 * baktığı sütun aynı değildir. Fiyat çalışan kişi ₺/DDP sütunlarını ister, kargo
 * takip eden kişi onları gürültü sayar.
 *
 * TERCİH KULLANICININ CİHAZINDA KALIR (`localStorage`): sunucuya yazmak bir ayar
 * uç noktası, bir migration ve bir senkron sorunu getirirdi; bu tercihler kişisel
 * ve zararsızdır — yanlış okunursa en kötü hâlde varsayılan görünür.
 *
 * OKUMA/YAZMA HER ZAMAN KORUMALIDIR: gizli sekmede, site verisi kapalı tarayıcıda
 * ya da eski/bozuk bir kayıtta `localStorage` ya boş döner ya da doğrudan hata
 * atar. O yüzden her erişim try/catch içindedir ve bozuk kayıt varsayılana düşer —
 * ekran, kaydedilmiş bir tercih yüzünden ASLA açılmamazlık etmez.
 */

export const SUTUNLAR = {
  gorsel: 'Görsel',
  kategori: 'Kategori',
  adet: 'Miktar',
  birim_yuan: '¥ Birim',
  satir_yuan: '¥ Satır',
  birim_tl: '₺ Birim',
  ddp_usd: '$ DDP',
  satir_tl: '₺ Satır',
  durum: 'Durum',
  hazir: 'Hazır',
} as const;

export type SutunAnahtari = keyof typeof SUTUNLAR;

export type Yogunluk = 'rahat' | 'sik';
export type Gruplama = 'yok' | 'kategori' | 'durum';

export interface TabloTercihi {
  /** Görünür sütunlar. "Ürün" sütunu listede yoktur: o her zaman görünür. */
  sutunlar: SutunAnahtari[];
  yogunluk: Yogunluk;
  grupla: Gruplama;
}

const ANAHTAR = 'tedarikapp.liste-tablosu';

export const VARSAYILAN: TabloTercihi = {
  sutunlar: Object.keys(SUTUNLAR) as SutunAnahtari[],
  yogunluk: 'rahat',
  grupla: 'yok',
};

function gecerliSutunlar(deger: unknown): SutunAnahtari[] | null {
  if (!Array.isArray(deger)) return null;
  const bilinen = deger.filter((ad): ad is SutunAnahtari => typeof ad === 'string' && ad in SUTUNLAR);

  // Boş küme kabul edilir (kullanıcı hepsini kapatabilir) ama bilinmeyen adlar
  // atılır: eski bir sürümde var olan sütun bugün yoksa tercih onu taşımasın.
  return bilinen;
}

export function tercihOku(): TabloTercihi {
  try {
    const ham = window.localStorage.getItem(ANAHTAR);
    if (ham === null) return VARSAYILAN;

    const cozulmus: unknown = JSON.parse(ham);
    if (typeof cozulmus !== 'object' || cozulmus === null) return VARSAYILAN;

    const kayit = cozulmus as Partial<TabloTercihi>;
    const sutunlar = gecerliSutunlar(kayit.sutunlar);

    return {
      sutunlar: sutunlar ?? VARSAYILAN.sutunlar,
      yogunluk: kayit.yogunluk === 'sik' ? 'sik' : 'rahat',
      grupla: kayit.grupla === 'kategori' || kayit.grupla === 'durum' ? kayit.grupla : 'yok',
    };
  } catch {
    // Depolama erişilemez ya da kayıt bozuk: varsayılanla devam.
    return VARSAYILAN;
  }
}

export function tercihYaz(tercih: TabloTercihi): void {
  try {
    window.localStorage.setItem(ANAHTAR, JSON.stringify(tercih));
  } catch {
    // Yazamamak bir hata değildir: tercih bu oturumda yaşar, ekran çalışmaya devam eder.
  }
}

/** Satır yoğunluğunun hücre sınıfı — tek yerden, iki tabloda aynı. */
export function hucreSinifi(yogunluk: Yogunluk): string {
  return yogunluk === 'sik' ? 'px-3 py-1' : 'px-3 py-2';
}
