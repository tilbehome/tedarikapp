import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Panel build'i `public/panel/` altına çıkar (İE#8 §5).
 *
 * Çıktı repoya COMMIT EDİLMEZ (.gitignore) — release zip'i build'i içerir (docs/07 §4).
 * `base` sabit `/panel/`: Slim catch-all bu yolu index.html'e bağlar.
 */
export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: '/panel/',
  build: {
    outDir: '../public/panel',
    emptyOutDir: true,
    // Kaynak haritası üretimde kapalı: kodun tamamını dışarıya vermenin gereği yok.
    sourcemap: false,
  },
  server: {
    port: 5173,
    proxy: {
      // Geliştirmede API aynı origin'den gelsin: oturum çerezi ve CSRF böyle çalışır.
      '/api': { target: 'http://127.0.0.1:8080', changeOrigin: false },
    },
  },
});
