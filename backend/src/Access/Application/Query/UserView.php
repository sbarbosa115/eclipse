<?php

namespace App\Access\Application\Query;

final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $email,
        public string $name,
        public string $role,
        public string $status,
        public bool $canSignIn,
        public ?string $passwordHash,
    ) {
    }
}
