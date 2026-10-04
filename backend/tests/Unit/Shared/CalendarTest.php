<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Calendar;
use App\Shared\Domain\Clock;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    private static function at(string $moment): Calendar
    {
        return new Calendar(new class($moment) implements Clock {
            public function __construct(private readonly string $moment)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->moment);
            }
        });
    }

    public function testTodayIsColombiasDayNotUtcs(): void
    {
        // 9 p.m. in Bogotá on the 3rd is 2 a.m. UTC on the 4th.
        $calendar = self::at('2026-10-04T02:00:00+00:00');

        self::assertSame('2026-10-03', $calendar->today()->format('Y-m-d'), 'A document dated at 9 p.m. in Bogotá is dated that day.');
        self::assertSame('00:00:00', $calendar->today()->format('H:i:s'), 'Today is a day: midnight.');
    }

    public function testNowIsTheClocksMoment(): void
    {
        self::assertSame('2026-10-04T02:00:00+00:00', self::at('2026-10-04T02:00:00+00:00')->now()->format(\DATE_ATOM));
    }

    public function testADayOfAMomentIsReadOnColombiasCalendar(): void
    {
        $calendar = self::at('2026-01-01T12:00:00+00:00');

        self::assertSame('2026-10-03', $calendar->dayOf(new \DateTimeImmutable('2026-10-04T04:59:59+00:00'))->format('Y-m-d'));
        self::assertSame('2026-10-04', $calendar->dayOf(new \DateTimeImmutable('2026-10-04T05:00:00+00:00'))->format('Y-m-d'));
    }
}
