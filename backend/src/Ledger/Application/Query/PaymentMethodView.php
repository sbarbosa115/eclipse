<?php

namespace App\Ledger\Application\Query;

final readonly class PaymentMethodView
{
    public function __construct(
        public string $id,
        public string $name,
        /** cash (contado) or credit */
        public string $kind,
        public ?string $accountId,
        public ?string $accountCode,
        public ?string $accountName,
        public bool $active,
        public bool $standard,
    ) {
    }
}
