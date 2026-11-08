<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Deletes a draft orden de compra: an emitted one is voided instead. */
final readonly class DeleteDraftPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
    ) {
    }
}
