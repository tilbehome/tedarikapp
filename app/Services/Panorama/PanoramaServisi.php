<?php

declare(strict_types=1);

namespace App\Services\Panorama;

use App\Core\Clock;
use App\Core\Connection;
use App\Models\RateSnapshotRepository;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\KuyrukIsleyicileri;
use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * PANORAMA — "BUGÜN NE VAR?" (V3-B Blok B).
 *
 * ÜÇ TASARIM KARARI, üçü de emrin şartı:
 *
 * 1. KOŞULLAR SUNUCUDA DEĞERLENDİRİLİR. Katalogdaki `kosul` alanı bir ifadedir
 *    ("kuyruk.dead > 0"); onu tarayıcıda yorumlamak, aynı gerçeği ikinci bir
 *    yoldan okumak olurdu — bu projenin tekrar eden hatası (popup ile panel,
 *    sayaç ile işleyici, sınav ile ekran). Panel yalnız CÜMLE alır.
 *
 * 2. TEK TOPLU OKUMA. Sekiz brifing sekiz sorgu yapmaz: metrikler bir kez
 *    toplanır (`olcumler()`), koşullar o dizinin üstünde çalışır. `JobQueue::saglik()`
 *    zaten toplu döner; ondan sonra ikinci bir kuyruk sorgusu atmak N+1'in
 *    ta kendisiydi.
 *
 * 3. "HENÜZ ÖLÇÜLMÜYOR" ≠ "KOŞUL SAĞLANMADI". Katalogdaki 18 brifingin 10'u
 *    bugün ölçülemiyor (V3 liste durumları, firma portalı, yakalama metrikleri,
 *    sağlayıcı kotası). Bunları "koşul sağlanmadı" saymak, panelin "her şey
 *    yolunda" demesine yol açardı — oysa o alanlara HİÇ BAKILMIYOR. Ayrı
 *    listede, ayrı gerekçeyle döner.
 */
final class PanoramaServisi
{
    private const KATALOG = '/docs/v3/hazirlik/v3-b/panorama-brifing-katalogu.json';

    /**
     * Bugün ÖLÇÜLEBİLEN brifingler. Her biri bir kapatma (closure) ile
     * eşlenir: koşul sağlanırsa cümlenin yer tutucularını dolduran değerler
     * döner, sağlanmazsa null.
     *
     * @var list<string>
     */
    public const OLCULEBILIR = [
        'BRF-006', 'BRF-009', 'BRF-010', 'BRF-011',
        'BRF-012', 'BRF-013', 'BRF-017', 'BRF-018',
    ];

    /**
     * Ölçülemeyen brifingler ve SEBEBİ. Boş gerekçe kabul edilmez — ekranda
     * "henüz ölçülmüyor" yazan satırın yanında bu cümle görünür.
     *
     * @var array<string, string>
     */
    public const OLCULEMEYEN = [
        'BRF-001' => 'Fiyat bekleyen liste durumu V3-C ile gelecek.',
        'BRF-002' => 'Fiyat bekleyen liste durumu V3-C ile gelecek.',
        'BRF-003' => 'Onay bekleyen liste durumu V3-C ile gelecek.',
        'BRF-004' => 'HAZIR liste durumu V3-C ile gelecek.',
        'BRF-005' => 'Teklif geçerlilik süresi kavramı henüz yok (V3-C).',
        'BRF-007' => 'Firma yanıtı bekleyen durum V3-C ile gelecek.',
        'BRF-008' => 'Süresi dolmuş liste durumu V3-C ile gelecek.',
        'BRF-014' => 'Yakalama başarım oranı ölçülmüyor; metrik tablosu yok.',
        'BRF-015' => 'Son başarılı yakalama zamanı ölçülmüyor; metrik tablosu yok.',
        'BRF-016' => 'Çeviri sağlayıcısı kalan kotayı bildirmiyor (K67: bilinmeyen ≠ sıfır).',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $saat,
        private readonly string $kokDizin,
    ) {
    }

