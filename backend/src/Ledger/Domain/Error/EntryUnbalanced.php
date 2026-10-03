<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Rejected;
use App\Shared\Domain\Money\Money;

/** §5 invariant 1: every entry balances to the cent. A document that builds an unbalanced entry is not emitted. */
final class EntryUnbalanced extends Rejected
{
    public function __construct(private readonly Money $debit, private readonly Money $credit)
    {
        parent::__construct('entry_unbalanced', \sprintf('The entry does not balance: %s débito, %s crédito.', $debit, $credit));
    }

    public function details(): array
    {
        return ['debit' => $this->debit->toString(), 'credit' => $this->credit->toString()];
    }
}
