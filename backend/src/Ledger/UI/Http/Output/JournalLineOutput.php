<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\JournalLineView;

final readonly class JournalLineOutput
{
    public function __construct(
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public ?string $terceroId,
        public ?string $terceroName,
        /** Decimal string; one of débito and crédito is "0.00". */
        public string $debit,
        public string $credit,
        public ?string $description,
    ) {
    }

    public static function of(JournalLineView $v): self
    {
        return new self($v->accountId, $v->accountCode, $v->accountName, $v->terceroId, $v->terceroName, $v->debit, $v->credit, $v->description);
    }
}
