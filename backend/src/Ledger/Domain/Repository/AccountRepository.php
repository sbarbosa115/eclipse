<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Model\Account;
use Symfony\Component\Uid\Uuid;

interface AccountRepository
{
    /** @throws \App\Ledger\Domain\Error\AccountNotFound also for another company's account */
    public function get(Uuid $companyId, Uuid $accountId): Account;

    public function byCode(Uuid $companyId, string $code): ?Account;

    public function add(Account $account): void;
}
