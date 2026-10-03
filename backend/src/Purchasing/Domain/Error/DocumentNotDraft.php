<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Documents are immutable once emitted (§4.6): change them through a void. */
final class DocumentNotDraft extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_not_draft', 'Only a draft can be changed.');
    }
}
