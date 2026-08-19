/**
 * Popup akışı (İE#11 C2): sayfa verisi → önizleme → hedef/adet/not → Panele Gönder.
 * Parser popup'ta koşar (saf fonksiyon); ağ istekleri background üzerinden.
 */

import { getSettings, saveSettings } from '../../core/api';
import { parse1688, PARSER_VERSION } from '../../modules/m1688/parser';
import type { CapturePayload, ParseResult, SelectorSet } from '../../core/types';

const $ = <T extends HTMLElement>(id: string): T => document.getElementById(id) as T;

const durum = $('durum');
let parseResult: ParseResult | null = null;

function send<T>(message: { type: string; payload?: unknown }): Promise<T> {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(message, (response: { ok: boolean; data?: T; error?: string }) => {
      if (chrome.runtime.lastError) return reject(new Error(chrome.runtime.lastError.message));
      if (!response?.ok) return reject(new Error(response?.error ?? 'Bilinmeyen hata'));
      resolve(response.data as T);
    });
  });
}

function goster(bolum: 'ayarlar' | 'yakala' | 'desteklenmiyor'): void {
  for (const id of ['ayarlar', 'yakala', 'desteklenmiyor']) {
    $(id).hidden = id !== bolum;
  }
}

async function ayarlariYukle(): Promise<void> {
  const settings = await getSettings();
  ($('panel-url') as HTMLInputElement).value = settings.panelUrl;
  ($('token') as HTMLInputElement).value = settings.token;
}

async function baglantiyiDene(): Promise<boolean> {
  try {
    await send({ type: 'LISTS' });
    durum.textContent = 'bağlı ✓';
    durum.className = 'durum ok';
    return true;
  } catch (error) {
    durum.textContent = error instanceof Error && error.message === 'AYAR_EKSIK' ? 'ayar gerekli' : 'bağlantı yok';
    durum.className = 'durum hata';
    return false;
  }
}

async function listeleriDoldur(): Promise<void> {
  const hedef = $('hedef') as HTMLSelectElement;
  try {
    const lists = await send<{ id: number; name: string; status: string }[]>({ type: 'LISTS' });
    for (const list of lists) {
      const option = document.createElement('option');
      option.value = String(list.id);
      option.textContent = list.name + (list.status === 'draft' ? '' : ` (${list.status})`);
      hedef.appendChild(option);
    }
    const yeni = document.createElement('option');
    yeni.value = 'YENI';
    yeni.textContent = '+ Hızlı yeni liste…';
    hedef.appendChild(yeni);
  } catch {
    /* liste seçici dolmasa da Gelen Kutusu yolu çalışır */
  }
}

async function sayfayiOku(): Promise<void> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab?.id || !tab.url || !/^https:\/\/detail\.1688\.com\//.test(tab.url)) {
    goster('desteklenmiyor');
    return;
  }

  const page = await new Promise<{ ok: boolean; context?: unknown; dom?: Record<string, string | null>; url?: string; error?: string }>(
    (resolve) => chrome.tabs.sendMessage(tab.id as number, { type: 'PAGE_DATA' }, resolve),
  );
  if (!page?.ok) {
    goster('desteklenmiyor');
    return;
  }

  const selectors = await send<SelectorSet>({ type: 'SELECTORS' });
  parseResult = parse1688(page.context, selectors, page.url ?? tab.url, page.dom ?? {});

  const { normalized } = parseResult;
  $('ad').textContent = normalized.name || '(ad çıkarılamadı)';
  $('fiyat').textContent =
    normalized.price_tiers.length > 1
      ? normalized.price_tiers.map((t) => `${t.min_qty}+ → ¥${t.price_yuan}`).join(' · ')
      : normalized.price_yuan
        ? `¥${normalized.price_yuan}`
        : '(fiyat çıkarılamadı)';
  $('varyasyon').textContent = normalized.sku_matrix ? `${normalized.sku_matrix.length} varyasyon` : '';
  const gorsel = $('gorsel') as HTMLImageElement;
  if (normalized.images[0]) {
    gorsel.src = normalized.images[0];
    gorsel.hidden = false;
  }
  if (!parseResult.ok) {
    $('eksik').textContent = 'Eksik alanlar: ' + parseResult.missing.join(', ') + ' — yine de gönderilebilir (kuyruğa düşer).';
    $('eksik').hidden = false;
  }
  goster('yakala');
}

