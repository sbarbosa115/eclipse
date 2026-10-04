<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAmountsKeepTwoDecimalsAsAString(): void
    {
        self::assertSame('1190000.00', Money::of('1190000')->toString(), 'Money travels as a decimal string with two decimals.');
        self::assertSame('0.10', Money::of('0.1')->plus(Money::of('0.2'))->minus(Money::of('0.2'))->toString(), 'No float drift.');
    }

    public function testMoreThanTwoDecimalsIsRefusedInsteadOfSilentlyRounded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::of('10.005');
    }

    public function testRoundingFromAnExactValueIsHalfUp(): void
    {
        self::assertSame('10.01', Money::rounded(\Brick\Math\BigDecimal::of('10.005'))->toString(), 'Half up, as the DIAN annex rounds.');
        self::assertSame('-10.01', Money::rounded(\Brick\Math\BigDecimal::of('-10.005'))->toString(), 'Half away from zero for negatives.');
    }

    public function testComparisons(): void
    {
        self::assertTrue(Money::of('5')->equals(Money::of('5.00')));
        self::assertTrue(Money::of('5')->isGreaterThan(Money::of('4.99')));
        self::assertTrue(Money::zero()->isZero());
        self::assertTrue(Money::of('-1')->isNegative());
        self::assertSame('15.50', Money::sum(Money::of('10'), Money::of('5.5'))->toString());
    }

    public function testRatesQuantitiesAndUnitPricesKeepFourDecimals(): void
    {
        self::assertSame('19.0000', Rate::of('19')->toString());
        self::assertTrue(Rate::of('19')->fraction()->isEqualTo('0.19'), '19 % is the fraction 0.19.');
        self::assertSame('2.5000', Quantity::of('2.5')->toString());
        self::assertSame('1000.1234', UnitPrice::of('1000.1234')->toString());
    }

    public function testRatesOutsideZeroToHundredAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Rate::of('100.01');
    }

    public function testQuantityMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::of('0');
    }
}
