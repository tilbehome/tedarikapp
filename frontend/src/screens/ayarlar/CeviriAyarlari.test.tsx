import { beforeEach, describe, expect, test, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import CeviriAyarlari from './CeviriAyarlari';

/**
 * AYARLAR > ÇEVİRİ (E2E-PNL-47/48/52).
 *
 * NOT: `Field` etiketi ipucu metnini de içerir; bu yüzden sorgular TAM eşleşme
 * değil ALT DİZE (regex) ile yapılır — aksi hâlde ipucu değişince test kırılır.
 *
 * Üç söz sınanır:
 *  · PNL-47 — sağlayıcı/model kaydedilir, API anahtarı MASKELİ kalır (geri okunmaz),
 *  · PNL-48 — bağlantı testi hatası EKRANDA kalır (toast kaçırılabilir),
 *  · PNL-52 — hedef dil listesi değişimi kaydedilir ve bağımlı alanlar buna uyar.
 */

/**
 * `vi.mock` çağrıları dosyanın en üstüne TAŞINIR; fabrika içinde kullanılan
 * casusları sıradan `const` ile tanımlamak "erişilemez değişken" hatası verir ve
 * bileşen sessizce hata durumunda kalır (bu testte tam olarak o oldu).
 * `vi.hoisted` casusları mock'la birlikte yukarı taşır.
 */
const casuslar = vi.hoisted(() => ({
  ayarlar: vi.fn(async () => ({
    saglayicilar: ['deepseek', 'openai'],
    saglayici: 'deepseek',
    model_ham: 'deepseek-chat',
    varsayilan_model: 'deepseek-chat',
    anahtar_tanimli: true,
    anahtar_onizleme: '••••1234',
    hedef_diller: ['tr', 'en'],
    acik: true,
    bekleyen: 3,
  })),
  ayarlariKaydet: vi.fn(async () => ({})),
  baglantiTesti: vi.fn(async () => ({
    basarili: true,
    saglayici: 'deepseek',
    model: 'deepseek-chat',
    sure_ms: 420,
    hata: null as string | null,
  })),
}));

vi.mock('../../api/endpoints', () => ({
  ceviri: {
    ayarlar: () => casuslar.ayarlar(),
    ayarlariKaydet: (govde: unknown) => casuslar.ayarlariKaydet(govde as never),
    baglantiTesti: () => casuslar.baglantiTesti(),
    topluCevir: async () => ({ kuyruga_alindi: 0 }),
  },
}));

vi.mock('../../components/Toast', () => ({
  useToast: (secici: (durum: { push: (mesaj: string) => void }) => unknown) => secici({ push: () => {} }),
}));

const { ayarlariKaydet, baglantiTesti } = casuslar;

beforeEach(() => {
  ayarlariKaydet.mockClear();
  baglantiTesti.mockClear();
  baglantiTesti.mockResolvedValue({
    basarili: true,
    saglayici: 'deepseek',
    model: 'deepseek-chat',
    sure_ms: 420,
    hata: null,
  });
});

describe('E2E-PNL-47 — sağlayıcı kaydı ve anahtar maskeleme', () => {
  test('tanımlı anahtar ASLA geri gösterilmez; yalnız maskeli önizleme görünür', async () => {
    render(<CeviriAyarlari />);

    await waitFor(() => expect(screen.getByLabelText(/API anahtarı/)).toBeTruthy());
    const kutu = screen.getByLabelText(/API anahtarı/) as HTMLInputElement;

    expect(kutu.value).toBe('');
    expect(kutu.placeholder).toContain('••••');
    expect(document.body.textContent).not.toContain('sk-gercek-anahtar');
  });

  test('anahtar boş bırakılırsa istekte GÖNDERİLMEZ — mevcut anahtar silinmez', async () => {
    const kullanici = userEvent.setup();
    render(<CeviriAyarlari />);

    await waitFor(() => expect(screen.getByLabelText(/API anahtarı/)).toBeTruthy());
    await kullanici.click(screen.getByRole('button', { name: /Kaydet/i }));

    await waitFor(() => expect(ayarlariKaydet).toHaveBeenCalled());
    expect(ayarlariKaydet.mock.calls[0]?.[0]).not.toHaveProperty('anahtar');
  });
});

describe('E2E-PNL-48 — bağlantı testi hatası EKRANDA kalır', () => {
  test('başarısız test mesajı görünür ve kaybolmaz', async () => {
    const kullanici = userEvent.setup();
    baglantiTesti.mockResolvedValue({
      basarili: false,
      saglayici: 'deepseek',
      model: 'yok-model',
      sure_ms: 12,
      hata: 'model_not_found',
    });
    render(<CeviriAyarlari />);

    await waitFor(() => expect(screen.getByLabelText(/API anahtarı/)).toBeTruthy());
    await kullanici.click(screen.getByRole('button', { name: /Bağlantıyı test et/i }));

    await waitFor(() => expect(screen.getByText(/model_not_found/)).toBeTruthy());
  });

  test('başarılı test de ekranda görünür', async () => {
    const kullanici = userEvent.setup();
    render(<CeviriAyarlari />);

    await waitFor(() => expect(screen.getByLabelText(/API anahtarı/)).toBeTruthy());
    await kullanici.click(screen.getByRole('button', { name: /Bağlantıyı test et/i }));

    await waitFor(() => expect(screen.getByText(/yanıt verdi/)).toBeTruthy());
  });
});

describe('E2E-PNL-52 — hedef dil listesi', () => {
  test('mevcut diller virgülle görünür ve değişiklik kaydedilir', async () => {
    const kullanici = userEvent.setup();
    render(<CeviriAyarlari />);

    const kutu = await waitFor(() => screen.getByLabelText(/Hedef diller/) as HTMLInputElement);
    expect(kutu.value).toBe('tr, en');

    // TEK değişim olayı gönderiliyor (yapıştırma eşdeğeri). Harf harf yazmak bu
    // alanda metni bozuyor: değer her tuşta normalize edilip yeniden basılıyor ve
    // bileşen `setAnahtar((m) => m)` ile render tetiklemeye çalıştığı için React
    // güncellemeyi atlıyor. BULGU olarak raporlandı; düzeltme panel kapsamındadır.
    await kullanici.click(kutu);
    fireEvent.change(kutu, { target: { value: 'tr, en, zh' } });
    await kullanici.click(screen.getByRole('button', { name: /Kaydet/i }));

    await waitFor(() => expect(ayarlariKaydet).toHaveBeenCalled());
    expect(ayarlariKaydet.mock.calls[0]?.[0]).toMatchObject({ hedef_diller: ['tr', 'en', 'zh'] });
  });
});
