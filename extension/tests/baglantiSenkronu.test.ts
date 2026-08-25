import { describe, expect, it } from 'vitest';

import {
  DENEME_ARALIKLARI,
  baglantiyiDene,
  hatayiSinifla,
  VARSAYILAN_HEDEF_ADI,
} from '../core/baglanti';
import { Akis, type AkisBagimliliklari } from '../ui/v2/akis';
import { gonderDugmesiKapali } from '../ui/v2/panel';
import type { PanelGorunumu } from '../ui/v2/panel';
import type { ParseResult } from '../core/types';

/**
 * D5 SAHA BULGUSU — SAYFA İÇİ PANELİN BAĞLANTI SENKRONU (25 Ağu 2026).
 *
 * Canlıda popup "bağlı ✓" derken sayfa içi panel bağlantısız görünüyordu: Hedef
 * listesi boş, "Yakala ve Gönder" pasif, hiçbir açıklama yok. Sebep, iki yüzeyin
 * aynı veriye ayrı yollardan bakmasıydı — inline taraf hatayı yutup tek seferlik
 * boş listeye düşüyordu.
 *
 * Bu süit o davranışı KİLİTLER: bağlantı tek kaynaktan (`core/baglanti`) okunur,
 * geçici hatada yeniden denenir, bağlanınca hedef listesi kendiliğinden dolar ve
 * gönder düğmesi açılır. Hepsi DOM'suz sınanır; çizim katmanının kuralı
 * `gonderDugmesiKapali` olarak dışarı alındığı için arayüzün kendi kuralını
 * uydurmadığı da doğrulanabiliyor.
 */

const HEMEN = async (): Promise<void> => {};

function bosSonuc(): ParseResult {
  return {
    raw: { title: '洞洞鞋' } as ParseResult['raw'],
    normalized: {
      name: 'Terlik',
      sku_matrix: [],
      price_tiers: [],
    } as unknown as ParseResult['normalized'],
    missing: [],
  } as unknown as ParseResult;
}

function akisKur(
  ustyaz: Partial<AkisBagimliliklari>,
): { akis: Akis; gorunumler: PanelGorunumu[] } {
  const gorunumler: PanelGorunumu[] = [];
  const akis = new Akis({
    ayristir: async () => bosSonuc(),
    gonder: async () => ({ sonuc: 'BASARILI', urunId: 1 }),
    onayliMi: async () => true,
    duranlar: async () => [],
    listeleriGetir: async () => [],
    kimlikUret: () => 'kimlik-1',
    sonListeyiOku: async () => null,
    sonListeyiYaz: async () => {},
    paneldeAc: () => {},
    ciz: (gorunum) => gorunumler.push(gorunum),
    bekle: HEMEN,
    ...ustyaz,
  });

  return { akis, gorunumler };
}

describe('D5 — hata sınıflandırma tek kaynaktan yapılır', () => {
  it('ayar eksikliği, yetki ve erişim hatası ayrı sınıflardır', () => {
    expect(hatayiSinifla('AYAR_EKSIK')).toBe('AYAR_EKSIK');
    expect(hatayiSinifla('HTTP 401')).toBe('YETKI');
    expect(hatayiSinifla('token geçersiz')).toBe('YETKI');
    // "Bilinmeyen hata" diye bir sonuç YOKTUR: sınıflandırılamayan her şey
    // erişim sorunudur ve yeniden denenmeye değer.
    expect(hatayiSinifla('Failed to fetch')).toBe('ERISILEMIYOR');
    expect(hatayiSinifla('Could not establish connection')).toBe('ERISILEMIYOR');
  });
});

describe('D5 — geçici hata yeniden denenir, kalıcı hata denenmez', () => {
  it('ilk iki deneme düşse de üçüncüde bağlanır (MV3 service worker uykusu)', async () => {
    let deneme = 0;
    const sonuc = await baglantiyiDene({
      bekle: HEMEN,
      listeleriGetir: async () => {
        deneme += 1;
        if (deneme < 3) throw new Error('Could not establish connection');

        return [{ id: 7, name: 'Kış Siparişi' }];
      },
    });

    expect(deneme).toBe(3);
    expect(sonuc.durum).toBe('BAGLI');
    expect(sonuc.listeler[0]).toEqual({ id: null, ad: VARSAYILAN_HEDEF_ADI });
    expect(sonuc.listeler[1]).toEqual({ id: 7, ad: 'Kış Siparişi' });
  });

  it('token hatası TEK denemede biter — tekrar denemek yalnız gecikme üretir', async () => {
    let deneme = 0;
    const sonuc = await baglantiyiDene({
      bekle: HEMEN,
      listeleriGetir: async () => {
        deneme += 1;
        throw new Error('HTTP 401 unauthorized');
      },
    });

    expect(deneme).toBe(1);
    expect(sonuc.durum).toBe('YETKI');
    expect(sonuc.listeler).toEqual([]);
  });

  it('sürekli erişilemezse deneme sayısı sınırlıdır ve sebep yazılır', async () => {
    let deneme = 0;
    const sonuc = await baglantiyiDene({
      bekle: HEMEN,
      listeleriGetir: async () => {
        deneme += 1;
        throw new Error('Failed to fetch');
      },
    });

    expect(deneme).toBe(DENEME_ARALIKLARI.length + 1);
    expect(sonuc.durum).toBe('ERISILEMIYOR');
    expect(sonuc.mesaj).toMatch(/ulaşılamıyor/i);
  });
});

