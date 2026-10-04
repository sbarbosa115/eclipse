<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** §9 Q11: any date after the fecha de bloqueo and not in the future. */
final class IssueDateInFuture extends Refused
{
    public function __construct()
    {
        parent::__construct('issue_date_in_future', 'The invoice date cannot be in the future.');
    }
}
