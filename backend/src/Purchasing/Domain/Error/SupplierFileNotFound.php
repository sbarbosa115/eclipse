<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** A file attached to another invoice, or another company's, answers the same. */
final class SupplierFileNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('attachment_not_found', 'Attachment not found.');
    }
}
