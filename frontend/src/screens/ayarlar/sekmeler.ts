/**
 * AYARLAR — 16 SEKME SİCİLİ (V3-B C1).
 *
 * Kaynak: `docs/v3/hazirlik/v3-b/ayarlar-bilgi-mimarisi.md` §1. Sıra ve adlar
 * ORADAN gelir; burada değiştirilmez.
 *
 * BOŞ SEKME GİZLENMEZ. Menüdeki "hazır olmayan ekran" kuralının (İE#20 C8)
 * tersi bir karar ve gerekçesi farklı: menüdeki ölü öğe kullanıcıyı boşa
 * tıklatır, ama ayarlarda 16 sekmenin 11'ini göstermek "geri kalanı nerede?"
 * sorusunu doğurur — bilgi mimarisi kullanıcının zihninde bir harita kurar ve
 * o haritanın yarısını saklamak haritayı bozar. Boş sekme, NE ZAMAN
 * dolacağını söyleyen tek satırla görünür; bu, Panorama'daki "henüz
 * ölçülmüyor" ayrımının aynısıdır.
 */

export interface AyarSekmesi {
  /** URL parçası — `/ayarlar?sekme=kur` biçiminde paylaşılabilir. */
  kod: string;
  no: number;
  ad: string;
  kapsam: string;
  /** Bu sekmede bugün gerçek ayar var mı? */
  dolu: boolean;
  /** Boşsa: ne zaman dolacak? */
  bekleyen?: string;
}

export const AYAR_SEKMELERI: AyarSekmesi[] = [
  {
    kod: 'genel',
    no: 1,
    ad: 'Genel',
    kapsam: 'Uygulama adresi, görünüm teması ve panel temelleri',
    dolu: true,
  },
  {
    kod: 'panorama',
    no: 2,
    ad: 'Panorama',
    kapsam: 'Brifing yoğunluğu, öncelik ve anomali görünümü',
    dolu: false,
    bekleyen: 'Panorama yeni açıldı; eşik ayarları ölçüm geçmişi biriktikten sonra anlamlı olacak.',
  },
  {
    kod: 'yakalama',
    no: 3,
    ad: 'Yakalama & Eklenti',
    kapsam: 'Platform, önizleme, çevrimdışı kuyruk ve seçici sağlığı',
    dolu: false,
    bekleyen: 'Eklenti ayarları bugün eklentinin kendi açılır penceresinde; panele taşınması V3-C.',
  },
  {
    kod: 'gelen-kutusu',
    no: 4,
    ad: 'Gelen Kutusu & Kurallar',
    kapsam: 'Deste modu, toplu işlem ve otomatik kural davranışı',
    dolu: false,
    bekleyen: 'Otomatik kural motoru henüz yok; deste modu şimdilik Gelen Kutusu ekranından yönetiliyor.',
  },
  {
    kod: 'kesif',
    no: 5,
    ad: 'Keşif & Skor',
    kapsam: 'Varsayılan filtreler, karşılaştırma, kümeler ve skor gösterimi',
    dolu: false,
    bekleyen: 'Keşif tercihleri şimdilik ekranın kendi süzgeçlerinde saklanıyor.',
  },
  {
    kod: 'listeler',
    no: 6,
    ad: 'Listeler & İş Akışı',
    kapsam: 'Kategoriler, HAZIR kapısı, revizyon ve liste varsayılanları',
    dolu: true,
  },
  {
    kod: 'kur',
    no: 7,
    ad: 'Kur & Para Birimleri',
    kapsam: 'Aktif kur, TCMB önerisi, onay ve kur geçmişi',
    dolu: true,
  },
  {
    kod: 'ceviri',
    no: 8,
    ad: 'Çeviri Sağlayıcısı',
    kapsam: 'Sağlayıcı, model, gizli anahtar ve bağlantı testi',
    dolu: true,
  },
  {
    kod: 'diller',
    no: 9,
    ad: 'Diller & Sözlük',
    kapsam: 'Hedef diller, sözlük dışa/içe aktarma ve sürümleme',
    dolu: true,
  },
  {
    kod: 'ciktilar',
    no: 10,
    ad: 'Çıktılar & Antet',
    kapsam: 'Excel/PDF/paylaşım anteti ve kurumsal bilgiler',
    dolu: true,
  },
  {
    kod: 'paylasim',
    no: 11,
    ad: 'Paylaşım & WhatsApp',
    kapsam: 'WhatsApp köprü numarası ve paylaşım iletişimi',
    dolu: true,
  },
  {
    kod: 'firma-portali',
    no: 12,
    ad: 'Firma Portalı',
    kapsam: 'Firma yanıtı, otomatik kayıt ve DDP teyidi',
    dolu: false,
    bekleyen: 'Firma portalı V3-C ile geliyor.',
  },
  {
    kod: 'bildirimler',
    no: 13,
    ad: 'Bildirimler',
    kapsam: 'Bildirim merkezi, birleştirme ve kritik görünürlük',
    dolu: true,
  },
  {
    kod: 'guvenlik',
    no: 14,
    ad: 'Güvenlik & API',
    kapsam: 'İki adımlı doğrulama, eklenti token’ı ve denetim kaydı',
    dolu: true,
  },
  {
    kod: 'kuyruk',
    no: 15,
    ad: 'Kuyruk & Zamanlayıcı',
    kapsam: 'Arka plan işleri, ölü iş rafı ve tur özeti',
    dolu: true,
  },
  {
    kod: 'veri',
    no: 16,
    ad: 'Veri & Bakım',
    kapsam: 'Görsel arşivi, yedekleme, günlükler, migration ve sistem durumu',
    dolu: true,
  },
];

/** Geçerli sekme kodu mu? Bilinmeyen kod ilk sekmeye düşer. */
export function sekmeyiCoz(kod: string | null): AyarSekmesi {
  // Dizi sabittir ve boş olamaz; `noUncheckedIndexedAccess` altında yine de
  // açık bir yedek verilir — tip iddiası (`as`) yerine gerçek bir değer.
  const varsayilan: AyarSekmesi = AYAR_SEKMELERI[0] ?? {
    kod: 'genel',
    no: 1,
    ad: 'Genel',
    kapsam: '',
    dolu: false,
  };

  return AYAR_SEKMELERI.find((sekme) => sekme.kod === kod) ?? varsayilan;
}
