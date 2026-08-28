<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * BELGE MARKASI — antet, amblem ve filigran (İE#21 B13).
 *
 * Marka kiti (`docs/marka/`) belge için hazır varlıklar taşıyor: antet bandı, alt
 * bilgi bandı, amblem ve filigran. Bugüne kadar bunlar YALNIZ belgede duruyordu —
 * yani kit vardı, belgeler onu kullanmıyordu. Bu sınıf o boşluğu kapatır.
 *
 * VARLIKLAR `public/marka/belge/` ALTINDA: `docs/` sürüm paketine GİRMEZ
 * (bin/release.php yalnız app, bin, bootstrap, config, migrations, public, setup,
 * vendor taşır), dolayısıyla belgenin kullandığı görseller pakete giren bir yerde
 * durmalıdır. Tema renkleri de aynı nedenle `config/belge-tema.json`dadır.
 *
 * VARLIK YOKSA BELGE YİNE ÜRETİLİR (K50/K61): filigran ya da amblem eksikse o
 * öge basılmaz, üretim DURMAZ. Belge üretimi bir görselin varlığına bağlanamaz —
 * kullanıcı çıktının kendisini bekliyordur, süslemesini değil.
 *
 * ŞABLON PALETİ KAZANIR (bilinçli karar, ÇIKTI RAPORU'nda bildirildi): rev7
 * Excel/PDF şablonları PM onaylıdır ve lacivert/altın paleti taşır; marka kitinin
 * belge teması ise krem/turuncudur. İki paleti karıştırmak onaylı şablonu bozardı,
 * bu yüzden RENKLER `TemplateV2`de kalır; kitten alınan şey GÖRSEL VARLIKLARDIR
 * (amblem, filigran). Tema dosyası yine okunur — sayı biçimleri ve yazı tipi
 * adları için tek kaynak odur.
 */
final class BelgeMarkasi
{
    private const DIZIN = '/public/marka/belge/';

    /** @var array<string, mixed>|null */
    private ?array $tema = null;

    public function __construct(private readonly string $basePath)
    {
    }

    /** Amblem (yatay logo) dosya yolu — yoksa null. */
    public function amblem(): ?string
    {
        return $this->varlik('amblem.png');
    }

    /** Filigran (soluk marka işareti) dosya yolu — yoksa null. */
    public function filigran(): ?string
    {
        return $this->varlik('filigran.png');
    }

    /** Antet bandı görseli — yoksa null. */
    public function antet(): ?string
    {
        return $this->varlik('antet.png');
    }

    private function varlik(string $ad): ?string
    {
        $yol = $this->basePath . self::DIZIN . $ad;

        return is_file($yol) && is_readable($yol) ? $yol : null;
    }

    /**
     * Sayı biçimi (Excel): `config/belge-tema.json` → `numberFormat`.
     *
     * Tema okunamazsa güvenli varsayılan döner; bir JSON hatası yüzünden Excel
     * üretimi düşmemelidir.
     */
    public function sayiBicimi(string $ad, string $varsayilan): string
    {
        $tema = $this->tema();
        $bicimler = is_array($tema['numberFormat'] ?? null) ? $tema['numberFormat'] : [];
        $deger = $bicimler[$ad] ?? null;

        return is_string($deger) && $deger !== '' ? $deger : $varsayilan;
    }

    /** @return array<string, mixed> */
    private function tema(): array
    {
        if ($this->tema !== null) {
            return $this->tema;
        }

        $yol = $this->basePath . '/config/belge-tema.json';
        if (!is_file($yol) || !is_readable($yol)) {
            return $this->tema = [];
        }

        $ham = file_get_contents($yol);
        if ($ham === false) {
            return $this->tema = [];
        }

        $cozulmus = json_decode($ham, true);

        return $this->tema = is_array($cozulmus) ? $cozulmus : [];
    }
}
