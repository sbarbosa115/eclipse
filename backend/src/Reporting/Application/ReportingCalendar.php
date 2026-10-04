<?php

namespace App\Reporting\Application;

use App\Shared\Domain\Clock;

/** "Today" for a report is a Colombian calendar day, like the documents' and the resolution's. */
final class ReportingCalendar
{
    public const TIMEZONE = 'America/Bogota';

    public function __construct(private readonly Clock $clock)
    {
    }

    public function today(): \DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return new \DateTimeImmutable($now->format('Y-m-d'));
    }
}
