<?php

namespace App\Access\Application\Command;

final readonly class ResetPassword
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
