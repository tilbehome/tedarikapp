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
  /**
   * D11a: görsel henüz arşive alınmadı; adres kaynak sitededir. Kaynak site
   * hotlink'e izin vermeyebilir (alicdn Referer ACL) — arayüz bunu işaretler,
   * sessiz boş kare bırakmaz.
   */
  uzak?: boolean;
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
  /**
   * D11b: EKRANDA gösterilecek ad. `name` saklanan kayıttır; çeviri turu onu
   * ezmez (K54). Bu alan en güncel kalıcı çeviriyi taşır — eski sürümlerle
   * uyum için isteğe bağlıdır, yoksa `name` kullanılır.
   */
  ad_gosterim?: string;
  /** 'elle' | 'ceviri' | 'yakalama' — arayüz rozeti bunu okur. */
  ad_kaynak?: 'elle' | 'ceviri' | 'yakalama';
  ad_saglayici?: string | null;
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

/**
 * ÜRÜN ÇEKMECESİ VERİSİ (İE#21 B3 · `GET /api/products/{id}/cekmece`).
 *
 * Alan yoksa NULL gelir (K67): ilan kaydı olmayan elle girilmiş ürün `ilan: null`
 * döner, sinyali olmayan alan null kalır. Arayüz bunları "—" basar, uydurmaz.
 */
export interface IlanGorunumu {
  platform: string | null;
  external_id: string | null;
  url: string | null;
  baslik_orijinal: string | null;
  satici_ad: string | null;
  satici_url: string | null;
  satici_yil: number | null;
  satici_puan: string | null;
  yanit_orani: string | null;
  satis_adedi: number | null;
  satis_toplam: number | null;
  moq: number | null;
  birim_fiyat: string | null;
  para_birimi: string | null;
  skor: number | null;
  bant: string;
  skor_bilesenleri: Record<string, number> | null;
}

export interface FiyatKademesi {
  min_adet: number;
  /** Para: string taşınır (K14). */
  birim_fiyat: string;
}

export interface UrunCekmecesiVerisi {
  urun: Product;
  ilan: IlanGorunumu | null;
  kademeler: FiyatKademesi[];
  yorum_ozeti: { adet: number | null; puan: string | null } | null;
  /** Yurt içi kıyas: bugün veri kaynağı YOK — daima null (V3-C kapsamı). */
  yurtici_kiyas: null;
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
  /** İE#21 EK-4: kilit ekranındaki anahtar talebi köprüsünün numarası (boş olabilir). */
  share_contact_phone: string | null;
  /**
   * rc8/K4 (F-08): paylaşım bağlantısının ve QR'ın tabanı — `settings.APP_URL`.
   * `app_url_kanonik` false ise uygulama link üretmeyi reddeder; ekran kırmızı
   * şerit basar.
   */
  app_url: string | null;
  app_url_kanonik: boolean;
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
  /**
   * ESKİ ALAN ADI KORUNUR (İE#22 A3): uç `rate_snapshots.effective_from`
   * değerini bu adla döndürmeye devam ediyor. Yeniden adlandırmak, ekran
   * sözleşmesini kırar ve hiçbir şey kazandırmazdı.
   */
  set_at: string;
  /** İE#22 A3: bu satır ŞU AN geçerli olan mı? */
  aktif: boolean;
  /** Değer nereden geldi: elle onay mı, TCMB önerisi mi (K4). */
  kaynak: 'elle' | 'tcmb';
  /** Ne zaman devre dışı kaldı; aktif satırda null. */
  superseded_at: string | null;
}

export interface SystemStatus {
  app_version: string;
  php_version: string;
  /**
   * K99: çalışma zamanı katalogları (bildirim, panorama). Sağlıklı olanlar da
   * listelenir — boş liste "denetim yapılmadı" ile "her şey yolunda" arasında
   * ayırt edilemezdi.
   */
  kataloglar: { kod: string; ad: string; yol: string; saglikli: boolean; hata: string | null }[];
  /**
   * K102: kayıt SONRASI yazılamayan bildirimler. Birincil eylem düşmedi ama
   * olay kayboldu — sıfırdan büyükse ekranda görünür.
   */
  bildirim_hatalari: { sayi: number; son: string | null };
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
