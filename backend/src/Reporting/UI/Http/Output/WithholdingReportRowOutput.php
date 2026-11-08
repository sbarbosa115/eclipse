<?php

namespace App\Reporting\UI\Http\Output;

/** One tercero and retención tax of a month: base, value and how many documents (observations PRD §4.7 a, b). */
final readonly class WithholdingReportRowOutput
{
    public function __construct(
        public string $terceroId,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public string $terceroName,
        public ?string $taxId,
        public string $taxName,
        public string $rate,
        public string $base,
        public string $amount,
        public int $documents,
    ) {
    }
}
