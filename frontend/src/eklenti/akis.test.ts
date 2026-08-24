import { describe, expect, test, vi } from 'vitest';

import { Akis, type AkisBagimliliklari, type GonderimYaniti } from '../../../extension/ui/v2/akis';
import type { ParseResult } from '../../../extension/core/types';

/**
 * EKLENTİ v2 — AKIŞ (durum makinesi + arayüz bağlantısı).
 *
 * Kapsanan senaryolar: EKL-03/04 (tek tarama, çift tık), EKL-05 (önizleme),
 * EKL-10 (kısmi), EKL-11 (okuma hatası), EKL-12/13 (tek gönderim, başarı),
 * EKL-15 (502 → aynı capture_id ile tekrar), EKL-16..20 (mükerrer seçenekleri),
 * EKL-21 (yetki), EKL-23 (SPA), EKL-24 (disclosure kapısı).
 */

function ayristirma(fark: Partial<ParseResult> = {}): ParseResult {
  return {
    ok: true,
    missing: [],
    source: {
      platform: '1688',
      external_id: '1',
      url: 'https://detail.1688.com/offer/1.html',
      seller_name: 'Satıcı',
      seller_url: 'https://shop.1688.com/x',
      captured_at: '2026-08-24T10:00:00+03:00',
    },
    raw: {
      title: '原文',
      price_blocks: null,
      images: ['a.jpg'],
      normalized_attributes: { 材质: 'EVA' },
      min_order: 2,
      unit: '双',
      breadcrumb: ['家居鞋'],
      origin_text: '中国',
      video: { id: '1', poster: null },
    },
    normalized: {
      name: 'Terlik',
      price_yuan: '15.90',
      price_tiers: [{ min_qty: 1, price_yuan: '15.90' }],
      images: ['a.jpg'],
      sku_matrix: [{ props: { renk: 'siyah' } }, { props: { renk: 'gri' } }],
      video_url: 'https://cdn/v.mp4',
      country_of_origin: 'CN',
    },
    ...fark,
  };
}

function kur(fark: Partial<AkisBagimliliklari> = {}) {
  let sayac = 0;
  let sonListe: number | null = null;
  const ciz = vi.fn();
  const paneldeAc = vi.fn();
  const bagimliliklar: AkisBagimliliklari = {
    ayristir: vi.fn(async () => ayristirma()),
    gonder: vi.fn(async (): Promise<GonderimYaniti> => ({ sonuc: 'BASARILI', urunId: 42 })),
    onayliMi: vi.fn(async () => true),
    duranlar: vi.fn(async () => []),
    listeler: vi.fn(async () => [{ id: null, ad: 'Gelen Kutusu' }]),
    kimlikUret: () => `cap-${++sayac}`,
    sonListeyiOku: vi.fn(async () => sonListe),
    sonListeyiYaz: vi.fn(async (id: number | null) => {
      sonListe = id;
    }),
    paneldeAc,
    ciz,
    ...fark,
  };

  return { akis: new Akis(bagimliliklar), bagimliliklar, ciz, paneldeAc };
}

describe('E2E-EKL-24 — disclosure kapısı', () => {
  test('onay yoksa panel açılışında SADECE disclosure gerekir ve tarama yapılmaz', async () => {
    const { akis, bagimliliklar } = kur({ onayliMi: vi.fn(async () => false) });

    await akis.ac();

    expect(akis.gorunum().disclosureGerekli).toBe(true);
    expect(bagimliliklar.ayristir).not.toHaveBeenCalled();
  });

  test('onay verilince tarama başlar', async () => {
    const { akis, bagimliliklar } = kur({ onayliMi: vi.fn(async () => false) });
    await akis.ac();

    await akis.disclosureKarari(true);

    expect(bagimliliklar.ayristir).toHaveBeenCalledTimes(1);
    expect(akis.gorunum().disclosureGerekli).toBe(false);
  });

  test('ret hâlinde hiçbir şey okunmaz', async () => {
    const { akis, bagimliliklar } = kur({ onayliMi: vi.fn(async () => false) });
    await akis.ac();

    await akis.disclosureKarari(false);

    expect(bagimliliklar.ayristir).not.toHaveBeenCalled();
    expect(akis.durum().durum).toBe('D1_KAPALI');
  });
});

