import {
  Activity,
  Bell,
  BookOpen,
  Coins,
  FileText,
  Inbox,
  LayoutDashboard,
  Languages,
  Puzzle,
  Scroll,
  Server,
  Share2,
  Shield,
  SlidersHorizontal,
  Target,
  Workflow,
  type LucideIcon,
} from 'lucide-react';

/**
 * AYARLAR — 16 BÖLÜM, BEŞ GRUP (V3-B yeniden tasarım).
 *
 * Kaynak: `docs/v3/hazirlik/v3-b/ayarlar-bilgi-mimarisi.md` §1 (kapsam) +
 * `tasarim-referans/ayarlar-referans.html` (gruplama, ikon, açıklama metni).
 *
 * ÖNCEKİ HÂLİ 16 SEKMELİK YATAY ŞERİTTİ ve kabul turunda reddedildi. Yatay
 * şeritte on altı madde ya sığmıyor ya da yatay kaydırma gerektiriyordu:
 * kullanıcı neyin var olduğunu tek bakışta göremiyor, aradığını kaydırarak
 * arıyordu. Dikey gezinme on altı maddeyi de aynı anda gösterir ve
 * GRUPLAR bir harita kurar — "fiyatla ilgili bir şey arıyorum" diyen kişi
 * doğrudan FİYAT VE DİL bloğuna bakar.
 *
 * AÇIKLAMA SATIRI ZORUNLUDUR. "Keşif & Skor" tek başına ne olduğunu
 * söylemez; "Sinyal ve puanlama modeli" söyler. Referansın en değerli
 * katkısı bu metinlerdir ve buradan alınmıştır.
 *
 * BOŞ BÖLÜM GİZLENMEZ (K98): bilgi mimarisi kullanıcının zihninde bir harita
 * kurar, yarısını saklamak haritayı bozar. Ne zaman dolacağını söyleyen tek
 * satırla görünür.
 */

export interface AyarBolumu {
  /** URL parçası — `/ayarlar?sekme=kur` biçiminde paylaşılabilir. */
  kod: string;
  ad: string;
  /** Gezinmede adın altındaki tek satır. */
  aciklama: string;
  /** Bölüm başlığı kartındaki alt başlık (kapsam). */
  kapsam: string;
  ikon: LucideIcon;
  /** Aramanın taradığı ek kelimeler (referanstaki `data-ara`). */
  aramaSozcukleri: string;
  /** Bu bölümde bugün gerçek ayar var mı? */
  dolu: boolean;
  /** Boşsa: ne zaman dolacak? */
  bekleyen?: string;
}

export interface AyarGrubu {
  baslik: string;
  bolumler: AyarBolumu[];
}

