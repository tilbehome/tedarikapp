<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Kuyruk\JobQueue;
use App\Services\Kuyruk\JobRunner;
use Psr\Log\NullLogger;
use Tests\Support\AuthTestCase;

/**
 * D9 — "ÇEVRİLMEMİŞ ÜRÜNLERİ ÇEVİR" DÜĞMESİNDEN İŞÇİYE, UÇTAN UCA.
 *
 * SAHA VAKASI (25 Ağu 2026, 19:57): düğmeye basıldı, panel "5 iş kuyruğa
 * alındı" dedi, sayaç beşi gösterdi — ama cron her turda "kuyruk boş" yazdı.
 * Kimse bu iki cümlenin çeliştiğini fark etmedi, çünkü hiçbir test uçun
 * yazdığı işi İŞÇİNİN gözünden kontrol etmiyordu: uç testi "kaç iş yazıldı"
 * diye bakıyor, kuyruk testi işleri KENDİ yazıyordu. Aradaki boşluk tam da
 * sahada kırılan yerdi.
 *
 * D12 GÜNCELLEMESİ (28 Ağu 2026): düğme artık YALNIZ kuyruğa yazmıyor, işi
 * isteğin içinde de yapıyor (K86). Kuyruk ORTADAN KALKMADI: sekme kapanırsa
 * kalanların kaybolmaması için hâlâ yazılıyor ve arka plan tetikleyicileri onu
 * işliyor. Bu süitin sınadığı köprü — "ucun yazdığı işi İŞÇİ görebiliyor mu?" —
 * bu yüzden hâlâ geçerlidir; değişen tek şey yanıtın alanlarıdır.
 *
 * Bu test o boşluğu kapatır: iş HTTP ucundan yazılır, sonra GERÇEK `JobRunner`
 * ile alınır. Ağa çıkılmaz — çeviri işleyicisi testte yerine konur; sınanan
 * şey çevirinin kalitesi değil, işin İŞÇİYE ULAŞMASIDIR.
 */
final class TopluCeviriKuyrugaTest extends AuthTestCase
{
    private string $csrf = '';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];
    }

    /** @return list<int> kuyrukta bekleyen iş kimlikleri */
    private function bekleyenIsler(): array
    {
        $satirlar = $this->pdo->query("SELECT id FROM jobs WHERE durum = 'bekliyor' ORDER BY id")->fetchAll();

        return array_map(static fn (array $r): int => (int) $r['id'], $satirlar ?: []);
    }

    private function urunEkle(string $orijinal): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (list_id, name, name_original, created_at, updated_at)
             VALUES (1, :ad, :orijinal, :simdi, :simdi)',
        );
        $statement->execute([
            'ad' => $orijinal,
            'orijinal' => $orijinal,
            'simdi' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testDUGMEYAZAR_ISCIALIR_ISBITER(): void
    {
        foreach (['无脚踏', '乐扣杯', '保温杯', '竹纤维浴巾', '洞洞鞋'] as $ad) {
            $this->urunEkle($ad);
        }

        $yanit = $this->call(
            'POST',
            '/api/panel/translate-backfill',
            [],
            [Csrf::HEADER => $this->csrf],
        );
        self::assertSame(200, $yanit->getStatusCode());

        $govde = json_decode((string) $yanit->getBody(), true);
        // D12: yanıt artık "kuyruğa alındı" değil İŞ SONUCU taşır. Sağlayıcı
        // yapılandırılmadığı için hiçbiri çevrilemez; beşi de kalan sayılır ve
        // kuyruğa yazılır — bu süitin köprüsü tam olarak orayı sınar.
        self::assertSame(5, $govde['data']['toplam'] ?? null);
        self::assertSame(5, $govde['data']['kalan'] ?? null);
        self::assertArrayNotHasKey('kuyruga_alinan', $govde['data']);

        // 1) Panelin gördüğü: beş bekleyen iş.
        $bekleyen = $this->bekleyenIsler();
        self::assertCount(5, $bekleyen);

        $kuyruk = new JobQueue($this->connection);
        $saglik = $kuyruk->saglik($this->clock->now());
        self::assertSame(5, $saglik['bekleyen']);
        // 2) İşçinin görebileceği: AYNI beş iş. Sahada burası 0'dı.
        self::assertSame(5, $saglik['alinabilir']);
        self::assertSame(0, $saglik['ileri_tarihli']);

        // 3) Gerçek işçi turu: beşi de alınır ve biter.
        $alinan = [];
        $kosucu = new JobRunner($kuyruk, new NullLogger(), sureSiniri: 50, isSiniri: 10);
        $kosucu->kaydet('ceviri', static function (array $yuk, array $is) use (&$alinan): void {
            $alinan[] = (int) $is['id'];
        });
        $sonuc = $kosucu->kos($this->clock->now(), 'test:1');

        self::assertSame(5, $sonuc['islenen']);
        self::assertSame(5, $sonuc['basarili']);
        self::assertSame(0, $sonuc['basarisiz']);
        sort($alinan);
        self::assertSame($bekleyen, $alinan, 'Ucun yazdığı işlerin AYNISI alınmalı.');

        // 4) Tur sonunda kuyruk gerçekten boşalır — "biten 0" tablosu tekrarlanmaz.
        self::assertSame(0, $kuyruk->saglik($this->clock->now())['bekleyen']);
    }

    public function testAYNIDUGMEYEIKIKEZBASMAK_ISIIKIYEKATLAMAZ(): void
    {
        $this->urunEkle('无脚踏');

        $this->call('POST', '/api/panel/translate-backfill', [], [Csrf::HEADER => $this->csrf]);
        $this->call('POST', '/api/panel/translate-backfill', [], [Csrf::HEADER => $this->csrf]);

        // `tur + anahtar` UNIQUE: kullanıcının düğmeye üst üste basması zararsızdır.
        self::assertCount(1, $this->bekleyenIsler());
    }
}
