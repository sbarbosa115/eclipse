<?php

namespace App\Reporting\Application\Tax;

/** What the documents of one month say about one tercero and one retención tax: base, value and how many invoices. */
final readonly class WithholdingLine
{
    public function __construct(
        /** YYYY-MM */
        public string $month,
        public WithholdingKind $kind,
        public string $terceroId,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public string $terceroName,
        public ?string $taxId,
        public string $taxName,
        /** percentage or per_unit */
        public string $calculation,
        public string $rate,
        public string $base,
        public string $amount,
        public int $documents,
    ) {
    }
}
