<?php

namespace App\Reporting\UI\Http\Output;

/** IVA at one rate: the base and the tax. */
final readonly class VatRateRowOutput
{
    public function __construct(
        public string $rate,
        public string $base,
        public string $tax,
    ) {
    }
}
