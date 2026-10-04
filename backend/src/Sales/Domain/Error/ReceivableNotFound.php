<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class ReceivableNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('receivable_not_found', 'Receivable not found.');
    }
}
