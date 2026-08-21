/**
 * 1688 parser'ı (İE#11 C3 — SAF FONKSİYON: DOM/ağ yok, fixture'la test edilir).
 *
 * Katmanlı çıkarım (docs/arastirma/1688-parser-raporu):
 *   1. window.context (SSR gömülü JSON — verinin %90'ı burada)
 *   2. og: meta etiketleri (başlık/görsel yedeği)
 *   3. DOM seçicileri (son çare — popup önizlemesi yine dolu kalsın)
 * Hangi yolların okunacağı SEÇİCİ VERİSİNDEN gelir (K53): backend
 * /api/extension/selectors JSON'ı — site değişince eklenti güncellemesi gerekmez.
 *
 * Bilinen tuzaklar (raporla sabit): offerId number gelir → HER ZAMAN String();
 * fastjson $ref göstergeleri çözülür; kademeli fiyat currentPrices[]'ta birden çok
 * beginAmount; görseller lazy-load soneki taşır (_.webp vb.) → temizlenir.
 */

import { firstPath, resolveRefs } from '../../core/jsonpath';
import { normalizeTiers } from './format';
import type { CaptureNormalized, CaptureRaw, CaptureSource, ParseResult, PriceTier, SelectorSet, SkuEntry } from '../../core/types';

export const PARSER_VERSION = '1688-2026.08.2';

interface DomFallback {
  ogTitle?: string | null;
  ogImage?: string | null;
  domTitle?: string | null;
  domPrice?: string | null;
  /** İE#14 A4: sayfadaki kırıntı yolu adımları (面包屑) — kategori buradan türer. */
  breadcrumb?: string[] | null;
  /** İE#15 E2: sayfadaki <video> öğesinin oynatılabilir adresi (varsa). */
  videoSrc?: string | null;
}

/**
 * Oynatılabilir video adresi (İE#15 E2).
 *
 * YALNIZ https ve tanıdık bir video uzantısı/servisi kabul edilir: sayfadaki
 * rastgele bir blob:/data: adresi paylaşım sayfasına taşınırsa orada çalışmaz;
 * çalışmayacak bir adresi taşımak, "video yok" demekten daha kötüdür (boş modal).
 */
