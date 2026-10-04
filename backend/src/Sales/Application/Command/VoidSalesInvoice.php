<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Voids an emitted invoice today, with a reason (§4.12). */
final readonly class VoidSalesInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
