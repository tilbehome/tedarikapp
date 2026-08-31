<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;

/**
 * PAYLAŞIM KAYITLARININ TEK ERİŞİM NOKTASI (V3-C A3 · K103).
 *
 * ÖNCESİ: paylaşım `lists` tablosunda altı kolondu (`share_token_hash`,
 * `share_token_prefix`, `share_expires_at`, `share_key_hash`, `share_key_plain`,
 * `share_key_enabled`) ve on iki dosya bu kolonları DOĞRUDAN okuyordu. Model
 * bir listenin TEK paylaşımı olabileceğini varsayıyordu; V3-C'nin birimi ise
 * `liste × firma × tur` — aynı liste üç firmaya gidebilir ve her birinin ayrı
 * linki, ayrı anahtarı olmalıdır.
 *
 * K103: kolonlar SİLİNMEDİ, geçiş süresince salt-okunur duruyor (canlıda
 * gönderilmiş linkler var). Ama OKUMA VE YAZMA YOLU ARTIK BURADAN GEÇER —
 * `PaylasimKolonuBekcisiTest` uygulama kodunda o kolonlara tek bir başvuru
 * bile kalmadığını zorlar. İki kaynak arasında sessizce ayrışmanın önü
 * böyle kapanır.
 *
 * ALICI TİPİ (`recipient_type`) bugün tek değer alır: `importer`. V3-N'de
 * müşteri ve üretici paylaşımları gelecek; kolon şimdi açıldı ki o gün
 * tablo yeniden yazılmasın.
 */
final class ShareRepository
{
    /** Bugünkü tek alıcı tipi — V3-N'de genişler. */
    public const ALICI_ITHALATCI = 'importer';

    /** @var list<string> */
    private const KOLONLAR = [
        'id', 'list_id', 'supplier_round_id', 'recipient_type',
        'token_hash', 'token_prefix', 'key_hash', 'key_plain', 'key_enabled',
        'expires_at', 'revoked_at', 'created_at', 'updated_at',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Listenin AKTİF paylaşımı (iptal edilmemiş).
     *
     * Bir listenin bugün en çok bir aktif ithalatçı paylaşımı vardır; V3-C'de
     * tur bazlı paylaşımlar `supplier_round_id` ile ayrışır.
     *
     * @return array<string, mixed>|null
     */
    public function listeninAktifi(int $listId, ?int $turId = null): ?array
    {
        $kosul = $turId === null
            ? 'supplier_round_id IS NULL'
            : 'supplier_round_id = :tur';

        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . implode(', ', self::KOLONLAR) . ' FROM shares
             WHERE list_id = :liste AND ' . $kosul . ' AND revoked_at IS NULL
             ORDER BY id DESC LIMIT 1',
        );
        $parametreler = ['liste' => $listId];
        if ($turId !== null) {
            $parametreler['tur'] = $turId;
        }
        $statement->execute($parametreler);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    /**
     * Token özetiyle paylaşım — dış dünyanın giriş kapısı.
     *
     * İPTAL EDİLMİŞ KAYIT DÖNMEZ: iptal edilen bir link, silinmiş bir link
     * gibi davranmalıdır (K51: sabit 404, sebep sızmaz).
     *
     * @return array<string, mixed>|null
     */
    public function tokenOzetiyle(string $tokenHash): ?array
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT ' . implode(', ', self::KOLONLAR) . ' FROM shares
             WHERE token_hash = :ozet AND revoked_at IS NULL LIMIT 1',
        );
        $statement->execute(['ozet' => $tokenHash]);
        $satir = $statement->fetch();

