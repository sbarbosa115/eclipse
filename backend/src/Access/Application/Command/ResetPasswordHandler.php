<?php

namespace App\Access\Application\Command;

use App\Access\Application\Links;
use App\Access\Application\Port\PasswordHasher;
use App\Access\Domain\Error\LinkInvalid;
use App\Access\Domain\Error\UserNotFound;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * A new password from a reset link. Every session signed in with the old one ends on its next request: the user
 * provider reloads the user, and the firewall signs out a session whose password hash changed.
 */
final class ResetPasswordHandler implements CommandHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Links $links,
        private readonly PasswordHasher $hasher,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {
    }

    /** @return Uuid the user, to sign in */
    public function __invoke(ResetPassword $command): Uuid
    {
        $now = $this->clock->now();
        $link = $this->links->find($command->token, TokenPurpose::PasswordReset, $now);
        try {
            $user = $this->users->getById($link->userId());
        } catch (UserNotFound) {
            throw new LinkInvalid();
        }
        if (!$user->canSignIn()) {
            throw new LinkInvalid();
        }

        $user->changePassword($this->hasher->hash($command->password));
        $link->use($now);
        $this->audit->record($user->companyId(), $user->id(), 'user.password_reset', 'user', $user->id());

        return $user->id();
    }
}