    /**
     * Panorama gövdesi.
     *
     * @return array{
     *     brifingler: list<array<string, mixed>>,
     *     olculmeyen: list<array{id: string, sebep: string}>,
     *     bos_gun: string|null,
     *     olcum_zamani: string
     * }
     */
    public function panorama(): array
    {
        $now = $this->saat->now();
        $olcum = $this->olcumler($now);
        $katalog = $this->katalog();

        $brifingler = [];
        foreach (self::OLCULEBILIR as $id) {
            $sablon = $katalog['brifing_sablonlari'][$id] ?? null;
            if ($sablon === null) {
                continue;
            }

            $degerler = $this->kosul($id, $olcum);
            if ($degerler === null) {
                continue;
            }

            $brifingler[] = [
                'id' => $id,
                'oncelik' => (int) $sablon['oncelik'],
                'cumle' => $this->doldur((string) $sablon['cumle'], $degerler),
                'eylem' => (string) $sablon['eylem'],
                'eylem_linki' => $this->eylemLinki($id),
            ];
        }

        // Öncelik 1 en acildir; eşit öncelikte katalog sırası korunur ki ekran
        // her yenilemede aynı sırayı göstersin (belirlenimcilik, K50 disiplini).
        usort($brifingler, static fn (array $a, array $b): int => $a['oncelik'] <=> $b['oncelik']);

        $olculmeyen = [];
        foreach (self::OLCULEMEYEN as $id => $sebep) {
            $olculmeyen[] = ['id' => $id, 'sebep' => $sebep];
        }

        return [
            'brifingler' => $brifingler,
            'olculmeyen' => $olculmeyen,
            // Boş gün cümlesi YALNIZ hiç brifing yokken. Varyant, günün
            // tarihine göre BELİRLENİMCİ seçilir: aynı gün içinde sayfa
            // yenilendiğinde cümle değişmez — değişseydi kullanıcı sistemin
            // ne dediğini takip edemezdi.
            'bos_gun' => $brifingler === [] ? $this->bosGunCumlesi($katalog, $now) : null,
            'olcum_zamani' => $now->format(DATE_ATOM),
        ];
    }

    /**
     * TEK TOPLU OKUMA — bütün metrikler burada toplanır.
     *
     * @return array<string, int|float|null>
     */
    private function olcumler(DateTimeImmutable $now): array
    {
        $saglik = (new JobQueue($this->connection))->saglik($now);

        return [
            'gelen_kutusu_bekleyen' => $this->tekSayi(
                "SELECT COUNT(*) FROM inbox_items WHERE status = 'pending'",
            ),
            'eksik_urun_sayisi' => $this->eksikUrunSayisi(),
            'kuyruk_dead' => $saglik['olu'],
            'kuyruk_ready' => $saglik['alinabilir'],
            'kuyruk_bekleyen' => $saglik['bekleyen'],
            'kuyruk_en_eski_dakika' => $saglik['en_eski_bekleyen_dakika'],
            // "retry_wait": ileri tarihli, yani beklemeye alınmış işler.
            'kuyruk_retry_wait' => $saglik['ileri_tarihli'],
            'kur_yas_saat' => (new RateSnapshotRepository($this->connection))->aktifYasSaat('CNY', $now),
            'ceviri_bekleyen' => $this->tekSayi(
                'SELECT COUNT(*) FROM jobs WHERE tur = :tur AND durum = :durum',
                ['tur' => KuyrukIsleyicileri::TUR_CEVIRI, 'durum' => JobQueue::BEKLIYOR],
            ),
            'ceviri_en_eski_dakika' => $this->ceviriEnEskiDakika($now),
            'ceviri_basarisiz_24s' => $this->tekSayi(
                'SELECT COUNT(*) FROM jobs WHERE tur = :tur AND durum = :durum AND bitti_at >= :sinir',
                [
                    'tur' => KuyrukIsleyicileri::TUR_CEVIRI,
                    'durum' => JobQueue::OLU,
                    'sinir' => $now->modify('-24 hours')->format('Y-m-d H:i:s'),
                ],
            ),
        ];
    }

