/**
 * ORTAK yakalama formu (İE#13 A1) — tarayıcı popup'ı ve sayfa içi mini panel
 * AYNI kodu çalıştırır. İki yüzeyin ayrışmaması için akış tek yerde durur:
 * bağlantı → listeler → sayfa verisi → önizleme → hedef/adet/koli/not → gönder.
 *
 * Yüzeye özgü olan her şey `CaptureFormHost` üzerinden dışarıdan verilir
 * (mesaj gönderme, sayfa verisini okuma, durum ve hata yönlendirmeleri) — bu modül
 * chrome.* API'sine doğrudan dokunmaz, böylece saf DOM olarak sınanabilir.
 */

import { parse1688, PARSER_VERSION } from '../modules/m1688/parser';
import { hasCjk, priceLabel, variationLabel } from '../modules/m1688/format';
import type { CapturePayload, PageData, ParseResult, SelectorSet } from '../core/types';
import { baglantiyiDene } from '../core/baglanti';

export interface PanelListesi {
  id: number;
  name: string;
  status: string;
}

export interface CaptureFormHost {
  /** Background service worker'a mesaj (ağ istekleri orada yapılır — token sayfaya inmez). */
  send<T>(message: { type: string; payload?: unknown }): Promise<T>;
  /** Sayfa verisi: popup aktif sekmeden, mini panel kendi sayfasından okur. */
  readPage(): Promise<PageData>;
  /** Durum metni yüzeyin kendi başlığında gösterilir. */
  onStatus(text: string, kind: 'bilgi' | 'ok' | 'hata'): void;
  /** Panel adresi/token eksik ya da geçersiz — yüzey ayar ekranına yönlendirir. */
  onNeedSettings(reason: string): void;
  /** Sayfa verisi okunamadı (desteklenmeyen sayfa / content script yok). */
  onPageUnavailable(reason: string): void;
  /** Gönderim sonrası (mini panelde paneli kapatmak için). */
  onSent?: () => void;
}

export interface CaptureFormHandle {
  element: HTMLElement;
  baslat(): Promise<void>;
}

const el = <K extends keyof HTMLElementTagNameMap>(
  tag: K,
  className?: string,
  text?: string,
): HTMLElementTagNameMap[K] => {
  const node = document.createElement(tag);
  if (className !== undefined) node.className = className;
  if (text !== undefined) node.textContent = text;

  return node;
};

const etiketli = (baslik: string, alan: HTMLElement): HTMLLabelElement => {
  const label = el('label');
  label.append(document.createTextNode(baslik), alan);

  return label;
};

