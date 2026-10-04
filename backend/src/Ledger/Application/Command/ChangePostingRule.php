<?php

namespace App\Ledger\Application\Command;

use App\Shared\Domain\Accounting\PostingConcept;
use Symfony\Component\Uid\Uuid;

/** The accountant (or the owner) points a concept at another account (§5). */
final readonly class ChangePostingRule
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public PostingConcept $concept,
        public Uuid $accountId,
    ) {
    }
}