async function gonder(): Promise<void> {
  if (!parseResult) return;
  const buton = $('gonder') as HTMLButtonElement;
  buton.disabled = true;
  $('sonuc').hidden = true;

  const hedefSecim = ($('hedef') as HTMLSelectElement).value;
  let targetListId: number | null = null;
  // "Hızlı yeni liste" backend'de tek çağrıyla yok — v1: panel liste ucu Bearer'a açık değil.
  // Çözüm: yeni liste adı NOT alanına işlenir ve kayıt Gelen Kutusu'na düşer (rapor: sapma).
  let note = (($('not') as HTMLInputElement).value || '').trim() || null;
  if (hedefSecim === 'YENI') {
    const yeniAd = (($('yeni-liste-adi') as HTMLInputElement).value || '').trim();
    note = ((note ?? '') + (yeniAd !== '' ? ` [yeni liste: ${yeniAd}]` : '')).trim() || null;
  } else if (hedefSecim !== '') {
    targetListId = Number.parseInt(hedefSecim, 10);
  }

  const payload: CapturePayload = {
    capture_id: crypto.randomUUID(),
    schema_version: 2,
    extension_version: chrome.runtime.getManifest().version,
    parser_version: PARSER_VERSION,
    target_list_id: targetListId,
    qty: Math.max(1, Number.parseInt(($('adet') as HTMLInputElement).value, 10) || 1),
    units_per_carton: Number.parseInt(($('koli') as HTMLInputElement).value, 10) || null,
    note,
    source: parseResult.source,
    raw: parseResult.raw,
    normalized: parseResult.normalized,
  };

  try {
    const result = await send<{ status: string; product_id: number | null; duplicate: { list_name: string } | null }>({
      type: 'CAPTURE',
      payload,
    });
    const sonuc = $('sonuc');
    sonuc.textContent =
      result.status === 'assigned'
        ? 'Ürün listeye eklendi ✓'
        : result.status === 'error'
          ? 'Gönderildi — eksik alanlar panelde tamamlanacak (Gelen Kutusu).'
          : "Gelen Kutusu'na gönderildi ✓";
    sonuc.className = 'sonuc ok';
    sonuc.hidden = false;
    if (result.duplicate) {
      $('mukerrer').textContent = `Bu ürün "${result.duplicate.list_name}" listesinde zaten var — yine de eklendi (K25).`;
      $('mukerrer').hidden = false;
    }
  } catch (error) {
    const sonuc = $('sonuc');
    sonuc.textContent = 'Gönderilemedi: ' + (error instanceof Error ? error.message : String(error));
    sonuc.className = 'sonuc hata';
    sonuc.hidden = false;
  } finally {
    buton.disabled = false;
  }
}

async function init(): Promise<void> {
  await ayarlariYukle();

  $('kaydet').addEventListener('click', () => {
    void (async () => {
      await saveSettings({
        panelUrl: ($('panel-url') as HTMLInputElement).value.trim(),
        token: ($('token') as HTMLInputElement).value.trim(),
      });
      if (await baglantiyiDene()) {
        await listeleriDoldur();
        await sayfayiOku();
      }
    })();
  });
  for (const id of ['ayar-ac', 'ayar-ac-2']) {
    $(id).addEventListener('click', () => goster('ayarlar'));
  }
  ($('hedef') as HTMLSelectElement).addEventListener('change', (event) => {
    $('yeni-liste-kutu').hidden = (event.target as HTMLSelectElement).value !== 'YENI';
  });
  $('gonder').addEventListener('click', () => void gonder());

  if (!(await baglantiyiDene())) {
    goster('ayarlar');
    return;
  }
  await listeleriDoldur();
  await sayfayiOku();
}

void init();
