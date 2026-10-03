<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** The invoice's date is outside the resolution's validity (§4.1): no authorised number may be given. */
final class ResolutionInactive extends Refused
{
    public function __construct()
    {
        parent::__construct('resolution_inactive', 'The invoicing resolution is not valid on the invoice date.');
    }
}
