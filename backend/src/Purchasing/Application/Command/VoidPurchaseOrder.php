<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Voids an emitted orden de compra with a reason: no entry to reverse, the number is kept. */
final readonly class VoidPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
