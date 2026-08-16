<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingsRepository;

/**
 * Ürün görsellerinin sunucuya alınması (K6, K33, docs/04 §2d).
 *
 * **Çift mod (K33):** üretim sunucusunda PHP diske yazamıyor (`nobody`, DSO).
 *  • `download` — `public/media/` yazılabiliyorsa: indir → GD ile YENİDEN KODLA → rastgele ad.
 *  • `hotlink`  — yazılamıyorsa: indirme denenmez, orijinal URL saklanır.
 * Mod ayarı veritabanındadır ve `/api/system/status` üzerinden panele açılır.
 *
 * **Neden yeniden kodlama:** indirilen dosya "görsel gibi görünen" bir şey olabilir
 * (EXIF içine gömülü PHP, polyglot dosya). GD ile yeniden üretilen çıktı, kaynağın
 * baytlarından bağımsız TEMİZ bir görseldir; metadata da bu sırada düşer.
 *
 * **Neden rastgele ad:** kaynak dosya adı saldırgan kontrolündedir (`../`, `.php`,
 * çok uzun ad). Ad sunucuda üretilir, kaynaktan hiçbir şey taşınmaz.
 */
final class MediaService
{
    public const string MODE_DOWNLOAD = 'download';
    public const string MODE_HOTLINK = 'hotlink';
    public const string SETTING_KEY = 'media_mode';

    /** docs/04 §2d: SVG YASAK — içine script gömülebilir ve tarayıcıda çalışır. */
    private const array ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly UrlGuard $guard,
        private readonly MediaFetcher $fetcher,
        private readonly SettingsRepository $settings,
        private readonly int $maxBytes,
        private readonly string $mediaPath = 'public/media',
    ) {
    }

    /** Etkin mod: ayarda yazılıysa o, yoksa yazılabilirlikten türetilir. */
    public function mode(): string
    {
        $stored = $this->settings->get(self::SETTING_KEY);
        if ($stored === self::MODE_DOWNLOAD || $stored === self::MODE_HOTLINK) {
            return $stored;
        }

        return $this->detectMode();
    }

    /** `public/media/` gerçekten yazılabiliyor mu? */
    public function detectMode(): string
    {
        return $this->isWritable() ? self::MODE_DOWNLOAD : self::MODE_HOTLINK;
    }

    public function isWritable(): bool
    {
        $directory = $this->directory();

        return is_dir($directory) && is_writable($directory);
    }

    /** Modu ayara yazar (kurulum sihirbazı ve ayarlar ekranı kullanır). */
    public function rememberMode(string $mode): void
    {
        if ($mode !== self::MODE_DOWNLOAD && $mode !== self::MODE_HOTLINK) {
            throw new MediaException('Bilinmeyen medya modu: ' . $mode);
        }
        $this->settings->set(self::SETTING_KEY, $mode);
    }

    /**
     * Görseli sisteme alır.
     *
     * @return array{mode: string, path: string|null, url: string} `path` yalnız download modunda dolu
     *
     * @throws MediaException
     */
    public function store(string $url): array
    {
        // SSRF denetimi HER modda yapılır: hotlink modunda da bu URL kullanıcıya servis edilecek.
        $this->guard->assertAllowed($url);

        if ($this->mode() === self::MODE_HOTLINK) {
            return ['mode' => self::MODE_HOTLINK, 'path' => null, 'url' => $url];
        }

        $fetched = $this->fetcher->fetch($url, $this->maxBytes);

        // Yönlendirme sonrası ULAŞILAN adres de denetlenir — cURL'ün kör takibi kullanılmaz.
        if ($fetched['final_url'] !== $url) {
            $this->guard->assertAllowed($fetched['final_url']);
        }

        $extension = $this->assertImage($fetched['body'], $fetched['content_type']);
        $encoded = $this->reencode($fetched['body'], $extension);

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $relative = trim($this->mediaPath, '/') . '/' . $name;

        if (@file_put_contents($this->basePath . '/' . $relative, $encoded) === false) {
            // Yazma anında izin kaybolduysa hotlink'e düş; ürün kaydı yine de oluşsun.
            $this->rememberMode(self::MODE_HOTLINK);

            return ['mode' => self::MODE_HOTLINK, 'path' => null, 'url' => $url];
        }
        @chmod($this->basePath . '/' . $relative, 0644);

        return ['mode' => self::MODE_DOWNLOAD, 'path' => $relative, 'url' => $this->publicUrl($relative)];
    }

    /**
     * Diskteki göreli yolu tarayıcının göreceği adrese çevirir.
     *
     * Dosya `<kök>/public/media/…` altında durur ama Apache'nin docroot'u `public/`tir;
     * adrese `public/` önekiyle dönmek panelde 404 demektir. Önek burada düşürülür.
     */
    private function publicUrl(string $relative): string
    {
        $webPath = preg_replace('#^public/#', '', $relative) ?? $relative;

        return '/' . ltrim($webPath, '/');
    }

    /**
     * Gerçek tür DOSYANIN İÇİNDEN okunur; sunucunun bildirdiği Content-Type tek başına
     * yeterli sayılmaz (yalan söyleyebilir).
     *
     * @throws MediaException
     */
    private function assertImage(string $body, string $contentType): string
    {
        if ($body === '') {
            return throw new MediaException('Boş dosya indirildi.');
        }

        $info = @getimagesizefromstring($body);
        if ($info === false) {
            throw new MediaException('İndirilen dosya geçerli bir görsel değil.');
        }

        $realMime = strtolower($info['mime']);
        if (!array_key_exists($realMime, self::ALLOWED_MIME)) {
            throw new MediaException(sprintf('Bu görsel türü kabul edilmiyor: %s', $realMime));
        }

        $declared = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($declared !== '' && $declared !== $realMime && !str_starts_with($declared, 'image/')) {
            throw new MediaException('Dosyanın gerçek türü bildirilen türle uyuşmuyor.');
        }

        return self::ALLOWED_MIME[$realMime];
    }

    /**
     * GD ile yeniden üretir — çıktı kaynağın baytlarından BAĞIMSIZDIR.
     *
     * @throws MediaException
     */
    private function reencode(string $body, string $extension): string
    {
        $image = @imagecreatefromstring($body);
        if ($image === false) {
            throw new MediaException('Görsel çözümlenemedi.');
        }

        ob_start();
        $ok = match ($extension) {
            'png' => imagepng($image, null, 8),
            'gif' => imagegif($image),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, 85) : imagejpeg($image, null, 88),
            'avif' => function_exists('imageavif') ? imageavif($image, null, 70) : imagejpeg($image, null, 88),
            default => imagejpeg($image, null, 88),
        };
        $output = (string) ob_get_clean();

        if (!$ok || $output === '') {
            throw new MediaException('Görsel yeniden kodlanamadı.');
        }

        return $output;
    }

    public function directory(): string
    {
        return $this->basePath . '/' . trim($this->mediaPath, '/');
    }
}
