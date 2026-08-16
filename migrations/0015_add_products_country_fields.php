<?php

declare(strict_types=1);

/**
 * products.country_of_origin / country_of_dispatch (M23 değerlendirmesi §6.2).
 *
 * "Çin'den gönderildi" ≠ "Çin menşeli". Menşe; gümrük vergisi, antidamping ve ticaret
 * politikası önlemlerinde tarife sınıflandırması kadar belirleyicidir. İki kavram tek
 * alanda tutulursa ileride ayrıştırılamaz — geçmiş kayıtlarda hangisinin kastedildiği
 * bilinemez. Bu yüzden ayrı kolonlar bugünden açılır.
 *
 * ISO 3166-1 alpha-2 (CHAR(2)); doldurma Faz 3/M23 işidir.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        $pdo->exec(
            'ALTER TABLE products
                ADD COLUMN country_of_origin CHAR(2) NULL AFTER units_per_carton,
                ADD COLUMN country_of_dispatch CHAR(2) NULL AFTER country_of_origin',
        );
    }
};
