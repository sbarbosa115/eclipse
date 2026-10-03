<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** One resolution per company in stage 1 (§9 Q12): edit it instead of adding another. */
final class ResolutionExists extends Conflict
{
    public function __construct()
    {
        parent::__construct('resolution_exists', 'The company already has an invoicing resolution: edit it.');
    }
}
