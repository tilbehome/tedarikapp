<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * V3-C AŞAMA 2.1 — TEKLİF TURU: ÜRÜN SAHİBİ TARAFI (İE#23 Blok B kalanı).
 *
 * Birim `liste × firma × tur` (K103). Bu süit sahibin döngüsünü uçtan uca
 * sınar: tur aç (DRAFT) → firmaya gönder (SENT) → yanıt → onayla / revizyon
 * iste / vazgeç. Firma tarafı (portal, Blok C) BU SÜİTTE YOK; firma geçişleri
 * yalnız durum makinesi kuralı olarak sınanır (sahip VIEWED yazamaz).
 *
 * GÖNDERİM ANINDA ÜÇ ŞEY DONAR — üçü de ayrı test:
 *   1. RFQ SNAPSHOT: firmaya gösterilen satırlar `rfq_lines`e kopyalanır; liste
 *      sonradan değişse de tur aynı kalır ("firma neyi gördü?" sorusunun tek
 *      cevabı).
 *   2. KUR DÖRTLÜSÜ (K104): `kur_para_birimi/kur_degeri/kur_kaynagi/kur_kilit_at`
 *      KOPYALANIR; `rate_snapshot_id` yalnız provenance.
 *   3. PAYLAŞIM: `shares` satırı TUR kimliğiyle açılır (liste geneli değil) ve
 *      gönderim günlüğüne düşer.
 */
final class TeklifTuruTest extends AuthTestCase
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

    /** @return array{liste: int, firma: int} */
    private function listeVeFirma(): array
    {
        $listeId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Eylül siparişi']))['data']['id'];
        $this->write('POST', '/api/lists/' . $listeId . '/products', ['name' => 'Termos', 'qty' => 24, 'price_yuan' => '12.00']);
        $this->write('POST', '/api/lists/' . $listeId . '/products', ['name' => 'Hoparlör', 'qty' => 10, 'price_yuan' => '35.50']);

        $firma = $this->json($this->write('POST', '/api/firmalar', ['ad' => 'Yiwu Test Co', 'varsayilan_dil' => 'zh']));
        self::assertSame(201, $firma['meta']['status'] ?? 201, json_encode($firma));

        return ['liste' => $listeId, 'firma' => (int) $firma['data']['id']];
    }

    /** @return array<string, mixed> */
    private function turAc(int $listeId, int $firmaId, array $ek = []): array
    {
        $yanit = $this->write('POST', '/api/lists/' . $listeId . '/turlar', ['firma_id' => $firmaId] + $ek);
        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());

        return $this->json($yanit)['data'];
    }

    // ── Açılış ─────────────────────────────────────────────────────────

    public function testTURTASLAKOLARAKDOGAR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();

        $tur = $this->turAc($l, $f);

        self::assertSame('DRAFT', $tur['state']);
        self::assertSame(1, $tur['tur_no']);
        self::assertNull($tur['rfq_snapshot_id'], 'Taslakta snapshot YOK — RFQ hâlâ düzenlenebilir.');
        self::assertNull($tur['sent_at']);
        self::assertSame('R1 taslak', $tur['etiket']);
    }

    public function testAYNIFIRMAYAIKINCITURNUMARAALIR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $ilk = $this->turAc($l, $f);
        $this->write('POST', '/api/turlar/' . $ilk['id'] . '/vazgec', ['sebep' => 'yanlış firma']);

        $ikinci = $this->turAc($l, $f);

        self::assertSame(2, $ikinci['tur_no'], 'Aynı liste+firma için tur numarası artar (uq_tur).');
    }

    public function testACIKTASLAKVARKENIKINCITASLAKACILMAZ(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $this->turAc($l, $f);

        $yanit = $this->write('POST', '/api/lists/' . $l . '/turlar', ['firma_id' => $f]);

        self::assertSame(422, $yanit->getStatusCode(), 'Aynı firmaya iki açık tur, firmaya iki link demektir.');
        self::assertSame('TUR_ACIK', $this->json($yanit)['error']['code']);
    }

    public function testBOSLISTEYETURACILMAZ(): void
    {
        $listeId = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Boş']))['data']['id'];
        $firmaId = (int) $this->json($this->write('POST', '/api/firmalar', ['ad' => 'F']))['data']['id'];

        $yanit = $this->write('POST', '/api/lists/' . $listeId . '/turlar', ['firma_id' => $firmaId]);

        self::assertSame(422, $yanit->getStatusCode(), 'Ürünsüz RFQ firmaya boş sayfa gönderir.');
    }

    // ── Gönderim: üç dondurma ──────────────────────────────────────────

    public function testGONDERIMRFQSNAPSHOTIDONDURUR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);

        $gonder = $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', ['gecerlilik_gun' => 15]);
        self::assertSame(200, $gonder->getStatusCode(), (string) $gonder->getBody());
        $sonuc = $this->json($gonder)['data'];

        self::assertSame('SENT', $sonuc['state']);
        self::assertNotNull($sonuc['rfq_snapshot_id']);
        self::assertSame(2, $sonuc['satir_sayisi']);
        self::assertSame('R1 gönderildi', $sonuc['etiket']);

        // Liste SONRADAN değişir: satır eklenir, miktar değişir.
        $this->write('POST', '/api/lists/' . $l . '/products', ['name' => 'Yeni ürün', 'qty' => 5, 'price_yuan' => '1.00']);
        $satirlar = $this->pdo->query('SELECT rfq_satir_id, talep_miktar, urun_adi_json FROM rfq_lines WHERE rfq_snapshot_id = ' . (int) $sonuc['rfq_snapshot_id'] . ' ORDER BY sira')->fetchAll(\PDO::FETCH_ASSOC);

        self::assertCount(2, $satirlar, 'Snapshot liste değişince BÜYÜMEZ.');
        self::assertSame('24', rtrim(rtrim((string) $satirlar[0]['talep_miktar'], '0'), '.'));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $satirlar[0]['rfq_satir_id'], 'Satır kimliği UUID — Excel/yapıştır eşleştirmesinin anahtarı.');
        self::assertStringContainsString('Termos', (string) $satirlar[0]['urun_adi_json']);
    }

    public function testGONDERIMKURDORTLUSUNUKOPYALAR(): void
    {
        // K104: tur kuru KOPYADIR. Snapshot silinse de tur değişmez.
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);

        $sonuc = $this->json($this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', []))['data'];

        self::assertSame('CNY', $sonuc['kur']['para_birimi']);
        self::assertMatchesRegularExpression('/^\d+\.\d{4}$/', $sonuc['kur']['deger'], 'Kur DECIMAL(12,4) string — float değil (K14).');
        self::assertNotNull($sonuc['kur']['kilit_at']);
        self::assertContains($sonuc['kur']['kaynak'], ['ayar', 'snapshot', 'liste']);

        $satir = $this->pdo->query('SELECT kur_degeri, kur_kilit_at FROM supplier_rounds WHERE id = ' . (int) $tur['id'])->fetch(\PDO::FETCH_ASSOC);
        self::assertNotNull($satir['kur_degeri']);
        self::assertNotNull($satir['kur_kilit_at']);
    }

    public function testGONDERIMTURAOZELPAYLASIMACARVEGUNLUGEYAZAR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);

        $sonuc = $this->json($this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', ['kanal' => 'whatsapp']))['data'];

        self::assertMatchesRegularExpression('#/liste/[0-9a-f]{64}$#', $sonuc['share_url'], 'Tur linki paylaşım sayfasını açar.');
        self::assertSame(64, strlen($sonuc['share_token']), 'Tam token YALNIZ bu yanıtta döner.');
        self::assertSame(6, strlen($sonuc['erisim_anahtari']), '6 haneli anahtar ayrı kanaldan gönderilir; yanıt onu da bir kez verir.');

        $paylasim = $this->pdo->query('SELECT supplier_round_id, token_prefix FROM shares WHERE supplier_round_id = ' . (int) $tur['id'])->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($paylasim, 'Paylaşım TUR kimliğiyle açılır (liste geneli değil).');

        $gunluk = $this->json($this->call('GET', '/api/lists/' . $l . '/gonderim-gunlugu'))['data'];
        self::assertCount(1, $gunluk);
        self::assertSame('whatsapp', $gunluk[0]['kanal']);
        self::assertSame((int) $tur['id'], $gunluk[0]['supplier_round_id']);
    }

    public function testGONDERILMISTURYENIDENGONDERILMEZ(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);
        $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', []);

        $yanit = $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', []);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('TUR_GECIS', $this->json($yanit)['error']['code']);
    }

    // ── Sahip kararları ────────────────────────────────────────────────

    private function yanitlanmisTur(): array
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);
        $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', []);
        // Firma yanıtı Blok C'de; burada satır doğrudan RESPONDED'a alınır.
        $this->pdo->exec("UPDATE supplier_rounds SET state = 'RESPONDED', responded_at = '2026-09-05 10:00:00' WHERE id = " . (int) $tur['id']);

        return ['liste' => $l, 'firma' => $f, 'tur' => (int) $tur['id']];
    }

    public function testYANITLANANTURONAYLANIR(): void
    {
        ['tur' => $turId] = $this->yanitlanmisTur();

        $sonuc = $this->json($this->write('POST', '/api/turlar/' . $turId . '/onayla', []))['data'];

        self::assertSame('APPROVED', $sonuc['state']);
        self::assertNotNull($sonuc['approved_at']);
    }

    public function testREVIZYONYENITURACARESKISIKAPANIR(): void
    {
        ['liste' => $l, 'tur' => $turId] = $this->yanitlanmisTur();

        $yanit = $this->write('POST', '/api/turlar/' . $turId . '/revizyon', ['sebep' => 'MOQ yüksek', 'rate_policy' => 'inherit']);
        self::assertSame(201, $yanit->getStatusCode(), (string) $yanit->getBody());
        $yeni = $this->json($yanit)['data'];

        self::assertSame('DRAFT', $yeni['state']);
        self::assertSame(2, $yeni['tur_no']);
        self::assertSame($turId, $yeni['parent_round_id']);
        self::assertSame('R2 taslak', $yeni['etiket']);

        $eski = $this->pdo->query('SELECT state, state_reason FROM supplier_rounds WHERE id = ' . $turId)->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('REVISION_REQUESTED', $eski['state']);
        self::assertSame('MOQ yüksek', $eski['state_reason']);

        // inherit: yeni tur eski turun kurunu TAŞIR (kopya, referans değil).
        $kurlar = $this->pdo->query('SELECT id, kur_degeri FROM supplier_rounds WHERE list_id = ' . $l . ' ORDER BY tur_no')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertSame($kurlar[0]['kur_degeri'], $kurlar[1]['kur_degeri']);
    }

    public function testTASLAKTANVAZGECILIR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);

        $sonuc = $this->json($this->write('POST', '/api/turlar/' . $tur['id'] . '/vazgec', ['sebep' => 'firma cevap vermedi']))['data'];

        self::assertSame('ABANDONED', $sonuc['state']);
    }

    public function testGECERSIZGECIS422(): void
    {
        // DRAFT → APPROVED yok: gönderilmemiş teklif onaylanamaz.
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);

        $yanit = $this->write('POST', '/api/turlar/' . $tur['id'] . '/onayla', []);

        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('TUR_GECIS', $this->json($yanit)['error']['code']);
    }

    public function testSAHIPGORUNTULENDIYAZAMAZ(): void
    {
        // VIEWED bir GÖZLEMDİR; sahip yazabilseydi bekleme süresi yalan söylerdi.
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);
        $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', []);

        $yanit = $this->write('PATCH', '/api/turlar/' . $tur['id'], ['state' => 'VIEWED']);

        self::assertContains($yanit->getStatusCode(), [404, 405, 422], 'Sahibin elle durum yazma yolu YOK.');
        self::assertSame('SENT', $this->pdo->query('SELECT state FROM supplier_rounds WHERE id = ' . (int) $tur['id'])->fetchColumn());
    }

    // ── Teklifler ekranı verisi ─────────────────────────────────────────

    public function testACIKVEGECMISTURLARAYRILISTELENIR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $acik = $this->turAc($l, $f);
        $this->write('POST', '/api/turlar/' . $acik['id'] . '/gonder', []);
        $firma2 = (int) $this->json($this->write('POST', '/api/firmalar', ['ad' => 'İkinci Firma']))['data']['id'];
        $kapali = $this->turAc($l, $firma2);
        $this->write('POST', '/api/turlar/' . $kapali['id'] . '/vazgec', ['sebep' => 'x']);

        $veri = $this->json($this->call('GET', '/api/teklifler'))['data'];

        self::assertCount(1, $veri['acik']);
        self::assertCount(1, $veri['gecmis']);
        self::assertSame('Yiwu Test Co', $veri['acik'][0]['firma_adi']);
        self::assertSame('Eylül siparişi', $veri['acik'][0]['liste_adi']);
        // Ana kolon: açıldı mı / kaç gündür bekliyor (yol haritası §7.6).
        self::assertArrayHasKey('bekleme_gun', $veri['acik'][0]);
        self::assertFalse($veri['acik'][0]['goruntulendi']);
    }

    public function testLISTENINTURLARIDETAYDAGORUNUR(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);

        $turlar = $this->json($this->call('GET', '/api/lists/' . $l . '/turlar'))['data'];

        self::assertCount(1, $turlar);
        self::assertSame((int) $tur['id'], $turlar[0]['id']);
        self::assertSame('Yiwu Test Co', $turlar[0]['firma_adi']);
    }

    public function testKAPALILISTEDETURACILMAZ(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $this->write('PATCH', '/api/lists/' . $l, ['status' => 'cancelled']);

        $yanit = $this->write('POST', '/api/lists/' . $l . '/turlar', ['firma_id' => $f]);

        self::assertSame(422, $yanit->getStatusCode());
    }

    public function testAKTIVITEIZIVEBILDIRIM(): void
    {
        ['liste' => $l, 'firma' => $f] = $this->listeVeFirma();
        $tur = $this->turAc($l, $f);
        $this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', []);

        $eylemler = $this->pdo->query("SELECT action FROM activity_log WHERE entity_type = 'supplier_round' ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame(['round_drafted', 'round_sent'], $eylemler);
    }
}
