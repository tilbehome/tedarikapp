import { api } from './client';
import type {
  ActivityEntry,
  Category,
  Product,
  ProductStatus,
  RateHistoryEntry,
  Settings,
  StateMachineMap,
  SupplyList,
  MediaMigrateResult,
  SystemStatus,
  Trash,
  User,
  Visibility,
} from './types';

/**
 * Uç tanımları — docs/10 ile birebir. Ekranlar ham yol dizesi kullanmaz;
 * sözleşme değişirse tek dosya değişir.
 */

export const auth = {
  login: (email: string, password: string, remember: boolean) =>
    // K45: 2FA tanımlıysa {stage:'totp'}, değilse doğrudan {user} döner (şifre yeterli).
    api.post<{ stage?: string; user?: User }>('/api/auth/login', { email, password, remember }, { silentUnauthorized: true }),
  totp: (code: string) => api.post<{ user: User }>('/api/auth/totp', { code }, { silentUnauthorized: true }),
  recovery: (code: string) =>
    api.post<{ user: User; remaining_codes: number }>('/api/auth/recovery', { code }, { silentUnauthorized: true }),
  me: () => api.get<{ user: User; csrf_token: string }>('/api/auth/me', { silentUnauthorized: true }),
  logout: () => api.post<void>('/api/auth/logout'),
  sessions: () => api.get<{ id: number; created_at: string; expires_at: string; is_current: boolean }[]>('/api/auth/sessions'),
  revokeSession: (id: number) => api.delete<void>(`/api/auth/sessions/${id}`),
};

export const lists = {
  all: (params: { visibility?: Visibility; status?: string; q?: string } = {}, signal?: AbortSignal) => {
    const query = new URLSearchParams();
    if (params.visibility) query.set('visibility', params.visibility);
    if (params.status) query.set('status', params.status);
    if (params.q) query.set('q', params.q);
    const suffix = query.toString();
    return api.get<SupplyList[]>(`/api/lists${suffix ? `?${suffix}` : ''}`, { signal });
  },
  find: (id: number) => api.get<SupplyList>(`/api/lists/${id}`),
  create: (body: { name: string; period?: string; supplier_name?: string; note?: string }) =>
    api.post<SupplyList>('/api/lists', body),
  update: (id: number, body: Record<string, unknown>) => api.patch<SupplyList>(`/api/lists/${id}`, body),
  remove: (id: number) => api.delete<void>(`/api/lists/${id}`),
  duplicate: (id: number, name?: string) =>
    api.post<SupplyList>(`/api/lists/${id}/duplicate`, name ? { name } : {}),
};

export const products = {
  forList: (
    listId: number,
    params: { status?: ProductStatus; q?: string; category_id?: number } = {},
    signal?: AbortSignal,
  ) => {
    const query = new URLSearchParams();
    if (params.status) query.set('status', params.status);
    if (params.q) query.set('q', params.q);
    if (params.category_id !== undefined) query.set('category_id', String(params.category_id));
    const suffix = query.toString();
    return api.get<Product[]>(`/api/lists/${listId}/products${suffix ? `?${suffix}` : ''}`, { signal });
  },
  /**
   * İE#19 E11: TEK ürün. Düzenleme ekranı eskiden listenin tamamını çekip içinden
   * arıyordu; 300 ürünlük bir listede tek alan düzeltmek 300 satır taşıyordu.
   */
  find: (id: number, signal?: AbortSignal) => api.get<Product>(`/api/products/${id}`, { signal }),
  create: (listId: number, body: Record<string, unknown>) =>
    api.post<Product>(`/api/lists/${listId}/products`, body),
  update: (id: number, body: Record<string, unknown>) => api.patch<Product>(`/api/products/${id}`, body),
  /** C8 HAZIR kapısı: eksik varsa sunucu 422 ile reddeder (İE#21 B2). */
  setHazir: (id: number, hazir: boolean) =>
    api.patch<{ hazir: boolean; eksikler: string[] }>(`/api/products/${id}/hazir`, { hazir }),
  setStatus: (id: number, status: ProductStatus) =>
    api.patch<Product>(`/api/products/${id}/status`, { status }),
  remove: (id: number) => api.delete<void>(`/api/products/${id}`),
  bulk: (body: { ids: number[]; action: 'status' | 'move' | 'delete'; status?: ProductStatus; target_list_id?: number }) =>
    api.patch<{ updated: number; failed: { id: number; error: string }[] }>('/api/products/bulk', body),
  reorder: (listId: number, orderedIds: number[]) =>
    api.patch<{ updated: number }>(`/api/lists/${listId}/products/reorder`, { ordered_ids: orderedIds }),
  /** İE#10 5d: kırık görsel onarımı — uzaksa arşive alır, yerel+kayıpsa kaynaktan indirir. */
  mediaRepair: (id: number) =>
    api.post<{ repaired: boolean; main_image: string | null }>(`/api/products/${id}/media-repair`),
};

