/**
 * Capture v2 tipleri (docs/04 §2c v2 — SABİT ŞEMA, K14/K32).
 * Alan adları backend sözleşmesiyle birebir; değişiklik PM kararı ister.
 */

export interface CaptureSource {
  platform: string;
  external_id: string | null;
  url: string;
  seller_id?: string | null;
  seller_name?: string | null;
  seller_url?: string | null;
  captured_at: string;
}

export interface PriceTier {
  min_qty: number;
  price_yuan: string;
}

export interface SkuEntry {
  props: Record<string, string>;
  price_yuan?: string;
  min_qty?: number;
}

export interface CaptureRaw {
  title: string | null;
  price_blocks: unknown;
  images: string[];
  video?: { id: string | null; poster: string | null } | null;
  attributes?: unknown;
  /** İE#11 EK-3: featureAttributes → {ad: değer} (ürün raw_attributes'ına yazılır). */
  normalized_attributes?: Record<string, string>;
  min_order?: unknown;
  unit?: unknown;
  category_name?: unknown;
  origin_text?: string | null;
}

export interface CaptureNormalized {
  name: string;
  price_yuan: string;
  price_tiers: PriceTier[];
  images: string[];
  sku_matrix: SkuEntry[] | null;
  video_url: string | null;
  /** ISO 3166-1 alpha-2; menşe özniteliği yoksa null (İE#11 EK-3). */
  country_of_origin?: string | null;
}

export interface CapturePayload {
  capture_id: string;
  schema_version: 2;
  extension_version: string;
  parser_version: string;
  target_list_id: number | null;
  qty: number;
  units_per_carton: number | null;
  note: string | null;
  source: CaptureSource;
  raw: CaptureRaw;
  normalized: CaptureNormalized;
}

/** Backend'den gelen seçici seti (K53: seçiciler KOD DEĞİL VERİ). */
export interface SelectorSet {
  schema_version: number;
  platform: string;
  url_pattern: string;
  context: { global_name: string; html_regex: string };
  paths: Record<string, string[]>;
  fallbacks: Record<string, string>;
  image_cleanup: { strip_suffixes: string[] };
  origin_attribute_keys?: string[];
}

/** Content script'in döndürdüğü ham sayfa verisi (MAIN world context + DOM yedekleri). */
export interface PageData {
  ok: boolean;
  context?: unknown;
  dom?: Record<string, string | null>;
  url?: string;
  error?: string;
}

export interface ParseResult {
  ok: boolean;
  missing: string[];
  source: CaptureSource;
  raw: CaptureRaw;
  normalized: CaptureNormalized;
}
