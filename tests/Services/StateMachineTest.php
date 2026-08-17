<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\StateMachine;
use App\Services\StateTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * docs/04 §2b — kural sunucuda yaşar. Kabul kriteri geçiş matrisinin TAM taranmasını ister:
 * her (from, to) çifti ya izinlidir ya 422 verir; "unutulmuş" hücre kalmaz.
 */
final class StateMachineTest extends TestCase
{
    private StateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new StateMachine();
    }

    // ─────────────── Ürün: tam matris ───────────────

    /** @return list<array{string, string, bool}> */
    public static function urunMatrisi(): array
    {
        $states = ['to_order', 'ordered', 'in_transit', 'received', 'cancelled'];
        $allowed = [
            'to_order' => ['ordered', 'cancelled'],
            'ordered' => ['in_transit', 'to_order', 'cancelled'],
            'in_transit' => ['received', 'ordered', 'cancelled'],
            'received' => ['in_transit'],
            'cancelled' => [],
        ];

        $cases = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                $cases[] = [$from, $to, in_array($to, $allowed[$from], true)];
            }
        }

        return $cases;
    }

    #[DataProvider('urunMatrisi')]
    public function testUrunGecisMatrisiTamdir(string $from, string $to, bool $izinli): void
    {
        self::assertSame($izinli, $this->machine->canTransitionProduct($from, $to), $from . ' → ' . $to);

        if ($izinli) {
            $this->machine->assertProductTransition($from, $to);
            self::assertTrue(true);

            return;
        }

        try {
            $this->machine->assertProductTransition($from, $to);
            self::fail(sprintf('%s → %s reddedilmeliydi.', $from, $to));
        } catch (StateTransitionException $e) {
            self::assertSame($from, $e->from);
            self::assertSame($to, $e->to);
            self::assertSame($this->machine->allowedProductTransitions($from), $e->allowed);
        }
    }

    public function testUrunDurumAtlamaYasak(): void
    {
        self::assertFalse($this->machine->canTransitionProduct('to_order', 'in_transit'));
        self::assertFalse($this->machine->canTransitionProduct('to_order', 'received'));
        self::assertFalse($this->machine->canTransitionProduct('ordered', 'received'));
    }

    public function testUrunYalnizBirAdimGeriAlinir(): void
    {
        self::assertTrue($this->machine->canTransitionProduct('in_transit', 'ordered'));
        self::assertFalse($this->machine->canTransitionProduct('in_transit', 'to_order'), 'İki adım geri YASAK.');
        self::assertFalse($this->machine->canTransitionProduct('received', 'ordered'));
    }

    public function testGeldiDurumundanIptalEdilemez(): void
    {
        // CLAUDE.md §4: her durumdan İptal'e geçilebilir — Geldi HARİÇ.
        self::assertFalse($this->machine->canTransitionProduct('received', 'cancelled'));
    }

    public function testIptalTerminaldir(): void
    {
        self::assertSame([], $this->machine->allowedProductTransitions('cancelled'));
    }

    public function testAyniDurumaGecisGecersizdir(): void
    {
        foreach (['to_order', 'ordered', 'in_transit', 'received', 'cancelled'] as $state) {
            self::assertFalse($this->machine->canTransitionProduct($state, $state), $state . ' → ' . $state);
        }
    }

    public function testBilinmeyenDurumReddedilir(): void
    {
        self::assertFalse($this->machine->isValidProductStatus('Verilecek'), 'Türkçe değer makine kodu DEĞİLDİR (K22).');
        self::assertFalse($this->machine->isValidProductStatus('teslim_edildi'));

        $this->expectException(StateTransitionException::class);
        $this->machine->assertProductTransition('to_order', 'uydurma');
    }

    // ─────────────── Liste: tam matris ───────────────

    /** @return list<array{string, string, bool}> */
    public static function listeMatrisi(): array
    {
        $states = ['draft', 'sent', 'ordered', 'completed', 'cancelled'];
        $allowed = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['ordered', 'draft', 'cancelled'],
            'ordered' => ['completed', 'sent', 'cancelled'],
            // K37 §B4: completed TERMİNALDİR — reopen yok, çözüm kopyalama.
            'completed' => [],
            'cancelled' => [],
        ];

        $cases = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                $cases[] = [$from, $to, in_array($to, $allowed[$from], true)];
            }
        }

        return $cases;
    }

    #[DataProvider('listeMatrisi')]
    public function testListeGecisMatrisiTamdir(string $from, string $to, bool $izinli): void
    {
        self::assertSame($izinli, $this->machine->canTransitionList($from, $to), $from . ' → ' . $to);
    }

    public function testListeTamamlanmasiTumUrunlerinKapanmasiniIster(): void
    {
        // Açık ürün varken completed REDDEDİLİR.
        try {
            $this->machine->assertListTransition('ordered', 'completed', ['received', 'in_transit', 'cancelled']);
            self::fail('Açık ürün varken liste tamamlanmamalıydı.');
        } catch (StateTransitionException $e) {
            self::assertStringContainsString('1 ürün', $e->getMessage());
        }

        // Hepsi received/cancelled ise geçer.
        $this->machine->assertListTransition('ordered', 'completed', ['received', 'cancelled', 'received']);
        self::assertTrue(true);
    }

    public function testUrunsuzListeTamamlanabilir(): void
    {
        $this->machine->assertListTransition('ordered', 'completed', []);

        self::assertTrue($this->machine->allProductsClosed([]));
    }

    public function testTamamlanmisListeTerminaldir(): void
    {
        // K37 §B4: completed'dan HİÇBİR duruma dönüş yok — yanlış kapatılan
        // listenin çözümü kopyalamaktır (reopen ucu yok).
        self::assertSame([], $this->machine->allowedListTransitions('completed'));
        self::assertFalse($this->machine->canTransitionList('completed', 'ordered'));
        self::assertFalse($this->machine->canTransitionList('completed', 'sent'));
        self::assertFalse($this->machine->canTransitionList('completed', 'cancelled'));
    }

    public function testGecersizGecisIzinliListeyiTasir(): void
    {
        try {
            $this->machine->assertListTransition('draft', 'completed', []);
            self::fail('draft → completed reddedilmeliydi.');
        } catch (StateTransitionException $e) {
            self::assertSame(['sent', 'cancelled'], $e->allowed);
        }
    }
}
