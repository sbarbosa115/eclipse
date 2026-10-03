<?php

namespace App\Ledger\Application\Report;

/**
 * One account of the balance de prueba. Balances are débito minus crédito (a crédito balance is negative); débitos
 * and créditos are the period's.
 */
final readonly class TrialBalanceRow
{
    public function __construct(
        public string $code,
        public string $name,
        /** class, group, account, subaccount, auxiliary */
        public string $level,
        public string $nature,
        public string $opening,
        public string $debit,
        public string $credit,
        public string $closing,
    ) {
    }
}
