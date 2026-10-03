<?php

namespace App\Access\Application\Command;

use App\Access\Application\Links;
use App\Access\Domain\Event\PasswordResetRequested;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Clock;

final class RequestPasswordResetHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Links $links,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        $user = $this->users->findByEmail($command->email);
        // Only someone who can sign in gets a link: an invitee accepts their invitation, a deactivated user stays out.
        if (null === $user || !$user->canSignIn()) {
            return;
        }

        [$token, $expiresAt] = $this->links->issue($user, TokenPurpose::PasswordReset, $this->clock->now());
        $this->events->publish(new PasswordResetRequested($user->companyId(), $user->id(), $token, $expiresAt));
    }
}
