<?php

namespace App\Ledger\Application\Port;

use Symfony\Component\Uid\Uuid;

/**
 * What other contexts point at an account: products (revenue, expense) in Catalog and terceros (their own receivable,
 * payable) in Party. When the accountant subdivides it, they move on AccountSubdivided; the answer counts them first
 * (observations PRD §4.1).
 */
interface AccountReferences
{
    /** How many of the company's products have it as their revenue or expense account. */
    public function products(Uuid $companyId, Uuid $accountId): int;

    /** How many of the company's terceros have it as their own receivable or payable account. */
    public function terceros(Uuid $companyId, Uuid $accountId): int;
}
