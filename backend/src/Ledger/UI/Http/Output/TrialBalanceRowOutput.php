<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Report\TrialBalanceRow;

final readonly class TrialBalanceRowOutput
{
    public function __construct(
        public string $code,
        public string $name,
        /** class, group, account, subaccount, auxiliary */
        public string $level,
        /** debit or credit */
        public string $nature,
        /** Saldo anterior, débito minus crédito (a crédito balance is negative). */
        public string $opening,
        public string $debit,
        public string $credit,
        /** Nuevo saldo, débito minus crédito. */
        public string $closing,
    ) {
    }

    public static function of(TrialBalanceRow $r): self
    {
        return new self($r->code, $r->name, $r->level, $r->nature, $r->opening, $r->debit, $r->credit, $r->closing);
    }
}
