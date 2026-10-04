<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class VoidCashReceipt
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $receiptId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
