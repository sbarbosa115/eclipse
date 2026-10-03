<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Model\PostingRule;
use App\Shared\Domain\Accounting\PostingConcept;
use Symfony\Component\Uid\Uuid;

interface PostingRuleRepository
{
    public function forConcept(Uuid $companyId, PostingConcept $concept): ?PostingRule;

    /** Whether any rule of the company posts to this account. */
    public function pointsAt(Uuid $companyId, Uuid $accountId): bool;
}