describe('E2E-EKL-03/04/05 — tarama ve önizleme', () => {
  test('tarama tek ayrıştırma yapar ve önizlemeye geçer', async () => {
    const { akis, bagimliliklar } = kur();

    await akis.tara();

    expect(bagimliliklar.ayristir).toHaveBeenCalledTimes(1);
    expect(akis.durum().durum).toBe('D4_KISMI'); // koli ölçüsü gibi alanlar hep eksik
    expect(akis.gorunum().rapor?.satirlar.length).toBeGreaterThanOrEqual(16);
    expect(akis.gorunum().seciliVaryant).toBe('siyah');
  });

  test('okuma sürerken ikinci tarama İKİNCİ ayrıştırma açmaz', async () => {
    let cozumle: (() => void) | null = null;
    const bekleyen = new Promise<ParseResult>((resolve) => {
      cozumle = () => resolve(ayristirma());
    });
    const { akis, bagimliliklar } = kur({ ayristir: vi.fn(() => bekleyen) });

    const ilk = akis.tara();
    await akis.tara();
    cozumle?.();
    await ilk;

    expect(bagimliliklar.ayristir).toHaveBeenCalledTimes(1);
  });
});

describe('E2E-EKL-11 — zorunlu alan yoksa okuma hatası', () => {
  test('ad ve fiyat çıkmazsa gönderime izin verilmez', async () => {
    const bos = ayristirma();
    bos.normalized.name = '';
    bos.normalized.price_yuan = '';
    const { akis } = kur({ ayristir: vi.fn(async () => bos) });

    await akis.tara();

    expect(akis.durum().durum).toBe('D5_OKUMA_HATASI');
  });
});

describe('E2E-EKL-12/13/15 — gönderim ve idempotens', () => {
  test('gönderim tek istek açar ve başarıda D7ye geçer', async () => {
    const { akis, bagimliliklar } = kur();
    await akis.tara();
    akis.devam();

    await akis.gonder();

    expect(bagimliliklar.gonder).toHaveBeenCalledTimes(1);
    expect(akis.durum().durum).toBe('D7_GONDERILDI');
  });

  test('502 sonrası tekrar AYNI capture_id ile gider', async () => {
    const gonder = vi
      .fn<AkisBagimliliklari['gonder']>()
      .mockResolvedValueOnce({ sonuc: 'SUNUCU', hata: 'HTTP 502' })
      .mockResolvedValueOnce({ sonuc: 'BASARILI', urunId: 7 });
    const { akis } = kur({ gonder });

    await akis.tara();
    akis.devam();
    await akis.gonder();
    expect(akis.durum().durum).toBe('D10_SUNUCU_HATASI');

    await akis.gonder();

    const ilkKimlik = gonder.mock.calls[0]?.[0].captureId;
    const ikinciKimlik = gonder.mock.calls[1]?.[0].captureId;
    expect(ikinciKimlik).toBe(ilkKimlik);
    expect(akis.durum().durum).toBe('D7_GONDERILDI');
  });

  test('sunucu hatasında duran kayıtlar tazelenir — sessiz kayıp yok', async () => {
    const duranlar = vi.fn(async () => [{ captureId: 'cap-1', ad: 'Terlik', sonHata: 'HTTP 502' }]);
    const { akis } = kur({
      gonder: vi.fn(async () => ({ sonuc: 'SUNUCU', hata: 'HTTP 502' }) as GonderimYaniti),
      duranlar,
    });

    await akis.tara();
    akis.devam();
    await akis.gonder();

    expect(akis.gorunum().duranlar).toHaveLength(1);
  });
});

describe('E2E-EKL-16..21 — mükerrer ve yetki', () => {
  test('mükerrer yanıtı dört seçeneği açar; iptal önizlemeye döner', async () => {
    const { akis } = kur({ gonder: vi.fn(async () => ({ sonuc: 'MUKERRER', urunId: 9 }) as GonderimYaniti) });
    await akis.tara();
    akis.devam();
    await akis.gonder();

    expect(akis.durum().durum).toBe('D8_MUKERRER');

    await akis.mukerrerSecenek('IPTAL');
    expect(akis.durum().durum).toBe('D3_ONIZLEME');
  });

  test('"mevcut kaydı güncelle" aynı kimlikle yeniden gönderir', async () => {
    const gonder = vi
      .fn<AkisBagimliliklari['gonder']>()
      .mockResolvedValueOnce({ sonuc: 'MUKERRER', urunId: 9 })
      .mockResolvedValueOnce({ sonuc: 'BASARILI', urunId: 9 });
    const { akis } = kur({ gonder });

    await akis.tara();
    akis.devam();
    await akis.gonder();
    await akis.mukerrerSecenek('MEVCUDU_GUNCELLE');

    expect(gonder).toHaveBeenCalledTimes(2);
    expect(gonder.mock.calls[1]?.[0].captureId).toBe(gonder.mock.calls[0]?.[0].captureId);
    expect(akis.durum().durum).toBe('D7_GONDERILDI');
  });

  test('yetki hatası kendiliğinden tekrar denemez', async () => {
    const gonder = vi.fn(async () => ({ sonuc: 'YETKI' }) as GonderimYaniti);
    const { akis } = kur({ gonder });

    await akis.tara();
    akis.devam();
    await akis.gonder();

    expect(akis.durum().durum).toBe('D9_YETKI_HATASI');
    await akis.gonder();
    expect(gonder).toHaveBeenCalledTimes(1);
  });
});

