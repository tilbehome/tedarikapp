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

/** V3-C Aşama 2.1 — teklif turu durumları (#15 §2; tur numarası ayrı taşınır). */
export type TeklifTuruDurumu =
  | 'DRAFT'
  | 'SENT'
  | 'VIEWED'
  | 'PRICING'
  | 'RESPONDED'
  | 'REVISION_REQUESTED'
  | 'APPROVED'
  | 'ABANDONED'
  | 'EXPIRED'
  | 'REVOKED';

/** Firma (suppliers) — çekirdek; Firmalar & Kişiler modülü ilerideki fazda. */
export interface Firma {
  id: number;
  ad: string;
  varsayilan_dil: 'tr' | 'en' | 'zh';
  tip?: string;
  ulke?: string | null;
  platform?: string | null;
  varsayilan_gecerlilik_gun?: number | null;
  whatsapp?: string | null;
  eposta?: string | null;
  notlar?: string | null;
  created_at?: string;
}

/**
 * Teklif turu — birim `liste × firma × tur` (K103). `etiket` sunucuda
 * birleştirilir ("R2 gönderildi"); `kur` KOPYADIR, referans değil (K104).
 */
export interface TeklifTuru {
  id: number;
  list_id: number;
  liste_adi: string;
  supplier_id: number;
  firma_adi: string;
  tur_no: number;
  parent_round_id: number | null;
  state: TeklifTuruDurumu;
  etiket: string;
  cikti_terimi: string;
  nihai: boolean;
  state_reason: string | null;
  rfq_snapshot_id: number | null;
  rate_snapshot_id: number | null;
  rate_policy: 'inherit' | 'refresh';
  kur: { para_birimi: string | null; deger: string | null; kaynak: string | null; kilit_at: string | null };
  share_id: number | null;
  gecerlilik_gun: number | null;
  valid_until: string | null;
  portal_dili: string;
  /** Firma link'i en az bir kez açtı mı — sahip bunu YAZAMAZ, gözlemdir. */
  goruntulendi: boolean;
  /** Gönderimden bu yana geçen gün; nihai/gönderilmemiş turda null. */
  bekleme_gun: number | null;
  drafted_at: string | null;
  sent_at: string | null;
  first_viewed_at: string | null;
  responded_at: string | null;
  approved_at: string | null;
  revision_requested_at: string | null;
  partial_submission_count: number;
  created_at: string;
  updated_at: string;
}

/** Gönderim yanıtı: tam token + 6 haneli anahtar YALNIZ burada döner (K51). */
export interface TurGonderimSonucu extends TeklifTuru {
  share_url: string;
  share_token: string;
  erisim_anahtari: string;
  satir_sayisi: number;
}

/** V3-C Aşama 2.2 — firma yanıtı: kademeli fiyat (kaynak sınır korunur, ara miktar hesaplanmaz). */
export interface YanitKademe {
  min_adet: string;
  max_adet: string | null;
  birim_fiyat: string;
  para_birimi: string | null;
  kademe_tipi: 'esik' | 'aralik';
}

export type YanitDurumu = 'unanswered' | 'found' | 'not_found' | 'alternative_available';

/**
 * Kanonik yanıt satırı — `quote_lines` ile aynı adlar. Para/miktar DİZEDİR (K14).
 * `temizle`: yalnız uygulama gövdesinde; boş alan mevcut değeri SİLMEZ, silme açık listeyle olur.
 */
export interface YanitSatiri {
  rfq_satir_id: string;
  yanit_durumu: YanitDurumu;
  ddp_birim_fiyat: string | null;
  para_birimi: string | null;
  ddp_kdv_dahil_onayi: boolean | null;
  moq_deger: string | null;
  moq_birim: string | null;
  termin_baslangici: string | null;
  termin_baslangici_aciklamasi: string | null;
  termin_suresi: number | null;
  termin_birimi: string | null;
  koli_ici_adet: number | null;
  koli_uzunluk_cm: string | null;
  koli_genislik_cm: string | null;
  koli_yukseklik_cm: string | null;
  koli_cbm: string | null;
  koli_brut_kg: string | null;
  koli_net_kg: string | null;
  ambalaj: string | null;
  firma_notu: string | null;
  alternatif_baglanti: string | null;
  alternatif_aciklama: string | null;
  kademeler: YanitKademe[];
  temizle?: string[];
}

export interface YanitAlanHatasi {
  satir_id?: string;
  alan: string;
  deger: unknown;
  kural: string;
}

/** Yapıştır-ayrıştır önizlemesi (yazmaz). */
export interface YapistirOnizlemeSatiri {
  rfq_satir_id: string;
  urun_kodu: string | null;
  urun_adi: { tr?: string; en?: string; zh?: string } | null;
  talep_miktar: string;
  yeni: YanitSatiri;
  eski: YanitSatiri | null;
  hatalar: YanitAlanHatasi[];
  eksik_zorunlu: string[];
  secilebilir: boolean;
  varsayilan_secili: boolean;
}

export interface YapistirOnizleme {
  parmak_izi: string;
  satirlar: YapistirOnizlemeSatiri[];
  /** Bağlanamayan parçalar — asla bir satıra yazılmaz; aday listesi + YASAK işlem. */
  belirsiz: { parca: string; aday_satir_idleri: string[]; neden: string; yasak_otomatik_islem: string }[];
  dogrulama_hatalari: YanitAlanHatasi[];
  eslesmeyen_satirlar: string[];
}

export type ExcelOnizlemeGrubu = 'uygulanabilir' | 'uyarili' | 'hatali' | 'belirsiz' | 'degisiklik_yok';

export interface ExcelOnizlemeSatiri {
  rfq_satir_id: string;
  hucre: string;
  urun_kodu: string | null;
  urun_adi: { tr?: string; en?: string; zh?: string } | null;
  talep_miktar: string | null;
  grup: ExcelOnizlemeGrubu;
  secilebilir: boolean;
  varsayilan_secili: boolean;
  imza_bozuk: boolean;
  eski: YanitSatiri | null;
  yeni: YanitSatiri | null;
  degisen: string[];
  hatalar: string[];
  uyarilar: string[];
  belirsiz: string[];
}

export interface ExcelOnizleme {
  parmak_izi: string;
  manifest: { schema_version: number; exported_at: string; supplier_round_id: number; rfq_snapshot_id: number; row_count: number; tur_satir_sayisi: number };
  ozet: Record<ExcelOnizlemeGrubu, number>;
  satirlar: ExcelOnizlemeSatiri[];
}

export interface YanitUygulamaSonucu {
  tekrar: boolean;
  yazilan: number;
  satirlar: string[];
  state: TeklifTuruDurumu;
  yanit: Record<string, YanitSatiri>;
}