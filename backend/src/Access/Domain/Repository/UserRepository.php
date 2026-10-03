<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\User;
use Symfony\Component\Uid\Uuid;

interface UserRepository
{
    /** @throws \App\Access\Domain\Error\UserNotFound */
    public function get(Uuid $companyId, Uuid $id): User;

    public function findByEmail(string $email): ?User;

    public function add(User $user): void;
}
