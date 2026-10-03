<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\AccountView;

final readonly class AccountOutput
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

    public static function of(AccountView $v): self
    {
        return new self($v->id, $v->code, $v->name, $v->nature, $v->level, $v->parentCode, $v->standard, $v->active, $v->postable, $v->usableOnPurchases);
    }
}
