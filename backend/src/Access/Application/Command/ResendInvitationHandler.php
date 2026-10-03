<?php

namespace App\Access\Application\Command;

use App\Access\Application\Links;
use App\Access\Domain\Error\NotAnInvitation;
use App\Access\Domain\Event\InvitationIssued;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\UserStatus;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Clock;

final class ResendInvitationHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Links $links,
        private readonly AuditTrail $audit,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ResendInvitation $command): void
    {
        $user = $this->users->get($command->companyId, $command->userId);
        if (UserStatus::Invited !== $user->status()) {
            throw new NotAnInvitation();
        }

        $now = $this->clock->now();
        [$token, $expiresAt] = $this->links->issue($user, TokenPurpose::Invitation, $now);
        $this->audit->record($command->companyId, $command->resentBy, 'user.invitation_resent', 'user', $user->id(), ['email' => $user->email()]);
        $this->events->publish(new InvitationIssued($command->companyId, $user->id(), $command->resentBy, $token, $expiresAt));
    }
}
