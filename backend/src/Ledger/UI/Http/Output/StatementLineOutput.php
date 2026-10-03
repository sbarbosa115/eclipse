<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Report\StatementLine;

final readonly class StatementLineOutput
{
    public function __construct(
        public string $code,
        public string $name,
        /** group or account */
        public string $level,
        public string $amount,
    ) {
    }

    public static function of(StatementLine $l): self
    {
        return new self($l->code, $l->name, $l->level, $l->amount);
    }
}
