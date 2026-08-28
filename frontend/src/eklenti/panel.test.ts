import { describe, expect, test, vi } from 'vitest';

import { panelGovdesi, type PanelEylemleri, type PanelGorunumu } from '../../../extension/ui/v2/panel';
import { alanRaporu } from '../../../extension/core/alanRaporu';
import { V2_CSS } from '../../../extension/ui/v2/stil';
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
    onPaneldeAc: vi.fn(),
    onBaglantiyiDene: vi.fn(),
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

describe('E2E-EKL-13 — başarıda panelde aç', () => {
  test('D7de "Panelde aç" düğmesi görünür ve çalışır', () => {
    const { govde, eylem } = ciz({
      makine: { durum: 'D7_GONDERILDI', captureId: 'cap-1', gonderimSayisi: 1, eksikler: [] },
      urunId: 42,
    });

    const ac = govde.querySelector('[data-eylem="panelde-ac"]') as HTMLButtonElement | null;
    expect(ac).not.toBeNull();

    ac?.click();
    expect(eylem.onPaneldeAc).toHaveBeenCalled();
  });

  test('gönderim öncesi böyle bir düğme YOKTUR', () => {
    const { govde } = ciz();

    expect(govde.querySelector('[data-eylem="panelde-ac"]')).toBeNull();
  });
});

describe('D5 — bağlantı şeridi ve boş hedef seçici (saha bulgusu)', () => {
  test('bağlıyken şerit ÇİZİLMEZ — gürültü yapmaz', () => {
    const { govde } = ciz({ baglanti: 'BAGLI', baglantiMesaj: 'Panele bağlı' });

    expect(govde.querySelector('[data-baglanti]')).toBeNull();
  });

  test('ulaşılamıyorsa sebep yazılır ve "Yeniden dene" düğmesi çıkar', () => {
    const { govde, eylem } = ciz({
      baglanti: 'ERISILEMIYOR',
      baglantiMesaj: 'Panele ulaşılamıyor — yakalama kuyrukta bekler, bağlanınca gönderilir.',
    });
    const serit = govde.querySelector('[data-baglanti]') as HTMLElement;

    expect(serit).not.toBeNull();
    expect(serit.textContent).toContain('kuyrukta bekler');
    (serit.querySelector('[data-eylem="baglanti-dene"]') as HTMLButtonElement).click();

    expect(eylem.onBaglantiyiDene).toHaveBeenCalled();
  });

  test('deneme sürerken "yeniden dene" gösterilmez — iki kez tetiklenmesin', () => {
    const { govde } = ciz({ baglanti: 'DENENIYOR', baglantiMesaj: 'Bağlantı deneniyor…' });

    expect(govde.querySelector('[data-eylem="baglanti-dene"]')).toBeNull();
  });

  test('liste henüz gelmediyse seçici BOŞ değil, sebebini söyler', () => {
    const { govde } = ciz({ listeler: [], baglanti: 'DENENIYOR', baglantiMesaj: 'Bağlantı deneniyor…' });
    const secim = govde.querySelector('#tdk-liste') as HTMLSelectElement;

    expect(secim.disabled).toBe(true);
    expect(secim.options[0]?.textContent).toBe('Bağlantı bekleniyor…');
  });

  test('disclosure ekranında bağlantı şeridi çizilmez — tek mesaj, tek karar', () => {
    const { govde } = ciz({ disclosureGerekli: true, baglanti: 'ERISILEMIYOR', baglantiMesaj: 'yok' });

    expect(govde.querySelector('[data-baglanti]')).toBeNull();
  });
});

