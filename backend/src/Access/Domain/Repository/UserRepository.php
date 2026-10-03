<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\User;
use Symfony\Component\Uid\Uuid;

interface UserRepository
{
    /** @throws \App\Access\Domain\Error\UserNotFound */
    public function get(Uuid $companyId, Uuid $id): User;

    /** Any company's user, for a link whose token named them. */
    public function getById(Uuid $id): User;

    public function findByEmail(string $email): ?User;

    /** @return list<User> the company's users by name, then e-mail */
    public function ofCompany(Uuid $companyId): array;

    public function countActiveOwners(Uuid $companyId): int;

    public function add(User $user): void;
}
