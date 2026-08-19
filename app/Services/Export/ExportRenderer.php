<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * Snapshot → dosya baytları (İE#10 Blok 1).
 *
 * Her biçim (csv/xlsx/pdf) bu portu uygular. Girdi YALNIZ snapshot'tır: render'cı
 * DB'ye, kura, saate dokunmaz — aynı snapshot her zaman aynı içeriği üretir (K25).
 * Çıktı bellekte string'dir; dosya diske YAZILMAZ (K33/K44), akışla verilir.
 */
interface ExportRenderer
{
    /** Dosya uzantısı (xlsx/pdf/csv). */
    public function extension(): string;

    /** Content-Type başlığı. */
    public function mime(): string;

    /**
     * @param array<string, mixed> $snapshot ExportSnapshot::build çıktısı
     *
     * @throws ExportException üretim engellendiğinde (örn. geçici dizin yok)
     */
    public function render(array $snapshot): string;
}
