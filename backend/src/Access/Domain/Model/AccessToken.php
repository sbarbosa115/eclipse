<?php

namespace App\Access\Domain\Model;

use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A one-use link sent by e-mail: a password reset or an invitation. Only the token's SHA-256 is stored; the e-mail
 * carries the token itself.
 */
#[ORM\Entity]
#[ORM\Table(name: 'access_token')]
#[ORM\UniqueConstraint(name: 'access_token_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'access_token_user', columns: ['user_id', 'purpose'])]
class AccessToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        #[References('app_user', onDelete: 'CASCADE')]
        private Uuid $userId,
        #[ORM\Column(length: 20, enumType: TokenPurpose::class)]
        private TokenPurpose $purpose,
        #[ORM\Column(length: 64)]
        private string $tokenHash,
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
    }

    /**
     * A new link for the user, valid for `$lifetime` from `$now`.
     *
     * @param string $token the secret the e-mail carries; only its hash is kept
     */
    public static function issue(Uuid $userId, TokenPurpose $purpose, #[\SensitiveParameter] string $token, \DateTimeImmutable $now, \DateInterval $lifetime): self
    {
        return new self($userId, $purpose, self::hash($token), $now->add($lifetime), $now);
    }

    public static function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $now < $this->expiresAt;
    }

    /** Spent: by the person who followed it, or because a newer link replaced it. */
    public function use(\DateTimeImmutable $at): void
    {
        $this->usedAt ??= $at;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function purpose(): TokenPurpose
    {
        return $this->purpose;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }
}
