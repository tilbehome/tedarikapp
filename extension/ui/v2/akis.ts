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
import {
  DENEME_ARALIKLARI,
  baglantiMesaji,
  baglantiyiDene,
  type BaglantiDurumu,
} from '../../core/baglanti';
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
  /**
   * Panelden hedef listeleri ÇEKER ve hata FIRLATIR.
   *
   * D5: eskiden burada hata yutulup boş dizi dönüyordu; bağlantı yokluğu ile
   * "liste yok" ayırt edilemiyordu. Artık sınıflandırmayı `core/baglanti` yapar.
   */
  listeleriGetir: () => Promise<{ id: number; name: string }[]>;
  /** Yeni yakalama kimliği (UUID). */
  kimlikUret: () => string;
  /** Son seçilen hedef listeyi hatırlar (EKL-22). */
  sonListeyiOku: () => Promise<number | null>;
  sonListeyiYaz: (listeId: number | null) => Promise<void>;
  /** Paneldeki ürünü/kaydı tarayıcıda açar (EKL-13/17). */
  paneldeAc: (urunId: number | null) => void;
  /** Görünüm değişince çağrılır — arayüz kendini yeniden çizer. */
  ciz: (gorunum: PanelGorunumu) => void;
  /** Yeniden denemeler arasındaki bekleme (testte anında döner). */
  bekle?: (ms: number) => Promise<void>;
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

  /** D5: bağlantı popup ile AYNI kaynaktan okunur ve ekranda görünür. */
  private baglanti: BaglantiDurumu = 'BILINMIYOR';

  private baglantiMesaj = baglantiMesaji('BILINMIYOR');

  /**
   * OTOMATİK HAZIRLIK SÜRÜYOR MU? (rc7 EK-1 §6)
   *
   * Kullanıcı hiçbir üründe elle "Yeniden dene"ye basmamalı. Otomatik pencere
   * (~30 sn) açıkken arayüz İLERLEME gösterir; düğme ancak pencere gerçekten
   * tükenince çıkar.
   */
  private otomatikSuruyor = false;

  /** Gönderim/mükerrer yanıtından gelen ürün kimliği — "Panelde aç" bunu kullanır. */
  private urunId: number | null = null;

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
      urunId: this.urunId,
      baglanti: this.baglanti,
      baglantiMesaj: this.baglantiMesaj,
      otomatikSuruyor: this.otomatikSuruyor,
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

  /**
   * Panel açılışı. ÖNCE ÇİZ, sonra yükle: kullanıcı boş bir kabuk görmemeli
   * (D5'te panel, veriler gelene kadar bağlantısız görünüyordu).
   */
  public async ac(): Promise<void> {
    this.yayinla();

    this.disclosureGerekli = !(await this.bagimliliklar.onayliMi().catch(() => false));
    // Duran kayıt okunamazsa panel açılmayı sürdürür: kuyruk rozetinin yokluğu,
    // bağlantı şeridinin söylediği şeyi ikinci kez söylemesin.
    this.duranlar = await this.bagimliliklar.duranlar().catch(() => []);
    this.yayinla();

    // Bağlantı denemesi BEKLENMEZ: tarama onu beklerse, panele ulaşılamayan
    // kullanıcı önizlemeyi saniyelerce geç görür. Sonuç geldiğinde şerit ve
    // hedef listesi kendiliğinden tazelenir.
    if (!this.disclosureGerekli) {
      void this.baglantiyiTazele();
    }
  }

  /**
   * BAĞLANTIYI DENE (D5): sonuç ne olursa olsun EKRANA yazılır.
   *
   * Geçici hatada tekrar denenir (`core/baglanti`); bağlanınca hedef listesi
   * kendiliğinden dolar ve son seçim geri gelir. Kullanıcının "yeniden dene"ye
   * basması gerekmez — basmak isterse düğme de vardır.
   */
  public async baglantiyiTazele(): Promise<void> {
    this.baglanti = 'DENENIYOR';
    this.baglantiMesaj = baglantiMesaji('DENENIYOR');
    this.otomatikSuruyor = true;
    this.yayinla();

    const sonuc = await baglantiyiDene({
      listeleriGetir: this.bagimliliklar.listeleriGetir,
      bekle: this.bagimliliklar.bekle,
    });
    this.baglanti = sonuc.durum;
    this.baglantiMesaj = sonuc.mesaj;

    if (sonuc.durum === 'BAGLI') {
      this.listeler = sonuc.listeler;
      // EKL-22: son seçim hatırlanır — aynı listeye 30 ürün ekleyen kullanıcı
      // listeyi 30 kez seçmemeli.
      const sonListe = await this.bagimliliklar.sonListeyiOku();
      if (this.listeler.some((liste) => liste.id === sonListe)) {
        this.hedef = { ...this.hedef, listeId: sonListe };
      }
    }

    // Pencere bitti: bundan sonrası kullanıcının kararı ("Yeniden dene").
    this.otomatikSuruyor = false;
    this.yayinla();
  }

  /**
   * SAYFA YÜKLENİR YÜKLENMEZ HAZIRLA (rc7 EK-1 §6) — panel KAPALIYKEN çalışır.
   *
   * Kullanıcı düğmeye bastığında sonuç çoğu zaman HAZIR olsun diye bağlantı ve
   * okuma arka planda başlar. Sahada tersi yaşandı: her şey tıklamadan sonra
   * başlıyor, kullanıcı önce boş panele sonra dakikalarca "okunuyor…"a bakıyordu.
   *
   * Çekmece BURADA AÇILMAZ; yalnız çizilir. Açma kararı kullanıcınındır (EK-1 §5).
   */
  public async hazirla(): Promise<void> {
    this.disclosureGerekli = !(await this.bagimliliklar.onayliMi().catch(() => false));
    this.yayinla();

    // KUYRUK ROZETİ KRİTİK YOLDA DEĞİLDİR: duran kayıt sorgusu gecikirse ya da
    // hiç dönmezse bağlantı ve okuma BEKLEMEZ. Sahada (K3) iki iş birbirini
    // bekliyordu; ikincil bir bilgi, birincil akışı rehin alamaz.
    void this.bagimliliklar
      .duranlar()
      .then((duranlar) => {
        this.duranlar = duranlar;
        this.yayinla();
      })
      .catch(() => {
        /* duran kayıt okunamadı: rozet çıkmaz, akış sürer. */
      });

    if (this.disclosureGerekli) return; // A8: onay yoksa sayfa OKUNMAZ.

    // İkisi paralel: bağlantı yokken de sayfa okunur (D5 dersi), okuma
    // beklerken de bağlantı kurulur.
    await Promise.all([this.baglantiyiTazele(), this.taramayiSurdur()]);
  }

  /**
   * OKUMAYI SÜRDÜR (rc7 D10-c) — 1688'in veri bloğu geç gelebilir.
   *
   * Sayfa açılır açılmaz okunan gömülü veri bazen henüz yazılmamış olur; tek
   * denemede "okuma hatası" demek, saniyeler sonra hazır olacak bir sayfayı
   * kalıcı olarak kusurlu ilan etmektir. Üstel geri çekilmeyle ~30 sn denenir.
   */
  public async taramayiSurdur(): Promise<void> {
    this.otomatikSuruyor = true;

    for (let deneme = 0; deneme <= DENEME_ARALIKLARI.length; deneme++) {
      await this.tara();
      if (this.rapor !== null && this.makine.durum !== 'D5_OKUMA_HATASI') {
        this.otomatikSuruyor = false;
        this.yayinla();

        return;
      }

      const aralik = DENEME_ARALIKLARI[deneme];
      if (aralik === undefined) break;
      await this.bekle(aralik);
      // Yeniden denemek için makineyi hazır konuma al; aksi hâlde `tara()`
      // "ikinci tık" sayıp hiçbir şey yapmaz.
      this.makine = baslangicDurumu();
    }

    this.otomatikSuruyor = false;
    this.yayinla();
  }

  private bekle(ms: number): Promise<void> {
    const bekleyici = this.bagimliliklar.bekle;

    return bekleyici === undefined ? new Promise<void>((r) => setTimeout(r, ms)) : bekleyici(ms);
  }

  /**
   * Gönderim mümkün mü? Ulaşılamıyorsa MÜMKÜNDÜR — kuyruk devreye girer.
   * Ayar/token eksikse değildir: kuyruk aynı hatayı biriktirmekten başka bir şey
   * yapamaz.
   */
  public gonderilebilirMi(): boolean {
    return this.baglanti !== 'AYAR_EKSIK' && this.baglanti !== 'YETKI';
  }

  public async disclosureKarari(onay: boolean): Promise<void> {
    this.disclosureGerekli = !onay;
    if (onay) {
      await this.baglantiyiTazele();
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
    const listeDegisti = hedef.listeId !== this.hedef.listeId;
    this.hedef = hedef;
    if (listeDegisti) void this.bagimliliklar.sonListeyiYaz(hedef.listeId);
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
        this.urunId = yanit.urunId;
        this.makine = gecis(this.makine, 'YANIT_BASARILI');
        break;
      case 'MUKERRER':
        this.urunId = yanit.urunId;
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
        // Önizlemeye dönülür ki kullanıcı hedefi değiştirebilsin; kimlik KORUNUR,
        // yani yeni gönderim aynı yakalamanın devamıdır (idempotens).
        this.makine = gecis(this.makine, 'MUKERRER_IPTAL');
        this.yayinla();

        return;
      case 'MEVCUDU_GUNCELLE':
        await this.gonder();

        return;
      default:
        // MEVCUDU_AC: mevcut kayıt panelde açılır; yakalama durumu DEĞİŞMEZ —
        // kullanıcı sekmeye bakıp geri dönebilmeli.
        this.bagimliliklar.paneldeAc(this.urunId);
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

  /** Başarılı gönderimden sonra kaydı panelde açar (EKL-13). */
  public paneldeAc(): void {
    this.bagimliliklar.paneldeAc(this.urunId);
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
