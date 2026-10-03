<?php

namespace App\Ledger\Application\PaymentMethod;

use Symfony\Component\Uid\Uuid;

/** Renames a payment method and moves its account (a crédito method has none). */
final readonly class UpdatePaymentMethod
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $paymentMethodId,
        public string $name,
        public ?string $accountId,
    ) {
    }
}
