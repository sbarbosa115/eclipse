<?php

namespace App\Tests\Unit\Reporting;

use App\Reporting\Application\Cartera\AgeingBucket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AgeingBucketTest extends TestCase
{
    /** @return iterable<string, array{string, AgeingBucket}> */
    public static function dueDates(): iterable
    {
        yield 'due in the future' => ['2026-11-30', AgeingBucket::Current];
        yield 'due today is still al día' => ['2026-10-03', AgeingBucket::Current];
        yield 'one day late' => ['2026-10-02', AgeingBucket::Days1To30];
        yield '30 days late' => ['2026-09-03', AgeingBucket::Days1To30];
        yield '31 days late' => ['2026-09-02', AgeingBucket::Days31To60];
        yield '60 days late' => ['2026-08-04', AgeingBucket::Days31To60];
        yield '61 days late' => ['2026-08-03', AgeingBucket::Days61To90];
        yield '90 days late' => ['2026-07-05', AgeingBucket::Days61To90];
        yield '91 days late' => ['2026-07-04', AgeingBucket::Over90];
        yield 'years late' => ['2020-01-01', AgeingBucket::Over90];
    }

    #[DataProvider('dueDates')]
    public function testABalanceFallsInTheBucketOfItsDaysOverdue(string $due, AgeingBucket $bucket): void
    {
        self::assertSame($bucket, AgeingBucket::of(new \DateTimeImmutable($due), new \DateTimeImmutable('2026-10-03')));
    }

    public function testTheTimeOfDayDoesNotMoveABalance(): void
    {
        $late = AgeingBucket::of(new \DateTimeImmutable('2026-10-02 23:59:59'), new \DateTimeImmutable('2026-10-03 00:00:00'));

        self::assertSame(AgeingBucket::Days1To30, $late);
        self::assertSame(1, AgeingBucket::daysOverdue(new \DateTimeImmutable('2026-10-02 23:59:59'), new \DateTimeImmutable('2026-10-03 00:00:00')));
    }

    public function testOnlyAlDiaIsNotOverdue(): void
    {
        self::assertFalse(AgeingBucket::Current->isOverdue());
        foreach ([AgeingBucket::Days1To30, AgeingBucket::Days31To60, AgeingBucket::Days61To90, AgeingBucket::Over90] as $bucket) {
            self::assertTrue($bucket->isOverdue());
        }
    }

    public function testDaysOverdueIsNegativeUntilTheDueDate(): void
    {
        self::assertSame(-27, AgeingBucket::daysOverdue(new \DateTimeImmutable('2026-10-30'), new \DateTimeImmutable('2026-10-03')));
    }
}
