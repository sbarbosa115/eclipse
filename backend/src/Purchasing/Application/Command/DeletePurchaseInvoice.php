<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Deletes a draft (never an emitted invoice: that is voided) with its files. */
final readonly class DeletePurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
    ) {
    }
}
