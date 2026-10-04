<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\AccessToken;
use App\Access\Domain\Model\TokenPurpose;
use Symfony\Component\Uid\Uuid;

interface AccessTokenRepository
{
    public function findByHash(string $tokenHash): ?AccessToken;

    /** @return list<AccessToken> the user's links for this purpose that nobody used yet (expired ones included) */
    public function unusedOf(Uuid $userId, TokenPurpose $purpose): array;

    /**
     * @param list<Uuid> $userIds
     *
     * @return list<AccessToken> the unused links of these users for this purpose, in one query
     */
    public function unusedOfUsers(array $userIds, TokenPurpose $purpose): array;

    public function add(AccessToken $token): void;
}
