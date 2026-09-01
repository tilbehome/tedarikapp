<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * v1.2.1 D8 — `lists.share_key_plain` ŞİFRELİ DEĞERE YER AÇAR (TDR-034).
 *
 * NEDEN GEREKLİ: kolon `VARCHAR(12)` idi çünkü içinde 6 haneli düz anahtar
 * duruyordu. D8 ile değer AES-256-GCM ile şifreleniyor ve base64 zarfıyla
 * ~69 karaktere çıkıyor.
 *
 * BU KUSUR YEREL TESTLERDE GÖRÜNMEDİ: SQLite kolon uzunluğunu ZORLAMAZ, MySQL
 * strict modda "Data too long" ile reddeder. Kırmızı ancak CI'ın MySQL koşan
 * E2E işinde çıktı — paylaşım sayfası 500 verdi. Ders, dosyanın kendisinden
 * daha kalıcı: şema genişliği bir DAVRANIŞ sözleşmesidir ve SQLite onu
 * sınamaz.
 *
 * 96 KARAKTER SEÇİLDİ: bugünkü zarf 69; sodium ile AES-GCM zarfları farklı
 * uzunlukta olabilir ve sürüm öneki ileride büyüyebilir. Dar sınır, aynı
 * arızayı ikinci kez üretmenin en kolay yoludur.
 *
 * VERİ GÖÇÜ YOK (K23: DDL ve veri ayrı): mevcut düz değerler yerinde kalır ve
 * `ShareKeyService` onları okumaya devam eder (tembel göç). Satır bir sonraki
 * anahtar yenilemesinde kendiliğinden şifreliye döner.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            // SQLite'ta VARCHAR uzunluğu zaten bağlayıcı değildir; değiştirecek
            // bir şey yok. (`ALTER TABLE ... MODIFY` de desteklenmez.)
            return;
        }

        $pdo->exec('ALTER TABLE lists MODIFY share_key_plain VARCHAR(96) NULL');
    }
};
