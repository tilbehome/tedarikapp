/**
 * PROMINENT DISCLOSURE (İE#21 A8 · Chrome Web Store politikası).
 *
 * Store kuralı: kullanıcı verisi toplayan bir eklenti, TOPLAMAYA BAŞLAMADAN ÖNCE
 * ne topladığını açıkça söylemeli ve ONAY almalıdır. Bizim durumumuzda "veri
 * toplama", kullanıcının açtığı ürün sayfasının okunup TedarikApp paneline
 * gönderilmesidir.
 *
 * ÜÇ SÖZ (E2E-EKL-24):
 *  1. Onay ALINMADAN hiçbir yakalama yapılmaz — düğmeye ilk basışta panel değil,
 *     bu metin açılır.
 *  2. Metin NE toplandığını, NEREYE gittiğini ve neyin GİTMEDİĞİNİ sayar.
 *  3. Onay kalıcıdır ama geri alınabilir; reddedilirse eklenti sessizce durur —
 *     düğme kalır, yakalama olmaz.
 *
 * ONAY SÜRÜMLÜDÜR: metin değişirse (yeni alan toplamaya başlarsak) sürüm artar ve
 * onay yeniden istenir. Eski onayı yeni kapsam için kullanmak, onay olmamasıyla
 * aynı şeydir.
 */

export const DISCLOSURE_ANAHTARI = 'tedarikapp_disclosure_v2';

/** Metin değişirse ARTIR: eski onay yeni kapsamı kapsamaz. */
export const DISCLOSURE_SURUMU = 2;

export interface DisclosureKaydi {
  surum: number;
  onaylandi: boolean;
  tarih: string | null;
}

/** Kullanıcıya gösterilen metnin maddeleri — tek kaynak burasıdır. */
export const DISCLOSURE_METNI = {
  baslik: 'TedarikApp ürün sayfasını okuyacak',
  toplananlar: [
    'Ürün adı, fiyat ve fiyat kademeleri',
    'Görseller, varyasyonlar ve ürün özellikleri',
    'Satıcı adı ve ilan adresi',
  ],
  gonderilenYer: 'Yalnız sizin kurduğunuz TedarikApp paneline (Ayarlar’da girdiğiniz adres).',
  toplanmayanlar: [
    'Çerezleriniz, oturum bilgileriniz ve 1688 hesabınız okunmaz',
    'Gezinme geçmişiniz izlenmez; yalnız düğmeye bastığınız sayfa okunur',
    'Veri üçüncü taraflara gönderilmez, reklam/analitik kullanılmaz',
  ],
  onayDugmesi: 'Anladım, yakalamayı başlat',
  redDugmesi: 'Şimdi değil',
};

export interface Depo {
  get(anahtar: string): Promise<Record<string, unknown>>;
  set(deger: Record<string, unknown>): Promise<void>;
}

/** Kayıt geçerli mi? Sürüm eskiyse onay YOK sayılır. */
export function onayGecerli(kayit: unknown): boolean {
  if (typeof kayit !== 'object' || kayit === null) return false;
  const aday = kayit as Partial<DisclosureKaydi>;

  return aday.onaylandi === true && aday.surum === DISCLOSURE_SURUMU;
}

export class Disclosure {
  public constructor(private readonly depo: Depo) {}

  public async onayliMi(): Promise<boolean> {
    const kayit = await this.depo.get(DISCLOSURE_ANAHTARI);

    return onayGecerli(kayit[DISCLOSURE_ANAHTARI]);
  }

  public async onayla(simdi: string): Promise<void> {
    const kayit: DisclosureKaydi = { surum: DISCLOSURE_SURUMU, onaylandi: true, tarih: simdi };
    await this.depo.set({ [DISCLOSURE_ANAHTARI]: kayit });
  }

  /** Reddetmek de KAYDEDİLİR: her sayfa açılışında metni tekrar dayatmayız. */
  public async reddet(simdi: string): Promise<void> {
    const kayit: DisclosureKaydi = { surum: DISCLOSURE_SURUMU, onaylandi: false, tarih: simdi };
    await this.depo.set({ [DISCLOSURE_ANAHTARI]: kayit });
  }
}
