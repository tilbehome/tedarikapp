<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

use InvalidArgumentException;

/**
 * BİRLEŞTİRME ANAHTARI ÇÖZÜCÜSÜ (V3-B A2).
 *
 * Katalogdaki `grup_anahtari` alanı bir METİNDİR: `"kullanici_id+platform"`.
 * Bu metnin ne anlama geldiği hiçbir yerde kodlanmamıştı. Çözücü olmazsa her
 * çağrı noktası kendi yorumunu üretir; iki yer aynı olayı farklı anahtarla
 * yazar ve birleştirme SESSİZCE çalışmaz — bildirim merkezi aynı satırı on kez
 * gösterir, kimse bunun bir hata olduğunu anlamaz.
 *
 * Sözleşme üç maddedir:
 *   1. İfade `+` ile ayrılmış ATOMLARDAN oluşur.
 *   2. Her atom, yayıncıya verilen bağlam dizisinden okunur.
 *   3. Bilinmeyen atom SESSİZCE geçilmez — istisna atar. Katalogda olup burada
 *      karşılığı olmayan bir atom, BildirimAnahtarKatalogTest'i kırmızıya
 *      düşürür (TestSuiteKapsamiTest kalıbı: kayıt dışı kalan şey, çalışmayan
 *      şeydir).
 *
 * Eksik DEĞER (atom tanımlı ama bağlamda yok) istisna DEĞİLDİR: `-` yazılır.
 * Sebep: `platform` bilinmeyen bir yakalamada bildirim üretmemek, bildirimi
 * yanlış anahtarla üretmekten daha kötüdür — olay tamamen kaybolur.
 */
final class GrupAnahtariCozucu
{
    /**
     * Katalogda geçen TÜM atomlar. Yeni bir olay yeni bir atom getirirse
     * buraya eklenmeli — eklenmezse bekçi test kırmızı olur.
     *
     * @var list<string>
     */
    public const ATOMLAR = [
        'cihaz_id',
        'dil',
        'firma_id',
        'hata_kodu',
        'ip_hash',
        'is_turu',
        'istemci_id',
        'kaynak',
        'kullanici_id',
        'kural',
        'liste_id',
        'paylasim_id',
        'platform',
        'sekme_kodu',
    ];

    /** Değeri bağlamda bulunmayan atom için yazılan işaret. */
    public const BOS = '-';

    /**
     * `"a+b"` ifadesini bağlamdan çözer.
     *
     * @param  array<string, scalar|null> $baglam
     * @throws InvalidArgumentException bilinmeyen atom
     */
    public function coz(string $ifade, array $baglam): string
    {
        $parcalar = [];

        foreach ($this->atomlari($ifade) as $atom) {
            $deger = $baglam[$atom] ?? null;
            $parcalar[] = $deger === null || $deger === '' ? self::BOS : (string) $deger;
        }

        // 190 karakter sınırı VARCHAR(190) kolonundan gelir; uzun değer
        // (örneğin ip_hash) kısaltılırsa çakışma riski doğar, bu yüzden
        // aşan anahtar özetlenir — özet deterministiktir, birleştirme çalışır.
        $anahtar = implode('|', $parcalar);

        return mb_strlen($anahtar) <= 190 ? $anahtar : substr(hash('sha256', $anahtar), 0, 64);
    }

    /**
     * İfadedeki atomları döndürür; bilinmeyen atomda PATLAR.
     *
     * @return list<string>
     * @throws InvalidArgumentException
     */
    public function atomlari(string $ifade): array
    {
        $atomlar = [];

        foreach (explode('+', $ifade) as $ham) {
            $atom = trim($ham);
            if ($atom === '') {
                continue;
            }
            if (!in_array($atom, self::ATOMLAR, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Bilinmeyen birleştirme atomu "%s" (ifade: "%s"). '
                    . 'GrupAnahtariCozucu::ATOMLAR listesine eklenmeli.',
                    $atom,
                    $ifade,
                ));
            }
            $atomlar[] = $atom;
        }

        if ($atomlar === []) {
            throw new InvalidArgumentException('Boş grup anahtarı ifadesi.');
        }

        return $atomlar;
    }

    /** Bu ifade bütünüyle çözülebiliyor mu? (bekçi test bunu kullanır) */
    public function cozulebilirMi(string $ifade): bool
    {
        try {
            $this->atomlari($ifade);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
