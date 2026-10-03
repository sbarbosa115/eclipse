<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Fiscal\CheckDigit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckDigitTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function knownNits(): iterable
    {
        yield 'DIAN' => ['800197268', '4'];
        yield 'Bancolombia' => ['890903938', '8'];
        yield 'Ecopetrol' => ['899999068', '1'];
        yield 'a remainder of 0 gives 0' => ['900000009', '0'];
        yield 'a remainder of 1 gives 1' => ['900000002', '1'];
        yield 'separators are ignored' => ['800.197.268', '4'];
    }

    #[DataProvider('knownNits')]
    public function testTheDigitFollowsTheDianAlgorithm(string $nit, string $dv): void
    {
        self::assertSame($dv, CheckDigit::of($nit), "The DV of NIT $nit is $dv.");
    }

    public function testANitWithoutDigitsIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CheckDigit::of('abc');
    }
}
