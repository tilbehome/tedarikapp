<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Dates;
use App\Models\CategoryRepository;
use App\Models\ListRepository;
use App\Models\ProductRepository;
use App\Models\SettingsRepository;
use App\Services\Export\ExportException;
use App\Services\Export\ExportRenderer;
use App\Services\Export\ExportSnapshot;
use App\Services\Export\TemplateV2;
use App\Services\Share\ShareDownload;
use App\Services\Share\ShareGate;
use App\Services\Share\SharePage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OTURUMSUZ BELGE İNDİRME — /p/{token}/export (İE#15 A1/A2/A3/A4).
 *
 * Paylaşım sayfasını gören firma, panel oturumu olmadan Excel/PDF/CSV indirebilir.
 * Güvenlik dört kilitle sağlanır:
 *   1. İMZA (ShareDownload): bağlantı sayfa üretilirken APP_KEY ile imzalanır;
 *      kapsam token+biçim+dil+son kullanma. Elle biçim/dil değiştirmek imzayı bozar.
 *   2. SÜRE: 15 dakika. Kopyalanıp saklanan bağlantı işe yaramaz.
 *   3. HIZ SINIRI: token başına saatte 20 indirme; aşımda 429.
 *   4. SABİT 404 (K51): geçersiz imza, süresi dolmuş bağlantı, iptal edilmiş ya da
 *      süresi geçmiş paylaşım — HEPSİ aynı 404 sayfasını döndürür.
 *
 * İÇ KOPYA HİÇBİR KOŞULDA ÜRETİLMEZ (A4): kopya türü burada sabittir ('firma').
 * İstek gövdesinden ya da sorgudan kopya türü OKUNMAZ — okunmadığı için de
 * zorlanamaz. Hedef satış, kâr ve iç notlar üç biçimde de yoktur.
 *
 * KAYIT: her indirme activity_log'a yazılır (token ÖNEKİ, biçim, dil, kırpılmış IP).
 * `exports` tablosuna satır AÇILMAZ: bu bir panel çıktısı değil, firmanın canlı
 * sayfadan aldığı kopyadır — revizyon harfini tüketmemelidir (K57).
 */
final class PublicExportController
{
    /** @param array<string, ExportRenderer> $renderers biçim → render'cı */
    public function __construct(
        private readonly ListRepository $lists,
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly SettingsRepository $settings,
        private readonly ExportSnapshot $snapshot,
        private readonly array $renderers,
        private readonly ShareDownload $downloads,
        private readonly ShareGate $gate,
        private readonly SharePage $page,
        private readonly \App\Core\Clock $clock,
        private readonly \DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $args */
    public function download(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $now = $this->clock->now();
        $token = (string) ($args['token'] ?? '');
        $query = $request->getQueryParams();
        $format = strtolower(is_string($query['format'] ?? null) ? (string) $query['format'] : '');
        $dil = strtolower(is_string($query['lang'] ?? null) ? (string) $query['lang'] : 'tr');

        // Sabit 404 — hiçbir dalda "neden" sızmaz.
        $notFound = function () use ($response): ResponseInterface {
            $response->getBody()->write($this->page->renderNotFound());

            return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
        };

        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return $notFound();
        }
        if (!$this->downloads->dogrula(
            $token,
            $format,
            $dil,
            is_string($query['exp'] ?? null) ? (string) $query['exp'] : '',
            is_string($query['sig'] ?? null) ? (string) $query['sig'] : '',
            $now,
        )) {
            return $notFound();
        }

        $row = $this->lists->findByShareHash(hash('sha256', $token));
        if ($row === null) {
            return $notFound();
        }
        if ($row['share_expires_at'] !== null
            && Dates::fromStorage((string) $row['share_expires_at'], $this->timezone) <= $now) {
            return $notFound();
        }

        // Hız sınırı yalnız BURADA 429 döner: bağlantı geçerlidir, yalnız çok sık
        // kullanılmıştır — bu bilgi zaten bağlantıyı bilen kişiye aittir, sızıntı değil.
        if ($this->gate->downloadBlocked($token, $now)) {
            $response->getBody()->write(
                'Bu liste için indirme sınırına ulaşıldı (saatte 20). Lütfen bir süre sonra tekrar deneyin.',
            );

            return $response->withStatus(429)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader('Retry-After', '3600');
        }

        $renderer = $this->renderers[$format] ?? null;
        if ($renderer === null) {
            return $notFound();
        }

        $productRows = $this->products->forList((int) $row['id']);
        $categoryNames = array_column($this->categories->all(), 'name', 'id');
        $revision = TemplateV2::revisionLabel(1);

        $snapshot = $this->snapshot->build($row, $productRows, $categoryNames, $now, [
            // A4: KOPYA TÜRÜ SABİT. Burada 'ic' yazan bir yol YOKTUR.
            'copy' => 'firma',
            'statuses' => [],
            'lang' => $dil,
            'revision_label' => $revision,
            'document_code' => TemplateV2::documentCode((int) $row['id'], (int) $now->format('Y'), $revision),
            // QR: paylaşım adresi belgeye gömülmez — imzalı indirme adresi ya da
            // token belgeye YAZILMAZ (K51; belge elden ele dolaşabilir).
            'share_url' => null,
            'document_header' => $this->settings->documentHeader(),
        ]);

        try {
            $bytes = $renderer->render($snapshot);
        } catch (ExportException) {
            return $notFound();
        }

        $this->gate->recordDownload(
            $token,
            $format,
            $dil,
            ShareDownload::kirpilmisIp(ClientIp::from($request)),
            $now,
        );

        $ad = $this->dosyaAdi((string) $row['name'], $format, $now);
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', $renderer->mime())
            ->withHeader('Content-Disposition', 'attachment; filename="' . $ad . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    private function dosyaAdi(string $listeAdi, string $format, \DateTimeImmutable $now): string
    {
        $temiz = preg_replace('/[^\p{L}\p{N} _.-]+/u', '', $listeAdi) ?? '';
        $temiz = trim(preg_replace('/\s+/u', ' ', $temiz) ?? '');
        if ($temiz === '') {
            $temiz = 'tedarik-listesi';
        }
        $uzanti = $this->renderers[$format]->extension();

        return mb_substr($temiz, 0, 60) . ' - ' . $now->format('Y-m-d') . '.' . $uzanti;
    }
}