export function mountCaptureForm(host: CaptureFormHost): CaptureFormHandle {
  const kok = el('div', 'tdk');

  const gorsel = el('img', 'tdk-gorsel');
  gorsel.alt = '';
  gorsel.hidden = true;
  const ad = el('p', 'tdk-ad');

  // A4 — ÇEVİRİ ÖNERİSİ (K54): metin yalnız GÖSTERİLİR; "Kullan" denmedikçe
  // hiçbir alana yazılmaz ve orijinal (Çince) başlık her koşulda korunur.
  const ceviriMetin = el('span', 'tdk-ceviri-metin');
  const ceviriKullan = el('button', 'tdk-ceviri-kullan', 'Kullan');
  ceviriKullan.type = 'button';
  const ceviri = el('div', 'tdk-ceviri');
  ceviri.hidden = true;
  ceviri.append(el('span', 'tdk-ceviri-etiket', 'Türkçe öneri:'), ceviriMetin, ceviriKullan);

  const fiyat = el('p', 'tdk-fiyat');
  const varyasyon = el('p', 'tdk-ek');
  const eksik = el('p', 'tdk-uyari');
  eksik.hidden = true;
  const mukerrer = el('p', 'tdk-uyari');
  mukerrer.hidden = true;

  const bilgi = el('div', 'tdk-bilgi');
  bilgi.append(ad, ceviri, fiyat, varyasyon, eksik, mukerrer);
  const onizleme = el('div', 'tdk-onizleme');
  onizleme.append(gorsel, bilgi);

  const hedef = el('select');
  const varsayilan = el('option', undefined, 'Gelen Kutusu (varsayılan)');
  varsayilan.value = '';
  hedef.append(varsayilan);

  const yeniListeAdi = el('input');
  yeniListeAdi.type = 'text';
  yeniListeAdi.placeholder = 'Örn. Eylül 2026';
  const yeniListeKutu = el('div');
  yeniListeKutu.hidden = true;
  yeniListeKutu.append(etiketli('Yeni liste adı', yeniListeAdi));

  const adet = el('input');
  adet.type = 'number';
  adet.min = '1';
  adet.value = '1';
  const koli = el('input');
  koli.type = 'number';
  koli.min = '1';
  koli.placeholder = '—';
  const satir = el('div', 'tdk-satir');
  satir.append(etiketli('Adet', adet), etiketli('Koli içi', koli));

  const not = el('input');
  not.type = 'text';
  not.maxLength = 500;
  not.placeholder = 'opsiyonel';

  const gonderDugmesi = el('button', undefined, 'Panele Gönder');
  gonderDugmesi.type = 'button';
  gonderDugmesi.disabled = true;
  const sonuc = el('p', 'tdk-sonuc');
  sonuc.hidden = true;

  kok.append(onizleme, etiketli('Hedef', hedef), yeniListeKutu, satir, etiketli('Not', not), gonderDugmesi, sonuc);

  let parseResult: ParseResult | null = null;
  /** Kullanıcı öneriyi kabul ettiyse gönderilecek ad — aksi hâlde null (orijinal gider). */
  let secilenAd: string | null = null;

  ceviriKullan.addEventListener('click', () => {
    const oneri = ceviriMetin.textContent ?? '';
    if (oneri === '') return;
    secilenAd = oneri;
    ad.textContent = oneri;
    ceviri.hidden = true;
  });

  hedef.addEventListener('change', () => {
    yeniListeKutu.hidden = hedef.value !== 'YENI';
  });

  async function listeleriDoldur(listeler: PanelListesi[]): Promise<void> {
    for (const liste of listeler) {
      const option = el('option', undefined, liste.name + (liste.status === 'draft' ? '' : ` (${liste.status})`));
      option.value = String(liste.id);
      hedef.append(option);
    }
    const yeni = el('option', undefined, '+ Hızlı yeni liste…');
    yeni.value = 'YENI';
    hedef.append(yeni);
  }

  function onizlemeyiCiz(sonucVerisi: ParseResult): void {
    const { normalized } = sonucVerisi;
    ad.textContent = normalized.name || '(ad çıkarılamadı)';
    fiyat.textContent = priceLabel(normalized) || '(fiyat çıkarılamadı)';
    varyasyon.textContent = variationLabel(normalized.sku_matrix);
    if (normalized.images[0] !== undefined) {
      gorsel.src = normalized.images[0];
      gorsel.hidden = false;
    }
    if (!sonucVerisi.ok) {
      eksik.textContent = `Eksik alanlar: ${sonucVerisi.missing.join(', ')} — yine de gönderilebilir (kuyruğa düşer).`;
      eksik.hidden = false;
    }
  }

  /**
   * Öneriyi arka planda ister; başarısızlık SESSİZDİR (K54: çeviri akışı bloklamaz).
   * Zaten Türkçe/latin bir başlık için dış servise hiç gidilmez.
   */
  async function ceviriOner(baslik: string): Promise<void> {
    if (baslik === '' || !hasCjk(baslik)) return;
    try {
      const yanit = await host.send<{ suggestion: string | null }>({ type: 'TRANSLATE', payload: { text: baslik } });
      if (yanit.suggestion !== null && yanit.suggestion !== '') {
        ceviriMetin.textContent = yanit.suggestion;
        ceviri.hidden = false;
      }
    } catch {
      /* öneri yok — kullanıcı orijinal adla devam eder */
    }
  }

  async function gonder(): Promise<void> {
    if (parseResult === null) return;
    gonderDugmesi.disabled = true;
    sonuc.hidden = true;

    const secim = hedef.value;
    let targetListId: number | null = null;
    // "Hızlı yeni liste" eklenti API'sinde uç istemez (K30: yeni uç açılmaz) —
    // ad NOT alanına işlenir, kayıt Gelen Kutusu'na düşer (İE#11 sapması, sürüyor).
    let note = (not.value || '').trim() || null;
    if (secim === 'YENI') {
      const yeniAd = (yeniListeAdi.value || '').trim();
      note = ((note ?? '') + (yeniAd !== '' ? ` [yeni liste: ${yeniAd}]` : '')).trim() || null;
    } else if (secim !== '') {
      targetListId = Number.parseInt(secim, 10);
    }

    const payload: CapturePayload = {
      capture_id: crypto.randomUUID(),
      schema_version: 2,
      extension_version: chrome.runtime.getManifest().version,
      parser_version: PARSER_VERSION,
      target_list_id: targetListId,
      qty: Math.max(1, Number.parseInt(adet.value, 10) || 1),
      units_per_carton: Number.parseInt(koli.value, 10) || null,
      note,
      source: parseResult.source,
      raw: parseResult.raw,
      // K54: yalnız kullanıcı "Kullan" dediyse Türkçe ad gider; RAW başlık (Çince)
      // parseResult.raw içinde AYNEN durur — orijinal asla değişmez.
      normalized: secilenAd === null ? parseResult.normalized : { ...parseResult.normalized, name: secilenAd },
    };

    try {
      const yanit = await host.send<{
        status: string;
        product_id: number | null;
        duplicate: { list_name: string } | null;
      }>({ type: 'CAPTURE', payload });

      sonuc.textContent =
        yanit.status === 'assigned'
          ? 'Ürün listeye eklendi ✓'
          : yanit.status === 'error'
            ? 'Gönderildi — eksik alanlar panelde tamamlanacak (Gelen Kutusu).'
            : "Gelen Kutusu'na gönderildi ✓";
      sonuc.className = 'tdk-sonuc ok';
      sonuc.hidden = false;
      if (yanit.duplicate !== null) {
        mukerrer.textContent = `Bu ürün "${yanit.duplicate.list_name}" listesinde zaten var — yine de eklendi (K25).`;
        mukerrer.hidden = false;
      }
      host.onSent?.();
    } catch (hata) {
      sonuc.textContent = 'Gönderilemedi: ' + (hata instanceof Error ? hata.message : String(hata));
      sonuc.className = 'tdk-sonuc hata';
      sonuc.hidden = false;
    } finally {
      gonderDugmesi.disabled = false;
    }
  }

  gonderDugmesi.addEventListener('click', () => void gonder());

  async function baslat(): Promise<void> {
    host.onStatus('bağlantı denetleniyor…', 'bilgi');

    // D5: popup ve sayfa içi panel bağlantıyı AYNI modülden sorar; sınıflandırma
    // ve cümleler tek yerde durur, iki yüzey bir daha ayrışmaz.
    const baglanti = await baglantiyiDene<PanelListesi>({
      listeleriGetir: () => host.send<PanelListesi[]>({ type: 'LISTS' }),
    });

    if (baglanti.durum !== 'BAGLI') {
      host.onStatus(baglanti.durum === 'AYAR_EKSIK' ? 'ayar gerekli' : 'bağlantı yok', 'hata');
      host.onNeedSettings(baglanti.mesaj);

      return;
    }

    host.onStatus('bağlı ✓', 'ok');
    await listeleriDoldur(baglanti.ham);

    const sayfa = await host.readPage();
    if (!sayfa.ok) {
      host.onPageUnavailable(sayfa.error ?? 'Sayfa verisi okunamadı.');

      return;
    }

    const selectors = await host.send<SelectorSet>({ type: 'SELECTORS' });
    parseResult = parse1688(sayfa.context, selectors, sayfa.url ?? '', sayfa.dom ?? {});
    onizlemeyiCiz(parseResult);
    gonderDugmesi.disabled = false;
    void ceviriOner(parseResult.normalized.name);
  }

  return { element: kok, baslat };
}
