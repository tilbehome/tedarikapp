<?php

declare(strict_types=1);

namespace App\Services\Bildirim;

use App\Core\Clock;
use App\Models\SettingsRepository;
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
        // K102: kayıt SONRASI yayında hatayı sayacak yer. Null ise sayaç
        // tutulmaz ama hata yine loglanır — sessizlik hiçbir hâlde olmaz.
        private readonly ?SettingsRepository $ayarlar = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** Ayar anahtarı: art arda kaç bildirim yazılamadı. */
    public const KEY_HATA_SAYISI = 'bildirim_hata_sayisi';

    /** Ayar anahtarı: son bildirim hatasının metni ve zamanı. */
    public const KEY_SON_HATA = 'bildirim_son_hata';

    /**
     * TEK KURAL: TRANSACTION İÇİNDEYSE AT, DIŞINDAYSA SAY (K102).
     *
     * Bütün çağrı noktaları bunu kullanır; hangi noktanın transaction içinde
     * olduğunu tek tek bilmek gerekmez — kural kendini uygular ve bir çağrı
     * noktası ileride transaction'a taşınırsa davranış kendiliğinden değişir.
     *
     * · İÇERİDE: istisna YUKARI VERİLİR. Birincil kayıt geri alınır. Ya ikisi
     *   de olur ya hiçbiri; yarım kalmış bir kayıt kalmaz.
     * · DIŞARIDA: birincil kayıt ZATEN COMMIT OLMUŞTUR. İstisnayı yukarı
     *   vermek, başarıyla kaydedilmiş bir listeyi kullanıcıya 500 olarak
     *   göstermek olurdu. Bunun yerine KRİTİK log + "son bildirim hatası"
     *   sayacı; birincil eylem DÜŞMEZ ama hata GÖRÜNÜR — Ayarlar > Sistem
     *   durumu bu sayacı basar.
     *
     * Sözleşme ihlalleri (`InvalidArgumentException` — bilinmeyen olay kodu,
     * tanımsız atom, eksik audit) HER İKİ HÂLDE de yukarı verilir: onlar
     * çalışma zamanı arızası değil GELİŞTİRİCİ hatasıdır ve testte görülmeli.
     *
     * @param  array<string, scalar|null> $baglam
     * @return int|null kayıt sonrası hata hâlinde null
     */
    public function guvenliYayimla(string $olayKodu, array $baglam = [], ?int $auditId = null): ?int
    {
        if ($this->depo->islemIcindeMi()) {
            return $this->yayimla($olayKodu, $baglam, $auditId);
        }

        try {
            $id = $this->yayimla($olayKodu, $baglam, $auditId);
            $this->hataSayaciniSifirla();

            return $id;
        } catch (InvalidArgumentException $hata) {
            throw $hata;
        } catch (Throwable $hata) {
            $this->hatayiKaydet($olayKodu, $hata);

            return null;
        }
    }

    private function hatayiKaydet(string $olayKodu, Throwable $hata): void
    {
        $this->logger?->critical('Bildirim yazılamadı — olay KAYBOLDU', [
            'olay_kodu' => $olayKodu,
            'hata' => $hata->getMessage(),
            'karar' => 'K102',
        ]);

        if ($this->ayarlar === null) {
            return;
        }

        try {
            $sayi = (int) ($this->ayarlar->get(self::KEY_HATA_SAYISI, '0') ?? '0');
            $this->ayarlar->set(self::KEY_HATA_SAYISI, (string) ($sayi + 1));
            $this->ayarlar->set(self::KEY_SON_HATA, sprintf(
                '%s · %s · %s',
                $this->clock->now()->format(DATE_ATOM),
                $olayKodu,
                mb_substr($hata->getMessage(), 0, 300),
            ));
        } catch (Throwable) {
            // Sayacı yazamamak, asıl hatayı gizlemek için sebep değil: log
            // zaten düştü. Buradan yeni bir istisna çıkarmak, kayıt sonrası
            // yolu yine 500'e çevirirdi.
        }
    }

    /** Başarılı yayında sayaç sıfırlanır — eski bir arıza sonsuza dek kırmızı kalmaz. */
    private function hataSayaciniSifirla(): void
    {
        if ($this->ayarlar === null) {
            return;
        }

        try {
            if ((int) ($this->ayarlar->get(self::KEY_HATA_SAYISI, '0') ?? '0') > 0) {
                $this->ayarlar->set(self::KEY_HATA_SAYISI, '0');
            }
        } catch (Throwable) {
            // Sayaç yazılamadı; yayın başarılıydı, akış etkilenmez.
        }
    }

    /**
     * Olayı yayımlar.
     *
     * SESSİZ BAŞARISIZLIK YOK (K99 · V3-B paket düzeltmesi).
     *
     * Burada eskiden `catch (Throwable) { log(); return null; }` vardı ve
     * gerekçesi makul görünüyordu: "bildirim yardımcı bir çıktıdır, liste
     * kaydını düşürmemeli". Ama o yutma, katalog paketten eksik çıktığında
     * TÜM BİLDİRİM SİSTEMİNİN ölü olduğunu gizledi — uygulama çalışıyor,
     * hiçbir bildirim üretilmiyor, kimse fark etmiyordu.
     *
     * Yeni sözleşme: yapılandırma/sözleşme hataları YUKARI VERİLİR. Kataloğun
     * varlığı artık AÇILIŞTA denetleniyor (`KatalogDurumu`), dolayısıyla
     * buraya gelen bir hata gerçek bir arızadır ve görünmesi gerekir.
     *
     * @param  array<string, scalar|null> $baglam  hem şablon değerleri hem grup anahtarı atomları
     * @param  int|null $auditId `izinli=false` olaylarda ZORUNLU
     * @return int yazılan/birleşen bildirim id'si
     * @throws InvalidArgumentException sözleşme ihlali (bilinmeyen olay, tanımsız atom, eksik audit)
     *
     * Katalog okunamazsa `BildirimKatalogu` bir `RuntimeException` atar ve o da
     * BURADAN GEÇER — `@throws` etiketi yazılmadı çünkü PHPStan bağımlılık
     * üzerinden yayılan istisnayı izlemiyor ve "atılmıyor" diye kırmızı veriyor.
     * Davranış `KatalogDurumuTest::testYAYINCIARTIKYUTMAZ` ile kanıtlı.
     */
    public function yayimla(string $olayKodu, array $baglam = [], ?int $auditId = null): int
    {
        return $this->yaz($olayKodu, $baglam, $auditId);
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
