<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\ProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * REVİZYON SÖZLEŞMESİ (İE#20 C9 · K25 · K57).
 *
 * İDDİA: belgeye giren HER kaynak alan, değişince liste revizyonunu artırmalıdır.
 * Aksi hâlde firmaya aynı revizyon numarasıyla FARKLI iki belge gider ve "çıktı
 * güncel değil" rozeti yalan söyler.
 *
 * Bu testin yöntemi bilinçlidir: `ExportSnapshot` kaynağını TARAR ve `$product['x']`
 * biçiminde okunan her alanı çıkarır. Böylece test, listeyi elle güncellemeyi
 * unutan geliştiriciyi yakalar — snapshot'a yeni alan eklenip revizyon listesine
 * eklenmezse KIRILIR. Elle tutulan bir liste, elle unutulur.
 */
final class RevizyonSozlesmesiTest extends TestCase
{
    /**
     * Snapshot'ta okunan ama ÜRÜN KAYNAK ALANI OLMAYANLAR.
     *
     * Her biri için gerekçe: bunlar ya TÜRETİLMİŞ değerlerdir (kaynak alan zaten
     * listede) ya da sunucunun ürettiği alanlardır (kullanıcı değiştiremez).
     *
     * @var list<string>
     */
    private const TURETILMIS_ALANLAR = [
        // Kurla çarpılarak hesaplanır; kaynağı price_yuan / price_ddp_usd (listede).
        'price_yuan_tl', 'price_ddp_tl', 'line_total_yuan', 'line_total_yuan_tl',
        // Hedef satıştan türer; kaynağı price_target_try (listede).
        'unit_profit_try', 'line_profit_try',
        // Sunucu üretir (kimlik/sıralama), kullanıcı alanı değildir.
        'id', 'list_id', 'created_at', 'updated_at', 'images',
    ];

    public function testSNAPSHOTAGIRENHERALANREVIZYONUARTIRIR(): void
    {
        $kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Export/ExportSnapshot.php');

        preg_match_all("/\\\$product\\['([a-z_]+)'\\]/", $kaynak, $eslesmeler);
        $okunanAlanlar = array_values(array_unique($eslesmeler[1]));

        self::assertNotEmpty($okunanAlanlar, 'Tarama hiçbir alan bulamadı — desen bozulmuş olabilir.');

        $eksik = [];
        foreach ($okunanAlanlar as $alan) {
            if (in_array($alan, self::TURETILMIS_ALANLAR, true)) {
                continue;
            }
            if (!in_array($alan, ProductRepository::REVISION_FIELDS, true)) {
                $eksik[] = $alan;
            }
        }

        self::assertSame(
            [],
            $eksik,
            "Bu alanlar BELGEYE giriyor ama değişince revizyonu ARTIRMIYOR.\n"
            . "Sonuç: aynı revizyon numarasıyla farklı iki belge üretilebilir (K57 ihlali).\n"
            . 'Eksik: ' . implode(', ', $eksik),
        );
    }

    public function testTAKIPNOGIBIBELGEDISIALANLARREVIZYONUARTIRMAZ(): void
    {
        // Kargo takip numarası belgeye girmez; onu değiştirince firmaya "yeni
        // revizyon" demek yanlış alarm üretir ve revizyon harfini anlamsızlaştırır.
        self::assertNotContains('tracking_no', ProductRepository::REVISION_FIELDS);
        self::assertNotContains('vendor_name', ProductRepository::REVISION_FIELDS, 'Şablon v2 satıcıyı BASMAZ.');
    }

    public function testREVIZYONALANLARIYAZILABILIRALANLARINALTKUMESIDIR(): void
    {
        // Yazılamayan bir alanın revizyon listesinde olması ölü kuraldır: o alan
        // hiçbir zaman değişmez, dolayısıyla kural hiç tetiklenmez.
        $yazilabilir = [...ProductRepository::WRITABLE, 'status', 'sort_no', 'list_id'];

        $olu = array_values(array_diff(ProductRepository::REVISION_FIELDS, $yazilabilir));

        self::assertSame([], $olu, 'Bu alanlar güncellenemiyor ama revizyon listesinde: ' . implode(', ', $olu));
    }
}
