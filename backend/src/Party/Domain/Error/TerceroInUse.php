<?php

namespace App\Party\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A document names the tercero: it can be deactivated, never deleted. */
final class TerceroInUse extends Conflict
{
    public function __construct()
    {
        parent::__construct('tercero_in_use', 'A document references this tercero: deactivate it instead of deleting it.');
    }
}
