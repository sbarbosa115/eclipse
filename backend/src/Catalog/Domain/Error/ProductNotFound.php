<?php

namespace App\Catalog\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class ProductNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('product_not_found', 'Product not found.');
    }
}
