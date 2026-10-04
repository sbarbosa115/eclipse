<?php

namespace App\Access\Application\Command;

/**
 * Self-signup (§9 Q24): creates the company, provisions it, and makes the person its owner.
 */
final readonly class SignUp
{
    public function __construct(
        public string $companyName,
        public string $nit,
        public string $ownerName,
        public string $email,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
