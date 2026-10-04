<?php

namespace App\Access\Application\EventHandler;

use App\Access\Application\Port\AccessMailer;
use App\Access\Domain\Event\PasswordResetRequested;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Event\EventHandler;

final class SendPasswordResetEmail implements EventHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccessMailer $mailer,
    ) {
    }

    public function __invoke(PasswordResetRequested $event): void
    {
        $user = $this->users->get($event->companyId, $event->userId);

        $this->mailer->passwordReset($user->email(), $user->name(), $event->token);
    }
}
