<?php

namespace App\Ledger\Domain\Event;

/**
 * The first auxiliar was added under an account that received movements, and what pointed at the parent should move
 * to the new child (observations PRD §4.1): Catalog re-points products, Party terceros. Published after the commit.
 */
final readonly class AccountSubdivided
{
    public function __construct(
        public string $companyId,
        public string $parentAccountId,
        public string $childAccountId,
        public string $byUserId,
    ) {
    }
}
