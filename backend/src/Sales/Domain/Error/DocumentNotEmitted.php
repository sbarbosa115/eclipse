<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** The action needs an emitted, not voided, invoice (void, send, collect). */
final class DocumentNotEmitted extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_not_emitted', 'The invoice is a draft or was voided.');
    }
}
