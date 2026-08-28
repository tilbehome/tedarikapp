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
    // F43: V3 kabuğunda "Listeler" hem yan menüde hem alt sekme çubuğunda var — .first() şart.
    await page.getByRole('link', { name: 'Listeler' }).first().click();
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
    // Düğme adı formun kipine göre değişir: yeni üründe "Ürünü ekle",
    // düzenlemede "Değişiklikleri kaydet" (CI kanıtı: /Kaydet|Oluştur/ hiç eşleşmedi
    // ve test 60 sn bekleyip düştü).
    await page.getByRole('button', { name: 'Ürünü ekle' }).click();

    // Kayıt sonrası liste detayına dönülür; ürün orada görünür.
    await expect(page).toHaveURL(/\/panel\/listeler\/\d+$/);
    await expect(gorunen(page.getByText('E2E Ürünü'))).toBeVisible();

    // ── Liste durumunu ilerlet: Taslak → İletildi (K48: kur BU ANDA kilitlenir) ──
    //
    // rc8/K2 (F43 disiplini): İE#21 B2'de "Liste durumunu ilerlet:" metinli
    // düğme kümesi AŞAMA ÇUBUĞUYLA değişti. Aşamalar artık `data-testid`
    // taşır (`asama-<durum kodu>`) ve etiket `sr-only`dur; metne bakan eski
    // seçici hiçbir şey bulamıyordu.
    await expect(page.getByTestId('asama-cubugu')).toBeVisible();
    await page.getByTestId('asama-sent').click();

    // Aşama çubuğu "İletildi" adımını AKTİF gösterir (rozet metni değil,
    // bileşenin kendi durum niteliği sınanır).
    await expect(page.getByTestId('asama-sent')).toHaveAttribute('data-durum', 'aktif');
  });
});
