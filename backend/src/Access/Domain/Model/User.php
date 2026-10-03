<?php

namespace App\Access\Domain\Model;

use App\Access\Domain\Error\NotAnInvitation;
use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A person who signs in to one company (§9 Q22: one company per user; the company is a column, not part of the
 * identity, so a later stage can let an accountant hold several). The e-mail is unique across the whole app.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'app_user_email', columns: ['email'])]
#[ORM\Index(name: 'app_user_company', columns: ['company_id', 'name'])]
class User implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(length: 12, enumType: UserStatus::class)]
    private UserStatus $status;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSignInAt = null;

    private function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 12, enumType: Role::class)]
        private Role $role,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->status = UserStatus::Invited;
    }

    /** The person who signed the company up: its owner, active at once. */
    public static function owner(Uuid $companyId, string $email, string $name, string $passwordHash, \DateTimeImmutable $at): self
    {
        $user = new self($companyId, self::normalize($email), $name, Role::Owner, $at);
        $user->passwordHash = $passwordHash;
        $user->status = UserStatus::Active;

        return $user;
    }

    /** Someone the owner invites (§4.14): no password until they accept. */
    public static function invite(Uuid $companyId, string $email, string $name, Role $role, \DateTimeImmutable $at): self
    {
        return new self($companyId, self::normalize($email), $name, $role, $at);
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function canSignIn(): bool
    {
        return UserStatus::Active === $this->status && null !== $this->passwordHash;
    }

    public function signedIn(\DateTimeImmutable $at): void
    {
        $this->lastSignInAt = $at;
    }

    /** The invitee chose their name and password from the invitation link: they may sign in from now on. */
    public function acceptInvitation(string $name, string $passwordHash): void
    {
        if (UserStatus::Invited !== $this->status) {
            throw new NotAnInvitation();
        }
        $this->name = trim($name);
        $this->passwordHash = $passwordHash;
        $this->status = UserStatus::Active;
    }

    /** A new password (from a reset link): the old one, and every session signed in with it, no longer works. */
    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    /** The last active owner is never demoted: the use case checks it, it needs the other users. */
    public function changeRole(Role $role): void
    {
        $this->role = $role;
    }

    /** May not sign in any more; the session they have ends on its next request (the user provider refuses them). */
    public function deactivate(): void
    {
        $this->status = UserStatus::Deactivated;
    }

    /** Back as they were: active with their password, or still invited if they never accepted. */
    public function reactivate(): void
    {
        $this->status = null === $this->passwordHash ? UserStatus::Invited : UserStatus::Active;
    }

    public function isActiveOwner(): bool
    {
        return Role::Owner === $this->role && UserStatus::Active === $this->status;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastSignInAt(): ?\DateTimeImmutable
    {
        return $this->lastSignInAt;
    }
}
