/**
 * API tipleri — docs/10 sözleşmesiyle BİREBİR.
 *
 * ⚠️ PARA ALANLARI `string`'dir ve öyle kalır (K14/K29). `number`'a çevrilmez,
 * toplanmaz, çarpılmaz. Her hesap backend'in MoneyService'inden gelir; panel
 * yalnızca gelen dizeyi biçimlendirip gösterir.
 */

export type ProductStatus = 'to_order' | 'ordered' | 'in_transit' | 'received' | 'cancelled';
export type ListStatus = 'draft' | 'sent' | 'ordered' | 'completed' | 'cancelled';
export type Visibility = 'active' | 'passive' | 'archived';

export interface Envelope<T> {
  success: boolean;
  data: T;
  error: ApiErrorBody | null;
  meta: Record<string, unknown>;
}

export interface ApiErrorBody {
  code: string;
  message: string;
  fields?: Record<string, string>;
}

export interface User {
  id: number;
  email: string;
  created_at: string;
}

export interface ListTotals {
  qty: number;
  /** Para: string taşınır. */
  yuan: string;
  yuan_tl: string;
  ddp_usd: string;
  ddp_tl: string;
}

export interface SupplyList {
  id: number;
  name: string;
  period: string | null;
  supplier_name: string | null;
  note: string | null;
  status: ListStatus;
  visibility: Visibility;
  yuan_rate: string;
  usd_rate: string;
  rate_locked_at: string | null;
  revision: number;
  share_token_prefix: string | null;
  share_expires_at: string | null;
  product_count: number;
  progress: Record<ProductStatus, number>;
  totals: ListTotals;
  last_export: { format: string; created_at: string; list_revision: number } | null;
  is_export_stale: boolean;
  created_at: string;
  updated_at: string;
  archived_at: string | null;
  deleted_at: string | null;
}

export interface ProductImage {
  id: number;
  url: string;
  sort: number;
}

/** C8 HAZIR kapısının eksik dökümü — alan kimliktir, etiket görünen yüzdür. */
export interface UrunEksigi {
  alan: string;
  etiket: string;
}

export interface Product {
  id: number;
  list_id: number;
  sort_no: number;
  category_id: number | null;
  platform: string | null;
  external_id: string | null;
  name: string;
  name_original: string | null;
  detail: string | null;
  url: string | null;
  vendor_name: string | null;
  vendor_url: string | null;
  sku_selection: unknown;
  sku_matrix: unknown;
  main_image: string | null;
  video_url: string | null;
  qty: number;
  price_yuan: string;
  price_ddp_usd: string;
  /** İE#13 F5: hedef satış fiyatı (₺) — yalnız iç kopya çıktısında kâr hesabı için. */
  price_target_try: string | null;
  unit_profit_try: string | null;
  line_profit_try: string | null;
  price_yuan_tl: string;
  price_ddp_tl: string;
  line_total_yuan: string;
  line_total_yuan_tl: string;
  units_per_carton: number | null;
  tracking_no: string | null;
  status: ProductStatus;
  /** C8: ürün "HAZIR" işaretlendi mi? */
  hazir: boolean;
  /** C8: HAZIR olmasına engel eksikler (boşsa kapı açıktır). */
  hazir_eksikleri: UrunEksigi[];
  note: string | null;
  images: ProductImage[];
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface Category {
  id: number;
  name: string;
  sort: number;
  product_count: number;
}

export interface Settings {
  yuan_tl: string;
  usd_tl: string;
  totp_enabled: boolean;
  extension_token_preview: string | null;
  media_mode: 'download' | 'hotlink' | null;
  media_writable: boolean | null;
  /** İE#13 F1: çıktı ve paylaşım sayfası üst bandındaki firma kimliği. */
  document_header: {
    company: string | null;
    web: string | null;
    email: string | null;
    prepared_by: string | null;
  };
}

export interface RateHistoryEntry {
  id: number;
  currency: string;
  rate: string;
  set_at: string;
}

export interface SystemStatus {
  app_version: string;
  php_version: string;
  db_version: string | null;
  installed_at: string | null;
  migrations: { applied: number; pending: string[]; pending_count: number };
  media: { mode: string | null; writable: boolean | null };
  setup_lock_in_database: boolean;
}

/** POST /api/system/media-migrate — K47 arşive taşıma parti sonucu. */
export interface MediaMigrateResult {
  mode: string;
  scanned: number;
  migrated: number;
  failed: { kind: string; id: number; product_id: number; url: string; error: string }[];
  remaining: number;
}

/** GET /api/system/state-machine — docs/04 §2b kurallarının tek kaynağı backend'dir. */
export interface StateMachineMap {
  product: Record<ProductStatus, ProductStatus[]>;
  list: Record<ListStatus, ListStatus[]>;
}

export interface TrashListEntry {
  id: number;
  name: string;
  deleted_at: string;
  days_left: number;
}

export interface TrashProductEntry extends TrashListEntry {
  list_id: number;
  list_name: string;
  list_deleted: boolean;
}

export interface Trash {
  retention_days: number;
  lists: TrashListEntry[];
  products: TrashProductEntry[];
}

export interface ActivityEntry {
  id: number;
  entity_type: string;
  entity_id: number | null;
  action: string;
  detail: string | null;
  ip: string | null;
  actor_type: string;
  created_at: string;
}

export interface Paginated<T> {
  items: T[];
  page: number;
  per_page: number;
  total: number;
}
