<?php

declare(strict_types=1);

/**
 * platforms — KAYNAK SİTE KAYDI (İE#20 C2 · F37).
 *
 * PLATFORM BAĞIMSIZLIK İLKESİ: kodda "1688" diye gömülü hiçbir karar kalmaz.
 * Bugün bir kaynak var; yarın Alibaba, Taobao, Made-in-China eklenecek. Bunları
 * `if ($platform === '1688')` ile taşımak, her yeni kaynağı kod değişikliğine
 * bağlar — oysa kaynak eklemek bir VERİ işlemidir.
 *
 * Bu tablo o kaydı tutar: kod adı, görünen ad, temel adres, ilan adresi kalıbı,
 * para birimi ve AKTİFLİK. Pasif bir platformdan yakalama reddedilir (F37).
 *
 * `url_kalibi`: ilan numarasından adres üretmek için — `{id}` yer tutucusu ile.
 * Adres üretimi böylece platformun kendi kaydından gelir, koddan değil.
 *
 * İDEMPOTENT (K23): tablo varsa dokunulmaz; tohum satırları `INSERT IGNORE` ile
 * eklenir, mevcut kayıtların üzerine YAZILMAZ (kullanıcı adı değiştirmişse kalır).
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if (!$this->tabloVar($pdo, 'platforms')) {
            $pdo->exec($sqlite
                ? <<<'SQL'
                    CREATE TABLE platforms (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        kod VARCHAR(30) NOT NULL UNIQUE,
                        ad VARCHAR(100) NOT NULL,
                        temel_adres VARCHAR(200) NULL,
                        url_kalibi VARCHAR(300) NULL,
                        para_birimi VARCHAR(3) NOT NULL DEFAULT 'CNY',
                        aktif TINYINT(1) NOT NULL DEFAULT 1,
                        sira INT NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL
                    )
                    SQL
                : <<<'SQL'
                    CREATE TABLE platforms (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        kod VARCHAR(30) NOT NULL,
                        ad VARCHAR(100) NOT NULL,
                        temel_adres VARCHAR(200) NULL,
                        url_kalibi VARCHAR(300) NULL,
                        para_birimi VARCHAR(3) NOT NULL DEFAULT 'CNY',
                        aktif TINYINT(1) NOT NULL DEFAULT 1,
                        sira INT NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        UNIQUE KEY idx_platforms_kod (kod),
                        KEY idx_platforms_aktif (aktif, sira)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL);
        }

        // ── Tohum: bugün kullanılan kaynaklar ────────────────────────────────
        // Değerler VERİDİR; kullanıcı Ayarlar'dan değiştirebilir. Buradaki liste
        // yalnız "sistem boşken makul bir başlangıç" demektir.
        $simdi = date('Y-m-d H:i:s');
        $tohum = [
            ['1688', '1688.com', 'https://www.1688.com', 'https://detail.1688.com/offer/{id}.html', 'CNY', 1, 10],
            ['alibaba', 'Alibaba.com', 'https://www.alibaba.com', 'https://www.alibaba.com/product-detail/{id}.html', 'USD', 1, 20],
            ['taobao', 'Taobao', 'https://www.taobao.com', 'https://item.taobao.com/item.htm?id={id}', 'CNY', 0, 30],
            ['manuel', 'Elle girildi', null, null, 'CNY', 1, 90],
        ];

        $ekle = $pdo->prepare(
            ($sqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE')
            . ' INTO platforms (kod, ad, temel_adres, url_kalibi, para_birimi, aktif, sira, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($tohum as [$kod, $ad, $temel, $kalip, $para, $aktif, $sira]) {
            $ekle->execute([$kod, $ad, $temel, $kalip, $para, $aktif, $sira, $simdi, $simdi]);
        }

        // Verideki BİLİNMEYEN platformlar da kayda alınır: göç hiçbir ürünü
        // "platformu yok" diye dışarıda bırakmamalı (veri kaybı = kabul edilemez).
        try {
            $mevcut = $pdo->query(
                "SELECT DISTINCT platform FROM products WHERE platform IS NOT NULL AND TRIM(platform) <> ''",
            );
            foreach ($mevcut === false ? [] : $mevcut->fetchAll(PDO::FETCH_COLUMN) as $kod) {
                $kod = mb_substr(trim((string) $kod), 0, 30);
                if ($kod === '') {
                    continue;
                }
                $ekle->execute([$kod, $kod, null, null, 'CNY', 1, 50, $simdi, $simdi]);
            }
        } catch (Throwable) {
            // products tablosu yoksa (taze kurulum) tohum yeterlidir.
        }
    }

    private function tabloVar(PDO $pdo, string $tablo): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$tablo]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $statement->execute([$tablo]);

        return (int) $statement->fetchColumn() > 0;
    }
};
