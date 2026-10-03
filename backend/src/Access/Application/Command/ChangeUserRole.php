<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Model\Role;
use Symfony\Component\Uid\Uuid;

final readonly class ChangeUserRole
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $changedBy,
        public Uuid $userId,
        public Role $role,
    ) {
    }
}
