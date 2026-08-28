/**
 * SALT GÖRÜNÜM TÜRKÇELEŞTİRME (v1.0.1/A9 — Ürün Sahibi kararı 28 Ağu 2026).
 *
 * "Seçilen varyant" bölümündeki renk/beden/bölge adları çipte ham Çince
 * görünüyordu; kullanıcı hangi varyantı seçtiğini okuyamıyordu.
 *
 * BU BİR ÇEVİRİ KATMANI DEĞİLDİR — üç sınırı vardır ve üçü de bilinçlidir:
 *   1. YALNIZ GÖRÜNÜM. Sunucuya giden veri ORİJİNAL kalır; eşleşen bir terim
 *      bulunsa bile yakalama yükündeki değer değişmez (K54/K56 hattı, yani
 *      sözlük→önbellek→LLM sırası, sunucudadır ve olduğu gibi durur).
 *   2. EKLENTİDE LLM YOK (PM koşulu). Burada ağ isteği, model çağrısı, uzak
 *      sözlük yoktur; yalnız pakete gömülü kapalı bir küme.
 *   3. KAPALI KÜME. Yalnız 1688 varyant satırlarında fiilen görülen terimler
 *      girer. Eşleşme yoksa metin AYNEN gösterilir — yarım çeviri, hiç
 *      çevirmemekten kötüdür ("美规" yerine "ABD" yazıp voltajı düşürmek gibi).
 *
 * Eşleştirme parça parçadır: "颜色: 粉红色 / 美规" gibi bileşik değerlerde her
 * parça ayrı aranır, bulunamayan parça olduğu gibi kalır.
 */

/** Gömülü küme: 1688 varyant/renk/bölge terimleri → Türkçe karşılıkları. */
export const GORUNUM_SOZLUGU: Readonly<Record<string, string>> = {
  // Renkler
  粉红色: 'Pembe',
  粉色: 'Pembe',
  红色: 'Kırmızı',
  蓝色: 'Mavi',
  浅蓝色: 'Açık mavi',
  深蓝色: 'Lacivert',
  黑色: 'Siyah',
  白色: 'Beyaz',
  灰色: 'Gri',
  绿色: 'Yeşil',
  黄色: 'Sarı',
  紫色: 'Mor',
  橙色: 'Turuncu',
  棕色: 'Kahverengi',
  米色: 'Bej',
  银色: 'Gümüş',
  金色: 'Altın',
  透明: 'Şeffaf',
  混色: 'Karışık renk',
  随机色: 'Rastgele renk',
  // Elektrik standardı / bölge — voltaj bilgisi KORUNUR, yoksa yanlış ürün gelir.
  美规: 'ABD fişi (110V)',
  欧规: 'AB fişi (220V)',
  英规: 'İngiltere fişi (220V)',
  澳规: 'Avustralya fişi (220V)',
  国标: 'Çin standardı (220V)',
  // Beden / ölçü
  均码: 'Tek beden',
  大码: 'Büyük beden',
  小码: 'Küçük beden',
  加大: 'Ekstra büyük',
  加厚: 'Kalın',
  加绒: 'İçi peluş',
  // Ambalaj / adet
  单个: 'Tek adet',
  一对: 'Bir çift',
  套装: 'Set',
  散装: 'Dökme',
  // Sık alan adları
  颜色: 'Renk',
  尺码: 'Beden',
  规格: 'Özellik',
  款式: 'Model',
  型号: 'Model no',
  材质: 'Malzeme',
  重量: 'Ağırlık',
  数量: 'Adet',
};

/** Parçalara ayırırken korunacak ayraçlar. */
const AYRAC = /([:：/、,，|\s]+)/;

/**
 * Metni SALT GÖRÜNÜM için Türkçeleştirir; eşleşme yoksa aynen döndürür.
 *
 * @param metin ham (orijinal) değer — bu fonksiyon onu DEĞİŞTİRMEZ, kopyasını üretir
 */
export function gorunumIcinTurkce(metin: string): string {
  const ham = metin.trim();
  if (ham === '') return metin;

  const tam = GORUNUM_SOZLUGU[ham];
  if (tam !== undefined) return tam;

  // Bileşik değer: ayraçlar korunarak parça parça bakılır.
  const parcalar = ham.split(AYRAC);
  let degisti = false;
  const cevrilmis = parcalar.map((parca) => {
    const karsilik = GORUNUM_SOZLUGU[parca.trim()];
    if (karsilik === undefined) return parca;
    degisti = true;

    return parca.replace(parca.trim(), karsilik);
  });

  return degisti ? cevrilmis.join('') : metin;
}
