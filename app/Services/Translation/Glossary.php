<?php

declare(strict_types=1);

namespace App\Services\Translation;

/**
 * Yerel sözlük (İE#14 A2 · K56 KATMAN 1) — dosya tabanlı, belirlenimci çeviri.
 *
 * Kapalı kümeler (malzeme, renk, menşe, birim, ambalaj, sertifika) burada çevrilir:
 * aynı terim HER YERDE aynı karşılığı alır. Dış servise gitmez, kotası yoktur,
 * sonucu tekrarlanabilirdir — belgede "Paslanmaz çelik" bir gün "Paslanmaz Çelik" olmaz.
 *
 * ÇOK DİLLİ (Ürün Sahibi talebi): kaynak dil başına bir dosya —
 * `config/sozluk-zh-tr.php` (1688/Taobao) ve `config/sozluk-en-tr.php`
 * (Alibaba.com, AliExpress, Amazon — F37 ile gelecek kaynaklar). Dil metinden
 * OTOMATİK saptanır: CJK karakter varsa `zh`, yoksa `en`. Yeni bir kaynak dil
 * gerektiğinde tek iş `sozluk-<dil>-tr.php` dosyasını eklemektir.
 *
 * ÇEVRİLMEYENLER (K56 ortak kuralı): marka adı, model/stok kodu, ölçü-sayı-birim
 * içeren değerler, ilan numarası — `translatable()` bunları eler.
 *
 * YAZMA YERİ (K44 / CLAUDE.md §2 — uygulama YALNIZ storage/ altına yazar):
 * `config/sozluk-<dil>-tr.php` depoyla gelen SALT OKUNUR varsayılandır; panelden
 * yapılan değişiklikler `storage/sozluk-<dil>-tr.php` dosyasına yazılır ve okuma
 * sırasında varsayılanın ÜZERİNE biner. Bunun ikinci bir faydası daha var: sürüm
 * güncellemesi `config/` içeriğini tazelese bile kullanıcının terimleri SİLİNMEZ.
 * Migration yoktur; sözlük veri değil yapılandırmadır.
 */
final class Glossary
{
    public const DILLER = ['zh', 'en'];

    /** @var array<string, array<string, string>> dil → (terim → karşılık) */
    private array $onbellek = [];

    /**
     * @param string      $configDir  depoyla gelen varsayılan sözlükler (SALT OKUNUR)
     * @param string|null $storageDir panelden yazılan üstyazım dosyaları; null ise
     *                                yazma kapalıdır (yalnız varsayılan okunur)
     */
    public function __construct(
        private readonly string $configDir,
        private readonly ?string $storageDir = null,
    ) {
    }

    /**
     * SÖZLÜK SÜRÜMÜ (İE#21 B12) — içeriğin özeti, dosya zamanı DEĞİL.
     *
     * Önbellek anahtarına bu girer: sözlüğe terim eklenince eski çeviriler
     * geçersizleşmelidir. Neden mtime değil: dosyayı açıp kapatmak, kopyalamak
     * ya da deploy etmek zamanı değiştirir ama çeviriyi değiştirmez — bütün
     * önbelleği boşuna çöpe atardık. İçerik özeti yalnız GERÇEK değişimde döner.
     *
     * Tüm diller birlikte özetlenir: LLM isteğine hepsi gömülür, dolayısıyla
     * herhangi birindeki değişiklik çıktıyı etkileyebilir.
     */
    public function surum(): string
    {
        $parcalar = [];
        foreach (self::DILLER as $dil) {
            $terimler = $this->all($dil);
            ksort($terimler);
            $parcalar[] = $dil . ':' . count($terimler) . ':' . hash('sha256', serialize($terimler));
        }

        return substr(hash('sha256', implode('|', $parcalar)), 0, 12);
    }

    /** Metnin kaynak dili: CJK varsa zh, yoksa en. */
    public static function detect(string $metin): string
    {
        return preg_match('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u', $metin) === 1 ? 'zh' : 'en';
    }

    /** Depoyla gelen varsayılan sözlük (salt okunur). */
    public function path(string $dil): string
    {
        return $this->configDir . '/sozluk-' . $dil . '-tr.php';
    }

    /** Panelden yazılan üstyazım dosyası (storage/ altında — K44). */
    public function overridePath(string $dil): ?string
    {
        return $this->storageDir === null ? null : $this->storageDir . '/sozluk-' . $dil . '-tr.php';
    }

    /**
     * Bir dilin tüm sözlüğü — LLM isteğine gömülmek üzere de dışa verilir (K56 Katman 2).
     *
     * @return array<string, string>
     */
    public function all(string $dil = 'zh'): array
    {
        if (!in_array($dil, self::DILLER, true)) {
            return [];
        }
        if (isset($this->onbellek[$dil])) {
            return $this->onbellek[$dil];
        }

        // Varsayılan + üstyazım: aynı terim iki dosyada da varsa PANELDEKİ kazanır.
        $temiz = self::oku($this->path($dil));
        foreach (self::oku($this->overridePath($dil)) as $kaynak => $tr) {
            $temiz[$kaynak] = $tr;
        }

        return $this->onbellek[$dil] = $temiz;
    }

    /**
     * @return array<string, string>
     */
    private static function oku(?string $path): array
    {
        if ($path === null || !is_file($path)) {
            return [];
        }

        /** @var mixed $veri */
        $veri = require $path;
        if (!is_array($veri)) {
            return [];
        }

        $temiz = [];
        foreach ($veri as $kaynak => $tr) {
            if (is_string($kaynak) && is_string($tr) && trim($kaynak) !== '' && trim($tr) !== '') {
                $temiz[trim($kaynak)] = trim($tr);
            }
        }

        return $temiz;
    }

