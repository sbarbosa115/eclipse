<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class PostingRuleNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('posting_rule_not_found', 'Posting rule not found.');
    }
}
