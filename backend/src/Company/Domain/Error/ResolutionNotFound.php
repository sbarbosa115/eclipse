<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class ResolutionNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('resolution_not_found', 'The company has no invoicing resolution.');
    }
}