    /**
     * Tam metin karşılığı; yoksa null. Kısmi değiştirme YAPILMAZ (yanlış birleşim riski).
     * İngilizcede eşleşme büyük/küçük harf duyarsızdır; Çincede harf kavramı yoktur.
     */
    public function lookup(string $metin, ?string $dil = null): ?string
    {
        $anahtar = trim($metin);
        if ($anahtar === '') {
            return null;
        }

        $dil ??= self::detect($anahtar);
        $sozluk = $this->all($dil);

        if ($dil === 'en') {
            $anahtar = mb_strtolower($anahtar, 'UTF-8');
        }

        return $sozluk[$anahtar] ?? null;
    }

    /**
     * Bu değer çevrilmeli mi? Marka/model/ölçü/sayı gibi alanlar OLDUĞU GİBİ kalır.
     *
     * @param string|null $dil null ise metinden saptanır
     */
    public function translatable(string $metin, ?string $dil = null): bool
    {
        $metin = trim($metin);
        if ($metin === '' || mb_strlen($metin) > 120) {
            return false;
        }

        // Ölçü/teknik değerler korunur: 600×500×150 mm, 350ml, 21V, 4.5kg…
        if (preg_match('/\d/', $metin) === 1
            && preg_match('/[×xX*\/]|mm|cm|kg|ml|\bV\b|\bW\b|\bA\b|inch|"/u', $metin) === 1) {
            return false;
        }

        $dil ??= self::detect($metin);
        if ($dil === 'zh') {
            return true; // CJK metin çeviri adayıdır
        }

        // İngilizcede model/parça kodu görünümlü değerler elenir (harf+rakam karışımı).
        if (preg_match('/^[A-Za-z]{0,4}[-_ ]?\d[\dA-Za-z\-_.]*$/', $metin) === 1) {
            return false;
        }

        return preg_match('/[A-Za-z]{2,}/', $metin) === 1;
    }

    /**
     * Sözlüğü dosyaya yazar (Ayarlar > Terminoloji).
     *
     * Dosya PHP kaynağıdır: değerler `var_export` ile kaçırılır, elle string
     * birleştirme yapılmaz — panelden gelen metin kod olarak yorumlanamaz.
     *
     * @param array<string, string> $terimler
     *
     * @throws \RuntimeException yazılamazsa (storage/ yazılabilir olmalı)
     */
    public function save(array $terimler, string $dil = 'zh'): void
    {
        if (!in_array($dil, self::DILLER, true)) {
            throw new \RuntimeException('Bilinmeyen sözlük dili: ' . $dil);
        }
        $path = $this->overridePath($dil);
        if ($path === null) {
            throw new \RuntimeException('Sözlük yazma yeri tanımlı değil (storage dizini verilmedi).');
        }

        $temiz = [];
        foreach ($terimler as $kaynak => $tr) {
            $kaynak = trim((string) $kaynak);
            $tr = trim((string) $tr);
            if ($dil === 'en') {
                $kaynak = mb_strtolower($kaynak, 'UTF-8');
            }
            if ($kaynak !== '' && $tr !== '' && mb_strlen($kaynak) <= 120 && mb_strlen($tr) <= 200) {
                $temiz[$kaynak] = $tr;
            }
        }
        ksort($temiz);

        $baslik = $dil === 'zh' ? 'ZH→TR' : 'EN→TR';
        $satirlar = [
            '<?php', '', 'declare(strict_types=1);', '', '/**',
            ' * ' . $baslik . ' YEREL SÖZLÜK (İE#14 A2 · K56 Katman 1).',
            ' *',
            ' * PANELDEN ÜRETİLDİ (Ayarlar > Terminoloji) — elle de düzenlenebilir.',
            ' * Bu dosya depodaki config/sozluk-*.php varsayılanının ÜZERİNE biner;',
            ' * sürüm güncellemesi varsayılanı tazelese bile buradaki terimler korunur.',
            ' * Kapalı küme terimleri içindir — marka, model kodu, ölçü ve ilan no ÇEVRİLMEZ.',
            ' */', 'return [',
        ];
        foreach ($temiz as $kaynak => $tr) {
            $satirlar[] = '    ' . var_export($kaynak, true) . ' => ' . var_export($tr, true) . ',';
        }
        $satirlar[] = '];';

        $dizin = dirname($path);
        if (!is_dir($dizin) && !@mkdir($dizin, 0775, true) && !is_dir($dizin)) {
            throw new \RuntimeException('Sözlük dizini oluşturulamadı: ' . $dizin);
        }

        $gecici = $path . '.tmp';
        if (@file_put_contents($gecici, implode("\n", $satirlar) . "\n", LOCK_EX) === false || !@rename($gecici, $path)) {
            @unlink($gecici);

            throw new \RuntimeException('Sözlük dosyası yazılamadı: ' . $path);
        }

        $this->onbellek[$dil] = $temiz;
    }

    public function writable(string $dil = 'zh'): bool
    {
        $path = $this->overridePath($dil);
        if ($path === null) {
            return false;
        }
        if (is_file($path)) {
            return is_writable($path);
        }

        $dizin = dirname($path);

        return is_dir($dizin) ? is_writable($dizin) : is_writable(dirname($dizin));
    }
}
