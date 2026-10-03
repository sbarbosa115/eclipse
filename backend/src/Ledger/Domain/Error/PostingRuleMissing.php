<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Error\Conflict;

/** A document posted a concept the company has no posting rule for. */
final class PostingRuleMissing extends Conflict
{
    public function __construct(private readonly PostingConcept $concept)
    {
        parent::__construct('posting_rule_missing', \sprintf('No posting rule for "%s".', $concept->value));
    }

    public function details(): array
    {
        return ['concept' => $this->concept->value];
    }
}
