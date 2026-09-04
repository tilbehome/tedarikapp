<?php

declare(strict_types=1);

namespace App\Services\Yanit;

/**
 * YAPIŞTIR-AYRIŞTIR (V3-C Aşama 2.2 · #28-EK · altın set kabul kapısı).
 *
 * Ürün Sahibi firmadan WhatsApp/e-posta ile gelen ham cevabı yapıştırır;
 * çıktı bir ÖNİZLEMEDİR — hiçbir şey DB'ye yazılmaz. Sahip satır satır
 * bakıp uygular (`YanitUygulayici`).
 *
 * Üç çıktı listesi:
 *   · `eslesmeler`         — koda/eşsiz ada bağlanmış satırlar + alanları
 *   · `belirsiz`           — bağlanamayan parçalar (aday listesi + neden + YASAK işlem)
 *   · `dogrulama_hatalari` — bağlandı ama kural dışı (fiyat 0, MOQ 0, termin>365, brüt<net, çakışan kademe)
 *
 * Belirsiz parça asla bir satıra yazılmaz; "en olası" tahmini yoktur. Bu,
 * altın setin kritik kuralıdır: yanlış ürüne fiyat yazmak tek olayda rettir.
 */
final class YapistirAyristirici
{
    public function __construct(
        private readonly SatirEslestirici $eslestirici = new SatirEslestirici(),
        private readonly AlanCikarici $cikarici = new AlanCikarici(),
    ) {
    }

    /**
     * @param  list<array{satir_id: string, kod: string, adlar: list<string>}> $baglam
     * @return array{
     *     eslesmeler: list<array<string, mixed>>,
     *     belirsiz: list<array{parca: string, aday_satir_idleri: list<string>, neden: string, yasak_otomatik_islem: string}>,
     *     dogrulama_hatalari: list<array{satir_id: string, alan: string, deger: mixed, kural: string}>
     * }
     */
    public function ayristir(string $metin, array $baglam): array
    {
        $satirlar = MetinNormalizer::satirlar($metin);
        $bolme = $this->eslestirici->bol($satirlar, $baglam);

        $eslesmeler = [];
        $belirsiz = $bolme['belirsiz'];
        $hatalar = [];

        foreach ($bolme['bolumler'] as $satirId => $parca) {
            if (trim($parca) === '') {
                continue;
            }
            $alanlar = $this->cikarici->cikar($parca);
            foreach ($alanlar['belirsiz'] as $b) {
                $belirsiz[] = $b + ['aday_satir_idleri' => [$satirId]];
            }
            foreach ($alanlar['hatalar'] as $h) {
                $hatalar[] = ['satir_id' => $satirId] + $h;
            }
            unset($alanlar['belirsiz'], $alanlar['hatalar']);
            $eslesmeler[] = ['satir_id' => $satirId, 'kaynak' => $parca] + $alanlar;
        }

        return [
            'eslesmeler' => $eslesmeler,
            'belirsiz' => array_map(static fn (array $b): array => [
                'parca' => $b['parca'],
                'aday_satir_idleri' => $b['aday_satir_idleri'],
                'neden' => $b['neden'],
                'yasak_otomatik_islem' => $b['yasak_otomatik_islem'],
            ], $belirsiz),
            'dogrulama_hatalari' => $hatalar,
        ];
    }
}
