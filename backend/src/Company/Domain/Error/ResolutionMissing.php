<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** The company has not set up its invoicing resolution yet, so no sales invoice can be numbered. */
final class ResolutionMissing extends Refused
{
    public function __construct()
    {
        parent::__construct('resolution_missing', 'The company has no invoicing resolution: set it up in Configuración.');
    }
}
