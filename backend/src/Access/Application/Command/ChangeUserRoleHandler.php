<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Error\LastOwner;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

final class ChangeUserRoleHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(ChangeUserRole $command): void
    {
        $user = $this->users->get($command->companyId, $command->userId);
        $from = $user->role();
        if ($from === $command->role) {
            return;
        }
        if (Role::Owner !== $command->role && $user->isActiveOwner() && $this->users->countActiveOwners($command->companyId) <= 1) {
            throw new LastOwner();
        }

        $user->changeRole($command->role);
        $this->audit->record($command->companyId, $command->changedBy, 'user.role_changed', 'user', $user->id(), ['from' => $from->value, 'to' => $command->role->value]);
    }
}
