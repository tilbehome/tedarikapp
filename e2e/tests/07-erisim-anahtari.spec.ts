import { expect, test } from '@playwright/test';
import { csrfToken, girisYap, gorunen } from './yardimcilar';

/**
 * E2E-7 — ERİŞİM ANAHTARI KAPISI (İE#18 Görev 6 · K62).
 *
 * Akış: kilit ekranı → 6 haneli anahtar → reveal. Girişsiz bir tarayıcı
 * bağlamında koşar; firmanın gördüğü şey tam olarak budur.
 */
test.describe('Erişim anahtarı', () => {
  test('kilit ekranı → anahtar → liste açılır', async ({ page, browser }) => {
    await girisYap(page);

    const liste = await page.request.post('/api/lists', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'E2E Anahtar Listesi', supplier_name: 'Ningbo Test Co.' },
    });
    const listId = ((await liste.json()) as { data: { id: number } }).data.id;

    await page.request.post(`/api/lists/${listId}/products`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'Anahtarla Görünen Ürün', qty: 12, price_yuan: '9.90' },
    });

    // Panelden anahtarı oku (firmaya elden iletilecek değer).
    const anahtarYanit = await page.request.get(`/api/lists/${listId}/share-key`);
    const anahtar = ((await anahtarYanit.json()) as { data: { key: string } }).data.key;
    expect(anahtar).toMatch(/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/);

    const paylasim = await page.request.post(`/api/lists/${listId}/share`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
    });
    const url = ((await paylasim.json()) as { data: { share_url: string } }).data.share_url;
    expect(url, 'Kanonik ön ek /liste olmalı (İE#18 G5)').toContain('/liste/');

    // ── Girişsiz bağlam: firmanın gördüğü hâl ──
    const misafir = await browser.newContext();
    const sayfa = await misafir.newPage();
    await sayfa.goto(url);

    // KİLİT EKRANI: liste verisi YOK, yalnız ad + firma.
    await expect(gorunen(sayfa.getByText('E2E Anahtar Listesi'))).toBeVisible();
    await expect(gorunen(sayfa.getByText('Ningbo Test Co.'))).toBeVisible();
    await expect(sayfa.getByText('Anahtarla Görünen Ürün')).toHaveCount(0);

    const haneler = sayfa.locator('.kis-hane');
    await expect(haneler).toHaveCount(6);

    // YANLIŞ anahtar: sallanma + hata, liste hâlâ yok.
    await haneler.first().click();
    await sayfa.keyboard.type('ZZZZZZ');
    // Form gönderimi bir GEZİNMEDİR: yanıtın KENDİSİ beklenir, böylece hata
    // durumunda raporda durum kodu görünür (körlemesine "görünmedi" demez).
    const [yanlisYanit] = await Promise.all([
      sayfa.waitForResponse((y) => y.url().includes('/anahtar') && y.request().method() === 'POST'),
      sayfa.getByRole('button', { name: 'Görüntüle' }).click(),
    ]);
    expect(yanlisYanit.status(), 'Yanlış anahtar 401 dönmeli').toBe(401);
    await expect(sayfa.locator('[data-anahtar-hata]')).toBeVisible();
    await expect(sayfa.getByText('Anahtarla Görünen Ürün')).toHaveCount(0);

    // DOĞRU anahtar: otomatik ilerleyen kutulara yazılır, liste açılır.
    const yeniHaneler = sayfa.locator('.kis-hane');
    await yeniHaneler.first().click();
    await sayfa.keyboard.type(anahtar);
    const [dogruYanit] = await Promise.all([
      sayfa.waitForResponse((y) => y.url().includes('/anahtar') && y.request().method() === 'POST'),
      sayfa.getByRole('button', { name: 'Görüntüle' }).click(),
    ]);
    expect(dogruYanit.status(), 'Doğru anahtar 303 ile yönlendirmeli').toBe(303);

    await expect(gorunen(sayfa.getByText('Anahtarla Görünen Ürün'))).toBeVisible({ timeout: 15_000 });
    await expect(sayfa.locator('.kis-hane')).toHaveCount(0);

    // Çerez 12 saat geçerli: yenilemede tekrar anahtar sorulmaz.
    await sayfa.reload();
    await expect(gorunen(sayfa.getByText('Anahtarla Görünen Ürün'))).toBeVisible();

    await misafir.close();
  });

  test('yapıştırma ile anahtar kutulara dağılır', async ({ page, browser }) => {
    await girisYap(page);

    const liste = await page.request.post('/api/lists', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'E2E Yapıştırma Listesi' },
    });
    const listId = ((await liste.json()) as { data: { id: number } }).data.id;
    await page.request.post(`/api/lists/${listId}/products`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'Yapıştırma ürünü', qty: 3, price_yuan: '4.00' },
    });

    const anahtar = ((await (await page.request.get(`/api/lists/${listId}/share-key`)).json()) as {
      data: { key: string };
    }).data.key;
    const url = ((await (
      await page.request.post(`/api/lists/${listId}/share`, {
        headers: { 'X-CSRF-Token': await csrfToken(page) },
      })
    ).json()) as { data: { share_url: string } }).data.share_url;

    const misafir = await browser.newContext();
    const sayfa = await misafir.newPage();
    await sayfa.goto(url);

    // Panoyu doldurup ilk kutuya yapıştır: altı haneye dağılmalı.
    await sayfa.evaluate((deger) => navigator.clipboard.writeText(deger), anahtar).catch(() => undefined);
    await sayfa.locator('.kis-hane').first().click();
    await sayfa.locator('.kis-hane').first().evaluate((element, deger) => {
      const olay = new ClipboardEvent('paste', { clipboardData: new DataTransfer(), bubbles: true, cancelable: true });
      olay.clipboardData?.setData('text', deger);
      element.dispatchEvent(olay);
    }, anahtar);

    await expect(sayfa.locator('.kis-hane').nth(5)).toHaveValue(anahtar[5]);

    await Promise.all([
      sayfa.waitForLoadState('domcontentloaded'),
      sayfa.getByRole('button', { name: 'Görüntüle' }).click(),
    ]);
    await expect(gorunen(sayfa.getByText('Yapıştırma ürünü'))).toBeVisible({ timeout: 15_000 });

    await misafir.close();
  });
});
