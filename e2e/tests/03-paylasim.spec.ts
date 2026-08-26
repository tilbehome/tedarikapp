import { expect, test } from '@playwright/test';
import { csrfToken, girisYap, gorunen, listeAc, urunEkle } from './yardimcilar';

/**
 * E2E-3 (İE#13 E2 · K51): paylaşım linki GİRİŞSİZ açılır; iptalden sonra 404 verir.
 *
 * Kritik nokta: sayfa oturumsuz bir bağlamda (temiz çerez kavanozu) açılır — yoksa
 * "çalışıyor" sanılan bir sayfa aslında panel oturumuyla açılıyor olabilir.
 */
test.describe('Paylaşım sayfası', () => {
  test('link üretilir, girişsiz açılır; iptalden sonra 404 döner', async ({ page, browser }) => {
    await girisYap(page);
    const listId = await listeAc(page, 'E2E Paylaşım Listesi');
    await urunEkle(page, listId, 'Paylaşılan ürün', 12);

    await page.goto(`/panel/listeler/${listId}`);
    // rc8/K2 (F43 disiplini): seçiciler METİNDEN kimliğe taşındı.
    //
    // Eski hâl `getByRole('button', { name: 'Paylaş' })` idi; B6 Paylaş
    // penceresi eklendikten sonra aynı ada iki öğe uyuyor ve Playwright strict
    // mode ihlali veriyordu. Düğme adı da değişmişti: "Link üret" artık
    // "Bağlantı üret" (ve mevcut linkte "Bağlantıyı yenile"). Metne yaslanan
    // seçici, arayüz her tazelendiğinde kırılır — kimlik kırılmaz.
    await page.getByTestId('paylas-ac').click();
    await expect(page.getByTestId('paylas-penceresi')).toBeVisible();
    await page.getByTestId('paylas-uret').click();

    const linkMetni = page.getByTestId('paylas-adres');
    await expect(linkMetni).toBeVisible();
    const url = ((await linkMetni.textContent()) ?? '').trim();
    // İE#18 G5: kanonik ön ek /liste (eski /p alias olarak yaşamaya devam eder).
    expect(url).toMatch(/\/liste\/[0-9a-f]{64}$/);

    // İE#18 G6: bu süit paylaşım SÖZLEŞMESİNİ sınar, anahtar kapısını değil —
    // kapı ayrı bir süitin konusudur (07-erisim-anahtari). Burada kapatılır.
    await page.request.patch(`/api/lists/${listId}/share-key`, {
      headers: { 'X-CSRF-Token': await csrfToken(page) },
      data: { enabled: false },
    });

    // ── Girişsiz bağlam: yeni tarayıcı bağlamı, oturum çerezi YOK ──
    const misafir = await browser.newContext();
    const misafirSayfa = await misafir.newPage();
    const yanit = await misafirSayfa.goto(url);
    expect(yanit?.status()).toBe(200);
    await expect(gorunen(misafirSayfa.getByText('E2E Paylaşım Listesi'))).toBeVisible();
    await expect(gorunen(misafirSayfa.getByText('Paylaşılan ürün'))).toBeVisible();

    // ── İE#15 A1/F1: ÇIKTILAR firma tarafında (oturumsuz) çalışır ──
    const excelBaglantisi = misafirSayfa.locator('a[href*="/export?format=xlsx"]').first();
    await expect(excelBaglantisi).toBeVisible();
    const imzaliAdres = (await excelBaglantisi.getAttribute('href')) ?? '';
    expect(imzaliAdres, 'Bağlantı sunucuda imzalanmış olmalı').toMatch(/exp=\d+&sig=[A-Za-z0-9_-]{32}/);

    const indirme = await misafirSayfa.request.get(imzaliAdres);
    expect(indirme.status(), 'Oturumsuz Excel indirme 200 dönmeli').toBe(200);
    expect(indirme.headers()['content-disposition'] ?? '').toContain('attachment');

    // İmzasız aynı uç: SABİT 404 (K51)
    const imzasiz = await misafirSayfa.request.get(url + '/export?format=xlsx');
    expect(imzasiz.status(), 'İmzasız indirme 404 olmalı').toBe(404);

    // ── İptal: eski link ANINDA ölür (K51) ──
    await page.getByRole('button', { name: 'Linki iptal et' }).click();
    await expect(gorunen(page.getByText(/iptal edildi/i))).toBeVisible();

    const iptalSonrasi = await misafirSayfa.goto(url);
    expect(iptalSonrasi?.status(), 'İptal edilen link SABİT 404 döndürmeli (K51)').toBe(404);
    await expect(gorunen(misafirSayfa.getByText(/bağlantı|link/i))).toBeVisible();

    await misafir.close();
  });

  test('uydurma token sabit 404 verir (ayrım sızmaz)', async ({ browser }) => {
    const misafir = await browser.newContext();
    const sayfa = await misafir.newPage();

    const bicimsiz = await sayfa.goto('/p/kisa-token');
    expect(bicimsiz?.status()).toBe(404);

    const bilinmeyen = await sayfa.goto('/p/' + 'a'.repeat(64));
    expect(bilinmeyen?.status()).toBe(404);

    await misafir.close();
  });
});
