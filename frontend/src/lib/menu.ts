import {
  Archive,
  BarChart3,
  Boxes,
  Building2,
  Calendar,
  ClipboardList,
  Compass,
  Eye,
  FileText,
  FolderOpen,
  Inbox,
  LayoutDashboard,
  ListChecks,
  PieChart,
  Settings,
  ShoppingCart,
  Truck,
  type LucideIcon,
} from 'lucide-react';

/**
 * BİLGİ MİMARİSİ — sol menü (İE#16 D1.5 · V3 kanonu §3).
 *
 * Menü BEŞ GRUPTUR: ÇALIŞMA / TEDARİK / ANALİZ / KAYITLAR / SİSTEM. Kanon bu
 * yapıyı Faz 1'de tam gösterir; ekranların çoğu sonraki fazlarda dolacağı için
 * `hazir: false` olanlar görünür ama "Yakında" rozetiyle pasiftir. GİZLEMİYORUZ:
 * kullanıcı ürünün nereye gittiğini görsün, menü sonradan büyüyüp yerini
 * değiştirmesin (kas hafızası).
 *
 * Rozet sayısı 0 ise BASILMAZ (kanon §3).
 */

export interface MenuOgesi {
  /** Rota yolu — hazır olmayan ekranlarda yalnız anahtar görevi görür. */
  to: string;
  label: string;
  icon: LucideIcon;
  /** Ekran bu fazda çalışıyor mu? */
  hazir: boolean;
  /** Rozet sayacının hangi kaynaktan geleceği (0/undefined ise basılmaz). */
  rozet?: 'gelenKutusu';
  /** Kırıntı yolunda görünen bölüm adı. */
  bolum: string;
}

export interface MenuGrubu {
  baslik: string;
  ogeler: MenuOgesi[];
}

export const menuGruplari: MenuGrubu[] = [
  {
    baslik: 'ÇALIŞMA',
    ogeler: [
      { to: '/', label: 'Panorama', icon: LayoutDashboard, hazir: true, bolum: 'Çalışma' },
      { to: '/kesif', label: 'Keşif', icon: Compass, hazir: false, bolum: 'Çalışma' },
      { to: '/gelen-kutusu', label: 'Gelen Kutusu', icon: Inbox, hazir: true, rozet: 'gelenKutusu', bolum: 'Çalışma' },
      { to: '/listeler', label: 'Listeler', icon: ListChecks, hazir: true, bolum: 'Çalışma' },
    ],
  },
  {
    baslik: 'TEDARİK',
    ogeler: [
      { to: '/teklifler', label: 'Teklifler', icon: FileText, hazir: false, bolum: 'Tedarik' },
      { to: '/siparisler', label: 'Siparişler', icon: ShoppingCart, hazir: false, bolum: 'Tedarik' },
      { to: '/sevkiyat', label: 'Sevkiyat', icon: Truck, hazir: false, bolum: 'Tedarik' },
    ],
  },
  {
    baslik: 'ANALİZ',
    ogeler: [
      { to: '/avantaj', label: 'İthalat Avantajı', icon: BarChart3, hazir: false, bolum: 'Analiz' },
      { to: '/izleme', label: 'İzleme', icon: Eye, hazir: false, bolum: 'Analiz' },
      { to: '/raporlar', label: 'Raporlar', icon: PieChart, hazir: false, bolum: 'Analiz' },
    ],
  },
  {
    baslik: 'KAYITLAR',
    ogeler: [
      { to: '/takvim', label: 'Takvim', icon: Calendar, hazir: false, bolum: 'Kayıtlar' },
      { to: '/belgeler', label: 'Belgeler', icon: FolderOpen, hazir: false, bolum: 'Kayıtlar' },
      { to: '/firmalar', label: 'Firmalar', icon: Building2, hazir: false, bolum: 'Kayıtlar' },
    ],
  },
  {
    baslik: 'SİSTEM',
    ogeler: [
      { to: '/ayarlar', label: 'Ayarlar', icon: Settings, hazir: true, bolum: 'Sistem' },
      { to: '/arsiv', label: 'Arşiv', icon: Archive, hazir: true, bolum: 'Sistem' },
    ],
  },
];

/**
 * Arşiv bir "kapı" ekranıdır: çöp kutusu ve aktivite günlüğü onun altındadır.
 * Menüde tek satır durur, alt maddeler ekranın kendi sekmeleridir (kanon §3).
 */
export const arsivAltEkranlari: { to: string; label: string; icon: LucideIcon }[] = [
  { to: '/cop-kutusu', label: 'Çöp Kutusu', icon: Boxes },
  { to: '/aktivite', label: 'Aktivite Günlüğü', icon: ClipboardList },
];

/** Kırıntı yolu ve sayfa başlığı için: yol → [bölüm, ekran]. */
export function ekranAdi(pathname: string): [string, string] {
  const ozel: Record<string, [string, string]> = {
    '/cop-kutusu': ['Sistem', 'Çöp Kutusu'],
    '/aktivite': ['Sistem', 'Aktivite Günlüğü'],
    '/ayarlar/kategoriler': ['Sistem', 'Kategoriler'],
    '/bilesenler': ['Sistem', 'Bileşen Kitaplığı'],
  };
  if (ozel[pathname]) return ozel[pathname];

  for (const grup of menuGruplari) {
    for (const oge of grup.ogeler) {
      if (oge.to === '/' ? pathname === '/' : pathname.startsWith(oge.to)) {
        return [oge.bolum, oge.label];
      }
    }
  }

  return ['Çalışma', 'Panorama'];
}
