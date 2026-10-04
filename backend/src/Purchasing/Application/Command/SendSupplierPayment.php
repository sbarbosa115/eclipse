<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class SendSupplierPayment
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $paymentId,
    ) {
    }
}
