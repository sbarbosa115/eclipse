<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Deactivating yourself would end your own session halfway through; another owner does it. */
final class CannotDeactivateYourself extends Conflict
{
    public function __construct()
    {
        parent::__construct('cannot_deactivate_yourself', 'You cannot deactivate yourself.');
    }
}
