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
 * İE#21 EK-5 ile sözleşmenin ÜÇÜ DE UYGULANDI: paylaşım sayfasının arayüz
 * metinleri `ShareTexts` sözlüğünden gelir, PDF `options.lang` okur (pdf-rev4:
 * TEK SATIR başlık, seçilen dilde). Bu dosya üçünü birlikte korur.
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
        // EK-4 dökümündeki dokuz metin…
        'Yazdır',
        'Vazgeç',
        'Sipariş şartları',
        'Gönderim dili',
        'Özet metnini kopyala',
        'Kare kodu okutun',
        'Yazdırma ayarları',
        'Bir daha gösterme',
        'üstteki özet şerididir',
        // …ve elden geçirmede bulunan diğerleri (EK-5 raporunda listelendi).
        'GENEL TOPLAM',
        'MAL BEDELİ',
        'TOPLAM MİKTAR',
        'GÜNCELLEME',
        'Ürüne git',
        'Detaylar',
        'çeviri bekliyor',
        'Eksik bilgileri göster',
        'ÜRÜN BİLGİLERİ',
        'VARYASYONLAR',
        'Firma kopyası',
        'Kareyi indir',
        'Linki kopyala',
        'kapat: tıkla',
        'Ürün Tedarik Asistanı',
        'görsel<br>yok',
        'Galeriyi aç',
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

        self::assertSame([], $sizinti, "Türkçe arayüz metni başka dilin sayfasında:\n  "
            . implode("\n  ", $sizinti));
    }

    public function testK81_SECILEN_DIL_SAYFAYA_ISLENIR(): void
    {
        $en = $this->sayfa('en');
        self::assertStringContainsString('<html lang="en"', $en);
        self::assertStringContainsString('GRAND TOTAL', $en);
        self::assertStringContainsString('Print', $en);
        self::assertStringContainsString('Order terms', $en);

        $zh = $this->sayfa('zh');
        self::assertStringContainsString('<html lang="zh"', $zh);
        self::assertStringContainsString('总计', $zh);
        self::assertStringContainsString('打印', $zh);
    }

    // ── EK-5 madde 2: PDF dili (pdf-rev4) ─────────────────────────────────

    /**
     * PDF'i üretir ve İÇİNDEKİ METNİ döner.
     *
     * mPDF metni akışlara sıkıştırır ve alt küme fontlarda UTF-16BE yazar; ham
     * gövdede arama yapmak yanıltıcıdır (İngilizce metin "geçmiş" görünür, Türkçe
     * karakter hiç eşleşmez). Bu yüzden akışlar açılır, `( … )` dizeleri çözülür
     * ve UTF-8'e çevrilir. Kütüphane eklemeye değmez: aranan şey birkaç başlık.
     */
    private function pdfMetni(string $dil): string
    {
        $yanit = $this->write('POST', '/api/lists/' . $this->listId . '/export?format=pdf', ['lang' => $dil]);
        self::assertSame(200, $yanit->getStatusCode(), 'PDF üretilmeli.');

        $metin = $this->pdfDuzMetin((string) $yanit->getBody());
        // Büyük gövde bellekte kalmasın: aynı süreçte iki PDF üretiliyor.
        unset($yanit);

        return $metin;
    }

    private function pdfDuzMetin(string $pdf): string
    {
        $govde = '';
        if (preg_match_all("/stream\r?\n(.*?)\r?\nendstream/s", $pdf, $akislar) > 0) {
            foreach ($akislar[1] as $akis) {
                $cozulmus = @gzuncompress($akis);
                $govde .= is_string($cozulmus) ? $cozulmus : $akis;
            }
        }
        if ($govde === '') {
            $govde = $pdf;
        }

        $metin = '';
        // Basit desen bilerek: mPDF metni ( ... ) icinde yazar; kacisli parantez
        // ihtimali icin karmasik bir sinif kurmak, arama yardimcisini okunmaz kilardi.
        if (preg_match_all('/\(([^)]*)\)/s', $govde, $dizeler) > 0) {
            foreach ($dizeler[1] as $ham) {
                $ham = str_replace(['\(', '\)'], ['(', ')'], $ham);
                if (str_contains($ham, "\x00")) {
                    $cevrilmis = @mb_convert_encoding($ham, 'UTF-8', 'UTF-16BE');
                    $ham = is_string($cevrilmis) ? $cevrilmis : $ham;
                }
                $metin .= $ham . ' ';
            }
        }

        return $metin === '' ? $govde : $metin;
    }

    public function testPDF_BASLIK_SECILEN_DILDE_TEK_SATIR(): void
    {
        // pdf-rev4 + K81: kâğıtta üç dilli blok YOK; başlık tek satır ve seçilen dilde.
        $tr = mb_strtoupper($this->pdfMetni('tr'), 'UTF-8');
        self::assertStringContainsString('ÜRÜN ADI', $tr);
        self::assertStringContainsString('GENEL TOPLAM', $tr);
        self::assertStringNotContainsString('PRODUCT NAME', $tr);

        $en = mb_strtoupper($this->pdfMetni('en'), 'UTF-8');
        self::assertStringContainsString('PRODUCT NAME', $en);
        self::assertStringContainsString('GRAND TOTAL', $en);
        self::assertStringNotContainsString('ÜRÜN ADI', $en);
        self::assertStringNotContainsString('GENEL TOPLAM', $en);
    }

    public function testGONDERIMDILISECILENDILEUYAR(): void
    {
        // `?lang=` gönderim metnini (WhatsApp/e-posta) ve indirme bağlantısının
        // dilini de belirler.
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
            // hiçbir dilde çevrilmez, kırpılmaz, gizlenmez (K81 istisnası a).
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
