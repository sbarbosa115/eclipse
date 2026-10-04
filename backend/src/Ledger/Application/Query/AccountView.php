<?php

namespace App\Ledger\Application\Query;

final readonly class AccountView
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        /** debit or credit */
        public string $nature,
        /** class, group, account, subaccount, auxiliary */
        public string $level,
        public ?string $parentCode,
        public bool $standard,
        public bool $active,
        public bool $postable,
        public bool $usableOnPurchases,
    ) {
    }
}
