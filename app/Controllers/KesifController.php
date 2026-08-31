<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ClientIp;
use App\Core\Clock;
use App\Core\Connection;
use App\Core\Response;
use App\Models\CategoryRepository;
use App\Models\SettingsRepository;
use App\Services\ActivityLog;
use App\Services\Ilan\SkorHesaplayici;
use App\Services\Kesif\KesifSorgusu;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * KEŞİF HAVUZU UÇLARI (İE#21 B1).
 *
 * Havuz bir LİSTE değildir: listeye girmemiş ürünleri de kapsar ve sorusu
 * "hangisini alalım?" değil, "elimizde ne var, hangisi iyi?"dir.
 *
 * URL DURUMU İSTEMCİDEDİR, sunucu yalnız sorguyu yanıtlar. Kaydedilmiş görünümler
 * `settings` tablosunda saklanır — ayrı tablo açmak için henüz bir sebep yok
 * (K44: yapılandırma settings'te yaşar) ve görünüm sayısı onlarla ölçülür.
 */
final class KesifController
{
    /** Kaydedilmiş görünümlerin ayar anahtarı. */
    public const KEY_GORUNUMLER = 'kesif.gorunumler';

    /** Bir kullanıcının saklayabileceği en fazla görünüm. */
    private const AZAMI_GORUNUM = 30;

    public function __construct(
        private readonly Connection $connection,
        private readonly SettingsRepository $settings,
        private readonly ActivityLog $activity,
        private readonly Clock $clock,
        /**
         * v1.2.1 C4 — gizlenen hataların ayrıntısı buraya yazılır. Opsiyonel:
         * eski çağrılar ve bakım betikleri logger'sız kurabilir; o hâlde
         * ayrıntı kaybolur ama YANIT yine sızdırmaz.
         */
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    /**
     * GET /api/kesif — havuz sorgusu.
     *
     * Parametreler istemciden gelir ve HEPSİ isteğe bağlıdır; hiçbiri verilmezse
     * skor sırasına göre ilk sayfa döner.
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sorgu = $request->getQueryParams();
        $suzgec = $this->suzgec($sorgu);

        $motor = new KesifSorgusu($this->connection);

        try {
            $sonuc = $motor->calistir($suzgec);
        } catch (Throwable $e) {
            // C4: "HER Throwable → kurulu:false" KALKTI. Bozuk bir SQL, düşen
            // bir bağlantı ya da program hatası, kullanıcıya "tablolar hazır
            // değil" diye gösteriliyordu; gerçek arıza bekleyen bir migration
            // gibi görünüyor ve kimse doğru yere bakmıyordu. Üstelik yanıt ham
            // istisna metnini 200 OK gövdesinde taşıyordu.
            if (!\App\Core\GizliHata::tabloYokMu($e)) {
                $kimlik = $this->logger === null
                    ? null
                    : \App\Core\GizliHata::kaydet($e, $this->logger, 'kesif.index');

                return Response::error(
                    $response,
                    'SERVER_ERROR',
                    'Keşif sorgusu çalıştırılamadı. Sorun sürerse destek kaydında hata kimliğini belirtin.',
                    500,
                    [],
                    [],
                    $kimlik,
                );
            }

            // DOĞRULANMIŞ "tablo yok": kurulum yarım ya da migration bekliyor.
            // Ekran çökmez, sebebi söyler — ama teknik ayrıntı TAŞIMAZ.
            return Response::success($response, [
                'kurulu' => false,
                'mesaj' => 'Keşif havuzu tabloları hazır değil — veritabanı güncellemesi bekliyor olabilir.',
                'satirlar' => [],
                'toplam' => 0,
            ]);
        }

        $kumeli = ($sorgu['kumele'] ?? '') === '1';

        return Response::success($response, [
            'kurulu' => true,
            'satirlar' => $sonuc['satirlar'],
            'kumeler' => $kumeli ? $motor->kumele($sonuc['satirlar']) : null,
            'toplam' => $sonuc['toplam'],
            'sayfa' => $sonuc['sayfa'],
            'limit' => $sonuc['limit'],
            // Panel filtre çiplerini bundan kurar; kendi listesini TUTMAZ.
            'secenekler' => $this->secenekler(),
        ]);
    }

    /**
     * GET /api/kesif/gorunumler — kaydedilmiş görünümler.
     */
    public function views(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Response::success($response, ['gorunumler' => $this->gorunumler()]);
    }

    /**
     * POST /api/kesif/gorunumler — görünüm kaydet/güncelle.
     *
     * Aynı adla ikinci kayıt ÜZERİNE YAZAR: kullanıcı "Aday ürünler" görünümünü
     * güncellemek isterken ikinci bir "Aday ürünler" oluşmasını beklemez.
     */
    public function saveView(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $ad = trim((string) ($body['ad'] ?? ''));
        if ($ad === '') {
            return Response::error($response, 'VALIDATION', 'Doğrulama hatası', 422, [
                'ad' => 'Görünüm adı zorunludur.',
            ]);
        }

        $sorgu = is_array($body['sorgu'] ?? null) ? $body['sorgu'] : [];
        $varsayilan = ($body['varsayilan'] ?? false) === true;

        $gorunumler = $this->gorunumler();

        // Varsayılan TEK olabilir: iki varsayılan, hangisinin açılacağını
        // belirsiz bırakır.
        if ($varsayilan) {
            foreach ($gorunumler as $i => $mevcut) {
                $gorunumler[$i]['varsayilan'] = false;
            }
        }

        $yeni = ['ad' => mb_substr($ad, 0, 60), 'sorgu' => $sorgu, 'varsayilan' => $varsayilan];

        $bulundu = false;
        foreach ($gorunumler as $i => $mevcut) {
            if (mb_strtolower((string) $mevcut['ad']) === mb_strtolower($ad)) {
                $gorunumler[$i] = $yeni;
                $bulundu = true;

                break;
            }
        }
        if (!$bulundu) {
            if (count($gorunumler) >= self::AZAMI_GORUNUM) {
                return Response::error(
                    $response,
                    'VALIDATION',
                    sprintf('En fazla %d görünüm saklanabilir. Kullanmadığınız birini silin.', self::AZAMI_GORUNUM),
                    422,
                );
            }
            $gorunumler[] = $yeni;
        }

        $this->gorunumleriYaz($gorunumler);
        $this->log($request, 'kesif_view_saved', $ad);

        return Response::success($response, ['gorunumler' => $gorunumler]);
    }

    /**
     * DELETE /api/kesif/gorunumler/{ad}
     *
     * @param array<string, string> $args
     */
    public function deleteView(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ad = urldecode((string) ($args['ad'] ?? ''));
        $kalan = array_values(array_filter(
            $this->gorunumler(),
            static fn (array $g): bool => mb_strtolower((string) $g['ad']) !== mb_strtolower($ad),
        ));

        $this->gorunumleriYaz($kalan);
        $this->log($request, 'kesif_view_deleted', $ad);

        return Response::success($response, ['gorunumler' => $kalan]);
    }

    /**
     * POST /api/kesif/karsilastir — 2–6 ürünün yan yana matrisi.
     *
     * ÜST SINIR 6 BİLİNÇLİDİR: yedinci sütun ekranı taşırır ve karşılaştırma
     * okunamaz hâle gelir (E2E-PNL-10 bunu sınar). Alt sınır 2'dir: tek ürünle
     * karşılaştırma diye bir şey yoktur.
     */
    public function compare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $kimlikler = is_array($body) && is_array($body['ids'] ?? null) ? $body['ids'] : [];
        $kimlikler = array_values(array_unique(array_map('intval', $kimlikler)));

        if (count($kimlikler) < 2) {
            return Response::error($response, 'VALIDATION', 'Karşılaştırma için en az 2 ürün seçin.', 422);
        }
        if (count($kimlikler) > 6) {
            return Response::error(
                $response,
                'VALIDATION',
                'Karşılaştırmaya en fazla 6 ürün sığar; matris daha fazlasında okunamaz hâle gelir.',
                422,
                ['ids' => 'En fazla 6 ürün.'],
            );
        }

        $motor = new KesifSorgusu($this->connection);
        $sonuc = $motor->calistir(['id' => $kimlikler, 'limit' => 6]);

        // Satır bazında "en iyi" işareti: hangi üründe hangi ölçüt en iyi?
        $enIyiler = $this->enIyiler($sonuc['satirlar']);

        return Response::success($response, [
            'urunler' => $sonuc['satirlar'],
            'en_iyiler' => $enIyiler,
        ]);
    }

    // ─────────────────────────── yardımcılar ───────────────────────────

    /**
     * @param list<array<string, mixed>> $satirlar
     *
     * @return array<string, int|null> ölçüt => ürün kimliği
     */
    private function enIyiler(array $satirlar): array
    {
        $olcutler = [
            'skor' => 'yuksek',
            'birim_fiyat' => 'dusuk',
            'satis' => 'yuksek',
            'puan' => 'yuksek',
            'moq' => 'dusuk',
        ];

        $out = [];
        foreach ($olcutler as $alan => $yon) {
            $enIyi = null;
            $enIyiDeger = null;
            foreach ($satirlar as $satir) {
                $deger = $satir[$alan] ?? null;
                if ($deger === null) {
                    continue;
                }
                $sayi = (float) $deger;
                if ($enIyiDeger === null
                    || ($yon === 'yuksek' && $sayi > $enIyiDeger)
                    || ($yon === 'dusuk' && $sayi < $enIyiDeger)) {
                    $enIyiDeger = $sayi;
                    $enIyi = (int) $satir['id'];
                }
            }
            $out[$alan] = $enIyi;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $sorgu
     *
     * @return array<string, mixed>
     */
    private function suzgec(array $sorgu): array
    {
        $suzgec = [];

        foreach ([
            'q', 'platform', 'kategori', 'skor_bandi', 'siralama', 'yon', 'sayfa', 'limit',
            'fiyat_min', 'fiyat_max', 'satis_min', 'satis_max', 'puan_min', 'puan_max',
            'moq_min', 'moq_max', 'ilan_no',
        ] as $alan) {
            if (isset($sorgu[$alan]) && $sorgu[$alan] !== '') {
                $suzgec[$alan] = $sorgu[$alan];
            }
        }

        if (($sorgu['video'] ?? '') === '1') {
            $suzgec['video'] = true;
        }
        if (isset($sorgu['listede']) && $sorgu['listede'] !== '') {
            $suzgec['listede'] = $sorgu['listede'] === '1';
        }

        // HAZIR MOD: bir önayardır ve kullanıcının AÇIK seçimlerini EZMEZ.
        $mod = (string) ($sorgu['mod'] ?? '');
        if ($mod !== '' && isset(KesifSorgusu::HAZIR_MODLAR[$mod])) {
            foreach (KesifSorgusu::HAZIR_MODLAR[$mod] as $alan => $deger) {
                $suzgec[$alan] ??= $deger;
            }
        }

        return $suzgec;
    }

    /** @return array<string, mixed> */
    private function secenekler(): array
    {
        $platformlar = [];
        try {
            $statement = $this->connection->pdo()->query(
                "SELECT DISTINCT platform_kod FROM listings WHERE platform_kod IS NOT NULL AND platform_kod <> ''",
            );
            $platformlar = $statement === false ? [] : array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
        } catch (Throwable) {
            $platformlar = [];
        }

        $kategoriler = [];
        try {
            $kategoriler = array_column((new CategoryRepository($this->connection))->all(), 'name');
        } catch (Throwable) {
            $kategoriler = [];
        }

        return [
            'platformlar' => $platformlar,
            'kategoriler' => $kategoriler,
            'bantlar' => ['yuksek', 'orta', 'dusuk', 'gizli'],
            'modlar' => array_keys(KesifSorgusu::HAZIR_MODLAR),
            'esikler' => [
                'yuksek' => SkorHesaplayici::BANT_YUKSEK_ESIK,
                'orta' => SkorHesaplayici::BANT_ORTA_ESIK,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function gorunumler(): array
    {
        $ham = $this->settings->get(self::KEY_GORUNUMLER);
        if (!is_string($ham) || $ham === '') {
            return [];
        }

        /** @var mixed $veri */
        $veri = json_decode($ham, true);
        if (!is_array($veri)) {
            return [];
        }

        $out = [];
        foreach ($veri as $gorunum) {
            if (is_array($gorunum) && is_string($gorunum['ad'] ?? null)) {
                $out[] = [
                    'ad' => $gorunum['ad'],
                    'sorgu' => is_array($gorunum['sorgu'] ?? null) ? $gorunum['sorgu'] : [],
                    'varsayilan' => ($gorunum['varsayilan'] ?? false) === true,
                ];
            }
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $gorunumler */
    private function gorunumleriYaz(array $gorunumler): void
    {
        $this->settings->set(
            self::KEY_GORUNUMLER,
            json_encode($gorunumler, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        );
    }

    private function log(ServerRequestInterface $request, string $eylem, string $detay): void
    {
        try {
            $this->activity->record(
                'kesif',
                null,
                $eylem,
                $detay,
                ClientIp::from($request),
                $this->clock->now(),
                ActivityLog::ACTOR_ADMIN,
                null,
            );
        } catch (Throwable) {
            // Denetim kaydı yazılamazsa görünüm kaydı yine de geçerlidir.
        }
    }
}
