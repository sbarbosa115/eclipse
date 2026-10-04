<?php

namespace App\Access\UI\Http\Security;

use App\Access\Application\Query\UserView;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The signed-in user as the firewall keeps it in the session: ids, role and the password hash. The provider reloads
 * it on every request: a changed password or role signs the session out (Symfony compares them), and a deactivated
 * user is no longer found.
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface, SignedInUser
{
    public function __construct(
        private readonly string $id,
        private readonly string $companyId,
        private readonly string $email,
        private readonly string $role,
        private readonly bool $enabled,
        private readonly ?string $passwordHash,
    ) {
    }

    public static function from(UserView $user): self
    {
        return new self($user->id, $user->companyId, $user->email, $user->role, $user->canSignIn, $user->passwordHash);
    }

    public function userId(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function companyId(): Uuid
    {
        return Uuid::fromString($this->companyId);
    }

    public function role(): string
    {
        return $this->role;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : throw new \LogicException('A user has an e-mail.');
    }

    public function getRoles(): array
    {
        return ['ROLE_'.strtoupper($this->role)];
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }
}
