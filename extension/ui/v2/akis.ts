/**
 * SAYFA İÇİ AKIŞ — durum makinesini arayüze bağlar (İE#21 A1/A3/A5/A8).
 *
 * Sıralama bilinçlidir ve kataloğun ağ ilkesine uyar:
 *   düğme → (onay yoksa disclosure) → TARA → ayrıştır → önizleme → GÖNDER.
 *
 * Ağ işi background'dadır; bu dosya yalnız mesaj gönderir. Token bu katmana hiç
 * inmez (K34).
 */

import {
  gecis,
  baslangicDurumu,
  type MakineDurumu,
  type MukerrerSecenegi,
} from '../../core/durumMakinesi';
import { alanRaporu, gonderimiEngelleyenler, type AlanRaporu } from '../../core/alanRaporu';
import type { ParseResult } from '../../core/types';
import type { DuranKayit, HedefSecimi, PanelGorunumu } from './panel';

export interface AkisBagimliliklari {
  /** Sayfayı okuyup ayrıştırır (bridge + parser). */
  ayristir: () => Promise<ParseResult>;
  /** Yakalamayı background üzerinden gönderir. */
  gonder: (yuk: { captureId: string; hedef: HedefSecimi; sonuc: ParseResult }) => Promise<GonderimYaniti>;
  /** Disclosure onayı var mı? */
  onayliMi: () => Promise<boolean>;
  /** Kuyrukta duran (hakkı bitmiş) kayıtlar. */
  duranlar: () => Promise<DuranKayit[]>;
  /** Hedef liste seçenekleri. */
  listeler: () => Promise<{ id: number | null; ad: string }[]>;
  /** Yeni yakalama kimliği (UUID). */
  kimlikUret: () => string;
  /** Görünüm değişince çağrılır — arayüz kendini yeniden çizer. */
  ciz: (gorunum: PanelGorunumu) => void;
}

export type GonderimYaniti =
  | { sonuc: 'BASARILI'; urunId: number | null }
  | { sonuc: 'MUKERRER'; urunId: number | null }
  | { sonuc: 'YETKI' }
  | { sonuc: 'SUNUCU'; hata: string };

const VARSAYILAN_HEDEF: HedefSecimi = { listeId: null, miktar: 1, not: '', etiketler: [] };

export class Akis {
  private makine: MakineDurumu = baslangicDurumu();

  private rapor: AlanRaporu | null = null;

  private sonuc: ParseResult | null = null;

  private hedef: HedefSecimi = { ...VARSAYILAN_HEDEF };

  private seciliVaryant: string | null = null;

  private listeler: { id: number | null; ad: string }[] = [];

  private duranlar: DuranKayit[] = [];

  private disclosureGerekli = false;

  public constructor(private readonly bagimliliklar: AkisBagimliliklari) {}

  public durum(): MakineDurumu {
    return this.makine;
  }

  public gorunum(): PanelGorunumu {
    return {
      makine: this.makine,
      rapor: this.rapor,
      urunAdi: this.sonuc?.normalized.name ?? null,
      orijinalAd: this.sonuc?.raw.title ?? null,
      varyantlar: this.varyantlar(),
      seciliVaryant: this.seciliVaryant,
      listeler: this.listeler,
      hedef: this.hedef,
      duranlar: this.duranlar,
      disclosureGerekli: this.disclosureGerekli,
    };
  }

  private varyantlar(): string[] {
    const matris = this.sonuc?.normalized.sku_matrix ?? [];

    return matris
      .map((sku) => Object.values(sku.props).join(' / '))
      .filter((ad) => ad.trim() !== '')
      .slice(0, 24);
  }

  private yayinla(): void {
    this.bagimliliklar.ciz(this.gorunum());
  }

  /** Panel açılışı: onay durumu, listeler ve duran kayıtlar okunur. */
  public async ac(): Promise<void> {
    this.disclosureGerekli = !(await this.bagimliliklar.onayliMi());
    this.duranlar = await this.bagimliliklar.duranlar();
    if (!this.disclosureGerekli && this.listeler.length === 0) {
      this.listeler = await this.bagimliliklar.listeler();
    }
    this.yayinla();
  }

  public async disclosureKarari(onay: boolean): Promise<void> {
    this.disclosureGerekli = !onay;
    if (onay) {
      this.listeler = await this.bagimliliklar.listeler();
      await this.tara();

      return;
    }
    this.yayinla();
  }

