<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** The client's answer to an emitted, still valid cotización: accept. */
final readonly class AcceptQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
    ) {
    }
}
