<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductRepository;

/**
 * Medya dosyası yaşam döngüsü (K37 §C7).
 *
 * DB kaydı kalıcı silindiğinde fiziksel dosya da silinmeli — yoksa `public/media/`
 * dışa açık bir "hayalet arşiv" olarak büyür. İki kural:
 *  • Kopyalanan listeler AYNI dosyayı paylaşır: dosya ancak son referans da
 *    gittiğinde silinir (referans sayımı DB'den yapılır).
 *  • Silme İŞLEMDEN SONRA koşar: dosya silme geri alınamaz, DB transaction'ı
 *    geri alınabilir — sıra bu yüzden DB → disk'tir.
 */
final class MediaJanitor
{
    public function __construct(
        private readonly MediaService $media,
        private readonly ProductRepository $products,
    ) {
    }

    /**
     * Verilen referansların artık HİÇBİR kayıtça kullanılmayanlarını diskten siler.
     * Kalıcı silme akışları, kaydı silmeden ÖNCE referansları toplar ve DB silme
     * başarıyla bittikten SONRA bunu çağırır.
     *
     * @param list<string> $references
     *
     * @return list<string> silinen dosya adları
     */
    public function deleteUnreferenced(array $references): array
    {
        $deleted = [];

        foreach (array_unique($references) as $reference) {
            $name = $this->media->fileNameFor($reference);
            if ($name === null) {
                continue; // hotlink URL'si veya desen dışı — bize ait bir dosya değil
            }
            if ($this->products->mediaFileReferenceCount($name) > 0) {
                continue; // başka bir kayıt (ör. kopyalanmış liste) hâlâ kullanıyor
            }
            if ($this->media->deleteFile($name)) {
                $deleted[] = $name;
            }
        }

        return $deleted;
    }

    /**
     * Yetim dosya GC'si: DB'de hiçbir referansı kalmamış sunucu-üretimi medya
     * dosyalarını temizler (görsel değiştirme, yarım kalmış yazma vb. artıkları).
     * Soft-delete kayıtların görselleri KORUNUR — geri alınabilirler (K15).
     *
     * @return list<string> silinen dosya adları
     */
    public function purgeOrphans(): array
    {
        $referencedNames = [];
        foreach ($this->products->allMediaReferences() as $reference) {
            $name = $this->media->fileNameFor($reference);
            if ($name !== null) {
                $referencedNames[$name] = true;
            }
        }

        return $this->media->purgeOrphanFiles(array_keys($referencedNames));
    }
}
