<?php

namespace App\Access\Application\Query;

use App\Access\Application\Links;
use App\Access\Domain\Error\LinkInvalid;
use App\Access\Domain\Error\UserNotFound;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\UserStatus;
use App\Access\Domain\Repository\UserRepository;
use App\Company\Application\Query\Companies;
use App\Shared\Domain\Clock;

/** What the public pages show about a link before the person acts on it. */
final class Invitations
{
    public function __construct(
        private readonly Links $links,
        private readonly UserRepository $users,
        private readonly Companies $companies,
        private readonly Clock $clock,
    ) {
    }

    /** @throws LinkInvalid */
    public function lookup(#[\SensitiveParameter] string $token): InvitationView
    {
        $link = $this->links->find($token, TokenPurpose::Invitation, $this->clock->now());
        try {
            $user = $this->users->getById($link->userId());
        } catch (UserNotFound) {
            throw new LinkInvalid();
        }
        if (UserStatus::Invited !== $user->status()) {
            throw new LinkInvalid();
        }

        return new InvitationView($user->email(), $this->companies->view($user->companyId())->legalName, $user->role()->value);
    }

    /** @throws LinkInvalid when a password-reset link no longer works */
    public function checkPasswordReset(#[\SensitiveParameter] string $token): void
    {
        $link = $this->links->find($token, TokenPurpose::PasswordReset, $this->clock->now());
        try {
            $user = $this->users->getById($link->userId());
        } catch (UserNotFound) {
            throw new LinkInvalid();
        }
        if (!$user->canSignIn()) {
            throw new LinkInvalid();
        }
    }
}
