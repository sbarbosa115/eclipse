<?php

namespace App\Ledger\Application\Report;

final readonly class StatementLine
{
    public function __construct(
        public string $code,
        public string $name,
        /** group or account */
        public string $level,
        public string $amount,
    ) {
    }
}
