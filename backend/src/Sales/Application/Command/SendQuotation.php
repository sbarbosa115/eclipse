<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** E-mails an emitted cotización's PDF to the client again. */
final readonly class SendQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
    ) {
    }
}
