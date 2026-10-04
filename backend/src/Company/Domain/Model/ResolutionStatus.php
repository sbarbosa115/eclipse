<?php

namespace App\Company\Domain\Model;

/** Where the invoicing resolution stands on a given day (§4.1). */
enum ResolutionStatus: string
{
    case NotYetValid = 'not_yet_valid';
    case Active = 'active';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
}
