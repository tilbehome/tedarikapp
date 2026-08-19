import { expect, type Page } from '@playwright/test';

/**
 * E2E ortak yardımcıları (İE#13 Blok E).
 * Kullanıcı CI'da `bin/user-create.php --no-totp` ile tohumlanır: 2FA'sız akış (K45).
 */
export const KULLANICI = {
  email: process.env.E2E_EMAIL ?? 'e2e@tedarikapp.test',
  password: process.env.E2E_PASSWORD ?? 'e2e-cok-gizli-sifre',
};

/** Panele giriş yapar ve ana ekranın açıldığını doğrular. */
export async function girisYap(page: Page): Promise<void> {
  await page.goto('/panel');
  await page.getByLabel('E-posta').fill(KULLANICI.email);
  await page.getByLabel('Şifre').fill(KULLANICI.password);
  await page.getByRole('button', { name: 'Devam et' }).click();

  // 2FA kapalı kullanıcıda giriş TEK adımdır (K45) — doğrudan panele düşer.
  await expect(page.getByRole('navigation')).toBeVisible({ timeout: 15_000 });
}

/** Oturum çerezini paylaşan API bağlamı için CSRF token'ı. */
export async function csrfToken(page: Page): Promise<string> {
  const response = await page.request.get('/api/auth/me');
  expect(response.ok()).toBeTruthy();
  const body = (await response.json()) as { data: { csrf_token: string } };

  return body.data.csrf_token;
}

/** Liste açar ve kimliğini döndürür (API üzerinden — senaryo kurulumunu hızlandırır). */
export async function listeAc(page: Page, ad: string): Promise<number> {
  const response = await page.request.post('/api/lists', {
    headers: { 'X-CSRF-Token': await csrfToken(page) },
    data: { name: ad },
  });
  expect(response.status(), await response.text()).toBe(201);
  const body = (await response.json()) as { data: { id: number } };

  return body.data.id;
}

/** Listeye ürün ekler (API) ve ürün kimliğini döndürür. */
export async function urunEkle(page: Page, listId: number, ad: string, adet = 10): Promise<number> {
  const response = await page.request.post(`/api/lists/${listId}/products`, {
    headers: { 'X-CSRF-Token': await csrfToken(page) },
    data: { name: ad, qty: adet, price_yuan: '12.50', url: 'https://detail.1688.com/offer/1.html' },
  });
  expect(response.status(), await response.text()).toBe(201);
  const body = (await response.json()) as { data: { id: number } };

  return body.data.id;
}
