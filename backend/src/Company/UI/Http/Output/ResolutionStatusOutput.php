<?php

namespace App\Company\UI\Http\Output;

use App\Company\Application\Query\ResolutionStatusView;

/** Where the invoicing resolution stands today. */
final readonly class ResolutionStatusOutput
{
    public function __construct(
        /** missing, not_yet_valid, active, expired or exhausted */
        public string $status,
        public int $numbersLeft,
        /** Whole days after today until the last valid day. */
        public int $daysLeft,
        /** Active but running out: under the company's thresholds of numbers or days. */
        public bool $warning,
        public int $warningNumbers,
        public int $warningDays,
    ) {
    }

    public static function of(ResolutionStatusView $v): self
    {
        return new self($v->status, $v->numbersLeft, $v->daysLeft, $v->warning, $v->warningNumbers, $v->warningDays);
    }
}
