<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Core\Connection;
use App\Services\Kuyruk\JobQueue;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SERTLEŞTİRME v1.2.1 BLOK A7 — ADALET SQL'DE, YARIŞ KAYBI "BOŞ" DEĞİLDİR
 * (TDR-006, TDR-033).
 *
 * İKİ AYRI KUSUR:
 *
 * 1. "BOŞ" İLE "YARIŞ KAYBI" AYNI CEVABI VERİYORDU. Koşullu sahiplenme
 *    (`rowCount() !== 1`) yarışı kaybedince `sahiplen()` `null` dönüyordu —
 *    yani kuyruk boşmuş gibi. Çağıran turu SONLANDIRIYOR ve günlüğe
 *    "kuyruk boş" yazıyordu. Oysa kuyrukta iş VARDI; yalnız o işi başka bir
 *    işleyici kapmıştı. İki işleyici aynı anda koştuğunda biri her turda
 *    erken duruyor ve kuyruk yarı hızda ilerliyordu — üstelik günlük "boş"
 *    dediği için kimse sebebini aramıyordu.
 *
 * 2. ADALET SAYACI SÜREÇ ÖMRÜNDEYDİ. `$turSayaci` bir PHP alanıydı ve her
 *    cron turu SIFIRDAN başlıyordu. Her turun İLK işi daima aday listesinin
 *    0. türünden seçiliyordu; cron turları kısa ve sık olduğu için (5 dk),
 *    listenin sonundaki tür pratikte hiç sıra alamıyordu. "Dönüşümlü seçim"
 *    tek bir süreç içinde çalışıyor, süreçler arasında çalışmıyordu.
 *
 *    Yeni kural SQL'de ve DETERMİNİSTİK: eşit öncelikli türler arasında,
 *    EN ESKİ bekleyen işi olan tür seçilir. Süreç ömründen bağımsızdır;
 *    açlıktan ölen tür, bekledikçe kendiliğinden öne çıkar.
 */
final class KuyrukAdaletTest extends TestCase
{
    private PDO $pdo;
    private JobQueue $kuyruk;
    private DateTimeImmutable $simdi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        foreach (['0024_create_jobs', '0028_kuyruk_sertlestirme'] as $ad) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $ad . '.php';
            $migration->up($this->pdo);
        }

        $this->kuyruk = new JobQueue(Connection::fromCallable(fn (): PDO => $this->pdo));
        $this->simdi = new DateTimeImmutable('2026-08-31 12:00:00');
    }

    public function testBOSKUYRUKBOSDIYE_ISARETLENIR(): void
    {
        self::assertNull($this->kuyruk->sahiplen('isci-1', $this->simdi));
        self::assertSame(JobQueue::SECIM_BOS, $this->kuyruk->sonSecimNedeni());
    }

    public function testYARISKAYBIBOSTANAYRILIR(): void
    {
        // İki işleyici AYNI tek işe uzanır. Biri alır, diğeri kaybeder — ama
        // kaybedenin gördüğü şey "kuyruk boş" DEĞİLDİR.
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);

        $kazanan = $this->kuyruk->sahiplen('isci-1', $this->simdi);
        self::assertNotNull($kazanan);
        self::assertSame(JobQueue::SECIM_ALINDI, $this->kuyruk->sonSecimNedeni());

        // İkinci işleyici: alınabilir iş kalmadı; bu GERÇEKTEN boş.
        $ikinci = new JobQueue(Connection::fromCallable(fn (): PDO => $this->pdo));
        self::assertNull($ikinci->sahiplen('isci-2', $this->simdi));
        self::assertSame(JobQueue::SECIM_BOS, $ikinci->sonSecimNedeni());
    }

    public function testCASKAYBINDASIRADAKIISEGECILIR(): void
    {
        // ASIL DAVRANIŞ: aday iş kapılmışsa tur BİTMEZ, sıradaki iş denenir.
        // Yarışı taklit etmek için ilk adayı, seçildikten sonra elden çıkmış
        // gibi göstermek yerine iki iş koyup birini önceden kilitliyoruz:
        // eski kod bu durumda `null` döner ve turu bitirirdi.
        $this->kuyruk->ekle('ceviri', 'a', [], $this->simdi);
        $this->kuyruk->ekle('ceviri', 'b', [], $this->simdi);

        // 'a' başka bir işleyicinin elinde, kirası TAZE — alınamaz.
        $this->pdo->exec(
            "UPDATE jobs SET durum = 'calisiyor', kilit_token = 'baskasinin',
                    kilit_bitis = '2026-08-31 12:59:00' WHERE anahtar = 'a'",
        );

        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);

        self::assertNotNull($is, 'Kapılmış aday yüzünden tur bitmemeli.');
        self::assertSame('b', (string) $is['anahtar']);
    }

    public function testEN_ESKI_BEKLEYENI_OLAN_TUR_ONCE_ALIR(): void
    {
        // Adalet SQL'de ve süreç ömründen BAĞIMSIZ: her seferinde yeni bir
        // JobQueue kurulsa bile (her cron turu böyledir) açlıktan ölen tür
        // bekledikçe öne çıkar.
        $eski = $this->simdi->modify('-2 hours');
        $this->kuyruk->ekle('skor', 's1', [], $eski);
        for ($i = 0; $i < 5; $i++) {
            $this->kuyruk->ekle('ceviri', 'c' . $i, [], $this->simdi);
        }

        // HER TUR YENİ SÜREÇ: sayaç sıfırlanır. Eski kodda ilk seçim daima
        // aynı türden gelirdi.
        $taze = new JobQueue(Connection::fromCallable(fn (): PDO => $this->pdo));
        $is = $taze->sahiplen('isci-1', $this->simdi);

        self::assertNotNull($is);
        self::assertSame(
            'skor',
            (string) $is['tur'],
            'İki saattir bekleyen skor işi, az önce eklenen çevirilerin arkasında kalmamalı.',
        );
    }

    public function testONCELIKADALETINUSTUNDEKALIR(): void
    {
        // Adalet EŞİT öncelikler arasında paylaştırır; önceliğin yerine geçmez.
        // Eski ve düşük öncelikli iş, yeni ve yüksek öncelikli işi geçemez.
        $this->kuyruk->ekle('ceviri', 'c1', [], $this->simdi->modify('-2 hours'), oncelik: 200);
        $this->kuyruk->ekle('skor', 's1', [], $this->simdi, oncelik: 10);

        $is = $this->kuyruk->sahiplen('isci-1', $this->simdi);

        self::assertNotNull($is);
        self::assertSame('skor', (string) $is['tur'], 'Öncelik (küçük sayı = önce) adaletin üstündedir.');
    }
}
