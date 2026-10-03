<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\DocumentTotals;
use App\Shared\Domain\Totals\LineInput;
use App\Shared\Domain\Totals\TaxRate;
use PHPUnit\Framework\TestCase;

/**
 * §4.6 of the accounting PRD: totals with decimal arithmetic, rounded once at document level.
 */
final class DocumentTotalsTest extends TestCase
{
    public function testTotalsFollowThePrdFormula(): void
    {
        // 2 × 1 000 000 with 10 % off, IVA 19 %, ReteFuente servicios 4 %.
        $totals = DocumentTotals::of([
            new LineInput(Quantity::of('2'), UnitPrice::of('1000000'), Rate::of('10'), TaxRate::percentage('19'), TaxRate::percentage('4')),
        ]);

        self::assertSame('2000000.00', $totals->gross->toString(), 'Total bruto = Σ cantidad × valor unitario.');
        self::assertSame('200000.00', $totals->discounts->toString(), 'Descuentos = Σ bruto × % descuento.');
        self::assertSame('1800000.00', $totals->subtotal->toString(), 'Subtotal = bruto − descuentos.');
        self::assertSame('342000.00', $totals->taxes->toString(), 'IVA on the discounted base.');
        self::assertSame('72000.00', $totals->withholdings->toString(), 'Retención on the discounted base.');
        self::assertSame('2070000.00', $totals->net->toString(), 'Total neto = subtotal + impuestos − retenciones.');
    }

    public function testRoundingHappensOnceAtDocumentLevel(): void
    {
        // Three lines of 0.333 × 1 at IVA 19 %: per-line rounding would give 3 × 0.06 = 0.18; once gives 0.19.
        $line = new LineInput(Quantity::of('1'), UnitPrice::of('0.3333'), Rate::zero(), TaxRate::percentage('19'), TaxRate::none());
        $totals = DocumentTotals::of([$line, $line, $line]);

        self::assertSame('1.00', $totals->gross->toString(), '0.9999 rounds once to 1.00.');
        self::assertSame('0.19', $totals->taxes->toString(), '0.189981 rounds once to 0.19.');
    }

    public function testLineAmountsAlwaysAddUpToTheDocumentTotals(): void
    {
        $line = new LineInput(Quantity::of('1'), UnitPrice::of('0.3333'), Rate::zero(), TaxRate::percentage('19'), TaxRate::none());
        $totals = DocumentTotals::of([$line, $line, $line]);

        $taxes = array_map(static fn ($l) => $l->tax, $totals->lines);
        $subtotals = array_map(static fn ($l) => $l->subtotal, $totals->lines);
        self::assertSame('0.19', \App\Shared\Domain\Money\Money::sum(...$taxes)->toString(), 'Line taxes are what posting uses: they must add up to the document tax to the cent.');
        self::assertSame('1.00', \App\Shared\Domain\Money\Money::sum(...$subtotals)->toString(), 'Line subtotals add up to the document subtotal.');
    }

    public function testTaxPerUnitMultipliesTheQuantity(): void
    {
        // Impoconsumo por valor: 500 per unit.
        $totals = DocumentTotals::of([
            new LineInput(Quantity::of('3'), UnitPrice::of('10000'), Rate::zero(), TaxRate::perUnit('500'), TaxRate::none()),
        ]);

        self::assertSame('1500.00', $totals->taxes->toString());
        self::assertSame('31500.00', $totals->net->toString());
    }

    public function testAnEmptyDocumentIsAllZeros(): void
    {
        $totals = DocumentTotals::of([]);

        self::assertTrue($totals->net->isZero());
        self::assertSame([], $totals->lines);
    }
}
