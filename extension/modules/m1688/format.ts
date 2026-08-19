/**
 * Fiyat gösterim yardımcıları (İE#13 A3 — SAF FONKSİYON, fixture'la test edilir).
 *
 * CANLI VAKA: önizleme "1+ → ¥35 · 1+ → ¥1040" yazıyordu. Sebep: SKU fiyatlarının
 * `beginAmount`ı yoktur, hepsi min_qty=1 ile "kademe" sanılıyordu. Gerçekte iki ayrı
 * kavram var:
 *   • KADEME (adet kırılımı)  → farklı min_qty'ler: "1+ → ¥35 · 100+ → ¥30"
 *   • SKU ARALIĞI (varyasyon) → aynı min_qty, farklı fiyat: "¥35–¥1040"
 *
 * K14: JS'te para aritmetiği YOK. Karşılaştırma bile float'a düşmez — ondalık
 * metinler basamak bazında karşılaştırılır (compareDecimal).
 */

import type { CaptureNormalized, PriceTier, SkuEntry } from '../../core/types';

/** İki ondalık METNİ float'a çevirmeden karşılaştırır: <0, 0, >0 (K14). */
export function compareDecimal(a: string, b: string): number {
  const [aInt = '0', aFrac = ''] = a.trim().split('.');
  const [bInt = '0', bFrac = ''] = b.trim().split('.');
  const aWhole = aInt.replace(/^0+(?=\d)/, '');
  const bWhole = bInt.replace(/^0+(?=\d)/, '');
  if (aWhole.length !== bWhole.length) return aWhole.length - bWhole.length;
  if (aWhole !== bWhole) return aWhole < bWhole ? -1 : 1;

  const length = Math.max(aFrac.length, bFrac.length);
  const aPadded = aFrac.padEnd(length, '0');
  const bPadded = bFrac.padEnd(length, '0');
  if (aPadded === bPadded) return 0;

  return aPadded < bPadded ? -1 : 1;
}

/**
 * Kademeleri temizler: aynı min_qty birden çok kez geldiyse EN DÜŞÜK fiyat kalır
 * (SKU listesinden türeyen sahte kademeler burada erir), sonuç min_qty'ye göre artan.
 *
 * Bu aynı zamanda bir veri hatasını da kapatır: `price_yuan = tiers[0]` olduğu için
 * sıralama yalnız min_qty'ye bakarken birim fiyat ¥35 yerine ¥1040 çıkabiliyordu.
 */
export function normalizeTiers(tiers: PriceTier[]): PriceTier[] {
  const enDusuk = new Map<number, string>();
  for (const tier of tiers) {
    const mevcut = enDusuk.get(tier.min_qty);
    if (mevcut === undefined || compareDecimal(tier.price_yuan, mevcut) < 0) {
      enDusuk.set(tier.min_qty, tier.price_yuan);
    }
  }

  return [...enDusuk.entries()]
    .map(([min_qty, price_yuan]) => ({ min_qty, price_yuan }))
    .sort((a, b) => a.min_qty - b.min_qty);
}

/** SKU matrisindeki fiyatlardan [en düşük, en yüksek]; fiyat yoksa null. */
export function skuPriceRange(matrix: SkuEntry[] | null | undefined): [string, string] | null {
  const prices = (matrix ?? [])
    .map((entry) => entry.price_yuan)
    .filter((price): price is string => typeof price === 'string' && price !== '');
  if (prices.length === 0) return null;

  let min = prices[0] as string;
  let max = prices[0] as string;
  for (const price of prices) {
    if (compareDecimal(price, min) < 0) min = price;
    if (compareDecimal(price, max) > 0) max = price;
  }

  return [min, max];
}

/**
 * Önizleme fiyat metni (A3):
 *   • gerçek kademe varsa   → "1+ → ¥35 · 100+ → ¥30"
 *   • SKU'lar farklıysa     → "¥35–¥1040"
 *   • tek fiyat             → "¥35"
 *   • hiçbiri               → "" (çağıran "(fiyat çıkarılamadı)" gösterir)
 */
export function priceLabel(normalized: Pick<CaptureNormalized, 'price_tiers' | 'price_yuan' | 'sku_matrix'>): string {
  const tiers = normalizeTiers(normalized.price_tiers ?? []);
  if (tiers.length > 1) {
    return tiers.map((tier) => `${tier.min_qty}+ → ¥${tier.price_yuan}`).join(' · ');
  }

  const range = skuPriceRange(normalized.sku_matrix);
  if (range !== null && compareDecimal(range[0], range[1]) !== 0) {
    return `¥${range[0]}–¥${range[1]}`;
  }

  const tek = tiers[0]?.price_yuan ?? normalized.price_yuan ?? '';

  return tek === '' ? '' : `¥${tek}`;
}

/**
 * Metinde CJK (Çince/Japonca/Korece) karakter var mı? (İE#13 A4)
 * Çeviri önerisi yalnız gerekiyorsa istenir — zaten Türkçe bir başlık için dış
 * servise gidilmez (kota ve gereksiz gecikme).
 */
export function hasCjk(text: string): boolean {
  return /[㐀-䶿一-鿿豈-﫿぀-ヿ가-힯]/.test(text);
}

/** Varyasyon özeti: "Gri · Mavi (2 seçenek)" — matris yoksa boş metin. */
export function variationLabel(matrix: SkuEntry[] | null | undefined): string {
  if (!matrix || matrix.length === 0) return '';
  const adlar = matrix
    .map((entry) => Object.values(entry.props).join(' / '))
    .filter((ad) => ad !== '');
  if (adlar.length === 0) return `${matrix.length} varyasyon`;

  const ilk = adlar.slice(0, 3).join(' · ');

  return adlar.length > 3 ? `${ilk} … (${adlar.length} seçenek)` : `${ilk} (${adlar.length} seçenek)`;
}
