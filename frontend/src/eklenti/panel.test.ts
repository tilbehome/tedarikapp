import { describe, expect, test, vi } from 'vitest';

import { panelGovdesi, type PanelEylemleri, type PanelGorunumu } from '../../../extension/ui/v2/panel';
import { alanRaporu } from '../../../extension/core/alanRaporu';
import { baslangicDurumu, gecis, type MakineDurumu } from '../../../extension/core/durumMakinesi';
import type { ParseResult } from '../../../extension/core/types';

/**
 * EKLENTİ v2 — PANEL GÖVDESİ (İE#21 A1/A3/A5/A8).
 *
 * Testler panel çalışma alanındadır (jsdom orada kuruludur; eklenti bundle'ı
 * bağımlılıksız kalır — gerekçe `montaj.test.ts` başlığında).
 *
 * Kapsanan senaryolar: EKL-05 (önizleme), EKL-07 (varyant bütünlüğü), EKL-08
 * (MOQ/stok uyarısı), EKL-10 (kısmi + devam), EKL-16..20 (mükerrer dört seçenek),
 * EKL-22 (hedef liste + miktar), EKL-24 (disclosure), EKL-28 (kapsam dışı alan
 * uydurulmaz), EKL-29 (aynı önizleme yeniden çizilir).
 */

function ayristirma(fark: Partial<ParseResult> = {}): ParseResult {
  return {
    ok: true,
    missing: [],
    source: {
      platform: '1688',
      external_id: '895133432293',
      url: 'https://detail.1688.com/offer/895133432293.html',
      seller_name: '义乌市盎燕电子商务有限公司',
      seller_url: 'https://shop.1688.com/x',
      captured_at: '2026-08-24T10:00:00+03:00',
    },
    raw: {
      title: '洞洞鞋男士2025夏季新款',
      price_blocks: null,
      images: ['a.jpg'],
      video: null,
      normalized_attributes: { 材质: 'EVA' },
      min_order: 2,
      unit: '双',
      breadcrumb: ['家居鞋', '拖鞋'],
    },
    normalized: {
      name: 'EVA Kaymaz Terlik',
      price_yuan: '15.90',
      price_tiers: [{ min_qty: 1, price_yuan: '18.90' }],
      images: ['a.jpg', 'b.jpg'],
      sku_matrix: [{ props: { renk: 'siyah' } }],
      video_url: null,
    },
    ...fark,
  };
}

function eylemler(): PanelEylemleri {
  return {
    onTara: vi.fn(),
    onGonder: vi.fn(),
    onKapat: vi.fn(),
    onDevam: vi.fn(),
    onMukerrer: vi.fn(),
    onHedef: vi.fn(),
    onVaryant: vi.fn(),
    onDisclosure: vi.fn(),
    onKuyruk: vi.fn(),
  };
}

function gorunum(fark: Partial<PanelGorunumu> = {}): PanelGorunumu {
  return {
    makine: gecis(gecis(baslangicDurumu(), 'TARA'), 'OKUMA_TAM'),
    rapor: alanRaporu(ayristirma()),
    urunAdi: 'EVA Kaymaz Terlik',
    orijinalAd: '洞洞鞋男士2025夏季新款',
    varyantlar: ['siyah', 'gri', 'mavi'],
    seciliVaryant: 'siyah',
    listeler: [
      { id: null, ad: 'Gelen Kutusu (varsayılan)' },
      { id: 7, ad: 'MUTFAK ÜRÜNLERİ' },
    ],
    hedef: { listeId: null, miktar: 200, not: 'Yaz sezonu adayı', etiketler: ['yaz-2027'] },
    duranlar: [],
    disclosureGerekli: false,
    ...fark,
  };
}

function ciz(fark: Partial<PanelGorunumu> = {}, eylem = eylemler()): { govde: HTMLElement; eylem: PanelEylemleri } {
  const govde = panelGovdesi(gorunum(fark), eylem);
  document.body.innerHTML = '';
  document.body.append(govde);

  return { govde, eylem };
}

