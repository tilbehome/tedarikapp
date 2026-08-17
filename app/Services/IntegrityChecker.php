<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Kurulum bütünlüğü denetimi (İE#9.3 / K43).
 *
 * Release zip'i köke bir MANIFEST.txt koyar (bin/release.php üretir): her dosyanın
 * yolu + sha256'sı. Bu sınıf sunucudaki dosyaları o manifeste karşı doğrular —
 * "vendor/ eksik açıldı", "setup/ yüklenmedi" sınıfı hatalar artık sessiz NOT_FOUND
 * değil, İSİM İSİM eksik listesi olarak raporlanır (`GET /api/system/integrity`,
 * sihirbazın gereksinim adımı da gösterir).
 *
 * SIR KURALI: çıktı yalnız göreli dosya yolları taşır — içerik/sır yok.
 * Manifest'te .env ASLA yer almaz (release script yazmaz).
 */
final class IntegrityChecker
{
    public const MANIFEST_FILE = 'MANIFEST.txt';

    /** Yanıtı şişirmemek için liste başına üst sınır; kalanı sayıyla raporlanır. */
    private const LIST_LIMIT = 50;

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @return array{
     *     manifest_exists: bool,
     *     ok: bool,
     *     total: int,
     *     checked: int,
     *     missing: list<string>,
     *     missing_count: int,
     *     modified: list<string>,
     *     modified_count: int,
     *     message: string
     * }
     */
    public function check(): array
    {
        $manifestPath = $this->basePath . '/' . self::MANIFEST_FILE;

        if (!is_file($manifestPath)) {
            return [
                'manifest_exists' => false,
                'ok' => true,
                'total' => 0,
                'checked' => 0,
                'missing' => [],
                'missing_count' => 0,
                'modified' => [],
                'modified_count' => 0,
                'message' => 'MANIFEST.txt yok — geliştirme kurulumu veya release öncesi sürüm; '
                    . 'bütünlük denetimi yalnız release zip kurulumlarında çalışır.',
            ];
        }

        $entries = self::parseManifest((string) file_get_contents($manifestPath));

        $missing = [];
        $missingCount = 0;
        $modified = [];
        $modifiedCount = 0;
        $checked = 0;

        foreach ($entries as [$hash, $relative]) {
            $absolute = $this->basePath . '/' . $relative;
            $checked++;

            if (!is_file($absolute)) {
                $missingCount++;
                if (count($missing) < self::LIST_LIMIT) {
                    $missing[] = $relative;
                }

                continue;
            }
            if (!hash_equals($hash, (string) hash_file('sha256', $absolute))) {
                $modifiedCount++;
                if (count($modified) < self::LIST_LIMIT) {
                    $modified[] = $relative;
                }
            }
        }

        $ok = $missingCount === 0 && $modifiedCount === 0;

        return [
            'manifest_exists' => true,
            'ok' => $ok,
            'total' => count($entries),
            'checked' => $checked,
            'missing' => $missing,
            'missing_count' => $missingCount,
            'modified' => $modified,
            'modified_count' => $modifiedCount,
            'message' => $ok
                ? sprintf('Bütünlük tamam: %d dosya doğrulandı.', $checked)
                : sprintf(
                    'Kurulum EKSİK/BOZUK: %d dosya eksik, %d dosya beklenenden farklı. '
                    . 'Release zip\'ini eksiksiz ve üzerine yazarak yeniden açın.',
                    $missingCount,
                    $modifiedCount,
                ),
        ];
    }

    /**
     * Manifest satırlarını çözer. Biçim: `<sha256>  <göreli yol>`; `#` yorumdur.
     *
     * @return list<array{string, string}>
     */
    public static function parseManifest(string $content): array
    {
        $entries = [];
        foreach (preg_split('/\r?\n/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^([0-9a-f]{64})\s{2}(.+)$/', $line, $match) === 1) {
                $entries[] = [$match[1], $match[2]];
            }
        }

        return $entries;
    }

    /** Release script'in yazdığı satır biçimi — parse ile TEK kaynak. */
    public static function manifestLine(string $sha256, string $relativePath): string
    {
        return $sha256 . '  ' . $relativePath;
    }
}
