<?php

namespace App\Ledger\Application\Query;

use App\Shared\Domain\Money\Money;

/** What one account moved: before the period (its saldo anterior, débito minus crédito) and within it. */
final readonly class AccountMovement
{
    public function __construct(
        public string $code,
        public Money $opening,
        public Money $debit,
        public Money $credit,
    ) {
    }
}
