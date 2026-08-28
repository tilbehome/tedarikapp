import { beforeEach, describe, expect, test, vi } from 'vitest';
import { SUTUNLAR, VARSAYILAN, hucreSinifi, tercihOku, tercihYaz } from './tabloTercihi';

/**
 * TABLO TERCİHLERİ (İE#21 B2 cilaları).
 *
 * Tercih kişisel bir konfor ayarıdır; bozulduğunda ekran ÇALIŞMAYA DEVAM ETMELİ.
 * Bu testler tam olarak o dayanıklılığı sınar: bozuk kayıt, erişilemeyen depolama
 * ve artık var olmayan sütun adları.
 */

beforeEach(() => {
  window.localStorage.clear();
});

describe('Varsayılan', () => {
  test('kayıt yokken tüm sütunlar açık, rahat yoğunluk, gruplama kapalı', () => {
    const tercih = tercihOku();

    expect(tercih.sutunlar).toEqual(Object.keys(SUTUNLAR));
    expect(tercih.yogunluk).toBe('rahat');
    expect(tercih.grupla).toBe('yok');
  });
});

describe('Yazma ve okuma', () => {
  test('kaydedilen tercih geri okunur', () => {
    tercihYaz({ sutunlar: ['adet', 'durum'], yogunluk: 'sik', grupla: 'kategori' });

    expect(tercihOku()).toEqual({ sutunlar: ['adet', 'durum'], yogunluk: 'sik', grupla: 'kategori' });
  });

  test('kullanıcı TÜM sütunları kapatabilir', () => {
    tercihYaz({ ...VARSAYILAN, sutunlar: [] });

    expect(tercihOku().sutunlar).toEqual([]);
  });
});

describe('Bozuk kayıt varsayılana düşer', () => {
  test('JSON değilse', () => {
    window.localStorage.setItem('tedarikapp.liste-tablosu', 'bu json değil{');

    expect(tercihOku()).toEqual(VARSAYILAN);
  });

  test('beklenmeyen tipte ise', () => {
    window.localStorage.setItem('tedarikapp.liste-tablosu', '"metin"');

    expect(tercihOku()).toEqual(VARSAYILAN);
  });

  test('bilinmeyen sütun adları ATILIR', () => {
    window.localStorage.setItem(
      'tedarikapp.liste-tablosu',
      JSON.stringify({ sutunlar: ['adet', 'artik-olmayan-sutun'], yogunluk: 'sik', grupla: 'durum' }),
    );

    // Eski sürümde var olan bir sütun bugün yoksa tercih onu taşımaz; kalanı korunur.
    expect(tercihOku()).toEqual({ sutunlar: ['adet'], yogunluk: 'sik', grupla: 'durum' });
  });

  test('geçersiz yoğunluk/gruplama değerleri güvenli değere düşer', () => {
    window.localStorage.setItem(
      'tedarikapp.liste-tablosu',
      JSON.stringify({ sutunlar: ['adet'], yogunluk: 'uçuk', grupla: 'ay' }),
    );

    const tercih = tercihOku();
    expect(tercih.yogunluk).toBe('rahat');
    expect(tercih.grupla).toBe('yok');
  });
});

describe('Depolama erişilemezse ekran çalışır', () => {
  test('okuma hata atsa da varsayılan döner', () => {
    const casus = vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
      throw new Error('site verisi kapalı');
    });

    expect(tercihOku()).toEqual(VARSAYILAN);

    casus.mockRestore();
  });

  test('yazma hata atsa da çağrı patlamaz', () => {
    const casus = vi.spyOn(window.localStorage, 'setItem').mockImplementation(() => {
      throw new Error('kota doldu');
    });

    expect(() => tercihYaz(VARSAYILAN)).not.toThrow();

    casus.mockRestore();
  });
});

describe('Yoğunluk hücre sınıfı', () => {
  test('sık yoğunlukta satır daha alçaktır', () => {
    expect(hucreSinifi('sik')).not.toBe(hucreSinifi('rahat'));
    expect(hucreSinifi('sik')).toContain('py-1');
  });
});
