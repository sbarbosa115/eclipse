<?php

namespace App\Company\Application\Query;

/**
 * Where the resolution stands today, and whether the owner should be warned (§4.1).
 */
final readonly class ResolutionStatusView
{
    public function __construct(
        /** missing, not_yet_valid, active, expired or exhausted */
        public string $status,
        public int $numbersLeft,
        public int $daysLeft,
        /** Active, with fewer numbers or days left than the company's thresholds. */
        public bool $warning,
        public int $warningNumbers,
        public int $warningDays,
    ) {
    }
}
