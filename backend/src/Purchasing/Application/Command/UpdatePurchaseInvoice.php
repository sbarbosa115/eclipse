<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Rewrites a draft (only a draft changes, §4.6). */
final readonly class UpdatePurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public PurchaseInvoiceContents $contents,
    ) {
    }
}
