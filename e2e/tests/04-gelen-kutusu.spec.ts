import { expect, test } from '@playwright/test';
import { csrfToken, girisYap, listeAc } from './yardimcilar';

/**
 * E2E-4 (İE#13 E2): sahte yakalama → Gelen Kutusu → listeye taşı → listede gör.
 *
 * Yakalama GERÇEK uçtan gider (Bearer token'lı POST /api/capture): eklenti ile panel
 * arasındaki sözleşme uçtan uca sınanır. Eklentinin KENDİSİ kapsam dışıdır (E3).
 */
test.describe('Gelen Kutusu', () => {
  test('yakalama kuyruğa düşer, listeye taşınır ve üründe görünür', async ({ page }) => {
    await girisYap(page);
    const listId = await listeAc(page, 'E2E Kuyruk Listesi');

    // ── Eklenti token'ı üret (tam değer yalnız bu yanıtta — K34) ──
    const tokenYanit = await page.request.post('/api/settings/extension-token', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
    });
    expect(tokenYanit.ok(), await tokenYanit.text()).toBeTruthy();
    const token = ((await tokenYanit.json()) as { data: { token: string } }).data.token;

    // ── Sahte yakalama (capture v2 üç blok — docs/04 §2c) ──
    const yakalama = await page.request.post('/api/capture', {
      headers: { Authorization: `Bearer ${token}` },
      data: {
        capture_id: crypto.randomUUID(),
        schema_version: 2,
        extension_version: 'e2e',
        parser_version: 'e2e',
        qty: 7,
        source: {
          platform: '1688',
          external_id: 'e2e-' + Date.now(),
          url: 'https://detail.1688.com/offer/999.html',
          captured_at: new Date().toISOString(),
        },
        raw: { title: '便携式榨汁机', normalized_attributes: { 品牌: 'E2E' } },
        normalized: {
          name: 'E2E Yakalanan Ürün',
          price_yuan: '9.90',
          images: [],
          price_tiers: [{ min_qty: 1, price_yuan: '9.90' }],
        },
      },
    });
    expect(yakalama.status(), await yakalama.text()).toBe(201);

    // ── Panel: Gelen Kutusu'nda görünür ──
    await page.goto('/panel/gelen-kutusu');
    await expect(page.getByText('E2E Yakalanan Ürün').first()).toBeVisible();

    // ── Detay çekmecesi (İE#13 B3): kaynak veriler görünür ──
    await page.getByText('E2E Yakalanan Ürün').first().click();
    await expect(page.getByRole('dialog', { name: 'Yakalama detayı' })).toBeVisible();
    await expect(page.getByText('便携式榨汁机')).toBeVisible();
    await page.getByRole('button', { name: 'Kapat' }).first().click();

    // ── Listeye taşı (İE#13 B1: seç → hedef → taşı) ──
    await page.getByLabel('Seç').first().check();
    await page.getByLabel('Hedef liste').selectOption(String(listId));
    await page.getByRole('button', { name: /Seçilenleri taşı/ }).click();

    await expect(page.getByText(/listeye taşındı/i)).toBeVisible();

    // ── Listede ürün olarak duruyor ──
    await page.goto(`/panel/listeler/${listId}`);
    // Panel ürünü hem masaüstü hem mobil düzende basar — ilk eşleşme yeterlidir.
    await expect(page.getByText('E2E Yakalanan Ürün').first()).toBeVisible();
  });
});
