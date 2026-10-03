<?php

namespace App\Access\Application\Command;

use Symfony\Component\Uid\Uuid;

/** Deactivate a user (they may not sign in; their session ends on its next request), or reactivate them. */
final readonly class SetUserActive
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $changedBy,
        public Uuid $userId,
        public bool $active,
    ) {
    }
}
