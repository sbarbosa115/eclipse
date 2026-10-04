<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class VoidSupplierPayment
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $paymentId,
        public Uuid $userId,
        public string $reason,
    ) {
    }
}