/** İE#10 Blok 1-4: export + paylaşım. */
/** İE#13 F2/F5/F6: çıktı seçenekleri — kopya türü, durum filtresi, QR adresi. */
export interface ExportOptions {
  copy?: 'firma' | 'ic';
  statuses?: string[];
  /** Aktif paylaşım adresi; verilirse belgeye QR olarak gömülür (F6). */
  share_url?: string;
}

export const exports = {
  /** İE#11 Görev E: üretim POST'a çevrildi (CSRF'li) — dosya blob olarak alınır. */
  create: (listId: number, format: 'xlsx' | 'pdf' | 'csv', options: ExportOptions = {}) =>
    api.postBlob(`/api/lists/${listId}/export?format=${format}`, options),
  history: (listId: number) =>
    api.get<{ id: number; format: string; file_size: number | null; list_revision: number; created_at: string }[]>(
      `/api/lists/${listId}/exports`,
    ),
  fileUrl: (exportId: number) => `/api/exports/${exportId}/file`,
};

/** İE#20 C4 — Ayarlar > Çeviri. API anahtarı ASLA dönmez; yalnız maskeli önizleme. */
export interface CeviriAyarlariOzeti {
  saglayici: string;
  /** İstekte KULLANILAN model (ayar boşsa sağlayıcının varsayılanı). */
  model: string;
  /** Ayarda YAZAN değer — boşsa panel gri yer tutucu gösterir (D1). */
  model_ham: string;
  /** Ayar boşken etkin olacak ad; yer tutucu metni budur. */
  varsayilan_model: string;
  hedef_diller: string[];
  acik: boolean;
  anahtar_tanimli: boolean;
  anahtar_onizleme: string | null;
  saglayicilar: string[];
}

export const ceviri = {
  ayarlar: (signal?: AbortSignal) => api.get<CeviriAyarlariOzeti>('/api/settings/translation', { signal }),
  ayarlariKaydet: (body: Record<string, unknown>) =>
    api.put<CeviriAyarlariOzeti>('/api/settings/translation', body),
  /**
   * D1: bağlantı testi. YEDEĞE DÜŞMEZ — sağlayıcının hatası (model_not_found,
   * 401, 429 …) olduğu gibi döner. Yanıt 200'dür; sonucu `basarili` söyler.
   */
  baglantiTesti: () =>
    api.post<{
      basarili: boolean;
      saglayici: string;
      model: string;
      hata?: string;
      sure_ms?: number;
      ornek_yanit?: string;
    }>('/api/settings/translation/test'),

  /** Çevrilmemiş ürünleri KUYRUĞA alır — ekran beklemez (C4). */
  topluCevir: (listId?: number) =>
    api.post<{ kuyruga_alinan: number; mesaj: string }>(
      '/api/panel/translate-backfill',
      listId === undefined ? {} : { list_id: listId },
    ),
};

