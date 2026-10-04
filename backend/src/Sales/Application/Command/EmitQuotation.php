<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Emits a draft cotización (§4.7); with $send, then e-mails its PDF to the client ("Emitir y enviar"). */
final readonly class EmitQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
        public Uuid $userId,
        public bool $send = false,
    ) {
    }
}