export function playableVideoUrl(aday: unknown): string | null {
  if (typeof aday !== 'string') return null;
  const temiz = aday.trim();
  if (!/^https:\/\//i.test(temiz)) return null;
  if (!/\.(mp4|m3u8|webm)(\?|$)/i.test(temiz)) return null;

  return temiz;
}

/**
 * Kırıntı yolunu tek biçime indirir (İE#14 A4).
 *
 * 1688 bu bilgiyi üç ayrı biçimde verebilir: düz metin dizisi, `{name}`/`{categoryName}`
 * nesneleri ya da " > " ile ayrılmış tek metin. Üçü de aynı listeye çevrilir; ayıklama
 * (kök adım atma) BACKEND'DE yapılır ki kural tek yerde dursun.
 */
export function extractBreadcrumb(value: unknown, domYolu?: string[] | null): string[] {
  const out: string[] = [];
  const ekle = (metin: unknown): void => {
    if (typeof metin !== 'string') return;
    const temiz = metin.trim();
    if (temiz !== '' && !out.includes(temiz)) out.push(temiz);
  };

  if (typeof value === 'string') {
    for (const parca of value.split(/[>›»\/|]/)) ekle(parca);
  } else if (Array.isArray(value)) {
    for (const entry of value) {
      if (typeof entry === 'string') {
        ekle(entry);
      } else if (entry !== null && typeof entry === 'object') {
        const record = entry as Record<string, unknown>;
        ekle(record.name ?? record.categoryName ?? record.text ?? record.title);
      }
    }
  }

  if (out.length === 0 && Array.isArray(domYolu)) {
    for (const entry of domYolu) ekle(entry);
  }

  return out.slice(0, 8);
}

export function cleanImageUrl(url: string, stripSuffixes: string[]): string {
  let cleaned = url.trim();
  for (const suffix of stripSuffixes) {
    if (cleaned.endsWith(suffix)) {
      cleaned = cleaned.slice(0, -suffix.length);
    }
  }
  return cleaned;
}

/** currentPrices/skuRangePrices bloklarından kademe listesi üretir (para STRING — K14). */
export function extractTiers(priceBlock: unknown): PriceTier[] {
  if (!Array.isArray(priceBlock)) return [];
  const tiers: PriceTier[] = [];
  for (const entry of priceBlock) {
    if (entry === null || typeof entry !== 'object') continue;
    const record = entry as Record<string, unknown>;
    // globalData.model.tradeModel: currentPrices[] {price, beginAmount} · skuMap[] {price, discountPrice}
    const price = record.discountPrice ?? record.price ?? record.priceText ?? record.value;
    const begin = record.beginAmount ?? record.beginNum ?? record.startAmount;
    if (price === undefined) continue;
    const priceText = String(price).replace(/[^\d.]/g, '');
    if (priceText === '' || !/^\d{1,7}(\.\d{1,4})?$/.test(priceText)) continue;
    tiers.push({
      min_qty: typeof begin === 'number' ? begin : Number.parseInt(String(begin ?? '1'), 10) || 1,
      price_yuan: priceText,
    });
  }
  return tiers.sort((a, b) => a.min_qty - b.min_qty);
}

/** skuProps + sku fiyat bloklarından varyasyon matrisi (serbest ama tipli yapı). */
export function extractSkuMatrix(skuProps: unknown, skuRange: unknown): SkuEntry[] | null {
  const entries: SkuEntry[] = [];
  if (Array.isArray(skuRange)) {
    for (const entry of skuRange) {
      if (entry === null || typeof entry !== 'object') continue;
      const record = entry as Record<string, unknown>;
      const props: Record<string, string> = {};
      if (record.attributes !== null && typeof record.attributes === 'object' && !Array.isArray(record.attributes)) {
        for (const [key, value] of Object.entries(record.attributes as Record<string, unknown>)) {
          if (typeof value === 'string' || typeof value === 'number') props[key] = String(value);
        }
      } else if (typeof record.specAttrs === 'string') {
        // globalData.model.tradeModel.skuMap[] — gerçek yapı (rapor §4.2): specAttrs tek metin.
        props['seçenek'] = record.specAttrs;
      } else if (typeof record.skuName === 'string') {
        props['seçenek'] = record.skuName;
      }
      const tiers = extractTiers(record.rangePrices ?? record.prices ?? [record]);
      entries.push({
        props,
        price_yuan: tiers[0]?.price_yuan,
        min_qty: tiers[0]?.min_qty,
      });
    }
  }
  if (entries.length > 0) return entries;

  // Fiyatsız salt-özellik matrisi: skuProps'tan üret (önizleme "N varyasyon" der).
  if (Array.isArray(skuProps)) {
    for (const prop of skuProps) {
      if (prop === null || typeof prop !== 'object') continue;
      const record = prop as Record<string, unknown>;
      const propName = typeof record.prop === 'string' ? record.prop : 'seçenek';
      const values = Array.isArray(record.value) ? record.value : [];
      for (const value of values) {
        const name = value !== null && typeof value === 'object' ? (value as Record<string, unknown>).name : value;
        if (typeof name === 'string') entries.push({ props: { [propName]: name } });
      }
    }
  }
  return entries.length > 0 ? entries : null;
}

/**
 * featureAttributes[] → {ad: değer} sözlüğü (rapor B.6: {fid, name, value, values[]};
 * çoklu değerde `values` dizisi tercih edilir).
 *
 * @returns [sözlük, menşe metni|null]
 */
export function extractAttributes(raw: unknown, originKeys: string[]): [Record<string, string>, string | null] {
  const out: Record<string, string> = {};
  let origin: string | null = null;
  if (!Array.isArray(raw)) {
    // Eski/ikincil biçim: düz {ad: değer} sözlüğü.
    if (raw !== null && typeof raw === 'object') {
      for (const [key, value] of Object.entries(raw as Record<string, unknown>)) {
        if (typeof value === 'string' || typeof value === 'number') out[key] = String(value);
      }
    }
  } else {
    for (const item of raw) {
      if (item === null || typeof item !== 'object') continue;
      const record = item as Record<string, unknown>;
      const name = typeof record.name === 'string' ? record.name : null;
      if (name === null) continue;
      const values = Array.isArray(record.values)
        ? record.values.filter((v): v is string => typeof v === 'string')
        : [];
      const value = values.length > 0 ? values.join(', ') : typeof record.value === 'string' ? record.value : '';
      if (value !== '') out[name] = value;
    }
  }
  for (const key of originKeys) {
    if (out[key] !== undefined && out[key] !== '') {
      origin = out[key];
      break;
    }
  }

  return [out, origin];
}

/**
 * Ana giriş: gömülü context + seçici seti (+ DOM yedekleri) → ParseResult.
 * `ok:false` yalnız ZORUNLU alan (ad/fiyat/url) çıkarılamadıysa döner — eksikler
 * `missing`te isim isim listelenir; popup yine de önizleme gösterir (veri kaybolmaz).
 */
export function parse1688(
  context: unknown,
  selectors: SelectorSet,
  pageUrl: string,
  dom: DomFallback = {},
): ParseResult {
  const ctx = resolveRefs(context ?? {});
  const paths = selectors.paths;
  const missing: string[] = [];

  const rawOfferId = firstPath(ctx, paths.offer_id ?? []);
  const urlMatch = new RegExp(selectors.fallbacks.offer_id_from_url ?? 'offer/(\\d+)').exec(pageUrl);
  // offerId number gelebilir ve uzun olabilir — HER ZAMAN String() (rapor A.1 uyarısı).
  const externalId = rawOfferId !== undefined ? String(rawOfferId) : urlMatch?.[1] ?? null;

  const title =
    (firstPath(ctx, paths.title ?? []) as string | undefined) ??
    dom.ogTitle ??
    dom.domTitle ??
    null;

  const currentPrices = firstPath(ctx, paths.current_prices ?? []);
  const skuRangePrices = firstPath(ctx, paths.sku_range_prices ?? []);
  // İE#13 A3: normalizeTiers aynı min_qty'li kayıtları eritir — SKU fiyatları
  // "1+ → ¥35 · 1+ → ¥1040" sahte kademesine dönüşmez, birim fiyat en düşük olur.
  let tiers = normalizeTiers(extractTiers(currentPrices));
  if (tiers.length === 0) tiers = normalizeTiers(extractTiers(skuRangePrices));
  if (tiers.length === 0 && dom.domPrice) {
    const domPrice = dom.domPrice.replace(/[^\d.]/g, '');
    if (/^\d{1,7}(\.\d{1,4})?$/.test(domPrice)) tiers = [{ min_qty: 1, price_yuan: domPrice }];
  }

  const strip = selectors.image_cleanup?.strip_suffixes ?? [];
  const galleryRaw = firstPath(ctx, paths.gallery ?? []);
  const images: string[] = [];
  if (Array.isArray(galleryRaw)) {
    for (const item of galleryRaw) {
      const url =
        typeof item === 'string'
          ? item
          : item !== null && typeof item === 'object'
            ? ((item as Record<string, unknown>).fullPathImageURI ?? (item as Record<string, unknown>).imageURI)
            : undefined;
      if (typeof url === 'string' && url.startsWith('http')) {
        images.push(cleanImageUrl(url.replace(/^http:/, 'https:'), strip));
      }
    }
  }
  if (images.length === 0 && dom.ogImage) {
    images.push(cleanImageUrl(dom.ogImage.replace(/^http:/, 'https:'), strip));
  }

  const videoIdRaw = firstPath(ctx, paths.video_id ?? []);
  // Rapor §A.6: wirelessVideo.videoId === 0 → video YOK.
  const videoId = videoIdRaw === undefined || videoIdRaw === null || String(videoIdRaw) === '0' ? undefined : videoIdRaw;
  const videoPoster = firstPath(ctx, paths.video_poster ?? []);

  const [attributes, originText] = extractAttributes(
    firstPath(ctx, paths.attributes ?? []),
    selectors.origin_attribute_keys ?? [],
  );

  const source: CaptureSource = {
    platform: '1688',
    external_id: externalId,
    url: pageUrl,
    seller_id: (firstPath(ctx, paths.seller_login_id ?? []) as string | undefined) ?? null,
    seller_name: (firstPath(ctx, paths.seller_name ?? []) as string | undefined) ?? null,
    seller_url: (firstPath(ctx, paths.seller_url ?? []) as string | undefined) ?? null,
    captured_at: new Date().toISOString(),
  };

  const raw: CaptureRaw = {
    title: (firstPath(ctx, paths.title ?? []) as string | undefined) ?? null,
    price_blocks: { currentPrices: currentPrices ?? null, skuRangePrices: skuRangePrices ?? null },
    images,
    video: videoId !== undefined || videoPoster !== undefined
      ? { id: videoId !== undefined ? String(videoId) : null, poster: typeof videoPoster === 'string' ? videoPoster : null }
      : null,
    attributes: firstPath(ctx, paths.attributes ?? []) ?? null,
    // İE#11 EK-3 (2): normalize sözlük + 1688 gerçekleri — ürün raw_attributes'ına yazılır.
    normalized_attributes: attributes,
    min_order: firstPath(ctx, paths.min_order ?? []) ?? null,
    unit: firstPath(ctx, paths.unit ?? []) ?? null,
    category_name: firstPath(ctx, paths.category_name ?? []) ?? null,
    // İE#14 A4: kategori artık "Kategorisiz" basılmasın diye kırıntı yolu da taşınır.
    breadcrumb: extractBreadcrumb(firstPath(ctx, paths.breadcrumb ?? []), dom.breadcrumb),
    origin_text: originText,
  };

  const normalized: CaptureNormalized = {
    name: title ?? '',
    price_yuan: tiers[0]?.price_yuan ?? '',
    price_tiers: tiers,
    images,
    sku_matrix: extractSkuMatrix(firstPath(ctx, paths.sku_props ?? []), skuRangePrices),
    // İE#15 E2: DOM'da oynatılabilir bir adres varsa taşınır; yoksa null KALIR ve
    // "video var" bilgisi raw.video (id/poster) üzerinden okunur — sahte adres üretilmez.
    video_url: playableVideoUrl(dom.videoSrc),
    // İE#11 EK-3 (2): menşe — 1688 Çin tedarik platformudur; menşe özniteliği VARSA
    // ülke CN'dir (il/şehir metni raw'da durur, uydurma yapılmaz).
    country_of_origin: originText === null ? null : 'CN',
  };

  if (normalized.name === '') missing.push('normalized.name');
  if (normalized.price_yuan === '') missing.push('normalized.price_yuan');
  if (source.url === '') missing.push('source.url');

  return { ok: missing.length === 0, missing, source, raw, normalized };
}
