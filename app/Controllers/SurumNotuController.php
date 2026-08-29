<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AppVersion;
use App\Core\Response;
use App\Models\SettingsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * SÜRÜM NOTLARI — "YENİLİKLER" BALONU (V3-B B4).
 *
 * Notlar `docs/surum-notlari/<surum>.md` altındadır ve KULLANICI DİLİYLE
 * yazılır ("Çevir düğmesi artık takılıp kalmıyor"), sürüm günlüğü diliyle
 * değil ("CeviriYurutucu::urunuTamamla bütçe parametresi aldı"). İkisi ayrı
 * belgelerdir: `CHANGELOG.md` geliştirici içindir, bu dosya kullanıcı için.
 *
 * OKUNDU İŞARETİ AYARLARDA TUTULUR, tarayıcıda değil. Kullanıcı paneli başka
 * bir cihazdan açtığında aynı balonu yeniden görmemeli; `localStorage` bunu
 * cihaz başına ayrı sayardı.
 */
final class SurumNotuController extends ApiController
{
    /** Ayar anahtarı: kullanıcının "gördüm" dediği son sürüm. */
    public const KEY_GORULEN = 'surum_notu_gorulen';

    private const DIZIN = '/docs/surum-notlari/';

    public function __construct(
        private readonly SettingsRepository $ayarlar,
        private readonly string $kokDizin,
    ) {
    }

    /**
     * GET /api/surum-notu — geçerli sürümün notu + görülüp görülmediği.
     */
    public function guncel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $surum = AppVersion::VALUE;

        return Response::success($response, [
            'surum' => $surum,
            'maddeler' => $this->maddeler($surum),
            // Balon YALNIZ bu sürüm henüz görülmediyse açılır.
            'gorulmedi' => $this->ayarlar->get(self::KEY_GORULEN) !== $surum,
        ]);
    }

    /**
     * GET /api/surum-notu/gecmis — Ayarlar > Sürüm notları ekranı.
     */
    public function gecmis(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dosyalar = glob($this->kokDizin . self::DIZIN . '*.md') ?: [];
        $surumler = [];

        foreach ($dosyalar as $yol) {
            $surum = basename($yol, '.md');
            $surumler[] = ['surum' => $surum, 'maddeler' => $this->maddeler($surum)];
        }

        // En yeni sürüm başta: 1.10.0 > 1.9.0 karşılaştırması metin sıralamasıyla
        // YANLIŞ çıkar, bu yüzden sürüm karşılaştırması kullanılır.
        usort($surumler, static fn (array $a, array $b): int => version_compare($b['surum'], $a['surum']));

        return Response::success($response, ['surumler' => $surumler]);
    }

    /**
     * POST /api/surum-notu/goruldu — balonu kapatır.
     */
    public function gorulduIsaretle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->ayarlar->set(self::KEY_GORULEN, AppVersion::VALUE);

        return Response::success($response, ['surum' => AppVersion::VALUE]);
    }

    /**
     * Markdown madde imlerini düz metne çevirir.
     *
     * Tam bir Markdown ayrıştırıcısı GEREKMİYOR ve istenmez: not dosyaları
     * bilinçli olarak yalnız madde imi kullanır. Panele HTML göndermek, bu
     * dosyaları XSS yüzeyi hâline getirirdi — oysa metin panelde düz metin
     * olarak basılıyor ve kalın işaretleri (`**`) korunuyor.
     *
     * @return list<string>
     */
    private function maddeler(string $surum): array
    {
        // Sürüm adı dosya yoluna girer: yol geçişi (../) kesin olarak engellenir.
        if (preg_match('/^\d+\.\d+\.\d+(-[a-z0-9.]+)?$/i', $surum) !== 1) {
            return [];
        }

        $yol = $this->kokDizin . self::DIZIN . $surum . '.md';
        $ham = @file_get_contents($yol);
        if ($ham === false) {
            return [];
        }

        $maddeler = [];
        $mevcut = '';

        foreach (explode("\n", $ham) as $satir) {
            $kirpik = rtrim($satir);

            if (str_starts_with($kirpik, '- ')) {
                if ($mevcut !== '') {
                    $maddeler[] = $mevcut;
                }
                $mevcut = trim(substr($kirpik, 2));

                continue;
            }

            // Devam satırı (girintili): önceki maddeye eklenir.
            if ($mevcut !== '' && str_starts_with($kirpik, '  ')) {
                $mevcut .= ' ' . trim($kirpik);

                continue;
            }

            if ($mevcut !== '') {
                $maddeler[] = $mevcut;
                $mevcut = '';
            }
        }

        if ($mevcut !== '') {
            $maddeler[] = $mevcut;
        }

        return $maddeler;
    }
}
