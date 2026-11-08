<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Rewrites a draft orden de compra (only a draft changes). */
final readonly class UpdateDraftPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
        public PurchaseOrderContents $contents,
    ) {
    }
}