export const AYAR_GRUPLARI: AyarGrubu[] = [
  {
    baslik: 'TEMEL',
    bolumler: [
      {
        kod: 'genel',
        ad: 'Genel',
        aciklama: 'Temel çalışma tercihleri',
        kapsam: 'Uygulama adresi ve panel temelleri',
        ikon: SlidersHorizontal,
        aramaSozcukleri: 'genel çalışma tercihleri saat dilimi para birimi adres url',
        dolu: true,
      },
      {
        kod: 'gorunum',
        ad: 'Görünüm & Panorama',
        aciklama: 'Panel ve özet ekranı',
        kapsam: 'Tema seçimi ve "Bugün ne var?" görünümü',
        ikon: LayoutDashboard,
        aramaSozcukleri: 'görünüm panorama panel özet ekranı tema koyu açık brifing',
        dolu: true,
      },
      {
        kod: 'bildirimler',
        ad: 'Bildirimler',
        aciklama: 'Hangi olaylar zile düşer',
        kapsam: 'Bildirim merkezi, birleştirme ve kritik görünürlük',
        ikon: Bell,
        aramaSozcukleri: 'bildirim merkezi olay rozet zil birleştirme',
        dolu: true,
      },
    ],
  },
  {
    baslik: 'VERİ VE OPERASYON',
    bolumler: [
      {
        kod: 'yakalama',
        ad: 'Yakalama & Eklenti',
        aciklama: 'Tarayıcıdan ürün alma',
        kapsam: 'Platform, önizleme, çevrimdışı kuyruk ve seçici sağlığı',
        ikon: Puzzle,
        aramaSozcukleri: 'yakalama eklenti chrome tarayıcı 1688 seçici',
        dolu: false,
        bekleyen: 'Eklenti ayarları bugün eklentinin kendi açılır penceresinde; panele taşınması V3-C.',
      },
      {
        kod: 'gelen-kutusu',
        ad: 'Gelen Kutusu & Kurallar',
        aciklama: 'Otomasyon ve yönlendirme',
        kapsam: 'Deste modu, toplu işlem ve otomatik kural davranışı',
        ikon: Inbox,
        aramaSozcukleri: 'gelen kutusu kural otomasyon deste toplu',
        dolu: false,
        bekleyen: 'Otomatik kural motoru henüz yok; deste modu şimdilik Gelen Kutusu ekranından yönetiliyor.',
      },
      {
        kod: 'kesif',
        ad: 'Keşif & Skor',
        aciklama: 'Sinyal ve puanlama modeli',
        kapsam: 'Varsayılan filtreler, karşılaştırma, kümeler ve skor gösterimi',
        ikon: Target,
        aramaSozcukleri: 'keşif skor puan sinyal filtre küme karşılaştırma',
        dolu: false,
        bekleyen: 'Keşif tercihleri şimdilik ekranın kendi süzgeçlerinde saklanıyor.',
      },
      {
        kod: 'listeler',
        ad: 'Listeler & İş Akışı',
        aciklama: 'Durumlar ve sorumlular',
        kapsam: 'Kategoriler, HAZIR kapısı, revizyon ve liste varsayılanları',
        ikon: Workflow,
        aramaSozcukleri: 'liste iş akışı durum kategori revizyon hazır',
        dolu: true,
      },
    ],
  },
  {
    baslik: 'FİYAT VE DİL',
    bolumler: [
      {
        kod: 'kur',
        ad: 'Kur & Para Birimleri',
        aciklama: 'Kur kaynağı ve yuvarlama',
        kapsam: 'Aktif kur, TCMB önerisi, onay ve kur geçmişi',
        ikon: Coins,
        aramaSozcukleri: 'kur para birimi yuan dolar tcmb döviz',
        dolu: true,
      },
      {
        kod: 'ceviri',
        ad: 'Çeviri Sağlayıcısı',
        aciklama: 'Motor ve kullanım sınırları',
        kapsam: 'Sağlayıcı, model, gizli anahtar ve bağlantı testi',
        ikon: Languages,
        aramaSozcukleri: 'çeviri sağlayıcı llm deepseek anahtar api model',
        dolu: true,
      },
      {
        kod: 'diller',
        ad: 'Diller & Sözlük',
        aciklama: 'TR, EN ve ZH terimleri',
        kapsam: 'Hedef diller, sözlük dışa/içe aktarma ve sürümleme',
        ikon: BookOpen,
        aramaSozcukleri: 'dil sözlük terim csv türkçe ingilizce çince glossary',
        dolu: true,
      },
    ],
  },
  {
    baslik: 'ÇIKTI VE İLETİŞİM',
    bolumler: [
      {
        kod: 'ciktilar',
        ad: 'Çıktılar & Antet',
        aciklama: 'Excel, PDF ve belge antedi',
        kapsam: 'Kurumsal antet ve çıktı seçenekleri',
        ikon: FileText,
        aramaSozcukleri: 'çıktı antet excel pdf belge logo firma',
        dolu: true,
      },
      {
        kod: 'paylasim',
        ad: 'Paylaşım & WhatsApp',
        aciklama: 'Bağlantılar ve kanallar',
        kapsam: 'WhatsApp köprü numarası ve paylaşım iletişimi',
        ikon: Share2,
        aramaSozcukleri: 'paylaşım whatsapp link anahtar numara kanal',
        dolu: true,
      },
    ],
  },
  {
    baslik: 'SİSTEM',
    bolumler: [
      {
        kod: 'guvenlik',
        ad: 'Güvenlik',
        aciklama: 'Oturum, şifre ve eklenti anahtarı',
        kapsam: 'İki adımlı doğrulama, eklenti token’ı ve denetim kaydı',
        ikon: Shield,
        aramaSozcukleri: 'güvenlik şifre oturum token 2fa totp eklenti anahtarı',
        dolu: true,
      },
      {
        kod: 'sistem',
        ad: 'Sistem & Yedekler',
        aciklama: 'Medya, yedek ve bakım',
        kapsam: 'Görsel arşivi, yedekleme ve migration eylemleri',
        ikon: Server,
        aramaSozcukleri: 'sistem yedek medya arşiv backup migration bakım',
        dolu: true,
      },
      {
        kod: 'gunluk',
        ad: 'Günlük',
        aciklama: 'Hata ve uyarı kayıtları',
        kapsam: 'Arka planda oluşan hata ve uyarılar',
        ikon: Scroll,
        aramaSozcukleri: 'günlük log hata uyarı kayıt',
        dolu: true,
      },
      {
        kod: 'durum',
        ad: 'Sistem Durumu',
        aciklama: 'Sürüm, PHP ve bütünlük',
        kapsam: 'Sürüm, PHP, veritabanı, kataloglar ve migration durumu',
        ikon: Activity,
        aramaSozcukleri: 'durum sürüm php veritabanı bütünlük katalog migration',
        dolu: true,
      },
    ],
  },
];

