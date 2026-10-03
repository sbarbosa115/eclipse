<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\AccessToken;

interface AccessTokenRepository
{
    public function findByHash(string $tokenHash): ?AccessToken;

    public function add(AccessToken $token): void;
}
