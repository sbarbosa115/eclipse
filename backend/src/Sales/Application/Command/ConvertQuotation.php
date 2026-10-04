<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** "Convertir a factura" (§4.7): a draft sales invoice from the quotation, once → the invoice's id. */
final readonly class ConvertQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
        public Uuid $userId,
    ) {
    }
}