    /**
     * Koşulu değerlendirir; sağlanıyorsa cümlenin yer tutucu değerlerini döner.
     *
     * Katalogdaki ifadeler AYRIŞTIRILMAZ, kod olarak yazılır. Bir ifade
     * yorumlayıcısı yazmak cazip görünür ama iki sorunu vardır: (1) ifade
     * dilinde yapılan bir yazım hatası sessizce "koşul sağlanmadı"ya döner,
     * (2) yorumlayıcının kendisi test edilmesi gereken ikinci bir sistem olur.
     * Sekiz koşul için sekiz satır kod, her ikisinden de ucuzdur.
     *
     * @param  array<string, int|float|null> $o
     * @return array<string, scalar>|null
     */
    private function kosul(string $id, array $o): ?array
    {
        return match ($id) {
            'BRF-006' => ($o['eksik_urun_sayisi'] ?? 0) > 0
                ? ['n' => (int) $o['eksik_urun_sayisi']] : null,
            'BRF-009' => ($o['gelen_kutusu_bekleyen'] ?? 0) > 0
                ? ['n' => (int) $o['gelen_kutusu_bekleyen']] : null,
            'BRF-010' => ($o['kuyruk_retry_wait'] ?? 0) > 0
                ? ['n' => (int) $o['kuyruk_retry_wait']] : null,
            'BRF-011' => ($o['kuyruk_dead'] ?? 0) > 0
                ? ['n' => (int) $o['kuyruk_dead']] : null,
            'BRF-012' => ($o['kuyruk_ready'] ?? 0) > 0 && ($o['kuyruk_en_eski_dakika'] ?? 0) >= 15
                ? ['n' => (int) $o['kuyruk_ready'], 'dakika' => (int) $o['kuyruk_en_eski_dakika']] : null,
            // Kur yaşı NULL ise hiç snapshot yok demektir; "0 saattir yenilendi"
            // demek yanlış olurdu (K67: bilinmeyen ≠ sıfır) — brifing çıkmaz.
            'BRF-013' => $o['kur_yas_saat'] !== null && $o['kur_yas_saat'] >= 24
                ? ['saat' => (int) $o['kur_yas_saat']] : null,
            'BRF-017' => ($o['ceviri_bekleyen'] ?? 0) > 0
                ? ['n' => (int) $o['ceviri_bekleyen'], 'dakika' => (int) ($o['ceviri_en_eski_dakika'] ?? 0)] : null,
            'BRF-018' => ($o['ceviri_basarisiz_24s'] ?? 0) > 0
                ? ['n' => (int) $o['ceviri_basarisiz_24s']] : null,
            default => null,
        };
    }

    /** Brifingin eylem düğmesinin gideceği panel yolu. */
    private function eylemLinki(string $id): ?string
    {
        return match ($id) {
            'BRF-006' => '/listeler',
            'BRF-009' => '/gelen-kutusu',
            'BRF-010', 'BRF-011', 'BRF-012', 'BRF-017', 'BRF-018' => '/ayarlar',
            'BRF-013' => '/ayarlar',
            default => null,
        };
    }

