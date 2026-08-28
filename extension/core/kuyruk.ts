/**
 * KALICI YAKALAMA KUYRUĞU (İE#21 A4).
 *
 * SORUN: v1'de gönderilemeyen yakalama yalnız açık paneldeydi. Sekme kapanırsa,
 * MV3 service worker uykuya dalarsa ya da ağ koparsa yakalama SESSİZCE kayboluyordu
 * — kullanıcı "gönderdim" sanıp listeye bakınca ürünü bulamıyordu.
 *
 * ÇÖZÜM: yakalama önce `storage.local`e YAZILIR, sonra gönderilir. Gönderim
 * başarılıysa kayıt düşer; başarısızsa kuyrukta bekler ve MV3 uyanışında
 * (`onStartup`/`onInstalled`) toparlanır.
 *
 * TASARIM KARARLARI:
 *  · **Kimlik `capture_id`dir** — kuyruk kendi kimliğini üretmez. Aynı yakalama
 *    iki kez kuyruğa girerse ikincisi ÜSTÜNE yazar; sunucu tarafındaki idempotens
 *    (K25) ile aynı sözü paylaşır.
 *  · **Deneme sayısı ve son hata saklanır** — kullanıcıya "3 kez denendi, sunucu
 *    yanıt vermiyor" diyebilmek için. Sessiz sonsuz tekrar YOKTUR: `MAKS_DENEME`
 *    aşılınca kayıt "elde" kalır ve kullanıcı komutu beklenir (katalog D10 ilkesi).
 *  · **Sıra korunur** — önce yakalanan önce gider; kullanıcı listede beklediği
 *    sırayı görür.
 */

import type { CapturePayload } from './types';

export const KUYRUK_ANAHTARI = 'tedarikapp_kuyruk_v2';

/** Bu sayıdan sonra otomatik denenmez; kullanıcı komutu beklenir. */
export const MAKS_DENEME = 3;

export interface KuyrukKaydi {
  captureId: string;
  yuk: CapturePayload;
  deneme: number;
  sonHata: string | null;
  eklendi: string;
}

/** `storage.local` sözleşmesi — testte sahte, üretimde chrome.storage. */
export interface Depo {
  get(anahtar: string): Promise<Record<string, unknown>>;
  set(deger: Record<string, unknown>): Promise<void>;
}

export function chromeDeposu(): Depo {
  return {
    get: (anahtar) => chrome.storage.local.get(anahtar),
    set: (deger) => chrome.storage.local.set(deger),
  };
}

export class Kuyruk {
  public constructor(private readonly depo: Depo) {}

  public async liste(): Promise<KuyrukKaydi[]> {
    const kayit = await this.depo.get(KUYRUK_ANAHTARI);
    const ham = kayit[KUYRUK_ANAHTARI];

    return Array.isArray(ham) ? (ham as KuyrukKaydi[]) : [];
  }

  /** Kuyruğa ekler; aynı `capture_id` varsa ÜSTÜNE yazar (mükerrer kayıt olmaz). */
  public async ekle(yuk: CapturePayload, simdi: string): Promise<KuyrukKaydi[]> {
    const mevcut = await this.liste();
    const kalanlar = mevcut.filter((kayit) => kayit.captureId !== yuk.capture_id);
    const yeni: KuyrukKaydi = {
      captureId: yuk.capture_id,
      yuk,
      deneme: 0,
      sonHata: null,
      eklendi: simdi,
    };

    const sonuc = [...kalanlar, yeni];
    await this.yaz(sonuc);

    return sonuc;
  }

  public async dusur(captureId: string): Promise<KuyrukKaydi[]> {
    const kalanlar = (await this.liste()).filter((kayit) => kayit.captureId !== captureId);
    await this.yaz(kalanlar);

    return kalanlar;
  }

  /** Başarısız denemeyi işler: sayaç artar, hata metni saklanır. */
  public async basarisiz(captureId: string, hata: string): Promise<KuyrukKaydi[]> {
    const guncel = (await this.liste()).map((kayit) =>
      kayit.captureId === captureId ? { ...kayit, deneme: kayit.deneme + 1, sonHata: hata } : kayit,
    );
    await this.yaz(guncel);

    return guncel;
  }

  /** Otomatik toparlamaya UYGUN kayıtlar — deneme hakkı bitenler elde kalır. */
  public async toparlanacaklar(): Promise<KuyrukKaydi[]> {
    return (await this.liste()).filter((kayit) => kayit.deneme < MAKS_DENEME);
  }

  /** Kullanıcı "tekrar dene" derse sayaç sıfırlanır: komut, hakkı geri verir. */
  public async denemeleriSifirla(captureId: string): Promise<KuyrukKaydi[]> {
    const guncel = (await this.liste()).map((kayit) =>
      kayit.captureId === captureId ? { ...kayit, deneme: 0, sonHata: null } : kayit,
    );
    await this.yaz(guncel);

    return guncel;
  }

  private async yaz(kayitlar: KuyrukKaydi[]): Promise<void> {
    await this.depo.set({ [KUYRUK_ANAHTARI]: kayitlar });
  }
}
