<?php

namespace App\Party\Application\Command;

use App\Ledger\Application\Query\LedgerCatalog;
use App\Party\Domain\Error\InvalidTerceroValue;
use App\Party\Domain\Model\TerceroProfile;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Uid\Uuid;

/**
 * The per-tercero account overrides must be postable, active accounts of the company's chart: a 1305 for what the
 * tercero owes, a 2205 or 2335 for what the company owes it.
 */
final class TerceroAccounts
{
    public function __construct(private readonly LedgerCatalog $ledger)
    {
    }

    public function check(Uuid $companyId, TerceroProfile $profile): void
    {
        $this->checkOne($companyId, $profile->receivableAccountId, 'receivable_account_id', ['1305']);
        $this->checkOne($companyId, $profile->payableAccountId, 'payable_account_id', ['2205', '2335']);
    }

    /** @param list<string> $prefixes */
    private function checkOne(Uuid $companyId, ?Uuid $accountId, string $field, array $prefixes): void
    {
        if (null === $accountId) {
            return;
        }
        try {
            $account = $this->ledger->account($companyId, $accountId);
        } catch (NotFound) {
            throw new InvalidTerceroValue($field, 'Choose an account of the chart.');
        }
        if (!$account->postable || !$account->active) {
            throw new InvalidTerceroValue($field, 'Choose an account that accepts postings.');
        }
        foreach ($prefixes as $prefix) {
            if (str_starts_with($account->code, $prefix)) {
                return;
            }
        }
        throw new InvalidTerceroValue($field, 'Choose an account of the right group.');
    }
}
