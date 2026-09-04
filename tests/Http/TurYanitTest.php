<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use App\Services\Yanit\ExcelSema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * V3-C AŞAMA 2.2 — FİRMA YANITI: YAPIŞTIR-AYRIŞTIR + EXCEL GEL-GİT (uçtan uca).
 *
 * İki kanal aynı akıştan geçer: ÖNİZLE (hiçbir şey yazılmaz) → sahip seçer →
 * UYGULA (tek transaction, parmak izi ile idempotent) → tur PRICING.
 *
 * Fail-closed kapılar burada uç seviyesinde sınanır: gönderilmemiş tur,
 * kilitli tur, yabancı satır, alan hatası (hiçbiri yazılmaz), aynı parmak
 * izinin ikinci uygulaması (tekrar=true, yazım yok), boş alanın mevcut
 * değeri silmemesi, başka turun Excel dosyası.
 */
final class TurYanitTest extends AuthTestCase
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

    /** @param array<string, mixed> $body */
    private function write(string $method, string $path, array $body = []): ResponseInterface
    {
        return $this->call($method, $path, $body, [Csrf::HEADER => $this->csrf]);
    }

    /**
     * Liste + 2 ürün + firma + GÖNDERİLMİŞ tur. Döner: tur id + rfq satırları (urun_kodu ile).
     *
     * @return array{tur: int, rfq: list<array<string, mixed>>, liste: int}
     */
    private function gonderilmisTur(string $listeAdi = 'Eylül siparişi'): array
    {
        $listeId = (int) $this->json($this->write('POST', '/api/lists', ['name' => $listeAdi]))['data']['id'];
        $this->write('POST', '/api/lists/' . $listeId . '/products', ['name' => 'Termos', 'qty' => 24, 'price_yuan' => '12.00']);
        $this->write('POST', '/api/lists/' . $listeId . '/products', ['name' => 'Hoparlör', 'qty' => 10, 'price_yuan' => '35.50']);
        $firmaId = (int) $this->json($this->write('POST', '/api/firmalar', ['ad' => 'Yiwu Test Co', 'varsayilan_dil' => 'zh']))['data']['id'];
        $tur = $this->json($this->write('POST', '/api/lists/' . $listeId . '/turlar', ['firma_id' => $firmaId]))['data'];
        $gonder = $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', ['gecerlilik_gun' => 15]);
        self::assertSame(200, $gonder->getStatusCode(), (string) $gonder->getBody());
        $goster = $this->json($this->call('GET', '/api/turlar/' . $tur['id']))['data'];

        return ['tur' => (int) $tur['id'], 'rfq' => $goster['rfq_satirlari'], 'liste' => $listeId];
    }

    // ── yapıştır-ayrıştır ──────────────────────────────────────────────

    public function testYAPISTIRONIZLEMESIYAZMAZVEKODLAESLESTIRIR(): void
    {
        ['tur' => $turId, 'rfq' => $rfq] = $this->gonderilmisTur();
        $k1 = $rfq[0]['urun_kodu'];
        $k2 = $rfq[1]['urun_kodu'];
        $metin = "报价如下：\n{$k1} 有货，DDP含土耳其税 USD 4.20，MOQ 300，订单确认后20天。\n{$k2} 无货，面料停做。";

        $yanit = $this->write('POST', '/api/turlar/' . $turId . '/yapistir-ayristir', ['metin' => $metin]);
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());
        $on = $this->json($yanit)['data'];

        self::assertCount(2, $on['satirlar']);
        self::assertSame([], $on['belirsiz']);
        self::assertSame($rfq[0]['rfq_satir_id'], $on['satirlar'][0]['rfq_satir_id']);
        self::assertSame('found', $on['satirlar'][0]['yeni']['yanit_durumu']);
        self::assertSame('4.20', $on['satirlar'][0]['yeni']['ddp_birim_fiyat']);
        self::assertSame('USD', $on['satirlar'][0]['yeni']['para_birimi']);
        self::assertSame('not_found', $on['satirlar'][1]['yeni']['yanit_durumu']);
        self::assertTrue($on['satirlar'][0]['varsayilan_secili']);
        self::assertSame(64, strlen($on['parmak_izi']));

        // Önizleme YAZMAZ.
        $mevcut = $this->json($this->call('GET', '/api/turlar/' . $turId . '/yanit'))['data'];
        self::assertSame([], $mevcut['satirlar']);
        self::assertSame('SENT', $mevcut['state']);
    }

    public function testGONDERILMEMISTURDAYAPISTIRREDDEDILIR(): void
    {
        $listeId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Taslak']))['data']['id'];
        $this->write('POST', '/api/lists/' . $listeId . '/products', ['name' => 'Termos', 'qty' => 24, 'price_yuan' => '12.00']);
        $firmaId = (int) $this->json($this->write('POST', '/api/firmalar', ['ad' => 'F']))['data']['id'];
        $tur = $this->json($this->write('POST', '/api/lists/' . $listeId . '/turlar', ['firma_id' => $firmaId]))['data'];

        $yanit = $this->write('POST', '/api/turlar/' . $tur['id'] . '/yapistir-ayristir', ['metin' => 'P00001 USD 1']);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('TUR_GONDERILMEMIS', $this->json($yanit)['error']['code']);
    }

    // ── uygula ─────────────────────────────────────────────────────────

    public function testUYGULAYAZARPRICINGYAPARVEAYNIPARMAKIZIIKINCIKEZYAZMAZ(): void
    {
        ['tur' => $turId, 'rfq' => $rfq] = $this->gonderilmisTur();
        $on = $this->onizle($turId, "{$rfq[0]['urun_kodu']} 有货，DDP含土耳其税 USD 4.20，MOQ 300，订单确认后20天。");
        $govde = ['kaynak' => 'yapistir', 'parmak_izi' => $on['parmak_izi'], 'satirlar' => [$on['satirlar'][0]['yeni']]];

        $ilk = $this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', $govde);
        self::assertSame(200, $ilk->getStatusCode(), (string) $ilk->getBody());
        $sonuc = $this->json($ilk)['data'];
        self::assertFalse($sonuc['tekrar']);
        self::assertSame(1, $sonuc['yazilan']);
        self::assertSame('PRICING', $sonuc['state']);
        $satir = $sonuc['yanit'][$rfq[0]['rfq_satir_id']];
        self::assertSame('4.2', $satir['ddp_birim_fiyat']);
        self::assertSame('300', $satir['moq_deger']);
        self::assertSame(20, $satir['termin_suresi']);
        self::assertSame('order_confirmation', $satir['termin_baslangici']);

        $ikinci = $this->json($this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', $govde))['data'];
        self::assertTrue($ikinci['tekrar'], 'Aynı parmak izi ikinci kez YAZMAZ (tek kullanımlık anahtar).');
        self::assertSame(0, $ikinci['yazilan']);

        $aktivite = $this->pdo->query("SELECT COUNT(*) FROM activity_log WHERE action = 'quote_imported'")->fetchColumn();
        self::assertSame(1, (int) $aktivite, 'Tek uygulama, tek aktivite (actor=product_owner).');
    }

    public function testALANHATASIVARSAHICBIRSATIRYAZILMAZ(): void
    {
        ['tur' => $turId, 'rfq' => $rfq] = $this->gonderilmisTur();
        $on = $this->onizle($turId, "{$rfq[0]['urun_kodu']} 有货，DDP含土耳其税 USD 4.20，MOQ 300，订单确认后20天。\n{$rfq[1]['urun_kodu']} 有货，DDP含土耳其税 USD 0，MOQ 100，订单后10天。");
        $satirlar = array_map(static fn (array $s): array => $s['yeni'], $on['satirlar']);
        $satirlar[1]['ddp_birim_fiyat'] = '0'; // önizleme fiyat 0'ı düşürmüştü; istemci zorlarsa sunucu yakalar

        $yanit = $this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', ['kaynak' => 'yapistir', 'parmak_izi' => $on['parmak_izi'], 'satirlar' => $satirlar]);

        self::assertSame(422, $yanit->getStatusCode());
        $zarf = $this->json($yanit);
        self::assertSame('YANIT_GECERSIZ', $zarf['error']['code']);
        self::assertSame($rfq[1]['rfq_satir_id'], $zarf['meta']['hatalar'][0]['satir_id']);
        self::assertSame([], $this->json($this->call('GET', '/api/turlar/' . $turId . '/yanit'))['data']['satirlar'], 'Geçerli satır bile yazılmadı.');
    }

    public function testYABANCISATIRVEKILITLITURREDDEDILIR(): void
    {
        ['tur' => $turId, 'rfq' => $rfq] = $this->gonderilmisTur();
        $on = $this->onizle($turId, "{$rfq[0]['urun_kodu']} 有货，DDP含土耳其税 USD 4.20，MOQ 300，订单确认后20天。");

        $yabanci = $this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', [
            'kaynak' => 'yapistir', 'parmak_izi' => $on['parmak_izi'],
            'satirlar' => [['rfq_satir_id' => 'yok-boyle-satir', 'yanit_durumu' => 'found', 'ddp_birim_fiyat' => '1', 'para_birimi' => 'USD']],
        ]);
        self::assertSame(422, $yabanci->getStatusCode());
        self::assertSame('SATIR_YABANCI', $this->json($yabanci)['error']['code']);

        $this->write('POST', '/api/turlar/' . $turId . '/vazgec', ['sebep' => 'kapat']);
        $kilitli = $this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', ['kaynak' => 'yapistir', 'parmak_izi' => $on['parmak_izi'], 'satirlar' => [$on['satirlar'][0]['yeni']]]);
        self::assertSame(422, $kilitli->getStatusCode());
        self::assertSame('TUR_KILITLI', $this->json($kilitli)['error']['code']);
        self::assertStringContainsString('revizyon', $this->json($kilitli)['error']['message']);
    }

    public function testBOSALANMEVCUTDEGERISILMEZACIKTEMIZLESILER(): void
    {
        ['tur' => $turId, 'rfq' => $rfq] = $this->gonderilmisTur();
        $id = $rfq[0]['rfq_satir_id'];
        $on = $this->onizle($turId, "{$rfq[0]['urun_kodu']} 有货，DDP含土耳其税 USD 4.20，MOQ 300，订单确认后20天。");
        $this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', ['kaynak' => 'yapistir', 'parmak_izi' => $on['parmak_izi'], 'satirlar' => [$on['satirlar'][0]['yeni']]]);

        // Yalnız MOQ gelen ikinci uygulama: fiyat KORUNUR.
        $ikinci = $this->json($this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', [
            'kaynak' => 'yapistir', 'parmak_izi' => str_repeat('b', 64),
            'satirlar' => [['rfq_satir_id' => $id, 'moq_deger' => '500']],
        ]))['data'];
        self::assertSame('4.2', $ikinci['yanit'][$id]['ddp_birim_fiyat']);
        self::assertSame('500', $ikinci['yanit'][$id]['moq_deger']);

        // Açık temizleme: yalnız `temizle` listesindeki alan null olur.
        $ucuncu = $this->json($this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', [
            'kaynak' => 'yapistir', 'parmak_izi' => str_repeat('c', 64),
            'satirlar' => [['rfq_satir_id' => $id, 'temizle' => ['moq_deger']]],
        ]))['data'];
        self::assertNull($ucuncu['yanit'][$id]['moq_deger']);
        self::assertSame('4.2', $ucuncu['yanit'][$id]['ddp_birim_fiyat']);
    }

    // ── Excel gel-git ──────────────────────────────────────────────────

    public function testEXCELSABLONDOLDURULUPGERIYUKLENIRVEUYGULANIR(): void
    {
        ['tur' => $turId, 'rfq' => $rfq] = $this->gonderilmisTur();

        $sablon = $this->write('POST', '/api/turlar/' . $turId . '/excel-sablon', ['dil' => 'tr']);
        self::assertSame(200, $sablon->getStatusCode());
        self::assertStringContainsString('attachment; filename="RFQ-', $sablon->getHeaderLine('Content-Disposition'));
        $bytes = (string) $sablon->getBody();
        self::assertStringStartsWith('PK', $bytes);

        $dolu = $this->excelDuzenle($bytes, static function (\PhpOffice\PhpSpreadsheet\Spreadsheet $k) use ($rfq): void {
            $q = $k->getSheetByName(ExcelSema::SAYFA_QUOTATION);
            foreach (['K' => 'found', 'L' => '5,10', 'M' => 'USD', 'N' => 'YES', 'O' => '20', 'Q' => 'deposit_received', 'S' => '30', 'T' => 'calendar_day', 'AC' => 'white box'] as $h => $v) {
                $q?->setCellValueExplicit($h . '2', $v, DataType::TYPE_STRING);
            }
            $t = $k->getSheetByName(ExcelSema::SAYFA_TIERS);
            $t?->setCellValueExplicit('A2', $rfq[0]['rfq_satir_id'], DataType::TYPE_STRING);
            $t?->setCellValue('C2', 20);
            $t?->setCellValue('E2', 5.1);
            $t?->setCellValueExplicit('A3', $rfq[0]['rfq_satir_id'], DataType::TYPE_STRING);
            $t?->setCellValue('C3', 1000);
            $t?->setCellValue('E3', 4.7);
        });

        $on = $this->write('POST', '/api/turlar/' . $turId . '/excel-ice-aktar', ['dosya_base64' => base64_encode($dolu)]);
        self::assertSame(200, $on->getStatusCode(), (string) $on->getBody());
        $onizleme = $this->json($on)['data'];
        self::assertSame(1, $onizleme['ozet']['uygulanabilir']);
        self::assertSame(1, $onizleme['ozet']['degisiklik_yok']);
        $satir = $onizleme['satirlar'][0];
        self::assertSame('5.10', $satir['yeni']['ddp_birim_fiyat'], 'Virgüllü ondalık noktaya çevrilir.');
        self::assertCount(2, $satir['yeni']['kademeler']);

        $uygula = $this->json($this->write('POST', '/api/turlar/' . $turId . '/yanit-uygula', [
            'kaynak' => 'excel', 'parmak_izi' => $onizleme['parmak_izi'], 'etiket' => 'firma-r1.xlsx',
            'satirlar' => [$satir['yeni']],
        ]))['data'];
        self::assertSame(1, $uygula['yazilan']);
        self::assertSame('PRICING', $uygula['state']);
        $kayit = $uygula['yanit'][$rfq[0]['rfq_satir_id']];
        self::assertSame('white box', $kayit['firma_notu']);
        self::assertSame('1000', $kayit['kademeler'][1]['min_adet']);
        self::assertSame('4.7', $kayit['kademeler'][1]['birim_fiyat']);

        // Tekrar indirilen şablon dolu gelir (firma kaldığı yerden devam eder).
        $yeniden = (string) $this->write('POST', '/api/turlar/' . $turId . '/excel-sablon')->getBody();
        $kitap = $this->excelYukle($yeniden);
        self::assertSame('5.1', $kitap->getSheetByName(ExcelSema::SAYFA_QUOTATION)?->getCell('L2')->getValue());

        // Sonuç dosyası.
        $sonuc = $this->write('POST', '/api/turlar/' . $turId . '/excel-sonuc', ['onizleme' => $onizleme + ['uygulanan' => [$rfq[0]['rfq_satir_id']]]]);
        self::assertSame(200, $sonuc->getStatusCode());
        self::assertStringContainsString('IMPORT-RESULT.xlsx', $sonuc->getHeaderLine('Content-Disposition'));
    }

    public function testBASKATURUNEXCELDOSYASIREDDEDILIR(): void
    {
        ['tur' => $a] = $this->gonderilmisTur('A listesi');
        ['tur' => $b] = $this->gonderilmisTur('B listesi');
        $bytes = (string) $this->write('POST', '/api/turlar/' . $a . '/excel-sablon')->getBody();

        $yanit = $this->write('POST', '/api/turlar/' . $b . '/excel-ice-aktar', ['dosya_base64' => base64_encode($bytes)]);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('DOSYA_YABANCI', $this->json($yanit)['error']['code']);
    }

    // ── yardımcılar ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function onizle(int $turId, string $metin): array
    {
        $yanit = $this->write('POST', '/api/turlar/' . $turId . '/yapistir-ayristir', ['metin' => $metin]);
        self::assertSame(200, $yanit->getStatusCode(), (string) $yanit->getBody());

        return $this->json($yanit)['data'];
    }

    private function excelYukle(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $yol = tempnam(sys_get_temp_dir(), 'ta-xlsx-');
        self::assertNotFalse($yol);
        file_put_contents($yol, $bytes);
        try {
            return IOFactory::load($yol);
        } finally {
            @unlink($yol);
        }
    }

    /** @param callable(\PhpOffice\PhpSpreadsheet\Spreadsheet): void $degisiklik */
    private function excelDuzenle(string $bytes, callable $degisiklik): string
    {
        $kitap = $this->excelYukle($bytes);
        $degisiklik($kitap);
        $yol = tempnam(sys_get_temp_dir(), 'ta-xlsx-');
        self::assertNotFalse($yol);
        (new Xlsx($kitap))->save($yol);
        $kitap->disconnectWorksheets();
        $sonuc = (string) file_get_contents($yol);
        @unlink($yol);

        return $sonuc;
    }
}
