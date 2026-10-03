<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class UserNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('user_not_found', 'User not found.');
    }
}
