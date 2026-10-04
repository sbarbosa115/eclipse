<?php

namespace App\Access\Application\Command;

use App\Access\Application\Links;
use App\Access\Domain\Error\EmailTaken;
use App\Access\Domain\Event\InvitationIssued;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

final class InviteUserHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Links $links,
        private readonly AuditTrail $audit,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    /** @return Uuid the invitee's user id */
    public function __invoke(InviteUser $command): Uuid
    {
        // One person, one company (§9 Q22): an e-mail registered anywhere, invited or not, is refused.
        if (null !== $this->users->findByEmail($command->email)) {
            throw new EmailTaken();
        }

        $now = $this->clock->now();
        $user = User::invite($command->companyId, $command->email, '', $command->role, $now);
        $this->users->add($user);
        [$token, $expiresAt] = $this->links->issue($user, TokenPurpose::Invitation, $now);

        $this->audit->record($command->companyId, $command->invitedBy, 'user.invited', 'user', $user->id(), ['email' => $user->email(), 'role' => $user->role()->value]);
        $this->events->publish(new InvitationIssued($command->companyId, $user->id(), $command->invitedBy, $token, $expiresAt));

        return $user->id();
    }
}
