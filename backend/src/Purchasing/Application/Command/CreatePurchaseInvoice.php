<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Saves a new draft factura de compra (§4.10). */
final readonly class CreatePurchaseInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public PurchaseInvoiceContents $contents,
        /** The orden de compra this draft is converted from (observations PRD §4.8), if any. */
        public ?Uuid $purchaseOrderId = null,
    ) {
    }
}
