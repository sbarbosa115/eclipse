<?php

namespace App\Purchasing\Domain\Event;

/** Someone asked to e-mail the payment's PDF to the supplier: sent once the command has committed. */
final readonly class SupplierPaymentEmailRequested
{
    public function __construct(
        public string $companyId,
        public string $paymentId,
    ) {
    }
}
