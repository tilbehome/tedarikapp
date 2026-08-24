/**
 * MV3 BAŞLANGIÇ TOPARLAMASI (İE#21 A4/A5 sertleştirme maddesi).
 *
 * MV3'te service worker her an uyutulur. Gönderim ortasında uyutulan bir yakalama
 * "sending" damgasıyla kalır ve HİÇBİR ŞEY onu geri almazsa sonsuza kadar öyle
 * durur — kullanıcı için sessizce kaybolmuş demektir.
 *
 * PANELDEKİ İKİZİ (B11 `JobQueue::yukuDuzelt`): sahipsiz kalan işler kiralama
 * süresi dolunca serbest bırakılır. Eklentide kiralama yoktur; bunun yerine
 * "gönderiliyor" damgası ZAMAN taşır: uyanışta damganın üstünden `SAHIPSIZ_MS`
 * geçtiyse kayıt yeniden GÖNDERİLEBİLİR sayılır.
 *
 * NEDEN SÜRE: uyanış anında gerçekten uçmakta olan bir istek olabilir (worker iki
 * kez uyanmaz ama sekme tarafı yeniden deneyebilir). Kısa bir pencere, aynı
 * yakalamanın iki kez POST edilmesini engeller; zaten `capture_id` idempotenstir,
 * yani en kötü hâlde sunucu ikinciyi mükerrer sayar — veri kaybı olmaz.
 */

import type { KuyrukKaydi } from './kuyruk';

/** Bu süreden eski "gönderiliyor" damgası SAHİPSİZ sayılır. */
export const SAHIPSIZ_MS = 60_000;

export interface GonderimDamgasi {
  captureId: string;
  baslangic: number;
}

export const DAMGA_ANAHTARI = 'tedarikapp_gonderiliyor_v2';

export interface Depo {
  get(anahtar: string): Promise<Record<string, unknown>>;
  set(deger: Record<string, unknown>): Promise<void>;
}

export class GonderimIzi {
  public constructor(private readonly depo: Depo) {}

  public async isaretle(captureId: string, simdi: number): Promise<void> {
    const mevcut = await this.damgalar();
    const kalan = mevcut.filter((damga) => damga.captureId !== captureId);
    await this.depo.set({ [DAMGA_ANAHTARI]: [...kalan, { captureId, baslangic: simdi }] });
  }

  public async temizle(captureId: string): Promise<void> {
    const kalan = (await this.damgalar()).filter((damga) => damga.captureId !== captureId);
    await this.depo.set({ [DAMGA_ANAHTARI]: kalan });
  }

  public async damgalar(): Promise<GonderimDamgasi[]> {
    const kayit = await this.depo.get(DAMGA_ANAHTARI);
    const ham = kayit[DAMGA_ANAHTARI];

    return Array.isArray(ham) ? (ham as GonderimDamgasi[]) : [];
  }

  /**
   * Uyanışta çalışır: sahipsiz damgaları siler ve o kayıtları yeniden
   * gönderilebilir olarak döner.
   */
  public async sahipsizleriKurtar(simdi: number): Promise<string[]> {
    const damgalar = await this.damgalar();
    const sahipsiz = damgalar.filter((damga) => simdi - damga.baslangic >= SAHIPSIZ_MS);
    const taze = damgalar.filter((damga) => simdi - damga.baslangic < SAHIPSIZ_MS);

    if (sahipsiz.length > 0) {
      await this.depo.set({ [DAMGA_ANAHTARI]: taze });
    }

    return sahipsiz.map((damga) => damga.captureId);
  }
}

/**
 * Uyanışta gönderilecek kayıtlar: kuyrukta olan VE (damgasız ya da sahipsiz
 * damgalı) olanlar. Taze damgalı kayıt atlanır — biri onu şu an gönderiyor olabilir.
 */
export function toparlanacakKayitlar(
  kuyruk: KuyrukKaydi[],
  damgalar: GonderimDamgasi[],
  simdi: number,
  maksDeneme: number,
): KuyrukKaydi[] {
  const tazeDamgali = new Set(
    damgalar.filter((damga) => simdi - damga.baslangic < SAHIPSIZ_MS).map((damga) => damga.captureId),
  );

  return kuyruk.filter((kayit) => kayit.deneme < maksDeneme && !tazeDamgali.has(kayit.captureId));
}
