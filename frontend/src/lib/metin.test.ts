import { describe, expect, test } from 'vitest';
import { metniNormalize } from './metin';

/**
 * B4 saha bulgusu: Gelen Kutusu detayında varyasyon adı "黑色&gt;12" görünüyordu.
 *
 * Kaynak sayfadaki değer zaten entity taşıyor; React metni escape ederek basar ama
 * entity'yi çözmez. Düzeltme sunum katmanındadır — ham veri sözleşme gereği
 * değiştirilmeden saklanır (K32).
 */
describe('metniNormalize', () => {
  test('saha vakasını çözer', () => {
    expect(metniNormalize('黑色&gt;12')).toBe('黑色>12');
  });

  test('adlandırılmış entityleri çözer', () => {
    expect(metniNormalize('A &amp; B')).toBe('A & B');
    expect(metniNormalize('&lt;kutu&gt;')).toBe('<kutu>');
    expect(metniNormalize('12&quot;')).toBe('12"');
  });

  test('sayısal ve onaltılık entityleri çözer', () => {
    expect(metniNormalize('&#8250;')).toBe('›');
    expect(metniNormalize('&#x203A;')).toBe('›');
  });

  test('görünmez boşlukları temizler', () => {
    // NBSP + sıfır genişlikli boşluk: 1688 değerlerinde sık görülür.
    expect(metniNormalize('Gri ​ / L')).toBe('Gri / L');
  });

  test('boşlukları sıkıştırır ve kırpar', () => {
    expect(metniNormalize('  Gri    Kapak  ')).toBe('Gri Kapak');
  });

  test('tanınmayan entity OLDUĞU GİBİ kalır', () => {
    // Uydurma bir çözüm veriyi bozar; ham metin en azından doğrudur.
    expect(metniNormalize('&bilinmeyen;')).toBe('&bilinmeyen;');
  });

  test('script metni KOD DEĞİL METİN olarak çıkar', () => {
    // Çıktı React ile basılır ve escape edilir; bu yüzden çözmek güvenlidir.
    expect(metniNormalize('&lt;script&gt;alert(1)&lt;/script&gt;')).toBe('<script>alert(1)</script>');
  });

  test('boş girdi boş döner', () => {
    expect(metniNormalize('')).toBe('');
    expect(metniNormalize('   ')).toBe('');
  });

  test('normal metne dokunmaz', () => {
    expect(metniNormalize('Çift Cidarlı Termos 500 ml')).toBe('Çift Cidarlı Termos 500 ml');
  });
});
