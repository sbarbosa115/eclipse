<?php

namespace App\Ledger\Application\Tax;

use Symfony\Component\Uid\Uuid;

/** Deactivating takes a tax out of the pickers; documents that used it keep it. */
final readonly class SetTaxActive
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $taxId,
        public bool $active,
    ) {
    }
}
