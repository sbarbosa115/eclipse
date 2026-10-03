<?php

namespace App\Access\Application\Command;

use App\Access\Application\Links;
use App\Access\Domain\Error\CannotDeactivateYourself;
use App\Access\Domain\Error\LastOwner;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\UserStatus;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

final class SetUserActiveHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Links $links,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SetUserActive $command): void
    {
        $user = $this->users->get($command->companyId, $command->userId);
        if ($command->active === (UserStatus::Deactivated !== $user->status())) {
            return;
        }

        if ($command->active) {
            $user->reactivate();
        } else {
            if ($user->isActiveOwner() && $this->users->countActiveOwners($command->companyId) <= 1) {
                throw new LastOwner();
            }
            if ($user->id()->equals($command->changedBy)) {
                throw new CannotDeactivateYourself();
            }
            $user->deactivate();
            // Links already e-mailed stop working too.
            $now = $this->clock->now();
            $this->links->revoke($user, TokenPurpose::Invitation, $now);
            $this->links->revoke($user, TokenPurpose::PasswordReset, $now);
        }

        $this->audit->record($command->companyId, $command->changedBy, $command->active ? 'user.reactivated' : 'user.deactivated', 'user', $user->id());
    }
}
