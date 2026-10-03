<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** The company's profile, resolution and numbering are the owner's to change (§8); the other roles read them. */
final class CompanyEditNotAllowed extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('forbidden', 'Only the owner changes the company settings.');
    }
}
