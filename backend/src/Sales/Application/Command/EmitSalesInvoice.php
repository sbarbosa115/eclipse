<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Emits a draft (§4.8); with $send, then e-mails its PDF to the client ("Emitir y enviar"). */
final readonly class EmitSalesInvoice
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $invoiceId,
        public Uuid $userId,
        public bool $send = false,
    ) {
    }
}
