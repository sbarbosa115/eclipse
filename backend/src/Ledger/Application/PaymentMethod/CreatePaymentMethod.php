<?php

namespace App\Ledger\Application\PaymentMethod;

use Symfony\Component\Uid\Uuid;

/** The accountant or owner adds a forma de pago (§4.5): contado with a postable account, or crédito without one. */
final readonly class CreatePaymentMethod
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public string $name,
        public string $kind,
        public ?string $accountId,
    ) {
    }
}
