<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\TooLarge;

/** The supplier's PDF or XML is 10 MB at most. */
final class SupplierFileTooLarge extends TooLarge
{
    public function __construct()
    {
        parent::__construct('attachment_too_large', 'The supplier\'s file is larger than 10 MB.');
    }
}
