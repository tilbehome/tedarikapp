<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Middleware\Csrf;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\AuthTestCase;

/**
 * V3-C BLOK E — LİSTELER MERKEZİ (sunucu tarafı).
 *
 * Üç şey sınanır:
 *   1. SEKME TÜRETİMİ: `sekme` liste durumundan + turlardan hesaplanır, saklanmaz.
 *      draft→hazirlaniyor · sent→fiyat_bekleniyor · yanıt uygulanmış tur PRICING
 *      → hâlâ fiyat_bekleniyor (yanıt tamamlanmadı) · ordered→onayli.
 *      `?sekme=` filtresi çalışır; `meta.sayimlar` FİLTRESİZ kümeyi sayar.
 *   2. "18/25 FİYATLANDI" çubuğu: gönderilmiş tur varsa snapshot satırı /
 *      fiyatlı yanıt satırı; tur yoksa ürünlerin DDP alanı — iki kaynak, tek anlam.
 *   3. ŞABLONLAR (list_templates): listeden şablon → şablondan TASLAK liste
 *      (ürünler to_order, günün kuru, kullanım sayacı artar); boş listeden
 *      şablon alınmaz; silme 204. Görünümler: ekran başına kaydet/sil, ad
 *      eşsiz, varsayılan tek.
 */
