<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * `notifications` — BİLDİRİM MERKEZİ (V3-B Blok A1).
 *
 * BUGÜNKÜ EKSİK: `activity_log` bir DENETİM İZİDİR — geriye dönük "kim ne yaptı"
 * sorusunu cevaplar, ileriye dönük "şu an neye bakmalıyım" sorusunu cevaplamaz.
 * Kuyrukta ölen bir çeviri işi, iptal edilen bir paylaşım anahtarı ya da kaba
 * kuvvet denemesi bugün yalnız log satırıdır; kullanıcı panelde hiçbir şey
 * görmez. Katalog ilkesi tam da bunu yasaklıyor: "önemli hiçbir işlem sessiz
 * çalışmaz".
 *
 * BİRLEŞTİRME MOTORU — UNIQUE(olay_kodu, grup_anahtari, pencere_baslangic):
 * Katalog yüksek frekanslı olayların tek satırda sayılmasını istiyor
 * (`birlestirme.izinli=true`). Bu üçlü anahtar, birleştirmeyi UYGULAMA
 * MANTIĞINA değil VERİTABANI KISITINA bağlar: iki eşzamanlı istek aynı pencereye
 * düşerse ikincisi INSERT'te patlar, UPDATE yoluna döner. `rate_snapshots`
 * dersinin aynısı (İE#22 A3): önce UPDATE, satır yoksa INSERT.
 *
 * `grup_anahtari` NULL olamaz — birleştirmesi kapalı olaylarda satır kimliği
 * yine de benzersiz olmalı; yayıncı oraya olayın tekil kimliğini yazar.
 * NULL yazılsaydı MySQL UNIQUE kısıtı NULL'ları çakıştırmaz ve kısıt hiç
 * çalışmazdı — sessizce kapanan bir koruma, hiç olmayandan kötüdür.
 *
 * `audit_id` UYGULAMA KATMANINDA zorunludur (`birlestirme.izinli=false` olan
 * 22 olay için). Şemada NULL bırakılmasının sebebi, birleşen olayların tek bir
 * audit satırına bağlanamamasıdır — 12 olay birleşmişse hangi audit_id?
 * Zorlama BildirimYayinci'dadır ve testi vardır.
 *
 * K23: yalnız DDL. Bu tablo geçmişe dönük DOLDURULMAZ — bildirim "şu an ne
 * oluyor" demektir; `activity_log`u bildirime çevirmek, kullanıcıyı açılışta
 * aylarca eskimiş 4000 satırla karşılamak olurdu.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        if ($this->tabloVar($pdo, 'notifications')) {
            return;
        }

        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        $pdo->exec($sqlite
            ? 'CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                olay_kodu TEXT NOT NULL,
                onem TEXT NOT NULL DEFAULT "bilgi",
                grup TEXT NOT NULL,
                baslik TEXT NOT NULL,
                govde TEXT NOT NULL,
                eylem_linki TEXT NULL,
                kullanici_id INTEGER NULL,
                grup_anahtari TEXT NOT NULL,
                pencere_baslangic TEXT NOT NULL,
                birlesen_sayi INTEGER NOT NULL DEFAULT 1,
                audit_id INTEGER NULL,
                okundu_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (olay_kodu, grup_anahtari, pencere_baslangic)
            )'
            : 'CREATE TABLE notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                olay_kodu VARCHAR(48) NOT NULL,
                onem VARCHAR(8) NOT NULL DEFAULT "bilgi",
                grup VARCHAR(16) NOT NULL,
                baslik TEXT NOT NULL,
                govde TEXT NOT NULL,
                eylem_linki VARCHAR(255) NULL,
                kullanici_id BIGINT UNSIGNED NULL,
                grup_anahtari VARCHAR(190) NOT NULL,
                pencere_baslangic DATETIME NOT NULL,
                birlesen_sayi INT UNSIGNED NOT NULL DEFAULT 1,
                audit_id BIGINT UNSIGNED NULL,
                okundu_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_bildirim_pencere (olay_kodu, grup_anahtari, pencere_baslangic),
                KEY ix_bildirim_okunmamis (kullanici_id, okundu_at, created_at),
                KEY ix_bildirim_onem (onem, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', );
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
};
