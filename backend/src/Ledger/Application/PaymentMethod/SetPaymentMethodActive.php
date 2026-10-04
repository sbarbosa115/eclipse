<?php

namespace App\Ledger\Application\PaymentMethod;

use Symfony\Component\Uid\Uuid;

/** Deactivating takes a method out of the pickers; documents that used it keep it. */
final readonly class SetPaymentMethodActive
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $paymentMethodId,
        public bool $active,
    ) {
    }
}