        return is_array($satir) ? $satir : null;
    }

    /**
     * Yeni paylaşım açar ya da aynı listenin aktif kaydını YENİLER.
     *
     * Yenileme davranışı bilinçlidir: "Paylaş" düğmesine ikinci kez basmak
     * ikinci bir link üretip birincisini havada bırakmaz — eski token iptal
     * edilir, yeni satır açılır. Firmadaki eski link ölür ve bu İSTENEN
     * davranıştır (yenileme zaten "eskisini geçersiz kıl" demektir).
     *
     * @param  array{list_id: int, supplier_round_id?: int|null, token_hash: string,
     *               token_prefix: string, expires_at?: string|null,
     *               key_hash?: string|null, key_plain?: string|null, key_enabled?: bool} $veri
     * @return int paylaşım kimliği
     */
    public function ac(array $veri, DateTimeImmutable $now): int
    {
        $pdo = $this->connection->pdo();
        $turId = $veri['supplier_round_id'] ?? null;

        // Aynı kapsamdaki eski aktif kayıt iptal edilir; iki aktif link kalmaz.
        $iptal = $pdo->prepare(
            // HER SÜTUNA AYRI YER TUTUCU: MySQL native prepare aynı adın
            // iki kez geçmesini HY093 ile reddeder; SQLite emülasyonu bunu
            // gizler ve hata ancak üretimde görülürdü (SorguYerTutucuTest).
            'UPDATE shares SET revoked_at = :iptal_at, updated_at = :guncelleme_at
             WHERE list_id = :liste AND revoked_at IS NULL
               AND ' . ($turId === null ? 'supplier_round_id IS NULL' : 'supplier_round_id = :tur'),
        );
        $parametreler = [
            'iptal_at' => Dates::toStorage($now),
            'guncelleme_at' => Dates::toStorage($now),
            'liste' => $veri['list_id'],
        ];
        if ($turId !== null) {
            $parametreler['tur'] = $turId;
        }
        $iptal->execute($parametreler);

        $ekle = $pdo->prepare(
            'INSERT INTO shares
                (list_id, supplier_round_id, recipient_type, token_hash, token_prefix,
                 key_hash, key_plain, key_enabled, expires_at, revoked_at, created_at, updated_at)
             VALUES
                (:liste, :tur, :alici, :ozet, :onek, :anahtar_ozet, :anahtar_duz, :anahtar_acik,
                 :bitis, NULL, :olusma_at, :guncelleme_at)',
        );
        $ekle->execute([
            'liste' => $veri['list_id'],
            'tur' => $turId,
            'alici' => self::ALICI_ITHALATCI,
            'ozet' => $veri['token_hash'],
            'onek' => $veri['token_prefix'],
            'anahtar_ozet' => $veri['key_hash'] ?? null,
            'anahtar_duz' => $veri['key_plain'] ?? null,
            'anahtar_acik' => ($veri['key_enabled'] ?? true) ? 1 : 0,
            'bitis' => $veri['expires_at'] ?? null,
            'olusma_at' => Dates::toStorage($now),
            'guncelleme_at' => Dates::toStorage($now),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Paylaşımı iptal eder — link ölür, kayıt kalır (denetim izi). */
    public function iptalEt(int $listId, DateTimeImmutable $now, ?int $turId = null): bool
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE shares SET revoked_at = :iptal_at, updated_at = :guncelleme_at
             WHERE list_id = :liste AND revoked_at IS NULL
               AND ' . ($turId === null ? 'supplier_round_id IS NULL' : 'supplier_round_id = :tur'),
        );
        $parametreler = [
            'iptal_at' => Dates::toStorage($now),
            'guncelleme_at' => Dates::toStorage($now),
            'liste' => $listId,
        ];
        if ($turId !== null) {
            $parametreler['tur'] = $turId;
        }
        $statement->execute($parametreler);

        return $statement->rowCount() > 0;
    }

    /**
     * Erişim anahtarını yazar (yeni üretim ya da yenileme).
     *
     * `key_plain` DÜZ METİN SAKLANIR ve bu bilinçli bir istisnadır: anahtar
     * 6 hanedir, panelin onu kullanıcıya GÖSTERMESİ gerekir ("firmaya şu
     * kodu ilet"). Tek yönlü özet saklansaydı kod bir daha okunamazdı.
     * Özet (`key_hash`) doğrulama için ayrıca tutulur — K34 hattı korunur.
     */
    public function anahtariYaz(int $shareId, string $keyHash, string $keyPlain, DateTimeImmutable $now): void
    {
        // KAPI DURUMUNA DOKUNMAZ. `key_enabled = 1` yazmak, eksik anahtar
        // üretilirken kullanıcının KAPATTIĞI kapıyı sessizce yeniden açardı:
        // firma linki anahtarsız açabilir hâle gelirdi ve panelde hiçbir şey
        // değişmiş görünmezdi. Kapı ayrı bir karardır → `anahtarKapisi()`.
        $statement = $this->connection->pdo()->prepare(
            'UPDATE shares SET key_hash = :ozet, key_plain = :duz, updated_at = :simdi
             WHERE id = :id',
        );
        $statement->execute([
            'ozet' => $keyHash,
            'duz' => $keyPlain,
            'simdi' => Dates::toStorage($now),
            'id' => $shareId,
        ]);
    }

    /** Anahtar kapısını açar/kapatır (K62: anahtarın ömrü yok, ama kapısı var). */
    public function anahtarKapisi(int $shareId, bool $acik, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'UPDATE shares SET key_enabled = :acik, updated_at = :simdi WHERE id = :id',
        );
        $statement->execute([
            'acik' => $acik ? 1 : 0,
            'simdi' => Dates::toStorage($now),
            'id' => $shareId,
        ]);
    }

    /**
     * Gönderim kaydı — "hangi link kime ne zaman hangi kanaldan gitti?"
     *
     * @param array{share_id: int, supplier_round_id?: int|null, kanal: string,
     *              alici?: string|null, dil?: string|null, gonderen_id?: int|null,
     *              not_metni?: string|null} $veri
     */
    public function gonderimKaydet(array $veri, DateTimeImmutable $now): void
    {
        $statement = $this->connection->pdo()->prepare(
            'INSERT INTO share_dispatch_log
                (share_id, supplier_round_id, kanal, alici, dil, gonderen_id, not_metni, created_at)
             VALUES (:share, :tur, :kanal, :alici, :dil, :gonderen, :not_metni, :simdi)',
        );
        $statement->execute([
            'share' => $veri['share_id'],
            'tur' => $veri['supplier_round_id'] ?? null,
            'kanal' => $veri['kanal'],
            'alici' => $veri['alici'] ?? null,
            'dil' => $veri['dil'] ?? null,
            'gonderen' => $veri['gonderen_id'] ?? null,
            'not_metni' => $veri['not_metni'] ?? null,
            'simdi' => Dates::toStorage($now),
        ]);
    }

    /**
     * Listenin gönderim geçmişi (en yeni önce).
     *
     * @return list<array<string, mixed>>
     */
    public function gonderimGecmisi(int $listId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $statement = $this->connection->pdo()->prepare(
            'SELECT d.id, d.kanal, d.alici, d.dil, d.not_metni, d.created_at, s.token_prefix
             FROM share_dispatch_log d
             JOIN shares s ON s.id = d.share_id
             WHERE s.list_id = :liste
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT ' . $limit,
        );
        $statement->execute(['liste' => $listId]);

        /** @var list<array<string, mixed>> $satirlar */
        $satirlar = $statement->fetchAll() ?: [];

        return $satirlar;
    }
}
