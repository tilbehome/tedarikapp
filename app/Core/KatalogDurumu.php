<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;
use Psr\Log\LoggerInterface;

/**
 * ÇALIŞMA ZAMANI KATALOGLARININ SAĞLIK DENETİMİ (K99).
 *
 * NEDEN VAR: v1.2.0'ın ilk paketinde bildirim ve panorama katalogları `docs/`
 * altındaydı ve `docs/` release paketine GİRMİYOR. Sonuç, bu projenin en
 * tehlikeli hata sınıfıydı — SESSİZ BAŞARISIZLIK: liste oluşturma, paylaşım,
 * kur onayı çalışmaya devam ediyor ama hiçbiri bildirim üretmiyordu ve kimse
 * bunu fark etmiyordu. Testler yeşildi çünkü testler repo kökünden koşuyor,
 * orada `docs/` var. Paket doğrulaması da yakalayamadı çünkü DOSYA SAYIYORDU,
 * dosyaları KULLANMIYORDU.
 *
 * ÇÖZÜM İKİ PARÇALI:
 *   1. Kataloglar `config/` altına taşındı (K99) — pakete giren dizin.
 *   2. Denetim UYGULAMA AÇILIŞINDA BİR KEZ yapılır (bu sınıf). Eksikse
 *      kritik log düşer, "Sistem durumu" ekranında kırmızı madde çıkar ve
 *      panorama ucu ANLAŞILIR bir hata döner.
 *
 * Denetim çağrı başına DEĞİL açılışta olmasının sebebi: her bildirim yayınında
 * dosya okumaya çalışmak hem sıcak yolu yavaşlatır hem de hatayı yüzlerce kez
 * loglar. Bir kez bakılır, sonuç taşınır.
 */
final class KatalogDurumu
{
    /**
     * Denetlenen kataloglar: kod adı => [görünen ad, göreli yol].
     *
     * @var array<string, array{ad: string, yol: string}>
     */
    private const KATALOGLAR = [
        'bildirim' => ['ad' => 'Bildirim olay kataloğu', 'yol' => '/config/bildirim-olay-katalogu.json'],
        'panorama' => ['ad' => 'Panorama brifing kataloğu', 'yol' => '/config/panorama-brifing-katalogu.json'],
    ];

    /** @var array<string, string> kod => hata mesajı (yalnız SORUNLU olanlar) */
    private array $sorunlar;

    public function __construct(string $kokDizin, ?LoggerInterface $logger = null)
    {
        $this->sorunlar = $this->denetle($kokDizin);

        if ($this->sorunlar !== [] && $logger !== null) {
            // KRİTİK seviye bilinçli: bu, "bir şey biraz ters" değil,
            // "bir özellik komple ölü" durumudur.
            $logger->critical('Çalışma zamanı kataloğu yüklenemedi — bağlı özellikler ÇALIŞMAZ', [
                'sorunlar' => $this->sorunlar,
                'karar' => 'K99',
            ]);
        }
    }

    public function saglikliMi(): bool
    {
        return $this->sorunlar === [];
    }

    /** Belirli bir katalog sağlıklı mı? */
    public function katalogSaglikli(string $kod): bool
    {
        return !array_key_exists($kod, $this->sorunlar);
    }

    /** Bir kataloğun hata mesajı; sağlıklıysa null. */
    public function hata(string $kod): ?string
    {
        return $this->sorunlar[$kod] ?? null;
    }

    /**
     * Ekrana basılacak döküm — "Sistem durumu" kartı bunu gösterir.
     *
     * SAĞLIKLI kataloglar da listelenir: kullanıcı neyin denetlendiğini
     * görmeli. Yalnız sorunları göstermek, boş bir listeyi "denetim yapılmadı"
     * ile "her şey yolunda" arasında ayırt edilemez kılardı.
     *
     * @return list<array{kod: string, ad: string, yol: string, saglikli: bool, hata: string|null}>
     */
    public function dokum(): array
    {
        $dokum = [];
        foreach (self::KATALOGLAR as $kod => $bilgi) {
            $dokum[] = [
                'kod' => $kod,
                'ad' => $bilgi['ad'],
                'yol' => ltrim($bilgi['yol'], '/'),
                'saglikli' => $this->katalogSaglikli($kod),
                'hata' => $this->hata($kod),
            ];
        }

        return $dokum;
    }

    /**
     * VARLIK YETMEZ, OKUNABİLİRLİK DE YETMEZ: dosya AYRIŞTIRILIR.
     *
     * Bozuk JSON, eksik dosyayla aynı sonucu verir (özellik çalışmaz) ama
     * çok daha sinsidir — dosya orada durduğu için "var" görünür. Açılış
     * denetimi ikisini de aynı ciddiyetle yakalar.
     *
     * @return array<string, string>
     */
    private function denetle(string $kokDizin): array
    {
        $sorunlar = [];

        foreach (self::KATALOGLAR as $kod => $bilgi) {
            $yol = $kokDizin . $bilgi['yol'];

            if (!is_file($yol)) {
                $sorunlar[$kod] = sprintf(
                    '%s bulunamadı: %s. Paket eksik kurulmuş olabilir.',
                    $bilgi['ad'],
                    ltrim($bilgi['yol'], '/'),
                );

                continue;
            }

            $ham = @file_get_contents($yol);
            if ($ham === false) {
                $sorunlar[$kod] = sprintf('%s okunamadı (dosya izinleri): %s', $bilgi['ad'], ltrim($bilgi['yol'], '/'));

                continue;
            }

            try {
                json_decode($ham, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $hata) {
                $sorunlar[$kod] = sprintf('%s bozuk JSON: %s', $bilgi['ad'], $hata->getMessage());
            }
        }

        return $sorunlar;
    }
}
