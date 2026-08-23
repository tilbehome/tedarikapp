/**
 * TARAYICI OTOMATİK DOLDURMA KALKANI (İE#20 D3).
 *
 * SAHA VAKASI: Ayarlar > Çeviri ekranında "Model" kutusuna Chrome, kayıtlı
 * e-posta adresini bastı. Kullanıcı fark etmeden kaydetti; DeepSeek isteği
 * `model: "tilbehome@gmail.com"` ile gitti ve 400 döndü. Hata mesajı sağlayıcıdan
 * geldiği için de "çeviri bozuk" gibi göründü.
 *
 * NEDEN OLDU: Chrome bir formda e-posta/kullanıcı alanı görürse, ONU TAKİP EDEN
 * serbest metin kutularını da aynı profilin parçası sanar ve doldurur. Karar
 * alan ADINA, ETİKETİNE ve KOMŞULARINA bakılarak verilir.
 *
 * NEDEN `autocomplete="off"` YETMEZ: Chrome bu değeri ADRES/İLETİŞİM profili
 * doldurmalarında yıllardır bilinçli olarak yok sayar (kullanıcıyı formların
 * kararından koruma gerekçesiyle). Bu bir hata değil, belgelenmiş davranıştır.
 *
 * ÜÇ KATMANLI ÇÖZÜM — tek başına hiçbiri güvenilir değildir:
 *   1. ALAN ADINI TANINMAZ YAP: `model` yerine `model-x7f3` gibi. Sezgi ada bakar;
 *      tanımadığı adı bilinen bir profil alanıyla eşleştiremez.
 *   2. STANDART İPUÇLARI: `autocomplete="off"` + parola yöneticisi kaçışları
 *      (`data-lpignore`, `data-1p-ignore`). Uyanları uyarır, zarar vermez.
 *   3. ODAKLANANA KADAR SALT-OKUNUR: doldurma sayfa yüklenirken yapılır;
 *      salt-okunur alan doldurulamaz. Kullanıcı tıkladığı an kilit kalkar.
 *      Görünürde hiçbir farkı yoktur ve kalanı geçen tek katman budur.
 */

import { useCallback, useState } from 'react';

/**
 * Alan adına eklenen tuz. Oturum başına sabittir: her render'da değişseydi
 * React alanı yeniden kurar, imleç kaybolurdu.
 */
const ALAN_TUZU = Math.random().toString(36).slice(2, 8);

/** Tarayıcının tanıyamayacağı alan adı + standart kaçış ipuçları. */
export function otomatikDoldurmaKapali(ad: string) {
  return {
    name: `${ad}-${ALAN_TUZU}`,
    autoComplete: 'off',
    autoCorrect: 'off',
    autoCapitalize: 'none',
    spellCheck: false,
    'data-lpignore': 'true',
    'data-1p-ignore': '',
    'data-form-type': 'other',
  } as const;
}

/**
 * Odaklanana kadar salt-okunur tutan kalkan — yukarıdaki 3. katman.
 *
 * Kullanım:
 * ```tsx
 * const kalkan = useAutofillKalkani();
 * <input {...otomatikDoldurmaKapali('model')} {...kalkan} value={…} onChange={…} />
 * ```
 */
export function useAutofillKalkani() {
  const [kilitli, setKilitli] = useState(true);
  const ac = useCallback(() => setKilitli(false), []);

  return {
    readOnly: kilitli,
    onFocus: ac,
    onPointerDown: ac,
  };
}

/**
 * "Bu bir e-posta gibi görünüyor" akıl kontrolü (D3 ikinci yarısı).
 *
 * Kalkan geçmişte doldurulmuş bir ayarı geri almaz ve gelecekteki her tarayıcı
 * sürümünü de bağlamaz. Bu yüzden KAYDETMEDEN ÖNCE değerin kendisi denetlenir:
 * model adı hiçbir sağlayıcıda "@" içermez.
 */
export function epostaGibiMi(deger: string): boolean {
  return /@/.test(deger.trim());
}
