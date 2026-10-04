<?php

namespace App\Access\Application\Query;

final readonly class InvitationView
{
    public function __construct(
        public string $email,
        public string $companyName,
        public string $role,
    ) {
    }
}
