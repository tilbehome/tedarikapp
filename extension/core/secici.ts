/**
 * SEÇİCİ SÜRÜMLEME VE FİKSTÜR ÖN-KAPISI (İE#21 A6).
 *
 * K53: seçiciler KOD DEĞİL VERİDİR — 1688 sayfa yapısı değişince panelden yeni
 * seçici seti iner, eklenti güncellenmez. Bu esnekliğin bedeli şudur: BOZUK bir
 * seti indiren eklenti, kullanıcının gözünde "eklenti bozuldu" olur.
 *
 * ÜÇ KATMANLI SAVUNMA:
 *  1. **GÖMÜLÜ PAKET** — eklentinin içinde her zaman çalışan bir set vardır.
 *     Panel erişilemese, sunucu boş dönse, ağ kopsa bile yakalama çalışır.
 *  2. **FİKSTÜR ÖN-KAPISI** — inen set, KABUL EDİLMEDEN ÖNCE gerçek bir fikstür
 *     üzerinde denenir. Fikstürden ad ve fiyat çıkmıyorsa set REDDEDİLİR ve
 *     son iyi bilinen set kullanılmaya devam eder. Kapı, kullanıcının canlı
 *     sayfasında değil, elimizdeki örnekte açılır.
 *  3. **SÜRÜM KIYASI** — aynı ya da daha eski sürüm indiyse değiştirme yapılmaz;
 *     boş yere set değiştirmek, tekrar üretilemeyen hataların kaynağıdır.
 *
 * SÜRÜM ALANI: setin `updated_at` tarihidir (ISO gün). Şema sürümü ayrıdır ve
 * DESTEKLENEN aralığın dışındaki set hiç denenmez — bilinmeyen şemayı ayrıştırmaya
 * çalışmak, sessizce yanlış alan okumak demektir.
 */

import gomuluPaket from '../tests/fixtures/selectors-1688.json';
import type { SelectorSet } from './types';

/** Eklentinin içinde taşıdığı, her zaman çalışan set. */
export const GOMULU_SECICILER = gomuluPaket as unknown as SelectorSet & { updated_at?: string };

/** Bu şema sürümlerini ayrıştırabiliriz; başkasını denemeyiz. */
export const DESTEKLENEN_SEMALAR = [2];

export type SecimSebebi =
  | 'GOMULU_BASLANGIC'
  | 'UZAK_KABUL'
  | 'UZAK_SEMA_DESTEKSIZ'
  | 'UZAK_PLATFORM_UYUSMUYOR'
  | 'UZAK_EKSIK_ALAN'
  | 'UZAK_ESKI_SURUM'
  | 'UZAK_FIKSTUR_KAPISI';

export interface SecimSonucu {
  secilen: SelectorSet;
  sebep: SecimSebebi;
  /** Reddedildiyse kullanıcıya/loga yazılacak insan cümlesi. */
  aciklama: string | null;
}

/** Ön kapı: set gerçek bir fikstürü ayrıştırabiliyor mu? */
export type FiksturKapisi = (set: SelectorSet) => boolean;

/** Setin taşıdığı sürüm (ISO gün); yoksa boş dize — kıyasta "en eski" sayılır. */
export function surum(set: SelectorSet): string {
  const deger = (set as { updated_at?: unknown }).updated_at;

  return typeof deger === 'string' ? deger : '';
}

/** Set biçimsel olarak kullanılabilir mi? (şema, platform, zorunlu yollar) */
function bicimGecerli(set: unknown): set is SelectorSet {
  if (typeof set !== 'object' || set === null) return false;
  const aday = set as Partial<SelectorSet>;

  return (
    typeof aday.platform === 'string'
    && typeof aday.schema_version === 'number'
    && typeof aday.paths === 'object'
    && aday.paths !== null
  );
}

/**
 * Bu yollar olmadan yakalama anlamsızdır: kimlik, ad, fiyat ve görsel.
 * Adlar gerçek sözleşmeden alınır (`tests/fixtures/selectors-1688.json`) —
 * "images" gibi tahmini bir ad, sağlam paketi yanlışlıkla reddettirirdi.
 */
const ZORUNLU_YOLLAR = ['offer_id', 'title', 'current_prices', 'gallery'];

/**
 * Uzak seti kabul mü, ret mi? Ret hâlinde mevcut set aynen kalır.
 *
 * @param mevcut  şu an kullanılan set (ilk açılışta gömülü paket)
 * @param uzak    panelden inen aday (null: ağ yok / boş yanıt)
 * @param kapi    fikstür ön-kapısı
 */
export function secicileriSec(mevcut: SelectorSet, uzak: unknown, kapi: FiksturKapisi): SecimSonucu {
  if (uzak === null || uzak === undefined) {
    return { secilen: mevcut, sebep: 'GOMULU_BASLANGIC', aciklama: null };
  }

  if (!bicimGecerli(uzak)) {
    return {
      secilen: mevcut,
      sebep: 'UZAK_EKSIK_ALAN',
      aciklama: 'İnen seçici paketi beklenen biçimde değil; mevcut paket korundu.',
    };
  }

  if (!DESTEKLENEN_SEMALAR.includes(uzak.schema_version)) {
    return {
      secilen: mevcut,
      sebep: 'UZAK_SEMA_DESTEKSIZ',
      aciklama: `Seçici paketi şema ${uzak.schema_version} ile geldi; bu eklenti ${DESTEKLENEN_SEMALAR.join('/')} destekliyor.`,
    };
  }

  if (uzak.platform !== mevcut.platform) {
    return {
      secilen: mevcut,
      sebep: 'UZAK_PLATFORM_UYUSMUYOR',
      aciklama: `Paket "${uzak.platform}" platformu için; bu sayfa "${mevcut.platform}".`,
    };
  }

  const eksik = ZORUNLU_YOLLAR.filter((ad) => !Array.isArray(uzak.paths[ad]) || uzak.paths[ad].length === 0);
  if (eksik.length > 0) {
    return {
      secilen: mevcut,
      sebep: 'UZAK_EKSIK_ALAN',
      aciklama: `İnen pakette zorunlu yollar eksik: ${eksik.join(', ')}.`,
    };
  }

  if (surum(uzak) <= surum(mevcut)) {
    // Aynı sürümü yeniden yüklemek davranışı değiştirmez ama hata ayıklamayı
    // zorlaştırır: "hangi setle çalışıyordu?" sorusu cevapsız kalır.
    return { secilen: mevcut, sebep: 'UZAK_ESKI_SURUM', aciklama: null };
  }

  if (!kapi(uzak)) {
    return {
      secilen: mevcut,
      sebep: 'UZAK_FIKSTUR_KAPISI',
      aciklama: 'İnen seçici paketi örnek sayfayı ayrıştıramadı; eski paket korundu.',
    };
  }

  return { secilen: uzak, sebep: 'UZAK_KABUL', aciklama: null };
}
