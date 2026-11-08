<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Tax\VatRate;

/** IVA at one rate: the lines' base and their IVA. */
final readonly class VatRateRowOutput
{
    public function __construct(
        /** "19.0000" */
        public string $rate,
        public string $base,
        public string $tax,
    ) {
    }

    public static function of(VatRate $r): self
    {
        return new self($r->rate, $r->base, $r->tax);
    }
}
