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
 *
 * **Neden `final` değil (rc8/E1):** `commit()`in başarısız `rename()` dalı
 * gerçek dosya sisteminde taşınabilir biçimde tetiklenemiyor — hedef adı
 * rastgele üretiliyor (önceden engel konulamaz) ve Windows'ta salt-okunur
 * dizin `rename()`i durdurmuyor. Süit sözleşmeyi taklit eden bir alt sınıf
 * verebilsin diye sınıf açık bırakıldı; ÜRETİMDE ALT SINIF YOKTUR.
 * İE#22 için not: doğru çözüm bir `MediaStore` arayüzü çıkarmaktır.
 */
class MediaService
{
    public const MODE_DOWNLOAD = 'download';
    public const MODE_HOTLINK = 'hotlink';
    public const SETTING_KEY = 'media_mode';

    /** docs/04 §2d: SVG YASAK — içine script gömülebilir ve tarayıcıda çalışır. */
    /** E7: decode ÖNCESİ piksel tavanı — 40 megapiksel (decompression bomb kalkanı). */
    private const MAX_PIKSEL = 40000000;

    private const ALLOWED_MIME = [
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

    /**
     * Etkin mod — K47: yazılabilirlikten OTOMATİK türetilir.
     *
     * alicdn Referer ACL hotlink'i kullanılamaz kıldığı için varsayılan mod ARŞİV'dir:
     * `public/media/` yazılabilir olduğu anda uygulama kendiliğinden arşiv moduna geçer,
     * yazılamazsa hotlink'e düşer. Ayardaki değer görünürlük/iz içindir; eski bir
     * "hotlink" kaydı yazılabilirlik geldiğinde modu ARTIK kilitlemez (canlı vaka:
     * izin sonradan açıldı, rozet kalkmıyordu).
     */
    public function mode(): string
    {
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
     * Adres SSRF beyaz listesinden geçiyor mu? (rc8-01 / F-16)
     *
     * `store()` indirmeden önce zaten denetler; bu metot İNDİRMEDEN denetlemek
     * içindir — galeri satırları kayda girerken indirilmez ama sonradan medya
     * işi tarafından indirilir ve kullanıcıya servis edilir.
     *
     * @throws MediaDeniedException adres reddedildiyse
     */
    public function assertAllowed(string $url): void
    {
        $this->guard->assertAllowed($url);
    }

    /**
     * ERTELENMİŞ YAZIM (İE#19 E7): dosya önce `.tmp` adına yazılır; kalıcı ada
     * ancak veritabanı işlemi COMMIT olunca taşınır.
     *
     * NEDEN: eskiden görsel diske yazılıyor, sonra ürün satırı yazılıyordu. Ürün
     * yazımı düşerse (kısıt ihlali, terminal liste, bağlantı kopması) dosya diskte
     * KALIYORDU: hiçbir kaydın işaret etmediği yetim medya. Paylaşımlı hostingde
     * bunlar sessizce kotayı yiyor ve hangi dosyanın çöp olduğu sonradan
     * anlaşılamıyordu. Artık yazım iki adımlıdır ve başarısız işlem dosya bırakmaz.
     *
     * @param array{mode: string, path: string|null, url: string, temp?: string|null} $stored
     */
    /**
     * @return bool kalıcı dosya YERİNDE mi? (rc8-01 / F-13)
     *
     * Eskiden dönüş yoktu ve `@rename()` hatası yutuluyordu: ürün kaydı diskte
     * bulunmayan bir `/media/...` adresine işaret etmeye devam ediyordu. Çağıran
     * artık sonucu görür ve kaydı kaynak adrese düşürebilir.
     *
     * Ertelenmemiş yazımda (temp yok) dosya zaten kalıcı adındadır; bu durumda
     * "yapacak iş yok" da bir BAŞARIDIR — çağıran için ayrım yoktur.
     *
     * @param array{mode: string, path: string|null, url: string, temp?: string|null} $stored
     */
    public function commit(array $stored): bool
    {
        $temp = $stored['temp'] ?? null;
        if (!is_string($temp) || $temp === '' || !is_string($stored['path'])) {
            return true;
        }

        $hedef = $this->basePath . '/' . $stored['path'];
        $tempMutlak = $this->basePath . '/' . $temp;
        if (!is_file($tempMutlak)) {
            // Geçici dosya yok: ya zaten taşınmış (idempotent çağrı) ya da hiç
            // yazılamamış. Kalıcı dosyanın VARLIĞI belirler.
            return is_file($hedef);
        }

        if (@rename($tempMutlak, $hedef) === false) {
            return false;
        }
        @chmod($hedef, 0644);

        return is_file($hedef);
    }

    /**
     * İşlem geri sarıldı: geçici dosya SİLİNİR (yetim medya bırakılmaz).
     *
     * @param array{mode: string, path: string|null, url: string, temp?: string|null} $stored
     */
    public function discard(array $stored): void
    {
        $temp = $stored['temp'] ?? null;
        if (is_string($temp) && $temp !== '' && is_file($this->basePath . '/' . $temp)) {
            @unlink($this->basePath . '/' . $temp);
        }
    }

    /**
     * Görseli sisteme alır.
     *
     * @param bool $ertele true ise dosya `.tmp` adına yazılır; kalıcı ada `commit()` taşır
     *
     * @return array{mode: string, path: string|null, url: string, temp: string|null} `path` yalnız download modunda dolu
     *
     * @throws MediaException
     */
    public function store(string $url, bool $ertele = false): array
    {
        // SSRF denetimi HER modda yapılır: hotlink modunda da bu URL kullanıcıya servis edilecek.
        $this->guard->assertAllowed($url);

        if ($this->mode() === self::MODE_HOTLINK) {
            return ['mode' => self::MODE_HOTLINK, 'path' => null, 'url' => $url, 'temp' => null];
        }

        $fetched = $this->fetcher->fetch($url, $this->maxBytes);

        // Yönlendirme sonrası ULAŞILAN adres de denetlenir — cURL'ün kör takibi kullanılmaz.
        if ($fetched['final_url'] !== $url) {
            $this->guard->assertAllowed($fetched['final_url']);
        }

        $detected = $this->assertImage($fetched['body'], $fetched['content_type']);
        $encoded = $this->reencode($fetched['body'], $detected);

        // K37 §C8: dosya adı UZANTISINI kaynak değil, yeniden kodlama SONUCU belirler —
        // webp/avif encoder yoksa jpeg'e düşülür ve ad da .jpg olur; uzantı-içerik
        // uyumsuzluğu yapısal olarak imkânsızdır.
        $name = bin2hex(random_bytes(16)) . '.' . $encoded['extension'];
        $relative = trim($this->mediaPath, '/') . '/' . $name;
        // Ertelenmiş modda kalıcı ad ÖNCEDEN bilinir (URL kayda o adla yazılır),
        // içerik ise `.tmp` uzantısında bekler; taşıma commit'te yapılır.
        $yazilacak = $ertele ? $relative . '.tmp' : $relative;

        if (@file_put_contents($this->basePath . '/' . $yazilacak, $encoded['bytes']) === false) {
            // Yazma anında izin kaybolduysa hotlink'e düş; ürün kaydı yine de oluşsun.
            $this->rememberMode(self::MODE_HOTLINK);

            return ['mode' => self::MODE_HOTLINK, 'path' => null, 'url' => $url, 'temp' => null];
        }
        @chmod($this->basePath . '/' . $yazilacak, 0644);

        return [
            'mode' => self::MODE_DOWNLOAD,
            'path' => $relative,
            'url' => $this->publicUrl($relative),
            'temp' => $ertele ? $relative . '.tmp' : null,
        ];
    }

    /**
     * K47 VEKİLİ İÇİN GETİRİR — indirme hattıyla AYNI denetimler, diske yazmaz (D2).
     *
     * Sıra `store()` ile birebir: beyaz liste + DNS pinleme (guard), indirme
     * (boyut sınırı fetcher'da), yönlendirme sonrası adres denetimi, GERÇEK
     * görsel imzası. Yeniden kodlama YAPILMAZ: vekil bir "geçici gösterim"dir,
     * her istekte GD çalıştırmak paylaşımlı hostingde gereksiz yüktür; imza
     * denetimi tarayıcıya görsel dışı içerik gitmemesini zaten garanti eder.
     *
     * @return array{body: string, mime: string}
     *
     * @throws MediaDeniedException beyaz liste / güvenlik reddi
     * @throws MediaException      ağ hatası ya da görsel olmayan içerik
     */
    public function vekilGetir(string $url): array
    {
        $this->guard->assertAllowed($url);

        $fetched = $this->fetcher->fetch($url, $this->maxBytes);
        if ($fetched['final_url'] !== $url) {
            $this->guard->assertAllowed($fetched['final_url']);
        }

        $uzanti = $this->assertImage($fetched['body'], $fetched['content_type']);
        $mime = (string) array_search($uzanti, self::ALLOWED_MIME, true);

        return ['body' => $fetched['body'], 'mime' => $mime !== '' ? $mime : 'image/jpeg'];
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

        // İE#19 E7 — PİKSEL SINIRI, DECODE'DAN ÖNCE.
        //
        // Bayt sınırı (MEDIA_MAX_MB) tek başına yetmez: "decompression bomb" denen
        // görseller birkaç yüz KB'dir ama 30.000 × 30.000 piksel açılır. GD bunu
        // belleğe açmaya kalkınca PHP'nin memory_limit'i patlar ve süreç ÖLÜR —
        // paylaşımlı hostingde bu, tek bir yakalamayla siteyi düşürmek demektir.
        // Boyut bilgisi başlıktan okunur; karar GD'yi hiç çağırmadan verilir.
        $piksel = (int) $info[0] * (int) $info[1];
        if ($piksel > self::MAX_PIKSEL) {
            throw new MediaException(sprintf(
                'Görsel çok büyük: %d × %d piksel (üst sınır %d megapiksel).',
                (int) $info[0],
                (int) $info[1],
                intdiv(self::MAX_PIKSEL, 1000000),
            ));
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
     * K37 §C8: dönüş her zaman TUTARLI üçlüdür {bytes, extension, mime}. Encoder
     * eksikliğinde (webp/avif derlenmemiş GD) jpeg'e düşülür ve uzantı/mime de
     * jpeg olarak döner — çağıran asla "webp adında jpeg" üretemez.
     *
     * @return array{bytes: string, extension: string, mime: string}
     *
     * @throws MediaException
     */
    private function reencode(string $body, string $extension): array
    {
        $image = @imagecreatefromstring($body);
        if ($image === false) {
            throw new MediaException('Görsel çözümlenemedi.');
        }

        if ($extension === 'webp' && !function_exists('imagewebp')) {
            $extension = 'jpg';
        }
        if ($extension === 'avif' && !function_exists('imageavif')) {
            $extension = 'jpg';
        }

        ob_start();
        $ok = match ($extension) {
            'png' => imagepng($image, null, 8),
            'gif' => imagegif($image),
            'webp' => imagewebp($image, null, 85),
            'avif' => imageavif($image, null, 70),
            default => imagejpeg($image, null, 88),
        };
        $output = (string) ob_get_clean();

        if (!$ok || $output === '') {
            throw new MediaException('Görsel yeniden kodlanamadı.');
        }

        $mime = array_search($extension, self::ALLOWED_MIME, true);

        return [
            'bytes' => $output,
            'extension' => $extension,
            'mime' => $mime === false ? 'image/jpeg' : $mime,
        ];
    }

    public function directory(): string
    {
        return $this->basePath . '/' . trim($this->mediaPath, '/');
    }

    // ─────────────── Yaşam döngüsü — silme ve GC (K37 §C7) ───────────────

    /** Sunucu üretimi medya adları: 32 hex + bilinen görsel uzantısı. Başka HİÇBİR AD silinmez. */
    private const FILE_NAME_PATTERN = '/^[0-9a-f]{32}\.(jpg|png|gif|webp|avif)$/';

    /**
     * Referanstan (DB'deki `main_image` web yolu veya `product_images.path` disk yolu)
     * yerel medya dosya adını çıkarır. Hotlink URL'leri ve desen dışı her şey null döner —
     * bu yöntem aynı zamanda path-traversal kalkanıdır: yalnızca BİZİM ürettiğimiz
     * adlandırma kalıbındaki dosyalar silinebilir.
     */
    public function fileNameFor(?string $reference): ?string
    {
        if (!is_string($reference) || $reference === '') {
            return null;
        }

        $diskPrefix = trim($this->mediaPath, '/') . '/';
        $webPrefix = '/' . (preg_replace('#^public/#', '', trim($this->mediaPath, '/')) ?? '') . '/';

        $name = null;
        if (str_starts_with($reference, $webPrefix)) {
            $name = substr($reference, strlen($webPrefix));
        } elseif (str_starts_with($reference, $diskPrefix)) {
            $name = substr($reference, strlen($diskPrefix));
        }

        if ($name === null || preg_match(self::FILE_NAME_PATTERN, $name) !== 1) {
            return null;
        }

        return $name;
    }

    /** Doğrulanmış adlı medya dosyasını diskten siler. */
    public function deleteFile(string $fileName): bool
    {
        if (preg_match(self::FILE_NAME_PATTERN, $fileName) !== 1) {
            return false;
        }

        $path = $this->directory() . '/' . $fileName;

        return is_file($path) && @unlink($path);
    }

    /**
     * Medya klasöründeki, verilen ada listesinde OLMAYAN sunucu-üretimi dosyaları siler.
     * `.htaccess` ve desen dışı dosyalara dokunulmaz.
     *
     * @param list<string> $referencedNames korunacak dosya adları
     *
     * @return list<string> silinen dosya adları
     */
    public function purgeOrphanFiles(array $referencedNames): array
    {
        $keep = array_fill_keys($referencedNames, true);
        $deleted = [];

        foreach (glob($this->directory() . '/*') ?: [] as $file) {
            $name = basename($file);
            if (preg_match(self::FILE_NAME_PATTERN, $name) !== 1 || isset($keep[$name])) {
                continue;
            }
            if (@unlink($file)) {
                $deleted[] = $name;
            }
        }

        return $deleted;
    }
}
