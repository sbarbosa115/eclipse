<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class NumberingSeriesNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('numbering_series_not_found', 'There is no such numbering series.');
    }
}
