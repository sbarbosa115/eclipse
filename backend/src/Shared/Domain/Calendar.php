<?php

namespace App\Shared\Domain;

/**
 * Colombia's calendar, read from the Clock: documents, resolutions and reports are dated on Bogotá's day, so "today"
 * must not tick over at 7 p.m. (UTC midnight) and a moment is shown on the day it was in Colombia. The one place the
 * time zone is named on the server.
 */
final class Calendar
{
    public const TIMEZONE = 'America/Bogota';

    public function __construct(private readonly Clock $clock)
    {
    }

    /** Today in Colombia, at midnight. */
    public function today(): \DateTimeImmutable
    {
        return $this->dayOf($this->clock->now());
    }

    /** The moment itself (when something was emitted or voided). */
    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    /** The Colombian day a moment fell on, at midnight: 02:00 UTC on the 4th is the 3rd. */
    public function dayOf(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d'));
    }
}