describe('D10-NİHAİ — panel HER AÇILIŞTA mockup içeriğini gösterir', () => {
  /** Onaylı mockup'ın (docs/sablon/eklenti-v2-sayfa-ici-mockup.html) zorunlu parçaları. */
  function mockupParcalari(govde: HTMLElement) {
    return {
      durumSeridi: govde.querySelector('.tdk-serit'),
      urunKarti: govde.querySelector('.tdk-urun'),
      bilgiBandi: govde.querySelector('.tdk-bilgi'),
      onizleme: govde.querySelector('.tdk-doluluk'),
      alanlar: govde.querySelectorAll('.tdk-alan'),
      hedef: govde.querySelector('#tdk-liste'),
      miktar: govde.querySelector('#tdk-miktar'),
      not: govde.querySelector('#tdk-not'),
    };
  }

  test('veri geldiğinde tüm bölümler var: ürün · önizleme · kanal rozetleri · hedef', () => {
    const { govde } = ciz();
    const parca = mockupParcalari(govde);

    expect(parca.durumSeridi).not.toBeNull();
    expect(parca.urunKarti).not.toBeNull();
    expect(parca.bilgiBandi).not.toBeNull();
    expect(parca.onizleme).not.toBeNull();
    expect(parca.hedef).not.toBeNull();
    expect(parca.miktar).not.toBeNull();
    expect(parca.not).not.toBeNull();

    // Mockup'ta 16+ alan listelenir; kanal rozetleri (YANIT/GÖMÜLÜ/SAYFA) görünür.
    expect(parca.alanlar.length).toBeGreaterThanOrEqual(16);
    expect(govde.querySelectorAll('.kanal').length).toBeGreaterThan(0);
    // Eksik alan SESSİZ değildir: mockup'ın "panelde elle girilir" satırı.
    expect(govde.textContent).toContain('sayfada yok — panelde elle girilir');
    // ZH orijinal + TR önerisi rozeti (mockup c-zh / c-tr).
    expect(govde.textContent).toContain('洞洞鞋男士2025夏季新款');
    expect(govde.textContent).toContain('TR önerisi');
  });

  test('VERİ YOKKEN BOŞ KABUK DEĞİL İSKELET çizilir', () => {
    const { govde } = ciz({ makine: baslangicDurumu(), rapor: null, urunAdi: null, orijinalAd: null });
    const parca = mockupParcalari(govde);

    // Sahada görülen hâl: yalnız Hedef/Miktar/Not. Artık ürün kartı ve önizleme
    // de var; alanlar "okunuyor…" diyor.
    expect(parca.urunKarti).not.toBeNull();
    expect(parca.onizleme).not.toBeNull();
    expect(parca.alanlar.length).toBeGreaterThanOrEqual(16);
    expect(govde.querySelectorAll('.tdk-alan.iskelet').length).toBeGreaterThanOrEqual(16);
    expect(govde.textContent).toContain('okunuyor…');
    expect(parca.hedef).not.toBeNull();
  });

  test('on kez çizilse de görünüm AYNI kalır (deterministik)', () => {
    const imzalar = new Set<string>();
    for (let i = 0; i < 10; i++) {
      const { govde } = ciz();
      imzalar.add(
        [
          govde.querySelectorAll('.tdk-alan').length,
          govde.querySelectorAll('.kanal').length,
          govde.querySelector('.tdk-urun') === null ? 'yok' : 'var',
          govde.querySelector('.tdk-doluluk') === null ? 'yok' : 'var',
          govde.querySelector('#tdk-liste') === null ? 'yok' : 'var',
        ].join('|'),
      );
    }

    // Saha şikâyeti "aynı sayfada üç yenilemede üç farklı hâl"di.
    expect(imzalar.size).toBe(1);
  });

  test('mükerrer kayıtta bilgi bandı UYARIR', () => {
    const { govde } = ciz({ urunId: 42 });

    expect(govde.querySelector('.tdk-bilgi')?.getAttribute('data-bilgi')).toBe('mukerrer');
    expect(govde.textContent).toContain('panelde ZATEN VAR');
  });
});

