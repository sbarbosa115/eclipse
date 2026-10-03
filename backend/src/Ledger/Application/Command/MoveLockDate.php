<?php

namespace App\Ledger\Application\Command;

use Symfony\Component\Uid\Uuid;

/** The accountant moves the fecha de bloqueo contable (§4.1). */
final readonly class MoveLockDate
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public \DateTimeImmutable $lockedUntil,
    ) {
    }
}
