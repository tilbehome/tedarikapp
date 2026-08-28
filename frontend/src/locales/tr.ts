/**
 * Türkçe sözlük — K22'nin arayüz ayağı.
 *
 * DB ve API yalnızca İngilizce makine kodları taşır; Türkçe karşılıklar SADECE burada
 * yaşar. Ekranlarda ham enum görünmesi hata sayılır (docs/09 §6 çeviri tablosu).
 */

/**
 * DURUM ETİKETLERİ — TEK KAYNAK `config/durumlar.json` (İE#21 B13).
 *
 * Buradaki değerler o dosyanın Türkçe sütununun AYNISIDIR. Kopya olması bilinçli
 * bir ödünçtür: panel derlemesi repo kökündeki `config/` klasörüne erişemez
 * (Vite kökü `frontend/`), JSON'u paketin içine kopyalamak da derleme adımını
 * karmaşıklaştırırdı. Sapma riski TESTLE kapatılmıştır: `DurumSozluguTest`
 * (PHP) bu dosyayı okuyup JSON ile karşılaştırır ve fark varsa KIRMIZI yanar.
 * Yani iki liste var ama İKİSİNİN AYRI DÜŞMESİ mümkün değil.
 */
export const productStatusLabels = {
  to_order: 'Verilecek',
  ordered: 'Verildi',
  in_transit: 'Yolda',
  received: 'Geldi',
  cancelled: 'İptal',
} as const;

export const listStatusLabels = {
  draft: 'Taslak',
  sent: 'İletildi',
  ordered: 'Sipariş Verildi',
  completed: 'Tamamlandı',
  cancelled: 'İptal',
} as const;

export const visibilityLabels = {
  active: 'Aktif',
  passive: 'Pasif',
  archived: 'Arşiv',
} as const;

export const inboxStatusLabels = {
  pending: 'Bekliyor',
  error: 'Hatalı',
  assigned: 'Atandı',
} as const;

/** Durum rozetlerinin renk sınıfları — anlam renkle de taşınır (docs/09 erişilebilirlik). */
export const productStatusTone: Record<keyof typeof productStatusLabels, string> = {
  to_order: 'bg-g100 text-ink-2 ring-line',
  ordered: 'bg-warn-soft text-warn ring-warn/20',
  in_transit: 'bg-info-soft text-info ring-info/20',
  received: 'bg-ok-soft text-ok ring-ok/20',
  cancelled: 'bg-err-soft text-err ring-err/20',
};

export const listStatusTone: Record<keyof typeof listStatusLabels, string> = {
  draft: 'bg-g100 text-ink-2 ring-line',
  sent: 'bg-info-soft text-info ring-info/20',
  ordered: 'bg-warn-soft text-warn ring-warn/20',
  completed: 'bg-ok-soft text-ok ring-ok/20',
  cancelled: 'bg-err-soft text-err ring-err/20',
};

/**
 * API hata kodu → kullanıcıya gösterilecek Türkçe cümle (docs/10 §1).
 *
 * Sunucu zaten Türkçe mesaj döndürüyor; bu sözlük ağ hatası gibi mesajsız
 * durumlar ve kodun kendisi gösterilmesin diye yedek metin sağlar.
 */
export const errorMessages: Record<string, string> = {
  VALIDATION: 'Girdiğiniz bilgilerde hata var.',
  UNAUTHENTICATED: 'Oturumunuz sona ermiş. Lütfen yeniden giriş yapın.',
  TOTP_REQUIRED: 'İki adımlı doğrulama kodu bekleniyor.',
  FORBIDDEN: 'Bu işlem için yetkiniz yok.',
  CSRF: 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.',
  NOT_FOUND: 'Kayıt bulunamadı.',
  METHOD_NOT_ALLOWED: 'Bu işlem desteklenmiyor.',
  UNSUPPORTED_MEDIA_TYPE: 'İstek biçimi kabul edilmedi.',
  STATE_TRANSITION: 'Bu durum değişikliği yapılamaz.',
  LIST_IMMUTABLE: 'Bu liste kapanmış ve artık değiştirilemez. Devam etmek için listeyi kopyalayın.',
  HTTPS_REQUIRED: 'Bu adım güvenli (https) bağlantı gerektiriyor.',
  DUPLICATE_WARNING: 'Bu ürün daha önce eklenmiş.',
  RATE_LIMITED: 'Çok fazla istek gönderildi. Biraz bekleyin.',
  LOCKED: 'Çok fazla hatalı deneme yapıldı.',
  PAYLOAD_TOO_LARGE: 'Gönderilen veri çok büyük.',
  SERVER_ERROR: 'Beklenmeyen bir hata oluştu. Tekrar deneyin.',
  NETWORK: 'Sunucuya ulaşılamadı. Bağlantınızı kontrol edin.',
};

export const mediaModeLabels = {
  download: 'Görseller sunucuda arşivleniyor',
  hotlink: 'Arşivleme kapalı — görseller kaynak sitedeki bağlantıdan gösteriliyor',
} as const;
