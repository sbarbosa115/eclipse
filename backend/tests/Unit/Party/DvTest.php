<?php

namespace App\Tests\Unit\Party;

use App\Party\Domain\Error\InvalidTerceroValue;
use App\Party\Domain\Model\Identification;
use App\Shared\Domain\Fiscal\IdentificationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DvTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function nits(): iterable
    {
        yield 'DIAN' => ['800197268', '4'];
        yield 'Bancolombia' => ['890903938', '8'];
        yield 'Ecopetrol' => ['899999068', '1'];
    }

    #[DataProvider('nits')]
    public function testANitGetsItsComputedDv(string $nit, string $dv): void
    {
        self::assertSame($dv, Identification::checkDigit(IdentificationType::Nit, $nit, null), "The DIAN algorithm gives $dv for $nit.");
        self::assertSame($dv, Identification::checkDigit(IdentificationType::Nit, $nit, ''), 'An empty DV means compute it.');
    }

    public function testTheDvIsEditableForANit(): void
    {
        self::assertSame('9', Identification::checkDigit(IdentificationType::Nit, '800197268', '9'), 'A DV the person typed wins over the computed one.');
    }

    public function testOnlyANitCarriesADv(): void
    {
        self::assertNull(Identification::checkDigit(IdentificationType::CitizenshipCard, '1020304050', '7'), 'A cédula has no DV, whatever was sent.');
        self::assertNull(Identification::checkDigit(IdentificationType::Passport, 'AB123456', null));
    }

    public function testADvThatIsNotOneDigitIsRefused(): void
    {
        $this->expectException(InvalidTerceroValue::class);

        Identification::checkDigit(IdentificationType::Nit, '800197268', '12');
    }

    public function testANitWithLettersCannotHaveItsDvComputed(): void
    {
        $this->expectException(InvalidTerceroValue::class);

        Identification::checkDigit(IdentificationType::Nit, '8001A7268', null);
    }

    public function testPunctuationIsNotPartOfTheNumber(): void
    {
        self::assertSame('800197268', Identification::normalize('800.197.268'), 'Dots are dropped.');
        self::assertSame('AB123', Identification::normalize('ab-123'), 'Letters are upper-cased.');
    }
}
