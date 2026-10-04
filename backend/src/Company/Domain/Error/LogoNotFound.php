<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class LogoNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('logo_not_found', 'The company has no logo.');
    }
}
