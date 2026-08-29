<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

use App\Core\Clock;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * BİLDİRİM YAYINCISI (V3-B A2) — olayların tek çıkış kapısı.
 *
 * Çağrı noktaları yalnız ŞUNU bilir: bir olay kodu ve bir bağlam dizisi.
 * Başlığı, gövdeyi, önemi, birleştirme penceresini ve grup anahtarını katalog
 * söyler; bu sınıf ikisini birleştirir.
 *
 * KATMAN BAĞIMSIZ: `Connection` + `Clock` + katalog dışında bağımlılığı yoktur.
 * `JobQueue` da, denetleyici de, gece süpürmesi de aynı çağrıyı yapar. Bir HTTP
 * isteğine bağlı olsaydı kuyrukta ölen iş bildirim üretemezdi — oysa kullanıcının
 * en çok haber almak istediği olay tam olarak odur.
 *
 * SESSİZ ÇALIŞMA İLKESİNİN KENDİSİ SESSİZCE ÇÖKMEZ — AMA AKIŞI DA DURDURMAZ:
 * bildirim yazımı başarısız olursa istisna YUKARI SIZMAZ, loglanır. Bir liste
 * kaydedilirken bildirim tablosu doluysa, kaydın kendisi başarısız olmamalıdır;
 * bildirim yardımcı bir çıktıdır, işin kendisi değil. Bu kararın karşı tarafı da
 * korunmuştur: hata YUTULMAZ, `storage/logs/` altına bağlamıyla yazılır.
 */
final class BildirimYayinci
{
    /**
     * `birlestirme.izinli=false` olan olaylar için audit bağlantısı ZORUNLUDUR.
     * Katalog "değiştirilemez audit bağlantısıyla gösterilir" diyor; şema bunu
     * zorlayamaz (birleşen satırlar tek audit_id taşıyamaz), bu yüzden zorlama
     * BURADADIR ve testi vardır.
     */
    public const AUDIT_ZORUNLU_MESAJI = 'Birleştirmesi kapalı olay audit bağlantısı olmadan yayımlanamaz';

    public function __construct(
        private readonly BildirimRepository $depo,
        private readonly BildirimKatalogu $katalog,
        private readonly GrupAnahtariCozucu $cozucu,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Olayı yayımlar.
     *
     * @param  array<string, scalar|null> $baglam  hem şablon değerleri hem grup anahtarı atomları
     * @param  int|null $auditId `izinli=false` olaylarda ZORUNLU
     * @return int|null yazılan/birleşen bildirim id'si; hata hâlinde null
     */
    public function yayimla(string $olayKodu, array $baglam = [], ?int $auditId = null): ?int
    {
        try {
            return $this->yaz($olayKodu, $baglam, $auditId);
        } catch (InvalidArgumentException $hata) {
            // Sözleşme ihlali GELİŞTİRİCİ hatasıdır: bilinmeyen olay kodu,
            // tanımsız atom, eksik audit. Yutulmaz — yukarı verilir ki test
            // ve geliştirme ortamı görsün.
            throw $hata;
        } catch (Throwable $hata) {
            $this->logger->error('Bildirim yazılamadı', [
                'olay_kodu' => $olayKodu,
                'hata' => $hata->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, scalar|null> $baglam
     * @throws InvalidArgumentException
     */
    private function yaz(string $olayKodu, array $baglam, ?int $auditId): int
    {
        $olay = $this->katalog->olay($olayKodu);
        if ($olay === null) {
            throw new InvalidArgumentException(sprintf(
                'Katalogda olmayan olay kodu: "%s". Kod katalogla eşleşmiyorsa olay hiç doğmamalıdır.',
                $olayKodu,
            ));
        }

        /** @var array{izinli?: bool, pencere_dakika?: int, grup_anahtari?: string, toplu_govde_tr?: string} $birlestirme */
        $birlestirme = $olay['birlestirme'] ?? [];
        $izinli = (bool) ($birlestirme['izinli'] ?? false);

        if (!$izinli && $auditId === null) {
            throw new InvalidArgumentException(sprintf(
                '%s (olay: %s).',
                self::AUDIT_ZORUNLU_MESAJI,
                $olayKodu,
            ));
        }

        $now = $this->clock->now();
        $pencere = $izinli ? (int) ($birlestirme['pencere_dakika'] ?? 0) : 0;

        // Birleştirme kapalıysa satır kimliği olayın kendisidir; anahtar
        // benzersiz olmalı ki UNIQUE kısıtı iki farklı olayı çakıştırmasın.
        $grupAnahtari = $izinli
            ? $this->cozucu->coz((string) ($birlestirme['grup_anahtari'] ?? ''), $baglam)
            : 'tekil:' . $auditId;

        $sonuc = $this->depo->yaz([
            'olay_kodu' => $olayKodu,
            'onem' => (string) $olay['onem'],
            'grup' => (string) $olay['grup'],
            'baslik' => (string) $olay['baslik_tr'],
            'govde' => $this->katalog->doldur((string) $olay['govde_tr'], $baglam),
            'eylem_linki' => $this->eylemLinki($olay, $baglam),
            'kullanici_id' => isset($baglam['kullanici_id']) ? (int) $baglam['kullanici_id'] : null,
            'grup_anahtari' => $grupAnahtari,
            'audit_id' => $auditId,
        ], $now, $pencere);

        // Birleşen satırda katalogdaki TOPLU gövde kullanılır: "3 ürün panele
        // kabul edildi" — tekil cümleyi tekrarlamak birleştirmeyi anlamsız kılar.
        if ($sonuc['birlesti'] && isset($birlestirme['toplu_govde_tr'])) {
            $this->depo->govdeyiYaz($sonuc['id'], $this->katalog->doldur(
                (string) $birlestirme['toplu_govde_tr'],
                $baglam + ['n' => $sonuc['birlesen_sayi']],
            ));
        }

        return $sonuc['id'];
    }

    /**
     * @param array<string, mixed>       $olay
     * @param array<string, scalar|null> $baglam
     */
    private function eylemLinki(array $olay, array $baglam): ?string
    {
        $ham = $olay['eylem_linki'] ?? null;
        if (!is_string($ham) || $ham === '') {
            return null;
        }

        $link = $this->katalog->doldur($ham, $baglam);

        // Yer tutucusu dolmamış link, kullanıcıyı "/panel/urunler/—" adresine
        // götürür. Böyle bir link YOKTUR — bildirim linksiz gösterilir.
        return str_contains($link, '—') ? null : $link;
    }

}
