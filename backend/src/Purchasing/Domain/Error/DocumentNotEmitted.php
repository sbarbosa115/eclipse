<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A draft has no entry to reverse and owes nothing. */
final class DocumentNotEmitted extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_not_emitted', 'Only an emitted invoice can be voided or paid.');
    }
}
