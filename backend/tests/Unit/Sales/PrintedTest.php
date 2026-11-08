<?php

namespace App\Tests\Unit\Sales;

use App\Sales\Application\Document\Printed;
use PHPUnit\Framework\TestCase;

final class PrintedTest extends TestCase
{
    public function testAMomentIsPrintedOnTheDayItWasInBogota(): void
    {
        // Voided at 23:30 in Bogotá on 07/11 is 04:30 UTC on 08/11: the PDF must say 07/11, as the screen does.
        self::assertSame('07/11/2026', Printed::moment(new \DateTimeImmutable('2026-11-08 04:30:00', new \DateTimeZone('UTC'))));
        self::assertSame('08/11/2026', Printed::moment(new \DateTimeImmutable('2026-11-08 05:00:00', new \DateTimeZone('UTC'))));
        self::assertSame('', Printed::moment(null));
    }
}
