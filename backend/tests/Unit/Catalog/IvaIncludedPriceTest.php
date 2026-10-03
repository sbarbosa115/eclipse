<?php

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Pricing\PriceNetOfTax;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\DocumentTotals;
use App\Shared\Domain\Totals\LineInput;
use App\Shared\Domain\Totals\TaxRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * §4.3: with "Incluir IVA en el precio" the unit value on a line is the price net of the tax, and a line of one unit
 * totals the list price once the document rounds.
 */
final class IvaIncludedPriceTest extends TestCase
{
    public function testAPriceWithoutIncludedTaxIsTheUnitValueAsIs(): void
    {
        self::assertSame('100000.0000', PriceNetOfTax::of(UnitPrice::of('100000'), false, TaxRate::percentage('19'))->toString(), 'A price that does not include the tax is the line value.');
    }

    public function testAnExactPriceDividesCleanly(): void
    {
        self::assertSame('100000.0000', PriceNetOfTax::of(UnitPrice::of('119000'), true, TaxRate::percentage('19'))->toString(), '119 000 with IVA 19 % is 100 000 net.');
    }

    public function testAPriceThatDoesNotDivideKeepsFourDecimals(): void
    {
        self::assertSame('42016.8067', PriceNetOfTax::of(UnitPrice::of('50000'), true, TaxRate::percentage('19'))->toString(), '50 000 / 1.19 rounded to four decimals.');
    }

    public function testTheRoundedNetStillTotalsTheListPriceOnADocument(): void
    {
        $net = PriceNetOfTax::of(UnitPrice::of('50000'), true, TaxRate::percentage('19'));
        $totals = DocumentTotals::of([new LineInput(Quantity::of('1'), $net, Rate::zero(), TaxRate::percentage('19'), TaxRate::none())]);

        self::assertSame('50000.00', $totals->net->toString(), 'The customer pays the list price, no more, no less.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function listPrices(): iterable
    {
        foreach (['1', '0.01', '0.99', '1.05', '7', '999', '1234.56', '49999.99', '50000', '84033.61', '119000', '3333333.33', '99999999.99'] as $price) {
            foreach (['19', '5', '8', '0'] as $rate) {
                yield "$price at $rate %" => [$price, $rate];
            }
        }
    }

    #[DataProvider('listPrices')]
    public function testAOneUnitLineAlwaysTotalsTheListPrice(string $price, string $rate): void
    {
        $net = PriceNetOfTax::of(UnitPrice::of($price), true, TaxRate::percentage($rate));
        $totals = DocumentTotals::of([new LineInput(Quantity::of('1'), $net, Rate::zero(), TaxRate::percentage($rate), TaxRate::none())]);

        self::assertSame(number_format((float) $price, 2, '.', ''), $totals->net->toString(), "$price with $rate % included.");
    }

    public function testAPerUnitTaxIsSubtractedAsAValue(): void
    {
        self::assertSame('9500.0000', PriceNetOfTax::of(UnitPrice::of('10000'), true, TaxRate::perUnit('500'))->toString(), 'Impoconsumo por valor: 10 000 includes 500 of tax.');
    }

    public function testAZeroTaxLeavesThePriceAlone(): void
    {
        self::assertSame('10000.0000', PriceNetOfTax::of(UnitPrice::of('10000'), true, TaxRate::none())->toString());
    }

    public function testWithoutAChargeTaxThereIsNothingToRemove(): void
    {
        self::assertSame('10000.0000', PriceNetOfTax::of(UnitPrice::of('10000'), true, null)->toString(), 'No tax chosen: the price is the value.');
    }

    public function testAPerUnitTaxLargerThanThePriceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PriceNetOfTax::of(UnitPrice::of('400'), true, TaxRate::perUnit('500'));
    }
}
