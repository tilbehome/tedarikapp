<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * `rate_history` → `rate_snapshots` GÖÇÜ (İE#22 Blok A1).
 *
 * Eski defterdeki her satır bir snapshot olur: `effective_from = set_at`,
 * `source = 'elle'` (o gün TCMB otomatik yazmıyordu; kaynağı uydurmak yerine
 * bilinen doğruyu yazıyoruz — kullanıcı elle onaylamıştı).
 *
 * SÜPERSEDE ZİNCİRİ: her para biriminde satırlar zamana göre sıralanır ve
 * `superseded_at`, KENDİNDEN SONRAKİNİN `set_at`ıdır. En yeni satır NULL kalır
 * — yani aktif kur odur. Bu hesap PHP'de yapılır: pencere fonksiyonu (LAG) hem
 * SQLite'ın eski sürümlerinde hem MySQL 5.x'te yok; bir kurulumun kur geçmişi
 * birkaç yüz satırdır, bellekte işlemek güvenli.
 *
 * İDEMPOTANS (K23): her ekleme `WHERE NOT EXISTS` denetimiyle yapılır. İkinci
 * koşum hiçbir satırı çoğaltmaz; UNIQUE (currency, effective_from) da ikinci
 * savunmadır.
 *
 * DEFTERİ OLMAYAN KURULUM: `rate_history` boşsa (temiz kurulum) `settings`
 * içindeki güncel kur tek aktif snapshot olarak yazılır — aksi hâlde sistem
 * "aktif kur yok" durumuna düşerdi.
 */
return new class () implements Migration {
    public function up(PDO $pdo): void
    {
        $satirlar = $pdo->query(
            'SELECT currency, rate, set_at FROM rate_history ORDER BY currency, set_at, id',
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ekle = $pdo->prepare(
            'INSERT INTO rate_snapshots (currency, rate, source, effective_from, superseded_at, created_by, created_at)
             SELECT :currency, :rate, :source, :effective_from, :superseded_at, NULL, :created_at
             WHERE NOT EXISTS (
                 SELECT 1 FROM rate_snapshots
                 WHERE currency = :currency_kontrol AND effective_from = :effective_from_kontrol
             )',
        );

        // Para birimi bazında grupla; sıra zaten sorgudan geliyor.
        $gruplar = [];
        foreach ($satirlar as $satir) {
            $gruplar[(string) $satir['currency']][] = $satir;
        }

        foreach ($gruplar as $currency => $kayitlar) {
            $adet = count($kayitlar);
            foreach ($kayitlar as $sira => $kayit) {
                $sonraki = $kayitlar[$sira + 1] ?? null;
                $ekle->execute([
                    'currency' => $currency,
                    'rate' => (string) $kayit['rate'],
                    'source' => 'elle',
                    'effective_from' => (string) $kayit['set_at'],
                    // Son satır AKTİFTİR: superseded_at NULL kalır.
                    'superseded_at' => $sira === $adet - 1 ? null : (string) $sonraki['set_at'],
                    'created_at' => (string) $kayit['set_at'],
                    'currency_kontrol' => $currency,
                    'effective_from_kontrol' => (string) $kayit['set_at'],
                ]);
            }
        }

        $this->defterSizKurulumuTohumla($pdo, array_keys($gruplar));
    }

    /**
     * Defterde hiç kaydı olmayan para birimi için `settings`teki güncel değeri
     * tek aktif snapshot olarak yazar.
     *
     * @param list<string> $defterdekiler
     */
    private function defterSizKurulumuTohumla(PDO $pdo, array $defterdekiler): void
    {
        $eslesme = ['CNY' => 'yuan_tl', 'USD' => 'usd_tl'];
        $simdi = date('Y-m-d H:i:s');

        foreach ($eslesme as $currency => $ayarAnahtari) {
            if (in_array($currency, $defterdekiler, true)) {
                continue;
            }

            $oku = $pdo->prepare('SELECT value FROM settings WHERE ' . $this->anahtarKolonu($pdo) . ' = :anahtar');
            $oku->execute(['anahtar' => $ayarAnahtari]);
            $deger = $oku->fetchColumn();
            if (!is_string($deger) || trim($deger) === '') {
                continue;
            }

            $ekle = $pdo->prepare(
                'INSERT INTO rate_snapshots (currency, rate, source, effective_from, superseded_at, created_by, created_at)
                 SELECT :currency, :rate, :source, :effective_from, NULL, NULL, :created_at
                 WHERE NOT EXISTS (SELECT 1 FROM rate_snapshots WHERE currency = :currency_kontrol)',
            );
            $ekle->execute([
                'currency' => $currency,
                'rate' => trim($deger),
                'source' => 'elle',
                'effective_from' => $simdi,
                'created_at' => $simdi,
                'currency_kontrol' => $currency,
            ]);
        }
    }

    /** `settings` tablosunun anahtar kolonu ayrılmış sözcüktür; tırnaklama sürücüye göredir. */
    private function anahtarKolonu(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '"key"' : '`key`';
    }
};
