<?php

namespace App\Access\Application\Command;

use App\Access\Application\Links;
use App\Access\Application\Port\PasswordHasher;
use App\Access\Domain\Error\LinkInvalid;
use App\Access\Domain\Error\UserNotFound;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\UserStatus;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

final class AcceptInvitationHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Links $links,
        private readonly PasswordHasher $hasher,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {
    }

    /** @return Uuid the user, now active, to sign in */
    public function __invoke(AcceptInvitation $command): Uuid
    {
        $now = $this->clock->now();
        $link = $this->links->find($command->token, TokenPurpose::Invitation, $now);
        try {
            $user = $this->users->getById($link->userId());
        } catch (UserNotFound) {
            throw new LinkInvalid();
        }
        // Deactivated since the invitation was sent: the link no longer works.
        if (UserStatus::Invited !== $user->status()) {
            throw new LinkInvalid();
        }

        $user->acceptInvitation($command->name, $this->hasher->hash($command->password));
        $link->use($now);
        $this->audit->record($user->companyId(), $user->id(), 'user.invitation_accepted', 'user', $user->id());

        return $user->id();
    }
}
