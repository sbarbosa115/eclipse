<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A voided payment stays voided; its number is not reused (§4.12). */
final class PaymentAlreadyVoided extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_voided', 'The payment is already voided.');
    }
}
