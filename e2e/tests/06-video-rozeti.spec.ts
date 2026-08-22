import { expect, test } from '@playwright/test';
import { csrfToken, girisYap, gorunen } from './yardimcilar';

/**
 * E2E-6 — VİDEO ROZETİ ve BİLGİ MODU (İE#17 G11).
 *
 * CANLI BELİRTİ: rozet görünüyor, tıklayınca HİÇBİR ŞEY olmuyordu.
 *
 * TEŞHİS: ürünler eklenti 1.2.0 ÖNCESİ yakalandığı için `video_url` boştu; rozet
 * "bilgi modu" dalından (`data-video-yok`) basılıyordu. O dalı tanıyan kod İE#15
 * ile geldi — ama kullanıcının tarayıcısındaki BAYAT p-share.js hâlâ v0.11.0
 * sürümüydü ve yalnız `[data-video]` seçiyordu; `data-video-yok` rozetine yapılan
 * tıklama hiçbir işleyiciye düşmüyordu. Kök neden G1'in konusudur (sürümsüz
 * varlık); bu test TAZE varlıklarla davranışın DOĞRU olduğunu kanıtlar.
 */
test.describe('Video rozeti', () => {
  test('video verisi olan üründe rozet çıkar ve bilgi modu açılır', async ({ page, browser }) => {
    await girisYap(page);

    const listYanit = await page.request.post('/api/lists', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'E2E Video Listesi' },
    });
    const listId = ((await listYanit.json()) as { data: { id: number } }).data.id;

    const tokenYanit = await page.request.post('/api/settings/extension-token', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
    });
    const token = ((await tokenYanit.json()) as { data: { token: string } }).data.token;

    // Eklenti 1.2.0 ÖNCESİ davranışı: raw.video dolu ama normalized.video_url YOK.
    const yakalama = await page.request.post('/api/capture', {
      headers: { Authorization: `Bearer ${token}` },
      data: {
        capture_id: crypto.randomUUID(),
        schema_version: 2,
        extension_version: 'e2e',
        parser_version: 'e2e',
        qty: 3,
        source: {
          platform: '1688',
          external_id: 'video-' + Date.now(),
          url: 'https://detail.1688.com/offer/12345.html',
          captured_at: new Date().toISOString(),
        },
        raw: {
          title: '带视频的商品',
          video: { id: '441122', poster: 'https://cbu01.alicdn.com/poster.jpg' },
        },
        normalized: {
          name: 'E2E Videolu Ürün',
          price_yuan: '19.90',
          images: ['https://cbu01.alicdn.com/img/urun.jpg'],
          price_tiers: [{ min_qty: 1, price_yuan: '19.90' }],
        },
      },
    });
    expect(yakalama.status(), await yakalama.text()).toBe(201);

    // Kuyruktan listeye taşı.
    const kuyruk = await page.request.get('/api/inbox');
    const kayitlar = ((await kuyruk.json()) as { data: { id: number; name: string | null }[] }).data;
    const kayit = kayitlar.find((x) => x.name === 'E2E Videolu Ürün');
    expect(kayit, 'Yakalama kuyruğa düşmeli').toBeTruthy();

    const tasima = await page.request.post('/api/inbox/assign', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { ids: [kayit?.id], list_id: listId },
    });
    expect(tasima.ok(), await tasima.text()).toBeTruthy();

    // İE#18 G6: bu süit VİDEO davranışını sınar — anahtar kapısı kapatılır
    // (kapının kendisi 07-erisim-anahtari süitinin konusudur).
    await page.request.patch(`/api/lists/${listId}/share-key`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { enabled: false },
    });

    // Paylaşım linki üret.
    const paylasim = await page.request.post(`/api/lists/${listId}/share`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
    });
    const url = ((await paylasim.json()) as { data: { share_url: string } }).data.share_url;

    // ── Girişsiz bağlamda sayfayı aç: firmanın gördüğü hâl ──
    const misafir = await browser.newContext();
    const sayfa = await misafir.newPage();
    await sayfa.goto(url);

    const rozet = sayfa.locator('[data-video-yok]').first();
    await expect(rozet, 'Video verisi varsa rozet basılır').toBeVisible();

    // TIKLAMA: overlay AÇILMALI ve boş kalmamalı — nazik açıklama görünür.
    await rozet.click();
    const overlay = sayfa.locator('#lbx');
    await expect(overlay).toHaveClass(/on/);
    // İE#18 G4c: metin "veri eksiği" diliyle değişti (hata değil, eksik kayıt).
    await expect(gorunen(sayfa.getByText('video adresi kayıtlı değil'))).toBeVisible();
    await expect(sayfa.getByRole('link', { name: 'Kaynak sayfada aç' })).toBeVisible();

    // ESC kapatır.
    await sayfa.keyboard.press('Escape');
    await expect(overlay).not.toHaveClass(/on/);

    await misafir.close();
  });

  test('video verisi olmayan üründe rozet BASILMAZ (sahte rozet yok)', async ({ page, browser }) => {
    await girisYap(page);

    const listYanit = await page.request.post('/api/lists', {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'E2E Videosuz Liste' },
    });
    const listId = ((await listYanit.json()) as { data: { id: number } }).data.id;

    await page.request.post(`/api/lists/${listId}/products`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { name: 'Videosuz ürün', qty: 2, price_yuan: '5.00' },
    });

    await page.request.patch(`/api/lists/${listId}/share-key`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { enabled: false },
    });

    const paylasim = await page.request.post(`/api/lists/${listId}/share`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
    });
    const url = ((await paylasim.json()) as { data: { share_url: string } }).data.share_url;

    const misafir = await browser.newContext();
    const sayfa = await misafir.newPage();
    await sayfa.goto(url);

    await expect(sayfa.locator('[data-video-yok]')).toHaveCount(0);
    await expect(sayfa.locator('[data-video]')).toHaveCount(0);

    await misafir.close();
  });
});