  /**
   * TARA: tek geçiş, tek ayrıştırma (EKL-03/04).
   * Makine D2'de `TARA` kabul etmediği için çift tık kendiliğinden engellenir.
   */
  public async tara(): Promise<void> {
    const oncesi = this.makine;
    this.makine = gecis(this.makine, 'TARA', { captureId: this.bagimliliklar.kimlikUret() });
    if (this.makine === oncesi) return; // geçiş yok: ikinci tık
    this.yayinla();

    try {
      const sonuc = await this.bagimliliklar.ayristir();
      this.sonuc = sonuc;
      this.rapor = alanRaporu(sonuc);
      this.seciliVaryant = this.varyantlar()[0] ?? null;

      const engeller = gonderimiEngelleyenler(this.rapor);
      if (engeller.length > 0) {
        // Zorunlu alan yoksa bu bir OKUMA HATASIDIR: gönderilse backend reddederdi.
        this.makine = gecis(this.makine, 'OKUMA_HATASI');
      } else if (this.rapor.eksikler.length > 0) {
        this.makine = gecis(this.makine, 'OKUMA_KISMI', { eksikler: this.rapor.eksikler });
      } else {
        this.makine = gecis(this.makine, 'OKUMA_TAM');
      }
    } catch {
      this.makine = gecis(this.makine, 'OKUMA_HATASI');
    }

    this.yayinla();
  }

  public devam(): void {
    this.makine = gecis(this.makine, 'DEVAM');
    this.yayinla();
  }

  public varyantSec(varyant: string): void {
    this.seciliVaryant = varyant;
    this.yayinla();
  }

  public hedefDegistir(hedef: HedefSecimi): void {
    this.hedef = hedef;
    this.yayinla();
  }

  /** GÖNDER: tek istek; 502/mükerrer sonrası tekrar AYNI capture_id ile (EKL-15). */
  public async gonder(): Promise<void> {
    const oncesi = this.makine;
    this.makine = gecis(this.makine, 'GONDER', { captureId: this.bagimliliklar.kimlikUret() });
    if (this.makine === oncesi || this.sonuc === null || this.makine.captureId === null) return;
    this.yayinla();

    const yanit = await this.bagimliliklar.gonder({
      captureId: this.makine.captureId,
      hedef: this.hedef,
      sonuc: this.sonuc,
    });

    switch (yanit.sonuc) {
      case 'BASARILI':
        this.makine = gecis(this.makine, 'YANIT_BASARILI');
        break;
      case 'MUKERRER':
        this.makine = gecis(this.makine, 'YANIT_MUKERRER');
        break;
      case 'YETKI':
        this.makine = gecis(this.makine, 'YANIT_YETKI');
        break;
      default:
        this.makine = gecis(this.makine, 'YANIT_SUNUCU');
        this.duranlar = await this.bagimliliklar.duranlar();
    }

    this.yayinla();
  }

  /** Mükerrer dört seçenek (EKL-17..20). */
  public async mukerrerSecenek(secenek: MukerrerSecenegi): Promise<void> {
    switch (secenek) {
      case 'IPTAL':
        this.makine = gecis(this.makine, 'MUKERRER_IPTAL');
        this.yayinla();

        return;
      case 'BASKA_LISTEYE':
        // Kullanıcı hedefi değiştirip yeniden gönderir; kimlik KORUNUR.
        this.makine = gecis(this.makine, 'MUKERRER_IPTAL');
        this.yayinla();

        return;
      case 'MEVCUDU_GUNCELLE':
        await this.gonder();

        return;
      default:
        // MEVCUDU_AC: panelde açma işini çağıran üstlenir; durum değişmez.
        this.yayinla();
    }
  }

  /** SPA yönlendirmesi: her şey temizlenir (EKL-23). */
  public sayfaDegisti(): void {
    this.makine = gecis(this.makine, 'SAYFA_DEGISTI');
    this.sonuc = null;
    this.rapor = null;
    this.seciliVaryant = null;
    this.hedef = { ...VARSAYILAN_HEDEF };
    this.yayinla();
  }

  public kapat(): void {
    this.makine = gecis(this.makine, 'KAPAT');
    this.yayinla();
  }

  public async duranlariTazele(): Promise<void> {
    this.duranlar = await this.bagimliliklar.duranlar();
    this.yayinla();
  }
}
