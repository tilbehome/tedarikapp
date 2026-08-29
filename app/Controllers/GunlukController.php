<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Connection;
use App\Core\Dates;
use App\Core\Response;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PANEL İÇİ GÜNLÜK GÖRÜNTÜLEYİCİ (V3-B F2).
 *
 * NEDEN GEREKLİ: canlıda loglar veritabanına yazılıyor (K33 — uygulama diske
 * yazamıyor). "Çeviri neden gelmedi?" ya da "yakalama neden başarısız?"
 * sorusunun cevabı `app_logs` tablosunda duruyor ama oraya bakmanın tek yolu
 * cPanel'den phpMyAdmin'e girip SQL yazmaktı. Ürün Sahibi bir geliştirici
 * değil; bir hata olduğunda sunucuya girmek zorunda kalmamalı.
 *
 * GÜVENLİK SINIRI: bu uç OKUR, yazmaz ve siler. Bağlam alanı (`context`)
 * `LogRedactor`dan geçmiş hâliyle saklanır — API anahtarı, parola ve token
 * zaten YAZILIRKEN maskelenmiştir; burada ikinci bir maskeleme yapmak, yanlış
 * bir güven duygusu verirdi (asıl koruma yazma tarafındadır).
 */
final class GunlukController extends ApiController
{
    /** Tek istekte dönen en çok satır — panelde okunabilir bir pencere. */
    private const AZAMI = 200;

    public function __construct(
        private readonly Connection $connection,
        private readonly DateTimeZone $timezone,
    ) {
    }

    /**
     * GET /api/gunluk?seviye=error&ara=ceviri&limit=100
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sorgu = $request->getQueryParams();
        $limit = isset($sorgu['limit']) && is_numeric($sorgu['limit'])
            ? max(1, min(self::AZAMI, (int) $sorgu['limit']))
            : 50;

        $kosullar = [];
        $parametreler = [];

        // SEVİYE SÜZGECİ: seçilen seviye VE ÜSTÜ. "warning" diyen kullanıcı
        // error'ları da görmek ister; yalnız tam eşleşme filtrelemek, en
        // önemli satırları gizlerdi.
        $seviye = is_string($sorgu['seviye'] ?? null) ? strtolower((string) $sorgu['seviye']) : '';
        $esik = self::SEVIYELER[$seviye] ?? null;
        if ($esik !== null) {
            $kosullar[] = 'level >= :esik';
            $parametreler['esik'] = $esik;
        }

        $ara = is_string($sorgu['ara'] ?? null) ? trim((string) $sorgu['ara']) : '';
        if ($ara !== '') {
            // Yalnız parametreli sorgu (CLAUDE.md §5); `%` kullanıcı metnine
            // eklenir, SQL'e değil.
            $kosullar[] = '(message LIKE :ara OR context LIKE :ara)';
            $parametreler['ara'] = '%' . $ara . '%';
        }

        $nerede = $kosullar === [] ? '' : 'WHERE ' . implode(' AND ', $kosullar);

        try {
            $statement = $this->connection->pdo()->prepare(
                'SELECT id, channel, level_name, level, message, context, request_id, logged_at
                 FROM app_logs ' . $nerede . '
                 ORDER BY logged_at DESC, id DESC
                 LIMIT ' . $limit,
            );
            $statement->execute($parametreler);
            /** @var list<array<string, mixed>> $satirlar */
            $satirlar = $statement->fetchAll() ?: [];
        } catch (\Throwable) {
            // Tablo yoksa (dosya sürücüsüyle çalışan geliştirme ortamı) uç
            // BOŞ döner ve bunu AÇIKÇA söyler — hata değil, kaynak yokluğu.
            return Response::success($response, [
                'kayitlar' => [],
                'kaynak_var' => false,
                'not' => 'Günlük veritabanına yazılmıyor (LOG_DRIVER=file). Kayıtlar storage/logs/ altındadır.',
            ]);
        }

        return Response::success($response, [
            'kayitlar' => array_map(fn (array $satir): array => [
                'id' => (int) $satir['id'],
                'seviye' => (string) $satir['level_name'],
                'mesaj' => (string) $satir['message'],
                // Bağlam JSON'dur; panele DİZE olarak gider ve orada
                // katlanabilir gösterilir. Çözümleyip nesne döndürmek,
                // biçimi her satırda değişen bir alanı tipleştirmek olurdu.
                'baglam' => $satir['context'] === null ? null : (string) $satir['context'],
                'istek_id' => $satir['request_id'] === null ? null : (string) $satir['request_id'],
                'zaman' => Dates::toIso((string) $satir['logged_at'], $this->timezone),
            ], $satirlar),
            'kaynak_var' => true,
            'not' => null,
        ]);
    }

    /** Monolog seviye eşikleri — süzgeç "bu ve üstü" çalışır. */
    private const SEVIYELER = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
    ];
}
