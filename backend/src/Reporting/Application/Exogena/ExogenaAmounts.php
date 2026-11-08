<?php

namespace App\Reporting\Application\Exogena;

/** What the documents of the year add up to for one tercero (and, in 1001 and 1007, one account), by value column. */
final readonly class ExogenaAmounts
{
    /**
     * @param array<string, string> $values decimal strings by the format's column keys
     */
    public function __construct(
        public string $terceroId,
        public ?string $accountCode,
        public array $values,
    ) {
    }
}
