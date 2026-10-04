<?php

namespace App\Ledger\Application\Tax;

use Symfony\Component\Uid\Uuid;

/** The accountant or owner edits a tax: name, calculation, rate, accounts, validity dates ("Y-m-d"). */
final readonly class UpdateTax
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $taxId,
        public string $name,
        public string $calculation,
        public string $rate,
        public ?string $salesAccountId,
        public ?string $purchaseAccountId,
        public ?string $validFrom,
        public ?string $validTo,
    ) {
    }
}
