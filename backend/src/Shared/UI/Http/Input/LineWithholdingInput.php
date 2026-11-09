<?php

namespace App\Shared\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** One retención chosen for a document line (observations PRD §4.2): a withholding tax, at most one per kind. */
final class LineWithholdingInput
{
    #[Assert\NotBlank(message: 'Choose the retención.')]
    #[Assert\Uuid]
    public ?string $taxId = null;
}
