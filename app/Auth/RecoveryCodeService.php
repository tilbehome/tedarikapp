<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Connection;
use App\Core\Dates;
use DateTimeImmutable;
use SensitiveParameter;

/**
 * 2FA kurtarma kodları (K16): telefon kaybında tek giriş yolu — e-posta kapalı olduğundan (K8)
 * başka kurtarma kanalı yoktur.
 *
 * Kodlar yalnızca üretim anında bir kez gösterilir; veritabanında hash'li durur ve TEK kullanımlıktır.
 */
final class RecoveryCodeService
{
    public const int CODE_COUNT = 10;

    /** Karışabilen karakterler (0/O, 1/I/L) alfabede yok — kod elle yazılacak. */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /**
     * Düz metin kodlar üretir (XXXX-XXXX). Çağıran bunları kullanıcıya BİR KEZ gösterip
     * `replaceForUser()` ile saklamalıdır.
     *
     * @return list<string>
     */
    public function generate(int $count = self::CODE_COUNT): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->randomBlock() . '-' . $this->randomBlock();
        }

        return $codes;
    }

    /**
     * Kullanıcının tüm kurtarma kodlarını verilen setle değiştirir (yeniden üretim = eskiler ölür).
     *
     * @param list<string> $plainCodes
     */
    public function replaceForUser(int $userId, array $plainCodes): void
    {
        $pdo = $this->connection->pdo();

        $delete = $pdo->prepare('DELETE FROM recovery_codes WHERE user_id = :user_id');
        $delete->execute(['user_id' => $userId]);

        $insert = $pdo->prepare(
            'INSERT INTO recovery_codes (user_id, code_hash, used_at) VALUES (:user_id, :code_hash, NULL)',
        );
        foreach ($plainCodes as $code) {
            $insert->execute([
                'user_id' => $userId,
                'code_hash' => $this->hasher->hash($this->normalize($code)),
            ]);
        }
    }

    /**
     * Kodu tüketir. Başarılıysa `used_at` yazılır ve kod bir daha kabul edilmez.
     */
    public function consume(int $userId, #[SensitiveParameter] string $plainCode, DateTimeImmutable $now): bool
    {
        $normalized = $this->normalize($plainCode);
        if ($normalized === '') {
            return false;
        }

        $pdo = $this->connection->pdo();
        $statement = $pdo->prepare(
            'SELECT id, code_hash FROM recovery_codes WHERE user_id = :user_id AND used_at IS NULL ORDER BY id',
        );
        $statement->execute(['user_id' => $userId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        foreach ($rows as $row) {
            if (!$this->hasher->verify($normalized, (string) $row['code_hash'])) {
                continue;
            }

            // Yarış durumunda ikinci kullanımı engellemek için koşul UPDATE'te de tekrarlanır.
            $update = $pdo->prepare(
                'UPDATE recovery_codes SET used_at = :used_at WHERE id = :id AND used_at IS NULL',
            );
            $update->execute([
                'used_at' => Dates::toStorage($now),
                'id' => (int) $row['id'],
            ]);

            return $update->rowCount() === 1;
        }

        return false;
    }

    public function remainingCount(int $userId): int
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT COUNT(*) AS total FROM recovery_codes WHERE user_id = :user_id AND used_at IS NULL',
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['total'] : 0;
    }

    /** Kullanıcı küçük harf/boşlukla yazabilir; karşılaştırma tek biçim üzerinden yapılır. */
    private function normalize(string $code): string
    {
        $upper = strtoupper($code);
        $stripped = preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
        if (strlen($stripped) !== 8) {
            return '';
        }

        return substr($stripped, 0, 4) . '-' . substr($stripped, 4, 4);
    }

    private function randomBlock(): string
    {
        $block = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 4; $i++) {
            $block .= self::ALPHABET[random_int(0, $max)];
        }

        return $block;
    }
}
