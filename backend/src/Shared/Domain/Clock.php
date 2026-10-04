<?php

namespace App\Shared\Domain;

/**
 * The time, asked for instead of read with `new \DateTimeImmutable()`, so a rule that depends on it (token expiry,
 * session TTL) can be tested at any moment. Domain code gets the moment passed in; the handler asks the clock.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
