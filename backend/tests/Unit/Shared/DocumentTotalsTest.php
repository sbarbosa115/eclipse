<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Money\Money;
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
            new LineInput(Quantity::of('2'), UnitPrice::of('1000000'), Rate::of('10'), TaxRate::percentage('19'), [TaxRate::percentage('4')]),
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
        $line = new LineInput(Quantity::of('1'), UnitPrice::of('0.3333'), Rate::zero(), TaxRate::percentage('19'), []);
        $totals = DocumentTotals::of([$line, $line, $line]);

        self::assertSame('1.00', $totals->gross->toString(), '0.9999 rounds once to 1.00.');
        self::assertSame('0.19', $totals->taxes->toString(), '0.189981 rounds once to 0.19.');
    }

    public function testLineAmountsAlwaysAddUpToTheDocumentTotals(): void
    {
        $line = new LineInput(Quantity::of('1'), UnitPrice::of('0.3333'), Rate::zero(), TaxRate::percentage('19'), []);
        $totals = DocumentTotals::of([$line, $line, $line]);

        $taxes = array_map(static fn ($l) => $l->tax, $totals->lines);
        $subtotals = array_map(static fn ($l) => $l->subtotal, $totals->lines);
        self::assertSame('0.19', Money::sum(...$taxes)->toString(), 'Line taxes are what posting uses: they must add up to the document tax to the cent.');
        self::assertSame('1.00', Money::sum(...$subtotals)->toString(), 'Line subtotals add up to the document subtotal.');
    }

    public function testTaxPerUnitMultipliesTheQuantity(): void
    {
        // Impoconsumo por valor: 500 per unit.
        $totals = DocumentTotals::of([
            new LineInput(Quantity::of('3'), UnitPrice::of('10000'), Rate::zero(), TaxRate::perUnit('500'), []),
        ]);

        self::assertSame('1500.00', $totals->taxes->toString());
        self::assertSame('31500.00', $totals->net->toString());
    }

    public function testReteIvaIsAPercentageOfTheLinesIvaNotOfItsBase(): void
    {
        // Base 1 000 000, IVA 19 % = 190 000; ReteIVA 15 % is withheld on the IVA: 28 500.
        $totals = DocumentTotals::of([
            new LineInput(Quantity::of('1'), UnitPrice::of('1000000'), Rate::zero(), TaxRate::percentage('19'), [TaxRate::percentageOfTax('15')]),
        ]);

        self::assertSame('190000.00', $totals->taxes->toString());
        self::assertSame('28500.00', $totals->withholdings->toString(), 'ReteIVA applies to the IVA (Art. 437-1 ET), never to the base.');
        self::assertSame('1161500.00', $totals->net->toString());
    }

    public function testALineCarriesSeveralRetencionesEachOnItsOwnBase(): void
    {
        // OBS-2: 1 000 000 with IVA 19 %, ReteFuente 2,5 %, ReteICA 0,966 % (on the base) and ReteIVA 15 % (on the IVA).
        $totals = DocumentTotals::of([
            new LineInput(Quantity::of('1'), UnitPrice::of('1000000'), Rate::zero(), TaxRate::percentage('19'), [
                TaxRate::percentage('2.5'), TaxRate::percentage('0.966'), TaxRate::percentageOfTax('15'),
            ]),
        ]);

        self::assertSame('63160.00', $totals->withholdings->toString(), 'Retenciones = 25 000 + 9 660 + 28 500.');
        self::assertSame('1126840.00', $totals->net->toString(), 'Total neto = 1 000 000 + 190 000 − 63 160.');
        $line = $totals->lines[0];
        self::assertSame(['25000.00', '9660.00', '28500.00'], array_map(static fn (Money $m) => $m->toString(), $line->withholdings), 'Each retención keeps its own amount, in the order the line lists them, so each posts to its own account.');
        self::assertSame('63160.00', $line->withholding->toString(), 'The line\'s retención is the sum of its retenciones.');
    }

    public function testRetencionSharesAddUpToTheDocumentAcrossLinesAndTaxes(): void
    {
        // Three lines of 333,33 with ReteFuente 2,5 % and ReteICA 0,966 %: every (line, retención) is a share of one
        // rounded total, so the per-account journal lines and the PDF summary both add up to Retenciones.
        $line = new LineInput(Quantity::of('1'), UnitPrice::of('333.33'), Rate::zero(), TaxRate::percentage('19'), [TaxRate::percentage('2.5'), TaxRate::percentage('0.966')]);
        $totals = DocumentTotals::of([$line, $line, $line]);

        // Exact: 3 × (8.33325 + 3.2199678) = 34.6596534 → 34.66.
        self::assertSame('34.66', $totals->withholdings->toString(), 'Rounded once at document level.');
        $shares = [];
        foreach ($totals->lines as $amounts) {
            self::assertSame(Money::sum(...$amounts->withholdings)->toString(), $amounts->withholding->toString(), 'A line\'s retención is the sum of its shares.');
            array_push($shares, ...$amounts->withholdings);
        }
        self::assertSame('34.66', Money::sum(...$shares)->toString(), 'Every (line, retención) share adds up to the document\'s Retenciones to the cent.');
    }

    public function testALineWithoutRetencionesHasNoShares(): void
    {
        $totals = DocumentTotals::of([new LineInput(Quantity::of('1'), UnitPrice::of('1000'), Rate::zero(), TaxRate::percentage('19'), [])]);

        self::assertSame([], $totals->lines[0]->withholdings);
        self::assertTrue($totals->lines[0]->withholding->isZero());
    }

    public function testAnEmptyDocumentIsAllZeros(): void
    {
        $totals = DocumentTotals::of([]);

        self::assertTrue($totals->net->isZero());
        self::assertSame([], $totals->lines);
    }
}
