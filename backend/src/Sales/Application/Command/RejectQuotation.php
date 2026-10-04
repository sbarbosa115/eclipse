<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** The client's answer to an emitted, still valid cotización: reject. */
final readonly class RejectQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
    ) {
    }
}
