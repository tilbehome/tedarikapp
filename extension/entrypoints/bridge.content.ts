/**
 * ISOLATED köprü (İE#11 C3 · İE#13 A1 · İE#21 A1 ile v2'ye geçti).
 *
 * Üç iş:
 *  1. MAIN world'den sayfa verisini ister (popup ve sayfa içi arayüz aynı okuyucuyu
 *     paylaşır),
 *  2. sayfaya v2 arayüzünü basar (satır içi düğme / pill + çekmece),
 *  3. SPA yönlendirmelerini izleyip önizlemeyi temizler (EKL-23).
 *
 * chrome.* API'si yalnız BU dünyada vardır; MAIN world script'i sayfanın
 * `window.context`ine bakar. Sayfa scriptleri bu dünyaya erişemez ve token bu
 * katmana hiç inmez (K34) — ağ işini background yapar.
 */

import { defineContentScript } from 'wxt/utils/define-content-script';

import { Disclosure } from '../core/disclosure';
import { GOMULU_SECICILER, secicileriSec } from '../core/secici';
import { fiksturdenGecer } from '../core/fiksturKapisi';
import { parse1688 } from '../modules/m1688/parser';
import { Akis, type GonderimYaniti } from '../ui/v2/akis';
import { cekmeceKur } from '../ui/v2/cekmece';
import { montajYap, montajiKaldir, offerId } from '../ui/v2/montaj';
import type { PageData, ParseResult, SelectorSet } from '../core/types';
import type { DuranKayit, HedefSecimi } from '../ui/v2/panel';

const ZAMAN_ASIMI_MS = 3000;

/** MAIN world'den sayfa verisini ister; yanıt gelmezse hata değil, ok:false döner. */
function sayfaVerisiniOku(): Promise<PageData> {
  return new Promise((resolve) => {
    const timeout = setTimeout(() => {
      window.removeEventListener('message', onMessage);
      resolve({ ok: false, error: 'Sayfa verisi zaman aşımı.' });
    }, ZAMAN_ASIMI_MS);

    const onMessage = (event: MessageEvent) => {
      const data = event.data as { type?: string; context?: unknown; dom?: Record<string, string | null>; url?: string };
      if (event.source !== window || data?.type !== 'TEDARIKAPP_CONTEXT_RESPONSE') return;
      clearTimeout(timeout);
      window.removeEventListener('message', onMessage);
      resolve({ ok: true, context: data.context, dom: data.dom, url: data.url });
    };

    window.addEventListener('message', onMessage);
    window.postMessage({ type: 'TEDARIKAPP_CONTEXT_REQUEST' }, '*');
  });
}

/**
 * Background'a mesaj (D5 düzeltmesi).
 *
 * `chrome.runtime.lastError` OKUNMALIDIR: service worker uykudayken ya da
 * yeniden yüklenirken ilk mesaj düşer ve callback yanıtsız çağrılır. Eskiden
 * bu durum "BILINMEYEN_HATA"ya dönüşüyor, üstelik konsolda "Unchecked
 * runtime.lastError" gürültüsü bırakıyordu. Popup bunu baştan beri okuyordu;
 * sayfa içi panelin okumaması iki yüzey arasındaki farkın kaynağıydı.
 */
/** Arka plan mesajı için üst süre — bundan sonrası "yanıt gelmedi" sayılır. */
const ARKAPLAN_ZAMAN_ASIMI_MS = 4000;

function arkaPlan<T>(type: string, payload?: unknown): Promise<T> {
  return new Promise((resolve, reject) => {
    // ZAMAN AŞIMI ZORUNLUDUR (rc7 D10-c saha bulgusu).
    //
    // `sendMessage` geri çağrısı BAZEN HİÇ ÇAĞRILMAZ: service worker mesajı
    // alıp yanıt yazmadan uykuya dalarsa ne yanıt gelir ne `lastError`. Söz
    // (promise) sonsuza kadar askıda kalır. Sahada sonucu şuydu: panel
    // dakikalarca "Ürün okunuyor…" ve "Bağlantı durumu bilinmiyor" gösterdi —
    // ikisi de bekleyen bir `await` yüzündendi, hata yüzünden değil.
    //
    // Artık her mesajın bir üst süresi var; süre dolarsa HATA olarak döner ve
    // yeniden deneme mekanizması devreye girer. Askıda kalan bir söz, hata
    // veren bir sözden çok daha kötüdür: kimse fark etmez.
    let bitti = false;
    const sayac = setTimeout(() => {
      if (bitti) return;
      bitti = true;
      reject(new Error('ZAMAN_ASIMI: arka plan ' + type + ' mesajına yanıt vermedi.'));
    }, ARKAPLAN_ZAMAN_ASIMI_MS);

    const bitir = (islem: () => void): void => {
      if (bitti) return;
      bitti = true;
      clearTimeout(sayac);
      islem();
    };

    chrome.runtime.sendMessage({ type, payload }, (yanit: { ok: boolean; data?: T; error?: string }) => {
      const hata = chrome.runtime.lastError;
      if (hata) return bitir(() => reject(new Error(hata.message ?? 'MESAJ_ULASMADI')));
      if (yanit?.ok === true) return bitir(() => resolve(yanit.data as T));

      return bitir(() => reject(new Error(yanit?.error ?? 'BILINMEYEN_HATA')));
    });
  });
}

