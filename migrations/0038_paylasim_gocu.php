<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * V3-C BLOK A3 — PAYLAŞIM GÖÇÜ: `lists` KOLONLARINDAN `shares` TABLOSUNA.
 *
 * SIFIR KAYIP ŞARTI. Canlıda firmaya gönderilmiş, WhatsApp'ta duran linkler
 * var; bu göç onları BOZARSA firma "sayfa bulunamadı" görür ve bunun sebebini
 * kimse bilmez. Bu yüzden:
 *
 *   · `lists` KOLONLARI SİLİNMEZ. Göç bir KOPYALAMADIR, taşıma değil. Eski
 *     kolonlar okuma yolu tamamen `shares`e geçene kadar yerinde kalır;
 *     silinmeleri ayrı bir migration'ın (ve ayrı bir kararın) işidir.
 *   · `WHERE NOT EXISTS` (K23): migration ikinci kez koşarsa satır çoğalmaz.
 *   · Token HASH'i taşınır, düz token zaten hiçbir yerde saklı değil (K34) —
 *     yani eski link aynen çalışmaya devam eder, çünkü doğrulama aynı hash'e
 *     bakar.
 *
 * K58 İMZALAR DEĞİŞMEZ: `/liste/{token}` ve `/p/` alias'ları, imzalı indirme
 * bağlantıları ve QR içerikleri bu göçten ETKİLENMEZ — hepsi token'ın kendisine
 * dayanıyor, kaydın hangi tabloda durduğuna değil.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if (!$this->tabloVar($pdo, 'shares') || !$this->tabloVar($pdo, 'lists')) {
            return;
        }
        if (!$this->kolonVar($pdo, 'lists', 'share_token_hash')) {
            // Kolon hiç açılmamışsa (temiz kurulum) taşınacak bir şey de yok.
            return;
        }

        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $simdi = $sqlite ? "datetime('now')" : 'NOW()';

        // ANAHTAR KOLONLARI HER KURULUMDA OLMAYABİLİR: 0021 öncesi bir yedekten
        // dönülmüş bir kurulumda `share_key_*` yoktur. Varlığı denetlenir;
        // yoksa NULL yazılır — göç yine de linki kurtarır.
        $anahtarVar = $this->kolonVar($pdo, 'lists', 'share_key_hash');
        $keyHash = $anahtarVar ? 'l.share_key_hash' : 'NULL';
        $keyPlain = $anahtarVar ? 'l.share_key_plain' : 'NULL';
        $keyEnabled = $anahtarVar ? 'l.share_key_enabled' : '1';

        $pdo->exec(sprintf(
            'INSERT INTO shares
                (list_id, supplier_round_id, recipient_type, token_hash, token_prefix,
                 key_hash, key_plain, key_enabled, expires_at, revoked_at, created_at, updated_at)
             SELECT l.id, NULL, %s, l.share_token_hash, l.share_token_prefix,
                    %s, %s, %s, l.share_expires_at, NULL, %s, %s
             FROM lists l
             WHERE l.share_token_hash IS NOT NULL
               AND l.share_token_hash <> %s
               AND NOT EXISTS (
                   SELECT 1 FROM shares s WHERE s.token_hash = l.share_token_hash
               )',
            $this->tirnak('importer'),
            $keyHash,
            $keyPlain,
            $keyEnabled,
            $simdi,
            $simdi,
            $this->tirnak(''),
        ));
    }

    /** Sürücüden bağımsız tek tırnaklı sabit. */
    private function tirnak(string $deger): string
    {
        return "'" . str_replace("'", "''", $deger) . "'";
    }

    private function tabloVar(PDO $pdo, string $tablo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :ad");
            $statement->execute(['ad' => $tablo]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :ad',
        );
        $statement->execute(['ad' => $tablo]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function kolonVar(PDO $pdo, string $tablo, string $kolon): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->query('PRAGMA table_info(' . $tablo . ')');
            if ($statement === false) {
                return false;
            }
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $satir) {
                if (($satir['name'] ?? '') === $kolon) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :k',
        );
        $statement->execute(['t' => $tablo, 'k' => $kolon]);

        return (int) $statement->fetchColumn() > 0;
    }
};
