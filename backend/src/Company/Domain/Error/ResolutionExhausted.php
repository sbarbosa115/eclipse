<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** Every number up to hasta has been used. */
final class ResolutionExhausted extends Refused
{
    public function __construct()
    {
        parent::__construct('resolution_exhausted', 'The invoicing resolution has no numbers left.');
    }
}
