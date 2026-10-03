<?php

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock;
use Psr\Clock\ClockInterface;

/**
 * The domain's Clock on Symfony's clock service, so a test freezes or moves the time with ClockSensitiveTrait
 * (`static::mockTime('2026-03-01')`) and every rule that asks the clock follows.
 */
final class SystemClock implements Clock
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