    /**
     * Zorunlu alanı eksik AKTİF ürün sayısı.
     *
     * `HazirlikKapisi::ALANLAR` ile aynı alan kümesi kullanılır — orası tek
     * gerçek kaynaktır. SQL'de tekrarlanmasının sebebi, 5000 ürünü PHP'ye
     * çekip saymanın panorama ucunu yavaşlatmasıdır; alan listesi oradan
     * OKUNARAK üretilir, elle yazılmaz.
     */
    private function eksikUrunSayisi(): int
    {
        $kosullar = [];
        foreach (array_keys(\App\Services\Ilan\HazirlikKapisi::ALANLAR) as $alan) {
            $kosullar[] = $alan === 'qty'
                ? '(p.qty IS NULL OR p.qty <= 0)'
                : sprintf("(p.%s IS NULL OR p.%s = '')", $alan, $alan);
        }

        return $this->tekSayi(
            'SELECT COUNT(*) FROM products p
             JOIN lists l ON l.id = p.list_id
             WHERE p.deleted_at IS NULL
               AND l.status NOT IN (:tamam, :iptal)
               AND (' . implode(' OR ', $kosullar) . ')',
            ['tamam' => 'completed', 'iptal' => 'cancelled'],
        );
    }

    private function ceviriEnEskiDakika(DateTimeImmutable $now): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT MIN(created_at) FROM jobs WHERE tur = :tur AND durum = :durum',
        );
        $statement->execute(['tur' => KuyrukIsleyicileri::TUR_CEVIRI, 'durum' => JobQueue::BEKLIYOR]);
        $enEski = $statement->fetchColumn();

        if (!is_string($enEski) || $enEski === '') {
            return 0;
        }

        try {
            $an = new DateTimeImmutable($enEski);
        } catch (Throwable) {
            return 0;
        }

        return max(0, (int) floor(($now->getTimestamp() - $an->getTimestamp()) / 60));
    }

    /** @param array<string, scalar> $parametreler */
    private function tekSayi(string $sql, array $parametreler = []): int
    {
        try {
            $statement = $this->connection->pdo()->prepare($sql);
            $statement->execute($parametreler);

            return (int) $statement->fetchColumn();
        } catch (Throwable) {
            // Tablo yoksa (kurulum yarım) metrik SIFIR değil BİLİNMEYENDİR —
            // ama panorama bir uyarı yüzeyidir, çökmemesi daha önemlidir.
            // Sıfır dönmek brifingi hiç göstermez; yanlış brifing göstermez.
            return 0;
        }
    }

    /** @param array<string, scalar> $degerler */
    private function doldur(string $sablon, array $degerler): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z_0-9]+)\}/',
            static fn (array $e): string => isset($degerler[$e[1]]) ? (string) $degerler[$e[1]] : '—',
            $sablon,
        );
    }

    /** @param array{bos_gun_varyantlari: list<array{id: string, cumle: string}>} $katalog */
    private function bosGunCumlesi(array $katalog, DateTimeImmutable $now): string
    {
        $varyantlar = $katalog['bos_gun_varyantlari'];
        if ($varyantlar === []) {
            return 'Bugün müdahale gerektiren bir konu görünmüyor.';
        }

        $indeks = ((int) $now->format('z')) % count($varyantlar);

        return $varyantlar[$indeks]['cumle'];
    }

    /**
     * @return array{
     *     brifing_sablonlari: array<string, array<string, mixed>>,
     *     bos_gun_varyantlari: list<array{id: string, cumle: string}>
     * }
     */
    private function katalog(): array
    {
        $ham = @file_get_contents($this->kokDizin . self::KATALOG);
        if ($ham === false) {
            throw new RuntimeException('Panorama brifing kataloğu okunamadı.');
        }

        try {
            /** @var array{brifing_sablonlari: list<array<string, mixed>>, bos_gun_varyantlari: list<array{id: string, cumle: string}>} $veri */
            $veri = json_decode($ham, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $hata) {
            throw new RuntimeException('Panorama kataloğu bozuk JSON: ' . $hata->getMessage(), 0, $hata);
        }

        $sablonlar = [];
        foreach ($veri['brifing_sablonlari'] as $sablon) {
            $sablonlar[(string) $sablon['id']] = $sablon;
        }

        return [
            'brifing_sablonlari' => $sablonlar,
            'bos_gun_varyantlari' => $veri['bos_gun_varyantlari'],
        ];
    }
}