describe('D5 — sayfa içi panel bağlanınca hedef listesi kendiliğinden dolar', () => {
  it('açılışta bağlantı denenir, listeler dolar, son seçim geri gelir', async () => {
    const { akis, gorunumler } = akisKur({
      listeleriGetir: async () => [
        { id: 4, name: 'Ocak Listesi' },
        { id: 9, name: 'Numune' },
      ],
      sonListeyiOku: async () => 9,
    });

    await akis.ac();
    await akis.baglantiyiTazele();

    const son = akis.gorunum();
    expect(son.baglanti).toBe('BAGLI');
    expect(son.listeler.map((liste) => liste.id)).toEqual([null, 4, 9]);
    // EKL-22: aynı listeye ürün ekleyen kullanıcı listeyi her seferinde seçmez.
    expect(son.hedef.listeId).toBe(9);
    // Arayüz "deneniyor" ara halini de görür; kullanıcı sessiz bekletilmez.
    expect(gorunumler.map((g) => g.baglanti)).toContain('DENENIYOR');
  });

  it('ayar eksikse liste boş kalır ama sebep ekrana yazılır', async () => {
    const { akis } = akisKur({
      listeleriGetir: async () => {
        throw new Error('AYAR_EKSIK');
      },
    });

    await akis.ac();
    await akis.baglantiyiTazele();

    expect(akis.gorunum().baglanti).toBe('AYAR_EKSIK');
    expect(akis.gorunum().listeler).toEqual([]);
    expect(akis.gorunum().baglantiMesaj).toMatch(/Panel adresi ve token/i);
    // Ayar yoksa gönderim mümkün DEĞİLDİR: kuyruk aynı hatayı biriktirirdi.
    expect(akis.gonderilebilirMi()).toBe(false);
  });

  it('token sonradan girilince tazeleme bağlantıyı kurar (popup ile aynı sonuç)', async () => {
    let ayarVar = false;
    const { akis } = akisKur({
      listeleriGetir: async () => {
        if (!ayarVar) throw new Error('AYAR_EKSIK');

        return [{ id: 3, name: 'Gelen Kutusu Dışı' }];
      },
    });

    await akis.ac();
    await akis.baglantiyiTazele();
    expect(akis.gonderilebilirMi()).toBe(false);

    // Kullanıcı popup'tan token girer; köprü storage değişimini görüp tazeler.
    ayarVar = true;
    await akis.baglantiyiTazele();

    expect(akis.gorunum().baglanti).toBe('BAGLI');
    expect(akis.gorunum().listeler).toHaveLength(2);
    expect(akis.gonderilebilirMi()).toBe(true);
  });

  it('sayfa okuma bağlantıdan bağımsızdır: bağlantısızken de önizleme üretilir', async () => {
    const { akis } = akisKur({
      listeleriGetir: async () => {
        throw new Error('Failed to fetch');
      },
    });

    await akis.ac();
    await akis.tara();
    await akis.baglantiyiTazele();

    expect(akis.gorunum().baglanti).toBe('ERISILEMIYOR');
    // Önizleme üretildi (durum başlangıçtan ilerledi) — panel "ölü" değil.
    expect(akis.durum().durum).not.toBe('D1_HAZIR');
    // Ulaşılamıyor olmak gönderimi ENGELLEMEZ: yakalama kuyrukta bekler; alt
    // bilgideki söz ("bağlanınca gönderilir") ancak böyle tutulur.
    expect(akis.gonderilebilirMi()).toBe(true);
  });
});

describe('D5 — gönder düğmesinin kilidi bağlantıya da bakar', () => {
  const temel = {
    makine: { durum: 'D3_ONIZLEME' },
    disclosureGerekli: false,
  } as unknown as PanelGorunumu;

  it('ayar/token eksikse düğme kapalıdır — kuyruk bu hatayı çözemez', () => {
    expect(gonderDugmesiKapali({ ...temel, baglanti: 'AYAR_EKSIK' })).toBe(true);
    expect(gonderDugmesiKapali({ ...temel, baglanti: 'YETKI' })).toBe(true);
  });

  it('panele ULAŞILAMIYORSA düğme AÇIKTIR: yakalama kuyrukta bekler', () => {
    expect(gonderDugmesiKapali({ ...temel, baglanti: 'ERISILEMIYOR' })).toBe(false);
    expect(gonderDugmesiKapali({ ...temel, baglanti: 'BILINMIYOR' })).toBe(false);
  });

  it('bağlantı kurulunca düğme açıktır', () => {
    expect(gonderDugmesiKapali({ ...temel, baglanti: 'BAGLI' })).toBe(false);
  });

  it('bağlantı olsa da disclosure onayı yoksa düğme kapalı kalır (A8)', () => {
    expect(gonderDugmesiKapali({ ...temel, baglanti: 'BAGLI', disclosureGerekli: true })).toBe(true);
  });
});

describe('D10-NİHAİ — alan iskeleti ile gerçek rapor AYRIŞAMAZ', () => {
  it('ALAN_ADLARI, alanRaporu satırlarıyla birebir aynıdır', async () => {
    const { ALAN_ADLARI, alanRaporu } = await import('../core/alanRaporu');
    const sonuc = {
      raw: { title: '洞洞鞋', breadcrumb: [], normalized_attributes: {} },
      normalized: { name: 'Terlik', price_yuan: '15.90', price_tiers: [], sku_matrix: [], images: [] },
      source: { platform: '1688', external_id: '1', url: 'https://detail.1688.com/offer/1.html' },
    } as unknown as ParseResult;
    const rapor = alanRaporu(sonuc);

    // İskelet listesi elle yazılmıştır; bu test iki listeyi kilitler. Alan
    // eklenip iskelete yazılmazsa panel açılışta 16 satır, veri gelince 17
    // satır gösterirdi — ekran "zıplardı".
    expect(rapor.satirlar.map((satir) => satir.ad)).toEqual([...ALAN_ADLARI]);
  });
});
