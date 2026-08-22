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
        // İE#17 G6: başarısız girişimler İÇ TEŞHİS için app_logs'a yazılır.
        // activity_log'a DEĞİL — panel akışı sayaç satırlarıyla kirlenmesin
        // (İE#13 rate-limiter dersi). İstemciye hiçbir şey sızmaz.
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    /**
     * İE#17 G6 — REDDİN NEDENİ YALNIZ SUNUCUDA.
     *
     * Yanıt gövdesi ve statüsü DEĞİŞMEZ (K51: istemci hiçbir dalda "neden"
     * öğrenmez). Bu satır canlıda "indirme neden ölüyor?" sorusunu
     * yanıtlayabilmek içindir: sebep kodu, token ÖNEKİ (tam token asla),
     * biçim, dil ve KIRPILMIŞ IP.
     */
    private function redKaydi(
        string $sebep,
        string $token,
        string $format,
        string $dil,
        ServerRequestInterface $request,
    ): void {
        $this->logger?->warning('Oturumsuz indirme reddedildi', [
            'sebep' => $sebep,
            'token_onek' => substr($token, 0, 8),
            'format' => $format === '' ? '-' : $format,
            'dil' => $dil === '' ? '-' : $dil,
            'ip' => ShareDownload::kirpilmisIp(ClientIp::from($request)),
        ]);
    }

    /**
     * TAZE İMZA UCU — GET /p/{token}/export-link (İE#17 G4).
     *
     * SORUN: indirme bağlantıları sayfa AÇILIRKEN imzalanıyordu (ömür 15 dk).
     * Firma sayfayı sabah açıp öğleden sonra indirmeye kalkınca imza ölmüş
     * oluyor, sunucu sabit 404 dönüyor ve düğme "hazırlanıyor…"da kalıyordu.
     * Bu uç, sayfayı YENİLEMEDEN taze bağlantı verir.
     *
     * GÜVENLİK: token'ı bilen kişi sayfayı yenileyerek ZATEN taze imza alabilir —
     * uç saldırı yüzeyini genişletmez. İmza ÖMRÜ ve KAPSAMI değişmez (K58);
     * kopya türü yolu YOKTUR, üretilen bağlantı daima firma kopyasıdır (A4).
     * Ret dalları /p/{token} ile AYNI disiplindedir: hepsi sabit 404.
     *
     * @param array<string, string> $args
     */
    public function link(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $now = $this->clock->now();
        $token = (string) ($args['token'] ?? '');
        $query = $request->getQueryParams();
        $format = strtolower(is_string($query['format'] ?? null) ? (string) $query['format'] : '');
        $dil = strtolower(is_string($query['lang'] ?? null) ? (string) $query['lang'] : 'tr');

        $notFound = function (string $sebep) use ($request, $response, $token, $format, $dil): ResponseInterface {
            $this->redKaydi($sebep, $token, $format, $dil, $request);
            $response->getBody()->write($this->page->renderNotFound());

            return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
        };

        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return $notFound('token');
        }
        if ($this->gate->blocked(ClientIp::from($request), $now)) {
            return $notFound('hiz');
        }
        if (!in_array($format, ShareDownload::BICIMLER, true) || !in_array($dil, ShareDownload::DILLER, true)) {
            return $notFound('bicim');
        }

        $row = $this->lists->findByShareHash(hash('sha256', $token));
        if ($row === null) {
            $this->gate->recordInvalid(ClientIp::from($request), $token, $now);

            return $notFound('token');
        }
        if ($row['share_expires_at'] !== null
            && Dates::fromStorage((string) $row['share_expires_at'], $this->timezone) <= $now) {
            return $notFound('sure');
        }
        // Dakikalık üst sınır: uç imza üretim otomasyonuna dönüşmesin. SAATLİK
        // indirme sayacı (20) burada TÜKETİLMEZ — o yalnız /export'ta işler.
        if ($this->gate->linkBlocked($token, $now)) {
            return $notFound('hiz');
        }

        $this->gate->recordLink($token, $format, $dil, $now);

        $govde = json_encode(
            ['ok' => true, 'data' => ['url' => $this->downloads->adres($token, $format, $dil, $now)]],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $response->getBody()->write($govde === false ? '{"ok":false}' : $govde);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
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

        // Sabit 404 — hiçbir dalda "neden" İSTEMCİYE sızmaz; sebep yalnız
        // sunucu logunda durur (G6).
        $notFound = function (string $sebep) use ($request, $response, $token, $format, $dil): ResponseInterface {
            $this->redKaydi($sebep, $token, $format, $dil, $request);
            $response->getBody()->write($this->page->renderNotFound());

            return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
        };

        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return $notFound('token');
        }
        if (!$this->downloads->dogrula(
            $token,
            $format,
            $dil,
            is_string($query['exp'] ?? null) ? (string) $query['exp'] : '',
            is_string($query['sig'] ?? null) ? (string) $query['sig'] : '',
            $now,
        )) {
            return $notFound('imza');
        }

        $row = $this->lists->findByShareHash(hash('sha256', $token));
        if ($row === null) {
            return $notFound('token');
        }
        if ($row['share_expires_at'] !== null
            && Dates::fromStorage((string) $row['share_expires_at'], $this->timezone) <= $now) {
            return $notFound('sure');
        }

        // Hız sınırı yalnız BURADA 429 döner: bağlantı geçerlidir, yalnız çok sık
        // kullanılmıştır — bu bilgi zaten bağlantıyı bilen kişiye aittir, sızıntı değil.
        if ($this->gate->downloadBlocked($token, $now)) {
            $this->redKaydi('hiz', $token, $format, $dil, $request);
            $response->getBody()->write(
                'Bu liste için indirme sınırına ulaşıldı (saatte 20). Lütfen bir süre sonra tekrar deneyin.',
            );

            return $response->withStatus(429)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader('Retry-After', '3600');
        }

        $renderer = $this->renderers[$format] ?? null;
        if ($renderer === null) {
            return $notFound('bicim');
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
            return $notFound('uretim');
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
