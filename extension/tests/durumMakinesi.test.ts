import { describe, expect, it } from 'vitest';

import {
  AG_ISTEGI_ACIK,
  DURUM_METINLERI,
  MUKERRER_SECENEKLERI,
  baslangicDurumu,
  gecis,
  gonderilebilir,
  taranabilir,
  type Durum,
  type MakineDurumu,
} from '../core/durumMakinesi';

/**
 * DURUM MAKİNESİ (İE#21 A2) — katalog §2/§3'ün doğrudan karşılığı.
 *
 * Kapsanan senaryolar: EKL-02 (kapalı/tembel), EKL-03 (tek geçiş), EKL-04 (çift
 * tık), EKL-10 (kısmi→devam), EKL-11 (okuma hatası→tekrar), EKL-12 (gönderim
 * kilidi), EKL-13/14/15 (yanıtlar + aynı capture_id ile tekrar), EKL-16/20
 * (mükerrer + iptal), EKL-21 (yetki→yenile), EKL-23 (SPA değişimi), EKL-25/29
 * (kapat/yeniden aç).
 */

function zincir(olaylar: Parameters<typeof gecis>[1][], baslangic: MakineDurumu = baslangicDurumu()): MakineDurumu {
  return olaylar.reduce((durum, olay) => gecis(durum, olay), baslangic);
}

describe('Durum sözlüğü', () => {
  it('on durumun her biri için görünür metin vardır', () => {
    const durumlar = Object.keys(DURUM_METINLERI) as Durum[];

    expect(durumlar).toHaveLength(10);
    for (const metin of Object.values(DURUM_METINLERI)) {
      expect(metin.trim()).not.toBe('');
    }
  });

  it('metinler kataloğun zorunlu ifadeleridir', () => {
    expect(DURUM_METINLERI.D1_KAPALI).toBe("TedarikApp'e Ekle");
    expect(DURUM_METINLERI.D8_MUKERRER).toBe('Ürün zaten listede');
    expect(DURUM_METINLERI.D10_SUNUCU_HATASI).toBe('TedarikApp şu anda yanıt vermiyor');
  });
});

describe('E2E-EKL-02/03 — kapalıdan okunuyora tek geçiş', () => {
  it('başlangıç kapalıdır ve ağ isteği açık değildir', () => {
    const durum = baslangicDurumu();

    expect(durum.durum).toBe('D1_KAPALI');
    expect(AG_ISTEGI_ACIK).not.toContain(durum.durum);
    expect(durum.gonderimSayisi).toBe(0);
  });

  it('TARA olayı okunuyora götürür', () => {
    expect(gecis(baslangicDurumu(), 'TARA').durum).toBe('D2_OKUNUYOR');
  });

  it('okunuyor durumunda /api/capture çağrılmaz', () => {
    expect(AG_ISTEGI_ACIK).toEqual(['D6_GONDERILIYOR']);
  });
});

describe('E2E-EKL-04 — çift tık koruması', () => {
  it('okunuyorken ikinci TARA geçiş üretmez', () => {
    const ilk = gecis(baslangicDurumu(), 'TARA');
    const ikinci = gecis(ilk, 'TARA');
    const ucuncu = gecis(ikinci, 'TARA');

    expect(ikinci).toBe(ilk); // aynı nesne: hiç geçiş olmadı
    expect(ucuncu.durum).toBe('D2_OKUNUYOR');
    expect(taranabilir('D2_OKUNUYOR')).toBe(false);
  });
});

