<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A company keeps at least one active owner: the last one is neither demoted nor deactivated. */
final class LastOwner extends Conflict
{
    public function __construct()
    {
        parent::__construct('last_owner', 'The company must keep at least one active owner.');
    }
}
