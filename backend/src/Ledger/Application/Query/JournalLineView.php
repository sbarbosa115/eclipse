<?php

namespace App\Ledger\Application\Query;

final readonly class JournalLineView
{
    public function __construct(
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public ?string $terceroId,
        public ?string $terceroName,
        public string $debit,
        public string $credit,
        public ?string $description,
    ) {
    }
}
