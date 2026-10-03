<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class CompanyNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('company_not_found', 'Company not found.');
    }
}
