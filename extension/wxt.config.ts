import { defineConfig } from 'wxt';

/**
 * Tedarikapp eklentisi (İE#11 — K53: TypeScript + WXT, MV3).
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
    permissions: ['storage', 'activeTab', 'scripting'],
    host_permissions: ['https://detail.1688.com/*'],
    action: { default_title: 'Tedarikapp — Ürünü yakala' },
  },
});
