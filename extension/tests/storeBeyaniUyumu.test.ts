import { describe, expect, it } from 'vitest';

import { DISCLOSURE_METNI } from '../core/disclosure';
import storePaketi from '../../docs/v3/hazirlik/store-yayin-paketi.md?raw';

/**
 * A8 ÇAPRAZ KONTROL — disclosure metni ile Store veri beyanı ÇELİŞMEZ (İE#21).
 *
 * Store incelemesinde en sık düşülen tuzak şudur: eklentinin içindeki metin ile
 * mağaza formundaki beyan farklı şeyler söyler. İnceleyen bunu "yanıltıcı beyan"
 * sayar ve yayın reddedilir.
 *
 * Bu test iki belgeyi KELİME DÜZEYİNDE karşılaştırır:
 *   · eklenti içi metin (`core/disclosure.ts`) — kullanıcının gördüğü,
 *   · `docs/v3/hazirlik/store-yayin-paketi.md` — mağazaya verilen beyan.
 *
 * Biri değişip diğeri unutulursa test KIRMIZI yanar; belgeyi güncellemek, kodu
 * güncellemek kadar zorunlu olur.
 */

const STORE_PAKETI = storePaketi;

describe('Toplanan veri beyanı', () => {
  it('eklenti metnindeki "website content" kalemleri Store beyanında da geçer', () => {
    // Store beyanı (İngilizce): ürün başlığı, fiyat/MOQ, varyant, görsel/video,
    // satıcı sinyalleri. Eklenti metni (Türkçe) aynı kümeyi sayar.
    const beklenen = ['ürün başlığı', 'fiyat', 'varyant', 'görsel', 'satıcı'];

    const storeKucuk = STORE_PAKETI.toLocaleLowerCase('tr');
    for (const kelime of beklenen) {
      expect(storeKucuk).toContain(kelime);
    }

    const eklentiMetni = DISCLOSURE_METNI.toplananlar.join(' ').toLocaleLowerCase('tr');
    expect(eklentiMetni).toContain('fiyat');
    expect(eklentiMetni).toContain('görsel');
    expect(eklentiMetni).toContain('varyasyon');
    expect(eklentiMetni).toContain('satıcı');
  });

  it('kimlik doğrulama bilgisi (token) YALNIZ panele bağlanmak için kullanılır', () => {
    // Store beyanı "authentication information" kalemini token için işaretler.
    expect(STORE_PAKETI).toContain('access token');
    expect(STORE_PAKETI.toLocaleLowerCase('en')).toContain('used only to establish an authorized connection');

    // Eklenti metni token'ı KULLANICI VERİSİ gibi saymaz ama nereye gönderildiğini söyler.
    expect(DISCLOSURE_METNI.gonderilenYer).toContain('kendi');
    expect(DISCLOSURE_METNI.gonderilenYer).toContain('panel');
  });
});

describe('Toplanmayan veri beyanı', () => {
  it('üç "toplanmaz" sözü iki belgede de aynıdır', () => {
    const store = STORE_PAKETI.toLocaleLowerCase('tr');
    const eklenti = DISCLOSURE_METNI.toplanmayanlar.join(' ').toLocaleLowerCase('tr');

    // 1) analitik/reklam/profil izleme yok
    expect(store).toContain('analitik');
    expect(store).toContain('reklam');
    expect(eklenti).toContain('reklam');
    expect(eklenti).toContain('analitik');

    // 2) tarama geçmişi izlenmez
    expect(store).toContain('tarama geçmişi');
    expect(eklenti).toContain('gezinme geçmişiniz');

    // 3) üçüncü tarafa aktarım yok
    expect(store).toContain('üçüncü tarafa');
    expect(eklenti).toContain('üçüncü taraflara');
  });

  it('desteklenmeyen sayfa okunmaz sözü iki belgede de vardır', () => {
    expect(STORE_PAKETI.toLocaleLowerCase('tr')).toContain('desteklenmeyen sayfaların içeriği toplanmaz');
    expect(DISCLOSURE_METNI.toplanmayanlar.join(' ')).toContain('yalnız düğmeye bastığınız sayfa okunur');
  });
});

describe('Kullanıcı tetiklemesi', () => {
  it('iki belge de "kullanıcı basmadan hiçbir şey olmaz" der', () => {
    expect(STORE_PAKETI).toContain('does not initiate product transfers automatically');
    expect(STORE_PAKETI.toLocaleLowerCase('tr')).toContain('kendiliğinden ürün aktarımı başlatmaz');
    expect(DISCLOSURE_METNI.onayDugmesi.toLocaleLowerCase('tr')).toContain('başlat');
  });
});
