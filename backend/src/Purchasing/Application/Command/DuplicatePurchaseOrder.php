<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** A new draft dated today with the order's supplier and lines → its id. */
final readonly class DuplicatePurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
        public Uuid $userId,
    ) {
    }
}
