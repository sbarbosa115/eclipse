<?php

namespace App\Purchasing\Application;

use App\Shared\Domain\Clock;

/** Documents are dated on Colombian calendar days, whatever the server's time zone. */
final class ColombianCalendar
{
    public static function today(Clock $clock): \DateTimeImmutable
    {
        return new \DateTimeImmutable($clock->now()->setTimezone(new \DateTimeZone('America/Bogota'))->format('Y-m-d'));
    }
}
