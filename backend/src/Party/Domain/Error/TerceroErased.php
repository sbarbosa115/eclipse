<?php

namespace App\Party\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** The personal data was erased on request (Ley 1581): there is nothing left to edit or bring back. */
final class TerceroErased extends Refused
{
    public function __construct()
    {
        parent::__construct('tercero_erased', 'The personal data of this tercero was erased.');
    }
}
