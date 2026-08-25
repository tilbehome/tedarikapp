<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * `products.name_elle` — ürün adını KULLANICI mı yazdı? (D11b, 25 Ağu 2026)
 *
 * NEDEN: çeviri turu tazelendiğinde ekranın YENİ çeviriyi göstermesi isteniyor,
 * ama kullanıcının elle düzelttiği bir ad hiçbir otomatik turla değişmemeli
 * (K54: makine önerir, insan onaylar). Bu ikisi ancak "bu adı kim yazdı?"
 * sorusu kayıtlıysa ayırt edilebilir.
 *
 * Varsayılan 0'dır: mevcut kayıtların adı yakalamadan gelmiştir. Kullanıcı
 * paneldeki ürün adını değiştirdiğinde 1 olur ve o ad DOKUNULMAZ hâle gelir.
 *
 * İleri yönlüdür (K23): kolon eklenir, veri dönüştürülmez.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if ($this->kolonVar($pdo, 'products', 'name_elle')) {
            return;
        }

        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $pdo->exec($sqlite
            ? 'ALTER TABLE products ADD COLUMN name_elle INTEGER NOT NULL DEFAULT 0'
            : 'ALTER TABLE products ADD COLUMN name_elle TINYINT(1) NOT NULL DEFAULT 0 AFTER name_original');
    }

    private function kolonVar(PDO $pdo, string $tablo, string $kolon): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $satirlar = $pdo->query('PRAGMA table_info(' . $tablo . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($satirlar as $satir) {
                if (($satir['name'] ?? null) === $kolon) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tablo AND COLUMN_NAME = :kolon',
        );
        $statement->execute(['tablo' => $tablo, 'kolon' => $kolon]);

        return (int) $statement->fetchColumn() > 0;
    }
};
