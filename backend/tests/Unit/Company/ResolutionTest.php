<?php

namespace App\Tests\Unit\Company;

use App\Company\Domain\Error\InvalidResolution;
use App\Company\Domain\Error\ResolutionExhausted;
use App\Company\Domain\Error\ResolutionInactive;
use App\Company\Domain\Model\InvoicingMode;
use App\Company\Domain\Model\InvoicingResolution;
use App\Company\Domain\Model\ResolutionStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ResolutionTest extends TestCase
{
    private static function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    private static function resolution(int $from = 1, int $to = 1000, string $start = '2026-01-01', string $end = '2026-12-31'): InvoicingResolution
    {
        return InvoicingResolution::define(Uuid::v7(), '18760000001', 'SETP', $from, $to, self::day($start), self::day($end), InvoicingMode::Electronic, false);
    }

    /** @return list<string> the fields named by the violations */
    private static function violationFields(callable $attempt): array
    {
        try {
            $attempt();
        } catch (InvalidResolution $e) {
            return array_column($e->violations(), 'field');
        }
        self::fail('The resolution was accepted.');
    }

    public function testItStartsAtDesdeAndHasIssuedNothing(): void
    {
        $r = self::resolution(500, 600);

        self::assertSame(500, $r->nextNumber(), 'The first invoice takes the number the DIAN authorised first.');
        self::assertFalse($r->hasIssuedNumbers());
    }

    public function testDesdeCannotBeAfterHasta(): void
    {
        self::assertSame(['range_to'], self::violationFields(static fn () => self::resolution(10, 9)));
        self::assertSame(10, self::resolution(10, 10)->rangeTo(), 'desde = hasta is one number: allowed.');
    }

    public function testTheStartDateCannotBeAfterTheEndDate(): void
    {
        self::assertSame(['valid_to'], self::violationFields(static fn () => self::resolution(1, 10, '2026-02-01', '2026-01-31')));
        self::resolution(1, 10, '2026-02-01', '2026-02-01');
        $this->addToAssertionCount(1);
    }

    public function testNumbersStartAtOneAndAPrefixIsAlphanumeric(): void
    {
        self::assertSame(['range_from'], self::violationFields(static fn () => self::resolution(0, 10)));
        self::assertSame(['prefix'], self::violationFields(static fn () => InvoicingResolution::define(Uuid::v7(), '1', 'SE-TP', 1, 10, self::day('2026-01-01'), self::day('2026-12-31'), InvoicingMode::Electronic, false)));
    }

    public function testManualModeNeedsTheOwnersConfirmation(): void
    {
        $manual = static fn (bool $confirmed) => InvoicingResolution::define(Uuid::v7(), '1', 'A', 1, 10, self::day('2026-01-01'), self::day('2026-12-31'), InvoicingMode::Manual, $confirmed);

        self::assertSame(['mode'], self::violationFields(static fn () => $manual(false)), 'Sales invoices are electronic by law until the owner says the DIAN permission is held.');
        self::assertSame(InvoicingMode::Manual, $manual(true)->mode());
    }

    public function testItIsActiveOnBothBoundaryDays(): void
    {
        $r = self::resolution();

        self::assertSame(ResolutionStatus::NotYetValid, $r->status(self::day('2025-12-31')));
        self::assertSame(ResolutionStatus::Active, $r->status(self::day('2026-01-01')), 'The first day counts.');
        self::assertSame(ResolutionStatus::Active, $r->status(self::day('2026-12-31 18:00')), 'The last day counts at any hour.');
        self::assertSame(ResolutionStatus::Expired, $r->status(self::day('2027-01-01')));
    }

    public function testItIsExhaustedOnceHastaWasTaken(): void
    {
        $r = self::resolution(1, 2);
        $r->take(self::day('2026-03-01'));
        self::assertSame(1, $r->numbersLeft());
        self::assertSame(ResolutionStatus::Active, $r->status(self::day('2026-03-01')));

        $r->take(self::day('2026-03-01'));

        self::assertSame(0, $r->numbersLeft());
        self::assertSame(ResolutionStatus::Exhausted, $r->status(self::day('2026-03-01')));
    }

    public function testTakingNumbersCountsUpFromDesde(): void
    {
        $r = self::resolution(100, 200);

        self::assertSame([100, 101, 102], [$r->take(self::day('2026-03-01')), $r->take(self::day('2026-03-01')), $r->take(self::day('2026-03-01'))]);
        self::assertSame(103, $r->nextNumber());
        self::assertTrue($r->hasIssuedNumbers());
    }

    public function testItRefusesToNumberOutsideItsDates(): void
    {
        $r = self::resolution();

        foreach (['2025-12-31', '2027-01-01'] as $date) {
            try {
                $r->take(self::day($date));
                self::fail("$date is outside the resolution.");
            } catch (ResolutionInactive $e) {
                self::assertSame('resolution_inactive', $e->errorCode());
            }
        }
        self::assertSame(1, $r->nextNumber(), 'A refusal consumes nothing.');
    }

    public function testItRefusesToNumberPastHasta(): void
    {
        $r = self::resolution(1, 1);
        $r->take(self::day('2026-03-01'));

        $this->expectException(ResolutionExhausted::class);
        $r->take(self::day('2026-03-01'));
    }

    public function testItCountsTheDaysLeftIncludingTheLastDay(): void
    {
        $r = self::resolution();

        self::assertSame(0, $r->daysLeft(self::day('2026-12-31 20:00')), 'On the last day there are no more days after today.');
        self::assertSame(30, $r->daysLeft(self::day('2026-12-01')));
        self::assertSame(0, $r->daysLeft(self::day('2027-02-01')), 'Never negative.');
    }

    public function testItWarnsUnderEitherThreshold(): void
    {
        $r = self::resolution(1, 1000);

        self::assertFalse($r->isRunningOut(self::day('2026-03-01'), 100, 30), 'Plenty of both.');
        self::assertTrue($r->isRunningOut(self::day('2026-12-15'), 100, 30), 'Under 30 days left.');
        self::assertFalse($r->isRunningOut(self::day('2026-12-01'), 100, 30), 'Exactly 30 days left is not under 30.');
        for ($i = 0; $i < 901; ++$i) {
            $r->take(self::day('2026-03-01'));
        }
        self::assertSame(99, $r->numbersLeft());
        self::assertTrue($r->isRunningOut(self::day('2026-03-01'), 100, 30), 'Under 100 numbers left.');
    }

    public function testItNeverWarnsWhenItIsAlreadyBlockedOrNotYetValid(): void
    {
        self::assertFalse(self::resolution()->isRunningOut(self::day('2027-06-01'), 100, 30), 'Expired: the status says so, a warning would be noise.');
    }

    public function testBeforeAnyInvoiceEverythingCanChange(): void
    {
        $r = self::resolution(1, 100);

        $r->revise('999', 'FE', 5000, 5100, self::day('2026-02-01'), self::day('2026-11-30'), InvoicingMode::Electronic, false);

        self::assertSame([5000, 'FE', 5000], [$r->rangeFrom(), $r->prefix(), $r->nextNumber()], 'Desde moved: nothing was numbered, so the next number moves with it.');
    }

    public function testOnceNumberedDesdeAndPrefixAreLocked(): void
    {
        $r = self::resolution(1, 100);
        $r->take(self::day('2026-03-01'));

        self::assertSame(['range_from'], self::violationFields(static fn () => $r->revise('1', 'SETP', 2, 100, self::day('2026-01-01'), self::day('2026-12-31'), InvoicingMode::Electronic, false)));
        self::assertSame(['prefix'], self::violationFields(static fn () => $r->revise('1', 'FE', 1, 100, self::day('2026-01-01'), self::day('2026-12-31'), InvoicingMode::Electronic, false)));
    }

    public function testOnceNumberedHastaCannotGoBelowTheLastNumberUsed(): void
    {
        $r = self::resolution(1, 100);
        for ($i = 0; $i < 10; ++$i) {
            $r->take(self::day('2026-03-01'));
        }

        self::assertSame(['range_to'], self::violationFields(static fn () => $r->revise('1', 'SETP', 1, 9, self::day('2026-01-01'), self::day('2026-12-31'), InvoicingMode::Electronic, false)), 'Number 10 was already used: hasta 9 would leave an emitted invoice outside its resolution.');
    }

    public function testHastaMayStayAtTheLastNumberUsedOrGrow(): void
    {
        $r = self::resolution(1, 100);
        for ($i = 0; $i < 10; ++$i) {
            $r->take(self::day('2026-03-01'));
        }

        $r->revise('1', 'SETP', 1, 10, self::day('2026-01-01'), self::day('2026-12-31'), InvoicingMode::Electronic, false);
        self::assertSame(ResolutionStatus::Exhausted, $r->status(self::day('2026-03-02')), 'Hasta 10 with ten numbers taken: nothing left.');
        $r->revise('1', 'SETP', 1, 500, self::day('2026-01-01'), self::day('2027-06-30'), InvoicingMode::Electronic, false);
        self::assertSame(ResolutionStatus::Active, $r->status(self::day('2026-03-02')), 'Extending the range reopens it.');
        self::assertSame(11, $r->nextNumber(), 'The consecutive is never rewound.');
    }
}