describe('rc7 D10-b — panel içeriği yatay taşmaz', () => {
  const UZUN_ADRES =
    'https://detail.1688.com/offer/1062644236710.html?offerId=1062644236710&hotSaleSkuId=5310981234567&spm=a260k.home2024.recommendpart.9';

  test('adres değerleri TEK SATIRA kırpılır, tam değer title özniteliğinde durur', () => {
    const rapor = alanRaporu(
      ayristirma({
        source: {
          platform: '1688',
          external_id: '1062644236710',
          url: UZUN_ADRES,
          seller_url: 'https://shop1234567890.1688.com/page/offerlist.htm?spm=a2615.7691456.wp_pc_common_topnav_38975102',
          seller_name: '义乌市盎燕电子商务有限公司',
          captured_at: '2026-08-26T10:00:00+03:00',
        } as ParseResult['source'],
      }),
    );
    const { govde } = ciz({ rapor });

    const adresler = [...govde.querySelectorAll('.tdk-alan .deger')].filter((d) =>
      (d.textContent ?? '').startsWith('http'),
    );
    expect(adresler.length).toBeGreaterThanOrEqual(2);
    for (const deger of adresler) {
      // Kırpma sınıfı: panel taşmaz.
      expect(deger.classList.contains('tek-satir')).toBe(true);
      // v1.0/A4: TAM DEĞER ARTIK `title` BALONUNDA DEĞİL.
      //
      // Native balon 448 px'lik panelde ekranın dışına taşıyordu (saha bulgusu
      // 27 Ağu): kullanıcı kırpılan adresi yine okuyamıyordu. Veri kaybı yok —
      // satırın yanına, adresi panoya alan bir düğme kondu.
      expect((deger as HTMLElement).title).toBe('');
      const satir = deger.closest('.tdk-alan');
      expect(satir?.querySelector('[data-eylem="kopyala"]')).not.toBeNull();
    }
  });

  test('uzun varyant adları çipte kırpılır; tam adı erişilebilirlik katmanı taşır', () => {
    const varyantlar = [
      '绿色【足弓支撑 久站不累脚】加厚底 38/39',
      '蓝色【足弓支撑 久站不累脚】加厚底 40/41',
      '灰色【足弓支撑 久站不累脚】加厚底 42/43',
      '黑色【足弓支撑 久站不累脚】加厚底 44/45',
      '粉色【足弓支撑 久站不累脚】加厚底 36/37',
      '米色【足弓支撑 久站不累脚】加厚底 46/47',
    ];
    const { govde } = ciz({ varyantlar, seciliVaryant: varyantlar[0] });

    // Etiket şeridi de aynı çip sınıfını kullanır; varyantları uzunluklarıyla ayır.
    const cipler = [...govde.querySelectorAll('.tdk-varyant .cip')].filter(
      (cip) => (cip.textContent ?? '').includes('足弓支撑'),
    );
    expect(cipler).toHaveLength(6);
    for (const cip of cipler) {
      // v1.0/A4: `title` yok (balon ekran dışına taşıyordu); tam ad
      // `aria-label`dadır — ekran okuyucu ve otomasyon için erişilebilir kalır.
      expect((cip as HTMLElement).title).toBe('');
      expect(cip.getAttribute('aria-label')).toBe(cip.textContent);
      expect((cip.textContent ?? '').length).toBeGreaterThan(20);
    }
  });

  test('CSS taşma disiplini: değer sütunu min-width:0, kesintisiz metin kırılır', () => {
    // jsdom yerleşim hesaplamaz; bu yüzden KURALIN VARLIĞI sınanır. Gerçek
    // ölçüm Playwright senaryosundadır (EKL-30).
    expect(V2_CSS).toMatch(/\.tdk-alan \.deger \{[^}]*min-width:\s*0/);
    expect(V2_CSS).toMatch(/\.tdk-alan \.deger \{[^}]*overflow-wrap:\s*anywhere/);
    expect(V2_CSS).toMatch(/\.tdk-alan \.deger\.tek-satir \{[^}]*text-overflow:\s*ellipsis/);
    expect(V2_CSS).toMatch(/\.tdk-varyant \{[^}]*flex-wrap:\s*wrap/);
    // Taşmanın teknik sebebi grid/flex ögelerinin varsayılan `min-width: auto`
    // değeridir; kırpma (overflow:hidden) sorunu ÇÖZMEZ, yalnız gizler. Bu
    // yüzden sınanan şey sütunun açıkça sınırlanmasıdır (gerçek ölçüm: EKL-30).
    expect(V2_CSS).toMatch(/\.tdk-govde \{[^}]*grid-template-columns:\s*minmax\(0, 1fr\)/);
    expect(V2_CSS).toMatch(/\.tdk-alanlar \{[^}]*grid-template-columns:\s*minmax\(0, 1fr\)/);
    expect(V2_CSS).toMatch(/\.tdk-govde > \* \{[^}]*min-width:\s*0/);
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
