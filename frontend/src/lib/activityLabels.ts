/**
 * Aktivite kaydı kodları → Türkçe cümle, simge ve bağlantı (K22 · docs/09 §6 · İE#14 A7).
 *
 * Kodlar backend'deki `activity_log.action` değerleridir; ekranda HAM KOD GÖSTERİLMEZ.
 * Tanınmayan kod gelirse `insanlastir()` okunabilir bir cümleye çevirir — kayıt asla
 * gizlenmez, çünkü bilinmeyen bir işlem tam da görülmesi gereken şeydir.
 *
 * Her kayıt ilgili kayda GİDER: liste/ürün kaydı liste detayına, gelen kutusu kaydı
 * gelen kutusuna, ayar kaydı ayarlara. Hedefi olmayan kayıt (oturum, sistem) düz metindir.
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
  product_media_repaired: 'Ürün görseli onarıldı',

  category_created: 'Kategori eklendi',
  category_updated: 'Kategori güncellendi',
  category_deleted: 'Kategori silindi',

  // İE#14 A7: kayıtlı ama Türkçesi olmayan kodlar tamamlandı.
  export_created: 'Belge çıktısı alındı',
  share_created: 'Paylaşım bağlantısı oluşturuldu',
  share_renewed: 'Paylaşım bağlantısı yenilendi',
  share_revoked: 'Paylaşım bağlantısı iptal edildi',
  inbox_assigned: 'Gelen kutusundan listeye aktarıldı',
  inbox_deleted: 'Gelen kutusu kaydı silindi',
  extension_token_created: 'Eklenti anahtarı oluşturuldu',
  extension_token_revoked: 'Eklenti anahtarı iptal edildi',
  document_header_updated: 'Belge anteti güncellendi',
  glossary_updated: 'Terim sözlüğü güncellendi',
  rates_updated: 'Kurlar güncellendi',
  migrate: 'Veritabanı güncellemesi çalıştırıldı',

  // v1.2.2 Blok 0.3 — ETİKETSİZ KALMIŞ KODLAR.
  //
  // Bunlar `insanlastir()` yedeğine düşüyordu ve panelde "Ceviri urun",
  // "Migrate baseline" gibi HAM kod okunuyordu: Türkçe değil, cümle değil,
  // hatta doğru bile değil ("Ceviri urun" bir eylem adı gibi durmuyor).
  // Yedek bir NEZAKET katmanıdır; kalıcı çözüm değildir.
  migrate_baseline: 'Veritabanı defteri gerçeğe eşitlendi',
  backup_created: 'Yedek alındı',
  media_migrate: 'Görseller arşive taşındı',
  media_check: 'Görsel bütünlüğü denetlendi',
  glossary_imported: 'Terim sözlüğü içe aktarıldı',
  app_url_updated: 'Uygulama adresi güncellendi',
  share_contact_updated: 'Paylaşım iletişim numarası güncellendi',
  share_key_rotated: 'Erişim anahtarı yenilendi',
  share_key_rate_limited: 'Erişim anahtarı denemesi hız sınırına takıldı',
  queue_retry: 'Kuyruk işi yeniden denendi',
  queue_discard: 'Kuyruk işi çöpe atıldı',
  queue_fix: 'Kuyruk işinin yükü düzeltildi',
  sozluksuz_ceviri_yenile: 'Sözlüksüz çeviriler yeniden kuyruğa alındı',
  migrate_failed: 'Veritabanı güncellemesi başarısız',
  backup_completed: 'Yedek alındı',
  backup_failed: 'Yedekleme başarısız',
  maintenance_completed: 'Bakım görevleri çalıştı',
};

/**
 * Bilinmeyen kodu cümleye çevirir: `foo_bar_done` → "Foo bar done".
 * Ham kod (alt çizgili, İngilizce) ASLA olduğu gibi basılmaz.
 */
function insanlastir(action: string): string {
  const metin = action.replace(/[_-]+/g, ' ').trim();
  if (metin === '') return 'Bilinmeyen işlem';

  return metin.charAt(0).toLocaleUpperCase('tr-TR') + metin.slice(1);
}

export function actionLabel(action: string): string {
  return labels[action] ?? insanlastir(action);
}

/**
 * Kayıt türüne göre simge adı (lucide-react bileşen adı değil, kendi kümemiz —
 * ekran bunu kendi ikon eşlemesine bağlar).
 */
export type ActivityIcon =
  | 'oturum'
  | 'liste'
  | 'urun'
  | 'kategori'
  | 'ayar'
  | 'sistem'
  | 'belge'
  | 'paylasim'
  | 'gelen'
  | 'uyari';

export function actionIcon(action: string, entityType: string): ActivityIcon {
  if (action.endsWith('_failed') || action === 'login_locked' || action === 'remember_theft') return 'uyari';
  if (action.startsWith('share_')) return 'paylasim';
  if (action.startsWith('export_')) return 'belge';
  if (action.startsWith('inbox_')) return 'gelen';

  switch (entityType) {
    case 'auth':
      return 'oturum';
    case 'list':
      return 'liste';
    case 'product':
      return 'urun';
    case 'category':
      return 'kategori';
    case 'settings':
      return 'ayar';
    default:
      return 'sistem';
  }
}

export interface ActivityEntryRef {
  entity_type: string;
  entity_id: number | null;
  action: string;
}

/**
 * Kaydın gideceği panel adresi; hedefi yoksa null (satır düz metin kalır).
 * Ürün kaydında entity_id ÜRÜN kimliğidir; ürünün listesi bilinmediğinden
 * liste detayına değil, listeler ekranına gidilir — yanlış listeye götürmektense
 * bir adım geride bırakmak doğrudur.
 */
export function activityLink(entry: ActivityEntryRef): string | null {
  if (entry.action.startsWith('inbox_')) return '/gelen-kutusu';
  if (entry.action.startsWith('extension_token_') || entry.action === 'glossary_updated') return '/ayarlar';

  switch (entry.entity_type) {
    case 'list':
      return entry.entity_id !== null ? `/listeler/${entry.entity_id}` : '/listeler';
    case 'export':
      return entry.entity_id !== null ? `/listeler/${entry.entity_id}` : '/listeler';
    case 'product':
      return '/listeler';
    case 'category':
      return '/ayarlar/kategoriler';
    case 'settings':
      return '/ayarlar';
    default:
      return null;
  }
}

const entityLabels: Record<string, string> = {
  auth: 'Oturum',
  list: 'Liste',
  product: 'Ürün',
  category: 'Kategori',
  settings: 'Ayarlar',
  system: 'Sistem',
  export: 'Belge',
  inbox: 'Gelen kutusu',
};

export function entityLabel(entity: string): string {
  return entityLabels[entity] ?? insanlastir(entity);
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
