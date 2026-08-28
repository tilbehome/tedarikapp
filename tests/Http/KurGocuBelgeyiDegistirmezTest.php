<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Models\RateSnapshotRepository;
use Tests\Support\AuthTestCase;

/**
 * İE#22 A4 — KUR GÖÇÜ ESKİ BELGEYİ DEĞİŞTİRMEZ (K50 regresyonu).
 *
 * Kur snapshot omurgası devreye girerken en büyük risk şuydu: "kur artık
 * snapshot'tan okunuyor" diye belge hattı da oraya bağlanırsa, firmaya
 * gönderilmiş bir teklifin TL tutarı kendiliğinden değişir.
 *
 * Bu test o riski ÖLÇEREK kapatır: aynı listeden üretilen çıktının SHA-256'sı,
 * arada kur snapshot'ı defalarca değişse bile AYNI kalmalıdır. Kilitli liste
 * kendi kopyasını (`lists.yuan_rate`) taşır; yaşayan kur kaynağı belgeye
 * karışmaz.
 */
final class KurGocuBelgeyiDegistirmezTest extends AuthTestCase
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
            $this->call('POST', '/api/lists', ['name' => 'Kur göçü listesi'], [Csrf::HEADER => $this->csrf]),
        )['data']['id'];

        $this->call('POST', '/api/lists/' . $this->listeId . '/products', [
            'name' => 'Terlik',
            'qty' => 10,
            'price_yuan' => '15.90',
        ], [Csrf::HEADER => $this->csrf]);
    }

    private function ciktiSha(string $format): string
    {
        $yanit = $this->call(
            'POST',
            '/api/lists/' . $this->listeId . '/export?format=' . $format,
            [],
            [Csrf::HEADER => $this->csrf],
        );
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());

        return hash('sha256', (string) $yanit->getBody());
    }

    public function testKURSNAPSHOTIDEGISSEDEBELGEAYNI(): void
    {
        // Liste iletilir: kur KİLİTLENİR (K48).
        $this->call('PATCH', '/api/lists/' . $this->listeId, ['status' => 'sent'], [Csrf::HEADER => $this->csrf]);

        $once = $this->ciktiSha('csv');

        // Kur omurgası birkaç kez değişsin — hem doğrudan snapshot yazarak
        // hem de ayarlar ucundan.
        $depo = new RateSnapshotRepository($this->connection);
        $depo->yeniSurum('CNY', '9.9900', $this->clock->now(), RateSnapshotRepository::KAYNAK_TCMB);
        $depo->yeniSurum('USD', '55.0000', $this->clock->now(), RateSnapshotRepository::KAYNAK_TCMB);
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '12.3400'], [Csrf::HEADER => $this->csrf]);

        $sonra = $this->ciktiSha('csv');

        self::assertSame(
            $once,
            $sonra,
            'K50 İHLALİ: kur snapshot değişince kilitli listenin belgesi de değişti. '
            . 'Belge kuru lists.yuan_rate kopyasından okunmalı, yaşayan kaynaktan değil.',
        );
    }

    public function testKILITSIZLISTEYENIKURUIZLER(): void
    {
        // Karşı taraf: liste HENÜZ kilitlenmemişse güncel kuru izler (K4/K45).
        // Bu davranış değişmedi; snapshot yalnız kaynağı değiştirdi.
        $once = $this->ciktiSha('csv');

        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '12.3400'], [Csrf::HEADER => $this->csrf]);

        self::assertNotSame($once, $this->ciktiSha('csv'), 'Kilitsiz liste güncel kuru izlemeli.');
    }

    public function testKURDEGISIKLIGISNAPSHOTSATIRIURETIR(): void
    {
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '8.8800'], [Csrf::HEADER => $this->csrf]);

        $aktif = (new RateSnapshotRepository($this->connection))->aktif('CNY');
        self::assertSame('8.8800', $aktif['rate'] ?? null, 'Ayarlar ucu snapshot yazmalı.');
        self::assertSame('elle', $aktif['source'] ?? null, 'Varsayılan kaynak elle onaydır (K4).');
    }

    public function testTCMBONAYIKAYNAGITCMBYAZAR(): void
    {
        // K4: otomatik yazma yok — yazan yine kullanıcının onayı; değişen tek
        // şey değerin NEREDEN geldiğinin kayda geçmesi.
        $this->call('PUT', '/api/settings/rates', [
            'yuan_tl' => '7.7700',
            'kaynak' => 'tcmb',
        ], [Csrf::HEADER => $this->csrf]);

        self::assertSame('tcmb', (new RateSnapshotRepository($this->connection))->aktif('CNY')['source'] ?? null);
    }

    /**
     * AYNI SANİYEDEKİ İKİNCİ DEĞİŞİKLİK KAYBOLMAZ (son yazan kazanır).
     *
     * `UNIQUE (currency, effective_from)` doğru bir kısıttır ama ham INSERT
     * onu hataya çevirip işlemi geri sarardı: kullanıcı kuru düzeltip hemen
     * yeniden kaydettiğinde DEĞİŞİKLİK KAYBOLURDU.
     */
    public function testAYNIANDAKIIKINCIDEGISIKLIKKAYBOLMAZ(): void
    {
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '7.5000'], [Csrf::HEADER => $this->csrf]);
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '7.9900'], [Csrf::HEADER => $this->csrf]);

        $depo = new RateSnapshotRepository($this->connection);
        self::assertSame('7.9900', $depo->aktifDeger('CNY'), 'Son değer geçerli olmalı.');
        self::assertCount(1, $depo->gecmis('CNY'), 'Aynı an tek satırda birleşir.');
    }

    public function testTARIHCEEKRANIAKTIFSATIRIISARETLER(): void
    {
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '7.5000'], [Csrf::HEADER => $this->csrf]);
        // Saat ilerletilir: AYNI saniyedeki iki değişiklik tasarım gereği TEK
        // satırda birleşir (son yazan kazanır) — tarihçe iki satır göstersin
        // diye zamanın gerçekten ilerlemesi gerekir.
        $this->clock->advance('+1 hour');
        $this->call('PUT', '/api/settings/rates', ['yuan_tl' => '7.6000'], [Csrf::HEADER => $this->csrf]);

        $satirlar = $this->json($this->call('GET', '/api/settings/rates/history?currency=CNY'))['data'];

        self::assertGreaterThanOrEqual(2, count($satirlar), 'İki kur değişikliği iki snapshot satırı üretmeli.');
        self::assertTrue($satirlar[0]['aktif'], 'En yeni satır "geçerli" işaretli olmalı.');
        self::assertFalse($satirlar[1]['aktif']);
        self::assertArrayHasKey('kaynak', $satirlar[0], 'Kaynak (elle/TCMB) ekrana taşınmalı.');
        self::assertArrayHasKey('set_at', $satirlar[0], 'Eski alan adı korunur — ekran sözleşmesi kırılmaz.');
    }
}
