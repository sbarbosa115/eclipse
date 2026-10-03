<?php

namespace App\Company\UI\Http\Controller;

use App\Company\Domain\Error\CompanyEditNotAllowed;
use App\Shared\UI\Http\Security\SignedInUser;

/** Who may change the company's settings: the owner (§8). */
trait EditsCompany
{
    private function requireOwner(SignedInUser $user): void
    {
        if ('owner' !== $user->role()) {
            throw new CompanyEditNotAllowed();
        }
    }
}
