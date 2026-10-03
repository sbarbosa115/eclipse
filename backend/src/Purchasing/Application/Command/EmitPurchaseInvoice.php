<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Emits a draft: internal number, payables, journal entry (§4.10, A.3). */
final readonly class EmitPurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
    ) {
    }
}