describe('E2E-EKL-23 — SPA değişimi', () => {
  test('önizleme ve hedef temizlenir', async () => {
    const { akis } = kur();
    await akis.tara();
    akis.hedefDegistir({ listeId: 7, miktar: 50, not: 'x', etiketler: ['a'] });

    akis.sayfaDegisti();

    const gorunum = akis.gorunum();
    expect(gorunum.makine.durum).toBe('D1_KAPALI');
    expect(gorunum.rapor).toBeNull();
    expect(gorunum.hedef).toEqual({ listeId: null, miktar: 1, not: '', etiketler: [] });
  });
});

describe('Görünüm yayını', () => {
  test('her durum değişiminde arayüz yeniden çizilir', async () => {
    const { akis, ciz } = kur();

    await akis.tara();

    // en az: TARA + sonuç
    expect(ciz.mock.calls.length).toBeGreaterThanOrEqual(2);
  });
});

describe('E2E-EKL-17 — mevcut ürünü panelde aç', () => {
  test('mükerrerde "mevcudu aç" paneli açar ve durum DEĞİŞMEZ', async () => {
    const { akis, paneldeAc } = kur({
      gonder: vi.fn(async () => ({ sonuc: 'MUKERRER', urunId: 55 }) as GonderimYaniti),
    });
    await akis.tara();
    akis.devam();
    await akis.gonder();

    await akis.mukerrerSecenek('MEVCUDU_AC');

    expect(paneldeAc).toHaveBeenCalledWith(55);
    // Kullanıcı sekmeye bakıp geri dönebilmeli: yakalama hâlâ mükerrer ekranında.
    expect(akis.durum().durum).toBe('D8_MUKERRER');
  });
});

describe('E2E-EKL-18 — başka listeye ekle önizlemeye döner', () => {
  test('hedef değiştirilebilsin diye D3e dönülür, kimlik korunur', async () => {
    const gonder = vi
      .fn<AkisBagimliliklari['gonder']>()
      .mockResolvedValueOnce({ sonuc: 'MUKERRER', urunId: 9 })
      .mockResolvedValueOnce({ sonuc: 'BASARILI', urunId: 10 });
    const { akis } = kur({ gonder });
    await akis.tara();
    akis.devam();
    await akis.gonder();

    await akis.mukerrerSecenek('BASKA_LISTEYE');
    expect(akis.durum().durum).toBe('D3_ONIZLEME');

    akis.hedefDegistir({ listeId: 7, miktar: 5, not: '', etiketler: [] });
    await akis.gonder();

    expect(gonder.mock.calls[1]?.[0].hedef.listeId).toBe(7);
    expect(gonder.mock.calls[1]?.[0].captureId).toBe(gonder.mock.calls[0]?.[0].captureId);
  });
});

describe('E2E-EKL-22 — hedef liste ve son seçim', () => {
  test('listeler yüklenir ve son seçim HATIRLANIR', async () => {
    const sonListeyiOku = vi.fn(async () => 7);
    const { akis } = kur({
      sonListeyiOku,
      listeler: vi.fn(async () => [
        { id: null, ad: 'Gelen Kutusu' },
        { id: 7, ad: 'MUTFAK' },
      ]),
    });

    await akis.ac();

    expect(akis.gorunum().listeler).toHaveLength(2);
    expect(akis.gorunum().hedef.listeId).toBe(7);
  });

  test('artık var olmayan liste hatırlanmaz — silinmiş listeye gönderilmez', async () => {
    const { akis } = kur({
      sonListeyiOku: vi.fn(async () => 99),
      listeler: vi.fn(async () => [{ id: null, ad: 'Gelen Kutusu' }]),
    });

    await akis.ac();

    expect(akis.gorunum().hedef.listeId).toBeNull();
  });

  test('liste değişimi kaydedilir', async () => {
    const { akis, bagimliliklar } = kur();
    await akis.ac();

    akis.hedefDegistir({ listeId: 7, miktar: 1, not: '', etiketler: [] });

    expect(bagimliliklar.sonListeyiYaz).toHaveBeenCalledWith(7);
  });
});
