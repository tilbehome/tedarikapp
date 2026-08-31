<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\ShareRepository;
use Tests\Support\AuthTestCase;

/**
 * V3-C A3 — PAYLAŞIM GÖÇÜ: ESKİ LİNK GÖÇTEN SONRA DA AÇILIR.
 *
 * BU TESTİN SINADIĞI FELAKET SOMUTTUR: canlıda firmalara gönderilmiş,
 * WhatsApp'ta duran paylaşım linkleri var. Göç onları bozarsa firma "sayfa
 * bulunamadı" görür, bize haber vermez ve teklif turu sessizce ölür. Kimse de
 * sebebini bilmez çünkü panelde her şey normal görünür.
 *
 * SENARYO CANLININ İKİZİDİR: önce ESKİ ŞEKİLDE (yalnız `lists` kolonlarında)
 * bir paylaşım kurulur — yani göç öncesi bir kurulumun aynısı — sonra göç
 * migration'ı koşturulur ve link denenir.
 */
final class PaylasimGocuTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listeId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listeId = (int) $this->json(
            $this->call('POST', '/api/lists', ['name' => 'Göç listesi'], [\App\Middleware\Csrf::HEADER => $this->csrf]),
        )['data']['id'];
    }

    /**
     * Göç ÖNCESİ dünyayı kurar: paylaşım YALNIZ `lists` kolonlarındadır ve
     * `shares` tablosunda hiçbir kaydı yoktur.
     *
     * @return array{token: string, anahtar: string}
     */
    private function eskiUsulPaylasimKur(): array
    {
        $token = bin2hex(random_bytes(32));
        $anahtar = 'A1B2C3';

        // Anahtar özeti `ShareKeyService`in ürettiğiyle AYNI olmalı; aksi hâlde
        // test kendi uydurduğu bir özeti doğrulamış olurdu.
        $servis = new \App\Services\Share\ShareKeyService(
            new ShareRepository($this->connection),
            $this->config()->get('APP_KEY', ''),
        );

        $this->pdo->prepare(
            'UPDATE lists SET share_token_hash = :ozet, share_token_prefix = :onek,
                    share_key_hash = :anahtar_ozet, share_key_plain = :anahtar, share_key_enabled = 1
             WHERE id = :id',
        )->execute([
            'ozet' => hash('sha256', $token),
            'onek' => substr($token, 0, 8),
            'anahtar_ozet' => $servis->hash($anahtar),
            'anahtar' => $anahtar,
            'id' => $this->listeId,
        ]);

        // Göç öncesi hâl: `shares` BOŞ.
        $this->pdo->exec('DELETE FROM shares');

        return ['token' => $token, 'anahtar' => $anahtar];
    }

    /** Göç migration'ını koşturur (canlıda `bin/migrate.php` ne yapıyorsa). */
    private function gocuKos(): void
    {
        /** @var \App\Core\Migration $migration */
        $migration = require dirname(__DIR__, 2) . '/migrations/0038_paylasim_gocu.php';
        $migration->up($this->pdo);
    }

    public function testGOCONCESILINKGOCSONRASIACILIR(): void
    {
        $eski = $this->eskiUsulPaylasimKur();

        // ÖN KOŞUL: göçten önce `shares` boş — yani link gerçekten eski usul.
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM shares')->fetchColumn());

        $this->gocuKos();

        $paylasim = (new ShareRepository($this->connection))->tokenOzetiyle(hash('sha256', $eski['token']));

        self::assertNotNull($paylasim, 'Eski link göçten sonra BULUNAMADI — canlıdaki linkler ölürdü.');
        self::assertSame($this->listeId, (int) $paylasim['list_id']);
        self::assertSame(substr($eski['token'], 0, 8), (string) $paylasim['token_prefix']);
        self::assertSame(
            ShareRepository::ALICI_ITHALATCI,
            (string) $paylasim['recipient_type'],
            'V3-C tek alıcı tipi taşır; V3-N genişletecek.',
        );
    }

    public function testGOCANAHTARIDATASIR(): void
    {
        // Anahtar taşınmazsa firma linki açar ama içeri giremez — link
        // "çalışıyor" görünür, kapı kapalıdır. Daha sinsi bir kayıp.
        $eski = $this->eskiUsulPaylasimKur();
        $this->gocuKos();

        $depo = new ShareRepository($this->connection);
        $paylasim = $depo->tokenOzetiyle(hash('sha256', $eski['token']));
        self::assertNotNull($paylasim);

        $servis = new \App\Services\Share\ShareKeyService($depo, $this->config()->get('APP_KEY', ''));

        self::assertTrue($servis->kapiAcik($paylasim), 'Anahtar kapısı açık taşınmalı.');
        self::assertTrue($servis->dogru($paylasim, $eski['anahtar']), 'Eski anahtar göçten sonra da geçerli olmalı.');
        self::assertSame($eski['anahtar'], (string) $paylasim['key_plain'], 'Panelde gösterilen anahtar korunmalı.');
    }

    public function testGOCIDEMPOTENTTIR(): void
    {
        // Migration ikinci kez koşarsa (baseline onarımı, yeniden kurulum)
        // aynı link için İKİNCİ bir satır açılmamalı: UNIQUE ihlali ya da
        // çift kayıt, ikisi de kurulumu bozar.
        $this->eskiUsulPaylasimKur();

        $this->gocuKos();
        $ilk = (int) $this->pdo->query('SELECT COUNT(*) FROM shares')->fetchColumn();

        $this->gocuKos();
        $ikinci = (int) $this->pdo->query('SELECT COUNT(*) FROM shares')->fetchColumn();

        self::assertSame(1, $ilk);
        self::assertSame($ilk, $ikinci, 'İkinci koşum satır ÇOĞALTMAMALI (K23).');
    }

    public function testPAYLASIMSIZLISTEICINSATIRACILMAZ(): void
    {
        // Hiç paylaşılmamış listeye "boş" bir paylaşım kaydı açmak, panelde
        // olmayan bir linki varmış gibi gösterirdi.
        $this->pdo->exec('DELETE FROM shares');
        $this->pdo->prepare('UPDATE lists SET share_token_hash = NULL WHERE id = :id')
            ->execute(['id' => $this->listeId]);

        $this->gocuKos();

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM shares')->fetchColumn());
    }

    public function testGOCSONRASIPAYLASIMSAYFASIACILIR(): void
    {
        // UÇTAN UCA: göç edilmiş bir linkin GERÇEKTEN HTTP üzerinden açıldığı.
        // Depo sorgusu geçse bile rota/kapı zincirinde bir kopukluk olabilir.
        $eski = $this->eskiUsulPaylasimKur();
        $this->gocuKos();

        $yanit = $this->call('GET', '/liste/' . $eski['token']);

        self::assertNotSame(404, $yanit->getStatusCode(), 'Göç edilen link 404 vermemeli.');
    }
}
