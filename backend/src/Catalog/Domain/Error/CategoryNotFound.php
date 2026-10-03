<?php

namespace App\Catalog\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class CategoryNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('category_not_found', 'Category not found.');
    }
}