describe('E2E-EKL-10/11 — kısmi okuma ve okuma hatası', () => {
  it('kısmi okuma eksik alanları taşır ve devam önizlemeye geçer', () => {
    const kismi = gecis(gecis(baslangicDurumu(), 'TARA'), 'OKUMA_KISMI', { eksikler: ['price_yuan', 'images'] });

    expect(kismi.durum).toBe('D4_KISMI');
    expect(kismi.eksikler).toEqual(['price_yuan', 'images']);

    const devam = gecis(kismi, 'DEVAM');
    expect(devam.durum).toBe('D3_ONIZLEME');
    // Eksikler önizlemeye TAŞINIR: kullanıcı neyi eksik gönderdiğini bilmeli.
    expect(devam.eksikler).toEqual(['price_yuan', 'images']);
  });

  it('okuma hatasından tekrar taranabilir', () => {
    const hata = zincir(['TARA', 'OKUMA_HATASI']);

    expect(hata.durum).toBe('D5_OKUMA_HATASI');
    expect(gecis(hata, 'TARA').durum).toBe('D2_OKUNUYOR');
  });

  it('tam okuma eksik listesini temizler', () => {
    const kismi = gecis(gecis(baslangicDurumu(), 'TARA'), 'OKUMA_KISMI', { eksikler: ['images'] });
    const yeniden = gecis(gecis(kismi, 'TARA'), 'OKUMA_TAM');

    expect(yeniden.eksikler).toEqual([]);
  });
});

describe('E2E-EKL-12/13 — gönderim kilidi ve başarı', () => {
  it('gönderiliyorken ikinci GONDER istek üretmez', () => {
    const gonderiliyor = zincir(['TARA', 'OKUMA_TAM', 'GONDER']);

    expect(gonderiliyor.durum).toBe('D6_GONDERILIYOR');
    expect(gonderiliyor.gonderimSayisi).toBe(1);

    const tekrar = gecis(gonderiliyor, 'GONDER');
    expect(tekrar).toBe(gonderiliyor);
    expect(tekrar.gonderimSayisi).toBe(1);
    expect(gonderilebilir('D6_GONDERILIYOR')).toBe(false);
  });

  it('başarılı yanıt gönderildi durumuna götürür ve orada POST üretmez', () => {
    const gonderildi = zincir(['TARA', 'OKUMA_TAM', 'GONDER', 'YANIT_BASARILI']);

    expect(gonderildi.durum).toBe('D7_GONDERILDI');
    expect(gonderilebilir('D7_GONDERILDI')).toBe(false);
    // Yeni yakalama ancak kullanıcı komutuyla:
    expect(gecis(gonderildi, 'TARA').durum).toBe('D2_OKUNUYOR');
  });
});

describe('E2E-EKL-14/15 — sunucu hatası ve idempotens', () => {
  it('502 sonrası tekrar AYNI capture_id ile gider', () => {
    const gonderiliyor = zincir(['TARA', 'OKUMA_TAM'], baslangicDurumu());
    const ilkGonderim = gecis(gonderiliyor, 'GONDER', { captureId: 'cap-1' });
    const hata = gecis(ilkGonderim, 'YANIT_SUNUCU');

    expect(hata.durum).toBe('D10_SUNUCU_HATASI');
    expect(hata.captureId).toBe('cap-1');

    const ikinciGonderim = gecis(hata, 'GONDER', { captureId: 'cap-YENI' });
    expect(ikinciGonderim.durum).toBe('D6_GONDERILIYOR');
    // Kimlik KORUNUR: sunucu bunu aynı yakalama sayar, mükerrer ürün doğmaz.
    expect(ikinciGonderim.captureId).toBe('cap-1');
    expect(ikinciGonderim.gonderimSayisi).toBe(2);
  });

  it('sunucu hatasında TARA tanımlı DEĞİLDİR — katalog yalnız "tekrar gönder" der', () => {
    const hata = gecis(gecis(zincir(['TARA', 'OKUMA_TAM']), 'GONDER', { captureId: 'cap-1' }), 'YANIT_SUNUCU');

    // Uydurma geçiş eklemiyoruz: veri korunur, kullanıcı komutu "tekrar gönder"dir.
    expect(gecis(hata, 'TARA')).toBe(hata);
    expect(taranabilir('D10_SUNUCU_HATASI')).toBe(false);
  });

  it('yeni tarama kimliği SIFIRLAR (gönderim sonrası)', () => {
    const gonderildi = gecis(gecis(zincir(['TARA', 'OKUMA_TAM']), 'GONDER', { captureId: 'cap-1' }), 'YANIT_BASARILI');
    const yeniTarama = gecis(gonderildi, 'TARA', { captureId: 'cap-2' });

    expect(yeniTarama.captureId).toBe('cap-2');
    expect(yeniTarama.gonderimSayisi).toBe(0);
  });
});

