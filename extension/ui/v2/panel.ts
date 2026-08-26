/**
 * EKLENTİ v2 SAYFA İÇİ PANEL (İE#21 A1 + A3 + A5 + A8).
 *
 * Onaylı mockup'ın (docs/sablon/eklenti-v2-sayfa-ici-mockup.html) çalışan hâli:
 *   · satır içi BİRİNCİL düğme (ürün detayına monte) + yedek PILL (sağ alt),
 *   · DURUM ŞERİDİ — 10 durumun her biri kendi Türkçe metniyle görünür,
 *   · ÖNİZLEME — 16+ alan, doluluk halkası, eksik uyarısı, seçilen varyant,
 *   · HEDEF — liste / miktar / not / etiketler,
 *   · MÜKERRER — dört seçenek, · KUYRUK — duran kayıtlar için rozet + üç eylem,
 *   · DISCLOSURE — onay alınmadan yakalama başlamaz.
 *
 * BU DOSYA YALNIZ ÇİZER. Karar durum makinesinde (`core/durumMakinesi`), veri
 * ayrıştırıcıda, ağ background'dadır. Böylece "arayüz bir şeyi sessizce yaptı"
 * durumu mümkün olmaz: her görünür değişimin arkasında bir geçiş vardır.
 */

import {
  DURUM_METINLERI,
  MUKERRER_SECENEKLERI,
  type Durum,
  type MakineDurumu,
  type MukerrerSecenegi,
} from '../../core/durumMakinesi';
import { DISCLOSURE_METNI } from '../../core/disclosure';
import { ALAN_ADLARI, dolulukYuzdesi, type AlanRaporu } from '../../core/alanRaporu';
import type { BaglantiDurumu } from '../../core/baglanti';

export interface HedefSecimi {
  listeId: number | null;
  miktar: number;
  not: string;
  etiketler: string[];
}

export interface DuranKayit {
  captureId: string;
  ad: string;
  sonHata: string | null;
}

export interface PanelGorunumu {
  makine: MakineDurumu;
  rapor: AlanRaporu | null;
  urunAdi: string | null;
  orijinalAd: string | null;
  varyantlar: string[];
  seciliVaryant: string | null;
  listeler: { id: number | null; ad: string }[];
  hedef: HedefSecimi;
  duranlar: DuranKayit[];
  disclosureGerekli: boolean;
  /** Gönderim/mükerrer yanıtından gelen ürün kimliği (varsa "Panelde aç"). */
  urunId?: number | null;
  /** D5: bağlantı durumu — popup ile aynı kaynaktan. */
  baglanti?: BaglantiDurumu;
  baglantiMesaj?: string;
  /**
   * rc7 EK-1 §6: otomatik hazırlık penceresi (~30 sn) sürüyor mu? Sürüyorsa
   * arayüz İLERLEME gösterir, "Yeniden dene" düğmesi ÇIKMAZ — kullanıcıdan elle
   * müdahale beklenmez.
   */
  otomatikSuruyor?: boolean;
}

export interface PanelEylemleri {
  onTara: () => void;
  onGonder: () => void;
  onKapat: () => void;
  onDevam: () => void;
  onMukerrer: (secenek: MukerrerSecenegi) => void;
  onHedef: (hedef: HedefSecimi) => void;
  onVaryant: (varyant: string) => void;
  onDisclosure: (onay: boolean) => void;
  onKuyruk: (captureId: string, eylem: 'YENIDEN' | 'DUZELT' | 'VAZGEC') => void;
  /** Paneldeki kaydı açar (başarı ve mükerrer durumlarında). */
  onPaneldeAc: () => void;
  /** Bağlantıyı yeniden dener (D5). */
  onBaglantiyiDene: () => void;
}

function el<K extends keyof HTMLElementTagNameMap>(
  etiket: K,
  sinif?: string,
  metin?: string,
): HTMLElementTagNameMap[K] {
  const dugum = document.createElement(etiket);
  if (sinif !== undefined) dugum.className = sinif;
  if (metin !== undefined) dugum.textContent = metin;

  return dugum;
}