/**
 * Düz liste — arama, çözümleme ve sıra numarası için.
 *
 * ADI `AYAR_SEKMELERI` KALIYOR (yeniden adlandırılmadı): C2 koruma listesi
 * bekçisi bu adı okuyor ve o bekçinin değişmemesi PM şartıdır. Yapı değişti,
 * sözleşme değişmedi — `no` alanı sıra numarasını taşımaya devam ediyor.
 */
export const AYAR_SEKMELERI: (AyarBolumu & { no: number })[] = AYAR_GRUPLARI.flatMap(
  (grup) => grup.bolumler,
).map((bolum, sira) => ({ ...bolum, no: sira + 1 }));

/** Geriye dönük ad — yeni kod bunu kullanır. */
export const AYAR_BOLUMLERI: AyarBolumu[] = AYAR_SEKMELERI;

/**
 * Geçerli bölüm kodu mu? Bilinmeyen kod ilkine düşer.
 *
 * ESKİ KODLAR KORUNUR: `?sekme=veri` adresi yer imine eklenmiş olabilir.
 * "Veri & Bakım" bölümü "Sistem & Yedekler" adını aldı ve kodu `sistem`
 * oldu; eski kod sessizce ilk bölüme düşseydi kullanıcı yanlış ekrana
 * giderdi ve sebebini anlamazdı.
 */
const ESKI_KODLAR: Record<string, string> = {
  veri: 'sistem',
  'gelen-kutusu': 'gelen-kutusu',
  panorama: 'gorunum',
  'firma-portali': 'listeler',
};

export function sekmeyiCoz(kod: string | null): AyarBolumu {
  const varsayilan = AYAR_BOLUMLERI[0] as AyarBolumu;
  if (kod === null || kod === '') return varsayilan;

  const hedef = ESKI_KODLAR[kod] ?? kod;

  return AYAR_BOLUMLERI.find((bolum) => bolum.kod === hedef) ?? varsayilan;
}

/** Yeni koddaki ad — `sekmeyiCoz` ile aynı işlevi görür. */
export const bolumuCoz = sekmeyiCoz;

/**
 * Aramaya göre süzülmüş gruplar.
 *
 * Ad, açıklama VE arama sözcükleri taranır: kullanıcı "api anahtarı" yazınca
 * "Çeviri Sağlayıcısı" çıkmalı, oysa o kelimeler başlıkta geçmiyor. Boşalan
 * grup tamamen düşer — başlığı olup öğesi olmayan grup, "burada bir şey vardı
 * ama kayboldu" hissi verir.
 */
export function gruplariSuz(sorgu: string): AyarGrubu[] {
  const kirpik = sorgu.trim().toLocaleLowerCase('tr');
  if (kirpik === '') return AYAR_GRUPLARI;

  return AYAR_GRUPLARI.map((grup) => ({
    ...grup,
    bolumler: grup.bolumler.filter((bolum) =>
      `${bolum.ad} ${bolum.aciklama} ${bolum.aramaSozcukleri}`.toLocaleLowerCase('tr').includes(kirpik),
    ),
  })).filter((grup) => grup.bolumler.length > 0);
}
