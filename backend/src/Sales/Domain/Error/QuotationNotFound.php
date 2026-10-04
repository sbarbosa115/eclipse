<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** No quotation with this id in the company (another company's id is "not found" too). */
final class QuotationNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('quotation_not_found', 'Quotation not found.');
    }
}