/** Durum şeridi — hiçbir eylem sessiz çalışmaz (A1 sözü). */
export function durumSeridi(durum: Durum): HTMLElement {
  const serit = el('div', 'tdk-serit');
  serit.setAttribute('data-durum', durum);
  serit.setAttribute('role', 'status');
  serit.setAttribute('aria-live', 'polite');
  serit.append(el('span', 'nokta'), el('span', 'metin', DURUM_METINLERI[durum]));

  return serit;
}

/**
 * GÖNDER DÜĞMESİ KİLİDİ (D5) — saf kural, çizimden ayrı.
 *
 * Durum makinesi gönderime izin vermeli ve disclosure onayı alınmış olmalıdır.
 *
 * BAĞLANTI KOŞULU İNCEDİR: panele ULAŞILAMIYOR olmak gönderimi engellemez —
 * yakalama cihaz kuyruğunda bekler ve bağlanınca gider (alt bilgideki söz budur).
 * Engelleyen tek şey, kullanıcı eylemi olmadan ÇÖZÜLEMEYECEK durumlardır: ayar
 * girilmemişse gönderilecek adres yoktur, token geçersizse kuyruk yalnız aynı
 * hatayı biriktirir. D5'te düğme her bağlantısızlıkta pasifti; sebebi de
 * yazmıyordu — kullanıcı eklentiyi bozuk sanıyordu.
 *
 * Ayrı fonksiyon olması bilinçlidir: kural DOM'suz test edilebilir.
 */
export const GONDERILEBILIR_DURUMLAR = ['D3_ONIZLEME', 'D4_KISMI', 'D8_MUKERRER', 'D10_SUNUCU_HATASI'];

export const GONDERIMI_ENGELLEYEN_BAGLANTILAR = ['AYAR_EKSIK', 'YETKI'];

export function gonderDugmesiKapali(gorunum: PanelGorunumu): boolean {
  if (!GONDERILEBILIR_DURUMLAR.includes(gorunum.makine.durum)) return true;
  if (gorunum.disclosureGerekli) return true;

  return GONDERIMI_ENGELLEYEN_BAGLANTILAR.includes(gorunum.baglanti ?? 'BILINMIYOR');
}

/**
 * BAĞLANTI ŞERİDİ (D5).
 *
 * Popup "bağlı ✓" derken sayfa içi panelin sessizce bağlantısız kalması, bu
 * şeridin yokluğundandı: kullanıcı ne olduğunu göremiyordu. Şerit üç şey söyler —
 * durum, sebep ve (gerekiyorsa) ne yapılacağı.
 */
export function baglantiSeridi(
  durum: BaglantiDurumu,
  mesaj: string,
  onDene: () => void,
  otomatikSuruyor = false,
): HTMLElement | null {
  if (durum === 'BAGLI') return null;

  const serit = el('div', 'tdk-baglanti');
  serit.setAttribute('data-baglanti', otomatikSuruyor ? 'DENENIYOR' : durum);
  serit.setAttribute('role', 'status');
  // rc7 EK-1 §6: pencere sürerken kullanıcıya "bekle" denir, iş buyrulmaz.
  serit.append(el('span', 'metin', otomatikSuruyor ? 'Bağlantı kuruluyor…' : mesaj));

  if (durum !== 'DENENIYOR' && !otomatikSuruyor) {
    const dugme = el('button', undefined, 'Yeniden dene');
    dugme.type = 'button';
    dugme.setAttribute('data-eylem', 'baglanti-dene');
    dugme.addEventListener('click', onDene);
    serit.append(dugme);
  }

  return serit;
}

