<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Emits a draft orden de compra: the next OC number, frozen; optionally e-mailed to the supplier. */
final readonly class EmitPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
        public Uuid $userId,
        public bool $send = false,
    ) {
    }
}
