<?php

namespace App\Ledger\Application\Command;

use Symfony\Component\Uid\Uuid;

/** The accountant renames the company's own account, (de)activates an account or marks it usable on purchases. */
final readonly class UpdateAccount
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public Uuid $accountId,
        public string $name,
        public bool $active,
        public bool $usableOnPurchases,
    ) {
    }
}
