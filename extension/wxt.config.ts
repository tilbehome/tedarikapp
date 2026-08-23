import { defineConfig } from 'wxt';

/**
 * TedarikApp eklentisi (İE#11 — K53: TypeScript + WXT, MV3).
 *
 * KARAR (İE#11 C1): v1 arayüzü POPUP — akış tek atımlıktır (sayfaya gel → önizle →
 * gönder → kapan); Side Panel'in kalıcı-panel değeri V2-A "araştırma havuzu/derin
 * yakalama" ile gelecek (F36) ve WXT'de entrypoint eklemek yeterli olduğundan geçiş
 * ucuzdur. Gerekçenin tamamı İE#11 ÇIKTI RAPORU'ndadır.
 *
 * İzin ilkesi (K38 Store incelemesi): host izni YALNIZ detail.1688.com; panel adresine
 * genel bir wildcard host izni EKLENMEZ — istekler background'dan yapılır ve CORS'u
 * panel tarafı (ExtensionAuth allowlist) yönetir.
 */
export default defineConfig({
  srcDir: '.',
  outDir: 'dist',
  manifest: {
    name: 'TedarikApp — Ürün Yakalama',
    description: "Desteklenen tedarik sitelerindeki ürün sayfasından tek tıkla TedarikApp'e ürün gönderin.",
    default_locale: undefined,
    // `scripting`: İE#13 A2 — kurulum/güncelleme anında AÇIK 1688 sekmelerine
    // content script enjeksiyonu (host izni yine yalnız detail.1688.com).
    //
    // İZİN GEREKÇELERİ (İE#21 A9 · store-politika-teyidi §5 — "kanıtlanacak"):
    //  • `storage`   — panel adresi, token ve GÖNDERİLEMEMİŞ yakalama kuyruğu
    //                  yerelde saklanır; kuyruk olmadan çevrimdışı yakalama kaybolur.
    //  • `activeTab` — popup açıldığında AKTİF sekmeye mesaj gönderilir
    //                  (`chrome.tabs.query({active:true})` → popup/main.ts).
    //                  Kalıcı host iznini KOPYALAMAZ: host izni statik content
    //                  script'in enjeksiyonu için, activeTab ise kullanıcının o
    //                  anki sekmesiyle konuşmak içindir.
    //  • `scripting` — kurulum/güncelleme anında AÇIK sekmelere yeniden enjeksiyon
    //                  (İE#13 A2); bu olmadan eklenti güncellendikten sonra açık
    //                  sayfada sessizce ölür.
    //  • host_permissions — YALNIZ `detail.1688.com`. Manifest Seçenek A (A8):
    //                  statik content script, DAR eşleşme, sabit API kökeni.
    //                  Panel adresine wildcard host izni ALINMAZ; istekler
    //                  background'dan gider ve CORS'u panel tarafı yönetir.
    permissions: ['storage', 'activeTab', 'scripting'],
    host_permissions: ['https://detail.1688.com/*'],
    action: { default_title: 'TedarikApp — Ürünü yakala' },
    // Marka kiti ikonları (docs/marka/chrome). Store ikonu ayrıca 128×128 olarak
    // yayın paketinde bulunur.
    icons: {
        16: '/icon/16.png',
        32: '/icon/32.png',
        48: '/icon/48.png',
        128: '/icon/128.png',
    },
  },
});
