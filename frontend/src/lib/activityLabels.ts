/**
 * Aktivite kaydı kodları → Türkçe cümle (K22 · docs/09 §6 ilkesi).
 *
 * Kodlar backend'deki `activity_log.action` değerleridir; ekranda ham kod
 * gösterilmez. Tanınmayan kod gelirse okunabilir bir metne çevrilir, gizlenmez —
 * kayıt kaybolmasın.
 */
const labels: Record<string, string> = {
  login_success: 'Giriş yapıldı',
  login_failed: 'Hatalı giriş denemesi',
  login_locked: 'Hesap geçici olarak kilitlendi',
  totp_success: 'İki adımlı doğrulama başarılı',
  totp_failed: 'Hatalı doğrulama kodu',
  recovery_used: 'Kurtarma kodu kullanıldı',
  recovery_failed: 'Hatalı kurtarma kodu',
  logout: 'Çıkış yapıldı',
  remember_login: 'Hatırlanan cihazdan giriş',
  remember_theft: 'Şüpheli hatırlama kaydı iptal edildi',
  remember_revoked: 'Cihaz oturumu kapatıldı',
  user_created: 'Kullanıcı oluşturuldu',

  list_created: 'Liste oluşturuldu',
  list_updated: 'Liste güncellendi',
  list_deleted: 'Liste çöp kutusuna atıldı',
  list_duplicated: 'Liste kopyalandı',
  list_restored: 'Liste geri alındı',
  list_purged: 'Liste kalıcı silindi',

  product_created: 'Ürün eklendi',
  product_updated: 'Ürün güncellendi',
  product_status_changed: 'Ürün durumu değişti',
  product_deleted: 'Ürün çöp kutusuna atıldı',
  product_restored: 'Ürün geri alındı',
  product_purged: 'Ürün kalıcı silindi',
  product_reordered: 'Ürün sırası değişti',
  product_bulk_status: 'Toplu durum güncellemesi',
  product_bulk_move: 'Ürünler başka listeye taşındı',
  product_bulk_delete: 'Toplu ürün silme',

  category_created: 'Kategori eklendi',
  category_updated: 'Kategori güncellendi',
  category_deleted: 'Kategori silindi',

  rates_updated: 'Kurlar güncellendi',
  migrate: 'Veritabanı güncellemesi çalıştırıldı',
  migrate_failed: 'Veritabanı güncellemesi başarısız',
};

export function actionLabel(action: string): string {
  return labels[action] ?? action.replace(/_/g, ' ');
}

const entityLabels: Record<string, string> = {
  auth: 'Oturum',
  list: 'Liste',
  product: 'Ürün',
  category: 'Kategori',
  settings: 'Ayarlar',
  system: 'Sistem',
};

export function entityLabel(entity: string): string {
  return entityLabels[entity] ?? entity;
}

export const activityFilters: { value: string; label: string }[] = [
  { value: '', label: 'Tümü' },
  { value: 'list', label: 'Liste' },
  { value: 'product', label: 'Ürün' },
  { value: 'category', label: 'Kategori' },
  { value: 'settings', label: 'Ayarlar' },
  { value: 'auth', label: 'Oturum' },
  { value: 'system', label: 'Sistem' },
];
