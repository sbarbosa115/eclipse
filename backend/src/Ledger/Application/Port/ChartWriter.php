<?php

namespace App\Ledger\Application\Port;

use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\PostingRule;
use Symfony\Component\Uid\Uuid;

/**
 * Writes a new company's chart, posting rules and open books in bulk: thousands of rows on every sign-up, inside the
 * sign-up's transaction.
 */
interface ChartWriter
{
    /**
     * @param list<Account>     $accounts
     * @param list<PostingRule> $rules
     */
    public function write(Uuid $companyId, array $accounts, array $rules): void;
}
