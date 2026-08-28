/**
 * HAM METİN NORMALİZASYONU — G9'un EKLENTİ İKİZİ (v1.0/A3, saha 27 Ağu).
 *
 * Canlı belirti: varyant çiplerinde "英文版&gt;1" görünüyordu. 1688 sayfasının
 * kendisi değeri entity'li veriyor; biz onu olduğu gibi ekrana basınca
 * kullanıcı "&gt;" harflerini okuyor. Sunucu tarafında aynı kusur İE#17 G9'da
 * `ValueSet::normalize()` ile kapandı; eklenti o hattın dışında kaldığı için
 * ikizi burada yaşıyordu.
 *
 * SÖZLEŞME (sunucuyla birebir):
 *   1. Değer sunuma girmeden ÖNCE BİR KEZ çözülür — iki kez değil. Çift çözüm
 *      "&amp;lt;" gibi bilerek kaçırılmış metni "<" yapar; kaynağın anlamını
 *      bozar.
 *   2. Görünmez boşluklar (NBSP, ZWSP, BOM) temizlenir; 1688 değerleri bunları
 *      sıkça taşır ve çipte "iki kelime bitişik" gibi görünür.
 *   3. Boşluk dizileri tek boşluğa iner, uçlar kırpılır.
 *
 * GÜVENLİK: bu bir KAÇIŞ DEĞİLDİR ve kaçışın yerine de geçmez. "&lt;script&gt;"
 * çözülüp "<script>" olur; eklenti bu metni DAİMA `textContent` ile basar, yani
 * tarayıcı onu metin olarak işler, işaretleme olarak değil. innerHTML'e
 * verilmesi YASAKTIR (CLAUDE.md §5).
 */

/** Yalnız 1688 çıktısında fiilen görülen adlandırılmış varlıklar. */
const ADLI_VARLIKLAR: Readonly<Record<string, string>> = {
  amp: '&',
  lt: '<',
  gt: '>',
  quot: '"',
  apos: "'",
  nbsp: ' ',
};

/** Tek geçişte çözer: iç içe kaçışlar bilerek ÇÖZÜLMEZ. */
function birKezCoz(ham: string): string {
  return ham.replace(/&(#x?[0-9a-fA-F]+|[a-zA-Z]+);/g, (tam, govde: string) => {
    if (govde.startsWith('#x') || govde.startsWith('#X')) {
      const kod = Number.parseInt(govde.slice(2), 16);

      return Number.isFinite(kod) && kod > 0 && kod <= 0x10ffff ? String.fromCodePoint(kod) : tam;
    }
    if (govde.startsWith('#')) {
      const kod = Number.parseInt(govde.slice(1), 10);

      return Number.isFinite(kod) && kod > 0 && kod <= 0x10ffff ? String.fromCodePoint(kod) : tam;
    }

    return ADLI_VARLIKLAR[govde.toLowerCase()] ?? tam;
  });
}

export function metinNormalize(ham: string): string {
  const cozulmus = birKezCoz(ham)
    .replace(/ /g, ' ')
    .replace(/[​﻿]/g, '');

  return cozulmus.replace(/\s+/g, ' ').trim();
}
