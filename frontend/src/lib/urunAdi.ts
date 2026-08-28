import type { Product } from '../api/types';

/**
 * EKRANDA GÖRÜNEN ÜRÜN ADI (D11b saha bulgusu, 25 Ağu 2026).
 *
 * Sunucu iki alan gönderir: `name` SAKLANAN kayıttır (çeviri turu onu ezmez —
 * K54), `ad_gosterim` ise en güncel kalıcı çeviridir. Ekran ikincisini basar;
 * yoksa (eski sürüm ya da çevirisi olmayan ürün) `name`e düşer.
 *
 * Tek satırlık bu işlev bilinçli olarak paylaşılıyor: liste, çekmece ve toplu
 * eylem çubuğu aynı adı göstermezse kullanıcı hangisinin doğru olduğunu
 * bilemez — sahada tam olarak bu yaşandı (sınav yeni çeviriyi, ekran eskisini
 * gösteriyordu).
 */
export function urunAdi(urun: Pick<Product, 'name' | 'ad_gosterim'>): string {
  const gosterim = urun.ad_gosterim?.trim();

  return gosterim !== undefined && gosterim !== '' ? gosterim : urun.name;
}

/** Ad çeviriden geliyorsa arayüz "TR önerisi" rozeti gösterir. */
export function ceviriRozetiGerekli(urun: Pick<Product, 'ad_kaynak'>): boolean {
  return urun.ad_kaynak === 'ceviri';
}
