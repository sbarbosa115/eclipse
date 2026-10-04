<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Only someone invited and not yet signed up accepts an invitation. */
final class NotAnInvitation extends Conflict
{
    public function __construct()
    {
        parent::__construct('not_an_invitation', 'This user has already accepted their invitation.');
    }
}
