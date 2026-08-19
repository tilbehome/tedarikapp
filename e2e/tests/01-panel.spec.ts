import { expect, test } from '@playwright/test';
import { girisYap, gorunen, KULLANICI } from './yardimcilar';

/**
 * E2E-1 (İE#13 E2): giriş (2FA'sız) → liste oluştur → ürün ekle → durum ilerlet.
 * Tarayıcıdan gerçek panelle konuşur; veritabanı CI'da gerçek MySQL'dir.
 */
test.describe('Panel temel akışı', () => {
  test('yanlış şifre girişi reddeder', async ({ page }) => {
    await page.goto('/panel');
    await page.getByLabel('E-posta', { exact: true }).fill(KULLANICI.email);
    await page.getByLabel('Şifre', { exact: true }).fill('yanlis-sifre-123');
    await page.getByRole('button', { name: 'Panele gir' }).click();

    await expect(gorunen(page.getByText(/hatalı|geçersiz|başarısız/i))).toBeVisible();
  });

  test('giriş → liste oluştur → ürün ekle → liste durumunu ilerlet', async ({ page }) => {
    await girisYap(page);

    // ── Liste oluştur (arayüzden) ──
    await page.getByRole('link', { name: 'Listeler' }).click();
    await page.getByRole('button', { name: 'Yeni liste' }).click();
    await page.getByLabel('Liste adı').fill('E2E Listesi');
    await page.getByLabel('Dönem').fill('2026 Sonbahar');
    await page.getByRole('button', { name: 'Oluştur' }).first().click();

    await expect(gorunen(page.getByText('E2E Listesi'))).toBeVisible();

    // ── Ürün ekle (arayüzden) ──
    await gorunen(page.getByText('E2E Listesi')).click();
    await page.getByRole('link', { name: 'Ürün ekle' }).first().click();
    await page.getByLabel('Ürün adı').fill('E2E Ürünü');
    await page.getByLabel('Adet', { exact: true }).fill('25');
    await page.getByLabel('Birim fiyat (¥)').fill('12,50');
    await page.getByRole('button', { name: /Kaydet|Oluştur/ }).click();

    await expect(gorunen(page.getByText('E2E Ürünü'))).toBeVisible();

    // ── Liste durumunu ilerlet: Taslak → İletildi (K48: kur BU ANDA kilitlenir) ──
    await expect(page.getByText('Liste durumunu ilerlet:')).toBeVisible();
    await page.getByRole('button', { name: 'İletildi' }).click();

    await expect(gorunen(page.getByText('İletildi'))).toBeVisible();
  });
});
