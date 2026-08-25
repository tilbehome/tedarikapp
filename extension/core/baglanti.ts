/**
 * BAĞLANTI DURUMU — TEK KAYNAK (İE#21 D5 saha bulgusu, 25 Ağu 2026).
 *
 * BULGU: canlıda toolbar popup "bağlı ✓" derken SAYFA İÇİ panel bağlantısız
 * görünüyordu — Hedef listesi boş, "Yakala ve Gönder" pasif. İki yüzey aynı
 * veriye BAŞKA yollardan bakıyordu:
 *
 *   · popup  → her açılışta `LISTS` sorar, hatayı OKUR ve sebebini yazar
 *              ("ayar gerekli" / "token geçersiz" / "bağlantı yok"),
 *   · inline → açılışta bir kez `LISTS` sorar; hata yakalanır ve SESSİZCE
 *              boş listeye düşer. Yeniden deneme yok, bağlantı kavramı yok,
 *              ayar sonradan girilse haber alan yok.
 *
 * Sonuç: geçici bir hata (service worker uykudan kalkarken düşen ilk mesaj,
 * panel henüz açılmamış, token yeni girilmiş) kalıcı bir "bağlantı yok"
 * görüntüsüne dönüşüyordu.
 *
 * BU MODÜL O AYRIMI KALDIRIR: iki yüzey de bağlantıyı BURADAN sorar, aynı
 * sınıflandırmayı ve aynı Türkçe cümleyi kullanır. Hata sebebi SINIFLANIR —
 * "bilinmeyen hata" diye bir sonuç yoktur; çünkü kullanıcı "ne yapmalıyım?"
 * sorusuna cevap arar, hata metnine değil.
 */

export type BaglantiDurumu = 'BILINMIYOR' | 'DENENIYOR' | 'BAGLI' | 'AYAR_EKSIK' | 'YETKI' | 'ERISILEMIYOR';

/** Panelden gelen liste kaydının bu modül için gereken en az yüzeyi. */
export interface PanelListeKaydi {
  id: number;
  name: string;
}

export interface BaglantiSonucu<T extends PanelListeKaydi = PanelListeKaydi> {
  durum: BaglantiDurumu;
  /** Kullanıcıya gösterilecek tek cümle. */
  mesaj: string;
  /** Bağlıysa hedef seçici için hazır liste (başında varsayılan hedef). */
  listeler: { id: number | null; ad: string }[];
  /**
   * Panelden gelen HAM kayıtlar — popup, hedef seçicide durum rozeti de gösterir
   * ve bu bilgiyi kaybetmemelidir.
   */
  ham: T[];
}

export const VARSAYILAN_HEDEF_ADI = 'Gelen Kutusu (varsayılan)';

/** Hata metnini kullanıcıya anlamlı bir duruma çevirir. */
export function hatayiSinifla(mesaj: string): Exclude<BaglantiDurumu, 'BAGLI' | 'BILINMIYOR' | 'DENENIYOR'> {
  if (/AYAR_EKSIK/i.test(mesaj)) return 'AYAR_EKSIK';
  if (/\b401\b|\b403\b|token|yetki|unauthorized|forbidden/i.test(mesaj)) return 'YETKI';

  return 'ERISILEMIYOR';
}

export function baglantiMesaji(durum: BaglantiDurumu): string {
  switch (durum) {
    case 'BAGLI':
      return 'Panele bağlı';
    case 'DENENIYOR':
      return 'Bağlantı deneniyor…';
    case 'AYAR_EKSIK':
      return 'Panel adresi ve token girilmemiş — eklenti simgesinden ayarlayın.';
    case 'YETKI':
      return 'Token geçersiz ya da iptal edilmiş — panelden yeni token alın.';
    case 'ERISILEMIYOR':
      return 'Panele ulaşılamıyor — yakalama kuyrukta bekler, bağlanınca gönderilir.';
    default:
      return 'Bağlantı durumu bilinmiyor.';
  }
}

/** Yeniden deneme aralıkları (ms) — artan, ama kullanıcıyı bekletmeyecek kadar kısa. */
export const DENEME_ARALIKLARI = [400, 1200, 3000];

export interface BaglantiSecenekleri<T extends PanelListeKaydi = PanelListeKaydi> {
  /** Panelden liste çeker; hata fırlatır. */
  listeleriGetir: () => Promise<T[]>;
  /** Denemeler arasında bekler (testte anında döner). */
  bekle?: (ms: number) => Promise<void>;
}

/**
 * Bağlantıyı dener; geçici hatalarda YENİDEN DENER.
 *
 * AYAR_EKSIK ve YETKI'de tekrar denenmez: ikisi de kullanıcı eylemi ister,
 * tekrar denemek yalnız gecikme üretir. Ağ/erişim hatası ise geçici olabilir —
 * MV3 service worker uykudan kalkarken ilk mesaj düşebilir; asıl saha vakası
 * buydu.
 */
export async function baglantiyiDene<T extends PanelListeKaydi = PanelListeKaydi>(
  secenekler: BaglantiSecenekleri<T>,
): Promise<BaglantiSonucu<T>> {
  const bekle = secenekler.bekle ?? ((ms: number) => new Promise<void>((r) => setTimeout(r, ms)));
  let sonDurum: BaglantiDurumu = 'ERISILEMIYOR';

  for (let deneme = 0; deneme <= DENEME_ARALIKLARI.length; deneme++) {
    try {
      const listeler = await secenekler.listeleriGetir();

      return {
        durum: 'BAGLI',
        mesaj: baglantiMesaji('BAGLI'),
        listeler: [
          { id: null, ad: VARSAYILAN_HEDEF_ADI },
          ...listeler.map((liste) => ({ id: liste.id, ad: liste.name })),
        ],
        ham: listeler,
      };
    } catch (hata) {
      const mesaj = hata instanceof Error ? hata.message : String(hata);
      sonDurum = hatayiSinifla(mesaj);

      if (sonDurum !== 'ERISILEMIYOR') break;

      const aralik = DENEME_ARALIKLARI[deneme];
      if (aralik === undefined) break;
      await bekle(aralik);
    }
  }

  return { durum: sonDurum, mesaj: baglantiMesaji(sonDurum), listeler: [], ham: [] };
}
