<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** The fecha de bloqueo closes past periods only. */
final class LockDateInFuture extends Refused
{
    public function __construct()
    {
        parent::__construct('lock_date_in_future', 'The books cannot be locked beyond today.');
    }
}
