<?php

declare(strict_types=1);

/**
 * activity_log — işlem ve giriş denetim kaydı (docs/04 §2, K15/K16).
 *
 * K25/K27 ile eklenen kolonlar: actor_type/actor_id (kim yaptı), request_id (isteği
 * loglarla eşleştirir), user_agent. Ürün durum tarihçesi buraya GÖMÜLMEZ — kendi
 * tablosunda tutulur (product_status_history, ürün tabloları yazılırken gelir).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE activity_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(32) NOT NULL,
                entity_id BIGINT UNSIGNED NULL,
                action VARCHAR(64) NOT NULL,
                detail TEXT NULL,
                ip VARCHAR(45) NULL,
                actor_type VARCHAR(16) NOT NULL DEFAULT 'admin',
                actor_id BIGINT UNSIGNED NULL,
                request_id CHAR(26) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_activity_entity (entity_type, entity_id),
                KEY idx_activity_request (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};
