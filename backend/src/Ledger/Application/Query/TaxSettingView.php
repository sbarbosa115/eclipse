<?php

namespace App\Ledger\Application\Query;

/** A tax as the settings screen shows it: the catalog's TaxView, the accounts it posts to by name, and whether a document uses it. */
final readonly class TaxSettingView
{
    public function __construct(
        public TaxView $tax,
        public ?AccountView $salesAccount,
        public ?AccountView $purchaseAccount,
        public bool $inUse,
    ) {
    }
}
