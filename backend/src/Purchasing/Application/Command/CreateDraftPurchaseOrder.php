<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** A new draft orden de compra → its id. */
final readonly class CreateDraftPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public PurchaseOrderContents $contents,
    ) {
    }
}
