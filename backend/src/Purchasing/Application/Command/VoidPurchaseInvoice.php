<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Voids an emitted invoice nothing is paid on (§4.12). */
final readonly class VoidPurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
