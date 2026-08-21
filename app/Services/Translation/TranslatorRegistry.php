<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Çevirmen sağlayıcı kaydı (İE#14 A2 · K56 Katman 2 hazırlığı).
 *
 * Sağlayıcı `TRANSLATOR_PROVIDER` yapılandırmasından seçilir. BUGÜN tek uygulama
 * vardır (`katmanli` — sözlük + makine); V3-A'da LLM motoru eklendiğinde yalnız
 * BURAYA bir dal eklenir, çağıran kod (controller/panel/eklenti) DEĞİŞMEZ.
 *
 * Bilinmeyen ad verilirse sessizce varsayılana düşülür: yanlış yapılandırma
 * yüzünden çeviri tamamen kaybolmaz (K54 — öneri akışı bloklanmaz).
 */
final class TranslatorRegistry
{
    public const VARSAYILAN = 'katmanli';

    /** @return list<string> */
    public static function providers(): array
    {
        return [self::VARSAYILAN];
    }

    public static function make(string $provider, Glossary $glossary, TranslationService $machine): TranslatorInterface
    {
        return match ($provider) {
            // V3-A: 'llm' => new LlmTranslator(...) — arayüz aynı kalacak.
            default => new LayeredTranslator($glossary, $machine),
        };
    }
}
