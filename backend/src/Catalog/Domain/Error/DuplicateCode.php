<?php

namespace App\Catalog\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** Another product or service of the company has this código (§4.3). */
final class DuplicateCode extends InvalidValue
{
    public function __construct()
    {
        parent::__construct('code', 'A product or service with this code already exists.');
    }
}
