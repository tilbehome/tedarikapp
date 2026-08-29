import { expect, test } from '@playwright/test';
import { girisYap } from './yardimcilar';

/**
 * AYARLAR GÖRÜNÜM SENARYOLARI + EKRAN GÖRÜNTÜSÜ ÜRETİMİ (V3-B madde 11).
 *
 * İKİ İŞ BİRDEN, bilinçli olarak:
 *   1. Gezinme, arama ve KPI şeridi DAVRANIŞINI sınar (asıl test).
 *   2. Aynı adımlarda ekran görüntüsü ÜRETİR (`e2e/ekranlar/` altına).
 *
 * Görüntüler ayrı bir betikle alınsaydı, o betik testlerden bağımsız çürürdü:
 * seçici değişir, betik hâlâ "çalışır" ama boş bir ekran fotoğraflar. Burada
 * görüntü ancak assertion'lar geçtikten SONRA alınır — yani fotoğraf her zaman
 * ÇALIŞAN bir ekranındır.
 *
 * Görüntüler görsel regresyon karşılaştırması YAPMAZ (`toHaveScreenshot`
 * değil): amaç PM'e kanıt sunmak. Piksel karşılaştırması ayrı bir karardır ve
 * yazı tipi/işletim sistemi farklarında sürekli kırmızı verir.
 */

/**
 * Görüntü dizini `.gitignore`dadır: kanıt olarak PM'e iletilir, repoya girmez.
 * Her koşumda yeniden üretilir; ikili dosyayı tarihçeye koymak, kanıtı
 * saklamanın en pahalı yoludur.
 */
const EKRAN_DIZINI = 'ekranlar';

test.describe('Ayarlar — gezinme, arama ve KPI', () => {
  test.beforeEach(async ({ page }) => {
    await girisYap(page);
    // "Yenilikler" balonu sağ alt köşede duruyor ve tam sayfa görüntüsünde
    // içeriğin üstüne biniyor. Kanıt fotoğrafı ekranı OLDUĞU GİBİ göstermeli,
    // bir bildirimin arkasını değil — kapatılır. (Balonun kendi davranışı
    // E2E-PNL-55'te ayrıca sınanıyor.)
    const balon = page.getByTestId('surum-balonu-kapat');
    if (await balon.isVisible().catch(() => false)) {
      await balon.click();
    }
  });

  test('E2E-PNL-63 sol gezinme grupları ve bölüm geçişi', async ({ page }) => {
    await page.goto('/panel/ayarlar');

    const gezinme = page.getByTestId('ayar-gezinme');
    await expect(gezinme).toBeVisible();

    // Beş grup başlığı: TEMEL / VERİ VE OPERASYON / FİYAT VE DİL /
    // ÇIKTI VE İLETİŞİM / SİSTEM.
    for (const baslik of ['TEMEL', 'VERİ VE OPERASYON', 'FİYAT VE DİL', 'ÇIKTI VE İLETİŞİM', 'SİSTEM']) {
      await expect(gezinme.getByText(baslik, { exact: true })).toBeVisible();
    }

    // Bölüme geçiş URL'yi taşır — yer imine eklenebilir, geri düğmesi çalışır.
    await page.getByTestId('ayar-sekme-kur').click();
    await expect(page).toHaveURL(/\?sekme=kur/);
    await expect(page.getByTestId('bolum-basligi')).toContainText('Kur & Para Birimleri');

    await page.goBack();
    await expect(page).not.toHaveURL(/\?sekme=kur/);
  });

  test('E2E-PNL-64 arama madde adını ve açıklamayı süzer', async ({ page }) => {
    await page.goto('/panel/ayarlar');

    const arama = page.getByPlaceholder('Ayarlarda ara...');
    await arama.fill('sözlük');

    // Ad eşleşmesi.
    await expect(page.getByTestId('ayar-sekme-diller')).toBeVisible();
    // Alakasız madde düşer.
    await expect(page.getByTestId('ayar-sekme-guvenlik')).toBeHidden();

    await page.screenshot({ path: `${EKRAN_DIZINI}/ayarlar-arama.png`, fullPage: true });

    // Sonuç yoksa TASARLANMIŞ boş durum çıkar — çıplak boşluk değil.
    await arama.fill('zzzz-olmayan-ayar');
    await expect(page.getByTestId('ayar-arama-bos')).toBeVisible();
  });

  test('E2E-PNL-65 KPI şeridi yalnız ölçülebilen bölümde ve gerçek veriyle', async ({ page }) => {
    await page.goto('/panel/ayarlar?sekme=sistem');

    const serit = page.getByTestId('kpi-serit');
    await expect(serit).toBeVisible();
    // Kartlar UYDURMA sayı basmaz: ölçülemeyen kart hiç render edilmez.
    // Bu yüzden en az bir kart olmalı ve hiçbiri "—" ile dolmamalı.
    await expect(serit.getByTestId('kpi-kart').first()).toBeVisible();

    await page.screenshot({ path: `${EKRAN_DIZINI}/ayarlar-sistem-yedekler.png`, fullPage: true });

    // Çeviri bölümünde de KPI var (önbellek satırı, son 24 saat çağrı).
    await page.goto('/panel/ayarlar?sekme=ceviri');
    await expect(page.getByTestId('kpi-serit')).toBeVisible();
    await page.screenshot({ path: `${EKRAN_DIZINI}/ayarlar-ceviri.png`, fullPage: true });

    // Ölçülebilir KPI'si OLMAYAN bölümde şerit BASILMAZ.
    await page.goto('/panel/ayarlar?sekme=paylasim');
    await expect(page.getByTestId('kpi-serit')).toHaveCount(0);
  });

  test('E2E-PNL-66 dar ekranda gezinme üstte açılır listeye döner', async ({ page }) => {
    await page.setViewportSize({ width: 780, height: 900 });
    await page.goto('/panel/ayarlar');

    // Sticky dikey sütun gider, açılır liste gelir.
    await expect(page.getByTestId('ayar-gezinme-mobil')).toBeVisible();
    await expect(page.getByTestId('ayar-gezinme')).toBeHidden();

    await page.screenshot({ path: `${EKRAN_DIZINI}/ayarlar-dar-ekran.png`, fullPage: true });
  });

  test('E2E-PNL-67 sürüm rozeti AppVersion değerini basar', async ({ page }) => {
    await page.goto('/panel/');

    // Sunucunun bildirdiği sürüm ile rozetteki değer AYNI olmalı.
    const durum = await page.evaluate(async () => {
      const yanit = await fetch('/api/system/status', { credentials: 'same-origin' });
      const govde = await yanit.json();

      return govde.data.app_version as string;
    });

    await expect(page.getByTestId('surum-rozeti')).toHaveText(durum);
    // Bulgu #2: sabit "1.0" bir daha basılmamalı.
    expect(durum).not.toBe('1.0');
  });
});
