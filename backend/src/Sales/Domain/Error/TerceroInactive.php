<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** An inactive tercero takes no new invoice (§4.2: deactivated, not deleted). */
final class TerceroInactive extends Refused
{
    public function __construct()
    {
        parent::__construct('tercero_inactive', 'The client is inactive.');
    }
}
