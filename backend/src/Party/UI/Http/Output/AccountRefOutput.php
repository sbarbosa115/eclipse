<?php

namespace App\Party\UI\Http\Output;

/** An account of the chart as the tercero's form shows it. */
final readonly class AccountRefOutput
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {
    }
}
