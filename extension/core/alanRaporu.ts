/**
 * ALAN RAPORU — "neyi yakaladık, neyi yakalayamadık" (İE#21 A3).
 *
 * Mockup'ın (docs/sablon/eklenti-v2-sayfa-ici-mockup.html) doluluk bölümünün veri
 * karşılığı: 16+ alan, her birinin DEĞERİ, hangi KANALDAN geldiği ve dolu olup
 * olmadığı. Kullanıcı göndermeden önce ne gönderdiğini görür.
 *
 * NEDEN KANAL ETİKETİ: aynı alan üç ayrı yerden gelebilir — sayfanın gömülü JSON'u,
 * DOM'un kendisi ve (v3'te) MTop yanıtı. Bir alan "eksik" göründüğünde ilk soru
 * "hangi kanaldan bekliyorduk?" olur; etiket o soruyu peşinen yanıtlar.
 *
 * EKSİK ALAN YALAN SÖYLEMEZ: veri yoksa satır "sayfada yok" der ve panelde elle
 * girileceğini söyler. Sıfır/boş değer DOLU sayılmaz (K67 disiplini).
 */

import type { ParseResult } from './types';

export type Kanal = 'GOMULU' | 'SAYFA' | 'YANIT';

export interface AlanSatiri {
  ad: string;
  /** Kullanıcıya gösterilecek özet değer; eksikse açıklama. */
  deger: string;
  dolu: boolean;
  kanal: Kanal | null;
}

export interface AlanRaporu {
  satirlar: AlanSatiri[];
  dolu: number;
  toplam: number;
  /** Eksik alan adları — durum makinesine `OKUMA_KISMI` ile taşınır. */
  eksikler: string[];
}

function metin(deger: unknown): string | null {
  if (typeof deger === 'string' && deger.trim() !== '') return deger.trim();
  if (typeof deger === 'number' && Number.isFinite(deger)) return String(deger);

  return null;
}

function satir(ad: string, deger: string | null, kanal: Kanal, bosMetin = 'sayfada yok'): AlanSatiri {
  return deger === null
    ? { ad, deger: bosMetin, dolu: false, kanal: null }
    : { ad, deger, dolu: true, kanal };
}

/**
 * Ayrıştırma sonucunu kullanıcıya gösterilecek alan listesine çevirir.
 *
 * Sıra ÖNEMLİDİR: önce sipariş kararını etkileyenler (ad, fiyat, kademe, varyant,
 * MOQ), sonra lojistik ve kaynak bilgileri. Kullanıcı listeyi yukarıdan aşağı
 * okuyup "bu ürünü alır mıyım?" sorusunu yanıtlayabilmeli.
 */
export function alanRaporu(sonuc: ParseResult): AlanRaporu {
  const { raw, normalized, source } = sonuc;
  const ham = raw as unknown as Record<string, unknown>;

  const kademe = normalized.price_tiers.length;
  const sku = normalized.sku_matrix?.length ?? 0;
  const gorsel = normalized.images.length;
  const ozellikler = (raw.normalized_attributes ?? {}) as Record<string, string>;
  const ozellikSayisi = Object.keys(ozellikler).length;

  const satirlar: AlanSatiri[] = [
    satir('Ürün adı', metin(normalized.name), 'GOMULU'),
    satir('Orijinal başlık', metin(raw.title), 'GOMULU'),
    satir('Birim fiyat', metin(normalized.price_yuan) === null ? null : `¥${normalized.price_yuan}`, 'GOMULU'),
    satir('Fiyat kademeleri', kademe > 0 ? `${kademe} kademe` : null, 'GOMULU', 'kademeli fiyat bildirilmemiş'),
    satir('Varyantlar', sku > 0 ? `${sku} SKU` : null, 'GOMULU', 'varyant bulunamadı'),
    satir('Görseller', gorsel > 0 ? `${gorsel} görsel` : null, 'GOMULU'),
    satir('Video', metin(normalized.video_url) ?? (raw.video?.id != null ? 'ilanda var' : null), 'GOMULU', 'video yok'),
    satir('Ürün özellikleri', ozellikSayisi > 0 ? `${ozellikSayisi} özellik` : null, 'GOMULU'),
    satir('MOQ', metin(ham.min_order), 'GOMULU'),
    satir('Birim', metin(ham.unit), 'GOMULU'),
    satir('Kategori yolu', (raw.breadcrumb ?? []).length > 0 ? (raw.breadcrumb ?? []).join(' › ') : null, 'SAYFA'),
    satir('Menşe', metin(normalized.country_of_origin) ?? metin(ham.origin_text), 'GOMULU'),
    satir('Satıcı', metin(source.seller_name), 'GOMULU'),
    satir('Satıcı adresi', metin(source.seller_url), 'GOMULU'),
    satir('İlan no', metin(source.external_id), 'GOMULU'),
    satir('İlan adresi', metin(source.url), 'SAYFA'),
    // Sayfada HİÇ olmayan, panelde elle girilen alanlar — mockup'ın "eksik" satırları.
    satir('Koli ölçüsü', null, 'GOMULU', 'sayfada yok — panelde elle girilir'),
    satir('Üretim süresi', null, 'GOMULU', 'sayfada yok'),
  ];

  const dolu = satirlar.filter((s) => s.dolu).length;

  return {
    satirlar,
    dolu,
    toplam: satirlar.length,
    eksikler: satirlar.filter((s) => !s.dolu).map((s) => s.ad),
  };
}

/** Doluluk yüzdesi (tam sayı) — halka göstergesi bunu kullanır. */
export function dolulukYuzdesi(rapor: AlanRaporu): number {
  if (rapor.toplam === 0) return 0;

  return Math.round((rapor.dolu / rapor.toplam) * 100);
}

/**
 * Gönderimi ENGELLEYEN eksikler — bunlar yoksa backend zaten reddeder.
 * Diğer eksikler yalnız uyarıdır: kullanıcı bilerek gönderebilir (D4 → D3).
 */
export const ZORUNLU_ALANLAR = ['Ürün adı', 'Birim fiyat', 'İlan adresi'];

export function gonderimiEngelleyenler(rapor: AlanRaporu): string[] {
  return rapor.eksikler.filter((ad) => ZORUNLU_ALANLAR.includes(ad));
}
