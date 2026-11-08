<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** "Convertir en factura": a draft purchase invoice from the order, once → the invoice's id. */
final readonly class ConvertPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
        public Uuid $userId,
    ) {
    }
}
