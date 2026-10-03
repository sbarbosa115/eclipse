<?php

namespace App\Ledger\Application\PaymentMethod;

use Symfony\Component\Uid\Uuid;

/** Removes a payment method nothing uses; a used one is only deactivated (payment_method_in_use). */
final readonly class DeletePaymentMethod
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $paymentMethodId,
    ) {
    }
}
