<?php

namespace App\Tests\Unit\Ledger;

use App\Ledger\Domain\Error\LockDateInFuture;
use App\Ledger\Domain\Model\LedgerSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class LedgerSettingsTest extends TestCase
{
    private static function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    public function testNewBooksAreOpenOnAnyDate(): void
    {
        self::assertTrue((new LedgerSettings(Uuid::v7()))->isOpen(self::day('2001-01-01')));
    }

    public function testNothingIsPostedOnOrBeforeTheLockDate(): void
    {
        $settings = new LedgerSettings(Uuid::v7());

        $settings->lockUntil(self::day('2026-09-30'), self::day('2026-10-03'));

        self::assertFalse($settings->isOpen(self::day('2026-09-30')), '§5 invariant 4: "on or before" the fecha de bloqueo.');
        self::assertFalse($settings->isOpen(self::day('2026-01-15')));
        self::assertTrue($settings->isOpen(self::day('2026-10-01 00:00:01')));
        self::assertEquals(self::day('2026-09-30'), $settings->lockedUntil());
    }

    public function testTheLockDateMayBeTodayButNotLater(): void
    {
        $settings = new LedgerSettings(Uuid::v7());
        $settings->lockUntil(self::day('2026-10-03'), self::day('2026-10-03 17:00'));

        $this->expectException(LockDateInFuture::class);
        $settings->lockUntil(self::day('2026-10-04'), self::day('2026-10-03 17:00'));
    }

    public function testTheAccountantMayReopenAPeriodByMovingTheDateBack(): void
    {
        $settings = new LedgerSettings(Uuid::v7());
        $settings->lockUntil(self::day('2026-09-30'), self::day('2026-10-03'));

        $settings->lockUntil(self::day('2026-08-31'), self::day('2026-10-03'));

        self::assertTrue($settings->isOpen(self::day('2026-09-15')));
    }

    public function testTheLockDateIsADayWithoutTime(): void
    {
        $settings = new LedgerSettings(Uuid::v7());

        $settings->lockUntil(self::day('2026-09-30 15:45'), self::day('2026-10-03'));

        self::assertSame('2026-09-30 00:00:00', $settings->lockedUntil()?->format('Y-m-d H:i:s'));
    }
}