/** Doluluk halkası + alan listesi (A3). */
export function dolulukBolumu(rapor: AlanRaporu | null): HTMLElement {
  // D10-NİHAİ: VERİ YOKSA BOŞLUK DEĞİL İSKELET. Panel her açılışta aynı yapıyı
  // gösterir; sayfa okunana kadar alanlar gri bekler. "Boş kabuk" diye bir
  // durum yoktur — kullanıcı ne geleceğini baştan görür.
  if (rapor === null) {
    return iskeletBolumu();
  }

  const kart = el('div', 'tdk-kart');
  const ust = el('div', 'tdk-doluluk');

  const halka = el('div', 'tdk-halka');
  halka.setAttribute('role', 'img');
  halka.setAttribute('aria-label', `${rapor.dolu} / ${rapor.toplam} alan yakalandı`);
  halka.style.setProperty('--oran', String(dolulukYuzdesi(rapor)));
  halka.append(el('span', undefined, `${rapor.dolu}/${rapor.toplam}`));

  const bilgi = el('div');
  bilgi.append(
    el('div', 'tdk-ad', `${dolulukYuzdesi(rapor)} % alan dolu`),
    el(
      'div',
      'tdk-zh',
      rapor.eksikler.length === 0
        ? 'Tüm alanlar yakalandı.'
        // rc7 §4: "EKSİK" ile "SAYFADA YOK" aynı şey değildir. Kategori yolu gibi
        // alanlar oturumsuz 1688 sayfasında GERÇEKTEN bulunmaz; bunu "eksik" diye
        // raporlamak, kullanıcıya düzeltebileceği bir kusur varmış izlenimi verir.
        // Sayı aynı kalır (dolu/toplam dürüsttür), etiket düzelir.
        : `${rapor.eksikler.length} alan sayfada yok: ${rapor.eksikler.slice(0, 3).join(', ')}${rapor.eksikler.length > 3 ? '…' : ''}`,
    ),
  );

  ust.append(halka, bilgi);

  const liste = el('div', 'tdk-alanlar');
  for (const satir of rapor.satirlar) {
    const satirDugumu = el('div', satir.dolu ? 'tdk-alan' : 'tdk-alan eksik');
    // D10-b: ADRESLER TEK SATIRA KIRPILIR, tam değer `title`da durur.
    // Kırpmak veri kaybı değildir; panelin genişliğini aşmak ise kullanıcının
    // ekranını yatay kaydırmaya zorlar (saha bulgusu K2).
    const adresMi = /^https?:\/\//.test(satir.deger);
    const deger = el('span', adresMi ? 'deger tek-satir' : 'deger', satir.deger);
    if (adresMi) deger.title = satir.deger;
    satirDugumu.append(el('span', 'ad', satir.ad), deger);
    if (satir.kanal !== null) {
      satirDugumu.append(el('span', 'kanal', satir.kanal));
    }
    liste.append(satirDugumu);
  }

  kart.append(ust, liste);

  return kart;
}

/** Önizleme iskeleti: alan adları görünür, değerler "okunuyor" der (D10). */
export function iskeletBolumu(): HTMLElement {
  const kart = el('div', 'tdk-kart');
  const ust = el('div', 'tdk-doluluk');

  const halka = el('div', 'tdk-halka');
  halka.setAttribute('role', 'img');
  halka.setAttribute('aria-label', 'Alanlar okunuyor');
  halka.style.setProperty('--oran', '0');
  halka.append(el('span', undefined, `–/${ALAN_ADLARI.length}`));

  const bilgi = el('div');
  bilgi.append(
    el('div', 'tdk-ad', 'Yakalama önizlemesi'),
    el('div', 'tdk-zh', 'Sayfa okunuyor — alanlar geldikçe dolar.'),
  );
  ust.append(halka, bilgi);

  const liste = el('div', 'tdk-alanlar');
  for (const ad of ALAN_ADLARI) {
    const satir = el('div', 'tdk-alan iskelet');
    satir.append(el('span', 'ad', ad), el('span', 'deger', 'okunuyor…'));
    liste.append(satir);
  }

  kart.append(ust, liste);

  return kart;
}

/**
 * ÜRÜN KARTI (mockup `c-urun`) — HER AÇILIŞTA çizilir.
 *
 * Mockup'ta üç şey var ve üçü de bilgi taşır: orijinal Çince satır (K55),
 * Türkçe karşılık ve "TR önerisi" rozeti. Rozet olmadan kullanıcı, Türkçe adı
 * kendi yazdığı bir metin sanabilir.
 */
export function urunKarti(urunAdi: string | null, orijinalAd: string | null): HTMLElement {
  const kart = el('div', 'tdk-kart tdk-urun');

  if (urunAdi === null && orijinalAd === null) {
    kart.append(el('div', 'tdk-ad iskelet-metin', 'Ürün okunuyor…'));
    kart.append(el('div', 'tdk-zh iskelet-metin', ' '));

    return kart;
  }

  if (orijinalAd !== null) {
    // K55: orijinal satır her dilde korunur — karşı taraf kendi kaydını bulur.
    kart.append(el('div', 'tdk-zh', orijinalAd));
  }
  if (urunAdi !== null) {
    const satir = el('div', 'tdk-ad');
    satir.append(document.createTextNode(urunAdi));
    satir.append(el('span', 'tdk-oneri', 'TR önerisi'));
    kart.append(satir);
  }

  return kart;
}

