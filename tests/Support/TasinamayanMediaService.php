<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\MediaService;

/**
 * rc8/E1 — TAŞIMASI BAŞARISIZ OLAN MEDYA SERVİSİ.
 *
 * `commit()` gerçek hayatta izin kaybı, dolu disk ya da aynı anda silinen
 * dosya yüzünden false döner. Bu arıza gerçek dosya sisteminde taşınabilir
 * biçimde tetiklenemiyor: hedef adı rastgele 16 bayttan üretiliyor (önceden
 * bir engel konulamıyor) ve Windows'ta salt-okunur dizin `rename()`i
 * durdurmuyor. Süit bu yüzden SÖZLEŞMEYİ taklit eder: `.tmp` yerinde kalır,
 * dönüş false olur — üretimdeki başarısız `rename()` ile birebir aynı durum.
 */
final class TasinamayanMediaService extends MediaService
{
    public function commit(array $stored): bool
    {
        return false;
    }
}
