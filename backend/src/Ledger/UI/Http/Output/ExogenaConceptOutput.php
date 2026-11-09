<?php

namespace App\Ledger\UI\Http\Output;

/** Accounts starting with account_prefix report under concept_code in format (the longest prefix wins). */
final readonly class ExogenaConceptOutput
{
    public function __construct(
        /** 1001 or 1007 */
        public string $format,
        public string $accountPrefix,
        public string $conceptCode,
    ) {
    }
}
