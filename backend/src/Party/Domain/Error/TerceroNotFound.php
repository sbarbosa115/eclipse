<?php

namespace App\Party\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class TerceroNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('tercero_not_found', 'Tercero not found.');
    }
}
