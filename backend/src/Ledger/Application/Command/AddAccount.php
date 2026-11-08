<?php

namespace App\Ledger\Application\Command;

use Symfony\Component\Uid\Uuid;

/** The accountant adds a sub-account or auxiliar to the chart (§4.1). */
final readonly class AddAccount
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public string $parentCode,
        public string $code,
        public string $name,
        /** Null: classes 5, 6 and 7 are, the rest are not (§9 Q14). */
        public ?bool $usableOnPurchases,
        /**
         * The first auxiliar of an account in use takes what pointed at it: posting rules, taxes, payment methods,
         * products, terceros (observations PRD §4.1). False leaves them for the accountant to change by hand.
         */
        public bool $moveReferences = true,
    ) {
    }
}
