<?php

namespace App\Ledger\Application\Tax;

use Symfony\Component\Uid\Uuid;

/** The accountant or owner adds a tax to the company's catalog (§4.4). Dates are "Y-m-d". */
final readonly class CreateTax
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public string $name,
        public string $taxClass,
        public string $kind,
        public string $calculation,
        public string $rate,
        public ?string $salesAccountId,
        public ?string $purchaseAccountId,
        public ?string $validFrom,
        public ?string $validTo,
    ) {
    }
}
