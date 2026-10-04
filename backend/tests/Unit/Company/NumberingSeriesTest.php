<?php

namespace App\Tests\Unit\Company;

use App\Company\Domain\Error\InvalidNumberingSeries;
use App\Company\Domain\Model\NumberingSeries;
use App\Company\Domain\Model\SeriesKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class NumberingSeriesTest extends TestCase
{
    private static function series(int $next = 1): NumberingSeries
    {
        return new NumberingSeries(Uuid::v7(), SeriesKind::CashReceipt, 'RC', $next);
    }

    public function testTheNextNumberCanMoveForward(): void
    {
        $s = self::series(5);

        $s->revise('RCB', 40);

        self::assertSame(['RCB', 40], [$s->prefix(), $s->nextNumber()]);
        self::assertSame(40, $s->take(), 'The next document gets the number that was set.');
    }

    public function testTheNextNumberCanStayWhereItIsWhileThePrefixChanges(): void
    {
        $s = self::series(5);

        $s->revise('RCB', 5);

        self::assertSame('RCB', $s->prefix());
    }

    public function testTheNextNumberNeverGoesBelowTheCurrentOne(): void
    {
        $s = self::series(5);

        try {
            $s->revise('RC', 4);
            self::fail('A number already handed out would be handed out again.');
        } catch (InvalidNumberingSeries $e) {
            self::assertSame('next_number', $e->field());
        }
        self::assertSame(5, $s->nextNumber());
    }

    public function testAPrefixIsUpToTenLettersAndDigits(): void
    {
        foreach (['R C', 'RC-', 'ABCDEFGHIJK'] as $bad) {
            try {
                self::series()->revise($bad, 1);
                self::fail("$bad is not a prefix.");
            } catch (InvalidNumberingSeries $e) {
                self::assertSame('prefix', $e->field());
            }
        }
        $s = self::series();
        $s->revise('', 1);
        self::assertSame('', $s->prefix(), 'No prefix is allowed: the number prints alone.');
    }

    public function testThePrefixIsKeptInCapitals(): void
    {
        $s = self::series();
        $s->revise('rc2', 1);

        self::assertSame('RC2', $s->prefix());
    }

    public function testTheJournalSeriesIsNotEditable(): void
    {
        self::assertFalse(SeriesKind::JournalEntry->isEditable(), 'The journal numbers its own entries (§4.1 lists only documents).');
        self::assertTrue(SeriesKind::SalesInvoiceInternal->isEditable());
    }
}
