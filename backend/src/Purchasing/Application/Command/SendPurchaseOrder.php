<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** "Enviar por correo": the emitted order's PDF to the supplier again. */
final readonly class SendPurchaseOrder
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $orderId,
    ) {
    }
}
