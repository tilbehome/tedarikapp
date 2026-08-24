/**
 * FİKSTÜR ÖN-KAPISI (İE#21 A6 — `secici.ts`nin kullandığı somut kapı).
 *
 * İnen seçici paketi, KABUL EDİLMEDEN ÖNCE elimizdeki örnek sayfa yapısıyla
 * denenir: adı ve fiyatı çıkarabiliyorsa kapı açılır. Kapı canlı sayfada değil,
 * BİLİNEN bir örnekte açılır — kullanıcının açtığı ürün, seçici denemesi için
 * bir deney tahtası değildir.
 *
 * Fikstür KÜÇÜKTÜR ve kasıtlı olarak burada durur: gerçek bir 1688 yanıtının
 * tamamını taşımak bundle'ı şişirirdi; kapının sorduğu soru "bu paket temel
 * alanları bulabiliyor mu?"dur, "her alanı buluyor mu?" değil.
 */

import { parse1688 } from '../modules/m1688/parser';
import type { SelectorSet } from './types';

/** Kapının denediği en küçük gerçekçi sayfa bağlamı (rapor §13 yapısı). */
export const KAPI_FIKSTURU = {
  result: {
    global: {
      globalData: {
        model: {
          offerDetail: { offerId: 895133432293, subject: 'Kapı fikstürü ürünü 门测试' },
          tradeModel: {
            offerPriceModel: {
              currentPrices: [{ price: '26.90', beginAmount: 1 }],
            },
          },
          images: [{ fullPathImageURI: 'https://cbu01.alicdn.com/img/ibank/kapi.jpg' }],
        },
      },
    },
  },
};

export const KAPI_URL = 'https://detail.1688.com/offer/895133432293.html';

/**
 * Paket temel alanları çıkarabiliyor mu?
 *
 * "Temel" = ad + fiyat. Bu ikisi olmadan yakalama zaten gönderilemez (backend
 * doğrulaması reddeder), dolayısıyla kapının ölçtüğü şey tam olarak "bu paketle
 * çalışılabilir mi?" sorusudur.
 */
export function fiksturdenGecer(set: SelectorSet): boolean {
  try {
    const sonuc = parse1688(KAPI_FIKSTURU, set, KAPI_URL);

    return sonuc.normalized.name.trim() !== '' && sonuc.normalized.price_yuan.trim() !== '';
  } catch {
    // Bozuk regex, beklenmeyen tip: paket zaten reddedilmeli — çökme, ret demektir.
    return false;
  }
}
