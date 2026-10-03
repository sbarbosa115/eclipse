<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** Nothing to post without a line. */
final class DocumentHasNoLines extends Refused
{
    public function __construct()
    {
        parent::__construct('document_has_no_lines', 'An invoice needs at least one line to be emitted.');
    }
}
