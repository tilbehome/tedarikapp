import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright yapılandırması (İE#13 Blok E — F22).
 *
 * Sunucuyu BU YAPILANDIRMA BAŞLATMAZ: CI, MySQL servisi + migrate + kullanıcı
 * tohumlaması yaptıktan sonra `php -S ... e2e/router.php` ile ayağa kaldırır ve
 * adresi `E2E_BASE_URL` ile verir. Böylece testler "hazır bir kuruluma" bakar —
 * gerçek dağıtım da öyledir (SQLite değil, gerçek MySQL — İE#13 E1).
 *
 * Tek tarayıcı (Chromium): panel Chrome hedefli bir iç araçtır (K35); çapraz
 * tarayıcı matrisi bu fazın konusu değildir.
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: false, // tek veritabanı — senaryolar birbirinin verisini bozmasın
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8099',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    locale: 'tr-TR',
    timezoneId: 'Europe/Istanbul',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
