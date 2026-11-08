<?php

namespace App\Reporting\Application\Tax;

/** IVA at one rate over a period: the lines' bases and their IVA. */
final readonly class VatRate
{
    public function __construct(public string $rate, public string $base, public string $tax)
    {
    }
}