/** İE#20 C3 — kuyruk sağlığı. */
export interface KuyrukDurumuVerisi {
  kurulu: boolean;
  mesaj?: string;
  bekleyen: number;
  calisan: number;
  olu: number;
  en_eski_bekleyen_dakika: number | null;
  turler: Record<string, number>;
  /** İE#21 B11 metrikleri — "kuyruk çalışıyor mu" sorusunun sayısal cevabı. */
  saatlik_biten?: number;
  saatlik_olen?: number;
  hata_orani_yuzde?: number;
  yeniden_denenen?: number;
  olu_isler: {
    id: number;
    tur: string;
    anahtar: string | null;
    hata: string | null;
    deneme: number;
    yuk?: unknown;
  }[];
  uyari: string | null;
}

export interface InboxItem {
  id: number;
  status: 'pending' | 'error';
  platform: string;
  external_id: string | null;
  name: string | null;
  price_yuan: string | null;
  image_url: string | null;
  url: string | null;
  error_note: string | null;
  created_at: string;
}

/** GET /api/inbox/{id} — detay çekmecesi (İE#13 B3). */
export interface InboxDetail extends InboxItem {
  images: string[];
  price_tiers: { min_qty: number; price_yuan: string }[];
  sku_matrix: { label: string; price_yuan: string | null }[];
  attributes: Record<string, string>;
  seller_name: string | null;
  captured_at: string | null;
  raw_title: string | null;
}

export interface InboxQueueParams {
  q?: string;
  platform?: string;
  from?: string;
  to?: string;
  page?: number;
}

export const inbox = {
  /** İE#13 B5: filtreli + sayfalı kuyruk; meta içinde toplam ve platform listesi gelir. */
  queue: (params: InboxQueueParams = {}) => {
    const query = new URLSearchParams();
    if (params.q) query.set('q', params.q);
    if (params.platform) query.set('platform', params.platform);
    if (params.from) query.set('from', params.from);
    if (params.to) query.set('to', params.to);
    if (params.page && params.page > 1) query.set('page', String(params.page));
    const suffix = query.toString();
    return api.getWithMeta<InboxItem[]>(`/api/inbox${suffix ? `?${suffix}` : ''}`);
  },
  detail: (id: number) => api.get<InboxDetail>(`/api/inbox/${id}`),
  /** `names`: yalnız kullanıcının "Kullan" dediği çeviri önerileri (K54). */
  /**
   * İE#21 B4 — DESTE MODU: tek yakalama, tek hedef, tek geçiş.
   * Yanıt geri alma bilgisini taşır; çöpe atma geri ALINAMAZ ve bunu söyler.
   */
  deste: (id: number, hedef: 'cop' | 'havuz' | 'liste', listId?: number) =>
    api.post<{
      hedef: 'cop' | 'havuz' | 'liste';
      inbox_id: number;
      liste_id?: number;
      urun_id?: number | null;
      geri_alinabilir: boolean;
    }>('/api/inbox/deste', { id, hedef, ...(listId ? { list_id: listId } : {}) }),

  desteGeriAl: (urunId: number, inboxId: number) =>
    api.post<{ geri_alindi: boolean; neden?: string }>('/api/inbox/deste/geri-al', {
      urun_id: urunId,
      inbox_id: inboxId,
    }),

  assign: (ids: number[], listId: number, names: Record<number, string> = {}) =>
    api.post<{ moved: number; failed: { id: number; error: string }[] }>('/api/inbox/assign', {
      ids,
      list_id: listId,
      ...(Object.keys(names).length > 0 ? { names } : {}),
    }),
  removeMany: (ids: number[]) => api.post<{ deleted: number }>('/api/inbox/delete', { ids }),
  remove: (id: number) => api.delete<void>(`/api/inbox/${id}`),
};

/** İE#13 C4 (K54): ZH→TR başlık önerisi — panelin hiçbir alanı kendiliğinden değişmez. */
/** İE#13 F1: Ayarlar > Belge Antedi — çıktıların üst bandındaki firma kimliği. */
export interface DocumentHeader {
  company: string | null;
  web: string | null;
  email: string | null;
  prepared_by: string | null;
}

