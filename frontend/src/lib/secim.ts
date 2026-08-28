import { useState } from 'react';

/**
 * SÜZGEÇ DEĞİŞİNCE SEÇİM SIFIRLAMA (İE#21 B2 · E2E-PNL-23).
 *
 * Kural tek cümlede: kullanıcı hangi süzgeçte seçtiyse o süzgeçte işlem yapar.
 * "Verilecek" süzgecinde 12 ürün seçip süzgeci "Geldi"ye çeviren kişi, ekranda
 * artık görünmeyen 12 ürüne toplu işlem uygulayabilmemelidir — gördüğü şeyle
 * yaptığı şey ayrışırsa toplu işlem bir kumar hâline gelir.
 *
 * SIFIRLAMA RENDER SIRASINDA TÜRETİLİR, efektle değil: efekt bir kare sonra
 * çalışır ve o kare boyunca eski seçimle çizilmiş toplu eylem çubuğu ekranda
 * durur. React'in önerdiği "önceki prop'u state'te tut" kalıbı bu yüzden burada.
 */
export function useSuzgecSecimi(imza: string, seciliAdedi: number, temizle: () => void): void {
  const [sonImza, setSonImza] = useState(imza);

  if (sonImza !== imza) {
    setSonImza(imza);
    if (seciliAdedi > 0) temizle();
  }
}

/**
 * Seçimi GÖRÜNÜR satırlarla kesiştirir.
 *
 * Sıfırlama süzgeç değişimini yakalar; bu yardımcı ise veri tazelendiğinde
 * (silinen/taşınan ürün) seçimde hayalet kimlik kalmasını engeller.
 */
export function gorunurSecim(secili: number[], gorunurIdler: number[]): number[] {
  const gorunur = new Set(gorunurIdler);

  return secili.filter((id) => gorunur.has(id));
}
