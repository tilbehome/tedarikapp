<?php

declare(strict_types=1);

namespace App\Services\Kur;

/**
 * GÜNCEL KUR KAYNAĞI (İE#21 B5) — TCMB günlük kur bülteni.
 *
 * NE YAPAR: TCMB'nin günlük XML bültenini çeker, USD ve CNY satış kurlarını
 * okur ve DÖNDÜRÜR. Hiçbir şey KAYDETMEZ.
 *
 * NEDEN KAYDETMEZ (K4, pazarlıksız): kur listeye kilitlenen bir ticari karardır;
 * arka planda kendiliğinden değişen bir kur, kullanıcının onaylamadığı bir fiyatı
 * belgeye basar. Bu yüzden bu sınıfın çıktısı FORMA doldurulur, kullanıcı görür,
 * onaylarsa kaydedilir. Zamanlanmış otomatik güncelleme YOKTUR ve eklenmeyecektir.
 *
 * NEDEN TCMB: resmî, ücretsiz, anahtarsız ve Türkiye'de ticari belgelerin
 * dayandığı kaynak. Bülten hafta içi ~15:30'da yayımlanır; hafta sonu ve resmî
 * tatillerde "bugün" dosyası YOKTUR — o zaman en son yayımlanan bülten okunur.
 *
 * K8: dış istek YALNIZ cURL ile yapılır (`file_get_contents` ile URL açmak yasak).
 * Adres SABİTTİR — kullanıcıdan adres alınmaz (SSRF kapısı açılmaz).
 */
final class KurKaynagi
{
    /** TCMB günlük bülten (bugün). */
    private const BUGUN = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    /** Bülten yayımlanmadıysa geriye doğru en fazla kaç gün aranır (tatil zinciri). */
    private const GERIYE_GUN = 7;

    public function __construct(private readonly int $zamanAsimi = 10)
    {
    }

    /**
     * @return array{
     *     ok: bool,
     *     yuan_tl?: string,
     *     usd_tl?: string,
     *     tarih?: string,
     *     kaynak?: string,
     *     hata?: string
     * }
     */
    public function getir(\DateTimeImmutable $now): array
    {
        $denenen = [];

        foreach ($this->adresler($now) as $adres) {
            $denenen[] = $adres;
            $xml = $this->indir($adres);
            if ($xml === null) {
                continue;
            }

            $kurlar = $this->ayristir($xml);
            if ($kurlar === null) {
                continue;
            }

            return [
                'ok' => true,
                'yuan_tl' => $kurlar['CNY'],
                'usd_tl' => $kurlar['USD'],
                'tarih' => $kurlar['tarih'],
                'kaynak' => 'TCMB',
            ];
        }

        // GÖRÜNÜR HATA (emir §B5): sessizce eski değerle devam etmek, kullanıcıya
        // "güncellendi" yalanını söylerdi. Ne denendiği de söylenir.
        return [
            'ok' => false,
            'hata' => sprintf(
                'TCMB kur bülteni okunamadı (%d adres denendi). Sunucunun dış bağlantısı '
                . 'kapalı olabilir ya da bülten henüz yayımlanmamış olabilir. Kuru elle girebilirsiniz.',
                count($denenen),
            ),
        ];
    }

    /**
     * Bugünün bülteni + geriye doğru tarihli bültenler.
     *
     * @return list<string>
     */
    private function adresler(\DateTimeImmutable $now): array
    {
        $adresler = [self::BUGUN];
        for ($i = 0; $i < self::GERIYE_GUN; $i++) {
            $gun = $now->modify(sprintf('-%d days', $i));
            // TCMB arşiv yolu: /kurlar/YYYYAA/GGAAYYYY.xml
            $adresler[] = sprintf(
                'https://www.tcmb.gov.tr/kurlar/%s/%s.xml',
                $gun->format('Ym'),
                $gun->format('dmY'),
            );
        }

        return $adresler;
    }

    /** K8: yalnız cURL, sabit adres, HTTPS zorunlu, yönlendirme kapalı. */
    private function indir(string $adres): ?string
    {
        $ch = curl_init();
        if ($ch === false) {
            return null;
        }

        $govde = '';
        curl_setopt_array($ch, [
            CURLOPT_URL => $adres,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->zamanAsimi,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->zamanAsimi),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'tedarikapp/1.0 (+kur)',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $parca) use (&$govde): int {
                // 1 MB üst sınır: bülten ~30 KB. Beklenmeyen büyük yanıt belleği yemez.
                if (strlen($govde) < 1_048_576) {
                    $govde .= $parca;
                }

                return strlen($parca);
            },
        ]);
        // PHP 8.1 uyumu (İE#9.7 dersi): CURLOPT_PROTOCOLS_STR 8.3+'tır.
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }

        $basarili = curl_exec($ch);
        $kod = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($basarili === false || $kod !== 200 || $govde === '') {
            return null;
        }

        return $govde;
    }

    /**
     * TCMB XML'inden USD ve CNY satış kurunu okur.
     *
     * TCMB iki fiyat verir: `ForexSelling` (döviz satış — havale/EFT) ve
     * `BanknoteSelling` (efektif satış — nakit). Ticari faturada kullanılan
     * DÖVİZ SATIŞ kurudur; CNY için efektif kur zaten çoğu gün BOŞ gelir.
     *
     * CNY birimi 1 DEĞİLDİR: bülten "1 Çin Yuanı" der ama `Unit` alanını 1 olarak
     * verir — yine de okunur ve bölünür; birim değişirse hesap kendiliğinden doğru kalır.
     *
     * @return array{USD: string, CNY: string, tarih: string}|null
     */
    private function ayristir(string $xml): ?array
    {
        $onceki = libxml_use_internal_errors(true);
        // LIBXML_NONET: dış varlık çözümlemesi ağa çıkmaz (XXE kalkanı).
        $belge = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        if ($belge === false) {
            return null;
        }

        $bulunan = [];
        foreach ($belge->Currency as $doviz) {
            $kod = strtoupper(trim((string) $doviz['CurrencyCode']));
            if ($kod !== 'USD' && $kod !== 'CNY') {
                continue;
            }

            $ham = trim((string) $doviz->ForexSelling);
            if ($ham === '') {
                $ham = trim((string) $doviz->BanknoteSelling);
            }
            if ($ham === '' || !is_numeric($ham)) {
                continue;
            }

            $birim = (int) trim((string) $doviz->Unit);
            $birim = $birim > 0 ? $birim : 1;

            // Para hesabı bcmath ile (CLAUDE.md §3): float'a düşmeden 4 haneye yuvarlanır.
            $bulunan[$kod] = bcdiv($ham, (string) $birim, 4);
        }

        if (!isset($bulunan['USD'], $bulunan['CNY'])) {
            return null;
        }

        $tarih = trim((string) $belge['Tarih']);

        return [
            'USD' => $bulunan['USD'],
            'CNY' => $bulunan['CNY'],
            'tarih' => $tarih !== '' ? $tarih : '—',
        ];
    }
}
