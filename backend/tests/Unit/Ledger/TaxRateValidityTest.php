<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Domain\Error\InvalidTaxDefinition;
use App\Ledger\Domain\Error\TaxNotEditable;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Model\TaxClass;
use App\Ledger\Domain\Model\TaxKind;
use App\Shared\Domain\Totals\TaxCalculation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TaxRateValidityTest extends TestCase
{
    private static function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    private static function iva(?string $from = null, ?string $to = null): Tax
    {
        return Tax::define(Uuid::v7(), 'IVA 19 %', TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '19', null, null, null === $from ? null : self::day($from), null === $to ? null : self::day($to));
    }

    public function testATaxWithoutDatesIsAlwaysInForce(): void
    {
        $tax = self::iva();

        self::assertTrue($tax->isValidOn(self::day('2020-01-01')), 'No start date: in force since ever.');
        self::assertTrue($tax->isValidOn(self::day('2090-12-31')), 'No end date: in force until changed.');
    }

    public function testTheRateIsInForceOnBothBoundaryDays(): void
    {
        $tax = self::iva('2026-01-01', '2026-12-31');

        self::assertFalse($tax->isValidOn(self::day('2025-12-31')), 'The day before it starts.');
        self::assertTrue($tax->isValidOn(self::day('2026-01-01')), 'The first day counts.');
        self::assertTrue($tax->isValidOn(self::day('2026-12-31 15:30')), 'The last day counts, at any hour.');
        self::assertFalse($tax->isValidOn(self::day('2027-01-01')), 'The day after it ends.');
    }

    public function testAnOpenEndedRateStartsOnItsDate(): void
    {
        $tax = self::iva('2027-01-01');

        self::assertFalse($tax->isValidOn(self::day('2026-12-31')));
        self::assertTrue($tax->isValidOn(self::day('2030-06-01')));
    }

    public function testItCannotEndBeforeItStarts(): void
    {
        $this->expectException(InvalidTaxDefinition::class);
        $this->expectExceptionMessage('The end date cannot be before the start date.');

        self::iva('2026-06-01', '2026-05-31');
    }

    public function testAOneDayRateIsAllowed(): void
    {
        self::assertTrue(self::iva('2026-06-01', '2026-06-01')->isValidOn(self::day('2026-06-01')));
    }

    public function testRevisingARateKeepsTheTaxButMovesItsDates(): void
    {
        $tax = self::iva('2026-01-01');
        $tax->revise('IVA 19 %', TaxCalculation::Percentage, '16', null, null, self::day('2026-01-01'), self::day('2026-12-31'));

        self::assertSame('16.0000', $tax->rate(), 'Rates are kept to four decimals.');
        self::assertFalse($tax->isValidOn(self::day('2027-01-01')));
    }

    /**
     * @return iterable<string, array{TaxClass, TaxKind, TaxCalculation, string}>
     */
    public static function refusedDefinitions(): iterable
    {
        yield 'a percentage above 100' => [TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '100.0001'];
        yield 'a negative rate' => [TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '-1'];
        yield 'more than four decimals' => [TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, '19.00001'];
        yield 'not a number' => [TaxClass::Charge, TaxKind::Vat, TaxCalculation::Percentage, 'diecinueve'];
        yield 'a retención of the wrong class' => [TaxClass::Charge, TaxKind::IncomeWithholding, TaxCalculation::Percentage, '4'];
        yield 'an IVA as a retención' => [TaxClass::Withholding, TaxKind::Vat, TaxCalculation::Percentage, '19'];
        yield 'a value per unit on a retención' => [TaxClass::Withholding, TaxKind::IncomeWithholding, TaxCalculation::PerUnit, '500'];
        yield 'a value per unit on IVA' => [TaxClass::Charge, TaxKind::Vat, TaxCalculation::PerUnit, '500'];
        yield 'a "none" tax with a rate' => [TaxClass::Charge, TaxKind::None, TaxCalculation::Percentage, '1'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusedDefinitions')]
    public function testIncoherentDefinitionsAreRefused(TaxClass $class, TaxKind $kind, TaxCalculation $calculation, string $rate): void
    {
        $this->expectException(InvalidTaxDefinition::class);

        Tax::define(Uuid::v7(), 'X', $class, $kind, $calculation, $rate, null, null);
    }

    public function testImpoconsumoMayBeAValuePerUnit(): void
    {
        $tax = Tax::define(Uuid::v7(), 'Impoconsumo por valor', TaxClass::Charge, TaxKind::Consumption, TaxCalculation::PerUnit, '500', null, null);

        self::assertSame('500.0000', $tax->rate());
    }

    public function testTheNoneTaxCannotBeChangedOrDeactivated(): void
    {
        $none = Tax::define(Uuid::v7(), 'Ninguno', TaxClass::Charge, TaxKind::None, TaxCalculation::Percentage, '0', null, null, standard: true);

        try {
            $none->deactivate();
            self::fail('"Ninguno" is what a line without a tax points at: it must stay.');
        } catch (TaxNotEditable) {
            self::assertTrue($none->isActive());
        }
        $this->expectException(TaxNotEditable::class);
        $none->revise('Otro', TaxCalculation::Percentage, '0', null, null, null, null);
    }

    public function testDeactivatingAndActivatingAreSymmetric(): void
    {
        $tax = self::iva();
        $tax->deactivate();
        self::assertFalse($tax->isActive());
        $tax->activate();
        self::assertTrue($tax->isActive());
    }
}
