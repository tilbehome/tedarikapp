import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, test } from 'vitest';
import { AYAR_SEKMELERI, sekmeyiCoz } from './sekmeler';

const kok = resolve(__dirname, '../../..');
const oku = (goreli: string): string => readFileSync(resolve(kok, goreli), 'utf8');

/**
 * V3-B C2 — TAŞIMA KORUMA LİSTESİ BEKÇİSİ.
 *
 * 645 satırlık tek sayfa 16 sekmeye bölündü. Böyle bir taşımanın klasik
 * kaybı, bir kartın "başka sekmeye gitti" sanılıp hiçbir sekmeye
 * konulmamasıdır — ekran çalışmaya devam eder, kart sessizce yok olur.
 *
 * Bu bekçi PM'in beş maddelik koruma listesini KAYNAK ÜZERİNDEN doğrular.
 * Ekran testi değil kaynak taraması olmasının sebebi: bu maddeler "render
 * edildi mi" sorusundan daha temel bir şeyi sınıyor — kodun hâlâ var olup
 * olmadığını. Silinen bir bileşen render testinde de görünmez ama sebebi
 * anlaşılmaz; burada hangi maddenin düştüğü ADIYLA yazılır.
 */
describe('C2 koruma listesi — taşımada düşen yok', () => {
  test('1) LLM anahtar uyarısı metni ve data-testid BİREBİR korunur (sekme 8)', () => {
    const kaynak = oku('src/screens/ayarlar/CeviriAyarlari.tsx');

    expect(kaynak).toContain('data-testid="llm-anahtar-uyarisi"');
    // D12 kabul turunda sessiz başarısızlığı görünür kılan TEK yüzey buydu.
    expect(kaynak).toContain('LLM anahtarı tanımlı değil — üç dil garantisi yok.');
  });

  test('2) Arka plan işleri kartı İKİ SİNYALİ de taşır (sekme 15)', () => {
    const kaynak = oku('src/screens/ayarlar/KuyrukDurumu.tsx');

    // Ölü iş: bir şey KALICI olarak başarısız oldu.
    expect(kaynak).toMatch(/olu|ölü/i);
    // En eski bekleyen işin yaşı: bekleyen sayısı tek başına akışı göstermez.
    expect(kaynak).toContain('en_eski_bekleyen_dakika');
  });

  test('3) Kur geçmişi YENİ ALANLARI gösterir, set_at adı korunur (sekme 7)', () => {
    const kaynak = oku('src/screens/ayarlar/bolumler/KurTarihcesi.tsx');

    expect(kaynak).toContain('satir.aktif');
    expect(kaynak).toContain('satir.kaynak');
    expect(kaynak).toContain('satir.superseded_at');
    // Eski alan adı DEĞİŞTİRİLMEZ — ekran sözleşmesi kırılmaz (İE#22 A3).
    expect(kaynak).toContain('satir.set_at');
    expect(kaynak).toContain('data-testid="kur-aktif-rozeti"');
  });

  test('4) damgayi_esitle AYARLAR\'A TAŞINMAZ — teşhis yüzeyinde kalır', () => {
    // D2-REV: sekiz durumlu kurulum sözleşmesi bozulmaz. Bu eylem yalnız
    // "dosya sürümü ≠ DB damgası" farkı VARKEN görünür ve kurulum/onarım
    // ekranına aittir; Ayarlar'a taşımak onu her zaman görünür kılardı.
    //
    // Aranan şey KULLANIM'dır, kelimenin kendisi değil: kabuğun belge yorumu
    // "buraya taşınmadı" diye bu adı ANIYOR ve anmalı da — kararın gerekçesi
    // koda yakın durmalı. Gerçek kullanım tırnak içinde bir dize olurdu
    // (eylem kodu olarak uca gönderilirdi).
    for (const dosya of [
      'src/screens/SettingsScreen.tsx',
      'src/screens/ayarlar/bolumler/VeriBakim.tsx',
      'src/screens/ayarlar/bolumler/GenelAyarlar.tsx',
    ]) {
      expect(oku(dosya)).not.toContain("'damgayi_esitle'");
      expect(oku(dosya)).not.toContain('"damgayi_esitle"');
    }
  });

  test('5) Medya yedeği alanları düşmez (sekme 16)', () => {
    const kaynak = oku('src/screens/ayarlar/bolumler/VeriBakim.tsx');

    // İE#22 E4: yedek kartı medya manifesti/zip'i de raporluyor.
    expect(kaynak).toContain('BackupCard');
    expect(kaynak).toMatch(/medya/i);
  });
});

describe('16 sekme sicili', () => {
  test('sekme sayısı 16 ve numaralar 1..16', () => {
    expect(AYAR_SEKMELERI).toHaveLength(16);
    expect(AYAR_SEKMELERI.map((s) => s.no)).toEqual(Array.from({ length: 16 }, (_, i) => i + 1));
  });

  test('kodlar benzersiz', () => {
    const kodlar = AYAR_SEKMELERI.map((s) => s.kod);
    expect(new Set(kodlar).size).toBe(kodlar.length);
  });

  test('boş sekmenin GEREKÇESİ var — gerekçesiz boşluk unutulmuş sekmedir', () => {
    for (const sekme of AYAR_SEKMELERI) {
      if (!sekme.dolu) {
        expect(sekme.bekleyen, `${sekme.ad}: gerekçe yok`).toBeTruthy();
        expect((sekme.bekleyen ?? '').length).toBeGreaterThan(20);
      }
    }
  });

  test('bilinmeyen kod ilk sekmeye düşer', () => {
    expect(sekmeyiCoz('uydurma-sekme').kod).toBe('genel');
    expect(sekmeyiCoz(null).kod).toBe('genel');
  });

  test('PM eşlemesinin beş yüzeyi doğru sekmede', () => {
    const kabuk = oku('src/screens/SettingsScreen.tsx');

    // kuyruk kartı → 15, görsel arşivi + migration + medya yedeği → 16,
    // uygulama adresi → 1.
    expect(kabuk).toMatch(/case 'kuyruk':\s*\n\s*return <KuyrukDurumu \/>;/);
    expect(kabuk).toMatch(/case 'veri':\s*\n\s*return <VeriBakim \/>;/);
    expect(kabuk).toMatch(/case 'genel':\s*\n\s*return <GenelAyarlar \/>;/);

    const veri = oku('src/screens/ayarlar/bolumler/VeriBakim.tsx');
    expect(veri).toContain('MediaArchiveCard');
    expect(veri).toContain('MigrationActions');

    expect(oku('src/screens/ayarlar/bolumler/GenelAyarlar.tsx')).toContain('UygulamaAdresi');
  });
});
