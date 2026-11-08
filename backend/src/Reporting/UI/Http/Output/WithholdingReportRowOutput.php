<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Tax\WithholdingLine;

/** One tercero and retención tax of a month: base, value and how many documents (observations PRD §4.7 a, b). */
final readonly class WithholdingReportRowOutput
{
    public function __construct(
        public string $terceroId,
        /** nit, cc, ce, pasaporte, ti… */
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public string $terceroName,
        /** null when the tax was deleted after the document copied it */
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

    public static function of(WithholdingLine $l): self
    {
        return new self($l->terceroId, $l->identificationType, $l->identificationNumber, $l->checkDigit, $l->terceroName, $l->taxId, $l->taxName, $l->calculation, $l->rate, $l->base, $l->amount, $l->documents);
    }
}
