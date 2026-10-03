<?php

namespace App\Ledger\Application\Query;

final readonly class PostingRuleView
{
    /**
     * @param list<string> $allowedPrefixes where in the PUC the concept may post
     */
    public function __construct(
        public string $concept,
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public array $allowedPrefixes,
    ) {
    }
}
