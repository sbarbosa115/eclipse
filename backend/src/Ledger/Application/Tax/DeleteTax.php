<?php

namespace App\Ledger\Application\Tax;

use Symfony\Component\Uid\Uuid;

/** Removes a tax nothing uses; a used one is only deactivated (tax_in_use). */
final readonly class DeleteTax
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $taxId,
    ) {
    }
}
