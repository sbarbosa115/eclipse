<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Voids an emitted cotización with a reason; there is no entry to reverse (§4.7). */
final readonly class VoidQuotation
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $quotationId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
