import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

/**
 * PANEL BİRİM TESTLERİ (İE#20 C11).
 *
 * Neden ayrı dosya: `vite.config.ts` panelin ÜRETİM derlemesini tarif eder
 * (`base: '/panel/'`, `outDir`, tailwind eklentisi). Test koşumunun bunlarla
 * işi yoktur; ikisini tek dosyada tutmak, test ayarının derleme çıktısını
 * etkilemesi riskini doğurur.
 *
 * Kapsam BİLİNÇLİ OLARAK DARDIR: bu bir "her bileşeni test et" hamlesi değil,
 * K35'in "otomatik UI testi yok, ekran kabulü manueldir" kuralını kırmadan
 * DAVRANIŞI olan parçaları (token bekçileri, kancalar, kritik akış bileşenleri)
 * güvenceye almaktır. Görsel doğrulama hâlâ manuel + E2E'nin işidir.
 */
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    // CI'da sessiz takılmasın: tek test 10 sn'yi geçmez.
    testTimeout: 10000,
  },
});
