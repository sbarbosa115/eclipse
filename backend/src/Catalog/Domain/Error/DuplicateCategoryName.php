<?php

namespace App\Catalog\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

final class DuplicateCategoryName extends InvalidValue
{
    public function __construct()
    {
        parent::__construct('name', 'A category with this name already exists.');
    }
}
