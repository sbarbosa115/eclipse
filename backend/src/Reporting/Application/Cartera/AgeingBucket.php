<?php

namespace App\Reporting\Application\Cartera;

/**
 * Where an open item falls on the ageing of cartera (§4.13), by its due date against the report's date: al día (not
 * yet due, or due that very day), 1–30, 31–60, 61–90 and more than 90 days overdue.
 */
enum AgeingBucket: string
{
    case Current = 'current';
    case Days1To30 = 'days1_to30';
    case Days31To60 = 'days31_to60';
    case Days61To90 = 'days61_to90';
    case Over90 = 'over90';

    public static function of(\DateTimeImmutable $dueDate, \DateTimeImmutable $asOf): self
    {
        return self::forDaysOverdue(self::daysOverdue($dueDate, $asOf));
    }

    /** Whole calendar days between the due date and the report's date: negative while not yet due. */
    public static function daysOverdue(\DateTimeImmutable $dueDate, \DateTimeImmutable $asOf): int
    {
        $due = new \DateTimeImmutable($dueDate->format('Y-m-d'), new \DateTimeZone('UTC'));
        $date = new \DateTimeImmutable($asOf->format('Y-m-d'), new \DateTimeZone('UTC'));

        return (int) $due->diff($date)->format('%r%a');
    }

    public static function forDaysOverdue(int $days): self
    {
        return match (true) {
            $days <= 0 => self::Current,
            $days <= 30 => self::Days1To30,
            $days <= 60 => self::Days31To60,
            $days <= 90 => self::Days61To90,
            default => self::Over90,
        };
    }

    public function isOverdue(): bool
    {
        return self::Current !== $this;
    }
}
