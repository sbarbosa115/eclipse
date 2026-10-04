<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A voided receipt stays voided; its number is not reused (§4.12). */
final class ReceiptAlreadyVoided extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_voided', 'The receipt is already voided.');
    }
}