export const documentHeader = {
  update: (body: Partial<Record<keyof DocumentHeader, string>>) =>
    api.put<DocumentHeader>('/api/settings/document-header', body),
};

export const translate = {
  // İE#14 A2 (K56): `source` önerinin hangi katmandan geldiğini söyler —
  // 'sozluk' (belirlenimci) ya da 'makine' (gözden geçirilmeli).
  suggest: (text: string) =>
    api.post<{
      suggestion: string | null;
      cached: boolean;
      provider: string | null;
      source: 'sozluk' | 'makine' | null;
    }>('/api/panel/translate-suggest', { text }),

  /** Ürünün TAMAMI tek çağrıda (K56 Katman 2 arayüzü) — yanıt yalnız ÖNERİDİR. */
  product: (urun: { name?: string; category?: string; attributes?: Record<string, string>; variants?: string[] }) =>
    api.post<{
      name?: string;
      category?: string;
      attributes?: Record<string, string>;
      variants?: string[];
      /** İE#20 C4/C5: dil → alanlar (TR ve EN aynı istekte üretilir). */
      ceviriler?: Record<string, { name?: string; category?: string } | undefined>;
      meta?: { provider?: string; sources?: Record<string, string>; target_langs?: string[] };
    }>('/api/panel/translate-product', urun),
};

export const share = {
  create: (listId: number, body: { expires_at?: string } = {}) =>
    api.post<{ share_url: string; share_token_prefix: string; share_expires_at: string | null }>(
      `/api/lists/${listId}/share`,
      body,
    ),
  revoke: (listId: number) => api.delete<void>(`/api/lists/${listId}/share`),

  // İE#18 G6 (K62): erişim anahtarı — paylaşım linki artık tek başına yetmez.
  key: (listId: number) => api.get<{ key: string; enabled: boolean }>(`/api/lists/${listId}/share-key`),
  rotateKey: (listId: number) =>
    api.post<{ key: string; enabled: boolean }>(`/api/lists/${listId}/share-key`, {}),
  toggleKey: (listId: number, enabled: boolean) =>
    api.patch<{ enabled: boolean }>(`/api/lists/${listId}/share-key`, { enabled }),
};

export const categories = {
  all: () => api.get<Category[]>('/api/categories'),
  create: (name: string) => api.post<Category>('/api/categories', { name }),
  update: (id: number, body: { name?: string; sort?: number }) => api.patch<Category>(`/api/categories/${id}`, body),
  remove: (id: number) => api.delete<void>(`/api/categories/${id}`),
};

export const settings = {
  read: () => api.get<Settings>('/api/settings'),
  /** İE#11: eklenti token'ı — tam değer yalnız üretim yanıtında bir kez. */
  extensionTokenCreate: () =>
    api.post<{ token: string; extension_token_preview: string }>('/api/settings/extension-token'),
  extensionTokenRevoke: () => api.delete<void>('/api/settings/extension-token'),
  updateRates: (body: { yuan_tl?: string; usd_tl?: string }) =>
    api.put<{
      yuan_tl: string;
      usd_tl: string;
      /** 3b (K48 ek): boş liste = değer değişmedi, tarihçeye yazılmadı. */
      changes: { currency: 'CNY' | 'USD'; from: string; to: string }[];
    }>('/api/settings/rates', body),
  rateHistory: (currency?: string) =>
    api.get<RateHistoryEntry[]>(`/api/settings/rates/history${currency ? `?currency=${currency}` : ''}`),
  /**
   * İE#21 B5: TCMB'den güncel kur ÖNERİSİ. KAYDETMEZ — panel formu doldurur,
   * kullanıcı onaylayınca kaydedilir (K4: kur bir ticari karardır).
   */
  suggestRates: (signal?: AbortSignal) =>
    api.get<{
      yuan_tl: string;
      usd_tl: string;
      kaynak: string;
      tarih: string | null;
      mevcut: { yuan_tl: string; usd_tl: string };
    }>('/api/settings/rates/suggest', { signal }),
};