final class ListelerMerkeziTest extends AuthTestCase
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

    private function liste(string $ad, int $urun = 2, ?string $ddp = null): int
    {
        $id = (int) $this->json($this->write('POST', '/api/lists', ['name' => $ad, 'period' => 'Eylül 2026']))['data']['id'];
        for ($i = 1; $i <= $urun; $i++) {
            $govde = ['name' => 'Ürün ' . $i, 'qty' => 10 * $i, 'price_yuan' => '12.00'];
            if ($ddp !== null) {
                $govde['price_ddp_usd'] = $ddp;
            }
            $this->write('POST', '/api/lists/' . $id . '/products', $govde);
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function listeyiBul(int $id, string $sorgu = ''): array
    {
        $zarf = $this->json($this->call('GET', '/api/lists' . $sorgu));
        foreach ($zarf['data'] as $l) {
            if ((int) $l['id'] === $id) {
                return $l;
            }
        }
        self::fail('Liste yanıtta yok: ' . $id);
    }

    // ── sekme türetimi ─────────────────────────────────────────────────

    public function testSEKMEDURUMVETURDANTURETILIRVEFILTRELENIR(): void
    {
        $taslak = $this->liste('Taslak');
        $gonderilen = $this->liste('Gönderilen');
        $this->write('PATCH', '/api/lists/' . $gonderilen, ['status' => 'sent']);
        $siparis = $this->liste('Sipariş');
        $this->write('PATCH', '/api/lists/' . $siparis, ['status' => 'sent']);
        $this->write('PATCH', '/api/lists/' . $siparis, ['status' => 'ordered']);

        self::assertSame('hazirlaniyor', $this->listeyiBul($taslak)['sekme']);
        self::assertSame('fiyat_bekleniyor', $this->listeyiBul($gonderilen)['sekme']);
        self::assertSame('onayli', $this->listeyiBul($siparis)['sekme']);

        $zarf = $this->json($this->call('GET', '/api/lists?sekme=hazirlaniyor'));
        self::assertSame([$taslak], array_map(static fn (array $l): int => (int) $l['id'], $zarf['data']));
        self::assertSame(3, $zarf['meta']['sayimlar']['tumu'], 'Sayımlar filtreden BAĞIMSIZ kümeyi anlatır.');
        self::assertSame(1, $zarf['meta']['sayimlar']['fiyat_bekleniyor']);
        self::assertSame(1, $zarf['meta']['sayimlar']['onayli']);

        $hatali = $this->call('GET', '/api/lists?sekme=yok-boyle');
        self::assertSame(422, $hatali->getStatusCode());
    }

    public function testACIKTURSAGLIKVEKPIYIBESLERYANITTAMAMLANMADANDEGERLENDIRMEYEGECMEZ(): void
    {
        $listeId = $this->liste('Turlu liste', 2);
        $firma = (int) $this->json($this->write('POST', '/api/firmalar', ['ad' => 'Yiwu']))['data']['id'];
        $tur = $this->json($this->write('POST', '/api/lists/' . $listeId . '/turlar', ['firma_id' => $firma]))['data'];
        $gonder = $this->json($this->write('POST', '/api/turlar/' . $tur['id'] . '/gonder', ['gecerlilik_gun' => 1]))['data'];
        self::assertSame('SENT', $gonder['state']);

        $liste = $this->listeyiBul($listeId);
        self::assertSame('fiyat_bekleniyor', $liste['sekme']);
        self::assertContains('fiyat_bekleyen', $liste['saglik']);
        self::assertContains('teklif_suresi', $liste['saglik'], '1 gün geçerlilik = 48 saat içinde doluyor.');
        self::assertSame(['fiyatlanan' => 0, 'toplam' => 2, 'yuzde' => 0, 'kaynak' => 'tur'], $liste['fiyatlama']);
        self::assertSame('SENT', $liste['tur_ozeti']['state']);

        // Bir satıra fiyat uygulanınca çubuk 1/2 olur; tur PRICING'dir, sekme hâlâ fiyat_bekleniyor.
        $rfq = $this->json($this->call('GET', '/api/turlar/' . $tur['id']))['data']['rfq_satirlari'];
        $on = $this->json($this->write('POST', '/api/turlar/' . $tur['id'] . '/yapistir-ayristir', ['metin' => $rfq[0]['urun_kodu'] . ' 有货，DDP含土耳其税 USD 4.20，MOQ 5，订单确认后20天。']))['data'];
        $this->write('POST', '/api/turlar/' . $tur['id'] . '/yanit-uygula', ['kaynak' => 'yapistir', 'parmak_izi' => $on['parmak_izi'], 'satirlar' => [$on['satirlar'][0]['yeni']]]);

        $liste = $this->listeyiBul($listeId);
        self::assertSame(['fiyatlanan' => 1, 'toplam' => 2, 'yuzde' => 50, 'kaynak' => 'tur'], $liste['fiyatlama']);
        self::assertSame('fiyat_bekleniyor', $liste['sekme']);
        self::assertSame('PRICING', $liste['tur_ozeti']['state']);

        $zarf = $this->json($this->call('GET', '/api/lists'));
        self::assertSame(1, $zarf['meta']['kpi']['fiyat_bekleyen_liste']);
        self::assertSame(1, $zarf['meta']['kpi']['fiyatlanmayan_satir']);
        self::assertSame(1, $zarf['meta']['kpi']['suresi_dolan_teklif']);
    }

    public function testTURSUZLISTEDEFIYATLAMAURUNDDPALANINDANSAYILIR(): void
    {
        $id = $this->liste('Eski usul', 3, '2.50');
        $this->write('POST', '/api/lists/' . $id . '/products', ['name' => 'Fiyatsız', 'qty' => 1, 'price_yuan' => '1.00']);

        $liste = $this->listeyiBul($id);

        self::assertSame(['fiyatlanan' => 3, 'toplam' => 4, 'yuzde' => 75, 'kaynak' => 'urun'], $liste['fiyatlama']);
        self::assertNull($liste['tur_ozeti']);
        self::assertSame([], $liste['saglik']);
    }

    // ── şablonlar ──────────────────────────────────────────────────────

    public function testLISTEDENSABLONSABLONDANTASLAKLISTE(): void
    {
        $kaynak = $this->liste('Sezonluk', 3);
        $olustur = $this->write('POST', '/api/lists/' . $kaynak . '/sablon', ['ad' => 'Sonbahar seti', 'aciklama' => 'Her sezon']);
        self::assertSame(201, $olustur->getStatusCode(), (string) $olustur->getBody());
        $sablon = $this->json($olustur)['data'];
        self::assertSame(3, $sablon['urun_sayisi']);
        self::assertSame(0, $sablon['kullanim_sayisi']);
        self::assertSame(['Ürün 1', 'Ürün 2', 'Ürün 3'], $sablon['ornek_urunler']);

        $yeni = $this->write('POST', '/api/sablonlar/' . $sablon['id'] . '/liste', ['name' => 'Sonbahar 2026', 'period' => 'Sonbahar']);
        self::assertSame(201, $yeni->getStatusCode(), (string) $yeni->getBody());
        $liste = $this->json($yeni)['data'];
        self::assertSame('draft', $liste['status']);
        self::assertSame(3, $liste['product_count']);
        self::assertSame('hazirlaniyor', $liste['sekme']);
        self::assertSame(3, $liste['progress']['to_order'], 'Şablondan doğan ürünler başa döner.');

        $urunler = $this->json($this->call('GET', '/api/lists/' . $liste['id'] . '/products'))['data'];
        self::assertSame(['Ürün 1', 'Ürün 2', 'Ürün 3'], array_column($urunler, 'name'));
        self::assertNull($urunler[0]['tracking_no'] ?? null, 'Takip kodu taşınmaz.');

        $hepsi = $this->json($this->call('GET', '/api/sablonlar'))['data'];
        self::assertSame(1, $hepsi[0]['kullanim_sayisi'], 'Kullanım sayacı arttı.');
        self::assertNotNull($hepsi[0]['son_kullanim_at']);

        // Şablon listeye bağlı değildir: kaynak liste silinse de kalır.
        $this->write('DELETE', '/api/lists/' . $kaynak);
        self::assertCount(1, $this->json($this->call('GET', '/api/sablonlar'))['data']);

        $sil = $this->write('DELETE', '/api/sablonlar/' . $sablon['id']);
        self::assertSame(204, $sil->getStatusCode());
        self::assertSame([], $this->json($this->call('GET', '/api/sablonlar'))['data']);
    }

    public function testBOSLISTEDENSABLONALINMAZADZORUNLU(): void
    {
        $bos = (int) $this->json($this->write('POST', '/api/lists', ['name' => 'Boş']))['data']['id'];

        $yanit = $this->write('POST', '/api/lists/' . $bos . '/sablon', ['ad' => 'X']);
        self::assertSame(422, $yanit->getStatusCode());
        self::assertSame('LISTE_BOS', $this->json($yanit)['error']['code']);

        $dolu = $this->liste('Dolu', 1);
        $adsiz = $this->write('POST', '/api/lists/' . $dolu . '/sablon', ['ad' => '   ']);
        self::assertSame(422, $adsiz->getStatusCode());
        self::assertSame('VALIDATION', $this->json($adsiz)['error']['code']);
    }

    // ── kaydedilmiş görünümler ─────────────────────────────────────────

    public function testGORUNUMEKRANBASINAKAYDEDILIRVARSAYILANTEKTIR(): void
    {
        $bir = $this->json($this->write('POST', '/api/gorunumler/listeler', ['ad' => 'Bekleyenler', 'sorgu' => ['sekme' => 'fiyat_bekleniyor', 'grup' => 'donem'], 'varsayilan' => true]))['data']['gorunumler'];
        self::assertCount(1, $bir);
        self::assertTrue($bir[0]['varsayilan']);

        $iki = $this->json($this->write('POST', '/api/gorunumler/listeler', ['ad' => 'Onaylılar', 'sorgu' => ['sekme' => 'onayli'], 'varsayilan' => true]))['data']['gorunumler'];
        self::assertCount(2, $iki);
        self::assertFalse($iki[0]['varsayilan'], 'Varsayılan TEK olabilir.');
        self::assertTrue($iki[1]['varsayilan']);

        // Aynı ad (büyük/küçük harf farkıyla) üzerine yazar.
        $uc = $this->json($this->write('POST', '/api/gorunumler/listeler', ['ad' => 'bekleyenler', 'sorgu' => ['sekme' => 'degerlendirmede']]))['data']['gorunumler'];
        self::assertCount(2, $uc);
        self::assertSame('degerlendirmede', $uc[0]['sorgu']['sekme']);

        $kalan = $this->json($this->write('DELETE', '/api/gorunumler/listeler/' . rawurlencode('Onaylılar')))['data']['gorunumler'];
        self::assertCount(1, $kalan);
        self::assertSame($kalan, $this->json($this->call('GET', '/api/gorunumler/listeler'))['data']['gorunumler']);

        self::assertSame(404, $this->call('GET', '/api/gorunumler/bilinmeyen')->getStatusCode(), 'Keyfi ekran anahtarı yok.');
    }
}
