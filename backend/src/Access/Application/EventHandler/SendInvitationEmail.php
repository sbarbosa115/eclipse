<?php

namespace App\Access\Application\EventHandler;

use App\Access\Application\Port\AccessMailer;
use App\Access\Domain\Event\InvitationIssued;
use App\Access\Domain\Repository\UserRepository;
use App\Company\Application\Query\Companies;
use App\Shared\Application\Event\EventHandler;

final class SendInvitationEmail implements EventHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Companies $companies,
        private readonly AccessMailer $mailer,
    ) {
    }

    public function __invoke(InvitationIssued $event): void
    {
        $user = $this->users->get($event->companyId, $event->userId);
        $inviter = $this->users->get($event->companyId, $event->invitedBy);
        $company = $this->companies->view($event->companyId);

        $this->mailer->invitation($user->email(), $company->legalName, $inviter->name(), $user->role()->value, $event->token, $event->expiresAt);
    }
}
