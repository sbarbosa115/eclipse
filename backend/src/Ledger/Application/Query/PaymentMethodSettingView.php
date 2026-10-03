<?php

namespace App\Ledger\Application\Query;

/** A payment method as the settings screen shows it: the catalog's view and whether a document uses it. */
final readonly class PaymentMethodSettingView
{
    public function __construct(
        public PaymentMethodView $method,
        public bool $inUse,
    ) {
    }
}
