/**
 * GÖNDERİM YÜRÜTÜCÜSÜ (v1.0/A7 — saha bulgusu 27 Ağu).
 *
 * SAHA: capture gönderimi 4 sn'lik mesaj zaman aşımına takılıyordu. Sunucu ana
 * görseli indirirken 4 sn'yi aşıyor, ÜRÜN LİSTEYE DÜŞÜYOR, ama panel
 * "TedarikApp şu anda yanıt vermiyor" diyordu. Kullanıcı başarılı bir işlemi
 * başarısız sanıp elle tekrar deniyordu.
 *
 * İKİ AYRI SÜRE VARDIR VE KARIŞTIRILMAMALIDIR:
 *   · DURUM/LİSTE sorguları hızlıdır — 4 sn üst sınır doğrudur, uzun bekleme
 *     kullanıcıyı boş ekrana bakar hâlde bırakır.
 *   · GÖNDERİM sunucuda görsel indirir, yeniden kodlar, diske yazar. Burada
 *     30 sn beklemek doğrudur; erken pes etmek YANLIŞ BİLGİ üretir.
 *
 * 30 SN DE AŞILIRSA HATA VERİLMEZ: gönderim büyük olasılıkla sürüyordur.
 * Kullanıcıya "gönderildi mi kontrol ediliyor…" denir ve istek AYNI
 * `capture_id` ile TEKRARLANIR. Sunucu tarafı bu kimliği rezerve ettiği için
 * (rc8-03 idempotans) ikinci istek YENİ KAYIT AÇMAZ; ilk isteğin sonucunu
 * döndürür. Bu yüzden tekrarın "duplicate" yanıtı MÜKERRER değil BAŞARI olarak
 * yorumlanır — mükerrer olan, kendi ilk denememizdir.
 */

export interface CaptureYaniti {
  duplicate?: boolean;
  product_id?: number | null;
  inbox_id?: number;
}

export type GonderimSonucu =
  | { sonuc: 'BASARILI'; urunId: number | null }
  | { sonuc: 'MUKERRER'; urunId: number | null }
  | { sonuc: 'YETKI' }
  | { sonuc: 'SUNUCU'; hata: string };

export const GONDERIM_ZAMAN_ASIMI_MS = 30_000;
export const KONTROL_NOTU = 'Gönderildi mi kontrol ediliyor…';

export interface GonderimBagimliliklari {
  /** İsteği AYNI capture_id ile yürütür; her çağrı yeni bir deneme sayılır. */
  istek: (deneme: number) => Promise<CaptureYaniti>;
  /** Panele düşen ilerleme notu; null notu siler. */
  onNot?: (not: string | null) => void;
  /** Test edilebilirlik: gerçek zamanlayıcı yerine sahte saat verilebilir. */
  bekle?: (ms: number) => Promise<void>;
  zamanAsimiMs?: number;
}

function varsayilanBekle(ms: number): Promise<void> {
  return new Promise((coz) => setTimeout(coz, ms));
}

/** Hata mesajından sonuç türü: yetki hataları yeniden denenmez. */
function hataSonucu(hata: unknown): GonderimSonucu {
  const mesaj = hata instanceof Error ? hata.message : String(hata);

  return /401|403|AYAR_EKSIK|YETKI/i.test(mesaj) ? { sonuc: 'YETKI' } : { sonuc: 'SUNUCU', hata: mesaj };
}

function yanitiCevir(yanit: CaptureYaniti, tekrarMi: boolean): GonderimSonucu {
  const urunId = yanit.product_id ?? null;
  if (yanit.duplicate === true && !tekrarMi) {
    return { sonuc: 'MUKERRER', urunId };
  }

  // Tekrar denemesinde "duplicate" bizim İLK isteğimizdir: kayıt açılmış
  // demektir, kullanıcıya "listeye eklendi" gösterilir.
  return { sonuc: 'BASARILI', urunId };
}

export async function gonderimiYurut(bag: GonderimBagimliliklari): Promise<GonderimSonucu> {
  const zamanAsimi = bag.zamanAsimiMs ?? GONDERIM_ZAMAN_ASIMI_MS;
  const bekle = bag.bekle ?? varsayilanBekle;

  let ilkIstek: Promise<CaptureYaniti>;
  try {
    ilkIstek = bag.istek(1);
  } catch (hata) {
    return hataSonucu(hata);
  }

  // Yarış: istek mi önce biter, süre mi dolar? Sonuç AYIRT EDİCİ bir zarfla
  // taşınır — yanıtın kendisi bir işaret değeriyle karıştırılamaz.
  type Yaris = { tur: 'yanit'; yanit: CaptureYaniti } | { tur: 'zamanAsimi' };
  let ilkSonuc: Yaris;
  try {
    ilkSonuc = await Promise.race<Yaris>([
      ilkIstek.then((yanit) => ({ tur: 'yanit' as const, yanit })),
      bekle(zamanAsimi).then(() => ({ tur: 'zamanAsimi' as const })),
    ]);
  } catch (hata) {
    return hataSonucu(hata);
  }

  if (ilkSonuc.tur === 'yanit') {
    bag.onNot?.(null);

    return yanitiCevir(ilkSonuc.yanit, false);
  }

  // Süre doldu: HATA DEĞİL, BELİRSİZLİK. Kullanıcı bilgilendirilir ve aynı
  // kimlikle tekrar sorulur.
  bag.onNot?.(KONTROL_NOTU);
  // Askıdaki ilk isteğin reddi sahipsiz kalmasın (unhandled rejection).
  void ilkIstek.catch(() => undefined);

  try {
    const tekrar = await bag.istek(2);
    bag.onNot?.(null);

    return yanitiCevir(tekrar, true);
  } catch (hata) {
    bag.onNot?.(null);

    return hataSonucu(hata);
  }
}