/**
 * BİLGİ BANDI (mockup `c-eski`) — "bu ilan panelde yok / daha önce yakalandı".
 *
 * Kullanıcının ilk sorusu "bunu zaten göndermiş miydim?"tir. Cevabı düğmeye
 * basmadan önce vermek, mükerrer gönderimi baştan engeller.
 */
export function bilgiBandi(gorunum: PanelGorunumu): HTMLElement {
  const band = el('div', 'tdk-bilgi');
  const mukerrer = gorunum.makine.durum === 'D8_MUKERRER' || gorunum.urunId !== null;
  band.setAttribute('data-bilgi', mukerrer ? 'mukerrer' : 'yeni');
  band.append(
    el(
      'span',
      undefined,
      mukerrer
        ? 'Bu ilan panelde ZATEN VAR — göndermek kaydı tazeler.'
        : 'Bu ilan panelde yok — gönderilince yeni kayıt açılır.',
    ),
  );

  return band;
}

/** Seçilen varyant bölümü (mockup'ın renk/beden şeridinin karşılığı). */
export function varyantBolumu(
  varyantlar: string[],
  secili: string | null,
  onSec: (varyant: string) => void,
): HTMLElement | null {
  if (varyantlar.length === 0) return null;

  const kart = el('div', 'tdk-kart');
  kart.append(el('div', 'tdk-ad', 'Seçilen varyant'));

  const sarmal = el('div', 'tdk-varyant');
  for (const varyant of varyantlar.slice(0, 12)) {
    const cip = el('button', 'cip', varyant);
    cip.type = 'button';
    // D10-b: 30+ karakterlik varyant adları çipte kırpılır; tam ad title'da.
    cip.title = varyant;
    cip.setAttribute('aria-pressed', String(varyant === secili));
    cip.addEventListener('click', () => onSec(varyant));
    sarmal.append(cip);
  }

  kart.append(sarmal);
  if (secili === null) {
    kart.append(el('div', 'tdk-zh', 'Varyant seçilmezse panelde "seçim yok" görünür.'));
  }

  return kart;
}

/** Mükerrer dört seçenek (A5 · EKL-16..20). */
export function mukerrerBolumu(onSec: (secenek: MukerrerSecenegi) => void): HTMLElement {
  const kart = el('div', 'tdk-mukerrer');
  kart.append(el('h4', undefined, 'Bu ilan panelde zaten var'));

  for (const secenek of MUKERRER_SECENEKLERI) {
    const dugme = el('button', undefined, secenek.etiket);
    dugme.type = 'button';
    dugme.setAttribute('data-mukerrer', secenek.kod);
    dugme.addEventListener('click', () => onSec(secenek.kod));
    kart.append(dugme);
  }

  return kart;
}

/**
 * DURAN KAYITLAR (A5 · B11'in eklenti ikizi).
 *
 * Deneme hakkı biten kayıt SESSİZ KALMAZ: sayısı rozetle görünür, üç eylem sunulur.
 * "Sessizce kaybolan yakalama" bu projenin en pahalı hatalarından biriydi.
 */
export function kuyrukBolumu(
  duranlar: DuranKayit[],
  onEylem: (captureId: string, eylem: 'YENIDEN' | 'DUZELT' | 'VAZGEC') => void,
): HTMLElement | null {
  if (duranlar.length === 0) return null;

  const kart = el('div', 'tdk-kuyruk');
  kart.setAttribute('data-duran-sayisi', String(duranlar.length));
  kart.append(
    el('div', 'baslik', `${duranlar.length} yakalama gönderilemedi ve bekliyor`),
  );

  for (const kayit of duranlar) {
    const satir = el('div');
    satir.append(el('div', undefined, `${kayit.ad}${kayit.sonHata === null ? '' : ` — ${kayit.sonHata}`}`));

    const eylemler = el('div', 'eylemler');
    for (const [kod, etiket] of [
      ['YENIDEN', 'Yeniden dene'],
      ['DUZELT', 'Düzelt'],
      ['VAZGEC', 'Vazgeç'],
    ] as const) {
      const dugme = el('button', undefined, etiket);
      dugme.type = 'button';
      dugme.setAttribute('data-kuyruk-eylem', kod);
      dugme.addEventListener('click', () => onEylem(kayit.captureId, kod));
      eylemler.append(dugme);
    }

    satir.append(eylemler);
    kart.append(satir);
  }

  return kart;
}

