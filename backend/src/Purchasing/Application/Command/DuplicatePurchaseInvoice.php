<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** A new draft like an existing invoice (§4.15). */
final readonly class DuplicatePurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
    ) {
    }
}
