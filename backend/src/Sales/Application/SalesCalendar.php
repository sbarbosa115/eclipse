<?php

namespace App\Sales\Application;

use App\Shared\Domain\Clock;

/** "Today" for documents is a Colombian calendar day (the resolution's dates are too): it must not tick over at 7 pm. */
final class SalesCalendar
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

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
