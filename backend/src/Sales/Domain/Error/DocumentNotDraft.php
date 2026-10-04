<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** §4.6: only drafts are editable; an emitted document changes through a void. */
final class DocumentNotDraft extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_not_draft', 'Only a draft can be changed or emitted.');
    }
}
