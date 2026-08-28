import type { Product, UrunEksigi } from '../api/types';

/**
 * ÜRÜN EKSİKLERİ — panelin yüzü, SUNUCUNUN kararı (İE#21 B2).
 *
 * Bir ürünün "HAZIR" olup olamayacağına `App\Services\Ilan\HazirlikKapisi`
 * karar verir (C8) ve kararını her ürün satırında `hazir_eksikleri` olarak
 * gönderir: `{ alan, etiket }`. Panel bu listeyi SAYAR ve SÜZER, ama kendi
 * "eksik" tanımını KURMAZ.
 *
 * Neden kopya yazmadık: iki dilde iki tanım bir gün ayrışır ve kullanıcı çipte
 * "1 üründe kategori eksik" görürken kaydetmeye çalıştığında "kategori zorunlu
 * değil" cevabını alır. Tek kaynak, tek gerçek.
 */

/** Alan kimliği kararlıdır (süzgeç bunu kullanır); etiket görünen yüzdür. */
export type EksikAlan = string;

export function eksikAlanlar(urun: Product): EksikAlan[] {
  return (urun.hazir_eksikleri ?? []).map((eksik: UrunEksigi) => eksik.alan);
}

export function eksikEtiketleri(urun: Product): string[] {
  return (urun.hazir_eksikleri ?? []).map((eksik: UrunEksigi) => eksik.etiket);
}

/** Alan kimliği → görünen etiket (ekrandaki ürünlerden toplanır). */
export function etiketHaritasi(urunler: Product[]): Map<EksikAlan, string> {
  const harita = new Map<EksikAlan, string>();
  for (const urun of urunler) {
    for (const eksik of urun.hazir_eksikleri ?? []) {
      harita.set(eksik.alan, eksik.etiket);
    }
  }

  return harita;
}

export function eksikMi(urun: Product, alan: EksikAlan): boolean {
  return eksikAlanlar(urun).includes(alan);
}

/** Sunucunun HAZIR kararı; eksik kalmadıysa kapı açıktır. */
export function hazirOlabilirMi(urun: Product): boolean {
  return (urun.hazir_eksikleri ?? []).length === 0;
}