/** Seçici paketi: panelden iner, fikstür kapısından geçerse kabul edilir (A6). */
async function seciciler(): Promise<SelectorSet> {
  try {
    const uzak = await arkaPlan<unknown>('SELECTORS');

    return secicileriSec(GOMULU_SECICILER, uzak, fiksturdenGecer).secilen;
  } catch {
    // Ağ yok / ayar eksik: gömülü paketle çalışmaya devam.
    return GOMULU_SECICILER;
  }
}

async function ayristir(): Promise<ParseResult> {
  const sayfa = await sayfaVerisiniOku();
  if (!sayfa.ok) throw new Error(sayfa.error ?? 'SAYFA_OKUNAMADI');

  const set = await seciciler();

  return parse1688(sayfa.context, set, sayfa.url ?? location.href, sayfa.dom ?? {});
}

function yukKur(captureId: string, hedef: HedefSecimi, sonuc: ParseResult, surum: string): Record<string, unknown> {
  return {
    capture_id: captureId,
    schema_version: 2,
    extension_version: surum,
    parser_version: sonuc.source.platform === '1688' ? '1688-2026.08.2' : 'bilinmiyor',
    target_list_id: hedef.listeId,
    qty: hedef.miktar,
    units_per_carton: null,
    note: hedef.not === '' ? null : hedef.not,
    source: sonuc.source,
    raw: sonuc.raw,
    normalized: sonuc.normalized,
  };
}

