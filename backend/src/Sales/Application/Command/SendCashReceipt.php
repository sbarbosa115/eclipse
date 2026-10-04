<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class SendCashReceipt
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $receiptId,
    ) {
    }
}