/** Prominent disclosure ekranı (A8 · EKL-24). */
export function disclosureBolumu(onKarar: (onay: boolean) => void): HTMLElement {
  const kart = el('div', 'tdk-disclosure');
  kart.append(el('h4', undefined, DISCLOSURE_METNI.baslik));

  kart.append(el('div', undefined, 'Bu sayfadan okunacaklar:'));
  const toplananlar = el('ul');
  for (const madde of DISCLOSURE_METNI.toplananlar) {
    toplananlar.append(el('li', undefined, madde));
  }
  kart.append(toplananlar, el('div', undefined, DISCLOSURE_METNI.gonderilenYer));

  kart.append(el('div', 'yesil', 'Okunmayanlar:'));
  const toplanmayanlar = el('ul', 'yesil');
  for (const madde of DISCLOSURE_METNI.toplanmayanlar) {
    toplanmayanlar.append(el('li', undefined, madde));
  }
  kart.append(toplanmayanlar);

  const dugmeler = el('div', 'dugmeler');
  const onay = el('button', 'tdk-onay', DISCLOSURE_METNI.onayDugmesi);
  onay.type = 'button';
  onay.setAttribute('data-disclosure', 'onay');
  onay.addEventListener('click', () => onKarar(true));
  const red = el('button', 'tdk-red', DISCLOSURE_METNI.redDugmesi);
  red.type = 'button';
  red.setAttribute('data-disclosure', 'red');
  red.addEventListener('click', () => onKarar(false));
  dugmeler.append(onay, red);
  kart.append(dugmeler);

  return kart;
}

/** Hedef alanları: liste, miktar, not, etiketler. */
export function hedefBolumu(
  gorunum: PanelGorunumu,
  onDegis: (hedef: HedefSecimi) => void,
): HTMLElement {
  const kart = el('div', 'tdk-kart');
  const grup = el('div', 'tdk-alan-grup');
  kart.append(el('div', 'tdk-ad', 'Nereye gitsin'), grup);

  const satir2 = el('div', 'tdk-satir2');

  const listeKutusu = el('div', 'tdk-girdi');
  const listeEtiket = el('label', undefined, 'Hedef');
  listeEtiket.htmlFor = 'tdk-liste';
  const secim = el('select');
  secim.id = 'tdk-liste';
  if (gorunum.listeler.length === 0) {
    // Boş seçici "liste yok" gibi görünüyordu; artık NEDEN boş olduğunu söyler.
    const bekleme = el('option', undefined, 'Bağlantı bekleniyor…');
    bekleme.value = '';
    secim.append(bekleme);
    secim.disabled = true;
  }
  for (const liste of gorunum.listeler) {
    const secenek = el('option', undefined, liste.ad);
    secenek.value = liste.id === null ? '' : String(liste.id);
    secenek.selected = liste.id === gorunum.hedef.listeId;
    secim.append(secenek);
  }
  secim.addEventListener('change', () => {
    onDegis({ ...gorunum.hedef, listeId: secim.value === '' ? null : Number(secim.value) });
  });
  listeKutusu.append(listeEtiket, secim);

  const miktarKutusu = el('div', 'tdk-girdi');
  const miktarEtiket = el('label', undefined, 'Miktar');
  miktarEtiket.htmlFor = 'tdk-miktar';
  const miktar = el('input');
  miktar.id = 'tdk-miktar';
  miktar.type = 'number';
  miktar.min = '1';
  miktar.value = String(gorunum.hedef.miktar);
  miktar.addEventListener('change', () => {
    const deger = Number(miktar.value);
    onDegis({ ...gorunum.hedef, miktar: Number.isInteger(deger) && deger > 0 ? deger : 1 });
  });
  miktarKutusu.append(miktarEtiket, miktar);

  satir2.append(listeKutusu, miktarKutusu);

  const notKutusu = el('div', 'tdk-girdi');
  const notEtiket = el('label', undefined, 'Not');
  notEtiket.htmlFor = 'tdk-not';
  const notAlani = el('textarea');
  notAlani.id = 'tdk-not';
  notAlani.rows = 2;
  notAlani.value = gorunum.hedef.not;
  notAlani.addEventListener('change', () => onDegis({ ...gorunum.hedef, not: notAlani.value }));
  notKutusu.append(notEtiket, notAlani);

  const etiketler = el('div', 'tdk-varyant');
  for (const etiket of gorunum.hedef.etiketler) {
    const cip = el('button', 'cip', etiket);
    cip.type = 'button';
    cip.setAttribute('aria-pressed', 'true');
    cip.addEventListener('click', () => {
      onDegis({ ...gorunum.hedef, etiketler: gorunum.hedef.etiketler.filter((ad) => ad !== etiket) });
    });
    etiketler.append(cip);
  }

  grup.append(satir2, notKutusu, etiketler);

  return kart;
}