export const system = {
  /** İE#20 C3: kuyruk sağlığı — panel Sistem durumu bölümü. */
  kuyruk: (signal?: AbortSignal) => api.get<KuyrukDurumuVerisi>('/api/system/queue', { signal }),
  kuyrukYenidenDene: (id: number) => api.post<{ queued: boolean }>(`/api/system/queue/${id}/retry`),
  /** İE#21 B11: ölü mektup eylemlerinin kalan ikisi. */
  kuyrukVazgec: (id: number) => api.post<{ silindi: boolean }>(`/api/system/queue/${id}/discard`),
  kuyrukDuzelt: (id: number, yuk: Record<string, unknown>) =>
    api.post<{ duzeltildi: boolean }>(`/api/system/queue/${id}/fix`, { yuk }),

  status: () => api.get<SystemStatus>('/api/system/status'),
  /** İzinli durum geçişleri — arayüz kendi kopyasını TUTMAZ (İE#8 §2). */
  stateMachine: () => api.get<StateMachineMap>('/api/system/state-machine'),
  migrate: () => api.post<{ applied: string[]; applied_count: number }>('/api/system/migrate'),
  /** K47: uzak görselleri arşive taşıma — tek çağrı bir parti işler, kalan sıfırlanana dek tekrarlanır.
   *  İE#10 5b: önceki turların başarısız kimlikleri geçilir — parti başı tıkanmaz. */
  mediaMigrate: (exclude?: { exclude_products?: number[]; exclude_images?: number[] }) =>
    api.post<MediaMigrateResult>('/api/system/media-migrate', exclude ?? {}),
  /** İE#10 5d: medya bütünlük denetimi — kayıp dosyaları kaynağından yeniden indirir. */
  mediaCheck: () =>
    api.post<{ mode: string; checked: number; missing: number; repaired: number; failed: unknown[] }>(
      '/api/system/media-check',
    ),
  /** İE#10.5: yedekleme — elle al (+ yapılandırılmışsa off-site), listele, indir. */
  backupCreate: () =>
    api.post<{
      backup: { name: string; size: number; sha256: string; created_at: string };
      offsite: { attempted: boolean; sent: boolean; via: string | null; error: string | null };
    }>('/api/system/backup'),
  backupList: () =>
    api.get<{
      backups: { name: string; size: number; created_at: string }[];
      writable: boolean;
      last_age_seconds: number | null;
      stale: boolean;
      /** İE#14 D1: 30 saati aşan gecikme — gecelik koşu bir kez atlanmış demektir. */
      gecikti: boolean;
      /** Son cron koşusunun izi (storage/logs/cron.log); hiç koşmadıysa null. */
      cron: { line: string; ok: boolean; at: string; age_seconds: number } | null;
      offsite_configured: boolean;
    }>('/api/system/backups'),
  backupFileUrl: (name: string) => `/api/system/backups/${encodeURIComponent(name)}/file`,
  /** K49: migration defterini gerçeğe eşitler — DDL koşmaz, idempotent. */
  migrateBaseline: () =>
    api.post<{ recorded: string[]; skipped: { name: string; reason: string }[]; pending_count: number }>(
      '/api/system/migrate-baseline',
    ),
};

export const trash = {
  read: () => api.get<Trash>('/api/trash'),
  restore: (type: 'lists' | 'products', id: number) =>
    api.post<{ type: string; id: number }>(`/api/trash/${type}/${id}/restore`),
  purge: (type: 'lists' | 'products', id: number) => api.delete<void>(`/api/trash/${type}/${id}`),
};

export const activity = {
  read: (params: { entity_type?: string; page?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.entity_type) query.set('entity_type', params.entity_type);
    if (params.page) query.set('page', String(params.page));
    const suffix = query.toString();
    return api.get<ActivityEntry[]>(`/api/activity${suffix ? `?${suffix}` : ''}`);
  },
};