describe('E2E-EKL-16..20 — mükerrer ve dört seçenek', () => {
  it('mükerrer yanıtı D8e götürür', () => {
    const mukerrer = zincir(['TARA', 'OKUMA_TAM', 'GONDER', 'YANIT_MUKERRER']);

    expect(mukerrer.durum).toBe('D8_MUKERRER');
  });

  it('dört seçenek eksiksiz tanımlıdır', () => {
    expect(MUKERRER_SECENEKLERI.map((secenek) => secenek.kod)).toEqual([
      'MEVCUDU_AC',
      'BASKA_LISTEYE',
      'MEVCUDU_GUNCELLE',
      'IPTAL',
    ]);
  });

  it('iptal önizlemeye döner, gönderim yeniden başlar', () => {
    const mukerrer = zincir(['TARA', 'OKUMA_TAM', 'GONDER', 'YANIT_MUKERRER']);

    expect(gecis(mukerrer, 'MUKERRER_IPTAL').durum).toBe('D3_ONIZLEME');
    expect(gecis(mukerrer, 'GONDER').durum).toBe('D6_GONDERILIYOR');
  });
});

describe('E2E-EKL-21 — yetki hatası', () => {
  it('401/403 sonrası kendiliğinden tekrar YOKTUR; bağlantı yenilenince önizlemeye döner', () => {
    const yetki = zincir(['TARA', 'OKUMA_TAM', 'GONDER', 'YANIT_YETKI']);

    expect(yetki.durum).toBe('D9_YETKI_HATASI');
    expect(gonderilebilir('D9_YETKI_HATASI')).toBe(false);
    expect(gecis(yetki, 'BAGLANTI_YENILENDI').durum).toBe('D3_ONIZLEME');
  });
});

describe('E2E-EKL-23/25/29 — sayfa değişimi ve kapatma', () => {
  it('SPA offer değişimi her durumdan TEMİZ kapalıya döner', () => {
    for (const durum of Object.keys(DURUM_METINLERI) as Durum[]) {
      const sonuc = gecis({ durum, captureId: 'cap-eski', gonderimSayisi: 3, eksikler: ['x'] }, 'SAYFA_DEGISTI');

      expect(sonuc.durum).toBe('D1_KAPALI');
      expect(sonuc.captureId).toBeNull();
      expect(sonuc.eksikler).toEqual([]);
      expect(sonuc.gonderimSayisi).toBe(0);
    }
  });

  it('kapatmak veriyi silmez, yalnız paneli kapatır', () => {
    const onizleme = zincir(['TARA', 'OKUMA_TAM']);
    const kapali = gecis({ ...onizleme, captureId: 'cap-9' }, 'KAPAT');

    expect(kapali.durum).toBe('D1_KAPALI');
    // EKL-29: aynı önizleme yeniden açılabilsin diye kimlik KORUNUR.
    expect(kapali.captureId).toBe('cap-9');
  });
});

describe('Tanımsız geçiş sessizce yok sayılır', () => {
  it('kapalıyken gelen yanıt olayı durumu bozmaz', () => {
    const kapali = baslangicDurumu();

    expect(gecis(kapali, 'YANIT_BASARILI')).toBe(kapali);
    expect(gecis(kapali, 'MUKERRER_IPTAL')).toBe(kapali);
  });
});
