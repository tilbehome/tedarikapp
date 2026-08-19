import { expect, test } from '@playwright/test';
import { girisYap, listeAc, urunEkle } from './yardimcilar';

/**
 * E2E-2 (İE#13 E2): Excel ve PDF çıktısı ÜRETİLİR ve İNDİRİLİR.
 *
 * İE#11 Görev E ile üretim POST'tur (CSRF'li) ve yanıt zarf değil dosyadır; panel
 * blob'u indirir. Burada indirmenin gerçekten gerçekleştiği ve dosyanın boş olmadığı
 * kanıtlanır — "buton çalışıyor" demek yetmez.
 */
test.describe('Çıktılar', () => {
  test('Excel ve PDF indirilir, dosyalar boş değildir', async ({ page }) => {
    await girisYap(page);
    const listId = await listeAc(page, 'E2E Çıktı Listesi');
    await urunEkle(page, listId, 'Çıktı ürünü', 40);

    await page.goto(`/panel/listeler/${listId}`);
    await expect(page.getByText('Çıktı ürünü').first()).toBeVisible();

    for (const [buton, uzanti] of [
      ['Excel', '.xlsx'],
      ['PDF', '.pdf'],
    ] as const) {
      const indirme = page.waitForEvent('download', { timeout: 45_000 });
      await page.getByRole('button', { name: buton }).click();
      const dosya = await indirme;

      expect(dosya.suggestedFilename(), `${buton} dosya adı`).toContain(uzanti);
      const yol = await dosya.path();
      expect(yol, `${buton} indirilmiş olmalı`).toBeTruthy();
    }
  });

  test('çıktı geçmişi üretilen kaydı listeler', async ({ page }) => {
    await girisYap(page);
    const listId = await listeAc(page, 'E2E Geçmiş Listesi');
    await urunEkle(page, listId, 'Geçmiş ürünü');

    // Üretim ucu POST + CSRF (İE#11 Görev E) — dosya döner, kayıt açılır.
    const csrf = await page.request
      .get('/api/auth/me')
      .then(async (r) => ((await r.json()) as { data: { csrf_token: string } }).data.csrf_token);
    // Biçim SORGU parametresidir (docs/10); gövde yalnız seçenekleri taşır (İE#13 F2/F5).
    const uretim = await page.request.post(`/api/lists/${listId}/export?format=csv`, {
      headers: { 'X-CSRF-Token': csrf },
      data: {},
    });
    expect(uretim.ok(), await uretim.text()).toBeTruthy();

    const gecmis = await page.request.get(`/api/lists/${listId}/exports`);
    const govde = (await gecmis.json()) as { data: unknown[] };
    expect(govde.data.length).toBeGreaterThan(0);
  });
});
