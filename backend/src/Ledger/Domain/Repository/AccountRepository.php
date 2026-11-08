<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Model\Account;
use Symfony\Component\Uid\Uuid;

interface AccountRepository
{
    /** @throws \App\Ledger\Domain\Error\AccountNotFound also for another company's account */
    public function get(Uuid $companyId, Uuid $accountId): Account;

    public function byCode(Uuid $companyId, string $code): ?Account;

    /**
     * Whether the account has an active child (a subcuenta with active auxiliares): such an account no longer
     * receives movements (observations PRD §4.1).
     */
    public function hasActiveChildren(Uuid $companyId, string $code): bool;

    public function add(Account $account): void;
}
