<?php

declare(strict_types=1);

/**
 * 0021 — ERİŞİM ANAHTARI (İE#18 Görev 6 · K62).
 *
 * Ürün Sahibi kararı: paylaşım sayfası artık "linki bilen görür" DEĞİLDİR.
 * Firmadan 6 haneli bir erişim anahtarı istenir; anahtar doğrulanmadan yanıtta
 * LİSTE VERİSİ BULUNMAZ.
 *
 * İki kolon:
 *   • `share_key_hash`    — anahtarın HMAC-SHA256 özeti (APP_KEY ile). Düz metin
 *     DB'ye ASLA yazılmaz; panelde gösterilen değer üretim anında verilir ve
 *     `share_key_plain` alanında YALNIZ panel oturumu için tutulur (aşağıda).
 *   • `share_key_enabled` — liste bazında AÇIK/KAPALI. VARSAYILAN AÇIK (1).
 *
 * MEVCUT LİSTELER: bu migration onlara da anahtar ÜRETİR ve AÇIK getirir —
 * emirdeki "varsayılan açık" kuralı geçmişe de uygulanır, yoksa eski listeler
 * sessizce korumasız kalırdı.
 *
 * NEDEN DÜZ METİN DE SAKLANIYOR (`share_key_plain`): anahtar 6 hanedir ve
 * kullanıcının onu FİRMAYA İLETMESİ gerekir; hash'ten geri okunamaz. Kullanıcı
 * paneli her açtığında "anahtar neydi?" diyebilmeli. Bu bir parola değil, bir
 * PAYLAŞIM KODUDUR: tehdit modeli "linki ele geçiren yabancı"dır, "DB'yi ele
 * geçiren saldırgan" değil (DB'yi alan zaten listenin kendisini de alır).
 * Doğrulama yine HASH üzerinden yapılır; düz metin yalnız panelde gösterilir.
 *
 * İDEMPOTENT (K23): kolon varlığı şema sorgusuyla denetlenir; iki kez çağrılırsa
 * hata vermez.
 */
return new class () implements \App\Core\Migration {
    public function up(PDO $pdo): void
    {
        if (!$this->kolonVar($pdo, 'lists', 'share_key_hash')) {
            $pdo->exec('ALTER TABLE lists ADD COLUMN share_key_hash CHAR(64) NULL AFTER share_expires_at');
        }
        if (!$this->kolonVar($pdo, 'lists', 'share_key_plain')) {
            $pdo->exec('ALTER TABLE lists ADD COLUMN share_key_plain VARCHAR(12) NULL AFTER share_key_hash');
        }
        if (!$this->kolonVar($pdo, 'lists', 'share_key_enabled')) {
            $pdo->exec('ALTER TABLE lists ADD COLUMN share_key_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER share_key_plain');
        }

        // Mevcut listelere anahtar üret: kapı geçmişe de uygulanır.
        // Anahtar üretimi UYGULAMA KATMANINDA yapılır (HMAC anahtarı APP_KEY'dedir
        // ve migration'ın yapılandırmaya erişimi yoktur); burada yalnız kolonlar
        // hazırlanır ve anahtarsız satırlar İŞARETLENİR. Uygulama, anahtarı olmayan
        // ve kapısı açık bir liste görürse ilk erişimde üretir (ShareKeyService).
    }

    /** Kolon var mı? MySQL'de information_schema, SQLite'ta pragma (0020 ile aynı desen). */
    private function kolonVar(PDO $pdo, string $tablo, string $kolon): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?');
            $statement->execute([$tablo, $kolon]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $statement->execute([$tablo, $kolon]);

        return (int) $statement->fetchColumn() > 0;
    }
};
