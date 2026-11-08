<?php

namespace App\Reporting\Application\Tax;

use App\Ledger\Application\Query\LedgerMovements;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * *Según libros*: what an account family moved in a period, read from the ledger's own query service (never SQL of ours
 * over the books). The movement on the side that grows it, net of the other side: a liability (2365, 240805) counts
 * crédito − débito, an asset (135515, 240810) débito − crédito. So a void in the same period nets to zero, as the
 * report leaves the voided invoice out, while one dated in a later month is the difference the footnote explains.
 */
final class BooksFigures
{
    public function __construct(private readonly LedgerMovements $movements)
    {
    }

    /**
     * @param list<string> $prefixes account families, e.g. ['2365', '2367']
     *
     * @return list<Money> what each family moved, in the order of $prefixes
     */
    public function moved(Uuid $companyId, MonthRange $range, array $prefixes, bool $growsOnCredit): array
    {
        $moved = array_map(static fn () => Money::zero(), $prefixes);
        foreach ($this->movements->byAccount($companyId, $range->first, $range->last) as $account) {
            foreach ($prefixes as $i => $prefix) {
                if (str_starts_with($account->code, $prefix)) {
                    $moved[$i] = $moved[$i]->plus($growsOnCredit ? $account->credit->minus($account->debit) : $account->debit->minus($account->credit));
                }
            }
        }

        return $moved;
    }
}