/**
 * Çekmecenin gövdesini kurar. Tüm bölümler durum makinesine göre AÇIK ya da
 * KAPALIDIR; hangi bölümün göründüğü tek bir yerden okunabilsin diye tek fonksiyon.
 */
export function panelGovdesi(gorunum: PanelGorunumu, eylemler: PanelEylemleri): HTMLElement {
  const govde = el('div', 'tdk-govde');
  govde.append(durumSeridi(gorunum.makine.durum));

  const baglanti = baglantiSeridi(
    gorunum.baglanti ?? 'BILINMIYOR',
    gorunum.baglantiMesaj ?? '',
    eylemler.onBaglantiyiDene,
    gorunum.otomatikSuruyor === true,
  );
  if (baglanti !== null && !gorunum.disclosureGerekli) govde.append(baglanti);

  if (gorunum.disclosureGerekli) {
    govde.append(disclosureBolumu(eylemler.onDisclosure));

    return govde;
  }

  const kuyruk = kuyrukBolumu(gorunum.duranlar, eylemler.onKuyruk);
  if (kuyruk !== null) govde.append(kuyruk);

  // D10-NİHAİ: ÜRÜN KARTI, BİLGİ BANDI ve ÖNİZLEME HER AÇILIŞTA ÇİZİLİR.
  // Eskiden üçü de veriye bağlıydı; veri yoksa panel yalnız "Hedef/Miktar/Not"
  // gösteriyordu ve kullanıcı bunu bozuk sanıyordu.
  govde.append(urunKarti(gorunum.urunAdi, gorunum.orijinalAd));
  govde.append(bilgiBandi(gorunum));
  govde.append(dolulukBolumu(gorunum.rapor));

  const varyant = varyantBolumu(gorunum.varyantlar, gorunum.seciliVaryant, eylemler.onVaryant);
  if (varyant !== null) govde.append(varyant);

  if (gorunum.makine.durum === 'D8_MUKERRER') {
    govde.append(mukerrerBolumu(eylemler.onMukerrer));
  }

  if (gorunum.makine.durum === 'D7_GONDERILDI') {
    // EKL-13: gönderim tamamlandı — kullanıcı kaydı panelde açabilmeli.
    const kart = el('div', 'tdk-kart');
    kart.append(el('div', 'tdk-ad', "TedarikApp'e gönderildi"));
    const ac = el('button', 'tdk-gonder', 'Panelde aç');
    ac.type = 'button';
    ac.setAttribute('data-eylem', 'panelde-ac');
    ac.addEventListener('click', eylemler.onPaneldeAc);
    kart.append(ac);
    govde.append(kart);
  }

  if (gorunum.makine.durum === 'D4_KISMI') {
    const devam = el('button', 'tdk-gonder', 'Eksiklere rağmen devam et');
    devam.type = 'button';
    devam.setAttribute('data-eylem', 'devam');
    devam.addEventListener('click', eylemler.onDevam);
    govde.append(devam);
  }

  govde.append(hedefBolumu(gorunum, eylemler.onHedef));

  return govde;
}
