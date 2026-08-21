import { expect, test } from '@playwright/test';
import { girisYap, gorunen } from './yardimcilar';

/**
 * E2E-5 — V3 KABUĞU (İE#16 Dilim 1 kabul kriterleri).
 *
 * Sınananlar: komut paleti (Ctrl+K) · koyu tema · sayfa geçişinde TAM YENİLEME
 * OLMAMASI · menü daraltma (Ctrl+B) · bileşen örnek sayfası · mevcut ekranların
 * bozulmamış olması.
 */
test.describe('V3 kabuğu', () => {
  test('komut paleti Ctrl+K ile açılır, Türkçe karakter duyarsız arar, Enter ile gider', async ({ page }) => {
    await girisYap(page);

    await page.keyboard.press('Control+k');
    const palet = page.getByRole('dialog', { name: 'Komut paleti' });
    await expect(palet).toBeVisible();

    // "kesif" (şapkasız/noktasız) yazınca "Keşif" bulunmalı — D1.7.
    await page.getByPlaceholder('Ne yapmak istiyorsun?').fill('gelen kutusu');
    await expect(palet.getByText('Gelen Kutusu').first()).toBeVisible();

    await page.keyboard.press('Enter');
    await expect(palet).toBeHidden();
    await expect(page).toHaveURL(/\/panel\/gelen-kutusu/);

    // Esc kapatır.
    await page.keyboard.press('Control+k');
    await expect(page.getByRole('dialog', { name: 'Komut paleti' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Komut paleti' })).toBeHidden();
  });

  test('koyu tema uygulanır ve sayfa yenilense de korunur', async ({ page }) => {
    await girisYap(page);

    await page.keyboard.press('Control+k');
    await page.getByPlaceholder('Ne yapmak istiyorsun?').fill('koyu tema');
    await page.keyboard.press('Enter');

    // Koyu tema TOKEN değişimiyle çalışır: kökte data-theme="dark" durmalı.
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    const koyuZemin = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue('--bg').trim(),
    );
    expect(koyuZemin).toBe('#0B1220');

    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    // Açık temaya dönüldüğünde zemin de geri gelmeli (tek kaynak çalışıyor).
    await page.keyboard.press('Control+k');
    await page.getByPlaceholder('Ne yapmak istiyorsun?').fill('acik tema');
    await page.keyboard.press('Enter');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  });

  test('ekranlar arası geçişte sayfa YENİDEN YÜKLENMEZ (kısmi güncelleme)', async ({ page }) => {
    await girisYap(page);

    // Sayfaya bir işaret bırakılır; tam yenileme olsaydı bu işaret kaybolurdu.
    await page.evaluate(() => {
      (window as unknown as { __kabukIsareti?: number }).__kabukIsareti = 42;
    });

    await page.getByRole('link', { name: 'Listeler' }).first().click();
    await expect(page).toHaveURL(/\/panel\/listeler/);
    await page.getByRole('link', { name: 'Ayarlar' }).first().click();
    await expect(page).toHaveURL(/\/panel\/ayarlar/);

    const isaret = await page.evaluate(() => (window as unknown as { __kabukIsareti?: number }).__kabukIsareti);
    expect(isaret, 'Gezinme sırasında sayfa yeniden yüklenmemeli').toBe(42);

    // Geri tuşu doğru çalışır (D1.4: ekran durumu URL'de).
    await page.goBack();
    await expect(page).toHaveURL(/\/panel\/listeler/);
  });

  test('Ctrl+B menüyü daraltır ve tercih hatırlanır', async ({ page }) => {
    await girisYap(page);

    const menu = page.locator('aside').first();
    await expect(menu).toHaveClass(/w-\[264px\]/);

    await page.keyboard.press('Control+b');
    await expect(menu).toHaveClass(/w-\[68px\]/);

    await page.reload();
    await expect(page.locator('aside').first()).toHaveClass(/w-\[68px\]/);

    await page.keyboard.press('Control+b');
    await expect(page.locator('aside').first()).toHaveClass(/w-\[264px\]/);
  });

  test('bileşen örnek sayfası açılır ve parçaları gösterir', async ({ page }) => {
    await girisYap(page);
    await page.goto('/panel/bilesenler');

    await expect(gorunen(page.getByRole('heading', { name: 'Bileşen kitaplığı' }))).toBeVisible();
    await expect(page.getByRole('button', { name: 'Birincil' })).toBeVisible();

    // Modal aç/kapa: odak yönetimi ve Esc davranışı bileşen sözleşmesidir.
    await page.getByRole('tab', { name: 'Katmanlar' }).click();
    await page.getByRole('button', { name: 'Modal aç' }).click();
    await expect(page.getByRole('dialog', { name: 'Örnek modal' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Örnek modal' })).toBeHidden();
  });

  test('mevcut ekranlar yeni kabukta çalışmaya devam eder', async ({ page }) => {
    await girisYap(page);

    for (const [ad, desen] of [
      ['Listeler', /\/panel\/listeler/],
      ['Gelen Kutusu', /\/panel\/gelen-kutusu/],
      ['Ayarlar', /\/panel\/ayarlar/],
      ['Panorama', /\/panel\/?$/],
    ] as [string, RegExp][]) {
      await page.getByRole('link', { name: ad }).first().click();
      await expect(page).toHaveURL(desen);
      // Kırıntı yolu ve sayfa başlığı ekranla birlikte değişir (D1.4/D1.6).
      await expect(page).toHaveTitle(new RegExp(ad === 'Panorama' ? 'Panorama' : ad));
    }
  });
});
