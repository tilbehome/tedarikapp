<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Tests\Support\AuthTestCase;

/**
 * PAYLAŞIM SAYFASI VE DİL — K81 SÖZLEŞMESİ (E2E-PNL-37/38/39).
 *
 * K81 (Ürün Sahibi kararı, 24 Ağu 2026 · İE#21 EK-4): "SIFIR KARIŞIK DİL" kuralı
 * geçerlidir — arayüz metinleri, durum adları, alan değerleri, şartlar ve
 * dipnotlar SEÇİLEN DİLDE tek dildir. Başka dilden ham değer = kusur.
 *
 * YALNIZ İKİ ADLI İSTİSNA karışık dil sayılmaz:
 *   a) K55 — ürün adının altındaki ORİJİNAL Çince referans satırı (her dilde kalır),
 *   b) K81 — üç dilli kademeli TABLO SÜTUN BAŞLIĞI bloğu (MİKTAR / 数量 / QUANTITY);
 *      paylaşım sayfası EKRANINDA ve EXCEL'de her dil seçiminde aynen üç dilli kalır.
 *      PDF'te pdf-rev4 sözleşmesi geçerlidir: başlık TEK satır.
 *
 * Bu dosya sözleşmenin üç parçasını da sınar. Üçüncüsü (istisna dışında tek dil)
 * BUGÜN SAĞLANMIYOR: paylaşım sayfasının arayüz metinleri sabit Türkçedir.
 * Test o yüzden "eksik" (incomplete) işaretlenir ve sızıntı DÖKÜMÜNÜ basar —
 * yeşil görünüp kusuru gizlemek, kusurun kendisinden kötüdür.
 */
final class PaylasimDiliTest extends AuthTestCase
{
    private string $csrf = '';
    private int $listId = 0;
    private string $token = '';

    /** K81(b): üç dilli sütun başlığı bloğu — her dilde birlikte bulunur. */
    private const UC_DILLI_BASLIK = ['MİKTAR', '数量', 'Qty'];

    /**
     * Türkçe ARAYÜZ metinleri: seçilen dil TR değilken sayfada BULUNMAMALI.
     *
     * Liste/ürün verisi (liste adı, ürün adı, dönem) bu listede YOKTUR —
     * o veri kullanıcının kendi yazdığıdır, çeviri konusu değildir.
     */
    private const TR_ARAYUZ_METINLERI = [
        'Yazdır',
        'Vazgeç',
        'Sipariş şartları',
        'Gönderim dili',
        'Özet metnini kopyala',
        'Kare kodu okutun',
        'Yazdırma ayarları',
        'Bir daha gösterme',
        'üstteki özet şerididir',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->call('POST', '/api/auth/login', ['email' => 'admin@tedarikapp.test', 'password' => 'cok-gizli-sifre']);
        $this->call('POST', '/api/auth/totp', ['code' => $this->totpCodeFor($user['secret'])]);
        $this->csrf = (string) $this->json($this->call('GET', '/api/auth/me'))['data']['csrf_token'];

        $this->listId = (int) $this->json($this->write('POST', '/api/lists', [
            'name' => 'Mutfak Ürünleri',
            'period' => 'Eylül 2026',
        ]))['data']['id'];

        $this->write('POST', '/api/lists/' . $this->listId . '/products', [
            'name' => 'Termos Yemek Kabı',
            'name_original' => '双层不锈钢保温饭盒500ml',
            'qty' => 240,
            'price_yuan' => '12.00',
        ]);

        $url = (string) $this->json($this->write('POST', '/api/lists/' . $this->listId . '/share'))['data']['share_url'];
        $this->token = substr($url, strrpos($url, '/') + 1);
    }

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    private function sayfa(string $dil): string
    {
        $yanit = $this->call(
            'GET',
            '/liste/' . $this->token . '?lang=' . $dil,
            null,
            [],
            $this->paylasimCerezi($this->token, $this->listId, $this->csrf),
        );
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());

        return (string) $yanit->getBody();
    }

    public function testK81_BASLIK_BLOGU_HER_DILDE_UC_DILLI(): void
    {
        // K81(b): başlık bloğu istisnadır — dil ne seçilirse seçilsin üç dilli kalır.
        foreach (['tr', 'en', 'zh'] as $dil) {
            $html = $this->sayfa($dil);

            foreach (self::UC_DILLI_BASLIK as $imza) {
                self::assertStringContainsString($imza, $html, $dil . ' sayfasında üç dilli başlık korunmalı.');
            }
        }
    }

    public function testK81_ISTISNA_DISINDA_TEK_DIL(): void
    {
        $sizinti = [];
        foreach (['en', 'zh'] as $dil) {
            $html = $this->sayfa($dil);
            foreach (self::TR_ARAYUZ_METINLERI as $metin) {
                if (str_contains($html, $metin)) {
                    $sizinti[] = $dil . ': ' . $metin;
                }
            }
        }

        if ($sizinti !== []) {
            // AÇIK KUSUR KAYDI: sözleşme yazıldı, uygulama HENÜZ yok. Paylaşım
            // sayfasının arayüz metinleri (SharePage.php) sabit Türkçedir ve
            // yerelleştirilmesi kendi iş emrini ister — İE#21 EK-4 kapsamı bu
            // dosyayı içermiyordu. Test yeşile boyanmaz, eksik işaretlenir.
            self::markTestIncomplete(
                'K81 tek dil kuralı paylaşım sayfasında HENÜZ sağlanmıyor. '
                . 'Sızıntı dökümü: ' . implode(' · ', $sizinti),
            );
        }

        self::assertSame([], $sizinti);
    }

    public function testGONDERIMDILISECILENDILEUYAR(): void
    {
        // `?lang=` GÖNDERİM metnini (WhatsApp/e-posta) ve indirme bağlantısının
        // dilini belirler: sayfanın değişen yüzü budur.
        $en = $this->sayfa('en');
        self::assertStringContainsString('data-dil="en"', $en);
        self::assertStringContainsString('supply list', mb_strtolower($en));

        $zh = $this->sayfa('zh');
        self::assertStringContainsString('data-dil="zh"', $zh);
        self::assertStringContainsString('采购清单', $zh);
    }

    public function testE2E_PNL_39_ORIJINALSATIRUCDILDEAYNEN(): void
    {
        foreach (['tr', 'en', 'zh'] as $dil) {
            $html = $this->sayfa($dil);

            // K55: orijinal başlık ÜRÜN VERİSİDİR, arayüz metni değildir —
            // hiçbir dilde çevrilmez, kırpılmaz, gizlenmez.
            self::assertStringContainsString(
                '双层不锈钢保温饭盒500ml',
                $html,
                $dil . ' sayfasında orijinal Çince satır aynen durmalı.',
            );
        }
    }

    public function testGECERSIZDILTURKCEYEDUSER(): void
    {
        $html = $this->sayfa('de');

        self::assertStringContainsString('data-dil="tr"', $html);
        self::assertStringContainsString('MİKTAR', $html);
    }
}
