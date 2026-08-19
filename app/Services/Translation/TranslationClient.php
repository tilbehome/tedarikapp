<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Çeviri sağlayıcısı sözleşmesi (İE#13 C1).
 *
 * Uygulamalar AĞA ÇIKAR; bu yüzden hata durumunda İSTİSNA FIRLATMAZ, `null` döner —
 * çeviri bir öneridir (K54), akışı bloklamaz. Arayüz sayesinde servis katmanı ağ
 * olmadan test edilir.
 */
interface TranslationClient
{
    /** @return string|null çeviri metni; sağlayıcı yanıt vermediyse/kota bittiyse null */
    public function translate(string $text, string $sourceLang, string $targetLang): ?string;

    /** Kayıtlarda ve API yanıtında görünen sağlayıcı adı (sır içermez). */
    public function name(): string;
}