describe('Durum şeridi — hiçbir eylem sessiz değildir', () => {
  test('her durum kendi Türkçe metniyle görünür', () => {
    const durumlar: [MakineDurumu['durum'], string][] = [
      ['D2_OKUNUYOR', 'Veriler okunuyor…'],
      ['D3_ONIZLEME', 'Ürün verileri bulundu'],
      ['D4_KISMI', 'Bazı bilgiler eksik'],
      ['D6_GONDERILIYOR', 'Gönderiliyor…'],
      ['D7_GONDERILDI', "TedarikApp'e gönderildi"],
      ['D8_MUKERRER', 'Ürün zaten listede'],
      ['D9_YETKI_HATASI', 'TedarikApp bağlantısı gerekli'],
      ['D10_SUNUCU_HATASI', 'TedarikApp şu anda yanıt vermiyor'],
    ];

    for (const [durum, metin] of durumlar) {
      const { govde } = ciz({ makine: { durum, captureId: null, gonderimSayisi: 0, eksikler: [] } });
      const serit = govde.querySelector('.tdk-serit');

      expect(serit?.getAttribute('data-durum')).toBe(durum);
      expect(serit?.textContent).toContain(metin);
      expect(serit?.getAttribute('aria-live')).toBe('polite');
    }
  });
});

describe('E2E-EKL-05/07 — önizleme ve varyantlar', () => {
  test('16+ alan, doluluk halkası ve kanal etiketi çizilir', () => {
    const { govde } = ciz();

    expect(govde.querySelectorAll('.tdk-alan').length).toBeGreaterThanOrEqual(16);
    const halka = govde.querySelector('.tdk-halka');
    expect(halka?.getAttribute('aria-label')).toMatch(/\d+ \/ \d+ alan yakalandı/);
    expect(govde.textContent).toContain('GOMULU');
  });

  test('seçilen varyant kendi bölümünde ve işaretli görünür', () => {
    const { govde, eylem } = ciz();
    const cipler = [...govde.querySelectorAll('.tdk-varyant .cip')] as HTMLButtonElement[];
    const siyah = cipler.find((cip) => cip.textContent === 'siyah');

    expect(siyah?.getAttribute('aria-pressed')).toBe('true');

    cipler.find((cip) => cip.textContent === 'gri')?.click();
    expect(eylem.onVaryant).toHaveBeenCalledWith('gri');
  });

  test('varyant yoksa bölüm hiç çizilmez — boş kutu gösterilmez', () => {
    const { govde } = ciz({ varyantlar: [] });

    expect(govde.textContent).not.toContain('Seçilen varyant');
  });
});

describe('E2E-EKL-08/28 — eksik alan uyarısı, uydurma yok', () => {
  test('sayfada olmayan alan "elle girilir" der ve eksik işaretlenir', () => {
    const { govde } = ciz();
    const eksikler = [...govde.querySelectorAll('.tdk-alan.eksik')].map((d) => d.textContent ?? '');

    expect(eksikler.join(' ')).toContain('Koli ölçüsü');
    expect(eksikler.join(' ')).toContain('elle girilir');
  });

  test('GTİP/vergi gibi kapsam dışı alanlar panelde HİÇ görünmez', () => {
    const { govde } = ciz();

    expect(govde.textContent).not.toContain('GTİP');
    expect(govde.textContent).not.toContain('TAREKS');
    expect(govde.textContent).not.toContain('vergi');
  });
});

describe('E2E-EKL-10 — kısmi okumada devam düğmesi', () => {
  test('D4te "eksiklere rağmen devam" görünür ve tıklanır', () => {
    const { govde, eylem } = ciz({
      makine: { durum: 'D4_KISMI', captureId: null, gonderimSayisi: 0, eksikler: ['Koli ölçüsü'] },
    });

    const devam = govde.querySelector('[data-eylem="devam"]') as HTMLButtonElement | null;
    expect(devam).not.toBeNull();

    devam?.click();
    expect(eylem.onDevam).toHaveBeenCalled();
  });

  test('D3te devam düğmesi YOKTUR', () => {
    const { govde } = ciz();

    expect(govde.querySelector('[data-eylem="devam"]')).toBeNull();
  });
});

describe('E2E-EKL-16..20 — mükerrerde dört seçenek', () => {
  test('yalnız D8de görünür ve dördü de tıklanabilir', () => {
    const { govde, eylem } = ciz({
      makine: { durum: 'D8_MUKERRER', captureId: 'cap-1', gonderimSayisi: 1, eksikler: [] },
    });

    const dugmeler = [...govde.querySelectorAll('[data-mukerrer]')] as HTMLButtonElement[];
    expect(dugmeler.map((d) => d.getAttribute('data-mukerrer'))).toEqual([
      'MEVCUDU_AC',
      'BASKA_LISTEYE',
      'MEVCUDU_GUNCELLE',
      'IPTAL',
    ]);

    dugmeler[2]?.click();
    expect(eylem.onMukerrer).toHaveBeenCalledWith('MEVCUDU_GUNCELLE');
  });

  test('önizlemede mükerrer bölümü çizilmez', () => {
    const { govde } = ciz();

    expect(govde.querySelector('[data-mukerrer]')).toBeNull();
  });
});

