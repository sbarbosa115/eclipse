<?php

namespace App\Access\Application\Query;

use App\Access\Domain\Model\AccessToken;
use App\Access\Domain\Model\TokenPurpose;
use App\Access\Domain\Model\User;
use App\Access\Domain\Model\UserStatus;
use App\Access\Domain\Repository\AccessTokenRepository;
use App\Access\Domain\Repository\UserRepository;
use Symfony\Component\Uid\Uuid;

/** The company's users for the owner's list: two queries, whatever the number of users. */
final class UserDirectory
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccessTokenRepository $tokens,
    ) {
    }

    /** @return list<UserListItem> */
    public function ofCompany(Uuid $companyId): array
    {
        $users = $this->users->ofCompany($companyId);
        $invited = array_values(array_map(static fn (User $u) => $u->id(), array_filter($users, static fn (User $u) => UserStatus::Invited === $u->status())));
        $expiry = [];
        foreach ($this->tokens->unusedOfUsers($invited, TokenPurpose::Invitation) as $link) {
            $expiry[$link->userId()->toRfc4122()] = self::later($expiry[$link->userId()->toRfc4122()] ?? null, $link);
        }

        return array_map(static fn (User $u) => self::item($u, $expiry[$u->id()->toRfc4122()] ?? null), $users);
    }

    public function get(Uuid $companyId, Uuid $userId): UserListItem
    {
        $user = $this->users->get($companyId, $userId);
        $expiry = null;
        if (UserStatus::Invited === $user->status()) {
            foreach ($this->tokens->unusedOf($user->id(), TokenPurpose::Invitation) as $link) {
                $expiry = self::later($expiry, $link);
            }
        }

        return self::item($user, $expiry);
    }

    private static function later(?\DateTimeImmutable $current, AccessToken $link): \DateTimeImmutable
    {
        return null === $current || $link->expiresAt() > $current ? $link->expiresAt() : $current;
    }

    private static function item(User $u, ?\DateTimeImmutable $invitationExpiresAt): UserListItem
    {
        return new UserListItem(
            $u->id()->toRfc4122(),
            $u->email(),
            $u->name(),
            $u->role()->value,
            $u->status()->value,
            $u->createdAt(),
            $u->lastSignInAt(),
            UserStatus::Invited === $u->status() ? $invitationExpiresAt : null,
        );
    }
}
