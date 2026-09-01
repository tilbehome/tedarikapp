<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Core\Config;
use App\Core\Connection;
use App\Core\Encrypter;
use App\Models\SettingsRepository;

/**
 * SÖZLÜKSÜZ ÇEVRİLMİŞ ÜRÜNLERİ SAYAR (v1.2.1 A6-EK).
 *
 * ARIZANIN GERÇEK ŞEKLİ — İLK TEŞHİSTEN FARKLI:
 *
 * Kuyruk yolu sözlüğü `new Glossary($kok)` ile kuruyordu; `config`/`storage`
 * ekleri olmadığı için sözlük BOŞTU. İlk bakışta "ürünler yanlış çevrildi"
 * gibi görünür. Kodu izleyince gerçek daha ince çıktı:
 *
 *   · Kuyruk işi ürün alanlarına HİÇBİR ŞEY YAZMAZ (K54: sonuç bir öneridir);
 *     yalnız `translation_cache` doldurur.
 *   · Önbellek anahtarı (`source_hash`) SÖZLÜK SÜRÜMÜNÜ İÇERİR
 *     (`TranslationCacheRepository::hash()` + `CeviriSurumu::anahtar()`).
 *
 * Yani boş sözlükle üretilen satırlar BAŞKA BİR ANAHTARA yazıldı. Panel ve
 * belgeler doğru sözlükle anahtar hesapladığı için o satırları HİÇ BULAMAZ.
 * Sonuç: ürüne yanlış metin yazılmadı — ama toplu çeviri **hiçbir işe
 * yaramadı**. Kullanıcı düğmeye bastı, iş "başarılı" bitti, ürün çevrilmemiş
 * kaldı ve LLM maliyeti boşa gitti. Sessizliğin bedeli buydu.
 *
 * BU YÜZDEN SAYIM ÖKSÜZ SATIRLARDAN YÜRÜR: boş sözlük sürümüyle üretilmiş
 * önbellek satırlarının kaynak metni hangi ürünlere aitse, o ürünler
 * "çevrildi sanılıp çevrilmemiş" olanlardır.
 */
final class SozluksuzCeviriSayaci
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Config $config,
        private readonly string $basePath,
    ) {
    }

    /**
     * Boş sözlükle üretilmiş satırların taşıdığı sürüm anahtarı.
     *
     * Sağlayıcı ve model AYARLARDAN okunur: arıza dönemindeki sağlayıcı bugün
     * kullanılanla aynıysa anahtar birebir tutar. Sağlayıcı o günden beri
     * değiştiyse bu sayım EKSİK kalır — uydurma yapmaktansa eksik saymak
     * yeğdir ("bilinmeyen ≠ sıfır" ilkesinin tersi burada geçerli değil:
     * sayının kendisi zaten bir alt sınırdır ve kart bunu iddia etmez).
     */
    public function bozukAnahtar(): string
    {
        $ayarlar = new CeviriAyarlari(new SettingsRepository($this->connection), new Encrypter($this->config));

        // Var olmayan bir dizin: sözlük boş kalır — arızanın ürettiği durumun
        // BİREBİR aynısı. Sabit bir "boş sürüm" yazmak yerine gerçekten boş bir
        // sözlükten hesaplarız ki Glossary::surum() bir gün değişse bile
        // sayaç kendiliğinden doğru kalsın.
        $bosSozluk = new Glossary($this->basePath . '/__sozluk_yok__');

        return CeviriSurumu::kur($ayarlar, $bosSozluk)->anahtar();
    }

    /** Bugün geçerli (doğru sözlüklü) sürüm anahtarı. */
    public function dogruAnahtar(): string
    {
        $ayarlar = new CeviriAyarlari(new SettingsRepository($this->connection), new Encrypter($this->config));

        return CeviriSurumu::kur($ayarlar, SozlukFabrikasi::kur($this->basePath))->anahtar();
    }

    /**
     * Etkilenen ürün sayısı. 0 ise kart gizlenir ("0'da gizli" kuralı).
     */
    public function urunSayisi(): int
    {
        return count($this->urunKimlikleri());
    }

    /**
     * Etkilenen ürün kimlikleri.
     *
     * Eşleşme KAYNAK METİN üzerindendir: önbellek satırında `product_id` yok
     * (tablo metin başına tekildir, ürün başına değil). Ürünün çeviriye giren
     * metni `name_original`dır; yoksa `name` kullanılır — çeviri yolu da tam
     * olarak bu sırayı izler, iki taraf aynı alanı görmezse eşleşme kaçardı.
     *
     * @return list<int>
     */
    public function urunKimlikleri(int $limit = 5000): array
    {
        $bozuk = $this->bozukAnahtar();
        $dogru = $this->dogruAnahtar();
        if ($bozuk === $dogru) {
            // Sözlük gerçekten boşsa (kurulum yeni, terim girilmemiş) iki anahtar
            // eşitlenir ve "bozuk" diye bir şey kalmaz. Sayım 0 döner.
            return [];
        }

        $statement = $this->connection->pdo()->prepare(
            'SELECT DISTINCT p.id
             FROM translation_cache tc
             JOIN products p
               ON p.deleted_at IS NULL
              AND COALESCE(NULLIF(p.name_original, ""), p.name) = tc.source_text
             WHERE tc.surum = :bozuk
             ORDER BY p.id
             LIMIT ' . max(1, $limit),
        );
        $statement->execute(['bozuk' => $bozuk]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }
}
