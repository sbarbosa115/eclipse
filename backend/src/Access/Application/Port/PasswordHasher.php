<?php

namespace App\Access\Application\Port;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;
}
