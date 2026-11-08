<?php

namespace App\Ledger\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** The whole exógena concept table: PUT replaces it. */
final class ExogenaConceptsInput
{
    /** @var list<ExogenaConceptInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 500)]
    public array $items = [];
}
