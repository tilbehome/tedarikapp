/**
 * HAM METİN NORMALİZASYONU — sunum katmanının tek giriş noktası (İE#21 B4).
 *
 * SAHA BULGUSU: Gelen Kutusu yakalama detayında varyasyon adı "黑色&gt;12" diye
 * görünüyordu. Kaynak sayfadaki değer ZATEN entity taşıyor ("&gt;"); React metni
 * güvenle basar ama entity'yi ÇÖZMEZ — dolayısıyla kullanıcı ham HTML artığını
 * okuyor.
 *
 * NEDEN SUNUMDA ÇÖZÜLÜYOR, VERİDE DEĞİL: yakalanan ham veri (`raw`) sözleşme
 * gereği DEĞİŞTİRİLMEDEN saklanır (K32) — kaynağın ne gönderdiği bir kanıttır.
 * Düzeltme yeri bu yüzden ekrandır. Sunucu tarafında aynı işi
 * `ValueSet::normalize()` yapar; bu dosya onun panel karşılığıdır ve AYNI
 * kuralları uygular: entity çöz, görünmez boşlukları temizle, boşlukları sıkıştır.
 *
 * GÜVENLİK: bu bir kaçış DEĞİLDİR. React zaten metni escape ederek basar; burada
 * çözülen "&lt;script&gt;" ekrana "<script>" METNİ olarak çıkar, kod olarak değil.
 * Bu yüzden çıktı asla `dangerouslySetInnerHTML` ile kullanılmamalıdır.
 */

/** Yalnız güvenli, iyi bilinen adlandırılmış entity'ler — genel bir HTML çözücü değil. */
const ADLI_ENTITYLER: Record<string, string> = {
  amp: '&',
  lt: '<',
  gt: '>',
  quot: '"',
  apos: "'",
  nbsp: ' ',
  '#39': "'",
  '#34': '"',
};

/** Görünmez karakterler: 1688 değerleri sık sık NBSP ve sıfır genişlikli boşluk taşır. */
const GORUNMEZLER = /[ ​﻿]/g;

export function metniNormalize(ham: string): string {
  if (ham === '') return '';

  const cozulmus = ham
    // Sayısal entity: &#8250; ve &#x203A; — ikisi de aynı karakteri verir.
    .replace(/&#x([0-9a-f]+);/gi, (_, onaltilik: string) =>
      String.fromCodePoint(Number.parseInt(onaltilik, 16)),
    )
    .replace(/&#(\d+);/g, (_, onluk: string) => String.fromCodePoint(Number.parseInt(onluk, 10)))
    .replace(/&([a-z]+);/gi, (tam, ad: string) => ADLI_ENTITYLER[ad.toLowerCase()] ?? tam);

  return cozulmus.replace(GORUNMEZLER, ' ').replace(/\s+/g, ' ').trim();
}