export default defineContentScript({
  matches: ['https://detail.1688.com/*'],
  main() {
    chrome.runtime.onMessage.addListener((message: { type: string }, _sender, sendResponse) => {
      if (message.type !== 'PAGE_DATA') return false;
      void sayfaVerisiniOku().then(sendResponse);

      return true; // async yanıt
    });

    const disclosure = new Disclosure({
      get: (anahtar) => chrome.storage.local.get(anahtar),
      set: (deger) => chrome.storage.local.set(deger),
    });

    // D10-NİHAİ: TEK ÖRNEK KORUMASI.
    //
    // `kur()` hem açılışta hem her SPA yönlendirmesinde çağrılır. Eskiden her
    // çağrı YENİ bir Akis + çekmece çifti üretiyordu; eski çift sökülen düğüme
    // çizmeye devam ediyor, ekrandaki çekmece ise hiç çizilmiyordu. "Kendiliğinden
    // açık boş kabuk" görüntüsünün kaynağı buydu. Artık önceki kurulum açıkça
    // sökülür ve aynı anda tek akış yaşar.
    let oncekiSokum: (() => void) | null = null;

    const kur = (): void => {
      oncekiSokum?.();
      const akis = new Akis({
        ayristir,
        gonder: async ({ captureId, hedef, sonuc }): Promise<GonderimYaniti> => {
          const surum = chrome.runtime.getManifest().version;
          try {
            const yanit = await arkaPlan<{ inbox_id?: number; product_id?: number | null; duplicate?: boolean }>(
              'CAPTURE',
              yukKur(captureId, hedef, sonuc, surum),
            );

            return yanit.duplicate === true
              ? { sonuc: 'MUKERRER', urunId: yanit.product_id ?? null }
              : { sonuc: 'BASARILI', urunId: yanit.product_id ?? null };
          } catch (hata) {
            const mesaj = hata instanceof Error ? hata.message : String(hata);

            return /401|403|AYAR_EKSIK|YETKI/i.test(mesaj)
              ? { sonuc: 'YETKI' }
              : { sonuc: 'SUNUCU', hata: mesaj };
          }
        },
        onayliMi: () => disclosure.onayliMi(),
        duranlar: async (): Promise<DuranKayit[]> => {
          try {
            return await arkaPlan<DuranKayit[]>('KUYRUK_DURANLAR');
          } catch {
            return [];
          }
        },
        // D5: hata YUTULMAZ; sınıflandırmayı ve yeniden denemeyi `core/baglanti`
        // yapar, sonucu kullanıcı şeritte görür.
        listeleriGetir: () => arkaPlan<{ id: number; name: string }[]>('LISTS'),
        kimlikUret: () => crypto.randomUUID(),
        // EKL-22: son hedef liste hatırlanır (cihaz yerelinde).
        sonListeyiOku: async () => {
          const kayit = await chrome.storage.local.get('sonHedefListe');
          const deger = kayit.sonHedefListe;

          return typeof deger === 'number' ? deger : null;
        },
        sonListeyiYaz: async (listeId) => {
          await chrome.storage.local.set({ sonHedefListe: listeId });
        },
        // EKL-13/17: kayıt panelde açılır. Adres ayarlardan gelir; token gitmez.
        paneldeAc: (urunId) => {
          void chrome.storage.local.get('panelUrl').then((kayit) => {
            const taban = typeof kayit.panelUrl === 'string' ? kayit.panelUrl.replace(/\/+$/, '') : '';
            if (taban === '') return;
            const yol = urunId === null ? '/panel/gelen-kutusu' : `/panel/urun/${urunId}`;
            window.open(taban + yol, '_blank', 'noopener');
          });
        },
        ciz: (gorunum) => cekmece.ciz(gorunum),
      });

      const cekmece = cekmeceKur({
        eylemler: {
          onTara: () => void akis.tara(),
          onGonder: () => void akis.gonder(),
          onKapat: () => akis.kapat(),
          onDevam: () => akis.devam(),
          onMukerrer: (secenek) => void akis.mukerrerSecenek(secenek),
          onHedef: (hedef) => akis.hedefDegistir(hedef),
          onVaryant: (varyant) => akis.varyantSec(varyant),
          onDisclosure: (onay) => {
            void (onay
              ? disclosure.onayla(new Date().toISOString())
              : disclosure.reddet(new Date().toISOString())
            ).then(() => akis.disclosureKarari(onay));
          },
          onPaneldeAc: () => akis.paneldeAc(),
          onBaglantiyiDene: () => void akis.baglantiyiTazele(),
          onKuyruk: (captureId, eylem) => {
            void arkaPlan('KUYRUK_EYLEM', { captureId, eylem }).then(() => akis.duranlariTazele());
          },
        },
        tetikleyici: null,
      });

      const montaj = montajYap({
        onTikla: () => {
          // rc7 EK-1 §5/§6: TIKLAMA YALNIZ AÇAR.
          //
          // Bağlantı ve okuma sayfa yüklenir yüklenmez arka planda başladı
          // (aşağıdaki `hazirla()`); tıklandığında sonuç çoğu zaman hazırdır.
          // Hazır değilse iskelet + ilerleme görünür ve dolunca kendiliğinden
          // güncellenir — kullanıcıdan elle bir şey beklenmez.
          cekmece.ciz(akis.gorunum());
          cekmece.ac();

          // Onay ekranı açıksa okuma yapılmaz (A8); onay verilince akış
          // `disclosureKarari()` üzerinden kendiliğinden sürer.
          if (akis.gorunum().disclosureGerekli) return;
          if (akis.gorunum().rapor === null) void akis.taramayiSurdur();
        },
      });

      // rc7 EK-1 §6: HAZIRLIK SAYFA YÜKLENİNCE BAŞLAR, PANEL KAPALIYKEN.
      // Panel AÇILMAZ; yalnız veriler toplanır ve çizim güncellenir.
      void akis.hazirla();

      // D5: ayarlar (panel adresi / token) sonradan girilir ya da düzeltilirse
      // sayfa içi panel bunu KENDİLİĞİNDEN görür. Eskiden yalnız açılışta bir kez
      // sorulduğu için, popup'tan token girildikten sonra bile sayfa içi panel
      // "bağlantı yok" kalıyordu.
      const ayarDinleyicisi = (
        degisiklikler: Record<string, chrome.storage.StorageChange>,
        alan: string,
      ): void => {
        if (alan !== 'local') return;
        if (!('panelUrl' in degisiklikler) && !('token' in degisiklikler)) return;
        void akis.baglantiyiTazele();
      };
      chrome.storage.onChanged.addListener(ayarDinleyicisi);

      // SPA yönlendirmesi: offer değişince önizleme temizlenir (EKL-23).
      //
      // ARALIK SIZINTISI DÜZELTMESİ (D5 turu): `kur()` her yönlendirmede yeniden
      // çağrıldığı için her seferinde YENİ bir interval açılıyordu; on yönlendirme
      // sonra sayfada on sayaç dönüyor, hepsi aynı işi tekrar tekrar yapıyordu.
      // Artık eski sayaç ve dinleyici sökülür.
      let sonOffer = offerId(location.href);
      let sayac = 0;
      oncekiSokum = (): void => {
        window.clearInterval(sayac);
        chrome.storage.onChanged.removeListener(ayarDinleyicisi);
        cekmece.kapat();
        montajiKaldir();
      };
      sayac = window.setInterval(() => {
        const simdiki = offerId(location.href);
        if (simdiki === sonOffer) return;

        sonOffer = simdiki;
        akis.sayfaDegisti();
        // Sökme işi `kur()` içindeki tek örnek korumasına bırakılır: iki yerde
        // ayrı ayrı sökmek, hangisinin çalıştığına bağlı davranış üretirdi.
        kur();
      }, 1000);

      if (montaj.tur === 'YOK') {
        // Ürün sayfası değil: hiçbir şey basılmadı, akış da başlatılmaz.
        cekmece.kapat();
      }
    };

    if (document.body !== null) {
      kur();
    } else {
      document.addEventListener('DOMContentLoaded', kur, { once: true });
    }
  },
});