describe('E2E-EKL-22 — hedef liste, miktar, not, etiket', () => {
  test('listeler seçicide, mevcut seçim işaretli', () => {
    const { govde } = ciz();
    const secim = govde.querySelector('#tdk-liste') as HTMLSelectElement;

    expect([...secim.options].map((o) => o.textContent)).toEqual([
      'Gelen Kutusu (varsayılan)',
      'MUTFAK ÜRÜNLERİ',
    ]);
    expect(secim.value).toBe('');
  });

  test('liste değişimi yukarı verilir', () => {
    const { govde, eylem } = ciz();
    const secim = govde.querySelector('#tdk-liste') as HTMLSelectElement;

    secim.value = '7';
    secim.dispatchEvent(new Event('change'));

    expect(eylem.onHedef).toHaveBeenCalledWith(expect.objectContaining({ listeId: 7 }));
  });

  test('geçersiz miktar 1e düşürülür — sıfır adet sipariş olmaz', () => {
    const { govde, eylem } = ciz();
    const miktar = govde.querySelector('#tdk-miktar') as HTMLInputElement;

    miktar.value = '0';
    miktar.dispatchEvent(new Event('change'));

    expect(eylem.onHedef).toHaveBeenCalledWith(expect.objectContaining({ miktar: 1 }));
  });

  test('not ve etiketler görünür', () => {
    const { govde } = ciz();

    expect((govde.querySelector('#tdk-not') as HTMLTextAreaElement).value).toBe('Yaz sezonu adayı');
    expect(govde.textContent).toContain('yaz-2027');
  });
});

describe('E2E-EKL-24 — prominent disclosure', () => {
  test('onay gerekliyse SADECE disclosure çizilir, önizleme yok', () => {
    const { govde } = ciz({ disclosureGerekli: true });

    expect(govde.querySelector('[data-disclosure="onay"]')).not.toBeNull();
    expect(govde.querySelector('.tdk-alanlar')).toBeNull();
    expect(govde.querySelector('#tdk-liste')).toBeNull();
  });

  test('ne toplandığı ve ne toplanmadığı yazar', () => {
    const { govde } = ciz({ disclosureGerekli: true });

    expect(govde.textContent).toContain('Bu sayfadan okunacaklar:');
    expect(govde.textContent).toContain('Okunmayanlar:');
    expect(govde.textContent).toContain('Çerezleriniz');
  });

  test('onay ve ret ayrı ayrı bildirilir', () => {
    const { govde, eylem } = ciz({ disclosureGerekli: true });

    (govde.querySelector('[data-disclosure="onay"]') as HTMLButtonElement).click();
    (govde.querySelector('[data-disclosure="red"]') as HTMLButtonElement).click();

    expect(eylem.onDisclosure).toHaveBeenNthCalledWith(1, true);
    expect(eylem.onDisclosure).toHaveBeenNthCalledWith(2, false);
  });
});

describe('Duran kayıtlar sessiz kalmaz (B11 ikizi)', () => {
  test('rozet sayısı ve üç eylem görünür', () => {
    const { govde, eylem } = ciz({
      duranlar: [{ captureId: 'cap-1', ad: 'EVA Terlik', sonHata: 'HTTP 502' }],
    });

    const kutu = govde.querySelector('.tdk-kuyruk');
    expect(kutu?.getAttribute('data-duran-sayisi')).toBe('1');
    expect(kutu?.textContent).toContain('HTTP 502');

    const eylemDugmeleri = [...govde.querySelectorAll('[data-kuyruk-eylem]')] as HTMLButtonElement[];
    expect(eylemDugmeleri.map((d) => d.getAttribute('data-kuyruk-eylem'))).toEqual(['YENIDEN', 'DUZELT', 'VAZGEC']);

    eylemDugmeleri[0]?.click();
    expect(eylem.onKuyruk).toHaveBeenCalledWith('cap-1', 'YENIDEN');
  });

  test('duran kayıt yoksa kutu hiç çizilmez', () => {
    const { govde } = ciz();

    expect(govde.querySelector('.tdk-kuyruk')).toBeNull();
  });
});

describe('E2E-EKL-29 — aynı önizleme yeniden çizilir', () => {
  test('panel iki kez kurulduğunda aynı alanlar ve seçimler görünür', () => {
    const ilk = panelGovdesi(gorunum(), eylemler());
    const ikinci = panelGovdesi(gorunum(), eylemler());

    expect(ikinci.querySelectorAll('.tdk-alan').length).toBe(ilk.querySelectorAll('.tdk-alan').length);
    expect((ikinci.querySelector('#tdk-miktar') as HTMLInputElement).value).toBe('200');
  });
});
